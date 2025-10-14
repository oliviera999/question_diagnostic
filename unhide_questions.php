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

// 🔧 DEBUG : Vérifier le sesskey
if ($action === 'unhide_all') {
    if (!confirm_sesskey()) {
        print_error('invalidsesskey', 'error');
    }
    
    if (!$confirm) {
        // Afficher la page de confirmation
        echo $OUTPUT->header();
        echo local_question_diagnostic_render_version_badge();
        
        echo html_writer::tag('h2', '⚠️ Confirmation requise');
        
        // Charger les questions cachées non utilisées
        $hidden_questions_to_unhide = question_analyzer::get_hidden_questions(true, 0); // true = exclure utilisées
        $count = count($hidden_questions_to_unhide);
        
        if ($count == 0) {
            echo html_writer::start_tag('div', ['class' => 'alert alert-info', 'style' => 'margin: 20px 0;']);
            echo html_writer::tag('p', '✅ Aucune question cachée manuellement à rendre visible.');
            echo html_writer::tag('p', 'Toutes les questions cachées sont des soft delete (utilisées dans des quiz) et sont protégées.');
            echo html_writer::end_tag('div');
            
            echo html_writer::link(
                new moodle_url('/local/question_diagnostic/unhide_questions.php'),
                '← Retour',
                ['class' => 'btn btn-secondary']
            );
            
            echo $OUTPUT->footer();
            exit;
        }
        
        echo html_writer::start_tag('div', ['class' => 'alert alert-warning', 'style' => 'margin: 20px 0;']);
        echo html_writer::tag('p', '<strong>Vous êtes sur le point de rendre visible ' . $count . ' question(s) cachée(s) manuellement.</strong>');
        echo html_writer::tag('p', 'Cette action va changer le statut de toutes les questions cachées NON utilisées de "hidden" à "ready".');
        echo html_writer::tag('p', '⚠️ <strong>Note :</strong> Les questions cachées mais utilisées dans des quiz (soft delete) ne seront PAS affectées.');
        echo html_writer::end_tag('div');
        
        // Afficher les questions qui seront affectées
        echo html_writer::tag('h4', '📋 Questions qui seront rendues visibles :');
        echo html_writer::start_tag('ul', ['style' => 'max-height: 300px; overflow-y: auto; background: #f8f9fa; padding: 15px; border-radius: 5px;']);
        foreach ($hidden_questions_to_unhide as $q) {
            echo html_writer::tag('li', 'ID ' . $q->id . ' : ' . format_string($q->name) . ' (' . $q->qtype . ')');
        }
        echo html_writer::end_tag('ul');
        
        echo html_writer::start_tag('div', ['style' => 'margin: 20px 0;']);
        echo html_writer::link(
            new moodle_url('/local/question_diagnostic/unhide_questions.php', [
                'action' => 'unhide_all',
                'confirm' => 1,
                'sesskey' => sesskey()
            ]),
            '✅ Oui, rendre visibles (' . $count . ' questions)',
            ['class' => 'btn btn-success btn-lg', 'style' => 'margin-right: 10px;']
        );
        echo html_writer::link(
            new moodle_url('/local/question_diagnostic/unhide_questions.php'),
            '❌ Annuler',
            ['class' => 'btn btn-secondary btn-lg']
        );
        echo html_writer::end_tag('div');
        
        echo $OUTPUT->footer();
        exit;
    } else {
        // Exécuter l'action
        $hidden_questions = question_analyzer::get_hidden_questions(true, 0);
        $question_ids = array_map(function($q) { return $q->id; }, $hidden_questions);
        
        if (empty($question_ids)) {
            redirect(
                new moodle_url('/local/question_diagnostic/unhide_questions.php'),
                '✅ Aucune question cachée trouvée.',
                null,
                \core\output\notification::NOTIFY_INFO
            );
        }
        
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
echo html_writer::tag('p', get_string('unhide_questions_intro', 'local_question_diagnostic'));
echo html_writer::end_tag('div');

// Charger TOUTES les questions cachées (y compris soft delete)
$all_hidden_questions = question_analyzer::get_hidden_questions(false, 1000); // false = inclure toutes
$total_hidden = count($all_hidden_questions);

// Calculer combien sont utilisées vs non utilisées
$hidden_question_ids = array_map(function($q) { return $q->id; }, $all_hidden_questions);
$usage_map = question_analyzer::get_questions_usage_by_ids($hidden_question_ids);

$manually_hidden = 0; // Cachées manuellement (non utilisées)
$soft_deleted = 0;    // Supprimées (soft delete, utilisées)

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
echo html_writer::tag('div', 'Utilisées dans quiz - Protégées', ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

echo html_writer::end_tag('div');

// Bouton pour rendre toutes visibles (seulement les cachées manuellement)
if ($manually_hidden > 0) {
    echo html_writer::start_tag('div', ['style' => 'margin: 30px 0; text-align: center;']);
    echo html_writer::link(
        new moodle_url('/local/question_diagnostic/unhide_questions.php', [
            'action' => 'unhide_all',
            'sesskey' => sesskey()
        ]),
        '👁️ Rendre visibles les questions cachées manuellement (' . $manually_hidden . ')',
        ['class' => 'btn btn-success btn-lg']
    );
    echo html_writer::tag('p', 
        '⚠️ Les questions soft delete (' . $soft_deleted . ') utilisées dans des quiz seront automatiquement protégées.',
        ['style' => 'margin-top: 10px; color: #666; font-size: 13px;']
    );
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

