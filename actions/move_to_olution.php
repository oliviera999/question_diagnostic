<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Action de déplacement vers Olution
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/../classes/olution_manager.php');

use local_question_diagnostic\olution_manager;

require_login();
require_sesskey();

if (!is_siteadmin()) {
    print_error('accessdenied', 'admin');
}

$PAGE->set_url(new moodle_url('/local/question_diagnostic/actions/move_to_olution.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('admin');

// Paramètres
$action = optional_param('action', 'move_one', PARAM_ALPHA);
$questionid = optional_param('questionid', 0, PARAM_INT);
$targetcatid = optional_param('targetcatid', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);

$return_url = new moodle_url('/local/question_diagnostic/olution_duplicates.php');

// ===========================================================================
// ACTION : DÉPLACER UNE SEULE QUESTION
// ===========================================================================
if ($action === 'move_one') {
    
    if (!$questionid || !$targetcatid) {
        redirect($return_url, get_string('invalid_parameters', 'local_question_diagnostic'), 
                null, \core\output\notification::NOTIFY_ERROR);
    }
    
    // Récupérer les informations pour la confirmation
    $question = $DB->get_record('question', ['id' => $questionid], '*', MUST_EXIST);
    $target_category = $DB->get_record('question_categories', ['id' => $targetcatid], '*', MUST_EXIST);
    
    // Récupérer la catégorie source
    $sql_source_cat = "SELECT qc.*
                       FROM {question_categories} qc
                       INNER JOIN {question_bank_entries} qbe ON qbe.questioncategoryid = qc.id
                       INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                       WHERE qv.questionid = :questionid
                       LIMIT 1";
    $source_category = $DB->get_record_sql($sql_source_cat, ['questionid' => $questionid]);
    
    // Si pas de confirmation, afficher la page de confirmation
    if (!$confirm) {
        $PAGE->set_title(get_string('confirm_move_to_olution', 'local_question_diagnostic'));
        $PAGE->set_heading(get_string('confirm_move_to_olution', 'local_question_diagnostic'));
        
        echo $OUTPUT->header();
        
        echo html_writer::tag('h2', get_string('confirm_move_to_olution', 'local_question_diagnostic'));
        
        // Afficher les détails du déplacement
        echo html_writer::start_div('alert alert-info');
        echo html_writer::tag('h4', get_string('move_details', 'local_question_diagnostic'));
        echo html_writer::tag('p', html_writer::tag('strong', get_string('question', 'question')) . ': ' . 
                              format_string($question->name) . ' (ID: ' . $question->id . ')');
        echo html_writer::tag('p', html_writer::tag('strong', get_string('question_type', 'local_question_diagnostic')) . ': ' . 
                              $question->qtype);
        if ($source_category) {
            echo html_writer::tag('p', html_writer::tag('strong', get_string('from_category', 'local_question_diagnostic')) . ': ' . 
                                  format_string($source_category->name) . ' (ID: ' . $source_category->id . ')');
        }
        echo html_writer::tag('p', html_writer::tag('strong', get_string('to_category', 'local_question_diagnostic')) . ': ' . 
                              format_string($target_category->name) . ' (ID: ' . $target_category->id . ')');
        echo html_writer::end_div();
        
        // Avertissement
        echo html_writer::start_div('alert alert-warning');
        echo '⚠️ ' . get_string('move_warning', 'local_question_diagnostic');
        echo html_writer::end_div();
        
        // Boutons de confirmation
        $confirm_url = new moodle_url('/local/question_diagnostic/actions/move_to_olution.php', [
            'action' => 'move_one',
            'questionid' => $questionid,
            'targetcatid' => $targetcatid,
            'confirm' => 1,
            'sesskey' => sesskey()
        ]);
        
        echo html_writer::link($confirm_url, get_string('confirm', 'core'), 
                              ['class' => 'btn btn-primary btn-lg mr-2']);
        echo html_writer::link($return_url, get_string('cancel', 'core'), 
                              ['class' => 'btn btn-secondary btn-lg']);
        
        echo $OUTPUT->footer();
        exit;
    }
    
    // Confirmation donnée : effectuer le déplacement
    $result = olution_manager::move_question_to_olution($questionid, $targetcatid);
    
    if ($result === true) {
        redirect($return_url, get_string('move_success', 'local_question_diagnostic'), 
                null, \core\output\notification::NOTIFY_SUCCESS);
    } else {
        redirect($return_url, get_string('move_error', 'local_question_diagnostic') . ': ' . $result, 
                null, \core\output\notification::NOTIFY_ERROR);
    }
}

// ===========================================================================
// ACTION : DÉPLACER TOUTES LES QUESTIONS DÉPLAÇABLES
// ===========================================================================
else if ($action === 'move_all') {
    
    // Si pas de confirmation, afficher la page de confirmation
    if (!$confirm) {
        $PAGE->set_title(get_string('confirm_move_all_to_olution', 'local_question_diagnostic'));
        $PAGE->set_heading(get_string('confirm_move_all_to_olution', 'local_question_diagnostic'));
        
        echo $OUTPUT->header();
        
        echo html_writer::tag('h2', get_string('confirm_move_all_to_olution', 'local_question_diagnostic'));
        
        // Récupérer les statistiques
        $stats = olution_manager::get_duplicate_stats();
        
        // Afficher les détails du déplacement global
        echo html_writer::start_div('alert alert-info');
        echo html_writer::tag('h4', get_string('move_all_details', 'local_question_diagnostic'));
        echo html_writer::tag('p', html_writer::tag('strong', get_string('total_questions_to_move', 'local_question_diagnostic')) . ': ' . 
                              $stats->movable_questions);
        echo html_writer::end_div();
        
        // Liste des catégories sources concernées
        if (!empty($stats->by_source_category)) {
            echo html_writer::tag('h4', get_string('affected_categories', 'local_question_diagnostic'));
            echo html_writer::start_tag('ul');
            foreach ($stats->by_source_category as $cat_info) {
                echo html_writer::tag('li', format_string($cat_info['category']->name) . ' : ' . 
                                      $cat_info['count'] . ' ' . get_string('questions', 'question'));
            }
            echo html_writer::end_tag('ul');
        }
        
        // Avertissement FORT
        echo html_writer::start_div('alert alert-danger');
        echo html_writer::tag('h4', '⚠️ ' . get_string('warning', 'core'));
        echo html_writer::tag('p', get_string('move_all_warning', 'local_question_diagnostic'));
        echo html_writer::end_div();
        
        // Boutons de confirmation
        $confirm_url = new moodle_url('/local/question_diagnostic/actions/move_to_olution.php', [
            'action' => 'move_all',
            'confirm' => 1,
            'sesskey' => sesskey()
        ]);
        
        echo html_writer::link($confirm_url, get_string('confirm', 'core'), 
                              ['class' => 'btn btn-danger btn-lg mr-2']);
        echo html_writer::link($return_url, get_string('cancel', 'core'), 
                              ['class' => 'btn btn-secondary btn-lg']);
        
        echo $OUTPUT->footer();
        exit;
    }
    
    // Confirmation donnée : effectuer le déplacement global
    $all_duplicates = olution_manager::find_course_to_olution_duplicates(0, 0);
    
    // Préparer les opérations de déplacement
    $operations = [];
    foreach ($all_duplicates as $dup) {
        if ($dup['olution_target_category']) {
            $operations[] = [
                'questionid' => $dup['course_question']->id,
                'target_category_id' => $dup['olution_target_category']->id
            ];
        }
    }
    
    if (empty($operations)) {
        redirect($return_url, get_string('no_movable_questions', 'local_question_diagnostic'), 
                null, \core\output\notification::NOTIFY_WARNING);
    }
    
    // Effectuer le déplacement en masse
    $result = olution_manager::move_questions_batch($operations);
    
    // Construire le message de résultat
    $message = get_string('move_batch_result', 'local_question_diagnostic', [
        'success' => $result['success'],
        'failed' => $result['failed']
    ]);
    
    if ($result['success'] > 0 && $result['failed'] == 0) {
        redirect($return_url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
    } else if ($result['success'] > 0) {
        redirect($return_url, $message, null, \core\output\notification::NOTIFY_WARNING);
    } else {
        redirect($return_url, $message, null, \core\output\notification::NOTIFY_ERROR);
    }
}

// Action inconnue
else {
    redirect($return_url, get_string('invalid_action', 'local_question_diagnostic'), 
            null, \core\output\notification::NOTIFY_ERROR);
}

