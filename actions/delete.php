<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../classes/category_manager.php');

use local_question_diagnostic\category_manager;

require_login();
require_sesskey();

if (!is_siteadmin()) {
    print_error('accessdenied', 'admin');
}

$categoryid = optional_param('id', 0, PARAM_INT);
$categoryids = optional_param('ids', '', PARAM_TEXT);
$confirm = optional_param('confirm', 0, PARAM_INT);
$return = optional_param('return', 'categories', PARAM_ALPHA);
$returnurl = new moodle_url('/local/question_diagnostic/' . ($return === 'index' ? 'index.php' : 'categories.php'));

// Suppression multiple
if ($categoryids) {
    $ids = array_filter(array_map('intval', explode(',', $categoryids)));
    
    if ($confirm) {
        $result = category_manager::delete_categories_bulk($ids);
        
        if ($result['success'] > 0) {
            redirect($returnurl, "✅ {$result['success']} catégorie(s) supprimée(s) avec succès.", null, \core\output\notification::NOTIFY_SUCCESS);
        }
        
        if (!empty($result['errors'])) {
            $errors = implode('<br>', $result['errors']);
            redirect($returnurl, "⚠️ Erreurs : <br>$errors", null, \core\output\notification::NOTIFY_ERROR);
        }
    } else {
        // Demander confirmation
        $PAGE->set_context(context_system::instance());
        $PAGE->set_url(new moodle_url('/local/question_diagnostic/actions/delete.php', ['ids' => $categoryids]));
        $PAGE->set_title('Confirmation de suppression');
        
        echo $OUTPUT->header();
        echo $OUTPUT->heading('⚠️ Confirmation de suppression');
        echo html_writer::tag('p', "Vous êtes sur le point de supprimer <strong>" . count($ids) . " catégorie(s)</strong>.");
        echo html_writer::tag('p', "Cette action est irréversible. Êtes-vous sûr ?");
        
        $confirmurl = new moodle_url('/local/question_diagnostic/actions/delete.php', [
            'ids' => $categoryids,
            'confirm' => 1,
            'sesskey' => sesskey()
        ]);
        
        echo html_writer::start_tag('div', ['style' => 'margin-top: 20px;']);
        echo html_writer::link($confirmurl, 'Oui, supprimer', ['class' => 'btn btn-danger']);
        echo ' ';
        echo html_writer::link($returnurl, 'Annuler', ['class' => 'btn btn-secondary']);
        echo html_writer::end_tag('div');
        
        echo $OUTPUT->footer();
        exit;
    }
}

// Suppression simple
if ($categoryid) {
    if ($confirm) {
        $result = category_manager::delete_category($categoryid);
        
        if ($result === true) {
            redirect($returnurl, '✅ Catégorie supprimée avec succès.', null, \core\output\notification::NOTIFY_SUCCESS);
        } else {
            redirect($returnurl, "⚠️ Erreur : $result", null, \core\output\notification::NOTIFY_ERROR);
        }
    } else {
        // Demander confirmation
        global $DB;
        $category = $DB->get_record('question_categories', ['id' => $categoryid]);
        
        $PAGE->set_context(context_system::instance());
        $PAGE->set_url(new moodle_url('/local/question_diagnostic/actions/delete.php', ['id' => $categoryid]));
        $PAGE->set_title('Confirmation de suppression');
        
        echo $OUTPUT->header();
        echo $OUTPUT->heading('⚠️ Confirmation de suppression');
        echo html_writer::tag('p', "Vous êtes sur le point de supprimer la catégorie : <strong>" . format_string($category->name) . "</strong> (ID: $categoryid)");
        echo html_writer::tag('p', "Cette action est irréversible. Êtes-vous sûr ?");
        
        $confirmurl = new moodle_url('/local/question_diagnostic/actions/delete.php', [
            'id' => $categoryid,
            'confirm' => 1,
            'sesskey' => sesskey()
        ]);
        
        echo html_writer::start_tag('div', ['style' => 'margin-top: 20px;']);
        echo html_writer::link($confirmurl, 'Oui, supprimer', ['class' => 'btn btn-danger']);
        echo ' ';
        echo html_writer::link($returnurl, 'Annuler', ['class' => 'btn btn-secondary']);
        echo html_writer::end_tag('div');
        
        echo $OUTPUT->footer();
        exit;
    }
}

redirect($returnurl);

