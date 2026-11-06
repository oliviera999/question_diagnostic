<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Définition des capabilities (permissions) du plugin
 * 
 * 🆕 v1.9.41 : TODO BASSE #4 - Permissions granulaires
 * 
 * Permet de définir des rôles avec des permissions spécifiques :
 * - Manager : Toutes les permissions
 * - Question Manager : Peut gérer catégories et questions
 * - Auditor : Lecture seule (monitoring, logs)
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    
    // =========================================================================
    // LECTURE : Voir et consulter (Auditor, Manager, Admin)
    // =========================================================================
    
    'local/question_diagnostic:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW
        ]
    ],
    
    'local/question_diagnostic:viewcategories' => [
        'riskbitmask' => RISK_CONFIG,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW
        ]
    ],
    
    'local/question_diagnostic:viewquestions' => [
        'riskbitmask' => RISK_CONFIG,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW
        ]
    ],
    
    'local/question_diagnostic:viewbrokenlinks' => [
        'riskbitmask' => RISK_CONFIG,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW
        ]
    ],
    
    'local/question_diagnostic:viewauditlogs' => [
        'riskbitmask' => RISK_CONFIG,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW
        ]
    ],
    
    'local/question_diagnostic:viewmonitoring' => [
        'riskbitmask' => RISK_CONFIG,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW
        ]
    ],
    
    // =========================================================================
    // GESTION CATÉGORIES : Supprimer, fusionner, déplacer (Question Manager, Admin)
    // =========================================================================
    
    'local/question_diagnostic:managecategories' => [
        'riskbitmask' => RISK_CONFIG | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => []  // v1.15.0 : écritures réservées aux administrateurs du site
    ],
    
    'local/question_diagnostic:deletecategories' => [
        'riskbitmask' => RISK_CONFIG | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => []  // Managers seulement par défaut (via is_siteadmin())
    ],
    
    'local/question_diagnostic:mergecategories' => [
        'riskbitmask' => RISK_CONFIG | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => []  // Managers seulement
    ],
    
    'local/question_diagnostic:movecategories' => [
        'riskbitmask' => RISK_CONFIG | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => []  // v1.15.0 : écritures réservées aux administrateurs du site
    ],
    
    // =========================================================================
    // GESTION QUESTIONS : Supprimer (Admin only)
    // =========================================================================
    
    'local/question_diagnostic:deletequestions' => [
        'riskbitmask' => RISK_CONFIG | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => []  // Admin seulement
    ],
    
    // =========================================================================
    // EXPORT : Exporter données CSV (Question Manager, Admin)
    // =========================================================================
    
    'local/question_diagnostic:export' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW
        ]
    ],
    
    // =========================================================================
    // ADMIN : Configuration plugin (Admin only)
    // =========================================================================
    
    'local/question_diagnostic:configureplugin' => [
        'riskbitmask' => RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => []  // Admin seulement
    ],
];

