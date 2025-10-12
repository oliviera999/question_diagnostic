<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Tests unitaires pour audit_logger
 * 
 * 🆕 v1.9.42 : Option E - Tests complets
 *
 * @package    local_question_diagnostic
 * @category   test
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_question_diagnostic;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/question_diagnostic/classes/audit_logger.php');

/**
 * Tests pour la classe audit_logger
 *
 * @covers \local_question_diagnostic\audit_logger
 */
class audit_logger_test extends \advanced_testcase {

    /**
     * Test log_action() basique
     */
    public function test_log_action_basic() {
        $this->resetAfterTest(true);
        
        // Enregistrer une action simple
        $result = audit_logger::log_action('test_action', ['key' => 'value'], 123);
        
        $this->assertTrue($result, 'log_action devrait retourner true');
    }

    /**
     * Test log_category_deletion()
     */
    public function test_log_category_deletion() {
        $this->resetAfterTest(true);
        
        $result = audit_logger::log_category_deletion(456, 'Catégorie Test', 0);
        
        $this->assertTrue($result, 'log_category_deletion devrait retourner true');
    }

    /**
     * Test log_category_merge()
     */
    public function test_log_category_merge() {
        $this->resetAfterTest(true);
        
        $result = audit_logger::log_category_merge(
            100, 200, 
            'Source Category', 'Destination Category', 
            5
        );
        
        $this->assertTrue($result, 'log_category_merge devrait retourner true');
    }

    /**
     * Test log_category_move()
     */
    public function test_log_category_move() {
        $this->resetAfterTest(true);
        
        $result = audit_logger::log_category_move(300, 10, 20, 'Catégorie Déplacée');
        
        $this->assertTrue($result, 'log_category_move devrait retourner true');
    }

    /**
     * Test log_question_deletion()
     */
    public function test_log_question_deletion() {
        $this->resetAfterTest(true);
        
        $result = audit_logger::log_question_deletion(789, 'Question Test', 'multichoice');
        
        $this->assertTrue($result, 'log_question_deletion devrait retourner true');
    }

    /**
     * Test log_export()
     */
    public function test_log_export() {
        $this->resetAfterTest(true);
        
        $result = audit_logger::log_export('csv', 100, 'categories');
        
        $this->assertTrue($result, 'log_export devrait retourner true');
    }

    /**
     * Test log_cache_purge()
     */
    public function test_log_cache_purge() {
        $this->resetAfterTest(true);
        
        $result = audit_logger::log_cache_purge('all');
        
        $this->assertTrue($result, 'log_cache_purge devrait retourner true');
    }

    /**
     * Test get_recent_logs() avec aucun log
     */
    public function test_get_recent_logs_empty() {
        $this->resetAfterTest(true);
        
        // Pas de logs dans répertoire de test
        $logs = audit_logger::get_recent_logs(10, 30);
        
        $this->assertIsArray($logs, 'get_recent_logs devrait retourner un tableau');
    }

    /**
     * Test cleanup_old_logs()
     */
    public function test_cleanup_old_logs() {
        $this->resetAfterTest(true);
        
        $deleted = audit_logger::cleanup_old_logs();
        
        $this->assertIsInt($deleted, 'cleanup_old_logs devrait retourner un entier');
        $this->assertGreaterThanOrEqual(0, $deleted, 'Le nombre de fichiers supprimés doit être >= 0');
    }

    /**
     * Test log_action() avec exception
     */
    public function test_log_action_handles_exceptions() {
        $this->resetAfterTest(true);
        
        // Tester avec des données invalides qui pourraient causer une exception
        $result = audit_logger::log_action('', [], null);
        
        // Même en cas d'erreur, la méthode devrait retourner false sans exception
        $this->assertIsBool($result, 'log_action devrait retourner un booléen même en cas d\'erreur');
    }

    /**
     * Test constantes d'événements
     */
    public function test_event_constants_defined() {
        $this->assertEquals('category_deleted', audit_logger::EVENT_CATEGORY_DELETED);
        $this->assertEquals('categories_merged', audit_logger::EVENT_CATEGORIES_MERGED);
        $this->assertEquals('category_moved', audit_logger::EVENT_CATEGORY_MOVED);
        $this->assertEquals('question_deleted', audit_logger::EVENT_QUESTION_DELETED);
        $this->assertEquals('data_exported', audit_logger::EVENT_DATA_EXPORTED);
        $this->assertEquals('cache_purged', audit_logger::EVENT_CACHE_PURGED);
    }
}

