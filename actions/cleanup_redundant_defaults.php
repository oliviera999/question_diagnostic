<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Nettoyage des catégories « par défaut » redondantes (vides) d'un même contexte.
 *
 * - GET : liste de ce qui sera supprimé (aucune écriture) ;
 * - POST + sesskey + confirm : suppression par category_manager::delete_category(), qui revérifie chaque
 *   catégorie (jamais la racine, jamais la catégorie par défaut utilisée par Moodle, jamais une catégorie avec
 *   description/idnumber, non vide ou utilisée par une question aléatoire). Seule la protection « nom par
 *   défaut » est levée, pour les doublons.
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/../classes/category_manager.php');

use local_question_diagnostic\category_manager;

// Contrôle d'accès : capacité du plugin + administrateur du site (écriture).
local_question_diagnostic_require_access('deletecategories', true);
// Le sesskey n'est exigé qu'à l'exécution (POST) : la confirmation s'affiche depuis un simple lien GET.

$action = required_param('action', PARAM_ALPHAEXT);
$confirm = optional_param('confirm', 0, PARAM_INT);
$returnurl = new moodle_url('/local/question_diagnostic/questions_cleanup.php');

$syscontext = context_system::instance();
$PAGE->set_context($syscontext);
$PAGE->set_url(new moodle_url('/local/question_diagnostic/actions/cleanup_redundant_defaults.php', ['action' => $action]));
$PAGE->set_pagelayout('admin');

if ($action !== 'cleanup_all') {
    redirect($returnurl);
}

\local_question_diagnostic\local\usage_checker::reset_caches();
$groups = category_manager::get_redundant_default_categories();

if ($confirm && data_submitted()) {
    require_sesskey();
    $count = 0;
    $errors = [];
    foreach ($groups as $group) {
        foreach ($group['delete'] as $cattodelete) {
            $result = category_manager::delete_category((int)$cattodelete->id, true);
            if ($result === true) {
                $count++;
            } else {
                $errors[] = s('Catégorie ' . (int)$cattodelete->id . ' : ' . $result);
            }
        }
    }

    if ($count > 0) {
        \local_question_diagnostic\question_analyzer::purge_all_caches();
        \core\notification::success("✅ Nettoyage terminé : $count catégorie(s) redondante(s) supprimée(s).");
    }
    if (!empty($errors)) {
        \core\notification::error('⚠️ Certaines suppressions ont échoué :<br>' . implode('<br>', array_slice($errors, 0, 20)));
    }
    if ($count === 0 && empty($errors)) {
        \core\notification::info('Aucune catégorie à nettoyer.');
    }
    redirect($returnurl);
}

// Confirmation : liste de ce qui sera supprimé.
$PAGE->set_title(get_string('redundant_defaults_confirm_title', 'local_question_diagnostic'));
echo $OUTPUT->header();
echo local_question_diagnostic_render_version_badge();
echo $OUTPUT->heading('🧹 ' . get_string('redundant_defaults_confirm_title', 'local_question_diagnostic'));

$total = 0;
foreach ($groups as $group) {
    $total += count($group['delete']);
}

if ($total === 0) {
    echo html_writer::div('Aucune catégorie à nettoyer.', 'alert alert-info');
    echo html_writer::link($returnurl, get_string('back', 'core'), ['class' => 'btn btn-secondary']);
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::div(get_string('redundant_defaults_confirm_intro', 'local_question_diagnostic', $total), 'alert alert-warning');

foreach ($groups as $group) {
    echo html_writer::tag('h4', s($group['context_name']) . ' (contexte ' . (int)$group['context_id'] . ')');
    echo html_writer::start_tag('ul');
    if (!empty($group['keep'])) {
        echo html_writer::tag('li', '✅ Conservée : ' . format_string($group['keep']->name, true, ['context' => $syscontext])
            . ' (ID ' . (int)$group['keep']->id . ')');
    }
    foreach ($group['delete'] as $cat) {
        echo html_writer::tag('li', '🗑️ Supprimée : ' . format_string($cat->name, true, ['context' => $syscontext])
            . ' (ID ' . (int)$cat->id . ')');
    }
    foreach ($group['blocked'] ?? [] as $blocked) {
        echo html_writer::tag('li', '🛡️ Conservée : ' . format_string($blocked->category->name, true, ['context' => $syscontext])
            . ' (ID ' . (int)$blocked->category->id . ') — ' . s($blocked->reason));
    }
    echo html_writer::end_tag('ul');
}

$confirmurl = new moodle_url('/local/question_diagnostic/actions/cleanup_redundant_defaults.php', [
    'action' => 'cleanup_all',
    'confirm' => 1,
]);
echo html_writer::start_tag('div', ['style' => 'margin-top: 20px; display: flex; gap: 10px; align-items: center;']);
echo $OUTPUT->render(new single_button($confirmurl, get_string('confirm', 'core'), 'post', single_button::BUTTON_DANGER));
echo html_writer::link($returnurl, get_string('cancel', 'core'), ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('div');

echo $OUTPUT->footer();
