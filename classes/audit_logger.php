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
        try {
            // v1.15.0 : événement Moodle (journal standard) au lieu d'un fichier dans dataroot.
            $details = json_decode(json_encode($details), true);
            $event = \local_question_diagnostic\event\operation_performed::create([
                'context' => \context_system::instance(),
                'objectid' => null,
                'other' => [
                    'action' => (string)$action,
                    'objectid' => $objectid === null ? null : (int)$objectid,
                    'details' => is_array($details) ? $details : [],
                ],
            ]);
            $event->trigger();
            return true;
        } catch (\Throwable $e) {
            debugging('audit_logger::log_action : ' . $e->getMessage(), DEBUG_DEVELOPER);
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
     * Opérations récentes, lues dans le journal standard de Moodle.
     *
     * @param int $limit Nombre maximal d'entrées
     * @param int $days Nombre de jours à inclure
     * @return array
     */
    public static function get_recent_logs($limit = 100, $days = 30) {
        global $DB;
        $logs = [];
        try {
            if (!$DB->get_manager()->table_exists('logstore_standard_log')) {
                return [];
            }
            $records = $DB->get_records_select('logstore_standard_log',
                'eventname = :eventname AND timecreated >= :since',
                [
                    'eventname' => '\\local_question_diagnostic\\event\\operation_performed',
                    'since' => time() - (int)$days * DAYSECS,
                ], 'id DESC', 'id, userid, timecreated, other', 0, (int)$limit);
            foreach ($records as $r) {
                $other = json_decode((string)$r->other, true);
                if (!is_array($other)) {
                    $other = [];
                }
                $logs[] = (object)[
                    'timestamp' => (int)$r->timecreated,
                    'date' => userdate((int)$r->timecreated, get_string('strftimedatetimeshort', 'core_langconfig')),
                    'userid' => (int)$r->userid,
                    'action' => (string)($other['action'] ?? ''),
                    'objectid' => $other['objectid'] ?? null,
                    'details' => is_array($other['details'] ?? null) ? $other['details'] : [],
                ];
            }
        } catch (\Throwable $e) {
            debugging('audit_logger::get_recent_logs : ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        return $logs;
    }

    /**
     * Supprime les fichiers écrits par le plugin dans dataroot au-delà de la durée de conservation : anciens journaux
     * (format antérieur à 1.15.0), exports XML avant suppression, archives de fichiers orphelins.
     *
     * @param int|null $days durée de conservation (réglage retentiondays par défaut)
     * @return int nombre de fichiers supprimés
     */
    public static function cleanup_old_logs(?int $days = null) {
        global $CFG;
        if ($days === null) {
            $days = (int)get_config('local_question_diagnostic', 'retentiondays');
        }
        if ($days <= 0) {
            return 0;
        }
        $root = $CFG->dataroot . '/local_question_diagnostic';
        if (!is_dir($root)) {
            return 0;
        }
        $cutoff = time() - $days * DAYSECS;
        $deleted = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                // Dossier vidé : supprimé (sauf la racine).
                @rmdir($item->getPathname());
            } else if ($item->getMTime() < $cutoff && @unlink($item->getPathname())) {
                $deleted++;
            }
        }
        return $deleted;
    }
}
