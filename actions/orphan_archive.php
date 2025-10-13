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
$PAGE->set_url(new moodle_url('/local/question_diagnostic/actions/orphan_archive.php'));
$PAGE->set_title(get_string('archive_orphan', 'local_question_diagnostic'));
$PAGE->set_pagelayout('admin');

// Récupérer les paramètres
$single_id = optional_param('id', 0, PARAM_INT);
$multiple_ids = optional_param_array('ids', [], PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);

// Déterminer les IDs à traiter
$ids_to_archive = [];
if ($single_id > 0) {
    $ids_to_archive = [$single_id];
} else if (!empty($multiple_ids)) {
    $ids_to_archive = $multiple_ids;
}

if (empty($ids_to_archive)) {
    redirect(
        new moodle_url('/local/question_diagnostic/orphan_files.php'),
        get_string('archive_error', 'local_question_diagnostic'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// Limiter à 100 fichiers pour sécurité
if (count($ids_to_archive) > 100) {
    $ids_to_archive = array_slice($ids_to_archive, 0, 100);
}

// Récupérer les détails des fichiers
$files_details = [];
$total_size = 0;
foreach ($ids_to_archive as $id) {
    $file_record = $DB->get_record('files', ['id' => $id]);
    if ($file_record) {
        $files_details[] = $file_record;
        $total_size += $file_record->filesize;
    }
}

if (empty($files_details)) {
    redirect(
        new moodle_url('/local/question_diagnostic/orphan_files.php'),
        get_string('archive_error', 'local_question_diagnostic') . ' - Aucun fichier trouvé',
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// Formater la taille totale
function format_filesize_local($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } else if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } else if ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

$total_size_formatted = format_filesize_local($total_size);

// ======================================================================
// PAGE DE CONFIRMATION
// ======================================================================

if (!$confirm) {
    echo $OUTPUT->header();
    
    // Titre
    echo html_writer::tag('h2', get_string('archive_orphans', 'local_question_diagnostic'));
    
    // Message de confirmation
    echo html_writer::tag('p', 'Vous êtes sur le point d\'archiver ' . count($files_details) . ' fichier(s) orphelin(s).', 
                          ['style' => 'font-size: 16px;']);
    
    // Informations sur l'archivage
    echo html_writer::start_div('alert alert-info', ['style' => 'margin: 20px 0;']);
    echo '<strong>📚 À propos de l\'archivage :</strong><br>';
    echo '• Les fichiers seront copiés dans un dossier temporaire<br>';
    echo '• Durée de rétention : ' . get_string('archive_retention_days', 'local_question_diagnostic', 30) . '<br>';
    echo '• Vous pourrez les restaurer si nécessaire<br>';
    echo '• Les fichiers ne seront PAS supprimés de la base de données';
    echo html_writer::end_div();
    
    // Tableau récapitulatif (max 20 fichiers affichés)
    echo html_writer::tag('h4', 'Fichiers qui seront archivés :');
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
        echo html_writer::tag('td', format_filesize_local($file->filesize));
        echo html_writer::end_tag('tr');
        
        $displayed++;
    }
    
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_tag('div');
    
    // Statistiques
    echo html_writer::start_div('alert alert-success', ['style' => 'margin: 20px 0;']);
    echo '<strong>Récapitulatif :</strong><br>';
    echo '• Nombre de fichiers : ' . count($files_details) . '<br>';
    echo '• Taille totale : ' . $total_size_formatted . '<br>';
    echo html_writer::end_div();
    
    // Boutons d'action
    echo html_writer::start_div('', ['style' => 'margin: 30px 0; display: flex; gap: 10px;']);
    
    // Bouton Confirmer
    $confirm_params = ['confirm' => 1, 'sesskey' => sesskey()];
    if ($single_id > 0) {
        $confirm_params['id'] = $single_id;
    } else {
        foreach ($ids_to_archive as $id) {
            $confirm_params['ids[]'] = $id;
        }
    }
    
    echo html_writer::link(
        new moodle_url('/local/question_diagnostic/actions/orphan_archive.php', $confirm_params),
        '🗄️ ' . get_string('confirm', 'core') . ' et archiver',
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
// EXÉCUTION DE L'ARCHIVAGE (après confirmation)
// ======================================================================

$success_count = 0;
$error_count = 0;
$archive_path = '';

foreach ($ids_to_archive as $id) {
    $result = orphan_file_detector::archive_orphan_file($id);
    
    if ($result['success']) {
        $success_count++;
        if (empty($archive_path)) {
            $archive_path = dirname($result['archive_path']);
        }
    } else {
        $error_count++;
    }
}

// Message de résultat
if ($success_count > 0) {
    $message = get_string('archive_success', 'local_question_diagnostic', $archive_path) . 
               ' (' . $success_count . ' fichier(s))';
    if ($error_count > 0) {
        $message .= ' - ' . $error_count . ' erreur(s)';
    }
    $notification_type = \core\output\notification::NOTIFY_SUCCESS;
} else {
    $message = get_string('archive_error', 'local_question_diagnostic') . ' - ' . $error_count . ' échec(s)';
    $notification_type = \core\output\notification::NOTIFY_ERROR;
}

// Redirection avec message
redirect(
    new moodle_url('/local/question_diagnostic/orphan_files.php'),
    $message,
    null,
    $notification_type
);

