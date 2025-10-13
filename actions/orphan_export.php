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

// Récupérer les paramètres
$selected_ids = optional_param_array('ids', [], PARAM_INT);
$export_all = optional_param('all', 0, PARAM_INT);

// Déterminer les fichiers à exporter
if ($export_all || empty($selected_ids)) {
    // Exporter tous les fichiers orphelins
    $orphans = orphan_file_detector::get_orphan_files(true, 0); // 0 = pas de limite
} else {
    // Exporter uniquement les fichiers sélectionnés
    $all_orphans = orphan_file_detector::get_orphan_files(true, 0);
    $orphans = array_filter($all_orphans, function($orphan) use ($selected_ids) {
        return in_array($orphan->file->id, $selected_ids);
    });
}

if (empty($orphans)) {
    redirect(
        new moodle_url('/local/question_diagnostic/orphan_files.php'),
        'Aucun fichier orphelin à exporter',
        null,
        \core\output\notification::NOTIFY_WARNING
    );
}

// Générer le CSV
$csv_content = orphan_file_detector::export_to_csv($orphans);

// Définir le nom du fichier
$filename = 'orphan_files_' . date('Y-m-d_H-i-s') . '.csv';

// Envoyer les headers pour le téléchargement
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($csv_content));
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');

// Envoyer le contenu
echo $csv_content;

// Logger l'export
$log_message = sprintf(
    '[ORPHAN_FILE_EXPORT] User: %s (%d) | Files: %d | Time: %s',
    fullname($USER),
    $USER->id,
    count($orphans),
    date('Y-m-d H:i:s')
);
debugging($log_message, DEBUG_NORMAL);

exit;

