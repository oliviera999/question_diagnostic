<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Page de triage des questions (commun → sous-catégories)
 *
 * Permet de traiter les questions placées dans la sous-catégorie :
 *   commun > "Question à trier"
 *
 * et de les déplacer vers la sous-catégorie correspondante si un doublon (name+type)
 * existe ailleurs dans commun (hors "Question à trier").
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

$PAGE->set_url(new moodle_url('/local/question_diagnostic/olution_triage.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('olution_triage_title', 'local_question_diagnostic'));
$PAGE->set_heading(get_string('olution_triage_heading', 'local_question_diagnostic'));
$PAGE->set_pagelayout('admin');

// Pagination.
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 50, PARAM_INT);
$perpage = max(10, min(200, (int)$perpage));
$page = max(0, (int)$page);

echo $OUTPUT->header();

// Badge de version.
echo local_question_diagnostic_render_version_badge();

// Bouton purge cache.
echo html_writer::start_div('text-right', ['style' => 'margin-bottom: 20px;']);
echo local_question_diagnostic_render_cache_purge_button();
echo html_writer::end_div();

// Bouton retour.
echo html_writer::div(
    local_question_diagnostic_render_back_link('olution_triage.php'),
    'mb-3'
);

echo html_writer::tag('h2', get_string('olution_triage_title', 'local_question_diagnostic'));

$stats = olution_manager::get_triage_stats();

if (empty($stats->triage_exists)) {
    echo $OUTPUT->notification(
        get_string('olution_triage_not_found', 'local_question_diagnostic'),
        \core\output\notification::NOTIFY_WARNING
    );
    echo html_writer::tag('p', get_string('olution_triage_not_found_help', 'local_question_diagnostic'));
    echo $OUTPUT->footer();
    exit;
}

// Bandeau info.
echo html_writer::start_div('alert alert-info mb-3');
echo html_writer::tag('strong', '✅ ' . get_string('olution_triage_detected', 'local_question_diagnostic'));
echo ' '; 
echo html_writer::tag('span', format_string($stats->triage_name));
echo html_writer::tag('small', ' (ID: ' . (int)$stats->triage_id . ')', ['class' => 'text-muted ml-2']);
echo html_writer::empty_tag('br');
echo html_writer::tag('small', get_string('olution_triage_signatures', 'local_question_diagnostic', (int)$stats->signatures), ['class' => 'text-muted']);
echo html_writer::end_div();

// Stats + action globale.
echo html_writer::start_div('row mb-4');

echo html_writer::start_div('col-md-4');
echo html_writer::start_div('card qd-card');
echo html_writer::start_div('card-body text-center');
echo html_writer::tag('h3', (int)$stats->movable_questions, ['class' => 'text-primary']);
echo html_writer::tag('p', get_string('olution_triage_movable_questions', 'local_question_diagnostic'), ['class' => 'mb-0']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('col-md-8');
echo html_writer::start_div('card qd-card');
echo html_writer::start_div('card-body');
echo html_writer::tag('p', get_string('olution_triage_explain', 'local_question_diagnostic'), ['class' => 'mb-3']);

if ((int)$stats->movable_questions > 0) {
    $move_all_url = new moodle_url('/local/question_diagnostic/actions/move_to_olution.php', [
        'action' => 'move_triage_all',
        'sesskey' => sesskey(),
    ]);
    echo html_writer::link(
        $move_all_url,
        get_string('olution_triage_move_all_button', 'local_question_diagnostic', (int)$stats->movable_questions),
        ['class' => 'btn btn-primary btn-lg']
    );
} else {
    echo $OUTPUT->notification(
        get_string('olution_triage_no_movable', 'local_question_diagnostic'),
        \core\output\notification::NOTIFY_INFO
    );
}

echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div();

// Liste.
$offset = $page * $perpage;
$total = 0;
$candidates = olution_manager::get_triage_move_candidates_paginated($perpage, $offset, $total);

echo html_writer::tag('h3', get_string('olution_triage_list_title', 'local_question_diagnostic'));

if (empty($candidates)) {
    echo $OUTPUT->notification(
        get_string('olution_triage_no_candidates_page', 'local_question_diagnostic'),
        \core\output\notification::NOTIFY_INFO
    );
} else {
    echo html_writer::start_tag('table', ['class' => 'table table-sm table-striped']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('question_id', 'local_question_diagnostic'));
    echo html_writer::tag('th', get_string('question_name', 'local_question_diagnostic'));
    echo html_writer::tag('th', get_string('question_type', 'local_question_diagnostic'));
    echo html_writer::tag('th', get_string('olution_target_category', 'local_question_diagnostic'));
    echo html_writer::tag('th', get_string('actions', 'local_question_diagnostic'));
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');

    echo html_writer::start_tag('tbody');
    foreach ($candidates as $cand) {
        $q = $cand['question'];
        $target = $cand['target_category'];

        $move_url = new moodle_url('/local/question_diagnostic/actions/move_to_olution.php', [
            'action' => 'move_one',
            'questionid' => (int)$q->id,
            'targetcatid' => (int)$target->id,
            'sesskey' => sesskey(),
        ]);

        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', (int)$q->id);
        echo html_writer::tag('td', format_string($q->name));
        echo html_writer::tag('td', s($q->qtype));
        echo html_writer::tag('td', format_string($target->name) . ' (ID: ' . (int)$target->id . ')');
        echo html_writer::start_tag('td');
        echo html_writer::link($move_url, get_string('move', 'local_question_diagnostic'), ['class' => 'btn btn-sm btn-primary']);
        echo html_writer::end_tag('td');
        echo html_writer::end_tag('tr');
    }
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');

    if ($total > $perpage) {
        $baseurl = new moodle_url('/local/question_diagnostic/olution_triage.php', ['perpage' => $perpage]);
        echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);
    }
}

echo $OUTPUT->footer();
