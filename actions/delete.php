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

// 🔧 SÉCURITÉ v1.9.27 : Limite stricte sur les opérations en masse
define('MAX_BULK_DELETE_CATEGORIES', 100);

// ⚠️ FIX: Accepter les paramètres POST et GET (POST pour éviter Request-URI Too Long)
$categoryid = optional_param('id', 0, PARAM_INT);
$categoryids = optional_param('ids', '', PARAM_TEXT);
$confirm = optional_param('confirm', 0, PARAM_INT);
// 🆕 v1.9.44 : URL de retour hiérarchique
$returnurl = local_question_diagnostic_get_parent_url('actions/delete.php');

// Suppression multiple
if ($categoryids) {
    $ids = array_filter(array_map('intval', explode(',', $categoryids)));
    
    // 🔧 SÉCURITÉ v1.9.27 : Vérifier la limite
    if (count($ids) > MAX_BULK_DELETE_CATEGORIES) {
        print_error('error', 'local_question_diagnostic', $returnurl, 
            'Trop de catégories sélectionnées. Maximum autorisé : ' . MAX_BULK_DELETE_CATEGORIES);
    }
    
    if ($confirm) {
        $result = category_manager::delete_categories_bulk($ids);
        
        // Purger tous les caches après modification
        if ($result['success'] > 0) {
            question_analyzer::purge_all_caches();
        }
        
        if ($result['success'] > 0) {
            redirect($returnurl, "✅ {$result['success']} catégorie(s) supprimée(s) avec succès.", null, \core\output\notification::NOTIFY_SUCCESS);
        }
        
        if (!empty($result['errors'])) {
            $errors = implode('<br>', $result['errors']);
            redirect($returnurl, "⚠️ Erreurs : <br>$errors", null, \core\output\notification::NOTIFY_ERROR);
        }
    } else {
        // Demander confirmation - Utiliser POST pour éviter Request-URI Too Long
        $PAGE->set_context(context_system::instance());
        $PAGE->set_url(new moodle_url('/local/question_diagnostic/actions/delete.php'));
        $PAGE->set_title('Confirmation de suppression');
        
        echo $OUTPUT->header();
        echo local_question_diagnostic_render_version_badge();
        echo $OUTPUT->heading('⚠️ Confirmation de suppression');
        echo html_writer::tag('p', "Vous êtes sur le point de supprimer <strong>" . count($ids) . " catégorie(s)</strong>.");
        echo html_writer::tag('p', "Cette action est irréversible. Êtes-vous sûr ?");
        
        echo html_writer::start_tag('div', ['style' => 'margin-top: 20px;']);
        
        // ⚠️ FIX v1.5.5 : Utiliser un formulaire POST au lieu d'un lien GET
        echo html_writer::start_tag('form', [
            'method' => 'post',
            'action' => new moodle_url('/local/question_diagnostic/actions/delete.php'),
            'style' => 'display: inline;'
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'ids', 'value' => $categoryids]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => '1']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => 'Oui, supprimer', 'class' => 'btn btn-danger']);
        echo html_writer::end_tag('form');
        
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
            // Purger tous les caches après modification
            question_analyzer::purge_all_caches();
            redirect($returnurl, '✅ Catégorie supprimée avec succès.', null, \core\output\notification::NOTIFY_SUCCESS);
        } else {
            redirect($returnurl, "⚠️ Erreur : $result", null, \core\output\notification::NOTIFY_ERROR);
        }
    } else {
        // Demander confirmation - Utiliser POST pour cohérence
        global $DB;
        $category = $DB->get_record('question_categories', ['id' => $categoryid]);
        
        $PAGE->set_context(context_system::instance());
        $PAGE->set_url(new moodle_url('/local/question_diagnostic/actions/delete.php'));
        $PAGE->set_title('Confirmation de suppression');
        
        echo $OUTPUT->header();
        echo local_question_diagnostic_render_version_badge();
        echo $OUTPUT->heading('⚠️ Confirmation de suppression');
        echo html_writer::tag('p', "Vous êtes sur le point de supprimer la catégorie : <strong>" . format_string($category->name) . "</strong> (ID: $categoryid)");
        echo html_writer::tag('p', "Cette action est irréversible. Êtes-vous sûr ?");
        
        echo html_writer::start_tag('div', ['style' => 'margin-top: 20px;']);
        
        // ⚠️ FIX v1.5.5 : Utiliser un formulaire POST pour cohérence
        echo html_writer::start_tag('form', [
            'method' => 'post',
            'action' => new moodle_url('/local/question_diagnostic/actions/delete.php'),
            'style' => 'display: inline;'
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $categoryid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => '1']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => 'Oui, supprimer', 'class' => 'btn btn-danger']);
        echo html_writer::end_tag('form');
        
        echo ' ';
        echo html_writer::link($returnurl, 'Annuler', ['class' => 'btn btn-secondary']);
        echo html_writer::end_tag('div');
        
        echo $OUTPUT->footer();
        exit;
    }
}

redirect($returnurl);

