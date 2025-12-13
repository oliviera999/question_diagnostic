<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_question_diagnostic;

defined('MOODLE_INTERNAL') || die();

/**
 * Moniteur de performance pour le plugin Question Diagnostic
 * 
 * 🚀 Phase 2 : Optimisation - Métriques de performance en temps réel
 * 
 * Cette classe fournit un monitoring complet des performances :
 * - Temps d'exécution des opérations critiques
 * - Utilisation mémoire et CPU
 * - Statistiques de base de données
 * - Métriques de cache
 * - Recommandations automatiques
 * 
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class performance_monitor {

    /**
     * Instance singleton
     * @var performance_monitor
     */
    private static $instance = null;

    /**
     * Métriques en cours de collecte
     * @var array
     */
    private $current_metrics = [];

    /**
     * Historique des métriques
     * @var array
     */
    private $metrics_history = [];

    /**
     * Seuils de performance
     * @var array
     */
    private $performance_thresholds = [
        'category_stats' => 2.0,      // 2 secondes max
        'duplicate_detection' => 10.0, // 10 secondes max
        'question_usage' => 5.0,      // 5 secondes max
        'broken_links' => 15.0,       // 15 secondes max
        'memory_usage' => 128 * 1024 * 1024, // 128MB max
        'cache_hit_ratio' => 80.0     // 80% minimum
    ];

    /**
     * Obtenir l'instance singleton
     * 
     * @return performance_monitor
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Démarrer le monitoring d'une opération
     * 
     * @param string $operation Nom de l'opération
     * @param array $context Contexte supplémentaire
     * @return string ID de la métrique
     */
    public static function start_operation($operation, $context = []) {
        $monitor = self::get_instance();
        $metric_id = uniqid($operation . '_', true);
        
        $monitor->current_metrics[$metric_id] = [
            'operation' => $operation,
            'context' => $context,
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'start_peak_memory' => memory_get_peak_usage(true),
            'sql_queries_before' => self::get_sql_query_count(),
            'status' => 'running'
        ];
        
        if (class_exists('\local_question_diagnostic\debug_manager')) {
            debug_manager::set_context('performance_monitor');
            debug_manager::progress("Started operation: {$operation}", ['id' => $metric_id]);
        }
        
        return $metric_id;
    }

    /**
     * Arrêter le monitoring d'une opération
     * 
     * @param string $metric_id ID de la métrique
     * @param array $result Données du résultat (optionnel)
     * @return array Métriques finales
     */
    public static function end_operation($metric_id, $result = []) {
        $monitor = self::get_instance();
        
        if (!isset($monitor->current_metrics[$metric_id])) {
            return null;
        }
        
        $metric = $monitor->current_metrics[$metric_id];
        $end_time = microtime(true);
        $end_memory = memory_get_usage(true);
        $end_peak_memory = memory_get_peak_usage(true);
        $end_sql_queries = self::get_sql_query_count();
        
        // Calculer les métriques
        $final_metric = [
            'id' => $metric_id,
            'operation' => $metric['operation'],
            'context' => $metric['context'],
            'result' => $result,
            'duration' => $end_time - $metric['start_time'],
            'memory_used' => $end_memory - $metric['start_memory'],
            'peak_memory_used' => $end_peak_memory - $metric['start_peak_memory'],
            'sql_queries_count' => $end_sql_queries - $metric['sql_queries_before'],
            'sql_queries_per_second' => ($end_sql_queries - $metric['sql_queries_before']) / max(0.001, $end_time - $metric['start_time']),
            'start_time' => $metric['start_time'],
            'end_time' => $end_time,
            'status' => 'completed'
        ];
        
        // Analyser les performances
        $final_metric['analysis'] = self::analyze_performance($final_metric);
        
        // Ajouter à l'historique
        $monitor->metrics_history[] = $final_metric;
        
        // Nettoyer les métriques courantes
        unset($monitor->current_metrics[$metric_id]);
        
        // Logger les résultats
        if (class_exists('\local_question_diagnostic\debug_manager')) {
            debug_manager::performance(
                "Completed operation: {$metric['operation']}",
                $final_metric['duration'],
                [
                    'memory_mb' => round($final_metric['memory_used'] / 1024 / 1024, 2),
                    'sql_queries' => $final_metric['sql_queries_count'],
                    'performance' => $final_metric['analysis']['performance_level']
                ]
            );
        }
        
        return $final_metric;
    }

    /**
     * Analyser les performances d'une opération
     * 
     * @param array $metric Métriques de l'opération
     * @return array Analyse des performances
     */
    private static function analyze_performance($metric) {
        $operation = $metric['operation'];
        $duration = $metric['duration'];
        $memory_mb = $metric['memory_used'] / 1024 / 1024;
        $sql_queries = $metric['sql_queries_count'];
        
        $analysis = [
            'performance_level' => 'good',
            'warnings' => [],
            'recommendations' => [],
            'score' => 100
        ];
        
        $monitor = self::get_instance();
        $thresholds = $monitor->performance_thresholds;
        
        // Analyser la durée
        $duration_threshold = $thresholds[$operation] ?? 5.0;
        if ($duration > $duration_threshold) {
            $analysis['performance_level'] = 'poor';
            $analysis['warnings'][] = "Operation took {$duration}s (threshold: {$duration_threshold}s)";
            $analysis['recommendations'][] = "Consider optimizing database queries or adding indexes";
            $analysis['score'] -= 30;
        } elseif ($duration > $duration_threshold * 0.7) {
            $analysis['performance_level'] = 'fair';
            $analysis['warnings'][] = "Operation took {$duration}s (approaching threshold)";
            $analysis['recommendations'][] = "Monitor performance and consider optimization";
            $analysis['score'] -= 15;
        }
        
        // Analyser l'utilisation mémoire
        $memory_threshold_mb = $thresholds['memory_usage'] / 1024 / 1024;
        if ($memory_mb > $memory_threshold_mb) {
            $analysis['performance_level'] = 'poor';
            $analysis['warnings'][] = "High memory usage: {$memory_mb}MB";
            $analysis['recommendations'][] = "Consider pagination or data chunking";
            $analysis['score'] -= 25;
        }
        
        // Analyser les requêtes SQL
        if ($sql_queries > 100) {
            $analysis['warnings'][] = "High number of SQL queries: {$sql_queries}";
            $analysis['recommendations'][] = "Consider query optimization or caching";
            $analysis['score'] -= 20;
        }
        
        // Analyser le ratio de requêtes par seconde
        if ($metric['sql_queries_per_second'] > 50) {
            $analysis['warnings'][] = "High SQL query rate: " . round($metric['sql_queries_per_second'], 1) . " queries/sec";
            $analysis['recommendations'][] = "Consider query batching or optimization";
            $analysis['score'] -= 15;
        }
        
        $analysis['score'] = max(0, $analysis['score']);
        
        return $analysis;
    }

    /**
     * Obtenir le nombre de requêtes SQL exécutées
     * 
     * @return int Nombre de requêtes
     */
    private static function get_sql_query_count() {
        global $DB;
        
        // Utiliser les métriques Moodle si disponibles
        if (method_exists($DB, 'get_query_count')) {
            return $DB->get_query_count();
        }
        
        // Fallback : utiliser les métriques de performance
        if (class_exists('\core\performance_measurement')) {
            return \core\performance_measurement::get_query_count();
        }
        
        return 0;
    }

    /**
     * Obtenir les statistiques globales de performance
     * 
     * @param int $hours Nombre d'heures à analyser (défaut: 24)
     * @return array Statistiques globales
     */
    public static function get_global_stats($hours = 24) {
        $monitor = self::get_instance();
        $cutoff_time = time() - ($hours * 3600);
        
        // Filtrer les métriques récentes
        $recent_metrics = array_filter($monitor->metrics_history, function($metric) use ($cutoff_time) {
            return $metric['start_time'] >= $cutoff_time;
        });
        
        if (empty($recent_metrics)) {
            return [
                'total_operations' => 0,
                'average_duration' => 0,
                'total_memory_used' => 0,
                'total_sql_queries' => 0,
                'performance_distribution' => ['good' => 0, 'fair' => 0, 'poor' => 0],
                'top_slow_operations' => [],
                'recommendations' => []
            ];
        }
        
        // Calculer les statistiques
        $total_duration = array_sum(array_column($recent_metrics, 'duration'));
        $total_memory = array_sum(array_column($recent_metrics, 'memory_used'));
        $total_sql = array_sum(array_column($recent_metrics, 'sql_queries_count'));
        
        $performance_distribution = ['good' => 0, 'fair' => 0, 'poor' => 0];
        foreach ($recent_metrics as $metric) {
            $performance_distribution[$metric['analysis']['performance_level']]++;
        }
        
        // Top 5 des opérations les plus lentes
        usort($recent_metrics, function($a, $b) {
            return $b['duration'] <=> $a['duration'];
        });
        $top_slow = array_slice($recent_metrics, 0, 5);
        
        // Recommandations automatiques
        $recommendations = self::generate_recommendations($recent_metrics);
        
        return [
            'total_operations' => count($recent_metrics),
            'average_duration' => $total_duration / count($recent_metrics),
            'total_memory_used' => $total_memory,
            'total_sql_queries' => $total_sql,
            'average_memory_per_operation' => $total_memory / count($recent_metrics),
            'average_sql_per_operation' => $total_sql / count($recent_metrics),
            'performance_distribution' => $performance_distribution,
            'top_slow_operations' => $top_slow,
            'recommendations' => $recommendations,
            'period_hours' => $hours
        ];
    }

    /**
     * Générer des recommandations automatiques
     * 
     * @param array $metrics Métriques récentes
     * @return array Recommandations
     */
    private static function generate_recommendations($metrics) {
        $recommendations = [];
        
        // Analyser les opérations lentes
        $slow_operations = array_filter($metrics, function($m) {
            return $m['analysis']['performance_level'] === 'poor';
        });
        
        if (count($slow_operations) > count($metrics) * 0.3) {
            $recommendations[] = [
                'type' => 'critical',
                'title' => 'Performance Issues Detected',
                'message' => 'More than 30% of operations are performing poorly',
                'action' => 'Consider installing database indexes or enabling caching'
            ];
        }
        
        // Analyser l'utilisation mémoire
        $high_memory_operations = array_filter($metrics, function($m) {
            return $m['memory_used'] > 50 * 1024 * 1024; // > 50MB
        });
        
        if (count($high_memory_operations) > 0) {
            $recommendations[] = [
                'type' => 'warning',
                'title' => 'High Memory Usage',
                'message' => count($high_memory_operations) . ' operations used more than 50MB',
                'action' => 'Consider implementing pagination or data chunking'
            ];
        }
        
        // Analyser les requêtes SQL
        $high_sql_operations = array_filter($metrics, function($m) {
            return $m['sql_queries_count'] > 50;
        });
        
        if (count($high_sql_operations) > 0) {
            $recommendations[] = [
                'type' => 'info',
                'title' => 'High SQL Query Count',
                'message' => count($high_sql_operations) . ' operations executed more than 50 SQL queries',
                'action' => 'Consider query optimization or result caching'
            ];
        }
        
        return $recommendations;
    }

    /**
     * Obtenir les métriques en cours
     * 
     * @return array Métriques actives
     */
    public static function get_current_operations() {
        $monitor = self::get_instance();
        return $monitor->current_metrics;
    }

    /**
     * Nettoyer l'historique des métriques
     * 
     * @param int $keep_hours Nombre d'heures à conserver
     */
    public static function cleanup_history($keep_hours = 168) { // 7 jours par défaut
        $monitor = self::get_instance();
        $cutoff_time = time() - ($keep_hours * 3600);
        
        $monitor->metrics_history = array_filter($monitor->metrics_history, function($metric) use ($cutoff_time) {
            return $metric['start_time'] >= $cutoff_time;
        });
    }

    /**
     * Exporter les métriques pour analyse
     * 
     * @param int $hours Nombre d'heures à exporter
     * @return array Données exportées
     */
    public static function export_metrics($hours = 24) {
        $monitor = self::get_instance();
        $cutoff_time = time() - ($hours * 3600);
        
        $metrics = array_filter($monitor->metrics_history, function($metric) use ($cutoff_time) {
            return $metric['start_time'] >= $cutoff_time;
        });
        
        return [
            'export_timestamp' => time(),
            'period_hours' => $hours,
            'total_metrics' => count($metrics),
            'metrics' => $metrics,
            'summary' => self::get_global_stats($hours)
        ];
    }

    /**
     * Wrapper pour mesurer automatiquement une fonction
     * 
     * @param callable $callback Fonction à mesurer
     * @param string $operation Nom de l'opération
     * @param array $context Contexte
     * @return mixed Résultat de la fonction
     */
    public static function measure($callback, $operation, $context = []) {
        $metric_id = self::start_operation($operation, $context);
        
        try {
            $result = $callback();
            self::end_operation($metric_id, ['success' => true, 'result' => $result]);
            return $result;
        } catch (\Exception $e) {
            self::end_operation($metric_id, ['success' => false, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
