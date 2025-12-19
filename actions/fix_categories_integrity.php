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
 * Action : correction assistée des incohérences de catégories (diagnostic de cohérence).
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/../classes/category_manager.php');

use local_question_diagnostic\category_manager;

require_login();
require_sesskey();

if (!is_siteadmin()) {
    print_error('accessdenied', 'admin');
}

$confirm = optional_param('confirm', 0, PARAM_INT);
$returnurlraw = optional_param('returnurl', '', PARAM_LOCALURL);
$returnurl = !empty($returnurlraw)
    ? new moodle_url($returnurlraw)
    : new moodle_url('/local/question_diagnostic/categories.php', ['integritycheck' => 1]);

$PAGE->set_url(new moodle_url('/local/question_diagnostic/actions/fix_categories_integrity.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('admin');

/**
 * Construit la liste des opérations de correction à partir du rapport de cohérence.
 *
 * @return array<int,array{type:string,categoryid:int,newparentid:int,contextid:int,reason:string,oldparentid:int}>
 */
$build_operations = function(): array {
    global $DB;

    $ops = [];

    // Rapport complet (pas de samplelimit) pour pouvoir corriger toutes les anomalies détectées.
    $report = category_manager::get_categories_integrity_report(0);

    // Charger un aperçu minimal des catégories pour calculer racines et tailles de sous-arbres.
    $cats = $DB->get_records('question_categories', null, '', 'id,contextid,parent,name');
    if (empty($cats)) {
        return [];
    }

    $rootsbycontext = [];
    $childrenbyparent = [];
    foreach ($cats as $c) {
        $ctxid = (int)$c->contextid;
        $pid = (int)$c->parent;
        if ($pid === 0) {
            $rootsbycontext[$ctxid] = $rootsbycontext[$ctxid] ?? [];
            $rootsbycontext[$ctxid][] = (int)$c->id;
        }
        if ($pid > 0) {
            $childrenbyparent[$pid] = ($childrenbyparent[$pid] ?? 0) + 1;
        }
    }

    // Choisir la racine à conserver : priorité à la racine ayant le plus d'enfants directs, puis id minimal.
    $pick_keep_root = function(int $contextid) use ($rootsbycontext, $childrenbyparent): int {
        $rootids = $rootsbycontext[$contextid] ?? [];
        if (empty($rootids)) {
            return 0;
        }
        $bestid = 0;
        $bestchildren = -1;
        foreach ($rootids as $rid) {
            $childcount = (int)($childrenbyparent[(int)$rid] ?? 0);
            if ($bestid === 0 || $childcount > $bestchildren || ($childcount === $bestchildren && $rid < $bestid)) {
                $bestid = (int)$rid;
                $bestchildren = $childcount;
            }
        }
        return $bestid;
    };

    // A) Parent dans un autre contexte -> ré-attacher au root du contexte.
    $pcm = $report->checks['parent_context_mismatch'] ?? null;
    if (!empty($pcm) && !empty($pcm->count) && !empty($pcm->sample) && is_array($pcm->sample)) {
        foreach ($pcm->sample as $item) {
            $catid = (int)($item->categoryid ?? 0);
            $ctxid = (int)($item->contextid ?? 0);
            $oldparent = (int)($item->parent ?? 0);
            if ($catid <= 0 || $ctxid <= 0) {
                continue;
            }
            $keep = $pick_keep_root($ctxid);
            if ($keep <= 0) {
                continue;
            }
            // Pas d'opération si déjà correct.
            if ($oldparent === $keep) {
                continue;
            }
            $ops[] = [
                'type' => 'move_category',
                'categoryid' => $catid,
                'newparentid' => $keep,
                'contextid' => $ctxid,
                'oldparentid' => $oldparent,
                'reason' => 'parent_context_mismatch',
            ];
        }
    }

    // B) Plusieurs racines (parent=0) -> ré-attacher les racines "en trop" sous la racine conservée.
    $mr = $report->checks['multiple_roots_per_context'] ?? null;
    if (!empty($mr) && !empty($mr->count) && !empty($mr->sample) && is_array($mr->sample)) {
        foreach ($mr->sample as $item) {
            $ctxid = (int)($item->contextid ?? 0);
            $rootids = $item->rootids ?? [];
            if ($ctxid <= 0 || empty($rootids) || !is_array($rootids)) {
                continue;
            }
            $keep = $pick_keep_root($ctxid);
            if ($keep <= 0) {
                continue;
            }
            foreach ($rootids as $rid) {
                $rid = (int)$rid;
                if ($rid <= 0 || $rid === $keep) {
                    continue;
                }
                $ops[] = [
                    'type' => 'reparent_root',
                    'categoryid' => $rid,
                    'newparentid' => $keep,
                    'contextid' => $ctxid,
                    'oldparentid' => 0,
                    'reason' => 'multiple_roots_per_context',
                ];
            }
        }
    }

    return $ops;
};

$operations = $build_operations();

if (!$confirm) {
    $PAGE->set_title(get_string('categories_integrity_fix_confirm_title', 'local_question_diagnostic'));
    $PAGE->set_heading(get_string('categories_integrity_fix_confirm_title', 'local_question_diagnostic'));

    echo $OUTPUT->header();
    echo local_question_diagnostic_render_version_badge();

    echo html_writer::tag('h2', '🛠️ ' . get_string('categories_integrity_fix_confirm_title', 'local_question_diagnostic'));
    echo html_writer::tag('p', get_string('categories_integrity_fix_confirm_intro', 'local_question_diagnostic'));

    if (empty($operations)) {
        echo html_writer::start_div('alert alert-info');
        echo get_string('categories_integrity_fix_nothing', 'local_question_diagnostic');
        echo html_writer::end_div();
        echo html_writer::link($returnurl, get_string('backtomenu', 'local_question_diagnostic'), ['class' => 'btn btn-secondary']);
        echo $OUTPUT->footer();
        exit;
    }

    echo html_writer::start_div('alert alert-warning');
    echo get_string('categories_integrity_fix_warning', 'local_question_diagnostic');
    echo html_writer::end_div();

    echo html_writer::tag('h3', get_string('categories_integrity_fix_operations', 'local_question_diagnostic'));
    echo html_writer::start_tag('ul');
    foreach ($operations as $op) {
        $line = 'catid=' . (int)$op['categoryid']
            . ' / contextid=' . (int)$op['contextid']
            . ' / parent: ' . (int)$op['oldparentid'] . ' → ' . (int)$op['newparentid']
            . ' (' . s($op['reason']) . ')';
        echo html_writer::tag('li', $line);
    }
    echo html_writer::end_tag('ul');

    $confirmurl = new moodle_url('/local/question_diagnostic/actions/fix_categories_integrity.php', [
        'confirm' => 1,
        'sesskey' => sesskey(),
        'returnurl' => $returnurl->out(false),
    ]);

    echo html_writer::start_div('mt-3', ['style' => 'margin-top: 20px;']);
    echo html_writer::link($confirmurl, get_string('confirm', 'core'), ['class' => 'btn btn-danger']);
    echo ' ';
    echo html_writer::link($returnurl, get_string('cancel', 'core'), ['class' => 'btn btn-secondary']);
    echo html_writer::end_div();

    echo $OUTPUT->footer();
    exit;
}

// Confirmé : appliquer les corrections.
$success = 0;
$failed = 0;
$errors = [];

foreach ($operations as $op) {
    $catid = (int)$op['categoryid'];
    $newparentid = (int)$op['newparentid'];

    if ($catid <= 0 || $newparentid <= 0) {
        $failed++;
        $errors[] = 'Invalid operation for catid=' . $catid;
        continue;
    }

    if ($op['type'] === 'reparent_root') {
        $result = category_manager::force_reparent_root_category($catid, $newparentid);
    } else {
        $result = category_manager::move_category($catid, $newparentid);
    }

    if ($result === true) {
        $success++;
    } else {
        $failed++;
        $errors[] = 'catid=' . $catid . ': ' . (string)$result;
    }
}

// Notifications.
if ($success > 0) {
    \core\notification::success(get_string('categories_integrity_fix_done', 'local_question_diagnostic', (object)[
        'success' => $success,
        'failed' => $failed,
    ]));
}
if (!empty($errors)) {
    \core\notification::error(get_string('categories_integrity_fix_failed', 'local_question_diagnostic') . '<br>' . implode('<br>', array_map('s', $errors)));
}
if ($success === 0 && $failed === 0) {
    \core\notification::info(get_string('categories_integrity_fix_nothing', 'local_question_diagnostic'));
}

redirect($returnurl);


