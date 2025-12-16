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

// Détails méthodes (filtrées) pour core_ai\manager, utile pour adapter l'appel.
if (class_exists('\\core_ai\\manager')) {
    $all = get_class_methods('\\core_ai\\manager');
    $all = is_array($all) ? $all : [];
    sort($all);

    // Filtrer les méthodes "probablement utiles" pour l'appel IA.
    $interesting = [];
    foreach ($all as $m) {
        $ml = strtolower((string)$m);
        if (strpos($ml, 'ai') !== false
            || strpos($ml, 'provider') !== false
            || strpos($ml, 'action') !== false
            || strpos($ml, 'generate') !== false
            || strpos($ml, 'chat') !== false
            || strpos($ml, 'prompt') !== false
            || strpos($ml, 'request') !== false
            || strpos($ml, 'text') !== false
            || strpos($ml, 'complete') !== false
            || strpos($ml, 'execute') !== false) {
            $interesting[] = $m;
        }
    }
    if (empty($interesting)) {
        // Si aucun match, on montre quand même la liste complète (compacte).
        $interesting = $all;
    }

    echo html_writer::tag('h3', get_string('ai_debug_methods', 'local_question_diagnostic'));
    echo html_writer::start_tag('pre', ['style' => 'white-space: pre-wrap;']);
    echo s("core_ai\\manager methods (filtered):\n" . implode("\n", $interesting));
    echo html_writer::end_tag('pre');

    // Signatures (best-effort) pour les 30 premières méthodes filtrées.
    $maxsig = 30;
    $siglines = [];
    $count = 0;
    foreach ($interesting as $m) {
        if ($count >= $maxsig) {
            break;
        }
        try {
            $rm = new ReflectionMethod('\\core_ai\\manager', $m);
            $params = [];
            foreach ($rm->getParameters() as $p) {
                $t = $p->hasType() ? (string)$p->getType() . ' ' : '';
                $def = '';
                if ($p->isOptional()) {
                    try {
                        if ($p->isDefaultValueAvailable()) {
                            $dv = $p->getDefaultValue();
                            $def = ' = ' . (is_scalar($dv) ? var_export($dv, true) : (is_null($dv) ? 'null' : gettype($dv)));
                        }
                    } catch (Throwable $t) {
                        // ignore
                    }
                }
                $params[] = $t . '$' . $p->getName() . $def;
            }
            $siglines[] = ($rm->isStatic() ? 'static ' : '') . $rm->getName() . '(' . implode(', ', $params) . ')';
            $count++;
        } catch (Throwable $t) {
            // ignore reflection errors
        }
    }
    if (!empty($siglines)) {
        echo html_writer::tag('h3', get_string('ai_debug_signatures', 'local_question_diagnostic'));
        echo html_writer::start_tag('pre', ['style' => 'white-space: pre-wrap;']);
        echo s(implode("\n", $siglines));
        echo html_writer::end_tag('pre');
    }
}

// Actions supportées + providers (si dispo).
if (class_exists('\\core_ai\\manager')) {
    echo html_writer::tag('h3', get_string('ai_debug_actions', 'local_question_diagnostic'));

    $pluginnames = [
        'core',
        'local_question_diagnostic',
    ];

    foreach ($pluginnames as $pname) {
        echo html_writer::tag('h4', 'get_supported_actions("' . s($pname) . '")');
        $actions = [];
        $err = '';
        try {
            $actions = \core_ai\manager::get_supported_actions($pname);
        } catch (Throwable $t) {
            $err = $t->getMessage();
        }
        if (!empty($err)) {
            echo $OUTPUT->notification('Error: ' . s($err), \core\output\notification::NOTIFY_WARNING);
            continue;
        }
        if (empty($actions) || !is_array($actions)) {
            echo html_writer::tag('p', 'No actions.');
            continue;
        }

        echo html_writer::start_tag('pre', ['style' => 'white-space: pre-wrap;']);
        echo s(json_encode(array_values($actions), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo html_writer::end_tag('pre');

        // Availability check for each action class (best-effort).
        echo html_writer::start_tag('table', ['class' => 'table table-sm table-striped']);
        echo html_writer::start_tag('thead');
        echo html_writer::start_tag('tr');
        echo html_writer::tag('th', 'Action');
        echo html_writer::tag('th', 'Available?');
        echo html_writer::end_tag('tr');
        echo html_writer::end_tag('thead');
        echo html_writer::start_tag('tbody');
        foreach ($actions as $a) {
            $a = (string)$a;
            $avail = false;
            $msg = '';
            try {
                $avail = \core_ai\manager::is_action_available($a);
            } catch (Throwable $t) {
                $msg = $t->getMessage();
            }
            echo html_writer::start_tag('tr');
            echo html_writer::tag('td', s($a));
            echo html_writer::tag('td', $msg !== '' ? ('⚠️ ' . s($msg)) : ($avail ? '✅' : '❌'));
            echo html_writer::end_tag('tr');
        }
        echo html_writer::end_tag('tbody');
        echo html_writer::end_tag('table');

        // Providers for actions.
        echo html_writer::tag('h5', 'get_providers_for_actions(actions, enabledonly=false)');
        try {
            $providers = \core_ai\manager::get_providers_for_actions(array_values($actions), false);
            echo html_writer::start_tag('pre', ['style' => 'white-space: pre-wrap;']);
            echo s(json_encode($providers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            echo html_writer::end_tag('pre');
        } catch (Throwable $t) {
            echo $OUTPUT->notification('Error: ' . s($t->getMessage()), \core\output\notification::NOTIFY_WARNING);
        }
    }
}

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


