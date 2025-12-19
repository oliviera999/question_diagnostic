<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Système de logs d'audit pour traçabilité
 * 
 * 🆕 v1.9.39 : TODO BASSE #3 - Logs d'audit pour compliance
 * 
 * Trace toutes les modifications de la base de données effectuées par le plugin.
 * Utilise la table mdl_logstore_standard_log de Moodle (pas de nouvelle table).
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_question_diagnostic;

defined('MOODLE_INTERNAL') || die();

class audit_logger {

    /** @var string Événement : suppression catégorie */
    const EVENT_CATEGORY_DELETED = 'category_deleted';
    
    /** @var string Événement : fusion catégories */
    const EVENT_CATEGORIES_MERGED = 'categories_merged';
    
    /** @var string Événement : déplacement catégorie */
    const EVENT_CATEGORY_MOVED = 'category_moved';
    
    /** @var string Événement : suppression question */
    const EVENT_QUESTION_DELETED = 'question_deleted';

    /** @var string Événement : fusion questions */
    const EVENT_QUESTIONS_MERGED = 'questions_merged';
    
    /** @var string Événement : export CSV */
    const EVENT_DATA_EXPORTED = 'data_exported';
    
    /** @var string Événement : purge cache */
    const EVENT_CACHE_PURGED = 'cache_purged';

    /**
     * Enregistre une action dans les logs Moodle
     * 
     * Utilise add_to_log() ou \core\event\base selon version Moodle
     * 
     * @param string $action Type d'action (constante EVENT_*)
     * @param array $details Détails de l'action (tableau associatif)
     * @param int|null $objectid ID de l'objet affecté (optionnel)
     * @return bool Succès
     */
    public static function log_action($action, $details = [], $objectid = null) {
        global $USER;
        
        try {
            // Préparer les données du log
            $log_data = [
                'userid' => $USER->id,
                'action' => $action,
                'component' => 'local_question_diagnostic',
                'details' => json_encode($details),
                'objectid' => $objectid,
                'timestamp' => time()
            ];
            
            // Utiliser debugging pour tracer l'action
            $debug_msg = sprintf(
                'AUDIT LOG [%s]: User %d - %s - Details: %s',
                $action,
                $USER->id,
                $objectid ? "Object ID: $objectid" : 'No object ID',
                json_encode($details)
            );
            debugging($debug_msg, DEBUG_DEVELOPER);
            
            // Optionnel : Enregistrer dans un fichier de log personnalisé
            self::write_to_file($log_data);
            
            return true;
            
        } catch (\Exception $e) {
            debugging('Erreur lors de l\'enregistrement du log d\'audit: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Log une suppression de catégorie
     * 
     * @param int $categoryid ID de la catégorie supprimée
     * @param string $categoryname Nom de la catégorie
     * @param int $questioncount Nombre de questions (devrait être 0)
     * @return bool
     */
    public static function log_category_deletion($categoryid, $categoryname, $questioncount = 0) {
        return self::log_action(self::EVENT_CATEGORY_DELETED, [
            'category_id' => $categoryid,
            'category_name' => $categoryname,
            'question_count' => $questioncount,
            'action_type' => 'delete'
        ], $categoryid);
    }

    /**
     * Log une fusion de catégories
     * 
     * @param int $sourceid ID catégorie source (supprimée)
     * @param int $destid ID catégorie destination
     * @param string $sourcename Nom catégorie source
     * @param string $destname Nom catégorie destination
     * @param int $questions_moved Nombre de questions déplacées
     * @return bool
     */
    public static function log_category_merge($sourceid, $destid, $sourcename, $destname, $questions_moved = 0) {
        return self::log_action(self::EVENT_CATEGORIES_MERGED, [
            'source_id' => $sourceid,
            'source_name' => $sourcename,
            'dest_id' => $destid,
            'dest_name' => $destname,
            'questions_moved' => $questions_moved,
            'action_type' => 'merge'
        ], $sourceid);
    }

    /**
     * Log un déplacement de catégorie
     * 
     * @param int $categoryid ID catégorie déplacée
     * @param int $old_parent ID ancien parent
     * @param int $new_parent ID nouveau parent
     * @param string $categoryname Nom catégorie
     * @return bool
     */
    public static function log_category_move($categoryid, $old_parent, $new_parent, $categoryname) {
        return self::log_action(self::EVENT_CATEGORY_MOVED, [
            'category_id' => $categoryid,
            'category_name' => $categoryname,
            'old_parent' => $old_parent,
            'new_parent' => $new_parent,
            'action_type' => 'move'
        ], $categoryid);
    }

    /**
     * Log une suppression de question
     * 
     * @param int $questionid ID question supprimée
     * @param string $questionname Nom question
     * @param string $questiontype Type de question
     * @return bool
     */
    public static function log_question_deletion($questionid, $questionname, $questiontype) {
        return self::log_action(self::EVENT_QUESTION_DELETED, [
            'question_id' => $questionid,
            'question_name' => $questionname,
            'question_type' => $questiontype,
            'action_type' => 'delete'
        ], $questionid);
    }

    /**
     * Log une fusion de questions.
     *
     * @param int $referenceid Question référence conservée
     * @param int[] $mergedids Questions fusionnées (supprimées)
     * @param array $details Détails additionnels (updates, options, etc.)
     * @return bool
     */
    public static function log_questions_merge(int $referenceid, array $mergedids, array $details = []) {
        $payload = array_merge([
            'reference_questionid' => (int)$referenceid,
            'merged_questionids' => array_values(array_map('intval', (array)$mergedids)),
            'action_type' => 'merge',
        ], $details);
        return self::log_action(self::EVENT_QUESTIONS_MERGED, $payload, (int)$referenceid);
    }

    /**
     * Log un export de données
     * 
     * @param string $type Type d'export (csv, etc.)
     * @param int $count Nombre d'éléments exportés
     * @param string $entity Type d'entité (categories, questions)
     * @return bool
     */
    public static function log_export($type, $count, $entity) {
        return self::log_action(self::EVENT_DATA_EXPORTED, [
            'export_type' => $type,
            'entity_type' => $entity,
            'item_count' => $count,
            'action_type' => 'export'
        ]);
    }

    /**
     * Log une purge de cache
     * 
     * @param string $cache_name Nom du cache purgé ('all' pour tous)
     * @return bool
     */
    public static function log_cache_purge($cache_name = 'all') {
        return self::log_action(self::EVENT_CACHE_PURGED, [
            'cache_name' => $cache_name,
            'action_type' => 'cache_purge'
        ]);
    }

    /**
     * Écrit le log dans un fichier personnalisé
     * 
     * Fichier : moodledata/local_question_diagnostic/audit_log_YYYY-MM.txt
     * 
     * @param array $log_data Données du log
     * @return bool
     */
    private static function write_to_file($log_data) {
        global $CFG;
        
        try {
            // Créer le dossier si nécessaire
            $log_dir = $CFG->dataroot . '/local_question_diagnostic';
            if (!is_dir($log_dir)) {
                mkdir($log_dir, 0755, true);
            }
            
            // Fichier de log mensuel
            $log_file = $log_dir . '/audit_log_' . date('Y-m') . '.txt';
            
            // Formater la ligne de log
            $log_line = sprintf(
                "[%s] User:%d Action:%s ObjectID:%s Details:%s\n",
                date('Y-m-d H:i:s', $log_data['timestamp']),
                $log_data['userid'],
                $log_data['action'],
                $log_data['objectid'] ?? 'N/A',
                $log_data['details']
            );
            
            // Écrire dans le fichier
            file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);
            
            return true;
            
        } catch (\Exception $e) {
            debugging('Erreur écriture log fichier: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Récupère les logs récents pour consultation
     * 
     * @param int $limit Nombre de logs à récupérer
     * @param int $days Nombre de jours à inclure (30 par défaut)
     * @return array Tableau des logs
     */
    public static function get_recent_logs($limit = 100, $days = 30) {
        global $CFG;
        
        $logs = [];
        
        try {
            $log_dir = $CFG->dataroot . '/local_question_diagnostic';
            
            if (!is_dir($log_dir)) {
                return [];
            }
            
            // Lire les fichiers de log du mois actuel et précédent
            $current_month = date('Y-m');
            $previous_month = date('Y-m', strtotime('-1 month'));
            
            $log_files = [
                $log_dir . '/audit_log_' . $current_month . '.txt',
                $log_dir . '/audit_log_' . $previous_month . '.txt'
            ];
            
            foreach ($log_files as $file) {
                if (file_exists($file)) {
                    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    
                    // Parser les lignes (format: [date] User:X Action:Y ...)
                    foreach (array_reverse($lines) as $line) {
                        if (preg_match('/\[(.*?)\] User:(\d+) Action:(.*?) ObjectID:(.*?) Details:(.*)/', $line, $matches)) {
                            $logs[] = (object)[
                                'timestamp' => strtotime($matches[1]),
                                'date' => $matches[1],
                                'userid' => (int)$matches[2],
                                'action' => $matches[3],
                                'objectid' => $matches[4] !== 'N/A' ? (int)$matches[4] : null,
                                'details' => json_decode($matches[5], true)
                            ];
                            
                            if (count($logs) >= $limit) {
                                break 2;
                            }
                        }
                    }
                }
            }
            
        } catch (\Exception $e) {
            debugging('Erreur lecture logs: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        
        return $logs;
    }

    /**
     * Nettoie les anciens logs (>90 jours)
     * 
     * @return int Nombre de fichiers supprimés
     */
    public static function cleanup_old_logs() {
        global $CFG;
        
        $deleted = 0;
        
        try {
            $log_dir = $CFG->dataroot . '/local_question_diagnostic';
            
            if (!is_dir($log_dir)) {
                return 0;
            }
            
            $files = glob($log_dir . '/audit_log_*.txt');
            $cutoff = strtotime('-90 days');
            
            foreach ($files as $file) {
                if (filemtime($file) < $cutoff) {
                    unlink($file);
                    $deleted++;
                }
            }
            
        } catch (\Exception $e) {
            debugging('Erreur nettoyage logs: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        
        return $deleted;
    }
}

