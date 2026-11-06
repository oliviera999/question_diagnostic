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
 * Archivage (copie durable, sans suppression) de fichiers orphelins.
 *
 * GET / POST sans confirmation : page de confirmation (aucune modification).
 * POST + sesskey + confirm=1 : exécution.
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../classes/orphan_file_detector.php');

use local_question_diagnostic\orphan_file_detector;

require_login();

// Vérification stricte : seuls les administrateurs du site peuvent accéder à cette page.
if (!is_siteadmin()) {
    throw new \moodle_exception('accessdenied', 'admin');
}

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/question_diagnostic/actions/orphan_archive.php'));
$PAGE->set_title(get_string('archive_orphans', 'local_question_diagnostic'));
$PAGE->set_heading(get_string('orphan_files_heading', 'local_question_diagnostic'));
$PAGE->set_pagelayout('admin');

$returnurl = new moodle_url('/local/question_diagnostic/orphan_files.php');

$singleid = optional_param('id', 0, PARAM_INT);
$multipleids = optional_param_array('ids', [], PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$requestedids = $singleid > 0 ? [$singleid] : $multipleids;
$requestedids = array_slice(array_values(array_unique(array_filter(array_map('intval', $requestedids)))),
    0, orphan_file_detector::MAX_BATCH);

if (empty($requestedids)) {
    redirect($returnurl, get_string('archive_error', 'local_question_diagnostic'), null,
        \core\output\notification::NOTIFY_ERROR);
}

// ======================================================================
// EXÉCUTION (POST + sesskey + confirmation uniquement)
// ======================================================================
if ($confirm && data_submitted()) {
    require_sesskey();

    // archive_multiple_orphans revérifie que chaque fichier est bien orphelin.
    $results = orphan_file_detector::archive_multiple_orphans($requestedids);
    $a = (object)['success' => $results['success'], 'failed' => $results['failed'], 'archive' => $results['archive_dir']];
    $message = get_string('orphan_archive_result', 'local_question_diagnostic', $a);
    $type = $results['success'] > 0 ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_ERROR;
    redirect($returnurl, s($message), null, $type);
}

// ======================================================================
// PAGE DE CONFIRMATION (aucune modification)
// ======================================================================
$orphans = orphan_file_detector::get_orphans_by_ids($requestedids);
if (empty($orphans)) {
    redirect($returnurl, get_string('orphan_none_orphan', 'local_question_diagnostic'), null,
        \core\output\notification::NOTIFY_ERROR);
}

$totalsize = 0;
foreach ($orphans as $orphan) {
    $totalsize += (int)$orphan->file->filesize;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('archive_orphans', 'local_question_diagnostic'));

echo $OUTPUT->notification(s(get_string('orphan_archive_notice', 'local_question_diagnostic',
    (object)['count' => count($orphans), 'dir' => orphan_file_detector::get_archive_dir(),
        'size' => orphan_file_detector::format_filesize($totalsize)])),
    \core\output\notification::NOTIFY_INFO, false);

$ignored = count($requestedids) - count($orphans);
if ($ignored > 0) {
    echo $OUTPUT->notification(s(get_string('orphan_delete_skipped', 'local_question_diagnostic', $ignored)),
        \core\output\notification::NOTIFY_INFO, false);
}

$table = new html_table();
$table->attributes['class'] = 'table table-sm generaltable';
$table->head = [
    get_string('orphan_file_id', 'local_question_diagnostic'),
    get_string('orphan_filename', 'local_question_diagnostic'),
    get_string('orphan_component', 'local_question_diagnostic'),
    get_string('orphan_filearea', 'local_question_diagnostic'),
    get_string('orphan_filesize', 'local_question_diagnostic'),
];
$displayed = 0;
foreach ($orphans as $orphan) {
    if ($displayed++ >= 20) {
        $cell = new html_table_cell(s(get_string('orphan_more_files', 'local_question_diagnostic', count($orphans) - 20)));
        $cell->colspan = 5;
        $table->data[] = new html_table_row([$cell]);
        break;
    }
    $f = $orphan->file;
    $table->data[] = [(int)$f->id, s($f->filename), s($f->component), s($f->filearea), s($orphan->filesize_formatted)];
}
echo html_writer::div(html_writer::table($table), 'qd-table-wrapper', ['style' => 'max-height: 400px; overflow-y: auto;']);

$actionurl = new moodle_url('/local/question_diagnostic/actions/orphan_archive.php');
echo html_writer::start_div('my-4');
echo orphan_file_detector::render_post_button($actionurl,
    ['ids' => array_map('intval', array_keys($orphans)), 'confirm' => 1],
    get_string('orphan_archive_confirm_button', 'local_question_diagnostic'), 'btn btn-warning');
echo html_writer::link($returnurl, get_string('cancel'), ['class' => 'btn btn-secondary']);
echo html_writer::end_div();

echo $OUTPUT->footer();
