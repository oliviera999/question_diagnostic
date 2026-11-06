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
 * « Réparation » d'un fichier orphelin : copie vers une question choisie explicitement par l'administrateur.
 *
 * Étape 1 (GET) : saisie de l'id de la question cible.
 * Étape 2 (GET) : aperçu de la cible et boutons de confirmation (POST).
 * Étape 3 (POST + sesskey + confirm=1) : copie via l'API de fichiers ; l'original reste intact.
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/../classes/orphan_file_detector.php');
require_once(__DIR__ . '/../classes/orphan_file_repairer.php');

use local_question_diagnostic\orphan_file_detector;
use local_question_diagnostic\orphan_file_repairer;

// Contrôle d'accès : capacité du plugin + administrateur du site (écriture).
local_question_diagnostic_require_access('configureplugin', true);

$fileid = required_param('id', PARAM_INT);
$questionid = optional_param('questionid', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$dryrun = optional_param('dryrun', 0, PARAM_BOOL);

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/question_diagnostic/actions/orphan_repair.php', ['id' => $fileid]));
$PAGE->set_title(get_string('repair_orphan', 'local_question_diagnostic'));
$PAGE->set_heading(get_string('orphan_files_heading', 'local_question_diagnostic'));
$PAGE->set_pagelayout('admin');

$returnurl = new moodle_url('/local/question_diagnostic/orphan_files.php');

$filerecord = $DB->get_record('files', ['id' => $fileid]);
if (!$filerecord) {
    redirect($returnurl, get_string('repair_file_not_found', 'local_question_diagnostic'), null,
        \core\output\notification::NOTIFY_ERROR);
}
if (!orphan_file_repairer::can_repair($filerecord)) {
    redirect($returnurl, get_string('orphan_repair_not_allowed', 'local_question_diagnostic'), null,
        \core\output\notification::NOTIFY_ERROR);
}

// ======================================================================
// EXÉCUTION (POST + sesskey + confirmation uniquement)
// ======================================================================
if ($confirm && data_submitted()) {
    require_sesskey();
    // copy_to_question revérifie le statut orphelin et la cible au moment de l'exécution.
    $result = orphan_file_repairer::copy_to_question($fileid, $questionid, (bool)$dryrun);
    if ($dryrun) {
        $type = \core\output\notification::NOTIFY_INFO;
    } else {
        $type = $result['success'] ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_ERROR;
    }
    redirect($returnurl, s($result['message']), null, $type);
}

// ======================================================================
// AFFICHAGE (aucune modification)
// ======================================================================
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('repair_modal_title', 'local_question_diagnostic'));

// Informations sur le fichier (valeurs issues de la base : toujours échappées).
$info = new html_table();
$info->attributes['class'] = 'table table-sm generaltable w-auto';
$info->data = [
    [get_string('orphan_file_id', 'local_question_diagnostic'), (int)$filerecord->id],
    [get_string('orphan_filename', 'local_question_diagnostic'), s($filerecord->filename)],
    [get_string('orphan_component', 'local_question_diagnostic'), s($filerecord->component)],
    [get_string('orphan_filearea', 'local_question_diagnostic'), s($filerecord->filearea)],
    [get_string('orphan_filesize', 'local_question_diagnostic'), s(orphan_file_detector::format_filesize($filerecord->filesize))],
];
echo html_writer::table($info);

echo $OUTPUT->notification(s(get_string('orphan_repair_explanation', 'local_question_diagnostic')),
    \core\output\notification::NOTIFY_INFO, false);

// Étape 1 : choix explicite de la question cible (formulaire GET, sans effet de bord).
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $PAGE->url->out_omit_querystring(),
    'class' => 'form-inline my-3']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => (int)$fileid]);
echo html_writer::label(get_string('orphan_repair_questionid', 'local_question_diagnostic'), 'qd-questionid', true,
    ['class' => 'mr-2']);
echo html_writer::empty_tag('input', ['type' => 'number', 'min' => 1, 'name' => 'questionid', 'id' => 'qd-questionid',
    'value' => $questionid > 0 ? $questionid : '', 'class' => 'form-control mr-2', 'required' => 'required']);
echo html_writer::tag('button', s(get_string('orphan_repair_preview', 'local_question_diagnostic')),
    ['type' => 'submit', 'class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');

// Étape 2 : aperçu de la cible et confirmation par POST.
if ($questionid > 0) {
    $target = orphan_file_repairer::get_question_target($questionid);
    if (!$target) {
        echo $OUTPUT->notification(s(get_string('repair_target_not_found', 'local_question_diagnostic')),
            \core\output\notification::NOTIFY_ERROR, false);
    } else {
        $targetcontext = context::instance_by_id($target->contextid, IGNORE_MISSING);
        $preview = new html_table();
        $preview->attributes['class'] = 'table table-sm generaltable w-auto';
        $preview->data = [
            [get_string('orphan_repair_target_question', 'local_question_diagnostic'),
                (int)$target->id . ' — ' . s($target->name) . ' (' . s($target->qtype) . ')'],
            [get_string('category'), s($target->categoryname)],
            // get_context_name() applique format_string() aux noms issus de la base.
            [get_string('context', 'role'), $targetcontext ? $targetcontext->get_context_name(true, true) : '-'],
            [get_string('orphan_filearea', 'local_question_diagnostic'), s(orphan_file_repairer::TARGET_FILEAREA)],
        ];
        echo html_writer::table($preview);

        $actionurl = new moodle_url('/local/question_diagnostic/actions/orphan_repair.php');
        $params = ['id' => (int)$fileid, 'questionid' => (int)$target->id, 'confirm' => 1];
        echo html_writer::start_div('my-3');
        echo orphan_file_detector::render_post_button($actionurl, $params,
            get_string('orphan_repair_copy_button', 'local_question_diagnostic'), 'btn btn-primary');
        echo orphan_file_detector::render_post_button($actionurl, $params + ['dryrun' => 1],
            get_string('repair_dry_run', 'local_question_diagnostic'), 'btn btn-warning');
        echo html_writer::end_div();
    }
}

echo html_writer::div(html_writer::link($returnurl, get_string('cancel'), ['class' => 'btn btn-secondary']), 'my-4');

echo $OUTPUT->footer();
