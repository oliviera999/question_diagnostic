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
 * Page : fusion de doublons strictement identiques (listing + accès preview).
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/question_analyzer.php');
require_once(__DIR__ . '/classes/question_merger.php');

use local_question_diagnostic\question_analyzer;
use local_question_diagnostic\question_merger;

require_login();

if (!is_siteadmin()) {
    print_error('accessdenied', 'admin');
}

$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 50, PARAM_INT);
$includequiz = optional_param('includequiz', 1, PARAM_INT);
$discovery = optional_param('discovery', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/local/question_diagnostic/question_merge.php', [
    'page' => $page,
    'perpage' => $perpage,
    'includequiz' => $includequiz,
    'discovery' => $discovery,
]));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('question_merge_title', 'local_question_diagnostic'));
$PAGE->set_heading(get_string('question_merge_title', 'local_question_diagnostic'));
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();
echo local_question_diagnostic_render_version_badge();

// Bouton purge caches.
echo html_writer::start_div('text-right', ['style' => 'margin-bottom: 20px;']);
echo local_question_diagnostic_render_cache_purge_button();
echo html_writer::end_div();

echo $OUTPUT->heading(get_string('question_merge_heading', 'local_question_diagnostic'));

echo html_writer::start_div('alert alert-info');
echo html_writer::tag('p', get_string('question_merge_intro', 'local_question_diagnostic'));
echo html_writer::end_div();

// Actions batch.
echo html_writer::start_div('mb-3', ['style' => 'display:flex;gap:12px;flex-wrap:wrap;align-items:center;']);
$batchurl = new moodle_url('/local/question_diagnostic/actions/merge_questions_batch.php', [
    'includequiz' => $includequiz,
    'discovery' => $discovery,
    'limit' => 200,
    // Par défaut, ne pas forcer "tout le site" : l'option est disponible sur la page batch.
    'processall' => 0,
    'afterrepid' => 0,
    'stoponerror' => 1,
    'returnurl' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
]);
echo html_writer::link($batchurl, get_string('question_merge_batch_preview_button', 'local_question_diagnostic'), ['class' => 'btn btn-warning']);
echo html_writer::end_div();

// Options.
$optionsurl = new moodle_url('/local/question_diagnostic/question_merge.php');
echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => $optionsurl->out(false),
    'class' => 'mb-3',
]);
echo html_writer::start_div('form-inline', ['style' => 'display:flex;gap:16px;flex-wrap:wrap;align-items:center;']);

// includequiz est toujours actif : une question utilisée dans un quiz mais sans tentatives est fusionnable
// (les références quiz sont remappées). On garde le paramètre en compat, mais on ne l'expose pas en UI.

echo html_writer::start_div();
echo html_writer::empty_tag('input', [
    'type' => 'checkbox',
    'name' => 'discovery',
    'value' => '1',
    'id' => 'qd-merge-discovery',
    'checked' => (int)$discovery === 1 ? 'checked' : null,
]);
echo html_writer::tag('label', ' ' . get_string('question_merge_discovery', 'local_question_diagnostic'), [
    'for' => 'qd-merge-discovery',
    'style' => 'margin-left:6px;',
]);
echo html_writer::end_div();

echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'perpage', 'value' => (int)$perpage]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'page', 'value' => 0]);

echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => get_string('applyfilters', 'core'),
    'class' => 'btn btn-secondary',
]);

echo html_writer::end_div();
echo html_writer::end_tag('form');

$offset = max(0, (int)$page) * (int)$perpage;
$totalgroups = (int)question_analyzer::count_duplicate_groups(false, false);
$groups = question_analyzer::get_duplicate_groups((int)$perpage, (int)$offset, false, false);

// Filtrer : ne conserver que les groupes avec au moins 1 doublon fusionnable.
// (Sinon, ces groupes ne doivent pas apparaître dans la liste.)
$options = [
    'include_quiz_references' => true,
    'advanced_discovery' => (int)$discovery === 1,
];
$eligiblegroups = [];
foreach ((array)$groups as $g) {
    $repid = (int)($g->representative_id ?? 0);
    if ($repid <= 0) {
        continue;
    }
    $plan = question_merger::build_merge_plan($repid, $options);
    if (!empty($plan->errors)) {
        continue;
    }
    $mergecount = count((array)($plan->mergeable_questionids ?? []));
    if ($mergecount <= 0) {
        continue;
    }
    $eligiblegroups[] = $g;
}
// Remplacer l'affichage par la liste filtrée.
$groups = $eligiblegroups;

if (empty($groups)) {
    echo $OUTPUT->notification(get_string('question_merge_no_groups', 'local_question_diagnostic'), \core\output\notification::NOTIFY_SUCCESS);
    echo $OUTPUT->footer();
    exit;
}

// Listing.
echo html_writer::tag('h3', get_string('question_merge_groups_title', 'local_question_diagnostic'));

$table = new html_table();
$table->head = [
    get_string('name'),
    get_string('type', 'local_question_diagnostic'),
    get_string('question_merge_group_size', 'local_question_diagnostic'),
    get_string('question_merge_used_count', 'local_question_diagnostic'),
    get_string('question_merge_unused_count', 'local_question_diagnostic'),
    get_string('actions', 'local_question_diagnostic'),
];
$table->data = [];

foreach ($groups as $g) {
    $previewurl = new moodle_url('/local/question_diagnostic/actions/merge_questions.php', [
        'repid' => (int)$g->representative_id,
        'includequiz' => (int)$includequiz,
        'discovery' => (int)$discovery,
        'returnurl' => $PAGE->url->out(false),
        'sesskey' => sesskey(),
    ]);
    $actions = html_writer::link($previewurl, get_string('question_merge_preview_button', 'local_question_diagnostic'), [
        'class' => 'btn btn-primary btn-sm',
    ]);

    $table->data[] = [
        format_string((string)($g->question_name ?? '')),
        s((string)($g->qtype ?? '')),
        (int)($g->duplicate_count ?? 0),
        (int)($g->used_count ?? 0),
        (int)($g->unused_count ?? 0),
        $actions,
    ];
}

echo html_writer::table($table);

// Pagination.
$baseurl = new moodle_url('/local/question_diagnostic/question_merge.php', [
    'perpage' => $perpage,
    'includequiz' => $includequiz,
    'discovery' => $discovery,
]);
echo $OUTPUT->paging_bar($totalgroups, $page, $perpage, $baseurl);

echo $OUTPUT->footer();

