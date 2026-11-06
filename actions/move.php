<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Déplacement d'une catégorie sous un autre parent du MÊME contexte.
 *
 * - GET : page de confirmation, avec contrôle préalable (category_manager::get_move_refusal) ;
 * - POST + sesskey + confirm : déplacement par l'API du cœur (update_category).
 * Le parent 0 (seconde racine, invisible dans Moodle 5.x) et le déplacement d'une racine sont interdits.
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/../classes/category_manager.php');

use local_question_diagnostic\category_manager;

require_login();
require_sesskey();

if (!is_siteadmin()) {
    throw new \moodle_exception('accessdenied', 'admin');
}

$categoryid = required_param('id', PARAM_INT);
$newparentid = required_param('parent', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);
// Retour hiérarchique, ou returnurl (ex : retour vers categories_by_context.php).
$returnurlparam = optional_param('returnurl', '', PARAM_LOCALURL);
if (!empty($returnurlparam)) {
    $returnurl = new moodle_url($returnurlparam);
} else {
    $returnurl = local_question_diagnostic_get_parent_url('actions/move.php');
}

$syscontext = context_system::instance();
$PAGE->set_context($syscontext);

if ($confirm && data_submitted()) {
    $result = category_manager::move_category($categoryid, $newparentid);

    if ($result === true) {
        redirect($returnurl, get_string('movesuccess', 'local_question_diagnostic'), null, \core\output\notification::NOTIFY_SUCCESS);
    } else {
        redirect($returnurl, get_string('moveerror', 'local_question_diagnostic') . ' ' . s($result), null,
            \core\output\notification::NOTIFY_ERROR);
    }
}

// Confirmation.
$category = $DB->get_record('question_categories', ['id' => $categoryid]);
if (!$category) {
    redirect($returnurl, 'Catégorie introuvable.', null, \core\output\notification::NOTIFY_ERROR);
}
$newparent = $newparentid > 0 ? $DB->get_record('question_categories', ['id' => $newparentid]) : null;

$PAGE->set_url(new moodle_url('/local/question_diagnostic/actions/move.php', [
    'id' => $categoryid,
    'parent' => $newparentid,
    'returnurl' => !empty($returnurlparam) ? $returnurlparam : null,
]));
$PAGE->set_title(get_string('move', 'local_question_diagnostic'));

\local_question_diagnostic\local\usage_checker::reset_caches();
$refusal = category_manager::get_move_refusal($category, (int)$newparentid);

echo $OUTPUT->header();
echo local_question_diagnostic_render_version_badge();
echo $OUTPUT->heading('📦 ' . get_string('move', 'local_question_diagnostic'));

echo html_writer::tag('p', 'Vous êtes sur le point de déplacer :');
echo html_writer::start_tag('ul');
echo html_writer::tag('li', '<strong>Catégorie :</strong> ' . format_string($category->name, true, ['context' => $syscontext])
    . ' (ID: ' . (int)$categoryid . ')');
if ($newparent) {
    echo html_writer::tag('li', '<strong>Vers le parent :</strong> '
        . format_string($newparent->name, true, ['context' => $syscontext]) . ' (ID: ' . (int)$newparentid . ')');
} else {
    echo html_writer::tag('li', '<strong>Vers le parent :</strong> ID ' . (int)$newparentid);
}
echo html_writer::end_tag('ul');

if ($refusal !== null) {
    echo html_writer::div(s($refusal), 'alert alert-danger');
    echo html_writer::link($returnurl, get_string('back', 'core'), ['class' => 'btn btn-secondary']);
    echo $OUTPUT->footer();
    exit;
}

$categorystats = category_manager::get_category_stats($category);

echo html_writer::tag('p', 'Cette catégorie contient :');
echo html_writer::start_tag('ul');
echo html_writer::tag('li', '<strong>' . (int)$categorystats->total_questions . ' question(s)</strong>');
echo html_writer::tag('li', '<strong>' . (int)$categorystats->subcategories . ' sous-catégorie(s)</strong>');
echo html_writer::end_tag('ul');

echo html_writer::tag('p', '⚠️ <strong>Cette action modifiera la hiérarchie des catégories.</strong>', ['class' => 'alert alert-warning']);

$confirmurl = new moodle_url('/local/question_diagnostic/actions/move.php', [
    'id' => $categoryid,
    'parent' => $newparentid,
    'confirm' => 1,
    'sesskey' => sesskey(),
    'returnurl' => !empty($returnurlparam) ? $returnurlparam : null,
]);

echo html_writer::start_tag('div', ['style' => 'margin-top: 20px; display: flex; gap: 10px; align-items: center;']);
echo $OUTPUT->render(new single_button($confirmurl, get_string('move', 'local_question_diagnostic'), 'post',
    single_button::BUTTON_PRIMARY));
echo html_writer::link($returnurl, get_string('cancel', 'core'), ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('div');

echo $OUTPUT->footer();
