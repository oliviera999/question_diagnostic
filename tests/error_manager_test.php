<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Tests unitaires pour error_manager
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
require_once($CFG->dirroot . '/local/question_diagnostic/classes/error_manager.php');

/**
 * Tests pour error_manager
 */
class error_manager_test extends \advanced_testcase {

    /**
     * Test de création d'erreur standard
     */
    public function test_create_error() {
        $this->resetAfterTest();
        
        $error = error_manager::create_error(
            error_manager::CATEGORY_NOT_FOUND,
            ['id' => 123]
        );
        
        $this->assertIsArray($error);
        $this->assertEquals(error_manager::CATEGORY_NOT_FOUND, $error['code']);
        $this->assertEquals('medium', $error['severity']);
        $this->assertStringContains('123', $error['technical_message']);
        $this->assertStringContains('catégorie demandée', $error['user_message']);
        $this->assertArrayHasKey('timestamp', $error);
    }

    /**
     * Test de création d'erreur avec exception
     */
    public function test_create_error_with_exception() {
        $this->resetAfterTest();
        
        $exception = new \Exception('Test exception');
        $error = error_manager::create_error(
            error_manager::DATABASE_ERROR,
            ['message' => 'SQL error'],
            $exception
        );
        
        $this->assertIsArray($error);
        $this->assertEquals(error_manager::DATABASE_ERROR, $error['code']);
        $this->assertEquals('critical', $error['severity']);
        $this->assertIsArray($error['exception']);
        $this->assertEquals('Exception', $error['exception']['type']);
        $this->assertEquals('Test exception', $error['exception']['message']);
    }

    /**
     * Test des méthodes de création d'erreur spécialisées
     */
    public function test_specialized_error_creation() {
        $this->resetAfterTest();
        
        // Test catégorie non trouvée
        $error = error_manager::category_not_found(456);
        $this->assertEquals(error_manager::CATEGORY_NOT_FOUND, $error['code']);
        $this->assertEquals(456, $error['params']['id']);
        
        // Test question non trouvée
        $error = error_manager::question_not_found(789);
        $this->assertEquals(error_manager::QUESTION_NOT_FOUND, $error['code']);
        $this->assertEquals(789, $error['params']['id']);
        
        // Test contexte invalide
        $error = error_manager::invalid_context(101112);
        $this->assertEquals(error_manager::INVALID_CONTEXT, $error['code']);
        $this->assertEquals(101112, $error['params']['context_id']);
        
        // Test erreur de base de données
        $exception = new \Exception('Connection failed');
        $error = error_manager::database_error('Connection failed', $exception);
        $this->assertEquals(error_manager::DATABASE_ERROR, $error['code']);
        $this->assertEquals('critical', $error['severity']);
        
        // Test permission refusée
        $error = error_manager::permission_denied('delete_category');
        $this->assertEquals(error_manager::PERMISSION_DENIED, $error['code']);
        $this->assertEquals('delete_category', $error['params']['action']);
        
        // Test erreur de validation
        $error = error_manager::validation_error('name', 'Name is required');
        $this->assertEquals(error_manager::VALIDATION_ERROR, $error['code']);
        $this->assertEquals('name', $error['params']['field']);
        $this->assertEquals('Name is required', $error['params']['message']);
    }

    /**
     * Test de l'historique des erreurs
     */
    public function test_error_history() {
        $this->resetAfterTest();
        
        // Créer plusieurs erreurs
        error_manager::category_not_found(1);
        error_manager::question_not_found(2);
        error_manager::invalid_context(3);
        
        $history = error_manager::get_error_history();
        $this->assertCount(3, $history);
        
        // Test filtrage par sévérité
        $critical_errors = error_manager::get_error_history(error_manager::SEVERITY_CRITICAL);
        $this->assertCount(0, $critical_errors); // Aucune erreur critique dans ce test
        
        $medium_errors = error_manager::get_error_history(error_manager::SEVERITY_MEDIUM);
        $this->assertCount(3, $medium_errors);
    }

    /**
     * Test des statistiques d'erreurs
     */
    public function test_error_stats() {
        $this->resetAfterTest();
        
        // Créer des erreurs de différents types
        error_manager::category_not_found(1);
        error_manager::category_not_found(2);
        error_manager::question_not_found(3);
        error_manager::database_error('SQL Error');
        
        $stats = error_manager::get_error_stats();
        
        $this->assertEquals(4, $stats['total_errors']);
        $this->assertEquals(2, $stats['by_severity']['medium']);
        $this->assertEquals(1, $stats['by_severity']['critical']);
        $this->assertEquals(2, $stats['by_code'][error_manager::CATEGORY_NOT_FOUND]);
        $this->assertEquals(1, $stats['by_code'][error_manager::QUESTION_NOT_FOUND]);
        $this->assertEquals(1, $stats['by_code'][error_manager::DATABASE_ERROR]);
        $this->assertCount(4, $stats['recent_errors']);
    }

    /**
     * Test de la réinitialisation de l'historique
     */
    public function test_reset_history() {
        $this->resetAfterTest();
        
        error_manager::category_not_found(1);
        $this->assertCount(1, error_manager::get_error_history());
        
        error_manager::reset_history();
        $this->assertCount(0, error_manager::get_error_history());
    }

    /**
     * Test de vérification d'erreur critique
     */
    public function test_is_critical() {
        $this->resetAfterTest();
        
        $critical_error = error_manager::database_error('Critical error');
        $medium_error = error_manager::category_not_found(1);
        
        $this->assertTrue(error_manager::is_critical($critical_error));
        $this->assertFalse(error_manager::is_critical($medium_error));
    }

    /**
     * Test des messages utilisateur et techniques
     */
    public function test_message_getters() {
        $this->resetAfterTest();
        
        $error = error_manager::category_not_found(123);
        
        $user_message = error_manager::get_user_message($error);
        $technical_message = error_manager::get_technical_message($error);
        
        $this->assertStringContains('catégorie demandée', $user_message);
        $this->assertStringContains('123', $technical_message);
    }

    /**
     * Test des réponses API standardisées
     */
    public function test_api_responses() {
        $this->resetAfterTest();
        
        // Test réponse d'erreur
        $error_response = error_manager::create_api_error_response(
            error_manager::CATEGORY_NOT_FOUND,
            ['id' => 123]
        );
        
        $this->assertFalse($error_response['success']);
        $this->assertEquals(error_manager::CATEGORY_NOT_FOUND, $error_response['error']['code']);
        $this->assertEquals('medium', $error_response['error']['severity']);
        $this->assertArrayHasKey('timestamp', $error_response);
        
        // Test réponse de succès
        $success_response = error_manager::create_api_success_response(
            ['data' => 'test'],
            'Operation successful'
        );
        
        $this->assertTrue($success_response['success']);
        $this->assertEquals(['data' => 'test'], $success_response['data']);
        $this->assertEquals('Operation successful', $success_response['message']);
        $this->assertArrayHasKey('timestamp', $success_response);
    }

    /**
     * Test d'erreur avec code inconnu
     */
    public function test_unknown_error_code() {
        $this->resetAfterTest();
        
        $error = error_manager::create_error('UNKNOWN_ERROR_CODE', ['test' => 'value']);
        
        // Doit fallback vers DATABASE_ERROR
        $this->assertEquals(error_manager::DATABASE_ERROR, $error['code']);
        $this->assertStringContains('Unknown error code', $error['technical_message']);
    }

    /**
     * Test de l'interpolation des messages
     */
    public function test_message_interpolation() {
        $this->resetAfterTest();
        
        $error = error_manager::validation_error('email', 'Invalid format');
        
        $this->assertStringContains('email', $error['technical_message']);
        $this->assertStringContains('Invalid format', $error['technical_message']);
    }
}
