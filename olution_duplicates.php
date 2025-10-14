<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Page de gestion des doublons cours → Olution
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/olution_manager.php');

use local_question_diagnostic\olution_manager;

require_login();

if (!is_siteadmin()) {
    print_error('accessdenied', 'admin');
}

$PAGE->set_url(new moodle_url('/local/question_diagnostic/olution_duplicates.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('olution_duplicates_title', 'local_question_diagnostic'));
$PAGE->set_heading(get_string('olution_duplicates_heading', 'local_question_diagnostic'));
$PAGE->set_pagelayout('admin');

// Pagination
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 50, PARAM_INT);

echo $OUTPUT->header();

// Badge de version
echo local_question_diagnostic_render_version_badge();

// Bouton retour
echo html_writer::div(
    local_question_diagnostic_render_back_link('olution_duplicates.php'),
    'mb-3'
);

// Titre de la page
echo html_writer::tag('h2', get_string('olution_duplicates_title', 'local_question_diagnostic'));

// Vérifier que la catégorie Olution existe
$olution = local_question_diagnostic_find_olution_category();

if (!$olution) {
    // Afficher un message d'erreur si Olution n'existe pas
    echo $OUTPUT->notification(
        get_string('olution_not_found', 'local_question_diagnostic'),
        \core\output\notification::NOTIFY_ERROR
    );
    
    echo html_writer::tag('p', get_string('olution_not_found_help', 'local_question_diagnostic'));
    
    echo $OUTPUT->footer();
    exit;
}

// Récupérer les statistiques
$stats = olution_manager::get_duplicate_stats();

// Afficher les statistiques globales
echo html_writer::start_div('row mb-4');

// Carte 1 : Total doublons
echo html_writer::start_div('col-md-3');
echo html_writer::start_div('card qd-card');
echo html_writer::start_div('card-body text-center');
echo html_writer::tag('h3', $stats->total_duplicates, ['class' => 'text-primary']);
echo html_writer::tag('p', get_string('olution_total_duplicates', 'local_question_diagnostic'), ['class' => 'mb-0']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Carte 2 : Questions déplaçables
echo html_writer::start_div('col-md-3');
echo html_writer::start_div('card qd-card');
echo html_writer::start_div('card-body text-center');
echo html_writer::tag('h3', $stats->movable_questions, ['class' => 'text-success']);
echo html_writer::tag('p', get_string('olution_movable_questions', 'local_question_diagnostic'), ['class' => 'mb-0']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Carte 3 : Questions non-déplaçables
echo html_writer::start_div('col-md-3');
echo html_writer::start_div('card qd-card');
echo html_writer::start_div('card-body text-center');
echo html_writer::tag('h3', $stats->unmovable_questions, ['class' => 'text-warning']);
echo html_writer::tag('p', get_string('olution_unmovable_questions', 'local_question_diagnostic'), ['class' => 'mb-0']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Carte 4 : Sous-catégories Olution
echo html_writer::start_div('col-md-3');
echo html_writer::start_div('card qd-card');
echo html_writer::start_div('card-body text-center');
echo html_writer::tag('h3', $stats->olution_subcategories_count, ['class' => 'text-info']);
echo html_writer::tag('p', get_string('olution_subcategories_count', 'local_question_diagnostic'), ['class' => 'mb-0']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div();

// Si aucun doublon trouvé
if ($stats->total_duplicates == 0) {
    echo $OUTPUT->notification(
        get_string('olution_no_duplicates_found', 'local_question_diagnostic'),
        \core\output\notification::NOTIFY_SUCCESS
    );
    
    echo $OUTPUT->footer();
    exit;
}

// Bouton d'action globale
if ($stats->movable_questions > 0) {
    echo html_writer::start_div('mb-3');
    $move_all_url = new moodle_url('/local/question_diagnostic/actions/move_to_olution.php', [
        'action' => 'move_all',
        'sesskey' => sesskey()
    ]);
    echo html_writer::link(
        $move_all_url,
        get_string('olution_move_all_button', 'local_question_diagnostic', $stats->movable_questions),
        ['class' => 'btn btn-primary btn-lg']
    );
    echo html_writer::end_div();
}

// Récupérer les doublons pour la page actuelle
$offset = $page * $perpage;
$duplicates = olution_manager::find_course_to_olution_duplicates($perpage, $offset);

// Afficher la liste des doublons
echo html_writer::tag('h3', get_string('olution_duplicates_list', 'local_question_diagnostic'));

if (!empty($duplicates)) {
    echo html_writer::start_tag('table', ['class' => 'table table-striped qd-table']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('question_name', 'local_question_diagnostic'));
    echo html_writer::tag('th', get_string('question_type', 'local_question_diagnostic'));
    echo html_writer::tag('th', get_string('course_category', 'local_question_diagnostic'));
    echo html_writer::tag('th', get_string('olution_target_category', 'local_question_diagnostic'));
    echo html_writer::tag('th', get_string('similarity', 'local_question_diagnostic'));
    echo html_writer::tag('th', get_string('actions', 'local_question_diagnostic'));
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');
    
    foreach ($duplicates as $dup) {
        echo html_writer::start_tag('tr');
        
        // Nom de la question
        echo html_writer::start_tag('td');
        echo html_writer::tag('strong', format_string($dup['course_question']->name));
        echo html_writer::tag('br');
        echo html_writer::tag('small', 'ID: ' . $dup['course_question']->id, ['class' => 'text-muted']);
        echo html_writer::end_tag('td');
        
        // Type de question
        echo html_writer::tag('td', $dup['course_question']->qtype);
        
        // Catégorie source (cours)
        echo html_writer::start_tag('td');
        echo format_string($dup['course_category']->name);
        echo html_writer::tag('br');
        echo html_writer::tag('small', 'ID: ' . $dup['course_category']->id, ['class' => 'text-muted']);
        echo html_writer::end_tag('td');
        
        // Catégorie cible (Olution)
        echo html_writer::start_tag('td');
        if ($dup['olution_target_category']) {
            echo html_writer::tag('span', format_string($dup['olution_target_category']->name), ['class' => 'badge badge-success']);
        } else {
            echo html_writer::tag('span', get_string('no_match', 'local_question_diagnostic'), ['class' => 'badge badge-warning']);
        }
        echo html_writer::end_tag('td');
        
        // Similarité
        echo html_writer::tag('td', round($dup['similarity'] * 100, 1) . '%');
        
        // Actions
        echo html_writer::start_tag('td');
        if ($dup['olution_target_category']) {
            $move_url = new moodle_url('/local/question_diagnostic/actions/move_to_olution.php', [
                'questionid' => $dup['course_question']->id,
                'targetcatid' => $dup['olution_target_category']->id,
                'sesskey' => sesskey()
            ]);
            echo html_writer::link(
                $move_url,
                get_string('move', 'local_question_diagnostic'),
                ['class' => 'btn btn-sm btn-primary']
            );
        } else {
            echo html_writer::tag('span', '-', ['class' => 'text-muted']);
        }
        echo html_writer::end_tag('td');
        
        echo html_writer::end_tag('tr');
    }
    
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    
    // Pagination
    if ($stats->total_duplicates > $perpage) {
        $baseurl = new moodle_url('/local/question_diagnostic/olution_duplicates.php', ['perpage' => $perpage]);
        echo $OUTPUT->paging_bar($stats->total_duplicates, $page, $perpage, $baseurl);
    }
}

echo $OUTPUT->footer();

