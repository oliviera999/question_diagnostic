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
$action = optional_param('action', 'move_one', PARAM_ALPHANUMEXT);
$questionid = optional_param('questionid', 0, PARAM_INT);
$targetcatid = optional_param('targetcatid', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);

$return_url = new moodle_url('/local/question_diagnostic/olution_duplicates.php');
if ($action === 'move_triage_all') {
    $return_url = new moodle_url('/local/question_diagnostic/olution_triage.php');
}

// Valider explicitement l'action (sécurité + éviter les valeurs inattendues).
$allowedactions = ['move_one', 'move_all', 'move_triage_all', 'move_selected'];
if (!in_array($action, $allowedactions, true)) {
    $action = 'invalid';
}

// ===========================================================================
// ACTION : DÉPLACER UNE SÉLECTION DE QUESTIONS (multi-sélection)
// ===========================================================================
if ($action === 'move_selected') {
    $ops = optional_param_array('ops', [], PARAM_RAW);

    // Valider / parser.
    $operations = [];
    $seen = [];
    foreach ($ops as $op) {
        $op = trim((string)$op);
        if ($op === '' || !preg_match('/^\d+:\d+$/', $op)) {
            continue;
        }
        list($qid, $tid) = explode(':', $op, 2);
        $qid = (int)$qid;
        $tid = (int)$tid;
        if ($qid <= 0 || $tid <= 0) {
            continue;
        }
        if (isset($seen[$qid])) {
            continue;
        }
        $seen[$qid] = true;
        $operations[] = [
            'questionid' => $qid,
            'target_category_id' => $tid,
        ];
    }

    if (empty($operations)) {
        redirect($return_url, get_string('no_selected_questions', 'local_question_diagnostic'),
            null, \core\output\notification::NOTIFY_WARNING);
    }

    // Si pas de confirmation, afficher la page de confirmation avec détails.
    if (!$confirm) {
        $PAGE->set_title(get_string('confirm_move_selected_to_olution', 'local_question_diagnostic'));
        $PAGE->set_heading(get_string('confirm_move_selected_to_olution', 'local_question_diagnostic'));

        echo $OUTPUT->header();
        echo html_writer::tag('h2', get_string('confirm_move_selected_to_olution', 'local_question_diagnostic'));

        echo html_writer::start_div('alert alert-info');
        echo html_writer::tag('h4', get_string('move_details', 'local_question_diagnostic'));
        echo html_writer::tag('p', html_writer::tag('strong', get_string('total_questions_to_move', 'local_question_diagnostic')) . ': ' . count($operations));
        echo html_writer::end_div();

        // Détails par question.
        echo html_writer::start_tag('table', ['class' => 'table table-sm table-striped']);
        echo html_writer::start_tag('thead');
        echo html_writer::start_tag('tr');
        echo html_writer::tag('th', get_string('question_id', 'local_question_diagnostic'));
        echo html_writer::tag('th', get_string('question_name', 'local_question_diagnostic'));
        echo html_writer::tag('th', get_string('context', 'local_question_diagnostic'));
        echo html_writer::tag('th', get_string('current_category_path', 'local_question_diagnostic'));
        echo html_writer::tag('th', get_string('olution_target_category', 'local_question_diagnostic'));
        echo html_writer::end_tag('tr');
        echo html_writer::end_tag('thead');
        echo html_writer::start_tag('tbody');

        foreach ($operations as $op) {
            $qid = (int)$op['questionid'];
            $tid = (int)$op['target_category_id'];
            $question = $DB->get_record('question', ['id' => $qid], 'id,name,qtype', IGNORE_MISSING);
            $target_category = $DB->get_record('question_categories', ['id' => $tid], 'id,name,contextid', IGNORE_MISSING);

            // Catégorie source.
            $sql_source_cat = "SELECT qc.*
                                 FROM {question_categories} qc
                                 INNER JOIN {question_bank_entries} qbe ON qbe.questioncategoryid = qc.id
                                 INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                                WHERE qv.questionid = :questionid
                                LIMIT 1";
            $source_category = $DB->get_record_sql($sql_source_cat, ['questionid' => $qid]);

            echo html_writer::start_tag('tr');
            echo html_writer::tag('td', $qid);

            $qname = $question ? format_string($question->name) : ('#' . $qid);
            $qcell = $qname;
            if ($question && $source_category) {
                $qurl = local_question_diagnostic_get_question_bank_url($source_category, $qid);
                if ($qurl) {
                    $qcell = html_writer::link($qurl, $qname, ['target' => '_blank']);
                }
            }
            echo html_writer::tag('td', $qcell);

            if ($source_category) {
                $ctx = local_question_diagnostic_get_context_details((int)$source_category->contextid);
                echo html_writer::tag('td', s($ctx->context_name));
                $crumb = local_question_diagnostic_get_question_category_breadcrumb((int)$source_category->id);
                echo html_writer::tag('td', s($crumb . ' (ID: ' . (int)$source_category->id . ')'));
            } else {
                echo html_writer::tag('td', '-');
                echo html_writer::tag('td', '-');
            }

            if ($target_category) {
                $tcrumb = local_question_diagnostic_get_question_category_breadcrumb((int)$target_category->id);
                $turl = local_question_diagnostic_get_question_bank_url($target_category);
                if ($turl) {
                    echo html_writer::tag('td', html_writer::link($turl, s($tcrumb . ' (ID: ' . (int)$target_category->id . ')'), ['target' => '_blank']));
                } else {
                    echo html_writer::tag('td', s($tcrumb . ' (ID: ' . (int)$target_category->id . ')'));
                }
            } else {
                echo html_writer::tag('td', '-');
            }

            echo html_writer::end_tag('tr');
        }

        echo html_writer::end_tag('tbody');
        echo html_writer::end_tag('table');

        // Avertissement.
        echo html_writer::start_div('alert alert-danger');
        echo html_writer::tag('h4', '⚠️ ' . get_string('warning', 'core'));
        echo html_writer::tag('p', get_string('move_selected_warning', 'local_question_diagnostic'));
        echo html_writer::end_div();

        // Boutons de confirmation : repost des ops[].
        echo html_writer::start_tag('form', [
            'method' => 'post',
            'action' => (new moodle_url('/local/question_diagnostic/actions/move_to_olution.php'))->out(false),
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'move_selected']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => 1]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        foreach ($ops as $opraw) {
            $opraw = (string)$opraw;
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'ops[]', 'value' => $opraw]);
        }
        echo html_writer::empty_tag('input', [
            'type' => 'submit',
            'class' => 'btn btn-danger btn-lg mr-2',
            'value' => get_string('confirm', 'core'),
        ]);
        echo html_writer::link($return_url, get_string('cancel', 'core'), ['class' => 'btn btn-secondary btn-lg']);
        echo html_writer::end_tag('form');

        echo $OUTPUT->footer();
        exit;
    }

    // Confirmation donnée : exécuter en batch.
    $result = olution_manager::move_questions_batch($operations);
    $message = get_string('move_batch_result', 'local_question_diagnostic', [
        'success' => $result['success'],
        'failed' => $result['failed'],
    ]);
    if (!empty($result['success']) && empty($result['failed'])) {
        redirect($return_url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
    } else if (!empty($result['success'])) {
        redirect($return_url, $message, null, \core\output\notification::NOTIFY_WARNING);
    } else {
        redirect($return_url, $message, null, \core\output\notification::NOTIFY_ERROR);
    }
}

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
        $qname = format_string($question->name) . ' (ID: ' . (int)$question->id . ')';
        if ($source_category) {
            $qurl = local_question_diagnostic_get_question_bank_url($source_category, (int)$question->id);
            if ($qurl) {
                $qname = html_writer::link($qurl, $qname, ['target' => '_blank']);
            }
        }
        echo html_writer::tag('p', html_writer::tag('strong', get_string('question', 'question')) . ': ' . $qname);
        echo html_writer::tag('p', html_writer::tag('strong', get_string('question_type', 'local_question_diagnostic')) . ': ' . s($question->qtype));

        if ($source_category) {
            $srcctx = local_question_diagnostic_get_context_details((int)$source_category->contextid);
            $srccrumb = local_question_diagnostic_get_question_category_breadcrumb((int)$source_category->id);
            $srccaturl = local_question_diagnostic_get_question_bank_url($source_category);
            $srclabel = $srccrumb . ' (ID: ' . (int)$source_category->id . ')';
            if ($srccaturl) {
                $srclabel = html_writer::link($srccaturl, s($srclabel), ['target' => '_blank']);
            } else {
                $srclabel = s($srclabel);
            }
            echo html_writer::tag('p', html_writer::tag('strong', get_string('from_category', 'local_question_diagnostic')) . ': ' . $srclabel);
            echo html_writer::tag('p', html_writer::tag('strong', get_string('context', 'local_question_diagnostic')) . ': ' . s($srcctx->context_name));
        }

        $tgtctx = local_question_diagnostic_get_context_details((int)$target_category->contextid);
        $tgtcrumb = local_question_diagnostic_get_question_category_breadcrumb((int)$target_category->id);
        $tgturl = local_question_diagnostic_get_question_bank_url($target_category);
        $tgtlabel = $tgtcrumb . ' (ID: ' . (int)$target_category->id . ')';
        if ($tgturl) {
            $tgtlabel = html_writer::link($tgturl, s($tgtlabel), ['target' => '_blank']);
        } else {
            $tgtlabel = s($tgtlabel);
        }
        echo html_writer::tag('p', html_writer::tag('strong', get_string('to_category', 'local_question_diagnostic')) . ': ' . $tgtlabel);
        echo html_writer::tag('p', html_writer::tag('strong', get_string('context', 'local_question_diagnostic')) . ': ' . s($tgtctx->context_name));
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
        
        // Liste des cours sources concernés
        if (!empty($stats->by_source_course)) {
            echo html_writer::tag('h4', get_string('affected_courses', 'local_question_diagnostic'));
            echo html_writer::start_tag('ul');
            foreach ($stats->by_source_course as $course_info) {
                echo html_writer::tag('li', format_string($course_info['course']->fullname) . ' : ' . 
                                      $course_info['count'] . ' ' . get_string('questions', 'question'));
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
    // Préparer les opérations de déplacement
    $operations = [];

    // Traiter par pages de groupes (vraie pagination) pour éviter de charger toute la détection d'un coup.
    $pagesize = 50;
    $offset = 0;
    $totalgroups = 0;
    do {
        $groups = olution_manager::find_all_duplicates_for_olution_paginated($pagesize, $offset, $totalgroups);
        foreach ($groups as $group) {
            // La cible est déjà calculée par olution_manager.
            if (empty($group['target_category'])) {
                continue;
            }

            $target_category = $group['target_category'];

            // Déplacer uniquement les questions HORS Olution vers la catégorie cible.
            foreach ($group['all_questions'] as $q_info) {
                $q = $q_info['question'];
                $cat = $q_info['category'];
                $is_in_olution = !empty($q_info['is_in_olution']);

                if ($is_in_olution) {
                    continue; // Déjà dans Olution (on ne touche pas en masse).
                }

                if ((int)$cat->id === (int)$target_category->id) {
                    continue; // Déjà à la bonne place
                }

                $operations[] = [
                    'questionid' => (int)$q->id,
                    'target_category_id' => (int)$target_category->id
                ];
            }
        }

        $offset += $pagesize;
    } while ($offset < $totalgroups);
    
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

// ===========================================================================
// ACTION : DÉPLACER TOUTES LES QUESTIONS "QUESTION À TRIER" (COMMUN → SOUS-CAT)
// ===========================================================================
else if ($action === 'move_triage_all') {

    // Si pas de confirmation, afficher la page de confirmation.
    if (!$confirm) {
        $PAGE->set_title(get_string('confirm_move_all_triage_to_olution', 'local_question_diagnostic'));
        $PAGE->set_heading(get_string('confirm_move_all_triage_to_olution', 'local_question_diagnostic'));

        echo $OUTPUT->header();

        echo html_writer::tag('h2', get_string('confirm_move_all_triage_to_olution', 'local_question_diagnostic'));

        $triagestats = olution_manager::get_triage_stats();
        $count = !empty($triagestats->movable_questions) ? (int)$triagestats->movable_questions : 0;

        echo html_writer::start_div('alert alert-info');
        echo html_writer::tag('h4', get_string('move_all_details', 'local_question_diagnostic'));
        echo html_writer::tag('p', html_writer::tag('strong', get_string('total_questions_to_move', 'local_question_diagnostic')) . ': ' . $count);
        echo html_writer::end_div();

        echo html_writer::start_div('alert alert-danger');
        echo html_writer::tag('h4', '⚠️ ' . get_string('warning', 'core'));
        echo html_writer::tag('p', get_string('triage_move_all_warning', 'local_question_diagnostic'));
        echo html_writer::end_div();

        $confirm_url = new moodle_url('/local/question_diagnostic/actions/move_to_olution.php', [
            'action' => 'move_triage_all',
            'confirm' => 1,
            'sesskey' => sesskey()
        ]);

        echo html_writer::link(
            $confirm_url,
            get_string('confirm', 'core'),
            ['class' => 'btn btn-danger btn-lg mr-2']
        );
        echo html_writer::link(
            $return_url,
            get_string('cancel', 'core'),
            ['class' => 'btn btn-secondary btn-lg']
        );

        echo $OUTPUT->footer();
        exit;
    }

    // Confirmation donnée : effectuer le déplacement des questions triables.
    $triagestats = olution_manager::get_triage_stats();
    $total = !empty($triagestats->movable_questions) ? (int)$triagestats->movable_questions : 0;
    if ($total <= 0) {
        redirect($return_url, get_string('no_movable_questions', 'local_question_diagnostic'),
            null, \core\output\notification::NOTIFY_WARNING);
    }

    $pagesize = 100;
    $success = 0;
    $failed = 0;
    $errors = [];

    // IMPORTANT: ne pas paginer avec un offset qui avance sur un dataset qui change,
    // sinon on risque de "sauter" des questions après déplacement.
    // On récupère toujours la première page et on recommence jusqu'à épuisement.
    $loops = 0;
    $maxloops = 1000; // garde-fou
    while ($loops < $maxloops) {
        $page_total = 0;
        $candidates = olution_manager::get_triage_move_candidates_paginated($pagesize, 0, $page_total);
        if (empty($candidates)) {
            break;
        }

        $operations = [];
        foreach ($candidates as $cand) {
            $q = $cand['question'];
            $target = $cand['target_category'];
            if (empty($q->id) || empty($target->id)) {
                continue;
            }
            $operations[] = [
                'questionid' => (int)$q->id,
                'target_category_id' => (int)$target->id
            ];
        }

        if (!empty($operations)) {
            $result = olution_manager::move_questions_batch($operations);
            $success += (int)($result['success'] ?? 0);
            $failed += (int)($result['failed'] ?? 0);
            if (!empty($result['errors'])) {
                $errors = array_merge($errors, (array)$result['errors']);
            }

            // Si aucune question n'a pu être déplacée, on évite une boucle infinie.
            if ((int)($result['success'] ?? 0) === 0) {
                break;
            }
        }

        $loops++;
    }

    $message = get_string('move_batch_result', 'local_question_diagnostic', [
        'success' => $success,
        'failed' => $failed
    ]);

    if ($success > 0 && $failed == 0) {
        redirect($return_url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
    } else if ($success > 0) {
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

