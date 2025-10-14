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

if ($action === 'unhide_all' && confirm_sesskey()) {
    if (!$confirm) {
        // Afficher la page de confirmation
        echo $OUTPUT->header();
        echo local_question_diagnostic_render_version_badge();
        
        echo html_writer::tag('h2', '⚠️ Confirmation requise');
        
        $hidden_questions = question_analyzer::get_hidden_questions(true, 0);
        $count = count($hidden_questions);
        
        echo html_writer::start_tag('div', ['class' => 'alert alert-warning', 'style' => 'margin: 20px 0;']);
        echo html_writer::tag('p', '<strong>Vous êtes sur le point de rendre visible ' . $count . ' question(s) cachée(s).</strong>');
        echo html_writer::tag('p', 'Cette action va changer le statut de toutes les questions cachées NON utilisées de "hidden" à "ready".');
        echo html_writer::tag('p', '⚠️ <strong>Note :</strong> Les questions cachées mais utilisées dans des quiz (soft delete) ne seront PAS affectées.');
        echo html_writer::end_tag('div');
        
        echo html_writer::start_tag('div', ['style' => 'margin: 20px 0;']);
        echo html_writer::link(
            new moodle_url('/local/question_diagnostic/unhide_questions.php', [
                'action' => 'unhide_all',
                'confirm' => 1,
                'sesskey' => sesskey()
            ]),
            '✅ Oui, rendre visibles',
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

// Charger les questions cachées
$hidden_questions = question_analyzer::get_hidden_questions(true, 1000); // Limiter à 1000 pour performance
$total_hidden = count($hidden_questions);

// Statistiques
echo html_writer::start_tag('div', ['class' => 'qd-dashboard', 'style' => 'margin: 30px 0;']);

echo html_writer::start_tag('div', ['class' => 'qd-card']);
echo html_writer::tag('div', get_string('total_hidden_questions', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', $total_hidden, ['class' => 'qd-card-value']);
echo html_writer::tag('div', get_string('manually_hidden_only', 'local_question_diagnostic'), ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

echo html_writer::end_tag('div');

// Bouton pour rendre toutes visibles
if ($total_hidden > 0) {
    echo html_writer::start_tag('div', ['style' => 'margin: 30px 0; text-align: center;']);
    echo html_writer::link(
        new moodle_url('/local/question_diagnostic/unhide_questions.php', [
            'action' => 'unhide_all',
            'sesskey' => sesskey()
        ]),
        '👁️ Rendre toutes les questions visibles (' . $total_hidden . ')',
        ['class' => 'btn btn-success btn-lg']
    );
    echo html_writer::end_tag('div');
    
    // Tableau des questions cachées
    echo html_writer::tag('h3', '📋 Questions cachées (non utilisées)');
    
    echo html_writer::start_tag('table', ['class' => 'qd-table', 'style' => 'width: 100%;']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'ID');
    echo html_writer::tag('th', 'Nom');
    echo html_writer::tag('th', 'Type');
    echo html_writer::tag('th', 'Catégorie');
    echo html_writer::tag('th', 'Créée le');
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    
    echo html_writer::start_tag('tbody');
    
    foreach ($hidden_questions as $question) {
        $stats = question_analyzer::get_question_stats($question);
        
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', $question->id);
        echo html_writer::tag('td', format_string($question->name));
        echo html_writer::tag('td', $question->qtype);
        echo html_writer::tag('td', isset($stats->category_name) ? format_string($stats->category_name) : '-');
        echo html_writer::tag('td', userdate($question->timecreated, '%d/%m/%Y'));
        echo html_writer::end_tag('tr');
    }
    
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    
    if (count($hidden_questions) >= 1000) {
        echo html_writer::start_tag('div', ['class' => 'alert alert-info', 'style' => 'margin-top: 20px;']);
        echo 'ℹ️ Affichage limité aux 1000 premières questions pour des raisons de performance.';
        echo html_writer::end_tag('div');
    }
} else {
    echo html_writer::start_tag('div', ['class' => 'alert alert-success', 'style' => 'margin: 30px 0; text-align: center;']);
    echo html_writer::tag('h3', '✅ Aucune question cachée trouvée', ['style' => 'margin-top: 0;']);
    echo html_writer::tag('p', 'Toutes vos questions sont soit visibles, soit supprimées (soft delete).');
    echo html_writer::end_tag('div');
}

// Pied de page Moodle standard
echo $OUTPUT->footer();

