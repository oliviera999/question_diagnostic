<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../classes/category_manager.php');
require_once(__DIR__ . '/../classes/question_analyzer.php');

use local_question_diagnostic\category_manager;
use local_question_diagnostic\question_analyzer;

require_login();
require_sesskey();

if (!is_siteadmin()) {
    print_error('accessdenied', 'admin');
}

// ⚠️ FIX: Accepter les paramètres POST et GET (POST pour éviter Request-URI Too Long)
$type = optional_param('type', 'csv', PARAM_ALPHA);
$ids = optional_param('ids', '', PARAM_TEXT);

if ($type === 'csv') {
    // Export des catégories
    $categories = category_manager::get_all_categories_with_stats();
    
    // Si des IDs spécifiques sont fournis, filtrer les catégories
    if ($ids) {
        $selectedIds = array_filter(array_map('intval', explode(',', $ids)));
        $categories = array_filter($categories, function($item) use ($selectedIds) {
            return in_array($item->category->id, $selectedIds);
        });
    }
    
    $csv = category_manager::export_to_csv($categories);
    
    // Nom du fichier selon le contexte
    $filename = 'categories_questions';
    if ($ids) {
        $filename .= '_selection';
    }
    $filename .= '_' . date('Y-m-d_H-i-s') . '.csv';
    
    // Envoyer le fichier CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo "\xEF\xBB\xBF"; // UTF-8 BOM pour Excel
    echo $csv;
    exit;
} else if ($type === 'questions_csv') {
    // Export des questions
    $questions = question_analyzer::get_all_questions_with_stats();
    $csv = question_analyzer::export_to_csv($questions);
    
    // Envoyer le fichier CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="questions_statistics_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo "\xEF\xBB\xBF"; // UTF-8 BOM pour Excel
    echo $csv;
    exit;
}

redirect(new moodle_url('/local/question_diagnostic/index.php'));

