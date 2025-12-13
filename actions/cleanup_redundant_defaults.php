<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/../classes/category_manager.php');

use local_question_diagnostic\category_manager;

require_login();
require_sesskey();

if (!is_siteadmin()) {
    print_error('accessdenied', 'admin');
}

$action = required_param('action', PARAM_ALPHA);
$returnurl = new moodle_url('/local/question_diagnostic/questions_cleanup.php');

if ($action === 'cleanup_all') {
    // 1. Récupérer la liste des redondances
    $groups = category_manager::get_redundant_default_categories();
    
    $count = 0;
    $errors = [];
    
    // 2. Parcourir et supprimer
    foreach ($groups as $contextid => $group) {
        foreach ($group['delete'] as $cat_to_delete) {
            // Appel avec le flag bypass_default_protection = true
            // et bypass_info_protection = true (les doublons "Default for" vides peuvent avoir une description).
            $result = category_manager::delete_category($cat_to_delete->id, true, true);
            
            if ($result === true) {
                $count++;
            } else {
                $errors[] = "Catégorie {$cat_to_delete->id}: $result";
            }
        }
    }
    
    // 3. Feedback
    if ($count > 0) {
        \core\notification::success("✅ Nettoyage terminé : $count catégories redondantes supprimées.");
    }
    
    if (!empty($errors)) {
        \core\notification::error("⚠️ Certaines suppressions ont échoué :<br>" . implode('<br>', $errors));
    }
    
    if ($count == 0 && empty($errors)) {
        \core\notification::info("Aucune catégorie à nettoyer.");
    }
    
    redirect($returnurl);
}

redirect($returnurl);

