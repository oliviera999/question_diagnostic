<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Page de tri automatisé (texte) pour les questions "Question à trier".
 *
 * - Liste les questions présentes dans : commun > "Question à trier"
 * - Propose une catégorie cible EXISTANTE (dans commun/* hors "Question à trier") à partir
 *   de la similarité entre le titre + contenu de la question et les noms/chemins de catégories.
 * - Si aucune catégorie ne ressort, propose un titre de nouvelle catégorie (sans rien créer).
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

$PAGE->set_url(new moodle_url('/local/question_diagnostic/olution_auto_sort.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('olution_auto_sort_title', 'local_question_diagnostic'));
$PAGE->set_heading(get_string('olution_auto_sort_heading', 'local_question_diagnostic'));
$PAGE->set_pagelayout('admin');

// Pagination + seuil.
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 50, PARAM_INT);
$minscore = optional_param('minscore', '0.30', PARAM_RAW_TRIMMED);
$mode = optional_param('mode', 'heuristic', PARAM_ALPHANUMEXT);
$perpage = max(10, min(200, (int)$perpage));
$page = max(0, (int)$page);
$minscore = max(0.0, min(1.0, (float)$minscore));
$mode = ($mode === 'ai') ? 'ai' : 'heuristic';

echo $OUTPUT->header();

echo local_question_diagnostic_render_version_badge();

// Bouton purge cache.
echo html_writer::start_div('text-right', ['style' => 'margin-bottom: 20px;']);
echo local_question_diagnostic_render_cache_purge_button();
echo html_writer::end_div();

// Bouton retour.
echo html_writer::div(
    local_question_diagnostic_render_back_link('olution_auto_sort.php'),
    'mb-3'
);

echo html_writer::tag('h2', get_string('olution_auto_sort_title', 'local_question_diagnostic'));
echo html_writer::tag('p', get_string('olution_auto_sort_explain', 'local_question_diagnostic'));
echo html_writer::div(
    html_writer::link(new moodle_url('/local/question_diagnostic/ai_debug.php'), get_string('ai_debug_link', 'local_question_diagnostic')),
    'mb-3'
);

$triage = olution_manager::get_triage_category();
if (!$triage) {
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
echo html_writer::tag('span', format_string($triage->name));
echo html_writer::tag('small', ' (ID: ' . (int)$triage->id . ')', ['class' => 'text-muted ml-2']);
echo html_writer::end_div();

// Contrôles.
echo html_writer::start_div('mb-3');
// Mode IA si disponible.
$aienabled = false;
try {
    $aienabled = \local_question_diagnostic\ai_suggester::is_available();
} catch (\Throwable $t) {
    $aienabled = false;
}

echo html_writer::start_div('mb-2');
echo html_writer::tag('strong', get_string('olution_auto_sort_mode', 'local_question_diagnostic') . ': ');
$baseparams = [
    'page' => $page,
    'perpage' => $perpage,
    'minscore' => $minscore,
];
$heururl = new moodle_url('/local/question_diagnostic/olution_auto_sort.php', $baseparams + ['mode' => 'heuristic']);
echo html_writer::link($heururl, get_string('olution_auto_sort_mode_heuristic', 'local_question_diagnostic'), [
    'class' => 'btn btn-sm ' . ($mode === 'heuristic' ? 'btn-primary' : 'btn-secondary'),
]);
if ($aienabled) {
    $aiurl = new moodle_url('/local/question_diagnostic/olution_auto_sort.php', $baseparams + ['mode' => 'ai']);
    echo ' ';
    echo html_writer::link($aiurl, get_string('olution_auto_sort_mode_ai', 'local_question_diagnostic'), [
        'class' => 'btn btn-sm ' . ($mode === 'ai' ? 'btn-primary' : 'btn-secondary'),
    ]);
} else {
    echo ' ';
    echo html_writer::tag('small', get_string('olution_auto_sort_ai_unavailable', 'local_question_diagnostic'), ['class' => 'text-muted ml-2']);
}
echo html_writer::end_div();

echo html_writer::start_tag('form', ['method' => 'get', 'action' => $PAGE->url->out(false), 'class' => 'form-inline']);
echo html_writer::tag('label', get_string('olution_auto_sort_threshold', 'local_question_diagnostic') . ' ', ['class' => 'mr-2']);
echo html_writer::empty_tag('input', [
    'type' => 'number',
    'name' => 'minscore',
    'min' => '0',
    'max' => '1',
    'step' => '0.05',
    'value' => (string)$minscore,
    'class' => 'form-control mr-2',
    'style' => 'width: 110px;',
]);
echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'perpage',
    'value' => (string)$perpage,
]);
echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'mode',
    'value' => $mode,
]);
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-secondary',
    'value' => get_string('apply', 'core'),
]);
echo html_writer::end_tag('form');
echo html_writer::end_div();

// Liste.
$offset = $page * $perpage;
$total = 0;
$rows = olution_manager::get_triage_auto_sort_candidates_paginated($perpage, $offset, $total, $minscore, $mode);

// Indiquer explicitement si l'IA est réellement utilisée ou si on est en fallback.
$used = ['ai' => 0, 'heuristic' => 0];
foreach ((array)$rows as $r) {
    $m = (string)($r['mode'] ?? 'heuristic');
    if ($m === 'ai') {
        $used['ai']++;
    } else {
        $used['heuristic']++;
    }
}
if ($mode === 'ai') {
    if (!$aienabled) {
        echo $OUTPUT->notification(
            get_string('olution_auto_sort_ai_unavailable', 'local_question_diagnostic'),
            \core\output\notification::NOTIFY_WARNING
        );
    } else if (!empty($rows) && $used['ai'] === 0) {
        echo $OUTPUT->notification(
            get_string('olution_auto_sort_fallback_active', 'local_question_diagnostic'),
            \core\output\notification::NOTIFY_WARNING
        );
    } else if (!empty($rows) && $used['heuristic'] > 0) {
        echo $OUTPUT->notification(
            get_string('olution_auto_sort_partial_fallback', 'local_question_diagnostic', (int)$used['heuristic']),
            \core\output\notification::NOTIFY_WARNING
        );
    }
}

// Formulaire global pour déplacer une sélection (réutilise move_selected).
$returnurl = new moodle_url('/local/question_diagnostic/olution_auto_sort.php', [
    'page' => $page,
    'perpage' => $perpage,
    'minscore' => $minscore,
    'mode' => $mode,
]);
$move_selected_url = new moodle_url('/local/question_diagnostic/actions/move_to_olution.php', [
    'action' => 'move_selected',
    'sesskey' => sesskey(),
    'returnurl' => $returnurl->out(false),
]);

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $move_selected_url->out(false),
    'id' => 'qd-auto-sort-move-selected-form',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'returnurl', 'value' => $returnurl->out(false)]);

echo html_writer::start_div('mb-3');
echo html_writer::tag('strong', get_string('selected_questions', 'local_question_diagnostic') . ': ', ['class' => 'mr-1']);
echo html_writer::tag('span', '0', ['id' => 'qd-auto-sort-selected-count']);
echo ' ';
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => get_string('move_selected_button', 'local_question_diagnostic'),
    'class' => 'btn btn-primary btn-sm ml-2',
    'id' => 'qd-auto-sort-move-selected-btn',
    'disabled' => 'disabled',
]);
echo html_writer::end_div();

if (empty($rows)) {
    echo $OUTPUT->notification(
        get_string('olution_auto_sort_no_results', 'local_question_diagnostic'),
        \core\output\notification::NOTIFY_INFO
    );
} else {
    echo html_writer::start_tag('table', ['class' => 'table table-sm table-striped']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('select', 'local_question_diagnostic'));
    echo html_writer::tag('th', get_string('question_id', 'local_question_diagnostic'));
    echo html_writer::tag('th', get_string('question_name', 'local_question_diagnostic'));
    echo html_writer::tag('th', get_string('question_type', 'local_question_diagnostic'));
    echo html_writer::tag('th', get_string('question_content', 'local_question_diagnostic'));
    echo html_writer::tag('th', get_string('olution_auto_sort_suggestion', 'local_question_diagnostic'));
    echo html_writer::tag('th', get_string('olution_auto_sort_used_mode', 'local_question_diagnostic'));
    echo html_writer::tag('th', get_string('actions', 'local_question_diagnostic'));
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');

    echo html_writer::start_tag('tbody');
    foreach ($rows as $row) {
        $q = $row['question'];
        $best = $row['best_target'];
        $score = (float)$row['best_score'];

        $qid = (int)$q->id;
        $qname = format_string((string)$q->name);
        $qtype = s((string)$q->qtype);

        $qurl = local_question_diagnostic_get_question_bank_url($triage, $qid);
        if ($qurl) {
            $qnamecell = html_writer::link($qurl, $qname, ['target' => '_blank']);
        } else {
            $qnamecell = $qname;
        }

        $qtext = (string)($q->questiontext ?? '');
        $snippet = $qtext;
        if (strlen($snippet) > 220) {
            $snippet = substr($snippet, 0, 220) . '…';
        }
        $snippet = s($snippet);

        echo html_writer::start_tag('tr');

        // Select.
        echo html_writer::start_tag('td');
        if ($best) {
            echo html_writer::empty_tag('input', [
                'type' => 'checkbox',
                'name' => 'ops[]',
                'value' => $qid . ':' . (int)$best->id,
                'class' => 'qd-auto-sort-op-checkbox',
            ]);
        } else {
            echo '-';
        }
        echo html_writer::end_tag('td');

        echo html_writer::tag('td', $qid);
        echo html_writer::tag('td', $qnamecell);
        echo html_writer::tag('td', $qtype);
        echo html_writer::tag('td', $snippet, ['style' => 'max-width: 420px;']);

        // Suggestion.
        echo html_writer::start_tag('td');
        if ($best) {
            $crumb = local_question_diagnostic_get_question_category_breadcrumb((int)$best->id);
            $turl = local_question_diagnostic_get_question_bank_url($best);
            $label = s($crumb . ' (ID: ' . (int)$best->id . ')');
            if ($turl) {
                echo html_writer::link($turl, $label, ['target' => '_blank']);
            } else {
                echo $label;
            }
            echo html_writer::empty_tag('br');
            echo html_writer::tag('small', get_string('olution_auto_sort_score', 'local_question_diagnostic', (object)['score' => sprintf('%.2f', $score)]), [
                'class' => 'text-muted',
            ]);
            if (!empty($row['ai_reason']) && !empty($row['mode']) && $row['mode'] === 'ai') {
                echo html_writer::empty_tag('br');
                echo html_writer::tag('small', s((string)$row['ai_reason']), ['class' => 'text-muted']);
            }
        } else {
            echo html_writer::tag('span', get_string('olution_auto_sort_no_match', 'local_question_diagnostic'), ['class' => 'text-muted']);
            if (!empty($row['proposed_new_category'])) {
                echo html_writer::empty_tag('br');
                echo html_writer::tag('small',
                    get_string('olution_auto_sort_proposed_new_category', 'local_question_diagnostic', s((string)$row['proposed_new_category'])),
                    ['class' => 'text-muted']
                );
            }
        }
        echo html_writer::end_tag('td');

        // Mode réellement utilisé (IA vs fallback).
        $usedmode = (string)($row['mode'] ?? 'heuristic');
        echo html_writer::start_tag('td');
        if ($usedmode === 'ai') {
            echo html_writer::tag('span', get_string('olution_auto_sort_used_mode_ai', 'local_question_diagnostic'), ['class' => 'badge badge-success']);
        } else if ($mode === 'ai') {
            echo html_writer::tag('span', get_string('olution_auto_sort_used_mode_fallback', 'local_question_diagnostic'), ['class' => 'badge badge-warning']);
        } else {
            echo html_writer::tag('span', get_string('olution_auto_sort_used_mode_heuristic', 'local_question_diagnostic'), ['class' => 'badge badge-secondary']);
        }
        echo html_writer::end_tag('td');

        // Actions.
        echo html_writer::start_tag('td');
        if ($best) {
            $move_url = new moodle_url('/local/question_diagnostic/actions/move_to_olution.php', [
                'action' => 'move_one',
                'questionid' => $qid,
                'targetcatid' => (int)$best->id,
                'sesskey' => sesskey(),
                'returnurl' => $returnurl->out(false),
            ]);
            echo html_writer::link($move_url, get_string('move', 'local_question_diagnostic'), ['class' => 'btn btn-sm btn-primary']);
        } else {
            echo '-';
        }
        echo html_writer::end_tag('td');

        echo html_writer::end_tag('tr');
    }
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');

    if ($total > $perpage) {
        $baseurl = new moodle_url('/local/question_diagnostic/olution_auto_sort.php', [
            'perpage' => $perpage,
            'minscore' => $minscore,
            'mode' => $mode,
        ]);
        echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);
    }
}

echo html_writer::end_tag('form');

// JS minimal pour comptage sélection.
echo html_writer::tag('script', "
    (function() {
        var countEl = document.getElementById('qd-auto-sort-selected-count');
        var btn = document.getElementById('qd-auto-sort-move-selected-btn');
        function update() {
            var checked = document.querySelectorAll('.qd-auto-sort-op-checkbox:checked').length;
            if (countEl) { countEl.textContent = String(checked); }
            if (btn) { btn.disabled = checked === 0; }
        }
        document.addEventListener('change', function(e) {
            var t = e.target;
            if (!t) return;
            if (t.classList && t.classList.contains('qd-auto-sort-op-checkbox')) {
                update();
            }
        });
        update();
    })();
", ['type' => 'text/javascript']);

echo $OUTPUT->footer();


