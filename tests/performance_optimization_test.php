<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Tests de performance pour les optimisations Phase 1&2
 * 
 * 🚀 Phase 2 : Optimisation - Tests de performance
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_question_diagnostic;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/question_diagnostic/classes/performance_monitor.php');
require_once($CFG->dirroot . '/local/question_diagnostic/classes/cache_manager.php');
require_once($CFG->dirroot . '/local/question_diagnostic/classes/debug_manager.php');
require_once($CFG->dirroot . '/local/question_diagnostic/classes/error_manager.php');

/**
 * Tests de performance pour les optimisations
 */
class performance_optimization_test extends \advanced_testcase {

    /**
     * Test des métriques de performance
     */
    public function test_performance_monitoring() {
        $this->resetAfterTest();
        
        // Test d'une opération rapide
        $metric_id = performance_monitor::start_operation('test_fast_operation', ['test' => 'data']);
        
        // Simuler du travail
        usleep(10000); // 10ms
        
        $result = performance_monitor::end_operation($metric_id, ['result' => 'success']);
        
        $this->assertIsArray($result);
        $this->assertEquals('test_fast_operation', $result['operation']);
        $this->assertGreaterThan(0, $result['duration']);
        $this->assertArrayHasKey('analysis', $result);
        $this->assertEquals('completed', $result['status']);
    }

    /**
     * Test du wrapper de mesure automatique
     */
    public function test_measure_wrapper() {
        $this->resetAfterTest();
        
        $result = performance_monitor::measure(
            function() {
                usleep(5000); // 5ms
                return 'test_result';
            },
            'test_wrapped_operation',
            ['context' => 'test']
        );
        
        $this->assertEquals('test_result', $result);
        
        // Vérifier que les métriques ont été enregistrées
        $stats = performance_monitor::get_global_stats(1);
        $this->assertGreaterThan(0, $stats['total_operations']);
    }

    /**
     * Test des statistiques globales
     */
    public function test_global_stats() {
        $this->resetAfterTest();
        
        // Exécuter plusieurs opérations
        for ($i = 0; $i < 3; $i++) {
            $metric_id = performance_monitor::start_operation("test_operation_{$i}");
            usleep(1000); // 1ms
            performance_monitor::end_operation($metric_id);
        }
        
        $stats = performance_monitor::get_global_stats(1);
        
        $this->assertEquals(3, $stats['total_operations']);
        $this->assertGreaterThan(0, $stats['average_duration']);
        $this->assertArrayHasKey('performance_distribution', $stats);
        $this->assertArrayHasKey('recommendations', $stats);
    }

    /**
     * Test du cache intelligent
     */
    public function test_intelligent_cache() {
        $this->resetAfterTest();
        
        $test_data = ['test' => 'data', 'timestamp' => time()];
        
        // Test du cache adaptatif
        $result = cache_manager::set_adaptive(
            cache_manager::CACHE_GLOBALSTATS,
            $test_data
        );
        
        $this->assertTrue($result);
        
        // Test du cache conditionnel
        $result = cache_manager::set_if_changed(
            cache_manager::CACHE_GLOBALSTATS,
            'test_key',
            $test_data
        );
        
        $this->assertTrue($result);
        
        // Test avec les mêmes données (ne devrait pas être mis en cache)
        $result = cache_manager::set_if_changed(
            cache_manager::CACHE_GLOBALSTATS,
            'test_key',
            $test_data
        );
        
        $this->assertFalse($result); // Pas de changement
    }

    /**
     * Test du warm-up intelligent du cache
     */
    public function test_intelligent_warmup() {
        $this->resetAfterTest();
        
        $results = cache_manager::intelligent_warmup();
        
        $this->assertIsArray($results);
        $this->assertArrayHasKey('started_at', $results);
        $this->assertArrayHasKey('completed_at', $results);
        $this->assertArrayHasKey('operations', $results);
        $this->assertGreaterThan(0, count($results['operations']));
    }

    /**
     * Test des métriques de cache
     */
    public function test_cache_metrics() {
        $this->resetAfterTest();
        
        // Enregistrer quelques métriques
        cache_manager::record_metric('hit', cache_manager::CACHE_DUPLICATES);
        cache_manager::record_metric('miss', cache_manager::CACHE_DUPLICATES);
        cache_manager::record_metric('set', cache_manager::CACHE_DUPLICATES, 3600);
        
        $metrics = cache_manager::get_performance_metrics();
        
        $this->assertArrayHasKey('cache_hits', $metrics);
        $this->assertArrayHasKey('cache_misses', $metrics);
        $this->assertArrayHasKey('cache_sets', $metrics);
        $this->assertArrayHasKey('hit_ratio', $metrics);
        $this->assertEquals(1, $metrics['cache_hits']);
        $this->assertEquals(1, $metrics['cache_misses']);
        $this->assertEquals(1, $metrics['cache_sets']);
    }

    /**
     * Test du système de debugging centralisé
     */
    public function test_centralized_debugging() {
        $this->resetAfterTest();
        
        debug_manager::init();
        debug_manager::set_context('test_performance');
        
        // Test des différents niveaux
        debug_manager::set_level(debug_manager::LEVEL_VERBOSE);
        debug_manager::error('Test error');
        debug_manager::warning('Test warning');
        debug_manager::info('Test info');
        debug_manager::verbose('Test verbose');
        
        $stats = debug_manager::get_stats();
        
        $this->assertEquals(4, $stats['total_messages']);
        $this->assertEquals('test_performance', $stats['current_context']);
        $this->assertEquals(debug_manager::LEVEL_VERBOSE, $stats['current_level']);
    }

    /**
     * Test du gestionnaire d'erreurs centralisé
     */
    public function test_centralized_error_handling() {
        $this->resetAfterTest();
        
        // Test création d'erreur
        $error = error_manager::create_error(
            error_manager::CATEGORY_NOT_FOUND,
            ['id' => 123]
        );
        
        $this->assertIsArray($error);
        $this->assertEquals(error_manager::CATEGORY_NOT_FOUND, $error['code']);
        $this->assertArrayHasKey('technical_message', $error);
        $this->assertArrayHasKey('user_message', $error);
        
        // Test historique
        $history = error_manager::get_error_history();
        $this->assertCount(1, $history);
        
        // Test statistiques
        $stats = error_manager::get_error_stats();
        $this->assertEquals(1, $stats['total_errors']);
    }

    /**
     * Test de l'export des métriques
     */
    public function test_metrics_export() {
        $this->resetAfterTest();
        
        // Générer quelques métriques
        $metric_id = performance_monitor::start_operation('export_test');
        usleep(1000);
        performance_monitor::end_operation($metric_id);
        
        $export = performance_monitor::export_metrics(1);
        
        $this->assertArrayHasKey('export_timestamp', $export);
        $this->assertArrayHasKey('metrics', $export);
        $this->assertArrayHasKey('summary', $export);
        $this->assertGreaterThan(0, $export['total_metrics']);
    }

    /**
     * Test de performance avec des données volumineuses
     */
    public function test_large_data_performance() {
        $this->resetAfterTest();
        
        // Créer des données volumineuses
        $large_data = [];
        for ($i = 0; $i < 1000; $i++) {
            $large_data[] = [
                'id' => $i,
                'name' => "Item {$i}",
                'data' => str_repeat('x', 100)
            ];
        }
        
        $metric_id = performance_monitor::start_operation('large_data_test', [
            'data_size' => count($large_data)
        ]);
        
        // Simuler du traitement
        $result = array_map(function($item) {
            return $item['id'] * 2;
        }, $large_data);
        
        $final_metric = performance_monitor::end_operation($metric_id, [
            'processed_items' => count($result)
        ]);
        
        $this->assertIsArray($final_metric);
        $this->assertGreaterThan(0, $final_metric['memory_used']);
        $this->assertEquals(count($large_data), count($result));
    }

    /**
     * Test de nettoyage de l'historique
     */
    public function test_history_cleanup() {
        $this->resetAfterTest();
        
        // Générer quelques métriques
        for ($i = 0; $i < 5; $i++) {
            $metric_id = performance_monitor::start_operation("cleanup_test_{$i}");
            usleep(1000);
            performance_monitor::end_operation($metric_id);
        }
        
        $stats_before = performance_monitor::get_global_stats(1);
        $this->assertEquals(5, $stats_before['total_operations']);
        
        // Nettoyer l'historique (garder seulement 1 heure)
        performance_monitor::cleanup_history(1);
        
        // Les métriques devraient toujours être là (récentes)
        $stats_after = performance_monitor::get_global_stats(1);
        $this->assertEquals(5, $stats_after['total_operations']);
    }

    /**
     * Test des opérations en cours
     */
    public function test_current_operations() {
        $this->resetAfterTest();
        
        // Démarrer une opération sans la terminer
        $metric_id = performance_monitor::start_operation('running_operation');
        
        $current = performance_monitor::get_current_operations();
        $this->assertArrayHasKey($metric_id, $current);
        $this->assertEquals('running', $current[$metric_id]['status']);
        
        // Terminer l'opération
        performance_monitor::end_operation($metric_id);
        
        $current_after = performance_monitor::get_current_operations();
        $this->assertArrayNotHasKey($metric_id, $current_after);
    }
}
