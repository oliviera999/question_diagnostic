<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Suppression en masse de questions (doublons inutilisés)
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

// Récupérer les IDs des questions à supprimer
$questionids_param = required_param('ids', PARAM_TEXT);
$confirm = optional_param('confirm', 0, PARAM_INT);
$returnurl = new moodle_url('/local/question_diagnostic/questions_cleanup.php', ['randomtest_used' => 1, 'sesskey' => sesskey()]);

// Parser les IDs
$question_ids = array_map('intval', explode(',', $questionids_param));
$question_ids = array_filter($question_ids, function($id) { return $id > 0; });

if (empty($question_ids)) {
    print_error('invalidparameter', 'error');
}

// Vérifier toutes les questions en batch
$deletability_map = question_analyzer::can_delete_questions_batch($question_ids);

// Filtrer les questions supprimables
$can_delete = [];
$cannot_delete = [];

foreach ($question_ids as $qid) {
    if (isset($deletability_map[$qid]) && $deletability_map[$qid]->can_delete) {
        $can_delete[] = $qid;
    } else {
        $reason = isset($deletability_map[$qid]) ? $deletability_map[$qid]->reason : 'Vérification impossible';
        $cannot_delete[$qid] = $reason;
    }
}

// Si aucune question ne peut être supprimée
if (empty($can_delete)) {
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url(new moodle_url('/local/question_diagnostic/actions/delete_questions_bulk.php'));
    $PAGE->set_title('Suppression Interdite');
    
    echo $OUTPUT->header();
    echo $OUTPUT->heading('🛑 Suppression Interdite');
    
    echo html_writer::start_tag('div', ['class' => 'alert alert-danger']);
    echo html_writer::tag('h3', 'Aucune des ' . count($question_ids) . ' question(s) ne peut être supprimée');
    echo html_writer::tag('h4', 'Raisons :', ['style' => 'margin-top: 15px;']);
    echo html_writer::start_tag('ul');
    foreach ($cannot_delete as $qid => $reason) {
        echo html_writer::tag('li', '<strong>Question ' . $qid . ' :</strong> ' . $reason);
    }
    echo html_writer::end_tag('ul');
    echo html_writer::end_tag('div');
    
    echo html_writer::start_tag('div', ['style' => 'margin-top: 30px;']);
    echo html_writer::link($returnurl, '← Retour', ['class' => 'btn btn-secondary btn-lg']);
    echo html_writer::end_tag('div');
    
    echo $OUTPUT->footer();
    exit;
}

// PAGE DE CONFIRMATION
if (!$confirm) {
    global $DB;
    
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url(new moodle_url('/local/question_diagnostic/actions/delete_questions_bulk.php'));
    $PAGE->set_title('Confirmation de Suppression en Masse');
    
    echo $OUTPUT->header();
    echo $OUTPUT->heading('⚠️ Confirmation de Suppression en Masse');
    
    // Message principal
    echo html_writer::start_tag('div', ['class' => 'alert alert-warning', 'style' => 'margin: 20px 0;']);
    echo html_writer::tag('h3', 'Questions à supprimer', ['style' => 'margin-top: 0;']);
    echo html_writer::tag('p', 'Vous êtes sur le point de supprimer <strong>' . count($can_delete) . ' question(s)</strong>.');
    echo html_writer::end_tag('div');
    
    // Afficher questions non supprimables (si applicable)
    if (!empty($cannot_delete)) {
        echo html_writer::start_tag('div', ['class' => 'alert alert-info']);
        echo html_writer::tag('h4', '💡 Questions ignorées (' . count($cannot_delete) . ')');
        echo html_writer::tag('p', 'Ces questions NE SERONT PAS supprimées car elles sont protégées :');
        echo html_writer::start_tag('ul', ['style' => 'font-size: 12px;']);
        foreach ($cannot_delete as $qid => $reason) {
            echo html_writer::tag('li', 'Question ' . $qid . ' : ' . $reason);
        }
        echo html_writer::end_tag('ul');
        echo html_writer::end_tag('div');
    }
    
    // Liste des questions à supprimer
    echo html_writer::start_tag('div', ['class' => 'alert alert-secondary']);
    echo html_writer::tag('h4', '🗑️ Questions qui seront supprimées (' . count($can_delete) . ')');
    
    $questions_to_delete = $DB->get_records_list('question', 'id', $can_delete, '', 'id, name, qtype');
    echo html_writer::start_tag('table', ['class' => 'table table-sm', 'style' => 'font-size: 12px;']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'ID');
    echo html_writer::tag('th', 'Nom');
    echo html_writer::tag('th', 'Type');
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');
    
    foreach ($questions_to_delete as $q) {
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', $q->id);
        echo html_writer::tag('td', format_string($q->name));
        echo html_writer::tag('td', $q->qtype);
        echo html_writer::end_tag('tr');
    }
    
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_tag('div');
    
    // AVERTISSEMENT IRRÉVERSIBLE
    echo html_writer::start_tag('div', ['class' => 'alert alert-danger', 'style' => 'border-left: 4px solid #d9534f;']);
    echo html_writer::tag('h4', '⚠️ ATTENTION', ['style' => 'margin-top: 0; color: #721c24;']);
    echo html_writer::tag('p', '<strong>' . get_string('action_irreversible', 'local_question_diagnostic') . '</strong>');
    echo html_writer::tag('p', 'Les ' . count($can_delete) . ' questions sélectionnées seront définitivement supprimées de la base de données.');
    echo html_writer::end_tag('div');
    
    // BOUTONS : Confirmer + Annuler
    echo html_writer::start_tag('div', ['style' => 'margin-top: 30px; display: flex; gap: 20px;']);
    
    // Formulaire de confirmation
    $confirm_url = new moodle_url('/local/question_diagnostic/actions/delete_questions_bulk.php', [
        'ids' => implode(',', $can_delete),
        'confirm' => 1,
        'sesskey' => sesskey()
    ]);
    
    echo html_writer::link($confirm_url, '🗑️ Confirmer la Suppression (' . count($can_delete) . ')', [
        'class' => 'btn btn-danger btn-lg'
    ]);
    
    echo html_writer::link($returnurl, '← Annuler', ['class' => 'btn btn-secondary btn-lg']);
    echo html_writer::end_tag('div');
    
    echo $OUTPUT->footer();
    exit;
}

// EXÉCUTION DE LA SUPPRESSION (après confirmation)
$success_count = 0;
$error_count = 0;
$errors = [];

foreach ($can_delete as $qid) {
    $result = question_analyzer::delete_question_safe($qid);
    
    if ($result === true) {
        $success_count++;
    } else {
        $error_count++;
        $errors[$qid] = $result;
    }
}

// Purger le cache
question_analyzer::purge_all_caches();

// Redirection avec message
if ($error_count == 0) {
    // SUCCÈS TOTAL
    redirect(
        $returnurl,
        '✅ ' . $success_count . ' question(s) supprimée(s) avec succès !',
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
} else {
    // SUCCÈS PARTIEL ou ERREUR
    $message = '⚠️ Suppression partielle : ' . $success_count . ' réussie(s), ' . $error_count . ' échec(s)';
    redirect(
        $returnurl,
        $message,
        null,
        \core\output\notification::NOTIFY_WARNING
    );
}

