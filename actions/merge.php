<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/../classes/category_manager.php');
require_once(__DIR__ . '/../classes/question_analyzer.php');

use local_question_diagnostic\category_manager;
use local_question_diagnostic\question_analyzer;

require_login();
require_sesskey();

if (!is_siteadmin()) {
    print_error('accessdenied', 'admin');
}

$sourceid = required_param('source', PARAM_INT);
$destid = required_param('dest', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);
// 🆕 v1.9.44 : URL de retour hiérarchique
$returnurl = local_question_diagnostic_get_parent_url('actions/merge.php');

if ($confirm) {
    $result = category_manager::merge_categories($sourceid, $destid);
    
    if ($result === true) {
        // Purger tous les caches après modification
        question_analyzer::purge_all_caches();
        redirect($returnurl, '✅ Catégories fusionnées avec succès.', null, \core\output\notification::NOTIFY_SUCCESS);
    } else {
        redirect($returnurl, "⚠️ Erreur : $result", null, \core\output\notification::NOTIFY_ERROR);
    }
} else {
    // Demander confirmation
    global $DB;
    $source = $DB->get_record('question_categories', ['id' => $sourceid]);
    $dest = $DB->get_record('question_categories', ['id' => $destid]);
    
    if (!$source || !$dest) {
        redirect($returnurl, 'Catégories introuvables.', null, \core\output\notification::NOTIFY_ERROR);
    }
    
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url(new moodle_url('/local/question_diagnostic/actions/merge.php', [
        'source' => $sourceid,
        'dest' => $destid
    ]));
    $PAGE->set_title('Confirmation de fusion');
    
    echo $OUTPUT->header();
    echo local_question_diagnostic_render_version_badge();
    echo $OUTPUT->heading('🔀 Confirmation de fusion');
    
    echo html_writer::tag('p', "Vous êtes sur le point de fusionner :");
    echo html_writer::start_tag('ul');
    echo html_writer::tag('li', "<strong>Source :</strong> " . format_string($source->name) . " (ID: $sourceid)");
    echo html_writer::tag('li', "<strong>Destination :</strong> " . format_string($dest->name) . " (ID: $destid)");
    echo html_writer::end_tag('ul');
    
    $sourcestats = category_manager::get_category_stats($source);
    
    echo html_writer::tag('p', "Cette action va :");
    echo html_writer::start_tag('ol');
    echo html_writer::tag('li', "Déplacer <strong>{$sourcestats->total_questions} question(s)</strong> vers la destination");
    echo html_writer::tag('li', "Déplacer <strong>{$sourcestats->subcategories} sous-catégorie(s)</strong> vers la destination");
    echo html_writer::tag('li', "Supprimer la catégorie source");
    echo html_writer::end_tag('ol');
    
    echo html_writer::tag('p', '⚠️ <strong>Cette action est irréversible.</strong>', ['class' => 'alert alert-warning']);
    
    $confirmurl = new moodle_url('/local/question_diagnostic/actions/merge.php', [
        'source' => $sourceid,
        'dest' => $destid,
        'confirm' => 1,
        'sesskey' => sesskey()
    ]);
    
    echo html_writer::start_tag('div', ['style' => 'margin-top: 20px;']);
    echo html_writer::link($confirmurl, 'Oui, fusionner', ['class' => 'btn btn-primary']);
    echo ' ';
    echo html_writer::link($returnurl, 'Annuler', ['class' => 'btn btn-secondary']);
    echo html_writer::end_tag('div');
    
    echo $OUTPUT->footer();
}

