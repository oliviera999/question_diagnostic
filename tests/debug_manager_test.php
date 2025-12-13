<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Tests unitaires pour debug_manager
 * 
 * 🔧 Phase 1 : Stabilisation - Tests critiques
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_question_diagnostic;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/question_diagnostic/classes/debug_manager.php');

/**
 * Tests pour debug_manager
 */
class debug_manager_test extends \advanced_testcase {

    /**
     * Test de l'initialisation du système de debugging
     */
    public function test_init() {
        $this->resetAfterTest();
        
        debug_manager::init();
        
        $stats = debug_manager::get_stats();
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('current_level', $stats);
        $this->assertArrayHasKey('message_counts', $stats);
        $this->assertEquals(0, $stats['total_messages']);
    }

    /**
     * Test de la définition du contexte
     */
    public function test_set_context() {
        $this->resetAfterTest();
        
        debug_manager::init();
        debug_manager::set_context('test_context');
        
        $stats = debug_manager::get_stats();
        $this->assertEquals('test_context', $stats['current_context']);
    }

    /**
     * Test de la définition du niveau de debugging
     */
    public function test_set_level() {
        $this->resetAfterTest();
        
        debug_manager::init();
        
        // Test niveau silencieux
        debug_manager::set_level(debug_manager::LEVEL_SILENT);
        debug_manager::error('Test error');
        
        $stats = debug_manager::get_stats();
        $this->assertEquals(0, $stats['total_messages']);
        
        // Test niveau erreur uniquement
        debug_manager::set_level(debug_manager::LEVEL_ERROR);
        debug_manager::error('Test error');
        debug_manager::warning('Test warning');
        
        $stats = debug_manager::get_stats();
        $this->assertEquals(1, $stats['total_messages']);
        $this->assertEquals(1, $stats['message_counts'][debug_manager::LEVEL_ERROR]);
        $this->assertEquals(0, $stats['message_counts'][debug_manager::LEVEL_WARNING]);
    }

    /**
     * Test des différents types de messages
     */
    public function test_message_types() {
        $this->resetAfterTest();
        
        debug_manager::init();
        debug_manager::set_context('test_messages');
        debug_manager::set_level(debug_manager::LEVEL_VERBOSE);
        
        debug_manager::error('Test error');
        debug_manager::warning('Test warning');
        debug_manager::info('Test info');
        debug_manager::verbose('Test verbose');
        debug_manager::success('Test success');
        debug_manager::progress('Test progress');
        
        $stats = debug_manager::get_stats();
        $this->assertEquals(6, $stats['total_messages']);
        $this->assertEquals(1, $stats['message_counts'][debug_manager::LEVEL_ERROR]);
        $this->assertEquals(1, $stats['message_counts'][debug_manager::LEVEL_WARNING]);
        $this->assertEquals(4, $stats['message_counts'][debug_manager::LEVEL_INFO]);
        $this->assertEquals(0, $stats['message_counts'][debug_manager::LEVEL_VERBOSE]);
    }

    /**
     * Test de la réinitialisation des statistiques
     */
    public function test_reset_stats() {
        $this->resetAfterTest();
        
        debug_manager::init();
        debug_manager::set_level(debug_manager::LEVEL_ERROR);
        debug_manager::error('Test error');
        
        $stats = debug_manager::get_stats();
        $this->assertEquals(1, $stats['total_messages']);
        
        debug_manager::reset_stats();
        
        $stats = debug_manager::get_stats();
        $this->assertEquals(0, $stats['total_messages']);
    }

    /**
     * Test de la gestion des exceptions
     */
    public function test_exception_logging() {
        $this->resetAfterTest();
        
        debug_manager::init();
        debug_manager::set_context('test_exceptions');
        
        $exception = new \Exception('Test exception message');
        debug_manager::exception($exception, 'test_function');
        
        $stats = debug_manager::get_stats();
        $this->assertEquals(1, $stats['total_messages']);
        $this->assertEquals(1, $stats['message_counts'][debug_manager::LEVEL_ERROR]);
    }

    /**
     * Test du logging de performance
     */
    public function test_performance_logging() {
        $this->resetAfterTest();
        
        debug_manager::init();
        debug_manager::set_level(debug_manager::LEVEL_INFO);
        
        debug_manager::performance('test_operation', 0.5, ['items' => 100]);
        
        $stats = debug_manager::get_stats();
        $this->assertEquals(1, $stats['total_messages']);
        $this->assertEquals(1, $stats['message_counts'][debug_manager::LEVEL_INFO]);
    }

    /**
     * Test du logging SQL
     */
    public function test_sql_logging() {
        $this->resetAfterTest();
        
        debug_manager::init();
        debug_manager::set_level(debug_manager::LEVEL_VERBOSE);
        
        debug_manager::sql('SELECT * FROM test', ['id' => 1], 0.1);
        
        $stats = debug_manager::get_stats();
        $this->assertEquals(1, $stats['total_messages']);
        $this->assertEquals(1, $stats['message_counts'][debug_manager::LEVEL_VERBOSE]);
        
        // Test sans niveau verbose
        debug_manager::set_level(debug_manager::LEVEL_INFO);
        debug_manager::reset_stats();
        debug_manager::sql('SELECT * FROM test', ['id' => 1], 0.1);
        
        $stats = debug_manager::get_stats();
        $this->assertEquals(0, $stats['total_messages']);
    }

    /**
     * Test du niveau de debugging recommandé
     */
    public function test_get_recommended_level() {
        global $CFG;
        
        $this->resetAfterTest();
        
        // Test en mode production
        $CFG->debug = DEBUG_NONE;
        $level = debug_manager::get_recommended_level();
        $this->assertEquals(debug_manager::LEVEL_ERROR, $level);
        
        // Test en mode développement
        $CFG->debug = DEBUG_DEVELOPER;
        $level = debug_manager::get_recommended_level();
        $this->assertEquals(debug_manager::LEVEL_VERBOSE, $level);
        
        // Test mode normal
        $CFG->debug = DEBUG_NORMAL;
        $level = debug_manager::get_recommended_level();
        $this->assertEquals(debug_manager::LEVEL_INFO, $level);
    }
}
