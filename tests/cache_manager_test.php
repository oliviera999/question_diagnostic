<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Tests unitaires pour cache_manager
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
require_once($CFG->dirroot . '/local/question_diagnostic/classes/cache_manager.php');

/**
 * Tests pour la classe cache_manager
 *
 * @covers \local_question_diagnostic\cache_manager
 */
class cache_manager_test extends \advanced_testcase {

    /**
     * Test get_cache() pour categories
     */
    public function test_get_cache_categories() {
        $this->resetAfterTest(true);
        
        $cache = cache_manager::get_cache('categories');
        
        $this->assertInstanceOf(\cache::class, $cache, 'Devrait retourner une instance de cache');
    }

    /**
     * Test get_cache() pour questions
     */
    public function test_get_cache_questions() {
        $this->resetAfterTest(true);
        
        $cache = cache_manager::get_cache('questions');
        
        $this->assertInstanceOf(\cache::class, $cache, 'Devrait retourner une instance de cache');
    }

    /**
     * Test get_cache() pour broken_links
     */
    public function test_get_cache_broken_links() {
        $this->resetAfterTest(true);
        
        $cache = cache_manager::get_cache('broken_links');
        
        $this->assertInstanceOf(\cache::class, $cache, 'Devrait retourner une instance de cache');
    }

    /**
     * Test get() et set()
     */
    public function test_get_and_set() {
        $this->resetAfterTest(true);
        
        $key = 'test_key';
        $value = ['data' => 'test_value', 'count' => 42];
        
        // Set
        cache_manager::set('categories', $key, $value);
        
        // Get
        $retrieved = cache_manager::get('categories', $key);
        
        $this->assertEquals($value, $retrieved, 'La valeur récupérée devrait correspondre à la valeur stockée');
    }

    /**
     * Test get() avec clé inexistante
     */
    public function test_get_nonexistent_key() {
        $this->resetAfterTest(true);
        
        $result = cache_manager::get('categories', 'nonexistent_key');
        
        $this->assertFalse($result, 'Devrait retourner false pour une clé inexistante');
    }

    /**
     * Test purge_cache() pour un cache spécifique
     */
    public function test_purge_specific_cache() {
        $this->resetAfterTest(true);
        
        // Stocker une valeur
        cache_manager::set('categories', 'key1', 'value1');
        
        // Purger le cache
        cache_manager::purge_cache('categories');
        
        // La valeur ne devrait plus être accessible
        $result = cache_manager::get('categories', 'key1');
        $this->assertFalse($result, 'La valeur devrait avoir été purgée');
    }

    /**
     * Test purge_all_caches()
     */
    public function test_purge_all_caches() {
        $this->resetAfterTest(true);
        
        // Stocker des valeurs dans différents caches
        cache_manager::set('categories', 'key1', 'value1');
        cache_manager::set('questions', 'key2', 'value2');
        cache_manager::set('broken_links', 'key3', 'value3');
        
        // Purger tous les caches
        cache_manager::purge_all_caches();
        
        // Toutes les valeurs devraient avoir été purgées
        $this->assertFalse(cache_manager::get('categories', 'key1'));
        $this->assertFalse(cache_manager::get('questions', 'key2'));
        $this->assertFalse(cache_manager::get('broken_links', 'key3'));
    }

    /**
     * Test set() et get() avec des types de données différents
     */
    public function test_different_data_types() {
        $this->resetAfterTest(true);
        
        // String
        cache_manager::set('categories', 'string_key', 'string_value');
        $this->assertEquals('string_value', cache_manager::get('categories', 'string_key'));
        
        // Integer
        cache_manager::set('categories', 'int_key', 123);
        $this->assertEquals(123, cache_manager::get('categories', 'int_key'));
        
        // Array
        $array_value = ['a' => 1, 'b' => 2, 'c' => [3, 4, 5]];
        cache_manager::set('categories', 'array_key', $array_value);
        $this->assertEquals($array_value, cache_manager::get('categories', 'array_key'));
        
        // Object
        $object_value = (object)['prop1' => 'value1', 'prop2' => 'value2'];
        cache_manager::set('categories', 'object_key', $object_value);
        $this->assertEquals($object_value, cache_manager::get('categories', 'object_key'));
    }

    /**
     * Test get_cache() avec nom invalide
     */
    public function test_get_cache_invalid_name() {
        $this->resetAfterTest(true);
        
        // Un nom de cache invalide devrait retourner le cache 'categories' par défaut
        $cache = cache_manager::get_cache('invalid_cache_name');
        
        $this->assertInstanceOf(\cache::class, $cache, 'Devrait retourner une instance de cache même avec nom invalide');
    }

    /**
     * Test performance: set et get multiples
     */
    public function test_performance_multiple_operations() {
        $this->resetAfterTest(true);
        
        $start_time = microtime(true);
        
        // 100 opérations set/get
        for ($i = 0; $i < 100; $i++) {
            cache_manager::set('categories', 'perf_key_' . $i, ['data' => $i]);
            cache_manager::get('categories', 'perf_key_' . $i);
        }
        
        $elapsed_time = (microtime(true) - $start_time) * 1000; // en ms
        
        // Devrait prendre moins de 500ms pour 100 opérations
        $this->assertLessThan(500, $elapsed_time, '100 opérations set/get devraient prendre moins de 500ms');
    }
}

