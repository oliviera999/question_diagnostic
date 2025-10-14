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
 * Page pour rendre les questions cachées visibles
 *
 * @package    local_question_diagnostic
 * @copyright  2025 Question Diagnostic Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/question_analyzer.php');

use local_question_diagnostic\question_analyzer;

// Charger les bibliothèques Moodle nécessaires.
require_login();

// Vérification stricte : seuls les administrateurs du site peuvent accéder à cette page.
if (!is_siteadmin()) {
    print_error('accessdenied', 'admin', '', 'Vous devez être administrateur du site pour accéder à cet outil.');
    exit;
}

// Définir le contexte de la page (système).
$context = context_system::instance();

// Définir le titre et l'URL de la page.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/question_diagnostic/unhide_questions.php'));
$pagetitle = get_string('unhide_questions_title', 'local_question_diagnostic');
$PAGE->set_title($pagetitle);
$PAGE->set_heading(local_question_diagnostic_get_heading_with_version($pagetitle));
$PAGE->set_pagelayout('report');

// Ajouter les CSS et JavaScript personnalisés
$PAGE->requires->css('/local/question_diagnostic/styles/main.css');
$PAGE->requires->js('/local/question_diagnostic/scripts/main.js', true);

// Traitement de l'action
$action = optional_param('action', '', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_INT);

if ($action === 'unhide_all' && $confirm) {
    require_sesskey();
    
    // Exécuter l'action : Rendre TOUTES les questions cachées visibles
    // 🔧 v1.9.60 : false = inclure TOUTES (même soft delete si l'utilisateur le demande)
    $all_hidden_questions = question_analyzer::get_hidden_questions(false, 0);
    $question_ids = array_map(function($q) { return $q->id; }, $all_hidden_questions);
    
    if (empty($question_ids)) {
        redirect(
            new moodle_url('/local/question_diagnostic/unhide_questions.php'),
            '✅ Aucune question cachée trouvée.',
            null,
            \core\output\notification::NOTIFY_INFO
        );
    }
    
    // Exécuter en masse
    $result = question_analyzer::unhide_questions_batch($question_ids);
    
    // Purger le cache
    question_analyzer::purge_all_caches();
    
    $message = '✅ Opération terminée : ' . $result['success'] . ' question(s) rendues visibles.';
    if ($result['failed'] > 0) {
        $message .= ' ' . $result['failed'] . ' échec(s).';
    }
    
    redirect(
        new moodle_url('/local/question_diagnostic/unhide_questions.php'),
        $message,
        null,
        $result['failed'] > 0 ? \core\output\notification::NOTIFY_WARNING : \core\output\notification::NOTIFY_SUCCESS
    );
}

// ======================================================================
// Section d'en-tête Moodle standard.
echo $OUTPUT->header();

// Afficher le badge de version
echo local_question_diagnostic_render_version_badge();

// Lien retour
echo html_writer::start_tag('div', ['style' => 'margin-bottom: 20px;']);
echo local_question_diagnostic_render_back_link('unhide_questions.php');
echo html_writer::end_tag('div');

// Titre de la page
echo html_writer::tag('h2', '👁️ ' . get_string('unhide_questions_title', 'local_question_diagnostic'));

// Introduction
echo html_writer::start_tag('div', ['class' => 'alert alert-info', 'style' => 'margin: 20px 0;']);
echo html_writer::tag('p', '🔍 Cette page affiche <strong>TOUTES</strong> les questions avec status="hidden" dans question_versions, quelle qu\'en soit la raison (cachée manuellement ou soft delete).');
echo html_writer::tag('p', 'Le bouton ci-dessous rendra <strong>TOUTES ces questions visibles</strong> en changeant leur statut de "hidden" à "ready".');
echo html_writer::end_tag('div');

// Charger TOUTES les questions cachées (y compris soft delete)
// 🔧 v1.9.60 : false = inclure TOUTES, 0 = pas de limite
$all_hidden_questions = question_analyzer::get_hidden_questions(false, 0);
$total_hidden = count($all_hidden_questions);

// 🔧 DEBUG : Afficher un message si aucune question trouvée
if ($total_hidden == 0) {
    echo html_writer::start_tag('div', ['class' => 'alert alert-warning', 'style' => 'margin: 20px 0; padding: 20px;']);
    echo html_writer::tag('strong', '🔍 Debug : ');
    echo 'Aucune question avec status="hidden" trouvée dans la table question_versions. ';
    echo 'Cela peut signifier que toutes vos questions sont visibles (status="ready"), ou qu\'il y a un problème avec la requête SQL.';
    echo html_writer::end_tag('div');
}

// Calculer combien sont utilisées vs non utilisées
$hidden_question_ids = array_map(function($q) { return $q->id; }, $all_hidden_questions);
$usage_map = [];
$manually_hidden = 0; // Cachées manuellement (non utilisées)
$soft_deleted = 0;    // Supprimées (soft delete, utilisées)

if (!empty($hidden_question_ids)) {
    $usage_map = question_analyzer::get_questions_usage_by_ids($hidden_question_ids);
    
    foreach ($all_hidden_questions as $q) {
        $is_used = false;
        if (isset($usage_map[$q->id]) && is_array($usage_map[$q->id])) {
            $quiz_count = isset($usage_map[$q->id]['quiz_count']) ? $usage_map[$q->id]['quiz_count'] : 0;
            $is_used = ($quiz_count > 0);
        }
        
        if ($is_used) {
            $soft_deleted++;
        } else {
            $manually_hidden++;
        }
    }
}

// 🔧 DEBUG : Afficher un résumé détaillé pour vérifier
echo html_writer::start_tag('div', ['class' => 'alert alert-light', 'style' => 'margin: 20px 0; font-size: 12px; border-left: 3px solid #0f6cbf;']);
echo html_writer::tag('strong', '🔍 Debug SQL : ');
echo 'Requête exécutée : <code>SELECT DISTINCT questionid FROM mdl_question_versions WHERE status = \'hidden\'</code><br>';
echo '<strong>Résultat :</strong> ' . $total_hidden . ' question(s) trouvée(s)<br>';

// Vérification supplémentaire : Compter directement dans la BDD
try {
    $direct_count = $DB->count_records_sql(
        "SELECT COUNT(DISTINCT qv.questionid) 
         FROM {question_versions} qv 
         WHERE qv.status = 'hidden'"
    );
    echo '<strong>Vérification directe BDD :</strong> ' . $direct_count . ' question(s) avec status=\'hidden\'<br>';
    
    if ($direct_count != $total_hidden) {
        echo '<span style="color: red; font-weight: bold;">⚠️ DIFFÉRENCE DÉTECTÉE ! La fonction get_hidden_questions() ne retourne pas toutes les questions.</span><br>';
    }
} catch (Exception $e) {
    echo '<span style="color: orange;">⚠️ Erreur vérification: ' . $e->getMessage() . '</span><br>';
}

if ($total_hidden > 0) {
    echo '<strong>IDs trouvés :</strong> ' . implode(', ', array_slice($hidden_question_ids, 0, 50)) . ($total_hidden > 50 ? '... (+ ' . ($total_hidden - 50) . ' autres)' : '');
}
echo html_writer::end_tag('div');

// Statistiques
echo html_writer::start_tag('div', ['class' => 'qd-dashboard', 'style' => 'margin: 30px 0;']);

echo html_writer::start_tag('div', ['class' => 'qd-card']);
echo html_writer::tag('div', get_string('total_hidden_questions', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', $total_hidden, ['class' => 'qd-card-value']);
echo html_writer::tag('div', 'Toutes les questions cachées (quel qu\'en soit la raison)', ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'qd-card success']);
echo html_writer::tag('div', 'Cachées Manuellement', ['class' => 'qd-card-title']);
echo html_writer::tag('div', $manually_hidden, ['class' => 'qd-card-value']);
echo html_writer::tag('div', 'Peuvent être rendues visibles', ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'qd-card danger']);
echo html_writer::tag('div', 'Soft Delete', ['class' => 'qd-card-title']);
echo html_writer::tag('div', $soft_deleted, ['class' => 'qd-card-value']);
echo html_writer::tag('div', 'Utilisées dans quiz - SERONT AUSSI RENDUES VISIBLES ⚠️', ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

echo html_writer::end_tag('div');

// Bouton pour rendre TOUTES visibles (avec avertissement sur soft delete)
if ($total_hidden > 0) {
    echo html_writer::start_tag('div', ['style' => 'margin: 30px 0; text-align: center;']);
    
    // 🔧 Utiliser un FORMULAIRE POST pour que ça fonctionne
    $confirm_message = "⚠️ ATTENTION CRITIQUE\n\n";
    $confirm_message .= "Vous allez rendre visible " . $total_hidden . " question(s) cachée(s).\n\n";
    $confirm_message .= "Cela inclut:\n";
    $confirm_message .= "- " . $manually_hidden . " question(s) cachées manuellement\n";
    $confirm_message .= "- " . $soft_deleted . " question(s) soft delete (utilisées dans quiz)\n\n";
    $confirm_message .= "Êtes-vous ABSOLUMENT sûr ?\n\n";
    $confirm_message .= "Cliquez OK pour continuer ou Annuler pour arrêter.";
    
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => new moodle_url('/local/question_diagnostic/unhide_questions.php'),
        'style' => 'display: inline-block;',
        'onsubmit' => 'return confirm(' . json_encode($confirm_message) . ')'
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'unhide_all']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => '1']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'value' => '👁️ Rendre TOUTES les questions visibles (' . $total_hidden . ')',
        'class' => 'btn btn-danger btn-lg'
    ]);
    echo html_writer::end_tag('form');
    
    echo html_writer::start_tag('div', ['class' => 'alert alert-danger', 'style' => 'margin-top: 20px; max-width: 800px; margin-left: auto; margin-right: auto;']);
    echo html_writer::tag('strong', '⚠️ ATTENTION IMPORTANTE : ');
    echo 'Cette action rendra visibles <strong>ABSOLUMENT TOUTES</strong> les ' . $total_hidden . ' question(s) cachées :<br><br>';
    echo '✅ <strong>' . $manually_hidden . '</strong> question(s) cachées manuellement (sans risque)<br>';
    echo '⚠️ <strong>' . $soft_deleted . '</strong> question(s) soft delete (utilisées dans ' . array_sum(array_map(function($q) use ($usage_map) {
        return isset($usage_map[$q->id]['quiz_count']) ? $usage_map[$q->id]['quiz_count'] : 0;
    }, array_filter($all_hidden_questions, function($q) use ($usage_map) {
        $qc = isset($usage_map[$q->id]['quiz_count']) ? $usage_map[$q->id]['quiz_count'] : 0;
        return $qc > 0;
    }))) . ' quiz - peut affecter les tentatives de quiz)';
    echo html_writer::end_tag('div');
    
    echo html_writer::end_tag('div');
}

if ($total_hidden > 0) {
    // Tableau de TOUTES les questions cachées
    echo html_writer::tag('h3', '📋 Toutes les questions cachées');
    
    echo html_writer::start_tag('table', ['class' => 'qd-table', 'style' => 'width: 100%;']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'ID');
    echo html_writer::tag('th', 'Nom');
    echo html_writer::tag('th', 'Type');
    echo html_writer::tag('th', 'Statut');
    echo html_writer::tag('th', 'Quiz', ['title' => 'Nombre de quiz utilisant cette question']);
    echo html_writer::tag('th', 'Catégorie');
    echo html_writer::tag('th', 'Créée le');
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    
    echo html_writer::start_tag('tbody');
    
    foreach ($all_hidden_questions as $question) {
        $stats = question_analyzer::get_question_stats($question);
        
        // Déterminer le statut
        $is_used = false;
        $quiz_count = 0;
        if (isset($usage_map[$question->id]) && is_array($usage_map[$question->id])) {
            $quiz_count = isset($usage_map[$question->id]['quiz_count']) ? $usage_map[$question->id]['quiz_count'] : 0;
            $is_used = ($quiz_count > 0);
        }
        
        $row_style = $is_used ? 'background: #f8d7da;' : 'background: #fff3cd;';
        
        echo html_writer::start_tag('tr', ['style' => $row_style]);
        echo html_writer::tag('td', $question->id);
        echo html_writer::tag('td', format_string($question->name));
        echo html_writer::tag('td', $question->qtype);
        
        // Colonne statut
        if ($is_used) {
            echo html_writer::tag('td', '🗑️ Supprimée (soft delete)', [
                'style' => 'color: #d9534f; font-weight: bold;',
                'title' => 'Cette question a été supprimée mais est conservée car utilisée dans ' . $quiz_count . ' quiz'
            ]);
        } else {
            echo html_writer::tag('td', '🔒 Cachée manuellement', [
                'style' => 'color: #f0ad4e; font-weight: bold;',
                'title' => 'Cette question peut être rendue visible sans risque'
            ]);
        }
        
        // Colonne quiz count
        echo html_writer::tag('td', $quiz_count, [
            'style' => $quiz_count > 0 ? 'font-weight: bold; color: #d9534f;' : 'color: #999;'
        ]);
        
        echo html_writer::tag('td', isset($stats->category_name) ? format_string($stats->category_name) : '-');
        echo html_writer::tag('td', userdate($question->timecreated, '%d/%m/%Y'));
        echo html_writer::end_tag('tr');
    }
    
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    
    if (count($all_hidden_questions) >= 1000) {
        echo html_writer::start_tag('div', ['class' => 'alert alert-info', 'style' => 'margin-top: 20px;']);
        echo 'ℹ️ Affichage limité aux 1000 premières questions pour des raisons de performance.';
        echo html_writer::end_tag('div');
    }
} else {
    echo html_writer::start_tag('div', ['class' => 'alert alert-success', 'style' => 'margin: 30px 0; text-align: center;']);
    echo html_writer::tag('h3', '✅ Aucune question cachée trouvée', ['style' => 'margin-top: 0;']);
    echo html_writer::tag('p', 'Toutes vos questions sont visibles. Aucune question avec status="hidden" détectée.');
    echo html_writer::end_tag('div');
}

// Message info si seulement des soft delete
if ($total_hidden > 0 && $manually_hidden == 0 && $soft_deleted > 0) {
    echo html_writer::start_tag('div', ['class' => 'alert alert-warning', 'style' => 'margin-top: 20px;']);
    echo html_writer::tag('strong', 'ℹ️ Information : ');
    echo 'Les ' . $soft_deleted . ' question(s) affichée(s) sont des questions supprimées (soft delete) mais conservées car utilisées dans des quiz. ';
    echo 'Elles <strong>NE PEUVENT PAS</strong> être rendues visibles automatiquement car cela pourrait affecter les tentatives de quiz existantes.';
    echo html_writer::end_tag('div');
}

// Pied de page Moodle standard
echo $OUTPUT->footer();

