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
 * Gestionnaire centralisé du debugging pour le plugin
 * 
 * 🔧 Phase 1 : Stabilisation - Centralisation du debugging
 * 
 * Cette classe centralise tous les appels de debugging pour :
 * - Contrôler le niveau de verbosité
 * - Standardiser les formats de messages
 * - Éviter les logs verbeux en production
 * - Faciliter le débogage et la maintenance
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class debug_manager {

    /**
     * Niveaux de debugging disponibles
     */
    const LEVEL_SILENT = 0;      // Aucun debug
    const LEVEL_ERROR = 1;       // Erreurs uniquement
    const LEVEL_WARNING = 2;     // Erreurs + avertissements
    const LEVEL_INFO = 3;        // Erreurs + avertissements + info
    const LEVEL_VERBOSE = 4;     // Tous les messages

    /**
     * Contexte actuel du debugging
     * @var string
     */
    private static $current_context = '';

    /**
     * Niveau de debugging actuel
     * @var int
     */
    private static $debug_level = self::LEVEL_INFO;

    /**
     * Compteur de messages par niveau
     * @var array
     */
    private static $message_counts = [
        self::LEVEL_ERROR => 0,
        self::LEVEL_WARNING => 0,
        self::LEVEL_INFO => 0,
        self::LEVEL_VERBOSE => 0
    ];

    /**
     * Définir le contexte actuel
     * 
     * @param string $context Nom du contexte (ex: 'category_manager', 'question_analyzer')
     */
    public static function set_context($context) {
        self::$current_context = $context;
    }

    /**
     * Définir le niveau de debugging
     * 
     * @param int $level Niveau de debugging (constantes LEVEL_*)
     */
    public static function set_level($level) {
        self::$debug_level = max(self::LEVEL_SILENT, min(self::LEVEL_VERBOSE, $level));
    }

    /**
     * Obtenir le niveau de debugging recommandé selon la configuration Moodle
     * 
     * @return int Niveau recommandé
     */
    public static function get_recommended_level() {
        global $CFG;
        
        // En production, limiter le debugging
        if (empty($CFG->debug) || $CFG->debug == DEBUG_NONE) {
            return self::LEVEL_ERROR;
        }
        
        // En développement, plus verbeux
        if ($CFG->debug == DEBUG_DEVELOPER) {
            return self::LEVEL_VERBOSE;
        }
        
        // Par défaut, niveau info
        return self::LEVEL_INFO;
    }

    /**
     * Log d'erreur critique
     * 
     * @param string $message Message d'erreur
     * @param array $context Données contextuelles
     */
    public static function error($message, $context = []) {
        if (self::$debug_level >= self::LEVEL_ERROR) {
            self::log_message(self::LEVEL_ERROR, '❌ ERROR', $message, $context);
        }
    }

    /**
     * Log d'avertissement
     * 
     * @param string $message Message d'avertissement
     * @param array $context Données contextuelles
     */
    public static function warning($message, $context = []) {
        if (self::$debug_level >= self::LEVEL_WARNING) {
            self::log_message(self::LEVEL_WARNING, '⚠️ WARNING', $message, $context);
        }
    }

    /**
     * Log d'information
     * 
     * @param string $message Message d'information
     * @param array $context Données contextuelles
     */
    public static function info($message, $context = []) {
        if (self::$debug_level >= self::LEVEL_INFO) {
            self::log_message(self::LEVEL_INFO, 'ℹ️ INFO', $message, $context);
        }
    }

    /**
     * Log verbeux (développement uniquement)
     * 
     * @param string $message Message verbeux
     * @param array $context Données contextuelles
     */
    public static function verbose($message, $context = []) {
        if (self::$debug_level >= self::LEVEL_VERBOSE) {
            self::log_message(self::LEVEL_VERBOSE, '🔍 VERBOSE', $message, $context);
        }
    }

    /**
     * Log d'opération réussie
     * 
     * @param string $message Message de succès
     * @param array $context Données contextuelles
     */
    public static function success($message, $context = []) {
        if (self::$debug_level >= self::LEVEL_INFO) {
            self::log_message(self::LEVEL_INFO, '✅ SUCCESS', $message, $context);
        }
    }

    /**
     * Log d'opération en cours
     * 
     * @param string $message Message de progression
     * @param array $context Données contextuelles
     */
    public static function progress($message, $context = []) {
        if (self::$debug_level >= self::LEVEL_INFO) {
            self::log_message(self::LEVEL_INFO, '🚀 PROGRESS', $message, $context);
        }
    }

    /**
     * Log centralisé avec formatage
     * 
     * @param int $level Niveau du message
     * @param string $prefix Préfixe du message
     * @param string $message Message principal
     * @param array $context Données contextuelles
     */
    private static function log_message($level, $prefix, $message, $context = []) {
        global $CFG;
        
        // Construire le message formaté
        $formatted_message = "[QD";
        if (!empty(self::$current_context)) {
            $formatted_message .= ":" . self::$current_context;
        }
        $formatted_message .= "] {$prefix} {$message}";
        
        // Ajouter le contexte si fourni
        if (!empty($context)) {
            $formatted_message .= " | Context: " . json_encode($context);
        }
        
        // Ajouter timestamp si en mode verbose
        if (self::$debug_level >= self::LEVEL_VERBOSE) {
            $timestamp = date('H:i:s.v');
            $formatted_message = "[{$timestamp}] " . $formatted_message;
        }
        
        // Incrémenter le compteur
        self::$message_counts[$level]++;
        
        // Log via l'API Moodle
        debugging($formatted_message, DEBUG_DEVELOPER);
    }

    /**
     * Obtenir les statistiques de debugging
     * 
     * @return array Statistiques des messages
     */
    public static function get_stats() {
        return [
            'current_level' => self::$debug_level,
            'current_context' => self::$current_context,
            'message_counts' => self::$message_counts,
            'total_messages' => array_sum(self::$message_counts)
        ];
    }

    /**
     * Réinitialiser les compteurs
     */
    public static function reset_stats() {
        self::$message_counts = [
            self::LEVEL_ERROR => 0,
            self::LEVEL_WARNING => 0,
            self::LEVEL_INFO => 0,
            self::LEVEL_VERBOSE => 0
        ];
    }

    /**
     * Initialiser le système de debugging avec la configuration recommandée
     */
    public static function init() {
        self::set_level(self::get_recommended_level());
        self::reset_stats();
    }

    /**
     * Log d'exception avec stack trace
     * 
     * @param Exception $exception Exception à logger
     * @param string $context Contexte de l'erreur
     */
    public static function exception($exception, $context = '') {
        self::error("Exception in {$context}: " . $exception->getMessage(), [
            'exception_type' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]);
    }

    /**
     * Log de performance (temps d'exécution)
     * 
     * @param string $operation Nom de l'opération
     * @param float $duration Durée en secondes
     * @param array $metrics Métriques supplémentaires
     */
    public static function performance($operation, $duration, $metrics = []) {
        $message = "Performance: {$operation} took " . number_format($duration * 1000, 2) . "ms";
        
        if (!empty($metrics)) {
            $message .= " | " . json_encode($metrics);
        }
        
        self::info($message);
    }

    /**
     * Log de requête SQL (pour debugging avancé)
     * 
     * @param string $sql Requête SQL
     * @param array $params Paramètres
     * @param float $duration Durée d'exécution
     */
    public static function sql($sql, $params = [], $duration = null) {
        if (self::$debug_level >= self::LEVEL_VERBOSE) {
            $message = "SQL Query: " . $sql;
            if (!empty($params)) {
                $message .= " | Params: " . json_encode($params);
            }
            if ($duration !== null) {
                $message .= " | Duration: " . number_format($duration * 1000, 2) . "ms";
            }
            self::verbose($message);
        }
    }
}
