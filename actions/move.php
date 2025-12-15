<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/../classes/category_manager.php');

use local_question_diagnostic\category_manager;

require_login();
require_sesskey();

if (!is_siteadmin()) {
    print_error('accessdenied', 'admin');
}

$categoryid = required_param('id', PARAM_INT);
$newparentid = required_param('parent', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);
// 🆕 v1.9.44 : URL de retour hiérarchique
// 🆕 v1.11.36 : support returnurl (ex: retour vers categories_by_context.php).
$returnurlparam = optional_param('returnurl', '', PARAM_LOCALURL);
if (!empty($returnurlparam)) {
    $returnurl = new moodle_url($returnurlparam);
} else {
    $returnurl = local_question_diagnostic_get_parent_url('actions/move.php');
}

if ($confirm) {
    $result = category_manager::move_category($categoryid, $newparentid);
    
    if ($result === true) {
        redirect($returnurl, get_string('movesuccess', 'local_question_diagnostic'), null, \core\output\notification::NOTIFY_SUCCESS);
    } else {
        redirect($returnurl, get_string('moveerror', 'local_question_diagnostic') . ' ' . $result, null, \core\output\notification::NOTIFY_ERROR);
    }
} else {
    // Demander confirmation
    global $DB;
    $category = $DB->get_record('question_categories', ['id' => $categoryid]);
    $newparent = null;
    if ((int)$newparentid !== 0) {
        $newparent = $DB->get_record('question_categories', ['id' => $newparentid]);
    }
    
    if (!$category || ((int)$newparentid !== 0 && !$newparent)) {
        redirect($returnurl, 'Catégories introuvables.', null, \core\output\notification::NOTIFY_ERROR);
    }
    
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url(new moodle_url('/local/question_diagnostic/actions/move.php', [
        'id' => $categoryid,
        'parent' => $newparentid,
        'returnurl' => !empty($returnurlparam) ? $returnurlparam : null,
    ]));
    $PAGE->set_title(get_string('move', 'local_question_diagnostic'));
    
    echo $OUTPUT->header();
    echo local_question_diagnostic_render_version_badge();
    echo $OUTPUT->heading('📦 ' . get_string('move', 'local_question_diagnostic'));
    
    echo html_writer::tag('p', "Vous êtes sur le point de déplacer :");
    echo html_writer::start_tag('ul');
    echo html_writer::tag('li', "<strong>Catégorie :</strong> " . format_string($category->name) . " (ID: $categoryid)");
    if ((int)$newparentid === 0) {
        echo html_writer::tag('li', "<strong>Vers le parent :</strong> " . get_string('move_root_parent', 'local_question_diagnostic') . " (ID: 0)");
    } else {
        echo html_writer::tag('li', "<strong>Vers le parent :</strong> " . format_string($newparent->name) . " (ID: $newparentid)");
    }
    echo html_writer::end_tag('ul');
    
    $categorystats = category_manager::get_category_stats($category);
    
    echo html_writer::tag('p', "Cette catégorie contient :");
    echo html_writer::start_tag('ul');
    echo html_writer::tag('li', "<strong>{$categorystats->total_questions} question(s)</strong>");
    echo html_writer::tag('li', "<strong>{$categorystats->subcategories} sous-catégorie(s)</strong>");
    echo html_writer::end_tag('ul');
    
    echo html_writer::tag('p', '⚠️ <strong>Cette action modifiera la hiérarchie des catégories.</strong>', ['class' => 'alert alert-warning']);
    
    $confirmurl = new moodle_url('/local/question_diagnostic/actions/move.php', [
        'id' => $categoryid,
        'parent' => $newparentid,
        'confirm' => 1,
        'sesskey' => sesskey(),
        'returnurl' => !empty($returnurlparam) ? $returnurlparam : null,
    ]);
    
    echo html_writer::start_tag('div', ['style' => 'margin-top: 20px;']);
    echo html_writer::link($confirmurl, get_string('move', 'local_question_diagnostic'), ['class' => 'btn btn-primary']);
    echo ' ';
    echo html_writer::link($returnurl, get_string('cancel', 'core'), ['class' => 'btn btn-secondary']);
    echo html_writer::end_tag('div');
    
    echo $OUTPUT->footer();
}
