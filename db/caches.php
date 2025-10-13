<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Cache definitions for Question Diagnostic Tool
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [
    // Cache pour la map des questions en doublon
    'duplicates' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => false,
        'ttl' => 3600, // 1 heure
        'staticacceleration' => true,
        'staticaccelerationsize' => 10,
    ],
    
    // Cache pour les statistiques globales
    'globalstats' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => false,
        'ttl' => 1800, // 30 minutes
        'staticacceleration' => true,
        'staticaccelerationsize' => 5,
    ],
    
    // Cache pour les statistiques d'usage des questions
    'questionusage' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => false,
        'ttl' => 1800, // 30 minutes
        'staticacceleration' => true,
        'staticaccelerationsize' => 100,
    ],
    
    // Cache pour les liens cassés
    'brokenlinks' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => false,
        'ttl' => 3600, // 1 heure
        'staticacceleration' => true,
        'staticaccelerationsize' => 10,
    ],
    
    // Cache pour les fichiers orphelins
    'orphanfiles' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => false,
        'ttl' => 3600, // 1 heure
        'staticacceleration' => true,
        'staticaccelerationsize' => 10,
    ],
];


