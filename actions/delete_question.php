<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Suppression sécurisée de questions individuelles
 * 
 * Règles de protection strictes :
 * - Question utilisée dans un quiz → INTERDITE
 * - Question avec tentatives → INTERDITE
 * - Question unique (pas de doublon) → INTERDITE
 * - Question en doublon ET inutilisée → AUTORISÉE
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../classes/question_analyzer.php');

use local_question_diagnostic\question_analyzer;

require_login();
require_sesskey();

if (!is_siteadmin()) {
    print_error('accessdenied', 'admin');
}

// 🆕 v1.9.23 : Support suppression en masse
$questionid = optional_param('id', 0, PARAM_INT);
$questionids_param = optional_param('ids', '', PARAM_TEXT);
$confirm = optional_param('confirm', 0, PARAM_INT);
$returnurl = new moodle_url('/local/question_diagnostic/questions_cleanup.php', ['loadstats' => 1, 'show' => 50]);

// Déterminer si c'est une suppression unique ou en masse
$question_ids = [];
$is_bulk = false;

if (!empty($questionids_param)) {
    // Suppression en masse
    $is_bulk = true;
    $question_ids = array_map('intval', explode(',', $questionids_param));
    $question_ids = array_filter($question_ids, function($id) { return $id > 0; });
} else if ($questionid > 0) {
    // Suppression unique
    $question_ids = [$questionid];
} else {
    print_error('invalidparameter', 'error');
}

// 🆕 v1.9.23 : Vérifier toutes les questions en batch
$deletability_map = question_analyzer::can_delete_questions_batch($question_ids);

// Filtrer les questions non supprimables
$cannot_delete = [];
$can_delete = [];

foreach ($question_ids as $qid) {
    if (isset($deletability_map[$qid])) {
        if ($deletability_map[$qid]->can_delete) {
            $can_delete[] = $qid;
        } else {
            $cannot_delete[$qid] = $deletability_map[$qid]->reason;
        }
    } else {
        $cannot_delete[$qid] = 'Vérification impossible';
    }
}

// Si TOUTES les questions sont non supprimables
if (empty($can_delete)) {
    // INTERDICTION DE SUPPRIMER - Afficher la raison
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url(new moodle_url('/local/question_diagnostic/actions/delete_question.php'));
    $PAGE->set_title(get_string('delete_question_forbidden', 'local_question_diagnostic'));
    
    echo $OUTPUT->header();
    echo $OUTPUT->heading('🛑 ' . get_string('delete_question_forbidden', 'local_question_diagnostic'));
    
    // Message d'erreur principal
    echo html_writer::start_tag('div', ['class' => 'alert alert-danger', 'style' => 'margin: 20px 0; padding: 20px;']);
    echo html_writer::tag('h3', '❌ ' . get_string('cannot_delete_question', 'local_question_diagnostic'), ['style' => 'margin-top: 0;']);
    
    if ($is_bulk) {
        echo html_writer::tag('p', 'Aucune des <strong>' . count($question_ids) . ' question(s) sélectionnée(s)</strong> ne peut être supprimée.', ['style' => 'font-size: 16px;']);
        echo html_writer::tag('h4', 'Raisons :', ['style' => 'margin-top: 15px;']);
        echo html_writer::start_tag('ul');
        foreach ($cannot_delete as $qid => $reason) {
            echo html_writer::tag('li', '<strong>Question ' . $qid . ' :</strong> ' . $reason);
        }
        echo html_writer::end_tag('ul');
    } else {
        $first_reason = reset($cannot_delete);
        echo html_writer::tag('p', '<strong>' . get_string('reason', 'local_question_diagnostic') . '</strong> : ' . $first_reason, ['style' => 'font-size: 16px;']);
    }
    echo html_writer::end_tag('div');
    
    // Détails spécifiques selon la raison
    if (isset($check->details['quiz_count']) && $check->details['quiz_count'] > 0) {
        echo html_writer::start_tag('div', ['class' => 'alert alert-warning']);
        echo html_writer::tag('h4', '📋 Détails de l\'utilisation');
        echo html_writer::tag('p', '<strong>Quiz utilisant cette question :</strong> ' . $check->details['quiz_count']);
        
        if (!empty($check->details['quiz_list'])) {
            echo html_writer::start_tag('ul');
            foreach ($check->details['quiz_list'] as $quiz) {
                $quiz_url = new moodle_url('/mod/quiz/view.php', ['id' => $quiz->id]);
                echo html_writer::tag('li', html_writer::link($quiz_url, format_string($quiz->name), ['target' => '_blank']));
            }
            echo html_writer::end_tag('ul');
        }
        
        if (isset($check->details['attempt_count']) && $check->details['attempt_count'] > 0) {
            echo html_writer::tag('p', '<strong>Tentatives enregistrées :</strong> ' . $check->details['attempt_count']);
        }
        echo html_writer::end_tag('div');
    }
    
    if (isset($check->details['is_unique']) && $check->details['is_unique']) {
        echo html_writer::start_tag('div', ['class' => 'alert alert-info']);
        echo html_writer::tag('h4', '💡 Question Unique');
        echo html_writer::tag('p', 'Cette question n\'a <strong>aucun doublon</strong> dans votre base de données. ');
        echo html_writer::tag('p', 'La suppression de questions uniques est <strong>interdite par sécurité</strong> pour éviter la perte de contenu pédagogique.');
        echo html_writer::end_tag('div');
    }
    
    // Règles de protection
    echo html_writer::start_tag('div', ['style' => 'margin: 30px 0; padding: 20px; background: #e7f3ff; border-left: 4px solid #0f6cbf;']);
    echo html_writer::tag('h4', '🛡️ ' . get_string('protection_rules', 'local_question_diagnostic'), ['style' => 'margin-top: 0;']);
    echo html_writer::tag('p', get_string('protection_rules_desc', 'local_question_diagnostic'));
    echo html_writer::start_tag('ol', ['style' => 'margin-top: 15px;']);
    echo html_writer::tag('li', '✅ ' . get_string('rule_used_protected', 'local_question_diagnostic'), ['style' => 'margin: 10px 0;']);
    echo html_writer::tag('li', '✅ ' . get_string('rule_unique_protected', 'local_question_diagnostic'), ['style' => 'margin: 10px 0;']);
    echo html_writer::tag('li', '⚠️ ' . get_string('rule_duplicate_deletable', 'local_question_diagnostic'), ['style' => 'margin: 10px 0;']);
    echo html_writer::end_tag('ol');
    echo html_writer::end_tag('div');
    
    // Bouton retour
    echo html_writer::start_tag('div', ['style' => 'margin-top: 30px;']);
    echo html_writer::link($returnurl, '← ' . get_string('backtoquestions', 'local_question_diagnostic'), ['class' => 'btn btn-secondary btn-lg']);
    echo html_writer::end_tag('div');
    
    echo $OUTPUT->footer();
    exit;
}

// Si on arrive ici : la suppression est AUTORISÉE
// Demander confirmation

// Avertir s'il y a des questions non supprimables dans la sélection
if (!empty($cannot_delete) && !empty($can_delete)) {
    // Certaines sont supprimables, d'autres non
    debugging('Suppression partielle : ' . count($can_delete) . ' sur ' . count($question_ids) . ' questions peuvent être supprimées', DEBUG_DEVELOPER);
}

if (!$confirm) {
    // PAGE DE CONFIRMATION
    global $DB;
    
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url(new moodle_url('/local/question_diagnostic/actions/delete_question.php'));
    $PAGE->set_title(get_string('confirm_delete_question', 'local_question_diagnostic'));
    
    echo $OUTPUT->header();
    
    if ($is_bulk) {
        echo $OUTPUT->heading('⚠️ Confirmation de Suppression en Masse');
    } else {
        echo $OUTPUT->heading('⚠️ ' . get_string('confirm_delete_question', 'local_question_diagnostic'));
    }
    
    // Informations sur la question à supprimer
    echo html_writer::start_tag('div', ['class' => 'alert alert-warning', 'style' => 'margin: 20px 0;']);
    echo html_writer::tag('h3', get_string('question_to_delete', 'local_question_diagnostic'), ['style' => 'margin-top: 0;']);
    echo html_writer::tag('p', '<strong>ID :</strong> ' . $question->id);
    echo html_writer::tag('p', '<strong>' . get_string('name') . ' :</strong> ' . format_string($question->name));
    echo html_writer::tag('p', '<strong>' . get_string('type') . ' :</strong> ' . $question->qtype);
    echo html_writer::tag('p', '<strong>' . get_string('category') . ' :</strong> ' . $stats->category_name);
    if (!empty($stats->course_name)) {
        echo html_writer::tag('p', '<strong>' . get_string('course') . ' :</strong> ' . $stats->course_name);
    }
    echo html_writer::tag('p', '<strong>' . get_string('created', 'moodle') . ' :</strong> ' . $stats->created_formatted);
    echo html_writer::end_tag('div');
    
    // Informations sur les doublons
    echo html_writer::start_tag('div', ['class' => 'alert alert-info']);
    echo html_writer::tag('h4', '🔀 ' . get_string('duplicate_info', 'local_question_diagnostic'));
    echo html_writer::tag('p', 'Cette question a <strong>' . $check->details['duplicate_count'] . ' doublon(s)</strong> dans la base de données.');
    echo html_writer::tag('p', 'Les autres versions de cette question seront conservées.');
    echo html_writer::end_tag('div');
    
    // AVERTISSEMENT IRRÉVERSIBLE
    echo html_writer::start_tag('div', ['class' => 'alert alert-danger', 'style' => 'border-left: 4px solid #d9534f;']);
    echo html_writer::tag('h4', '⚠️ ATTENTION', ['style' => 'margin-top: 0; color: #721c24;']);
    echo html_writer::tag('p', '<strong>' . get_string('action_irreversible', 'local_question_diagnostic') . '</strong>');
    echo html_writer::tag('p', get_string('confirm_delete_message', 'local_question_diagnostic'));
    echo html_writer::end_tag('div');
    
    // BOUTONS : Confirmer + Annuler
    echo html_writer::start_tag('div', ['style' => 'margin-top: 30px; display: flex; gap: 20px;']);
    
    // Formulaire de confirmation
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => new moodle_url('/local/question_diagnostic/actions/delete_question.php'),
        'style' => 'display: inline;'
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $questionid]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => '1']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'value' => '🗑️ ' . get_string('confirm_delete', 'local_question_diagnostic'),
        'class' => 'btn btn-danger btn-lg'
    ]);
    echo html_writer::end_tag('form');
    
    echo html_writer::link($returnurl, '← ' . get_string('cancel'), ['class' => 'btn btn-secondary btn-lg']);
    echo html_writer::end_tag('div');
    
    echo $OUTPUT->footer();
    exit;
}

// EXÉCUTION DE LA SUPPRESSION (après confirmation)
$result = question_analyzer::delete_question_safe($questionid);

if ($result === true) {
    // SUCCÈS
    question_analyzer::purge_all_caches();
    redirect(
        $returnurl,
        '✅ ' . get_string('question_deleted_success', 'local_question_diagnostic'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
} else {
    // ERREUR
    redirect(
        $returnurl,
        '❌ ' . get_string('error') . ' : ' . $result,
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}


