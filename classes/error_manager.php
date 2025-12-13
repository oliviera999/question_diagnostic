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
 * Gestionnaire centralisé des erreurs pour le plugin
 * 
 * 🔧 Phase 1 : Stabilisation - Centralisation de la gestion des erreurs
 * 
 * Cette classe centralise la gestion des erreurs pour :
 * - Standardiser les messages d'erreur
 * - Fournir des codes d'erreur cohérents
 * - Faciliter l'internationalisation
 * - Améliorer la traçabilité des erreurs
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class error_manager {

    /**
     * Codes d'erreur standardisés
     */
    const CATEGORY_NOT_FOUND = 'CATEGORY_NOT_FOUND';
    const QUESTION_NOT_FOUND = 'QUESTION_NOT_FOUND';
    const INVALID_CONTEXT = 'INVALID_CONTEXT';
    const DATABASE_ERROR = 'DATABASE_ERROR';
    const PERMISSION_DENIED = 'PERMISSION_DENIED';
    const VALIDATION_ERROR = 'VALIDATION_ERROR';
    const TRANSACTION_FAILED = 'TRANSACTION_FAILED';
    const CACHE_ERROR = 'CACHE_ERROR';
    const FILE_NOT_FOUND = 'FILE_NOT_FOUND';
    const CONFIGURATION_ERROR = 'CONFIGURATION_ERROR';
    const NETWORK_ERROR = 'NETWORK_ERROR';
    const TIMEOUT_ERROR = 'TIMEOUT_ERROR';

    /**
     * Niveaux de sévérité des erreurs
     */
    const SEVERITY_LOW = 'low';
    const SEVERITY_MEDIUM = 'medium';
    const SEVERITY_HIGH = 'high';
    const SEVERITY_CRITICAL = 'critical';

    /**
     * Mapping des codes d'erreur vers les messages et sévérités
     * @var array
     */
    private static $error_definitions = [
        self::CATEGORY_NOT_FOUND => [
            'message' => 'Category not found: {id}',
            'severity' => self::SEVERITY_MEDIUM,
            'user_message' => 'La catégorie demandée n\'existe pas ou a été supprimée.'
        ],
        self::QUESTION_NOT_FOUND => [
            'message' => 'Question not found: {id}',
            'severity' => self::SEVERITY_MEDIUM,
            'user_message' => 'La question demandée n\'existe pas ou a été supprimée.'
        ],
        self::INVALID_CONTEXT => [
            'message' => 'Invalid context: {context_id}',
            'severity' => self::SEVERITY_HIGH,
            'user_message' => 'Le contexte spécifié n\'est pas valide.'
        ],
        self::DATABASE_ERROR => [
            'message' => 'Database error: {message}',
            'severity' => self::SEVERITY_CRITICAL,
            'user_message' => 'Une erreur de base de données s\'est produite. Veuillez réessayer.'
        ],
        self::PERMISSION_DENIED => [
            'message' => 'Permission denied: {action}',
            'severity' => self::SEVERITY_HIGH,
            'user_message' => 'Vous n\'avez pas les permissions nécessaires pour effectuer cette action.'
        ],
        self::VALIDATION_ERROR => [
            'message' => 'Validation error: {field} - {message}',
            'severity' => self::SEVERITY_MEDIUM,
            'user_message' => 'Les données fournies ne sont pas valides.'
        ],
        self::TRANSACTION_FAILED => [
            'message' => 'Transaction failed: {operation}',
            'severity' => self::SEVERITY_CRITICAL,
            'user_message' => 'L\'opération a échoué. Les modifications ont été annulées.'
        ],
        self::CACHE_ERROR => [
            'message' => 'Cache error: {operation}',
            'severity' => self::SEVERITY_LOW,
            'user_message' => 'Erreur de cache. Les données peuvent être temporairement indisponibles.'
        ],
        self::FILE_NOT_FOUND => [
            'message' => 'File not found: {filepath}',
            'severity' => self::SEVERITY_MEDIUM,
            'user_message' => 'Le fichier demandé n\'a pas été trouvé.'
        ],
        self::CONFIGURATION_ERROR => [
            'message' => 'Configuration error: {setting}',
            'severity' => self::SEVERITY_HIGH,
            'user_message' => 'Erreur de configuration. Contactez l\'administrateur.'
        ],
        self::NETWORK_ERROR => [
            'message' => 'Network error: {message}',
            'severity' => self::SEVERITY_MEDIUM,
            'user_message' => 'Erreur de connexion réseau.'
        ],
        self::TIMEOUT_ERROR => [
            'message' => 'Timeout error: {operation}',
            'severity' => self::SEVERITY_MEDIUM,
            'user_message' => 'L\'opération a pris trop de temps et a été annulée.'
        ]
    ];

    /**
     * Historique des erreurs pour cette session
     * @var array
     */
    private static $error_history = [];

    /**
     * Créer une erreur standardisée
     * 
     * @param string $code Code d'erreur (constante)
     * @param array $params Paramètres pour le message
     * @param Exception|null $exception Exception originale (optionnelle)
     * @return array Données de l'erreur
     */
    public static function create_error($code, $params = [], $exception = null) {
        if (!isset(self::$error_definitions[$code])) {
            $code = self::DATABASE_ERROR;
            $params = ['message' => 'Unknown error code: ' . $code];
        }

        $definition = self::$error_definitions[$code];
        
        // Construire le message technique
        $technical_message = self::interpolate_message($definition['message'], $params);
        
        // Construire le message utilisateur
        $user_message = $definition['user_message'];
        if (!empty($params['user_message'])) {
            $user_message = $params['user_message'];
        }

        $error = [
            'code' => $code,
            'severity' => $definition['severity'],
            'technical_message' => $technical_message,
            'user_message' => $user_message,
            'params' => $params,
            'timestamp' => time(),
            'exception' => $exception ? [
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ] : null
        ];

        // Ajouter à l'historique
        self::$error_history[] = $error;

        // Logger l'erreur
        self::log_error($error);

        return $error;
    }

    /**
     * Créer une erreur de catégorie non trouvée
     * 
     * @param int $category_id ID de la catégorie
     * @return array Données de l'erreur
     */
    public static function category_not_found($category_id) {
        return self::create_error(self::CATEGORY_NOT_FOUND, ['id' => $category_id]);
    }

    /**
     * Créer une erreur de question non trouvée
     * 
     * @param int $question_id ID de la question
     * @return array Données de l'erreur
     */
    public static function question_not_found($question_id) {
        return self::create_error(self::QUESTION_NOT_FOUND, ['id' => $question_id]);
    }

    /**
     * Créer une erreur de contexte invalide
     * 
     * @param int $context_id ID du contexte
     * @return array Données de l'erreur
     */
    public static function invalid_context($context_id) {
        return self::create_error(self::INVALID_CONTEXT, ['context_id' => $context_id]);
    }

    /**
     * Créer une erreur de base de données
     * 
     * @param string $message Message d'erreur SQL
     * @param Exception|null $exception Exception originale
     * @return array Données de l'erreur
     */
    public static function database_error($message, $exception = null) {
        return self::create_error(self::DATABASE_ERROR, ['message' => $message], $exception);
    }

    /**
     * Créer une erreur de permission
     * 
     * @param string $action Action refusée
     * @return array Données de l'erreur
     */
    public static function permission_denied($action) {
        return self::create_error(self::PERMISSION_DENIED, ['action' => $action]);
    }

    /**
     * Créer une erreur de validation
     * 
     * @param string $field Champ en erreur
     * @param string $message Message de validation
     * @return array Données de l'erreur
     */
    public static function validation_error($field, $message) {
        return self::create_error(self::VALIDATION_ERROR, ['field' => $field, 'message' => $message]);
    }

    /**
     * Créer une erreur de transaction
     * 
     * @param string $operation Opération qui a échoué
     * @param Exception|null $exception Exception originale
     * @return array Données de l'erreur
     */
    public static function transaction_failed($operation, $exception = null) {
        return self::create_error(self::TRANSACTION_FAILED, ['operation' => $operation], $exception);
    }

    /**
     * Créer une erreur de cache
     * 
     * @param string $operation Opération de cache
     * @return array Données de l'erreur
     */
    public static function cache_error($operation) {
        return self::create_error(self::CACHE_ERROR, ['operation' => $operation]);
    }

    /**
     * Interpoler un message avec des paramètres
     * 
     * @param string $message Message avec placeholders {key}
     * @param array $params Paramètres à interpoler
     * @return string Message interpolé
     */
    private static function interpolate_message($message, $params) {
        foreach ($params as $key => $value) {
            $message = str_replace('{' . $key . '}', $value, $message);
        }
        return $message;
    }

    /**
     * Logger une erreur
     * 
     * @param array $error Données de l'erreur
     */
    private static function log_error($error) {
        // Utiliser le debug_manager si disponible
        if (class_exists('\local_question_diagnostic\debug_manager')) {
            debug_manager::set_context('error_manager');
            
            switch ($error['severity']) {
                case self::SEVERITY_CRITICAL:
                    debug_manager::error($error['technical_message'], $error['params']);
                    break;
                case self::SEVERITY_HIGH:
                    debug_manager::warning($error['technical_message'], $error['params']);
                    break;
                default:
                    debug_manager::info($error['technical_message'], $error['params']);
                    break;
            }
        } else {
            // Fallback vers l'API Moodle
            debugging("[QD:ERROR] {$error['technical_message']}", DEBUG_DEVELOPER);
        }

        // Logger l'exception si présente
        if (!empty($error['exception'])) {
            $exception_info = $error['exception'];
            debugging("[QD:EXCEPTION] {$exception_info['type']}: {$exception_info['message']} in {$exception_info['file']}:{$exception_info['line']}", DEBUG_DEVELOPER);
        }
    }

    /**
     * Obtenir l'historique des erreurs
     * 
     * @param string|null $severity Filtrer par sévérité (optionnel)
     * @return array Historique des erreurs
     */
    public static function get_error_history($severity = null) {
        if ($severity === null) {
            return self::$error_history;
        }
        
        return array_filter(self::$error_history, function($error) use ($severity) {
            return $error['severity'] === $severity;
        });
    }

    /**
     * Obtenir les statistiques des erreurs
     * 
     * @return array Statistiques
     */
    public static function get_error_stats() {
        $stats = [
            'total_errors' => count(self::$error_history),
            'by_severity' => [],
            'by_code' => [],
            'recent_errors' => array_slice(self::$error_history, -10) // 10 dernières erreurs
        ];

        // Compter par sévérité
        foreach (self::$error_history as $error) {
            $severity = $error['severity'];
            $code = $error['code'];
            
            $stats['by_severity'][$severity] = ($stats['by_severity'][$severity] ?? 0) + 1;
            $stats['by_code'][$code] = ($stats['by_code'][$code] ?? 0) + 1;
        }

        return $stats;
    }

    /**
     * Réinitialiser l'historique des erreurs
     */
    public static function reset_history() {
        self::$error_history = [];
    }

    /**
     * Vérifier si une erreur est critique
     * 
     * @param array $error Données de l'erreur
     * @return bool True si critique
     */
    public static function is_critical($error) {
        return $error['severity'] === self::SEVERITY_CRITICAL;
    }

    /**
     * Obtenir le message utilisateur pour une erreur
     * 
     * @param array $error Données de l'erreur
     * @return string Message utilisateur
     */
    public static function get_user_message($error) {
        return $error['user_message'] ?? 'Une erreur inattendue s\'est produite.';
    }

    /**
     * Obtenir le message technique pour une erreur
     * 
     * @param array $error Données de l'erreur
     * @return string Message technique
     */
    public static function get_technical_message($error) {
        return $error['technical_message'] ?? 'Unknown error';
    }

    /**
     * Créer une réponse d'erreur standardisée pour les APIs
     * 
     * @param string $code Code d'erreur
     * @param array $params Paramètres
     * @param Exception|null $exception Exception originale
     * @return array Réponse standardisée
     */
    public static function create_api_error_response($code, $params = [], $exception = null) {
        $error = self::create_error($code, $params, $exception);
        
        return [
            'success' => false,
            'error' => [
                'code' => $error['code'],
                'message' => $error['user_message'],
                'severity' => $error['severity']
            ],
            'timestamp' => $error['timestamp']
        ];
    }

    /**
     * Créer une réponse de succès standardisée pour les APIs
     * 
     * @param mixed $data Données de la réponse
     * @param string $message Message de succès (optionnel)
     * @return array Réponse standardisée
     */
    public static function create_api_success_response($data = null, $message = '') {
        return [
            'success' => true,
            'data' => $data,
            'message' => $message,
            'timestamp' => time()
        ];
    }
}
