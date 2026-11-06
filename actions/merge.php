<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Fusion de deux catégories du même contexte.
 *
 * - GET : page de confirmation, avec contrôle préalable (category_manager::get_merge_refusal) ;
 * - POST + sesskey + confirm : fusion par l'API du cœur, dans une transaction.
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/../classes/category_manager.php');
require_once(__DIR__ . '/../classes/question_analyzer.php');

use local_question_diagnostic\category_manager;
use local_question_diagnostic\question_analyzer;

require_login();
require_sesskey();

if (!is_siteadmin()) {
    throw new \moodle_exception('accessdenied', 'admin');
}

$sourceid = required_param('source', PARAM_INT);
$destid = required_param('dest', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);
$returnurl = local_question_diagnostic_get_parent_url('actions/merge.php');
$syscontext = context_system::instance();
$PAGE->set_context($syscontext);

if ($confirm && data_submitted()) {
    $result = category_manager::merge_categories($sourceid, $destid);

    if ($result === true) {
        question_analyzer::purge_all_caches();
        redirect($returnurl, '✅ Catégories fusionnées avec succès.', null, \core\output\notification::NOTIFY_SUCCESS);
    } else {
        redirect($returnurl, '⚠️ Erreur : ' . s($result), null, \core\output\notification::NOTIFY_ERROR);
    }
}

// Confirmation.
$source = $DB->get_record('question_categories', ['id' => $sourceid]);
$dest = $DB->get_record('question_categories', ['id' => $destid]);
if (!$source || !$dest) {
    redirect($returnurl, 'Catégories introuvables.', null, \core\output\notification::NOTIFY_ERROR);
}

$PAGE->set_url(new moodle_url('/local/question_diagnostic/actions/merge.php', [
    'source' => $sourceid,
    'dest' => $destid,
]));
$PAGE->set_title('Confirmation de fusion');

\local_question_diagnostic\local\usage_checker::reset_caches();
$refusal = category_manager::get_merge_refusal($source, $dest);
$sourcestats = category_manager::get_category_stats($source);

echo $OUTPUT->header();
echo local_question_diagnostic_render_version_badge();
echo $OUTPUT->heading('🔀 Confirmation de fusion');

echo html_writer::tag('p', 'Vous êtes sur le point de fusionner :');
echo html_writer::start_tag('ul');
echo html_writer::tag('li', '<strong>Source :</strong> ' . format_string($source->name, true, ['context' => $syscontext])
    . ' (ID: ' . (int)$sourceid . ')');
echo html_writer::tag('li', '<strong>Destination :</strong> ' . format_string($dest->name, true, ['context' => $syscontext])
    . ' (ID: ' . (int)$destid . ')');
echo html_writer::end_tag('ul');

if ($refusal !== null) {
    echo html_writer::div(s($refusal), 'alert alert-danger');
    echo html_writer::link($returnurl, 'Retour', ['class' => 'btn btn-secondary']);
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::tag('p', 'Cette action va :');
echo html_writer::start_tag('ol');
echo html_writer::tag('li', 'Déplacer <strong>' . (int)$sourcestats->total_questions . ' question(s)</strong> vers la destination');
echo html_writer::tag('li', 'Déplacer <strong>' . (int)$sourcestats->subcategories . ' sous-catégorie(s)</strong> vers la destination');
echo html_writer::tag('li', 'Supprimer la catégorie source');
echo html_writer::end_tag('ol');

echo html_writer::div(get_string('merge_categories_rules', 'local_question_diagnostic'), 'alert alert-info');
echo html_writer::tag('p', '⚠️ <strong>Cette action est irréversible.</strong>', ['class' => 'alert alert-warning']);

$confirmurl = new moodle_url('/local/question_diagnostic/actions/merge.php', [
    'source' => $sourceid,
    'dest' => $destid,
    'confirm' => 1,
    'sesskey' => sesskey(),
]);

echo html_writer::start_tag('div', ['style' => 'margin-top: 20px; display: flex; gap: 10px; align-items: center;']);
echo $OUTPUT->render(new single_button($confirmurl, 'Oui, fusionner', 'post', single_button::BUTTON_PRIMARY));
echo html_writer::link($returnurl, 'Annuler', ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('div');

echo $OUTPUT->footer();
