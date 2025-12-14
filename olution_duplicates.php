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

// Afficher le bouton de purge des caches
echo html_writer::start_div('text-right', ['style' => 'margin-bottom: 20px;']);
echo local_question_diagnostic_render_cache_purge_button();
echo html_writer::end_div();

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

// Afficher quelle catégorie de questions a été trouvée
echo html_writer::start_div('alert alert-info mb-3');
echo html_writer::tag('strong', '✅ Catégorie de questions Olution détectée : ');
echo html_writer::tag('span', format_string($olution->name));
echo html_writer::tag('small', ' (ID: ' . $olution->id . ')', ['class' => 'text-muted ml-2']);
echo html_writer::empty_tag('br');

// Afficher le contexte (système, cours ou catégorie de cours)
if (isset($olution->context_type)) {
    if ($olution->context_type === 'course_category') {
        echo html_writer::tag('small', '📚 Contexte : Catégorie de cours "' . format_string($olution->course_category_name) . '" (ID: ' . $olution->course_category_id . ')', ['class' => 'text-muted']);
        echo html_writer::empty_tag('br');
        echo html_writer::tag('small', '   → Cours : "' . format_string($olution->course_name) . '" (ID: ' . $olution->course_id . ')', ['class' => 'text-muted']);
    } else if ($olution->context_type === 'course') {
        echo html_writer::tag('small', '📚 Contexte : Cours "' . format_string($olution->course_name) . '" (ID: ' . $olution->course_id . ')', ['class' => 'text-muted']);
    } else {
        echo html_writer::tag('small', '🌐 Contexte : Système', ['class' => 'text-muted']);
    }
} else {
    echo html_writer::tag('small', '🌐 Contexte : Système', ['class' => 'text-muted']);
}
echo html_writer::empty_tag('br');

$subcats_count = $DB->count_records('question_categories', ['parent' => $olution->id]);
$all_subcats = local_question_diagnostic_get_olution_subcategories();
echo html_writer::tag('small', 'Cette catégorie contient ' . count($all_subcats) . ' sous-catégorie(s) (toute profondeur)', ['class' => 'text-muted']);
echo html_writer::end_div();

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
echo html_writer::tag('h3', $stats->olution_courses_count, ['class' => 'text-info']);
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

// Récupérer les groupes de doublons pour la page actuelle
$offset = $page * $perpage;
$totalgroups = 0;
$duplicate_groups = olution_manager::find_all_duplicates_for_olution_paginated($perpage, $offset, $totalgroups);

// Afficher la liste des groupes de doublons
echo html_writer::tag('h3', get_string('olution_duplicates_list', 'local_question_diagnostic'));

if (!empty($duplicate_groups)) {
    foreach ($duplicate_groups as $group) {
        // Afficher le groupe
        echo html_writer::start_div('card mb-3');
        echo html_writer::start_div('card-header bg-light');
        echo html_writer::tag('strong', format_string($group['group_name']));
        echo ' (' . $group['group_type'] . ') - ';
        echo html_writer::tag('span', $group['total_count'] . ' version(s)', ['class' => 'badge badge-primary']);
        echo ' ';
        echo html_writer::tag('span', $group['olution_count'] . ' dans Olution', ['class' => 'badge badge-success']);
        echo ' ';
        echo html_writer::tag('span', $group['non_olution_count'] . ' hors Olution', ['class' => 'badge badge-warning']);
        
        // Catégorie cible (plus profonde)
        if ($group['target_category']) {
            echo html_writer::empty_tag('br');
            echo '🎯 Catégorie cible (profondeur ' . $group['target_depth'] . ') : ';
            echo html_writer::tag('strong', format_string($group['target_category']->name));
        }
        echo html_writer::end_div();
        
        // Afficher toutes les questions du groupe
        echo html_writer::start_div('card-body');
        echo html_writer::start_tag('table', ['class' => 'table table-sm table-striped']);
        echo html_writer::start_tag('thead');
        echo html_writer::start_tag('tr');
        echo html_writer::tag('th', 'ID');
        echo html_writer::tag('th', 'Catégorie actuelle');
        echo html_writer::tag('th', 'Dans Olution?');
        echo html_writer::tag('th', 'Profondeur');
        echo html_writer::tag('th', 'Action');
        echo html_writer::end_tag('tr');
        echo html_writer::end_tag('thead');
        echo html_writer::start_tag('tbody');
        
        foreach ($group['all_questions'] as $q_info) {
            $q = $q_info['question'];
            $cat = $q_info['category'];
            $in_olution = $q_info['is_in_olution'];
            $depth = $q_info['depth'];
            
            $row_class = $in_olution ? 'table-success' : '';
            if ($group['target_category'] && $cat->id == $group['target_category']->id) {
                $row_class = 'table-primary'; // C'est la catégorie cible
            }
            
            echo html_writer::start_tag('tr', ['class' => $row_class]);
            
            echo html_writer::tag('td', $q->id);
            echo html_writer::tag('td', format_string($cat->name) . ' (ID: ' . $cat->id . ')');
            echo html_writer::tag('td', $in_olution ? '✅ Oui' : '❌ Non');
            echo html_writer::tag('td', $depth);
            
            // Action : déplacer vers catégorie cible (uniquement si la question est hors Olution)
            echo html_writer::start_tag('td');
            if (!$in_olution && $group['target_category'] && $cat->id != $group['target_category']->id) {
                $move_url = new moodle_url('/local/question_diagnostic/actions/move_to_olution.php', [
                    'questionid' => $q->id,
                    'targetcatid' => $group['target_category']->id,
                    'sesskey' => sesskey()
                ]);
                echo html_writer::link(
                    $move_url,
                    'Déplacer →',
                    ['class' => 'btn btn-sm btn-primary']
                );
            } else if ($cat->id == $group['target_category']->id) {
                echo html_writer::tag('span', '🎯 Cible', ['class' => 'text-success']);
            } else if ($in_olution) {
                echo html_writer::tag('span', '✅ Déjà dans Olution', ['class' => 'text-muted']);
            } else {
                echo '-';
            }
            echo html_writer::end_tag('td');
            
            echo html_writer::end_tag('tr');
        }
        
        echo html_writer::end_tag('tbody');
        echo html_writer::end_tag('table');
        echo html_writer::end_div(); // card-body
        echo html_writer::end_div(); // card
    }
    
    // Pagination
    if ($totalgroups > $perpage) {
        $baseurl = new moodle_url('/local/question_diagnostic/olution_duplicates.php', ['perpage' => $perpage]);
        echo $OUTPUT->paging_bar($totalgroups, $page, $perpage, $baseurl);
    }
}

echo $OUTPUT->footer();

