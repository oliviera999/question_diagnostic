<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Page de diagnostic IA (Moodle 4.5+).
 *
 * Objectif: aider à comprendre pourquoi le mode IA bascule en fallback :
 * - classes présentes (core_ai / tool_ai)
 * - méthodes disponibles
 * - test d'appel (best-effort) via ai_suggester
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/ai_suggester.php');

use local_question_diagnostic\ai_suggester;

require_login();
if (!is_siteadmin()) {
    print_error('accessdenied', 'admin');
}

$PAGE->set_url(new moodle_url('/local/question_diagnostic/ai_debug.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('ai_debug_title', 'local_question_diagnostic'));
$PAGE->set_heading(get_string('ai_debug_heading', 'local_question_diagnostic'));
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();
echo local_question_diagnostic_render_version_badge();
echo html_writer::div(local_question_diagnostic_render_back_link('ai_debug.php'), 'mb-3');

echo html_writer::tag('h2', get_string('ai_debug_title', 'local_question_diagnostic'));

echo html_writer::start_div('alert alert-info');
echo html_writer::tag('p', 'CFG->version: ' . (int)($CFG->version ?? 0));
echo html_writer::tag('p', 'ai_suggester::is_available(): ' . (ai_suggester::is_available() ? 'true' : 'false'));
echo html_writer::end_div();

$classes = [
    '\\core_ai\\manager',
    '\\tool_ai\\manager',
    '\\tool_ai\\ai_manager',
];

echo html_writer::tag('h3', get_string('ai_debug_classes', 'local_question_diagnostic'));
echo html_writer::start_tag('table', ['class' => 'table table-sm table-striped']);
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
echo html_writer::tag('th', 'Class');
echo html_writer::tag('th', 'Exists');
echo html_writer::tag('th', 'Methods');
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');
echo html_writer::start_tag('tbody');
foreach ($classes as $c) {
    $exists = class_exists($c);
    $methods = [];
    foreach (['generate_text', 'chat', 'complete'] as $m) {
        if ($exists && method_exists($c, $m)) {
            $methods[] = $m;
        }
    }
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', s($c));
    echo html_writer::tag('td', $exists ? '✅' : '❌');
    echo html_writer::tag('td', !empty($methods) ? s(implode(', ', $methods)) : '-');
    echo html_writer::end_tag('tr');
}
echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');

echo html_writer::tag('h3', get_string('ai_debug_test', 'local_question_diagnostic'));
$test = ai_suggester::suggest(
    'Test question: fractions addition',
    'Compute 1/2 + 1/3 and simplify. Choose the closest math category.',
    ['Olution / commun / Math / Fractions', 'Olution / commun / History / WW2', 'Olution / commun / French / Grammar']
);

echo html_writer::start_tag('pre', ['style' => 'white-space: pre-wrap;']);
echo s(json_encode($test, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo html_writer::end_tag('pre');

echo $OUTPUT->footer();


