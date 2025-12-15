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
 * Déplace toutes les questions d'une catégorie source vers Olution/commun/Question à trier.
 *
 * IMPORTANT :
 * - On déplace les QUESTIONS (question_bank_entries / API core), pas la catégorie.
 * - Cette action MODIFIE la base de données : confirmation obligatoire.
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/../classes/olution_manager.php');
require_once($CFG->dirroot . '/question/editlib.php');

use local_question_diagnostic\olution_manager;

require_login();
require_sesskey();

if (!is_siteadmin()) {
    print_error('accessdenied', 'admin');
}

$sourcecatid = required_param('sourcecatid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);
$returnurlparam = optional_param('returnurl', '', PARAM_LOCALURL);

$returnurl = null;
if (!empty($returnurlparam)) {
    $returnurl = new moodle_url($returnurlparam);
} else {
    $returnurl = new moodle_url('/local/question_diagnostic/categories_by_context.php', [
        'courseid' => $courseid,
    ]);
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/question_diagnostic/actions/move_category_questions_to_olution_triage.php', [
    'sourcecatid' => $sourcecatid,
    'courseid' => $courseid,
]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('tool_categories_by_context_move_questions_to_triage_title', 'local_question_diagnostic'));
$PAGE->set_heading(get_string('tool_categories_by_context_move_questions_to_triage_title', 'local_question_diagnostic'));

// Charger catégories.
$sourcecat = $DB->get_record('question_categories', ['id' => $sourcecatid], '*', MUST_EXIST);
$triagecat = olution_manager::get_triage_category();

if (!$triagecat) {
    redirect(
        $returnurl,
        get_string('olution_triage_not_found', 'local_question_diagnostic'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// Vérifier que la cible est accessible depuis le cours (editing contexts).
$coursecontext = context_course::instance($courseid, IGNORE_MISSING);
if (!$coursecontext) {
    redirect(
        $returnurl,
        get_string('invalid_parameters', 'local_question_diagnostic') . ' (courseid=' . (int)$courseid . ')',
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

$targetctxid = (int)$triagecat->contextid;
$accessible = false;
$method = '';
try {
    if (class_exists('\\question_edit_contexts')) {
        $qec = new \question_edit_contexts($coursecontext);
        if (method_exists($qec, 'all')) {
            $contexts = $qec->all();
            $method = 'question_edit_contexts::all';
        } else if (method_exists($qec, 'get_contexts')) {
            $contexts = $qec->get_contexts();
            $method = 'question_edit_contexts::get_contexts';
        } else {
            $contexts = [];
        }
        foreach ($contexts as $ctx) {
            if (!empty($ctx->id) && (int)$ctx->id === $targetctxid) {
                $accessible = true;
                break;
            }
        }
    }
} catch (Exception $e) {
    // fallback ci-dessous
}

if (!$accessible && function_exists('question_get_editing_contexts')) {
    try {
        $contexts = question_get_editing_contexts($coursecontext);
        $method = 'question_get_editing_contexts';
        foreach ($contexts as $ctx) {
            if (!empty($ctx->id) && (int)$ctx->id === $targetctxid) {
                $accessible = true;
                break;
            }
        }
    } catch (Exception $e) {
        // ignore
    }
}

if (!$accessible) {
    $msg = get_string('tool_categories_by_context_move_questions_to_triage_not_accessible', 'local_question_diagnostic', (object)[
        'courseid' => (int)$courseid,
        'targetcontextid' => (int)$targetctxid,
        'method' => $method !== '' ? $method : 'n/a',
    ]);
    redirect($returnurl, $msg, null, \core\output\notification::NOTIFY_ERROR);
}

// Construire la liste des question ids (version courante) à déplacer depuis cette catégorie.
$sql = "SELECT DISTINCT qv.questionid
          FROM {question_bank_entries} qbe
          INNER JOIN (
                SELECT questionbankentryid, MAX(version) AS maxversion
                  FROM {question_versions}
              GROUP BY questionbankentryid
          ) mv ON mv.questionbankentryid = qbe.id
          INNER JOIN {question_versions} qv
                  ON qv.questionbankentryid = mv.questionbankentryid
                 AND qv.version = mv.maxversion
         WHERE qbe.questioncategoryid = :sourcecatid";
$questionids = $DB->get_fieldset_sql($sql, ['sourcecatid' => $sourcecatid]);
if (!$questionids) {
    $questionids = [];
}

if (empty($questionids)) {
    redirect(
        $returnurl,
        get_string('tool_categories_by_context_move_questions_to_triage_nothing', 'local_question_diagnostic'),
        null,
        \core\output\notification::NOTIFY_WARNING
    );
}

// Page de confirmation.
if (!$confirm) {
    echo $OUTPUT->header();
    echo local_question_diagnostic_render_version_badge();
    echo $OUTPUT->heading(get_string('tool_categories_by_context_move_questions_to_triage_title', 'local_question_diagnostic'));

    echo html_writer::start_div('alert alert-info');
    echo html_writer::tag('h4', get_string('tool_categories_by_context_move_questions_to_triage_summary_title', 'local_question_diagnostic'));
    echo html_writer::tag('p', get_string('tool_categories_by_context_move_questions_to_triage_summary', 'local_question_diagnostic', (object)[
        'sourcecategory' => format_string($sourcecat->name) . ' (ID: ' . (int)$sourcecat->id . ')',
        'targetcategory' => format_string($triagecat->name) . ' (ID: ' . (int)$triagecat->id . ')',
        'count' => count($questionids),
    ]));
    echo html_writer::end_div();

    echo html_writer::start_div('alert alert-danger');
    echo html_writer::tag('h4', '⚠️ ' . get_string('warning', 'core'));
    echo html_writer::tag('p', get_string('tool_categories_by_context_move_questions_to_triage_warning', 'local_question_diagnostic'));
    echo html_writer::end_div();

    $confirmurl = new moodle_url('/local/question_diagnostic/actions/move_category_questions_to_olution_triage.php', [
        'sourcecatid' => $sourcecatid,
        'courseid' => $courseid,
        'confirm' => 1,
        'sesskey' => sesskey(),
        'returnurl' => !empty($returnurlparam) ? $returnurlparam : null,
    ]);

    echo html_writer::start_div(['style' => 'margin-top: 20px;']);
    echo html_writer::link($confirmurl, get_string('confirm', 'core'), ['class' => 'btn btn-danger btn-lg mr-2']);
    echo html_writer::link($returnurl, get_string('cancel', 'core'), ['class' => 'btn btn-secondary btn-lg']);
    echo html_writer::end_div();

    echo $OUTPUT->footer();
    exit;
}

// Exécution.
$operations = [];
foreach ($questionids as $qid) {
    $operations[] = [
        'questionid' => (int)$qid,
        'target_category_id' => (int)$triagecat->id,
    ];
}

$result = olution_manager::move_questions_batch($operations);
$message = get_string('move_batch_result', 'local_question_diagnostic', [
    'success' => (int)($result['success'] ?? 0),
    'failed' => (int)($result['failed'] ?? 0),
]);

if (!empty($result['success']) && empty($result['failed'])) {
    redirect($returnurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);
} else if (!empty($result['success'])) {
    redirect($returnurl, $message, null, \core\output\notification::NOTIFY_WARNING);
} else {
    redirect($returnurl, $message, null, \core\output\notification::NOTIFY_ERROR);
}


