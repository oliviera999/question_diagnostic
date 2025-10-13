<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../classes/orphan_file_detector.php');

use local_question_diagnostic\orphan_file_detector;

require_login();

// Vérification stricte : seuls les administrateurs du site peuvent accéder à cette page.
if (!is_siteadmin()) {
    print_error('accessdenied', 'admin');
    exit;
}

require_sesskey();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/question_diagnostic/actions/orphan_delete.php'));
$PAGE->set_title(get_string('delete', 'local_question_diagnostic'));
$PAGE->set_pagelayout('admin');

// Récupérer les paramètres
$single_id = optional_param('id', 0, PARAM_INT);
$multiple_ids = optional_param_array('ids', [], PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);
$dry_run = optional_param('dryrun', 0, PARAM_INT);

// Déterminer les IDs à traiter
$ids_to_delete = [];
if ($single_id > 0) {
    $ids_to_delete = [$single_id];
} else if (!empty($multiple_ids)) {
    $ids_to_delete = $multiple_ids;
}

if (empty($ids_to_delete)) {
    redirect(
        new moodle_url('/local/question_diagnostic/orphan_files.php'),
        get_string('delete_orphan_error', 'local_question_diagnostic'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// Limiter à 100 fichiers pour sécurité
if (count($ids_to_delete) > 100) {
    $ids_to_delete = array_slice($ids_to_delete, 0, 100);
}

// Récupérer les détails des fichiers
$files_details = [];
$total_size = 0;
foreach ($ids_to_delete as $id) {
    $file_record = $DB->get_record('files', ['id' => $id]);
    if ($file_record && orphan_file_detector::is_safe_to_delete($file_record)) {
        $files_details[] = $file_record;
        $total_size += $file_record->filesize;
    }
}

if (empty($files_details)) {
    redirect(
        new moodle_url('/local/question_diagnostic/orphan_files.php'),
        get_string('delete_orphan_error', 'local_question_diagnostic') . ' - Aucun fichier sûr à supprimer',
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// Formater la taille totale
$total_size_formatted = orphan_file_detector::format_filesize($total_size);

// ======================================================================
// PAGE DE CONFIRMATION
// ======================================================================

if (!$confirm) {
    echo $OUTPUT->header();
    
    // Titre
    echo html_writer::tag('h2', get_string('confirm_delete_orphans', 'local_question_diagnostic'));
    
    // Message de confirmation
    $message = get_string('confirm_delete_orphans_message', 'local_question_diagnostic', count($files_details));
    echo html_writer::tag('p', $message, ['style' => 'font-size: 16px;']);
    
    // Avertissement sur l'irréversibilité
    echo html_writer::start_div('alert alert-danger', ['style' => 'margin: 20px 0;']);
    echo get_string('delete_orphans_warning', 'local_question_diagnostic', $total_size_formatted);
    echo html_writer::end_div();
    
    // Tableau récapitulatif (max 20 fichiers affichés)
    echo html_writer::tag('h4', 'Fichiers qui seront supprimés :');
    echo html_writer::start_tag('div', ['class' => 'qd-table-wrapper', 'style' => 'max-height: 400px; overflow-y: auto;']);
    echo html_writer::start_tag('table', ['class' => 'table table-sm']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'ID');
    echo html_writer::tag('th', 'Nom du fichier');
    echo html_writer::tag('th', 'Composant');
    echo html_writer::tag('th', 'Taille');
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');
    
    $displayed = 0;
    foreach ($files_details as $file) {
        if ($displayed >= 20) {
            echo html_writer::start_tag('tr');
            echo html_writer::tag('td', '...', ['colspan' => 4, 'style' => 'text-align: center; font-style: italic;']);
            echo html_writer::end_tag('tr');
            echo html_writer::start_tag('tr');
            echo html_writer::tag('td', 'Et ' . (count($files_details) - 20) . ' autres fichiers', ['colspan' => 4, 'style' => 'text-align: center; font-weight: bold;']);
            echo html_writer::end_tag('tr');
            break;
        }
        
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', $file->id);
        echo html_writer::tag('td', htmlspecialchars($file->filename));
        echo html_writer::tag('td', htmlspecialchars($file->component));
        echo html_writer::tag('td', orphan_file_detector::format_filesize($file->filesize));
        echo html_writer::end_tag('tr');
        
        $displayed++;
    }
    
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_tag('div');
    
    // Statistiques
    echo html_writer::start_div('alert alert-info', ['style' => 'margin: 20px 0;']);
    echo '<strong>Récapitulatif :</strong><br>';
    echo '• Nombre de fichiers : ' . count($files_details) . '<br>';
    echo '• Espace à libérer : ' . $total_size_formatted . '<br>';
    echo html_writer::end_div();
    
    // Boutons d'action
    echo html_writer::start_div('', ['style' => 'margin: 30px 0; display: flex; gap: 10px;']);
    
    // Bouton Confirmer
    $confirm_params = ['confirm' => 1, 'sesskey' => sesskey()];
    if ($single_id > 0) {
        $confirm_params['id'] = $single_id;
    } else {
        foreach ($ids_to_delete as $id) {
            $confirm_params['ids[]'] = $id;
        }
    }
    
    echo html_writer::link(
        new moodle_url('/local/question_diagnostic/actions/orphan_delete.php', $confirm_params),
        '🗑️ ' . get_string('confirm', 'core') . ' et supprimer',
        ['class' => 'btn btn-danger btn-lg']
    );
    
    // Bouton Mode Dry-Run (simulation)
    $dryrun_params = $confirm_params;
    $dryrun_params['dryrun'] = 1;
    echo html_writer::link(
        new moodle_url('/local/question_diagnostic/actions/orphan_delete.php', $dryrun_params),
        '🧪 ' . get_string('dry_run_mode', 'local_question_diagnostic'),
        ['class' => 'btn btn-warning btn-lg']
    );
    
    // Bouton Annuler
    echo html_writer::link(
        new moodle_url('/local/question_diagnostic/orphan_files.php'),
        get_string('cancel', 'core'),
        ['class' => 'btn btn-secondary btn-lg']
    );
    
    echo html_writer::end_div();
    
    echo $OUTPUT->footer();
    exit;
}

// ======================================================================
// EXÉCUTION DE LA SUPPRESSION (après confirmation)
// ======================================================================

$results = orphan_file_detector::delete_multiple_orphans($ids_to_delete, $dry_run);

// Purger le cache après suppression
orphan_file_detector::purge_orphan_cache();

// Message de résultat
if ($dry_run) {
    $message = '[DRY-RUN] ' . $results['success'] . ' fichier(s) SERAIENT supprimés. ' . 
               $results['failed'] . ' échec(s).';
    $notification_type = \core\output\notification::NOTIFY_INFO;
} else {
    $message = $results['success'] . ' fichier(s) supprimé(s) avec succès. ' . 
               $results['failed'] . ' échec(s).';
    $notification_type = $results['success'] > 0 ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_ERROR;
}

// Redirection avec message
redirect(
    new moodle_url('/local/question_diagnostic/orphan_files.php'),
    $message,
    null,
    $notification_type
);

