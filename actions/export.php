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

// 🔧 SÉCURITÉ v1.9.28 : Limites strictes sur export CSV
define('MAX_EXPORT_CATEGORIES', 5000);
define('MAX_EXPORT_QUESTIONS', 5000);

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
    
    // 🔧 SÉCURITÉ v1.9.28 : Vérifier la limite
    if (count($categories) > MAX_EXPORT_CATEGORIES) {
        // 🆕 v1.9.44 : URL de retour hiérarchique
        $returnurl = local_question_diagnostic_get_parent_url('actions/export.php');
        print_error('error', 'local_question_diagnostic', $returnurl,
            'Trop de catégories à exporter. Maximum autorisé : ' . MAX_EXPORT_CATEGORIES . '. Trouvé : ' . count($categories) . '. Utilisez les filtres pour réduire la sélection.');
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
    // 🔧 SÉCURITÉ v1.9.28 : Limiter le nombre de questions exportées
    $questions = question_analyzer::get_all_questions_with_stats(false, MAX_EXPORT_QUESTIONS);
    
    // Vérifier la limite
    if (count($questions) >= MAX_EXPORT_QUESTIONS) {
        $returnurl = new moodle_url('/local/question_diagnostic/questions_cleanup.php');
        print_error('error', 'local_question_diagnostic', $returnurl,
            'Trop de questions à exporter. Maximum autorisé : ' . MAX_EXPORT_QUESTIONS . '. Utilisez les filtres ou la pagination pour réduire la sélection.');
    }
    
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

