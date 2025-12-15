<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Page de gestion des doublons cours → Olution
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

$PAGE->set_url(new moodle_url('/local/question_diagnostic/olution_duplicates.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('olution_duplicates_title', 'local_question_diagnostic'));
$PAGE->set_heading(get_string('olution_duplicates_heading', 'local_question_diagnostic'));
$PAGE->set_pagelayout('admin');

// Pagination
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 50, PARAM_INT);

echo $OUTPUT->header();

// Badge de version
echo local_question_diagnostic_render_version_badge();

// Afficher le bouton de purge des caches
echo html_writer::start_div('text-right', ['style' => 'margin-bottom: 20px;']);
echo local_question_diagnostic_render_cache_purge_button();
echo html_writer::end_div();

// Bouton retour
echo html_writer::div(
    local_question_diagnostic_render_back_link('olution_duplicates.php'),
    'mb-3'
);

// Titre de la page
echo html_writer::tag('h2', get_string('olution_duplicates_title', 'local_question_diagnostic'));

// Vérifier que la catégorie Olution existe
$olution = local_question_diagnostic_find_olution_category();

if (!$olution) {
    // Afficher un message d'erreur si Olution n'existe pas
    echo $OUTPUT->notification(
        get_string('olution_not_found', 'local_question_diagnostic'),
        \core\output\notification::NOTIFY_ERROR
    );
    
    echo html_writer::tag('p', get_string('olution_not_found_help', 'local_question_diagnostic'));
    
    echo $OUTPUT->footer();
    exit;
}

// Afficher quelle catégorie de questions a été trouvée
echo html_writer::start_div('alert alert-info mb-3');
echo html_writer::tag('strong', '✅ Catégorie de questions Olution détectée : ');
echo html_writer::tag('span', format_string($olution->name));
echo html_writer::tag('small', ' (ID: ' . $olution->id . ')', ['class' => 'text-muted ml-2']);
echo html_writer::empty_tag('br');

// Afficher le contexte (système, cours ou catégorie de cours)
if (isset($olution->context_type)) {
    if ($olution->context_type === 'course_category') {
        echo html_writer::tag('small', '📚 Contexte : Catégorie de cours "' . format_string($olution->course_category_name) . '" (ID: ' . $olution->course_category_id . ')', ['class' => 'text-muted']);
        echo html_writer::empty_tag('br');
        echo html_writer::tag('small', '   → Cours : "' . format_string($olution->course_name) . '" (ID: ' . $olution->course_id . ')', ['class' => 'text-muted']);
    } else if ($olution->context_type === 'course') {
        echo html_writer::tag('small', '📚 Contexte : Cours "' . format_string($olution->course_name) . '" (ID: ' . $olution->course_id . ')', ['class' => 'text-muted']);
    } else {
        echo html_writer::tag('small', '🌐 Contexte : Système', ['class' => 'text-muted']);
    }
} else {
    echo html_writer::tag('small', '🌐 Contexte : Système', ['class' => 'text-muted']);
}
echo html_writer::empty_tag('br');

$subcats_count = $DB->count_records('question_categories', ['parent' => $olution->id]);
$all_subcats = local_question_diagnostic_get_olution_subcategories();
echo html_writer::tag('small', 'Cette catégorie contient ' . count($all_subcats) . ' sous-catégorie(s) (toute profondeur)', ['class' => 'text-muted']);
echo html_writer::end_div();

// Récupérer les statistiques
$stats = olution_manager::get_duplicate_stats();

// Afficher les statistiques globales
echo html_writer::start_div('row mb-4');

// Carte 1 : Total doublons
echo html_writer::start_div('col-md-3');
echo html_writer::start_div('card qd-card');
echo html_writer::start_div('card-body text-center');
echo html_writer::tag('h3', $stats->total_duplicates, ['class' => 'text-primary']);
echo html_writer::tag('p', get_string('olution_total_duplicates', 'local_question_diagnostic'), ['class' => 'mb-0']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Carte 2 : Questions déplaçables
echo html_writer::start_div('col-md-3');
echo html_writer::start_div('card qd-card');
echo html_writer::start_div('card-body text-center');
echo html_writer::tag('h3', $stats->movable_questions, ['class' => 'text-success']);
echo html_writer::tag('p', get_string('olution_movable_questions', 'local_question_diagnostic'), ['class' => 'mb-0']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Carte 3 : Questions non-déplaçables
echo html_writer::start_div('col-md-3');
echo html_writer::start_div('card qd-card');
echo html_writer::start_div('card-body text-center');
echo html_writer::tag('h3', $stats->unmovable_questions, ['class' => 'text-warning']);
echo html_writer::tag('p', get_string('olution_unmovable_questions', 'local_question_diagnostic'), ['class' => 'mb-0']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Carte 4 : Sous-catégories Olution
echo html_writer::start_div('col-md-3');
echo html_writer::start_div('card qd-card');
echo html_writer::start_div('card-body text-center');
echo html_writer::tag('h3', $stats->olution_courses_count, ['class' => 'text-info']);
echo html_writer::tag('p', get_string('olution_subcategories_count', 'local_question_diagnostic'), ['class' => 'mb-0']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div();

// Si aucun doublon trouvé
if ($stats->total_duplicates == 0) {
    echo $OUTPUT->notification(
        get_string('olution_no_duplicates_found', 'local_question_diagnostic'),
        \core\output\notification::NOTIFY_SUCCESS
    );
    
    echo $OUTPUT->footer();
    exit;
}

// Bouton d'action globale
if ($stats->movable_questions > 0) {
    echo html_writer::start_div('mb-3');
    $move_all_url = new moodle_url('/local/question_diagnostic/actions/move_to_olution.php', [
        'action' => 'move_all',
        'sesskey' => sesskey()
    ]);
    echo html_writer::link(
        $move_all_url,
        get_string('olution_move_all_button', 'local_question_diagnostic', $stats->movable_questions),
        ['class' => 'btn btn-primary btn-lg']
    );
    echo html_writer::end_div();
}

// Bouton triage (commun > Question à trier).
$triagestats = olution_manager::get_triage_stats();
if (!empty($triagestats->triage_exists)) {
    echo html_writer::start_div('mb-3');
    $triage_url = new moodle_url('/local/question_diagnostic/olution_triage.php');
    $label = get_string('olution_triage_button', 'local_question_diagnostic', (int)$triagestats->movable_questions);
    $classes = 'btn btn-secondary btn-lg';
    if ((int)$triagestats->movable_questions > 0) {
        $classes = 'btn btn-warning btn-lg';
    }
    echo html_writer::link($triage_url, $label, ['class' => $classes]);
    echo html_writer::end_div();
}

// Récupérer les groupes de doublons pour la page actuelle
$offset = $page * $perpage;
$totalgroups = 0;
$duplicate_groups = olution_manager::find_all_duplicates_for_olution_paginated($perpage, $offset, $totalgroups);

// Afficher la liste des groupes de doublons
echo html_writer::tag('h3', get_string('olution_duplicates_list', 'local_question_diagnostic'));

if (!empty($duplicate_groups)) {
    // Formulaire global pour déplacer une sélection de questions (multi-sélection).
    $move_selected_url = new moodle_url('/local/question_diagnostic/actions/move_to_olution.php', [
        'action' => 'move_selected',
        'sesskey' => sesskey(),
    ]);
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $move_selected_url->out(false),
        'id' => 'qd-olution-move-selected-form',
    ]);
    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'sesskey',
        'value' => sesskey(),
    ]);

    // Barre d'action sélection.
    echo html_writer::start_div('mb-3');
    echo html_writer::tag('strong', get_string('selected_questions', 'local_question_diagnostic') . ': ', ['class' => 'mr-1']);
    echo html_writer::tag('span', '0', ['id' => 'qd-olution-selected-count']);
    echo ' ';
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'value' => get_string('move_selected_button', 'local_question_diagnostic'),
        'class' => 'btn btn-primary btn-sm ml-2',
        'id' => 'qd-olution-move-selected-btn',
        'disabled' => 'disabled',
    ]);
    echo html_writer::end_div();

    foreach ($duplicate_groups as $group) {
        $groupid = 'g' . md5($group['group_name'] . '|' . $group['group_type']);
        // Afficher le groupe
        echo html_writer::start_div('card mb-3');
        echo html_writer::start_div('card-header bg-light');
        echo html_writer::tag('strong', format_string($group['group_name']));
        echo ' (' . $group['group_type'] . ') - ';
        echo html_writer::tag('span', $group['total_count'] . ' version(s)', ['class' => 'badge badge-primary']);
        echo ' ';
        echo html_writer::tag('span', $group['olution_count'] . ' dans Olution', ['class' => 'badge badge-success']);
        echo ' ';
        echo html_writer::tag('span', $group['non_olution_count'] . ' hors Olution', ['class' => 'badge badge-warning']);

        // Sélectionner tout le groupe (uniquement les lignes déplaçables).
        echo html_writer::empty_tag('br');
        echo html_writer::tag('label',
            html_writer::empty_tag('input', [
                'type' => 'checkbox',
                'class' => 'qd-olution-select-group',
                'data-group' => $groupid,
            ]) . ' ' . get_string('select_group', 'local_question_diagnostic'),
            ['class' => 'small text-muted']
        );
        
        // Catégorie cible (plus profonde)
        if ($group['target_category']) {
            echo html_writer::empty_tag('br');
            echo '🎯 Catégorie cible (profondeur ' . $group['target_depth'] . ') : ';
            $targetcrumb = local_question_diagnostic_get_question_category_breadcrumb((int)$group['target_category']->id);
            $targeturl = local_question_diagnostic_get_question_bank_url($group['target_category']);
            if ($targeturl) {
                echo html_writer::link($targeturl, html_writer::tag('strong', $targetcrumb), ['target' => '_blank']);
            } else {
                echo html_writer::tag('strong', $targetcrumb);
            }
        }
        echo html_writer::end_div();
        
        // Afficher toutes les questions du groupe
        echo html_writer::start_div('card-body');
        echo html_writer::start_tag('table', ['class' => 'table table-sm table-striped']);
        echo html_writer::start_tag('thead');
        echo html_writer::start_tag('tr');
        echo html_writer::tag('th', get_string('select', 'local_question_diagnostic'));
        echo html_writer::tag('th', get_string('question_id', 'local_question_diagnostic'));
        echo html_writer::tag('th', get_string('question_name', 'local_question_diagnostic'));
        echo html_writer::tag('th', get_string('context', 'local_question_diagnostic'));
        echo html_writer::tag('th', get_string('current_category_path', 'local_question_diagnostic'));
        echo html_writer::tag('th', get_string('olution_target_category', 'local_question_diagnostic'));
        echo html_writer::tag('th', get_string('actions', 'local_question_diagnostic'));
        echo html_writer::end_tag('tr');
        echo html_writer::end_tag('thead');
        echo html_writer::start_tag('tbody');
        
        foreach ($group['all_questions'] as $q_info) {
            $q = $q_info['question'];
            $cat = $q_info['category'];
            $in_olution = $q_info['is_in_olution'];
            $depth = $q_info['depth'];
            
            $row_class = $in_olution ? 'table-success' : '';
            if ($group['target_category'] && $cat->id == $group['target_category']->id) {
                $row_class = 'table-primary'; // C'est la catégorie cible
            }
            
            echo html_writer::start_tag('tr', ['class' => $row_class]);

            $canmove = (!$in_olution && $group['target_category'] && $cat->id != $group['target_category']->id);
            $opvalue = '';
            if ($canmove) {
                $opvalue = (int)$q->id . ':' . (int)$group['target_category']->id;
            }
            echo html_writer::start_tag('td');
            if ($canmove) {
                echo html_writer::empty_tag('input', [
                    'type' => 'checkbox',
                    'name' => 'ops[]',
                    'value' => $opvalue,
                    'class' => 'qd-olution-op-checkbox',
                    'data-group' => $groupid,
                ]);
            } else {
                echo '-';
            }
            echo html_writer::end_tag('td');

            echo html_writer::tag('td', (int)$q->id);

            // Question cliquable : ouverture de la banque de questions à l'emplacement (catégorie + qid).
            $qname = format_string($q->name);
            $qurl = local_question_diagnostic_get_question_bank_url($cat, (int)$q->id);
            if ($qurl) {
                echo html_writer::tag('td', html_writer::link($qurl, $qname, ['target' => '_blank']));
            } else {
                echo html_writer::tag('td', $qname);
            }

            // Contexte.
            $ctx = local_question_diagnostic_get_context_details((int)$cat->contextid);
            echo html_writer::tag('td', s($ctx->context_name));

            // Catégorie actuelle (chemin).
            $crumb = local_question_diagnostic_get_question_category_breadcrumb((int)$cat->id);
            $caturl = local_question_diagnostic_get_question_bank_url($cat);
            $catlabel = $crumb . ' (ID: ' . (int)$cat->id . ')';
            if ($caturl) {
                echo html_writer::tag('td', html_writer::link($caturl, s($catlabel), ['target' => '_blank']));
            } else {
                echo html_writer::tag('td', s($catlabel));
            }

            // Cible Olution (chemin).
            echo html_writer::start_tag('td');
            if (!empty($group['target_category'])) {
                $tcat = $group['target_category'];
                $tcrumb = local_question_diagnostic_get_question_category_breadcrumb((int)$tcat->id);
                $turl = local_question_diagnostic_get_question_bank_url($tcat);
                if ($turl) {
                    echo html_writer::link($turl, s($tcrumb . ' (ID: ' . (int)$tcat->id . ')'), ['target' => '_blank']);
                } else {
                    echo s($tcrumb . ' (ID: ' . (int)$tcat->id . ')');
                }
            } else {
                echo '-';
            }
            echo html_writer::end_tag('td');
            
            // Action : déplacer vers catégorie cible (uniquement si la question est hors Olution)
            echo html_writer::start_tag('td');
            if ($canmove) {
                $move_url = new moodle_url('/local/question_diagnostic/actions/move_to_olution.php', [
                    'action' => 'move_one',
                    'questionid' => $q->id,
                    'targetcatid' => $group['target_category']->id,
                    'sesskey' => sesskey()
                ]);
                echo html_writer::link(
                    $move_url,
                    get_string('move', 'local_question_diagnostic') . ' →',
                    ['class' => 'btn btn-sm btn-primary']
                );
            } else if ($cat->id == $group['target_category']->id) {
                echo html_writer::tag('span', '🎯 Cible', ['class' => 'text-success']);
            } else if ($in_olution) {
                echo html_writer::tag('span', '✅ Déjà dans Olution', ['class' => 'text-muted']);
            } else {
                echo '-';
            }
            echo html_writer::end_tag('td');
            
            echo html_writer::end_tag('tr');
        }
        
        echo html_writer::end_tag('tbody');
        echo html_writer::end_tag('table');
        echo html_writer::end_div(); // card-body
        echo html_writer::end_div(); // card
    }

    // Fin formulaire global.
    echo html_writer::end_tag('form');

    // JS minimal pour comptage + sélection par groupe.
    echo html_writer::tag('script', "
        (function() {
            var countEl = document.getElementById('qd-olution-selected-count');
            var btn = document.getElementById('qd-olution-move-selected-btn');
            function update() {
                var checked = document.querySelectorAll('.qd-olution-op-checkbox:checked').length;
                if (countEl) { countEl.textContent = String(checked); }
                if (btn) { btn.disabled = checked === 0; }
            }
            document.addEventListener('change', function(e) {
                var t = e.target;
                if (!t) return;
                if (t.classList && t.classList.contains('qd-olution-op-checkbox')) {
                    update();
                }
                if (t.classList && t.classList.contains('qd-olution-select-group')) {
                    var group = t.getAttribute('data-group');
                    var boxes = document.querySelectorAll('.qd-olution-op-checkbox[data-group=\"' + group + '\"]');
                    for (var i=0; i<boxes.length; i++) { boxes[i].checked = t.checked; }
                    update();
                }
            });
            update();
        })();
    ", ['type' => 'text/javascript']);
    
    // Pagination
    if ($totalgroups > $perpage) {
        $baseurl = new moodle_url('/local/question_diagnostic/olution_duplicates.php', ['perpage' => $perpage]);
        echo $OUTPUT->paging_bar($totalgroups, $page, $perpage, $baseurl);
    }
}

echo $OUTPUT->footer();

