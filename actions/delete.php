<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/../classes/category_manager.php');
require_once(__DIR__ . '/../classes/question_analyzer.php');

use local_question_diagnostic\category_manager;
use local_question_diagnostic\question_analyzer;

require_login();
require_sesskey();

if (!is_siteadmin()) {
    print_error('accessdenied', 'admin');
}

// 🔧 SÉCURITÉ v1.9.27 : Limite stricte sur les opérations en masse (mode "one-shot" historique).
// 🆕 v1.12.x : Au-delà de cette limite, on bascule automatiquement en traitement par lots côté serveur (job en session).
define('MAX_BULK_DELETE_CATEGORIES', 100);

// 🧱 Sécurité additionnelle : limite absolue d'IDs acceptés pour démarrer un job (évite les payloads/sessions énormes).
define('MAX_BULK_DELETE_CATEGORIES_JOB', 5000);

// Taille d'un lot côté serveur.
define('BATCH_SIZE', 20);

// ⚠️ FIX: Accepter les paramètres POST et GET (POST pour éviter Request-URI Too Long)
$categoryid = optional_param('id', 0, PARAM_INT);
$categoryids = optional_param('ids', '', PARAM_TEXT);
$confirm = optional_param('confirm', 0, PARAM_INT);
// Option avancée : ignorer la protection "catégorie avec description".
$bypass_info = optional_param('bypass_info', 0, PARAM_INT);
// Mode job batché.
$jobid = optional_param('jobid', '', PARAM_ALPHANUMEXT);
$run = optional_param('run', 0, PARAM_INT);
$cancel = optional_param('cancel', 0, PARAM_INT);
// 🆕 v1.9.44 : URL de retour hiérarchique
$returnurl = local_question_diagnostic_get_parent_url('actions/delete.php');

/**
 * Initialise un job de suppression groupée en session.
 *
 * @param array $ids
 * @param int $bypass_info
 * @param moodle_url $returnurl
 * @return string jobid
 */
function local_question_diagnostic_start_bulk_delete_job(array $ids, int $bypass_info, moodle_url $returnurl): string {
    // Normaliser : uniques, ints > 0.
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function($v) {
        return $v > 0;
    })));

    if (empty($ids)) {
        throw new moodle_exception('invalidparameter', 'core');
    }
    if (count($ids) > MAX_BULK_DELETE_CATEGORIES_JOB) {
        throw new moodle_exception('invalidparameter', 'core', '', 'Too many IDs');
    }

    $jobid = bin2hex(random_bytes(16));
    if (!isset($_SESSION['qd_bulk_delete_jobs']) || !is_array($_SESSION['qd_bulk_delete_jobs'])) {
        $_SESSION['qd_bulk_delete_jobs'] = [];
    }

    $_SESSION['qd_bulk_delete_jobs'][$jobid] = [
        'ids' => $ids,
        'total' => count($ids),
        'index' => 0,
        'success' => 0,
        'errors' => 0,
        'error_samples' => [],
        'startedat' => time(),
        'bypass_info' => (int)$bypass_info,
        'returnurl' => $returnurl->out(false),
    ];

    return $jobid;
}

/**
 * Récupère un job depuis la session.
 *
 * @param string $jobid
 * @return array
 */
function local_question_diagnostic_get_bulk_delete_job(string $jobid): array {
    if (empty($jobid) || empty($_SESSION['qd_bulk_delete_jobs']) || !is_array($_SESSION['qd_bulk_delete_jobs'])) {
        throw new moodle_exception('invalidparameter', 'core');
    }
    if (!isset($_SESSION['qd_bulk_delete_jobs'][$jobid]) || !is_array($_SESSION['qd_bulk_delete_jobs'][$jobid])) {
        throw new moodle_exception('invalidparameter', 'core');
    }
    return $_SESSION['qd_bulk_delete_jobs'][$jobid];
}

/**
 * Sauvegarde un job en session.
 *
 * @param string $jobid
 * @param array $job
 * @return void
 */
function local_question_diagnostic_set_bulk_delete_job(string $jobid, array $job): void {
    if (!isset($_SESSION['qd_bulk_delete_jobs']) || !is_array($_SESSION['qd_bulk_delete_jobs'])) {
        $_SESSION['qd_bulk_delete_jobs'] = [];
    }
    $_SESSION['qd_bulk_delete_jobs'][$jobid] = $job;
}

/**
 * Supprime un job en session.
 *
 * @param string $jobid
 * @return void
 */
function local_question_diagnostic_clear_bulk_delete_job(string $jobid): void {
    if (!empty($_SESSION['qd_bulk_delete_jobs']) && is_array($_SESSION['qd_bulk_delete_jobs'])) {
        unset($_SESSION['qd_bulk_delete_jobs'][$jobid]);
    }
}

// Annulation d'un job en cours.
if (!empty($cancel) && !empty($jobid)) {
    local_question_diagnostic_clear_bulk_delete_job($jobid);
    redirect($returnurl, 'Opération annulée.', null, \core\output\notification::NOTIFY_INFO);
}

// Exécution d'un job en cours (traitement par lots côté serveur).
if (!empty($run) && !empty($jobid)) {
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url(new moodle_url('/local/question_diagnostic/actions/delete.php', [
        'jobid' => $jobid,
        'run' => 1,
        'sesskey' => sesskey()
    ]));
    $PAGE->set_title('Suppression en masse - progression');

    $job = local_question_diagnostic_get_bulk_delete_job($jobid);

    $total = (int)($job['total'] ?? 0);
    $index = (int)($job['index'] ?? 0);
    $ids = $job['ids'] ?? [];
    $bypassinfo = !empty($job['bypass_info']);

    echo $OUTPUT->header();
    echo local_question_diagnostic_render_version_badge();
    echo $OUTPUT->heading('🗑️ Suppression en masse — progression');

    if ($total <= 0 || empty($ids)) {
        local_question_diagnostic_clear_bulk_delete_job($jobid);
        echo html_writer::div('Aucun élément à traiter.', 'alert alert-info');
        echo $OUTPUT->footer();
        exit;
    }

    // Traitement du lot courant.
    $batchids = array_slice($ids, $index, BATCH_SIZE);
    $processed = 0;
    $batchsuccess = 0;
    $batcherrors = 0;

    echo html_writer::start_div('alert alert-info', ['style' => 'margin-top: 15px;']);
    echo 'Traitement des catégories ' . ($index + 1) . ' à ' . min($index + count($batchids), $total) . ' sur ' . $total . '...';
    echo html_writer::end_div();

    echo html_writer::start_div('', ['style' => 'margin: 15px 0; padding: 12px; background: #f8f9fa; border-radius: 6px;']);
    foreach ($batchids as $id) {
        $processed++;
        $result = category_manager::delete_category((int)$id, false, (bool)$bypassinfo);
        if ($result === true) {
            $batchsuccess++;
            $job['success'] = (int)($job['success'] ?? 0) + 1;
            echo html_writer::div('✅ Catégorie ID ' . (int)$id . ' : supprimée (ou déjà supprimée)', '', ['style' => 'color:#28a745; margin: 4px 0;']);
        } else {
            $batcherrors++;
            $job['errors'] = (int)($job['errors'] ?? 0) + 1;
            $msg = is_string($result) ? $result : 'Erreur inconnue';
            // Conserver un échantillon (éviter d'exploser la session).
            if (empty($job['error_samples']) || count($job['error_samples']) < 50) {
                $job['error_samples'][] = 'Catégorie ' . (int)$id . ' : ' . $msg;
            }
            echo html_writer::div('❌ Catégorie ID ' . (int)$id . ' : ' . s($msg), '', ['style' => 'color:#d9534f; margin: 4px 0;']);
        }
    }
    echo html_writer::end_div();

    // Avancer l'index.
    $job['index'] = $index + $processed;

    // Progress bar.
    $done = min((int)$job['index'], $total);
    $percent = $total > 0 ? round(($done / $total) * 100, 1) : 0;
    echo html_writer::start_div('progress', ['style' => 'height: 26px; margin: 15px 0;']);
    echo html_writer::div($percent . '%', 'progress-bar progress-bar-striped progress-bar-animated', [
        'role' => 'progressbar',
        'style' => 'width:' . $percent . '%;',
        'aria-valuenow' => $percent,
        'aria-valuemin' => '0',
        'aria-valuemax' => '100',
    ]);
    echo html_writer::end_div();

    echo html_writer::start_div('alert alert-success');
    echo '✅ Succès total : ' . (int)($job['success'] ?? 0) . ' — ⚠️ Erreurs : ' . (int)($job['errors'] ?? 0);
    echo html_writer::end_div();

    // Fin ?
    if ((int)$job['index'] >= $total) {
        // Purge cache une seule fois à la fin.
        if ((int)($job['success'] ?? 0) > 0) {
            question_analyzer::purge_all_caches();
        }

        echo html_writer::tag('h3', 'Résumé');
        echo html_writer::start_div('alert alert-info');
        echo 'Suppression en masse terminée.';
        echo html_writer::end_div();

        if (!empty($job['error_samples'])) {
            echo html_writer::start_div('alert alert-warning');
            echo html_writer::tag('strong', 'Échantillon d’erreurs (max 50) :');
            echo html_writer::start_tag('ul', ['style' => 'margin-top: 8px;']);
            foreach ($job['error_samples'] as $line) {
                echo html_writer::tag('li', s($line));
            }
            echo html_writer::end_tag('ul');
            echo html_writer::end_div();
        }

        $back = new moodle_url($job['returnurl'] ?? $returnurl->out(false));
        local_question_diagnostic_clear_bulk_delete_job($jobid);
        echo html_writer::link($back, '← Retour', ['class' => 'btn btn-primary']);
        echo $OUTPUT->footer();
        exit;
    }

    // Sauvegarder et rediriger vers le lot suivant.
    local_question_diagnostic_set_bulk_delete_job($jobid, $job);

    $nexturl = new moodle_url('/local/question_diagnostic/actions/delete.php', [
        'jobid' => $jobid,
        'run' => 1,
        'sesskey' => sesskey(),
    ]);
    $cancelurl = new moodle_url('/local/question_diagnostic/actions/delete.php', [
        'jobid' => $jobid,
        'cancel' => 1,
        'sesskey' => sesskey(),
    ]);

    echo html_writer::start_div('text-center', ['style' => 'margin: 20px 0; display:flex; gap:10px; justify-content:center; flex-wrap:wrap;']);
    echo html_writer::link($nexturl, '➡️ Continuer maintenant', ['class' => 'btn btn-primary btn-lg']);
    echo html_writer::link($cancelurl, '⏹️ Arrêter', ['class' => 'btn btn-secondary btn-lg']);
    echo html_writer::end_div();

    echo html_writer::start_tag('script');
    echo "setTimeout(function() { window.location.href = '" . $nexturl->out(false) . "'; }, 1200);";
    echo html_writer::end_tag('script');

    echo $OUTPUT->footer();
    exit;
}

// Suppression multiple
if ($categoryids) {
    $ids = array_filter(array_map('intval', explode(',', $categoryids)));
    
    // Normaliser.
    $ids = array_values(array_unique(array_filter($ids, function($v) { return $v > 0; })));
    if (count($ids) > MAX_BULK_DELETE_CATEGORIES_JOB) {
        print_error('error', 'local_question_diagnostic', $returnurl,
            'Trop de catégories sélectionnées. Maximum autorisé : ' . MAX_BULK_DELETE_CATEGORIES_JOB);
    }
    
    if ($confirm) {
        // 🆕 Traitement par lots côté serveur (job).
        $jobid = local_question_diagnostic_start_bulk_delete_job($ids, (int)$bypass_info, $returnurl);
        redirect(new moodle_url('/local/question_diagnostic/actions/delete.php', [
            'jobid' => $jobid,
            'run' => 1,
            'sesskey' => sesskey(),
        ]));
    } else {
        // Demander confirmation - Utiliser POST pour éviter Request-URI Too Long
        $PAGE->set_context(context_system::instance());
        $PAGE->set_url(new moodle_url('/local/question_diagnostic/actions/delete.php'));
        $PAGE->set_title('Confirmation de suppression');
        
        echo $OUTPUT->header();
        echo local_question_diagnostic_render_version_badge();
        echo $OUTPUT->heading('⚠️ Confirmation de suppression');
        echo html_writer::tag('p', "Vous êtes sur le point de supprimer <strong>" . count($ids) . " catégorie(s)</strong>.");
        echo html_writer::tag('p', "Cette action est irréversible. Êtes-vous sûr ?");
        echo html_writer::start_div('alert alert-info', ['style' => 'margin-top: 15px;']);
        echo '🧱 Sécurité : la suppression sera exécutée <strong>par lots</strong> ('
            . BATCH_SIZE . ' par page) avec re-vérification avant chaque suppression.';
        echo html_writer::end_div();
        
        echo html_writer::start_tag('div', ['style' => 'margin-top: 20px;']);
        
        // ⚠️ FIX v1.5.5 : Utiliser un formulaire POST au lieu d'un lien GET
        echo html_writer::start_tag('form', [
            'method' => 'post',
            'action' => new moodle_url('/local/question_diagnostic/actions/delete.php'),
            'style' => 'display: inline;'
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'ids', 'value' => $categoryids]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => '1']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::start_tag('div', ['style' => 'margin: 10px 0;']);
        echo html_writer::checkbox('bypass_info', 1, false, 'Supprimer aussi les catégories ayant une description (option avancée)');
        echo html_writer::end_tag('div');
        echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => 'Oui, supprimer', 'class' => 'btn btn-danger']);
        echo html_writer::end_tag('form');
        
        echo ' ';
        echo html_writer::link($returnurl, 'Annuler', ['class' => 'btn btn-secondary']);
        echo html_writer::end_tag('div');
        
        echo $OUTPUT->footer();
        exit;
    }
}

// Suppression simple
if ($categoryid) {
    if ($confirm) {
        $result = category_manager::delete_category($categoryid, false, (bool)$bypass_info);
        
        if ($result === true) {
            // Purger tous les caches après modification
            question_analyzer::purge_all_caches();
            redirect($returnurl, '✅ Catégorie supprimée avec succès.', null, \core\output\notification::NOTIFY_SUCCESS);
        } else {
            redirect($returnurl, "⚠️ Erreur : $result", null, \core\output\notification::NOTIFY_ERROR);
        }
    } else {
        // Demander confirmation - Utiliser POST pour cohérence
        global $DB;
        $category = $DB->get_record('question_categories', ['id' => $categoryid]);
        
        $PAGE->set_context(context_system::instance());
        $PAGE->set_url(new moodle_url('/local/question_diagnostic/actions/delete.php'));
        $PAGE->set_title('Confirmation de suppression');
        
        echo $OUTPUT->header();
        echo local_question_diagnostic_render_version_badge();
        echo $OUTPUT->heading('⚠️ Confirmation de suppression');
        echo html_writer::tag('p', "Vous êtes sur le point de supprimer la catégorie : <strong>" . format_string($category->name) . "</strong> (ID: $categoryid)");
        echo html_writer::tag('p', "Cette action est irréversible. Êtes-vous sûr ?");
        
        echo html_writer::start_tag('div', ['style' => 'margin-top: 20px;']);
        
        // ⚠️ FIX v1.5.5 : Utiliser un formulaire POST pour cohérence
        echo html_writer::start_tag('form', [
            'method' => 'post',
            'action' => new moodle_url('/local/question_diagnostic/actions/delete.php'),
            'style' => 'display: inline;'
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $categoryid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => '1']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::start_tag('div', ['style' => 'margin: 10px 0;']);
        echo html_writer::checkbox('bypass_info', 1, false, 'Supprimer aussi si la catégorie a une description (option avancée)');
        echo html_writer::end_tag('div');
        echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => 'Oui, supprimer', 'class' => 'btn btn-danger']);
        echo html_writer::end_tag('form');
        
        echo ' ';
        echo html_writer::link($returnurl, 'Annuler', ['class' => 'btn btn-secondary']);
        echo html_writer::end_tag('div');
        
        echo $OUTPUT->footer();
        exit;
    }
}

redirect($returnurl);

