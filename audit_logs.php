<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Page de consultation des logs d'audit
 * 
 * 🆕 v1.9.39 : TODO BASSE #3 - Logs d'audit
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/audit_logger.php');

use local_question_diagnostic\audit_logger;

require_login();

if (!is_siteadmin()) {
    print_error('accessdenied', 'admin');
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/question_diagnostic/audit_logs.php'));
$PAGE->set_title('Logs d\'Audit');
$PAGE->set_heading('📋 Logs d\'Audit - Traçabilité des Modifications');

echo $OUTPUT->header();

// 🆕 v1.9.44 : Lien retour hiérarchique
echo html_writer::start_tag('div', ['style' => 'margin-bottom: 20px;']);
echo local_question_diagnostic_render_back_link('audit_logs.php');
echo html_writer::end_tag('div');

// Introduction
echo html_writer::tag('h2', '📊 Logs d\'Audit du Plugin');

echo html_writer::start_div('alert alert-info', ['style' => 'margin: 20px 0;']);
echo html_writer::tag('p', '<strong>🛡️ Traçabilité :</strong> Cette page affiche toutes les modifications effectuées par le plugin sur la base de données.');
echo html_writer::tag('p', 'Les logs sont conservés pendant <strong>90 jours</strong> puis automatiquement supprimés.');
echo html_writer::end_div();

// Récupérer les logs récents
$logs = audit_logger::get_recent_logs(100, 30);

if (empty($logs)) {
    echo html_writer::start_div('alert alert-warning', ['style' => 'margin: 20px 0;']);
    echo '📭 Aucun log d\'audit disponible. Les modifications futures seront enregistrées ici.';
    echo html_writer::end_div();
} else {
    echo html_writer::tag('p', '<strong>' . count($logs) . ' log(s)</strong> trouvé(s) dans les 30 derniers jours.');
    
    // Tableau des logs
    echo html_writer::start_tag('table', ['class' => 'table table-striped', 'style' => 'margin: 20px 0;']);
    
    // En-tête
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'Date/Heure');
    echo html_writer::tag('th', 'Utilisateur');
    echo html_writer::tag('th', 'Action');
    echo html_writer::tag('th', 'Détails');
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    
    // Corps
    echo html_writer::start_tag('tbody');
    
    foreach ($logs as $log) {
        echo html_writer::start_tag('tr');
        
        // Date
        echo html_writer::start_tag('td');
        echo html_writer::tag('small', $log->date, ['style' => 'white-space: nowrap;']);
        echo html_writer::end_tag('td');
        
        // Utilisateur
        echo html_writer::start_tag('td');
        try {
            $user = \core_user::get_user($log->userid);
            echo html_writer::tag('span', fullname($user));
        } catch (\Exception $e) {
            echo 'User ' . $log->userid;
        }
        echo html_writer::end_tag('td');
        
        // Action
        echo html_writer::start_tag('td');
        $action_icon = '';
        switch ($log->action) {
            case audit_logger::EVENT_CATEGORY_DELETED:
                $action_icon = '🗑️';
                $action_text = 'Suppression catégorie';
                break;
            case audit_logger::EVENT_CATEGORIES_MERGED:
                $action_icon = '🔀';
                $action_text = 'Fusion catégories';
                break;
            case audit_logger::EVENT_CATEGORY_MOVED:
                $action_icon = '📦';
                $action_text = 'Déplacement catégorie';
                break;
            case audit_logger::EVENT_QUESTION_DELETED:
                $action_icon = '❌';
                $action_text = 'Suppression question';
                break;
            case audit_logger::EVENT_DATA_EXPORTED:
                $action_icon = '📥';
                $action_text = 'Export données';
                break;
            case audit_logger::EVENT_CACHE_PURGED:
                $action_icon = '🔄';
                $action_text = 'Purge cache';
                break;
            default:
                $action_icon = '📝';
                $action_text = $log->action;
        }
        echo $action_icon . ' ' . $action_text;
        echo html_writer::end_tag('td');
        
        // Détails
        echo html_writer::start_tag('td');
        if (is_array($log->details)) {
            echo html_writer::start_tag('small');
            foreach ($log->details as $key => $value) {
                if (!is_array($value) && !is_object($value)) {
                    echo html_writer::tag('div', '<strong>' . $key . ':</strong> ' . $value);
                }
            }
            echo html_writer::end_tag('small');
        }
        echo html_writer::end_tag('td');
        
        echo html_writer::end_tag('tr');
    }
    
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
}

// Lien vers la documentation
echo html_writer::start_div('', ['style' => 'margin: 30px 0; text-align: center;']);
echo html_writer::tag('p', '📖 Les logs sont stockés dans : <code>moodledata/local_question_diagnostic/audit_log_YYYY-MM.txt</code>');
echo html_writer::link(
    new moodle_url('/local/question_diagnostic/help.php'),
    '📚 Consulter l\'Aide',
    ['class' => 'btn btn-info']
);
echo html_writer::end_div();

echo $OUTPUT->footer();

