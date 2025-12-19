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
 * Action : fusion d'un groupe de doublons strictement identiques (preview + exécution).
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/../classes/question_analyzer.php');
require_once(__DIR__ . '/../classes/question_merger.php');

use local_question_diagnostic\question_analyzer;
use local_question_diagnostic\question_merger;

require_login();
require_sesskey();

if (!is_siteadmin()) {
    print_error('accessdenied', 'admin');
}

$repid = required_param('repid', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);
$includequiz = optional_param('includequiz', 1, PARAM_INT);
$discovery = optional_param('discovery', 0, PARAM_INT);
$returnurlparam = optional_param('returnurl', '', PARAM_LOCALURL);

$returnurl = !empty($returnurlparam)
    ? new moodle_url($returnurlparam)
    : new moodle_url('/local/question_diagnostic/question_merge.php');

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/question_diagnostic/actions/merge_questions.php', [
    'repid' => $repid,
    'includequiz' => $includequiz,
    'discovery' => $discovery,
    'returnurl' => $returnurl->out(false),
]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('question_merge_title', 'local_question_diagnostic'));
$PAGE->set_heading(get_string('question_merge_title', 'local_question_diagnostic'));

$options = [
    // include_quiz_references est toujours actif : les questions utilisées dans des quiz
    // mais sans tentatives sont fusionnables et les références quiz seront remappées.
    'include_quiz_references' => true,
    'advanced_discovery' => (int)$discovery === 1,
];

// Toujours reconstruire le plan depuis la BDD (preview ou exécution).
$plan = question_merger::build_merge_plan((int)$repid, $options);

if (!$confirm) {
    echo $OUTPUT->header();
    echo local_question_diagnostic_render_version_badge();

    echo $OUTPUT->heading(get_string('question_merge_preview_heading', 'local_question_diagnostic'));

    if (!empty($plan->errors)) {
        echo $OUTPUT->notification(implode('<br>', array_map('s', (array)$plan->errors)), \core\output\notification::NOTIFY_ERROR);
        echo html_writer::div(html_writer::link($returnurl, get_string('backtomenu', 'local_question_diagnostic'), ['class' => 'btn btn-secondary']), 'mt-3');
        echo $OUTPUT->footer();
        exit;
    }

    // Résumé.
    echo html_writer::start_div('alert alert-info');
    echo html_writer::tag('h4', get_string('question_merge_summary_title', 'local_question_diagnostic'));
    echo html_writer::tag('p', get_string('question_merge_summary', 'local_question_diagnostic', (object)[
        'groupname' => s((string)($plan->group->name ?? '')),
        'qtype' => s((string)($plan->group->qtype ?? '')),
        'count' => (int)($plan->group->size ?? 0),
        'referenceid' => (int)($plan->reference_questionid ?? 0),
        'mergecount' => count((array)($plan->mergeable_questionids ?? [])),
    ]));
    echo html_writer::end_div();

    // Warnings.
    if (!empty($plan->warnings)) {
        echo html_writer::start_div('alert alert-warning');
        echo html_writer::tag('h4', get_string('warning', 'core'));
        echo html_writer::start_tag('ul');
        foreach ((array)$plan->warnings as $w) {
            echo html_writer::tag('li', s((string)$w));
        }
        echo html_writer::end_tag('ul');
        echo html_writer::end_div();
    }

    // Table des questions.
    echo html_writer::tag('h4', get_string('question_merge_questions_list', 'local_question_diagnostic'));
    $table = new html_table();
    $table->head = [
        'ID',
        get_string('question_merge_open_in_context', 'local_question_diagnostic'),
        get_string('name'),
        get_string('type', 'local_question_diagnostic'),
        get_string('column_attempts', 'local_question_diagnostic'),
        get_string('question_merge_column_quiz_count', 'local_question_diagnostic'),
        get_string('category'),
        'contextid',
    ];
    $table->data = [];

    $mergeable = array_fill_keys(array_map('intval', (array)($plan->mergeable_questionids ?? [])), true);
    $refid = (int)$plan->reference_questionid;
    foreach ((array)$plan->questions as $qid => $info) {
        $qid = (int)$qid;
        $classes = [];
        if ($qid === $refid) {
            $classes[] = 'table-success';
        } else if (isset($mergeable[$qid])) {
            $classes[] = 'table-warning';
        }
        $catname = '';
        $catid = 0;
        $ctxid = 0;
        $openlink = '-';
        if (!empty($info->context)) {
            $catname = (string)($info->context->categoryname ?? '');
            $catid = (int)($info->context->categoryid ?? 0);
            $ctxid = (int)($info->context->contextid ?? 0);
        }

        // Lien "ouvrir la question" dans son contexte (banque de questions).
        if ($catid > 0 && $ctxid > 0) {
            $cat = (object)[
                'id' => $catid,
                'contextid' => $ctxid,
            ];
            $viewurl = local_question_diagnostic_get_question_bank_url($cat, $qid);
            if ($viewurl) {
                $openlink = html_writer::link($viewurl, '👁️', [
                    'class' => 'btn btn-sm btn-primary',
                    'target' => '_blank',
                    'title' => get_string('question_merge_open_in_context_help', 'local_question_diagnostic'),
                ]);
            }
        }

        // Catégorie cliquable (même logique helper).
        $categorydisplay = format_string($catname);
        if ($catid > 0 && $ctxid > 0) {
            $cat = (object)[
                'id' => $catid,
                'contextid' => $ctxid,
            ];
            $caturl = local_question_diagnostic_get_question_bank_url($cat, null);
            if ($caturl) {
                $categorydisplay = html_writer::link($caturl, $categorydisplay, [
                    'target' => '_blank',
                    'title' => get_string('question_merge_open_category_in_context_help', 'local_question_diagnostic'),
                ]);
            }
        }
        $row = new html_table_row([
            $qid,
            $openlink,
            format_string((string)($info->name ?? '')),
            s((string)($info->qtype ?? '')),
            (int)($info->attempt_count ?? 0),
            (int)($info->quiz_count ?? 0),
            $categorydisplay,
            $ctxid,
        ]);
        if (!empty($classes)) {
            $row->attributes['class'] = implode(' ', $classes);
        }
        $table->data[] = $row;
    }
    echo html_writer::table($table);

    // Impacts.
    echo html_writer::tag('h4', get_string('question_merge_impacts_title', 'local_question_diagnostic'));
    $it = new html_table();
    $it->head = ['table', 'column', 'type', get_string('question_merge_impacts_count', 'local_question_diagnostic')];
    $it->data = [];
    foreach ((array)($plan->impacts ?? []) as $impact) {
        $it->data[] = [
            s((string)($impact->table ?? '')),
            s((string)($impact->column ?? '')),
            s((string)($impact->type ?? '')),
            (int)($impact->before_count ?? 0),
        ];
    }
    echo html_writer::table($it);

    // Boutons.
    echo html_writer::start_div('mt-3', ['style' => 'display:flex;gap:12px;flex-wrap:wrap;align-items:center;']);
    $confirmurl = new moodle_url('/local/question_diagnostic/actions/merge_questions.php', [
        'repid' => $repid,
        'confirm' => 1,
        'includequiz' => $includequiz,
        'discovery' => $discovery,
        'sesskey' => sesskey(),
        'returnurl' => $returnurl->out(false),
    ]);
    echo html_writer::link($confirmurl, get_string('question_merge_confirm_button', 'local_question_diagnostic'), ['class' => 'btn btn-danger']);
    echo html_writer::link($returnurl, get_string('cancel', 'core'), ['class' => 'btn btn-secondary']);
    echo html_writer::end_div();

    echo $OUTPUT->footer();
    exit;
}

// Exécution (confirm=1).
require_sesskey();

// Rebuild plan right before applying, to avoid stale preview.
$plan = question_merger::build_merge_plan((int)$repid, $options);
if (!empty($plan->errors)) {
    redirect($returnurl, implode(' | ', array_map('s', (array)$plan->errors)), null, \core\output\notification::NOTIFY_ERROR);
}

$result = question_merger::apply_merge_plan($plan, $options);
if (!empty($result->success)) {
    redirect($returnurl, s((string)$result->message), null, \core\output\notification::NOTIFY_SUCCESS);
} else {
    redirect($returnurl, s((string)$result->message), null, \core\output\notification::NOTIFY_ERROR);
}

