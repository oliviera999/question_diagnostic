<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Action de nettoyage global de TOUS les doublons du site
 * 
 * Ce fichier gère le nettoyage automatique de tous les doublons inutilisés
 * en une seule opération, avec traitement par lots pour éviter les timeouts.
 * 
 * Modes de fonctionnement :
 * - preview : Affiche les statistiques et la page de confirmation
 * - download_csv : Génère le CSV de la liste des questions à supprimer
 * - execute : programme le nettoyage en tâche de fond (tâche adhoc cleanup_duplicates_task)
 * - complete : Affiche le résumé final
 *
 * @package    local_question_diagnostic
 * @copyright  2025 Question Diagnostic Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/../classes/question_analyzer.php');

use local_question_diagnostic\question_analyzer;

require_login();

if (!is_siteadmin()) {
    throw new \moodle_exception('accessdenied', 'admin');
}

require_sesskey();

// Définir le contexte de la page
$context = context_system::instance();
$PAGE->set_context($context);

// Détecter le mode
$preview = optional_param('preview', 0, PARAM_INT);
$download_csv = optional_param('download_csv', 0, PARAM_INT);
$execute = optional_param('execute', 0, PARAM_INT);

if ($download_csv) {
    // MODE TÉLÉCHARGEMENT CSV
    handle_csv_download();
    exit;
} else if ($execute) {
    // v1.15.0 : MODE EXÉCUTION EN TÂCHE DE FOND (plus de requête HTTP qui enchaîne les lots).
    queue_cleanup_task();
} else if ($preview) {
    // MODE PRÉVISUALISATION
    show_preview_page();
} else {
    // Rediriger vers preview par défaut
    redirect(new moodle_url('/local/question_diagnostic/actions/cleanup_all_duplicates.php', 
             ['preview' => 1, 'sesskey' => sesskey()]));
}

/**
 * Affiche la page de prévisualisation avec statistiques et confirmation
 */
function show_preview_page() {
    global $OUTPUT, $PAGE;
    
    $PAGE->set_url(new moodle_url('/local/question_diagnostic/actions/cleanup_all_duplicates.php', 
                   ['preview' => 1, 'sesskey' => sesskey()]));
    $PAGE->set_title(get_string('cleanup_all_preview_title', 'local_question_diagnostic'));
    $PAGE->set_heading(local_question_diagnostic_get_heading_with_version(
        get_string('cleanup_all_preview_title', 'local_question_diagnostic')
    ));
    $PAGE->set_pagelayout('admin');
    
    // Charger les statistiques de prévisualisation
    echo $OUTPUT->header();
    
    echo html_writer::start_tag('div', ['id' => 'loading-preview', 'style' => 'text-align: center; padding: 40px;']);
    echo html_writer::tag('h2', '⏳ Analyse en cours...');
    echo html_writer::tag('p', 'Calcul des statistiques pour tous les groupes de doublons...', 
                          ['style' => 'font-size: 16px;']);
    echo html_writer::end_tag('div');
    
    try {
        $stats = question_analyzer::get_cleanup_preview_stats();
    } catch (Exception $e) {
        echo html_writer::start_tag('script');
        echo "document.getElementById('loading-preview').style.display = 'none';";
        echo html_writer::end_tag('script');
        
        echo html_writer::start_tag('div', ['class' => 'alert alert-danger']);
        echo html_writer::tag('h3', '⚠️ Erreur');
        echo 'Impossible de charger les statistiques : ' . $e->getMessage();
        echo html_writer::end_tag('div');
        echo $OUTPUT->footer();
        exit;
    }
    
    // Masquer le loading
    echo html_writer::start_tag('script');
    echo "document.getElementById('loading-preview').style.display = 'none';";
    echo html_writer::end_tag('script');
    
    // Si aucun doublon à nettoyer
    if ($stats->total_questions_to_delete == 0) {
        echo html_writer::start_tag('div', ['class' => 'alert alert-success', 
                                            'style' => 'margin: 30px 0; padding: 40px; text-align: center;']);
        echo html_writer::tag('h2', '✅ ' . get_string('cleanup_all_no_duplicates', 'local_question_diagnostic'), 
                              ['style' => 'margin-top: 0; color: #28a745;']);
        echo html_writer::tag('p', get_string('cleanup_all_no_duplicates_desc', 'local_question_diagnostic'), 
                              ['style' => 'font-size: 16px;']);
        echo html_writer::end_tag('div');
        
        echo html_writer::start_tag('div', ['style' => 'text-align: center; margin-top: 30px;']);
        echo html_writer::link(
            new moodle_url('/local/question_diagnostic/questions_cleanup.php', ['loadstats' => 1]),
            '← ' . get_string('backtoquestions', 'local_question_diagnostic'),
            ['class' => 'btn btn-secondary btn-lg']
        );
        echo html_writer::end_tag('div');
        
        echo $OUTPUT->footer();
        exit;
    }
    
    // Afficher le titre et la description
    echo html_writer::tag('h2', '🧹 ' . get_string('cleanup_all_preview_title', 'local_question_diagnostic'));
    echo html_writer::tag('p', get_string('cleanup_all_preview_desc', 'local_question_diagnostic'), 
                          ['style' => 'font-size: 16px; margin-bottom: 30px;']);
    
    // 🆕 v1.9.53 : Message d'information sur l'optimisation
    echo html_writer::start_tag('div', ['class' => 'alert alert-info', 'style' => 'margin-bottom: 20px; padding: 15px;']);
    echo '⚡ <strong>Mode optimisé :</strong> Seuls les groupes contenant au moins 1 version <strong>supprimable</strong> sont analysés. ';
    echo 'Les groupes où toutes les versions sont utilisées ou protégées (questions cachées, uniques) sont automatiquement exclus du nettoyage.';
    echo html_writer::end_tag('div');
    
    // Statistiques principales en cartes
    echo html_writer::start_tag('div', ['class' => 'qd-dashboard', 'style' => 'margin: 30px 0;']);
    
    // Carte 1 : Groupes de doublons
    echo html_writer::start_tag('div', ['class' => 'qd-card']);
    echo html_writer::tag('div', get_string('cleanup_all_stats_groups', 'local_question_diagnostic'), 
                          ['class' => 'qd-card-title']);
    echo html_writer::tag('div', $stats->total_groups, ['class' => 'qd-card-value']);
    echo html_writer::tag('div', 'groupes à traiter', ['class' => 'qd-card-subtitle']);
    echo html_writer::end_tag('div');
    
    // Carte 2 : Questions à supprimer
    echo html_writer::start_tag('div', ['class' => 'qd-card danger']);
    echo html_writer::tag('div', get_string('cleanup_all_stats_to_delete', 'local_question_diagnostic'), 
                          ['class' => 'qd-card-title']);
    echo html_writer::tag('div', $stats->total_questions_to_delete, ['class' => 'qd-card-value']);
    echo html_writer::tag('div', 'versions inutilisées', ['class' => 'qd-card-subtitle']);
    echo html_writer::end_tag('div');
    
    // Carte 3 : Questions à conserver
    echo html_writer::start_tag('div', ['class' => 'qd-card success']);
    echo html_writer::tag('div', get_string('cleanup_all_stats_to_keep', 'local_question_diagnostic'), 
                          ['class' => 'qd-card-title']);
    echo html_writer::tag('div', $stats->total_questions_to_keep, ['class' => 'qd-card-value']);
    echo html_writer::tag('div', 'versions conservées', ['class' => 'qd-card-subtitle']);
    echo html_writer::end_tag('div');
    
    // Carte 4 : Temps estimé
    echo html_writer::start_tag('div', ['class' => 'qd-card']);
    echo html_writer::tag('div', get_string('cleanup_all_estimated_time', 'local_question_diagnostic'), 
                          ['class' => 'qd-card-title']);
    $time_display = format_time_estimate($stats->estimated_time_seconds);
    echo html_writer::tag('div', $time_display, ['class' => 'qd-card-value', 'style' => 'font-size: 28px;']);
    echo html_writer::tag('div', $stats->estimated_batches . ' lots de traitement', ['class' => 'qd-card-subtitle']);
    echo html_writer::end_tag('div');
    
    echo html_writer::end_tag('div'); // fin dashboard
    
    // Répartition par type de question
    if (!empty($stats->by_type)) {
        echo html_writer::tag('h3', '📊 ' . get_string('cleanup_all_by_type_title', 'local_question_diagnostic'), 
                              ['style' => 'margin-top: 30px;']);
        
        echo html_writer::start_tag('table', ['class' => 'generaltable', 'style' => 'width: 100%;']);
        echo html_writer::start_tag('thead');
        echo html_writer::start_tag('tr');
        echo html_writer::tag('th', get_string('question_type', 'local_question_diagnostic'));
        echo html_writer::tag('th', get_string('cleanup_all_stats_to_delete', 'local_question_diagnostic'), 
                              ['style' => 'text-align: center;']);
        echo html_writer::tag('th', get_string('cleanup_all_stats_to_keep', 'local_question_diagnostic'), 
                              ['style' => 'text-align: center;']);
        echo html_writer::tag('th', 'Total', ['style' => 'text-align: center;']);
        echo html_writer::end_tag('tr');
        echo html_writer::end_tag('thead');
        
        echo html_writer::start_tag('tbody');
        foreach ($stats->by_type as $qtype => $counts) {
            $total = $counts['to_delete'] + $counts['to_keep'];
            echo html_writer::start_tag('tr');
            echo html_writer::tag('td', html_writer::tag('strong', ucfirst($qtype)));
            echo html_writer::tag('td', $counts['to_delete'], 
                                  ['style' => 'text-align: center; color: #d9534f; font-weight: bold;']);
            echo html_writer::tag('td', $counts['to_keep'], 
                                  ['style' => 'text-align: center; color: #5cb85c;']);
            echo html_writer::tag('td', $total, ['style' => 'text-align: center;']);
            echo html_writer::end_tag('tr');
        }
        echo html_writer::end_tag('tbody');
        echo html_writer::end_tag('table');
    }
    
    // Règles de sécurité
    echo html_writer::start_tag('div', ['class' => 'alert alert-info', 'style' => 'margin: 30px 0;']);
    echo html_writer::tag('h4', '🔒 ' . get_string('cleanup_all_security_rules', 'local_question_diagnostic'), 
                          ['style' => 'margin-top: 0;']);
    echo html_writer::start_tag('ul');
    echo html_writer::tag('li', '✅ Les versions utilisées dans des quiz seront CONSERVÉES');
    echo html_writer::tag('li', '✅ Seules les versions inutilisées seront supprimées');
    echo html_writer::tag('li', '✅ Au moins 1 version sera toujours conservée par groupe (même si inutilisée)');
    echo html_writer::tag('li', '🌐 <strong>Logique de conservation intelligente :</strong> si aucune version n\'est utilisée, la version conservée sera celle du contexte le plus large (site > catégorie > cours > module), puis la plus ancienne en cas d\'égalité');
    echo html_writer::tag('li', '✅ ' . get_string('cleanup_all_background', 'local_question_diagnostic'));
    echo html_writer::end_tag('ul');
    echo html_writer::end_tag('div');
    
    // Avertissement sur l'irréversibilité
    echo html_writer::start_tag('div', ['class' => 'alert alert-danger', 'style' => 'margin: 30px 0;']);
    echo html_writer::tag('h3', 
        get_string('cleanup_all_warning', 'local_question_diagnostic', $stats->total_questions_to_delete), 
        ['style' => 'margin-top: 0; color: #d9534f;']
    );
    echo html_writer::tag('p', 'Une fois supprimées, ces questions ne pourront PAS être récupérées. ' .
                                'Assurez-vous d\'avoir une sauvegarde de votre base de données avant de continuer.');
    echo html_writer::end_tag('div');
    
    // Boutons d'action
    echo html_writer::start_tag('div', ['style' => 'text-align: center; margin: 40px 0;']);
    
    // Bouton télécharger CSV
    $csv_url = new moodle_url('/local/question_diagnostic/actions/cleanup_all_duplicates.php', [
        'download_csv' => 1,
        'sesskey' => sesskey()
    ]);
    echo html_writer::link(
        $csv_url,
        '📥 ' . get_string('cleanup_all_download_csv', 'local_question_diagnostic'),
        ['class' => 'btn btn-info btn-lg', 'style' => 'margin-right: 10px;']
    );
    
    echo '<br><br>';
    
    // Bouton confirmer
    $execute_url = new moodle_url('/local/question_diagnostic/actions/cleanup_all_duplicates.php', ['execute' => 1]);
    echo $OUTPUT->single_button(
        $execute_url,
        get_string('cleanup_all_confirm_button', 'local_question_diagnostic'),
        'post',
        ['type' => single_button::BUTTON_DANGER]
    );
    
    // Bouton annuler
    $cancel_url = new moodle_url('/local/question_diagnostic/questions_cleanup.php', ['loadstats' => 1]);
    echo html_writer::link(
        $cancel_url,
        '✗ ' . get_string('cancel', 'core'),
        ['class' => 'btn btn-secondary btn-lg']
    );
    
    echo html_writer::end_tag('div');
    
    echo $OUTPUT->footer();
}



/**
 * Programme le nettoyage global en tâche de fond et affiche la confirmation.
 */
function queue_cleanup_task() {
    global $OUTPUT, $PAGE, $USER;

    if (!data_submitted()) {
        throw new \moodle_exception('invalidrequest');
    }
    $task = new \local_question_diagnostic\task\cleanup_duplicates_task();
    $task->set_userid($USER->id);
    $task->set_custom_data(['userid' => (int)$USER->id]);
    \core\task\manager::queue_adhoc_task($task, true);
    \local_question_diagnostic\audit_logger::log_action('cleanup_all_duplicates_queued', []);

    redirect(
        new moodle_url('/local/question_diagnostic/questions_cleanup.php', ['loadstats' => 1]),
        get_string('cleanup_all_queued', 'local_question_diagnostic'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

/**
 * Génère et envoie le fichier CSV de la liste des questions à supprimer
 */
function handle_csv_download() {
    // Charger les statistiques
    $stats = question_analyzer::get_cleanup_preview_stats();
    
    // Générer le CSV
    $csv_content = "ID,Nom,Type,Date de création,Action\n";
    
    foreach ($stats->questions_list as $q) {
        $csv_content .= sprintf(
            "%d,\"%s\",\"%s\",\"%s\",\"%s\"\n",
            $q->id,
            str_replace('"', '""', $q->name),
            $q->qtype,
            userdate($q->timecreated, '%d/%m/%Y %H:%M'),
            $q->action
        );
    }
    
    // Envoyer les headers pour téléchargement
    $filename = 'cleanup_all_duplicates_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    // Ajouter le BOM UTF-8 pour Excel
    echo "\xEF\xBB\xBF";
    echo $csv_content;
}

/**
 * Formate le temps estimé de manière lisible
 * 
 * @param int $seconds Nombre de secondes
 * @return string Temps formaté
 */
function format_time_estimate($seconds) {
    if ($seconds < 60) {
        return round($seconds) . 's';
    } else if ($seconds < 3600) {
        $minutes = round($seconds / 60);
        return $minutes . ' min';
    } else {
        $hours = floor($seconds / 3600);
        $minutes = round(($seconds % 3600) / 60);
        return $hours . 'h' . ($minutes > 0 ? ' ' . $minutes . 'min' : '');
    }
}

