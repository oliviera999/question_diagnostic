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
 * Diagnostic général du site (BDD + ressources).
 *
 * Objectif: donner un aperçu "santé globale" et pointer vers les outils de détail/réparation.
 * Cette page est read-only (aucune modification BDD).
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/site_diagnostic_checker.php');
require_once(__DIR__ . '/classes/question_link_checker.php');
require_once(__DIR__ . '/classes/orphan_file_detector.php');
require_once(__DIR__ . '/classes/category_manager.php');
require_once(__DIR__ . '/classes/question_analyzer.php');

use local_question_diagnostic\site_diagnostic_checker;
use local_question_diagnostic\question_link_checker;
use local_question_diagnostic\orphan_file_detector;
use local_question_diagnostic\category_manager;
use local_question_diagnostic\question_analyzer;

require_login();

if (!is_siteadmin()) {
    print_error('accessdenied', 'admin');
}

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/question_diagnostic/site_diagnostic.php'));
$pagetitle = get_string('site_diagnostic_title', 'local_question_diagnostic');
$PAGE->set_title($pagetitle);
$PAGE->set_heading(local_question_diagnostic_get_heading_with_version($pagetitle));
$PAGE->set_pagelayout('report');
$PAGE->requires->css('/local/question_diagnostic/styles/main.css');

// Actions (read-only): refresh local caches to force recompute on next call.
$refresh = optional_param('refresh', 0, PARAM_INT);
if ($refresh) {
    require_sesskey();
    try {
        question_link_checker::purge_broken_links_cache();
    } catch (\Throwable $e) {
        // Ignore.
    }
    try {
        orphan_file_detector::purge_orphan_cache();
    } catch (\Throwable $e) {
        // Ignore.
    }
    redirect($PAGE->url, '✅ ' . get_string('site_diagnostic_refresh_done', 'local_question_diagnostic'),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo local_question_diagnostic_render_version_badge();

echo html_writer::start_div('text-right', ['style' => 'margin-bottom: 20px;']);
echo local_question_diagnostic_render_cache_purge_button();
echo html_writer::end_div();

echo html_writer::start_div('', ['style' => 'margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;']);
echo local_question_diagnostic_render_back_link('site_diagnostic.php');
echo html_writer::link(
    new moodle_url('/local/question_diagnostic/site_diagnostic.php', ['refresh' => 1, 'sesskey' => sesskey()]),
    '🔄 ' . get_string('site_diagnostic_refresh', 'local_question_diagnostic'),
    ['class' => 'btn btn-warning']
);
echo html_writer::link(
    new moodle_url('/local/question_diagnostic/help.php'),
    '📚 Aide',
    ['class' => 'btn btn-outline-info']
);
echo html_writer::end_div();

echo html_writer::tag('h2', '🩺 ' . get_string('site_diagnostic_heading', 'local_question_diagnostic'));

echo html_writer::start_div('alert alert-info', ['style' => 'margin: 20px 0;']);
echo get_string('site_diagnostic_intro', 'local_question_diagnostic');
echo html_writer::end_div();

// -------------------------------------------------------------------------
// Collect stats (keep it lightweight).
// -------------------------------------------------------------------------

$tables_to_check = [
    // Core.
    'course', 'course_categories', 'context', 'files', 'course_modules', 'modules',
    // Question bank (Moodle 4+).
    'question', 'question_categories', 'question_bank_entries', 'question_versions', 'question_references',
    // Common resource tables.
    'page', 'resource', 'label', 'url', 'forum', 'book',
    // Tasks (monitor scheduled tasks health).
    'task_scheduled',
];

$table_status = site_diagnostic_checker::get_table_existence($tables_to_check);
$missing_tables = array_values(array_filter($table_status, function($t) {
    return empty($t['exists']);
}));

$course_integrity = site_diagnostic_checker::get_course_integrity_stats();
$disk = site_diagnostic_checker::get_moodledata_disk_stats();

$orphan_stats = orphan_file_detector::get_global_stats();
$filedir_sample = site_diagnostic_checker::get_missing_filedir_content_stats(500, 12);

$cat_stats = category_manager::get_global_stats();
$question_stats = question_analyzer::get_global_stats();
$broken_link_stats = question_link_checker::get_global_stats();

// -------------------------------------------------------------------------
// Summary cards
// -------------------------------------------------------------------------

$course_issues = (int)$course_integrity->courses_missing_category
    + (int)$course_integrity->coursecats_missing_parent
    + (int)$course_integrity->courses_missing_context
    + (int)$course_integrity->coursecats_missing_context
    + (int)$course_integrity->orphan_course_contexts
    + (int)$course_integrity->orphan_coursecat_contexts;

$resource_issues = (int)$orphan_stats->total_orphans + (int)$filedir_sample->missing;
$question_issues = (int)$cat_stats->orphan_categories + (int)$broken_link_stats->questions_with_broken_links;
$db_issues = count($missing_tables);

$overall_issues = $course_issues + $resource_issues + $question_issues + $db_issues;

echo html_writer::tag('h3', '📌 ' . get_string('site_diagnostic_overview', 'local_question_diagnostic'));

echo html_writer::start_tag('div', ['class' => 'qd-dashboard', 'style' => 'margin-bottom: 30px;']);

$overall_class = $overall_issues > 0 ? ($overall_issues > 50 ? 'danger' : 'warning') : 'success';
echo html_writer::start_tag('div', ['class' => 'qd-card ' . $overall_class]);
echo html_writer::tag('div', get_string('site_diagnostic_health', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', (int)$overall_issues, ['class' => 'qd-card-value']);
echo html_writer::tag('div', get_string('site_diagnostic_issues_total', 'local_question_diagnostic'), ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

$db_class = $db_issues > 0 ? 'danger' : 'success';
echo html_writer::start_tag('div', ['class' => 'qd-card ' . $db_class]);
echo html_writer::tag('div', 'BDD', ['class' => 'qd-card-title']);
echo html_writer::tag('div', (int)$db_issues, ['class' => 'qd-card-value']);
echo html_writer::tag('div', get_string('site_diagnostic_missing_tables', 'local_question_diagnostic'), ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

$courses_class = $course_issues > 0 ? 'warning' : 'success';
echo html_writer::start_tag('div', ['class' => 'qd-card ' . $courses_class]);
echo html_writer::tag('div', get_string('site_diagnostic_courses', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', (int)$course_issues, ['class' => 'qd-card-value']);
echo html_writer::tag('div', get_string('site_diagnostic_course_integrity', 'local_question_diagnostic'), ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

$resources_class = $resource_issues > 0 ? 'warning' : 'success';
echo html_writer::start_tag('div', ['class' => 'qd-card ' . $resources_class]);
echo html_writer::tag('div', get_string('site_diagnostic_resources', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', (int)$resource_issues, ['class' => 'qd-card-value']);
echo html_writer::tag('div', get_string('site_diagnostic_files_health', 'local_question_diagnostic'), ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

echo html_writer::end_tag('div');

// -------------------------------------------------------------------------
// Detailed sections
// -------------------------------------------------------------------------

// 1) DB structure.
echo html_writer::tag('h3', '🗄️ ' . get_string('site_diagnostic_db_section', 'local_question_diagnostic'), ['style' => 'margin-top: 30px;']);
echo html_writer::start_tag('div', ['class' => 'qd-table-wrapper']);
echo html_writer::start_tag('table', ['class' => 'qd-table']);
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
echo html_writer::tag('th', get_string('site_diagnostic_check', 'local_question_diagnostic'));
echo html_writer::tag('th', get_string('site_diagnostic_status', 'local_question_diagnostic'));
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');
echo html_writer::start_tag('tbody');

foreach ($table_status as $t) {
    $exists = !empty($t['exists']);
    $badge = $exists
        ? html_writer::tag('span', '✅ OK', ['class' => 'qd-badge qd-badge-ok'])
        : html_writer::tag('span', '❌ KO', ['class' => 'qd-badge qd-badge-orphan']);
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', 'Table `' . s($t['table']) . '`');
    echo html_writer::tag('td', $badge);
    echo html_writer::end_tag('tr');
}

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');
echo html_writer::end_tag('div');

// 2) Courses + contexts.
echo html_writer::tag('h3', '🏫 ' . get_string('site_diagnostic_courses_section', 'local_question_diagnostic'), ['style' => 'margin-top: 40px;']);
echo html_writer::start_tag('div', ['class' => 'qd-table-wrapper']);
echo html_writer::start_tag('table', ['class' => 'qd-table']);
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
echo html_writer::tag('th', get_string('site_diagnostic_check', 'local_question_diagnostic'));
echo html_writer::tag('th', get_string('site_diagnostic_value', 'local_question_diagnostic'));
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');
echo html_writer::start_tag('tbody');

$course_checks = [
    (object)['label' => get_string('site_diagnostic_courses_missing_category', 'local_question_diagnostic'), 'value' => (int)$course_integrity->courses_missing_category],
    (object)['label' => get_string('site_diagnostic_coursecats_missing_parent', 'local_question_diagnostic'), 'value' => (int)$course_integrity->coursecats_missing_parent],
    (object)['label' => get_string('site_diagnostic_courses_missing_context', 'local_question_diagnostic'), 'value' => (int)$course_integrity->courses_missing_context],
    (object)['label' => get_string('site_diagnostic_coursecats_missing_context', 'local_question_diagnostic'), 'value' => (int)$course_integrity->coursecats_missing_context],
    (object)['label' => get_string('site_diagnostic_orphan_course_contexts', 'local_question_diagnostic'), 'value' => (int)$course_integrity->orphan_course_contexts],
    (object)['label' => get_string('site_diagnostic_orphan_coursecat_contexts', 'local_question_diagnostic'), 'value' => (int)$course_integrity->orphan_coursecat_contexts],
];

foreach ($course_checks as $check) {
    $v = (int)$check->value;
    $cls = $v > 0 ? 'qd-stat-warning' : 'qd-stat-success';
    $val = html_writer::tag('span', (string)$v, ['class' => 'qd-tool-stat-item ' . $cls]);
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', s($check->label));
    echo html_writer::tag('td', $val);
    echo html_writer::end_tag('tr');
}

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');
echo html_writer::end_tag('div');

// 3) Files + moodledata.
echo html_writer::tag('h3', '🗂️ ' . get_string('site_diagnostic_files_section', 'local_question_diagnostic'), ['style' => 'margin-top: 40px;']);

echo html_writer::start_div('qd-dashboard', ['style' => 'margin: 20px 0;']);

$diskclass = ($disk->free_bytes !== null && $disk->free_bytes < 5 * 1024 * 1024 * 1024) ? 'warning' : 'success'; // < 5GB warning.
echo html_writer::start_tag('div', ['class' => 'qd-card ' . $diskclass]);
echo html_writer::tag('div', get_string('site_diagnostic_disk_free', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', s($disk->free_formatted), ['class' => 'qd-card-value']);
echo html_writer::tag('div', get_string('site_diagnostic_moodledata', 'local_question_diagnostic'), ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

$orphclass = ((int)$orphan_stats->total_orphans > 0) ? 'warning' : 'success';
echo html_writer::start_tag('div', ['class' => 'qd-card ' . $orphclass]);
echo html_writer::tag('div', get_string('orphan_files', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', (int)$orphan_stats->total_orphans, ['class' => 'qd-card-value']);
echo html_writer::tag('div', s($orphan_stats->total_filesize_formatted ?? ''), ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

$missingclass = ((int)$filedir_sample->missing > 0) ? 'danger' : 'success';
echo html_writer::start_tag('div', ['class' => 'qd-card ' . $missingclass]);
echo html_writer::tag('div', get_string('site_diagnostic_missing_filedir', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', (int)$filedir_sample->missing, ['class' => 'qd-card-value']);
echo html_writer::tag('div', get_string('site_diagnostic_missing_filedir_sub', 'local_question_diagnostic', (int)$filedir_sample->checked), ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

echo html_writer::end_div();

echo html_writer::start_div('', ['style' => 'margin: 10px 0 20px 0; display: flex; gap: 10px; flex-wrap: wrap;']);
echo html_writer::link(
    new moodle_url('/local/question_diagnostic/orphan_files.php'),
    get_string('site_diagnostic_open_orphans', 'local_question_diagnostic') . ' →',
    ['class' => 'btn btn-info']
);
echo html_writer::end_div();

if (!empty($filedir_sample->examples)) {
    echo html_writer::start_div('alert alert-warning', ['style' => 'margin: 20px 0;']);
    echo '<strong>' . get_string('site_diagnostic_missing_filedir_examples_title', 'local_question_diagnostic') . '</strong><br>';
    echo get_string('site_diagnostic_missing_filedir_examples_desc', 'local_question_diagnostic');
    echo html_writer::end_div();

    echo html_writer::start_tag('div', ['class' => 'qd-table-wrapper']);
    echo html_writer::start_tag('table', ['class' => 'qd-table']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'ID');
    echo html_writer::tag('th', get_string('orphan_filename', 'local_question_diagnostic'));
    echo html_writer::tag('th', get_string('orphan_component', 'local_question_diagnostic'));
    echo html_writer::tag('th', 'contenthash');
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');
    foreach ($filedir_sample->examples as $ex) {
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', (int)$ex->id);
        echo html_writer::tag('td', s($ex->filename));
        echo html_writer::tag('td', s($ex->component) . ' / ' . s($ex->filearea));
        echo html_writer::tag('td', html_writer::tag('code', s($ex->contenthash)));
        echo html_writer::end_tag('tr');
    }
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_tag('div');
}

// 4) Question bank quick links.
echo html_writer::tag('h3', '❓ ' . get_string('site_diagnostic_questionbank_section', 'local_question_diagnostic'), ['style' => 'margin-top: 40px;']);

echo html_writer::start_tag('div', ['class' => 'qd-dashboard', 'style' => 'margin: 20px 0;']);

$qcatclass = ((int)$cat_stats->orphan_categories > 0) ? 'warning' : 'success';
echo html_writer::start_tag('div', ['class' => 'qd-card ' . $qcatclass]);
echo html_writer::tag('div', 'Catégories (orphelines)', ['class' => 'qd-card-title']);
echo html_writer::tag('div', (int)$cat_stats->orphan_categories, ['class' => 'qd-card-value']);
echo html_writer::tag('div', 'sur ' . (int)$cat_stats->total_categories, ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

$bclass = ((int)$broken_link_stats->questions_with_broken_links > 0) ? 'danger' : 'success';
echo html_writer::start_tag('div', ['class' => 'qd-card ' . $bclass]);
echo html_writer::tag('div', get_string('questions_with_broken_links', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', (int)$broken_link_stats->questions_with_broken_links, ['class' => 'qd-card-value']);
echo html_writer::tag('div', (int)$broken_link_stats->total_broken_links . ' liens cassés', ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'qd-card']);
echo html_writer::tag('div', get_string('total_questions_stats', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', number_format((int)$question_stats->total_questions), ['class' => 'qd-card-value']);
echo html_writer::tag('div', 'Base de questions', ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

echo html_writer::end_tag('div');

echo html_writer::start_div('', ['style' => 'margin: 10px 0 30px 0; display: flex; gap: 10px; flex-wrap: wrap;']);
echo html_writer::link(new moodle_url('/local/question_diagnostic/categories.php'), '📂 Catégories →', ['class' => 'btn btn-info']);
echo html_writer::link(new moodle_url('/local/question_diagnostic/broken_links.php'), '🔗 Liens cassés (questions) →', ['class' => 'btn btn-info']);
echo html_writer::link(new moodle_url('/local/question_diagnostic/monitoring.php'), '📊 Monitoring →', ['class' => 'btn btn-outline-secondary']);
echo html_writer::end_div();

// -------------------------------------------------------------------------
// Recommendations (simple)
// -------------------------------------------------------------------------
echo html_writer::tag('h3', '💡 ' . get_string('site_diagnostic_recommendations', 'local_question_diagnostic'));

$recs = [];

if ($db_issues > 0) {
    $recs[] = html_writer::start_div('alert alert-danger') .
        get_string('site_diagnostic_rec_db', 'local_question_diagnostic', $db_issues) .
        html_writer::end_div();
}
if ($course_issues > 0) {
    $recs[] = html_writer::start_div('alert alert-warning') .
        get_string('site_diagnostic_rec_courses', 'local_question_diagnostic', $course_issues) .
        html_writer::end_div();
}
if ((int)$filedir_sample->missing > 0) {
    $recs[] = html_writer::start_div('alert alert-danger') .
        get_string('site_diagnostic_rec_filedir', 'local_question_diagnostic', (object)[
            'missing' => (int)$filedir_sample->missing,
            'checked' => (int)$filedir_sample->checked,
        ]) .
        html_writer::end_div();
}
if ((int)$orphan_stats->total_orphans > 0) {
    $recs[] = html_writer::start_div('alert alert-info') .
        get_string('site_diagnostic_rec_orphans', 'local_question_diagnostic', (object)[
            'count' => (int)$orphan_stats->total_orphans,
            'size' => (string)($orphan_stats->total_filesize_formatted ?? ''),
        ]) .
        html_writer::end_div();
}
if ((int)$broken_link_stats->questions_with_broken_links > 0) {
    $recs[] = html_writer::start_div('alert alert-warning') .
        get_string('site_diagnostic_rec_broken_links', 'local_question_diagnostic', (object)[
            'qcount' => (int)$broken_link_stats->questions_with_broken_links,
            'lcount' => (int)$broken_link_stats->total_broken_links,
        ]) .
        html_writer::end_div();
}

if (empty($recs)) {
    echo html_writer::start_div('alert alert-success');
    echo get_string('site_diagnostic_no_recommendations', 'local_question_diagnostic');
    echo html_writer::end_div();
} else {
    foreach ($recs as $r) {
        echo $r;
    }
}

echo $OUTPUT->footer();

