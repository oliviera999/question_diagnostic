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
 * Action : fusion batch des groupes de doublons stricts (preview + exécution).
 *
 * ⚠️ Cette action peut impacter beaucoup d'enregistrements.
 * Elle est volontairement protégée par :
 * - preview (dry-run)
 * - confirmation explicite
 * - exécution groupe par groupe, avec rapport
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

$confirm = optional_param('confirm', 0, PARAM_INT);
$includequiz = optional_param('includequiz', 1, PARAM_INT);
$discovery = optional_param('discovery', 0, PARAM_INT);
$limit = optional_param('limit', 200, PARAM_INT); // taille du lot (nb de groupes scannés par itération)
$offset = optional_param('offset', 0, PARAM_INT); // compat ancienne pagination
$processall = optional_param('processall', 1, PARAM_INT); // 1 = traiter tout le site par lots
$afterrepid = optional_param('afterrepid', 0, PARAM_INT); // pagination stable (seek) si processall=1
$stoponerror = optional_param('stoponerror', 1, PARAM_INT);
$returnurlparam = optional_param('returnurl', '', PARAM_LOCALURL);

$limit = max(1, min(1000, (int)$limit));
$offset = max(0, (int)$offset);
$afterrepid = max(0, (int)$afterrepid);

$returnurl = !empty($returnurlparam)
    ? new moodle_url($returnurlparam)
    : new moodle_url('/local/question_diagnostic/question_merge.php');

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/question_diagnostic/actions/merge_questions_batch.php', [
    'confirm' => $confirm,
    'includequiz' => $includequiz,
    'discovery' => $discovery,
    'limit' => $limit,
    'offset' => $offset,
    'processall' => $processall,
    'afterrepid' => $afterrepid,
    'stoponerror' => $stoponerror,
    'returnurl' => $returnurl->out(false),
]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('question_merge_batch_title', 'local_question_diagnostic'));
$PAGE->set_heading(get_string('question_merge_batch_title', 'local_question_diagnostic'));

$options = [
    // include_quiz_references est toujours actif : les questions utilisées dans des quiz
    // mais sans tentatives sont fusionnables et les références quiz seront remappées.
    'include_quiz_references' => true,
    'advanced_discovery' => (int)$discovery === 1,
];

// Récupérer une fenêtre de groupes (soit seek sur tout le site, soit compat offset).
if ((int)$processall === 1) {
    $groups = question_analyzer::get_duplicate_groups_seek((int)$limit, (int)$afterrepid);
} else {
    $groups = question_analyzer::get_duplicate_groups((int)$limit, (int)$offset, false, false);
}
if (empty($groups)) {
    echo $OUTPUT->header();
    echo local_question_diagnostic_render_version_badge();
    echo $OUTPUT->heading(get_string('question_merge_batch_heading', 'local_question_diagnostic'));
    echo $OUTPUT->notification(get_string('question_merge_batch_no_groups', 'local_question_diagnostic'), \core\output\notification::NOTIFY_INFO);
    echo html_writer::div(html_writer::link($returnurl, get_string('cancel', 'core'), ['class' => 'btn btn-secondary']), 'mt-3');
    echo $OUTPUT->footer();
    exit;
}

// Construire un preview batch : n'inclure que les groupes avec mergeable_questionids > 0.
$eligible = [];
$skipped = 0;
$errors = 0;
$impactsagg = []; // key "table|column|type" => count

foreach ($groups as $g) {
    $repid = (int)($g->representative_id ?? 0);
    if ($repid <= 0) {
        continue;
    }
    $plan = question_merger::build_merge_plan($repid, $options);
    if (!empty($plan->errors)) {
        $errors++;
        continue;
    }
    $mergecount = count((array)($plan->mergeable_questionids ?? []));
    if ($mergecount <= 0) {
        $skipped++;
        continue;
    }
    $eligible[] = (object)[
        'repid' => $repid,
        'name' => (string)($plan->group->name ?? ''),
        'qtype' => (string)($plan->group->qtype ?? ''),
        'size' => (int)($plan->group->size ?? 0),
        'referenceid' => (int)($plan->reference_questionid ?? 0),
        'mergecount' => $mergecount,
        'warnings' => (array)($plan->warnings ?? []),
        'impacts' => (array)($plan->impacts ?? []),
    ];
    foreach ((array)($plan->impacts ?? []) as $impact) {
        $t = (string)($impact->table ?? '');
        $c = (string)($impact->column ?? '');
        $ty = (string)($impact->type ?? '');
        $k = $t . '|' . $c . '|' . $ty;
        if (!isset($impactsagg[$k])) {
            $impactsagg[$k] = 0;
        }
        $impactsagg[$k] += (int)($impact->before_count ?? 0);
    }
}

echo $OUTPUT->header();
echo local_question_diagnostic_render_version_badge();
echo $OUTPUT->heading(get_string('question_merge_batch_heading', 'local_question_diagnostic'));

// Résumé.
echo html_writer::start_div('alert alert-info');
echo html_writer::tag('h4', get_string('question_merge_batch_summary_title', 'local_question_diagnostic'));
echo html_writer::tag('p', get_string('question_merge_batch_summary', 'local_question_diagnostic', (object)[
    'windowcount' => count($groups),
    'eligiblecount' => count($eligible),
    'skipped' => (int)$skipped,
    'errors' => (int)$errors,
    'limit' => (int)$limit,
    'offset' => (int)$offset,
]));
if ((int)$processall === 1) {
    echo html_writer::tag('p', 'Mode : traitement de tout le site par lots (seek). Dernier representative_id traité : ' . (int)$afterrepid . '.', [
        'style' => 'margin-bottom:0;',
    ]);
}
echo html_writer::end_div();

if (empty($eligible)) {
    echo $OUTPUT->notification(get_string('question_merge_batch_nothing', 'local_question_diagnostic'), \core\output\notification::NOTIFY_WARNING);
    echo html_writer::div(html_writer::link($returnurl, get_string('cancel', 'core'), ['class' => 'btn btn-secondary']), 'mt-3');
    echo $OUTPUT->footer();
    exit;
}

// Impacts agrégés.
echo html_writer::tag('h4', get_string('question_merge_batch_impacts_title', 'local_question_diagnostic'));
$it = new html_table();
$it->head = ['table', 'column', 'type', get_string('question_merge_impacts_count', 'local_question_diagnostic')];
$it->data = [];
foreach ($impactsagg as $k => $cnt) {
    list($t, $c, $ty) = explode('|', $k);
    $it->data[] = [s($t), s($c), s($ty), (int)$cnt];
}
echo html_writer::table($it);

// Table des groupes éligibles.
echo html_writer::tag('h4', get_string('question_merge_batch_groups_title', 'local_question_diagnostic'));
$table = new html_table();
$table->head = [
    get_string('name'),
    get_string('type', 'local_question_diagnostic'),
    get_string('question_merge_group_size', 'local_question_diagnostic'),
    get_string('question_merge_batch_reference', 'local_question_diagnostic'),
    get_string('question_merge_batch_mergeable', 'local_question_diagnostic'),
    get_string('warning', 'core'),
];
$table->data = [];
foreach ($eligible as $e) {
    $table->data[] = [
        format_string($e->name),
        s($e->qtype),
        (int)$e->size,
        (int)$e->referenceid,
        (int)$e->mergecount,
        count((array)$e->warnings),
    ];
}
echo html_writer::table($table);

// Confirmation / exécution.
if (!$confirm) {
    echo html_writer::start_div('alert alert-danger mt-3');
    echo html_writer::tag('h4', '⚠️ ' . get_string('warning', 'core'));
    echo html_writer::tag('p', get_string('question_merge_batch_warning', 'local_question_diagnostic'));
    echo html_writer::end_div();

    echo html_writer::start_div('mt-3', ['style' => 'display:flex;gap:12px;flex-wrap:wrap;align-items:center;']);
    $confirmurl = new moodle_url('/local/question_diagnostic/actions/merge_questions_batch.php', [
        'confirm' => 1,
        'includequiz' => $includequiz,
        'discovery' => $discovery,
        'limit' => $limit,
        'offset' => $offset,
        'stoponerror' => $stoponerror,
        'sesskey' => sesskey(),
        'returnurl' => $returnurl->out(false),
    ]);
    echo html_writer::link($confirmurl, get_string('question_merge_batch_confirm_button', 'local_question_diagnostic'), ['class' => 'btn btn-danger']);
    echo html_writer::link($returnurl, get_string('cancel', 'core'), ['class' => 'btn btn-secondary']);
    echo html_writer::end_div();

    echo $OUTPUT->footer();
    exit;
}

// confirm=1 : exécution.
require_sesskey();

$results = [
    'success_groups' => 0,
    'failed_groups' => 0,
    'deleted_questions' => 0,
    'scanned_groups' => 0,
    'eligible_groups' => 0,
    'skipped_no_mergeable' => 0,
    'skipped_errors' => 0,
    'messages' => [],
];

// Exécuter soit uniquement la fenêtre preview, soit tout le site par lots.
$loopafter = (int)$afterrepid;
do {
    $batchgroups = (int)$processall === 1
        ? question_analyzer::get_duplicate_groups_seek((int)$limit, (int)$loopafter)
        : $groups;

    if (empty($batchgroups)) {
        break;
    }

    // Mettre à jour le curseur de progression (max repid du lot scanné).
    $maxrepid = $loopafter;
    foreach ($batchgroups as $g) {
        $rid = (int)($g->representative_id ?? 0);
        if ($rid > $maxrepid) {
            $maxrepid = $rid;
        }
    }

    foreach ($batchgroups as $g) {
        $repid = (int)($g->representative_id ?? 0);
        if ($repid <= 0) {
            continue;
        }
        $results['scanned_groups']++;

        $plan = question_merger::build_merge_plan($repid, $options);
        if (!empty($plan->errors)) {
            $results['skipped_errors']++;
            if ((int)$stoponerror === 1) {
                $results['failed_groups']++;
                $results['messages'][] = 'Group repid=' . $repid . ' : ' . implode(' | ', array_map('s', (array)$plan->errors));
                break 2;
            }
            continue;
        }
        $mergecount = count((array)($plan->mergeable_questionids ?? []));
        if ($mergecount <= 0) {
            $results['skipped_no_mergeable']++;
            continue;
        }
        $results['eligible_groups']++;

        $res = question_merger::apply_merge_plan($plan, $options);
        if (!empty($res->success)) {
            $results['success_groups']++;
            $results['deleted_questions'] += count((array)($res->details['deleted'] ?? []));
        } else {
            $results['failed_groups']++;
            $results['messages'][] = 'Group repid=' . $repid . ' : ' . s((string)($res->message ?? 'Erreur inconnue'));
            if ((int)$stoponerror === 1) {
                break 2;
            }
        }
    }

    $loopafter = $maxrepid;

    // En mode fenêtre unique, on ne boucle pas.
    if ((int)$processall !== 1) {
        break;
    }
} while (true);

echo html_writer::start_div('alert alert-info mt-3');
echo html_writer::tag('h4', get_string('question_merge_batch_done_title', 'local_question_diagnostic'));
echo html_writer::tag('p', get_string('question_merge_batch_done_summary', 'local_question_diagnostic', (object)[
    'success' => (int)$results['success_groups'],
    'failed' => (int)$results['failed_groups'],
    'deleted' => (int)$results['deleted_questions'],
]));
echo html_writer::tag('p', 'Groupes scannés: ' . (int)$results['scanned_groups']
    . ' | éligibles: ' . (int)$results['eligible_groups']
    . ' | ignorés (0 fusionnable): ' . (int)$results['skipped_no_mergeable']
    . ' | ignorés (erreurs plan): ' . (int)$results['skipped_errors']
    . ((int)$processall === 1 ? ' | dernier representative_id: ' . (int)$loopafter : ''), [
    'style' => 'margin-bottom:0;',
]);
echo html_writer::end_div();

if (!empty($results['messages'])) {
    echo html_writer::start_div('alert alert-warning');
    echo html_writer::tag('h4', get_string('question_merge_batch_messages_title', 'local_question_diagnostic'));
    echo html_writer::start_tag('ul');
    foreach ($results['messages'] as $m) {
        echo html_writer::tag('li', s($m));
    }
    echo html_writer::end_tag('ul');
    echo html_writer::end_div();
}

echo html_writer::start_div('mt-3', ['style' => 'display:flex;gap:12px;flex-wrap:wrap;align-items:center;']);
echo html_writer::link($returnurl, get_string('backtomenu', 'local_question_diagnostic'), ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/question_diagnostic/purge_cache.php', [
    'returnurl' => $returnurl->out(false),
]), get_string('purge_caches', 'local_question_diagnostic'), ['class' => 'btn btn-secondary']);
echo html_writer::end_div();

echo $OUTPUT->footer();

