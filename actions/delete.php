<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Suppression de catégories de questions VIDES (une ou plusieurs).
 *
 * - GET : page de confirmation (aucune écriture) ;
 * - POST + sesskey + confirm : suppression par l'API du cœur, après contrôle complet
 *   (category_manager::get_deletion_refusal) de chaque catégorie.
 *
 * Suppression multiple : la liste est figée au lancement (seules les catégories supprimables à cet instant
 * sont retenues, pas de cascade). Jusqu'à MAX_BULK_DELETE_CATEGORIES catégories, la suppression est faite dans
 * la requête ; au-delà, elle est confiée à une tâche de fond (cleanup_categories_task).
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/../classes/category_manager.php');
require_once(__DIR__ . '/../classes/question_analyzer.php');

use local_question_diagnostic\category_manager;
use local_question_diagnostic\question_analyzer;

// Contrôle d'accès : capacité du plugin + administrateur du site (écriture).
local_question_diagnostic_require_access('deletecategories', true);
// Le sesskey n'est exigé qu'à l'exécution (POST) : la confirmation s'affiche depuis un simple lien GET.

// Au-delà de cette limite, la suppression multiple passe en tâche de fond.
define('MAX_BULK_DELETE_CATEGORIES', 100);
// Limite absolue d'identifiants acceptés.
define('MAX_BULK_DELETE_CATEGORIES_JOB', 5000);

// POST pour éviter « Request-URI Too Long » ; GET accepté pour l'affichage de la confirmation.
$categoryid = optional_param('id', 0, PARAM_INT);
$categoryids = optional_param('ids', '', PARAM_SEQUENCE);
$confirm = optional_param('confirm', 0, PARAM_INT);
$returnurl = local_question_diagnostic_get_parent_url('actions/delete.php');

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/question_diagnostic/actions/delete.php'));
$PAGE->set_title('Confirmation de suppression');

/**
 * Affiche une liste de messages d'erreur (échappés), limitée.
 *
 * @param array<int,string> $messages id => message
 * @param int $max
 * @return string
 */
function local_question_diagnostic_delete_errors_html(array $messages, int $max = 10): string {
    $lines = [];
    foreach (array_slice($messages, 0, $max, true) as $id => $msg) {
        $lines[] = s('Catégorie ' . (int)$id . ' : ' . $msg);
    }
    if (count($messages) > $max) {
        $lines[] = '…';
    }
    return implode('<br>', $lines);
}

// Suppression multiple.
if ($categoryids !== '') {
    $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $categoryids)), fn($v) => $v > 0)));
    if (empty($ids)) {
        redirect($returnurl);
    }
    if (count($ids) > MAX_BULK_DELETE_CATEGORIES_JOB) {
        redirect($returnurl, 'Trop de catégories sélectionnées. Maximum autorisé : ' . MAX_BULK_DELETE_CATEGORIES_JOB, null,
            \core\output\notification::NOTIFY_ERROR);
    }

    if ($confirm && data_submitted()) {
        require_sesskey();
        // Liste figée maintenant : seules les catégories supprimables à cet instant sont retenues.
        [$eligible, $refused] = category_manager::freeze_deletable_ids($ids);

        if (count($eligible) > MAX_BULK_DELETE_CATEGORIES) {
            \local_question_diagnostic\task\cleanup_categories_task::queue($eligible, 'bulk_delete');
            $message = get_string('delete_categories_queued', 'local_question_diagnostic', (object)[
                'queued' => count($eligible),
                'refused' => count($refused),
            ]);
            redirect($returnurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);
        }

        $result = category_manager::delete_frozen_category_list($eligible);
        if (!empty($result->deleted)) {
            question_analyzer::purge_all_caches();
        }
        $errors = $refused + $result->skipped;
        $message = '✅ ' . count($result->deleted) . ' catégorie(s) supprimée(s).';
        if (!empty($errors)) {
            $message .= '<br>⚠️ ' . count($errors) . ' catégorie(s) non supprimée(s) :<br>'
                . local_question_diagnostic_delete_errors_html($errors);
        }
        $type = empty($errors) ? \core\output\notification::NOTIFY_SUCCESS
            : (empty($result->deleted) ? \core\output\notification::NOTIFY_ERROR : \core\output\notification::NOTIFY_WARNING);
        redirect($returnurl, $message, null, $type);
    }

    // Confirmation (formulaire POST).
    echo $OUTPUT->header();
    echo local_question_diagnostic_render_version_badge();
    echo $OUTPUT->heading('⚠️ Confirmation de suppression');
    echo html_writer::tag('p', 'Vous êtes sur le point de supprimer <strong>' . count($ids) . ' catégorie(s)</strong>.');
    echo html_writer::tag('p', 'Cette action est irréversible. Êtes-vous sûr ?');
    echo html_writer::div(get_string('delete_categories_rules', 'local_question_diagnostic'), 'alert alert-info');
    if (count($ids) > MAX_BULK_DELETE_CATEGORIES) {
        echo html_writer::div(get_string('delete_categories_background', 'local_question_diagnostic',
            MAX_BULK_DELETE_CATEGORIES), 'alert alert-warning');
    }

    echo html_writer::start_tag('div', ['style' => 'margin-top: 20px;']);
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => (new moodle_url('/local/question_diagnostic/actions/delete.php'))->out(false),
        'style' => 'display: inline;',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'ids', 'value' => implode(',', $ids)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => '1']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => 'Oui, supprimer', 'class' => 'btn btn-danger']);
    echo html_writer::end_tag('form');
    echo ' ';
    echo html_writer::link($returnurl, 'Annuler', ['class' => 'btn btn-secondary']);
    echo html_writer::end_tag('div');

    echo $OUTPUT->footer();
    exit;
}

// Suppression simple.
if ($categoryid) {
    if ($confirm && data_submitted()) {
        require_sesskey();
        $result = category_manager::delete_category($categoryid);
        if ($result === true) {
            question_analyzer::purge_all_caches();
            redirect($returnurl, '✅ Catégorie supprimée avec succès.', null, \core\output\notification::NOTIFY_SUCCESS);
        } else {
            redirect($returnurl, '⚠️ Erreur : ' . s($result), null, \core\output\notification::NOTIFY_ERROR);
        }
    }

    $category = $DB->get_record('question_categories', ['id' => $categoryid]);
    if (!$category) {
        redirect($returnurl, 'Catégorie introuvable.', null, \core\output\notification::NOTIFY_ERROR);
    }
    $refusal = category_manager::get_deletion_refusal($category);

    echo $OUTPUT->header();
    echo local_question_diagnostic_render_version_badge();
    echo $OUTPUT->heading('⚠️ Confirmation de suppression');
    echo html_writer::tag('p', 'Vous êtes sur le point de supprimer la catégorie : <strong>'
        . format_string($category->name, true, ['context' => context_system::instance()])
        . '</strong> (ID: ' . (int)$categoryid . ')');

    echo html_writer::start_tag('div', ['style' => 'margin-top: 20px;']);
    if ($refusal !== null) {
        echo html_writer::div(s($refusal), 'alert alert-danger');
    } else {
        echo html_writer::tag('p', 'Cette action est irréversible. Êtes-vous sûr ?');
        echo html_writer::start_tag('form', [
            'method' => 'post',
            'action' => (new moodle_url('/local/question_diagnostic/actions/delete.php'))->out(false),
            'style' => 'display: inline;',
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => (int)$categoryid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => '1']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => 'Oui, supprimer', 'class' => 'btn btn-danger']);
        echo html_writer::end_tag('form');
        echo ' ';
    }
    echo html_writer::link($returnurl, 'Annuler', ['class' => 'btn btn-secondary']);
    echo html_writer::end_tag('div');

    echo $OUTPUT->footer();
    exit;
}

redirect($returnurl);
