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

$type = optional_param('type', 'csv', PARAM_ALPHA);

if ($type === 'csv') {
    // Export des catégories
    $categories = category_manager::get_all_categories_with_stats();
    $csv = category_manager::export_to_csv($categories);
    
    // Envoyer le fichier CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="categories_questions_' . date('Y-m-d_H-i-s') . '.csv"');
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

