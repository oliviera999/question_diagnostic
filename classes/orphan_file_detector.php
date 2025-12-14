<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_question_diagnostic;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

/**
 * Détecteur de fichiers orphelins dans Moodle
 *
 * Cette classe permet de détecter les fichiers orphelins dans la base de données
 * et dans le système de fichiers (moodledata/filedir/).
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class orphan_file_detector {

    /**
     * Composants supportés pour la vérification des fichiers orphelins
     * Format: component => [table_parent, id_field]
     */
    const SUPPORTED_COMPONENTS = [
        'question' => ['question', 'id'],
        'mod_label' => ['label', 'id'],
        'mod_resource' => ['resource', 'id'],
        'mod_page' => ['page', 'id'],
        'mod_forum' => ['forum', 'id'],
        'mod_book' => ['book', 'id'],
        'course' => ['course', 'id'],
        'user' => ['user', 'id'],
    ];

    /**
     * Récupère tous les fichiers orphelins (BDD uniquement)
     *
     * @param bool $use_cache Utiliser le cache
     * @param int $limit Limite de résultats (0 = tous)
     * @return array Tableau d'objets orphelin
     */
    public static function get_orphan_files($use_cache = true, $limit = 1000) {
        global $DB;

        // Essayer le cache d'abord
        require_once(__DIR__ . '/cache_manager.php');
        $cache_key = 'orphan_files_list_' . $limit;
        if ($use_cache) {
            $cached = cache_manager::get(cache_manager::CACHE_ORPHANFILES, $cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }

        $orphans = [];

        // Type 1: Fichiers avec contexte invalide
        $orphans = array_merge($orphans, self::get_orphans_invalid_context($limit));

        // Type 2: Fichiers avec parent supprimé (par composant)
        foreach (self::SUPPORTED_COMPONENTS as $component => $config) {
            $component_orphans = self::get_orphans_missing_parent($component, $config, $limit);
            $orphans = array_merge($orphans, $component_orphans);
        }

        // Limiter le nombre total si nécessaire
        if ($limit > 0 && count($orphans) > $limit) {
            $orphans = array_slice($orphans, 0, $limit);
        }

        // Mettre en cache pour 1 heure
        if ($use_cache) {
            cache_manager::set(cache_manager::CACHE_ORPHANFILES, $cache_key, $orphans);
        }

        return $orphans;
    }

    /**
     * Détecte les fichiers dont le contexte n'existe plus
     *
     * @param int $limit Limite de résultats
     * @return array Tableau de fichiers orphelins
     */
    private static function get_orphans_invalid_context($limit = 0) {
        global $DB;

        $sql = "SELECT f.* 
                FROM {files} f
                LEFT JOIN {context} c ON c.id = f.contextid
                WHERE c.id IS NULL 
                  AND f.filename != '.'
                  AND f.component != ''
                ORDER BY f.timecreated DESC";

        if ($limit > 0) {
            $files = $DB->get_records_sql($sql, [], 0, $limit);
        } else {
            $files = $DB->get_records_sql($sql);
        }

        $orphans = [];
        foreach ($files as $file) {
            $orphans[] = self::create_orphan_object($file, 'context_invalid', 'Contexte invalide (ID: ' . $file->contextid . ')');
        }

        return $orphans;
    }

    /**
     * Détecte les fichiers dont l'élément parent a été supprimé
     *
     * @param string $component Composant à vérifier
     * @param array $config Configuration [table, id_field]
     * @param int $limit Limite de résultats
     * @return array Tableau de fichiers orphelins
     */
    private static function get_orphans_missing_parent($component, $config, $limit = 0) {
        global $DB;

        list($table, $idfield) = $config;

        // Vérifier que la table existe
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists($table)) {
            return [];
        }

        $sql = "SELECT f.* 
                FROM {files} f
                WHERE f.component = :component
                  AND f.filename != '.'
                  AND f.itemid != 0
                  AND NOT EXISTS (
                      SELECT 1 FROM {{$table}} t 
                      WHERE t.{$idfield} = f.itemid
                  )
                ORDER BY f.timecreated DESC";

        if ($limit > 0) {
            $files = $DB->get_records_sql($sql, ['component' => $component], 0, $limit);
        } else {
            $files = $DB->get_records_sql($sql, ['component' => $component]);
        }

        $orphans = [];
        foreach ($files as $file) {
            $reason = 'Élément parent supprimé (' . $component . ' ID: ' . $file->itemid . ')';
            $orphans[] = self::create_orphan_object($file, 'parent_deleted', $reason);
        }

        return $orphans;
    }

    /**
     * Crée un objet orphelin avec métadonnées enrichies
     *
     * @param object $file Objet fichier depuis mdl_files
     * @param string $orphan_type Type d'orphelin (context_invalid, parent_deleted, unreferenced)
     * @param string $reason Raison détaillée
     * @return object Objet orphelin enrichi
     */
    private static function create_orphan_object($file, $orphan_type, $reason) {
        return (object)[
            'file' => $file,
            'orphan_type' => $orphan_type,
            'reason' => $reason,
            'filesize_formatted' => self::format_filesize($file->filesize),
            'timecreated_formatted' => userdate($file->timecreated, get_string('strftimedatetime', 'langconfig')),
            'age_days' => floor((time() - $file->timecreated) / (60 * 60 * 24)),
        ];
    }

    /**
     * Obtient les statistiques globales sur les fichiers orphelins
     *
     * @param bool $use_cache Utiliser le cache
     * @return object Statistiques
     */
    public static function get_global_stats($use_cache = true) {
        global $DB;

        // Essayer le cache d'abord
        require_once(__DIR__ . '/cache_manager.php');
        if ($use_cache) {
            $cached_stats = cache_manager::get(cache_manager::CACHE_ORPHANFILES, 'global_stats');
            if ($cached_stats !== false) {
                return $cached_stats;
            }
        }

        $stats = new \stdClass();
        
        // Récupérer tous les orphelins
        $orphans = self::get_orphan_files($use_cache);
        $stats->total_orphans = count($orphans);

        // Calculer l'espace disque total
        $stats->total_filesize = 0;
        $stats->by_component = [];
        $stats->by_type = [];
        $stats->by_age = ['recent' => 0, 'medium' => 0, 'old' => 0]; // <1 mois, 1-6 mois, >6 mois

        foreach ($orphans as $orphan) {
            $file = $orphan->file;
            
            // Taille totale
            $stats->total_filesize += $file->filesize;

            // Par composant
            if (!isset($stats->by_component[$file->component])) {
                $stats->by_component[$file->component] = 0;
            }
            $stats->by_component[$file->component]++;

            // Par type d'orphelin
            if (!isset($stats->by_type[$orphan->orphan_type])) {
                $stats->by_type[$orphan->orphan_type] = 0;
            }
            $stats->by_type[$orphan->orphan_type]++;

            // Par âge
            if ($orphan->age_days < 30) {
                $stats->by_age['recent']++;
            } else if ($orphan->age_days < 180) {
                $stats->by_age['medium']++;
            } else {
                $stats->by_age['old']++;
            }
        }

        $stats->total_filesize_formatted = self::format_filesize($stats->total_filesize);

        // Mettre en cache pour 30 minutes
        if ($use_cache) {
            cache_manager::set(cache_manager::CACHE_ORPHANFILES, 'global_stats', $stats);
        }

        return $stats;
    }

    /**
     * Vérifie si un fichier est sûr à supprimer
     *
     * @param object $file Objet fichier
     * @return bool True si sûr à supprimer
     */
    public static function is_safe_to_delete($file) {
        global $DB;

        // 1. Vérifier que ce n'est pas un fichier système important
        if (self::is_system_file($file)) {
            return false;
        }

        // 2. Vérifier le contexte
        $context_exists = $DB->record_exists('context', ['id' => $file->contextid]);
        if (!$context_exists) {
            return true; // Contexte invalide → sûr à supprimer
        }

        // 3. Vérifier le parent selon le composant
        if (isset(self::SUPPORTED_COMPONENTS[$file->component])) {
            list($table, $idfield) = self::SUPPORTED_COMPONENTS[$file->component];
            
            $dbman = $DB->get_manager();
            if ($dbman->table_exists($table)) {
                $parent_exists = $DB->record_exists($table, [$idfield => $file->itemid]);
                if (!$parent_exists) {
                    return true; // Parent n'existe pas → sûr à supprimer
                }
            }
        }

        // 4. Si le fichier a un parent existant, ne pas supprimer
        return false;
    }

    /**
     * Vérifie si un fichier est un fichier système (à ne jamais supprimer)
     *
     * @param object $file Objet fichier
     * @return bool True si fichier système
     */
    private static function is_system_file($file) {
        // Fichiers avec component vide ou '.'
        if (empty($file->component) || $file->filename === '.') {
            return true;
        }

        // Composants système critiques
        $system_components = ['core', 'theme', 'block', 'backup'];
        foreach ($system_components as $comp) {
            if (strpos($file->component, $comp) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Supprime un fichier orphelin de manière sécurisée
     *
     * @param int $fileid ID du fichier
     * @param bool $dry_run Mode simulation (ne supprime pas réellement)
     * @return array ['success' => bool, 'message' => string]
     */
    public static function delete_orphan_file($fileid, $dry_run = false) {
        global $DB;

        try {
            // Récupérer le fichier
            $file_record = $DB->get_record('files', ['id' => $fileid]);
            if (!$file_record) {
                return ['success' => false, 'message' => 'Fichier introuvable (ID: ' . $fileid . ')'];
            }

            // Vérifier la sécurité
            if (!self::is_safe_to_delete($file_record)) {
                return ['success' => false, 'message' => 'Fichier non sûr à supprimer (parent existe ou fichier système)'];
            }

            // Mode dry-run : simuler seulement
            if ($dry_run) {
                return [
                    'success' => true, 
                    'message' => '[DRY-RUN] Fichier SERAIT supprimé : ' . $file_record->filename
                ];
            }

            // Supprimer via l'API File Storage
            $fs = get_file_storage();
            $file = $fs->get_file_by_id($fileid);

            if ($file) {
                $filename = $file->get_filename();
                $filesize = $file->get_filesize();
                
                // Supprimer le fichier (BDD + filesystem)
                $file->delete();

                // Logger l'action
                self::log_operation('delete', $fileid, $filename, $filesize);

                return [
                    'success' => true,
                    'message' => 'Fichier supprimé avec succès : ' . $filename . ' (' . self::format_filesize($filesize) . ')'
                ];
            }

            return ['success' => false, 'message' => 'Impossible de récupérer le fichier via l\'API'];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
        }
    }

    /**
     * Supprime plusieurs fichiers orphelins
     *
     * @param array $fileids Tableau d'IDs de fichiers
     * @param bool $dry_run Mode simulation
     * @return array ['success' => int, 'failed' => int, 'messages' => array]
     */
    public static function delete_multiple_orphans($fileids, $dry_run = false) {
        $results = ['success' => 0, 'failed' => 0, 'messages' => []];

        // Limiter à 100 fichiers par lot pour sécurité
        if (count($fileids) > 100) {
            $fileids = array_slice($fileids, 0, 100);
            $results['messages'][] = '⚠️ Limité à 100 fichiers pour sécurité';
        }

        foreach ($fileids as $fileid) {
            $result = self::delete_orphan_file($fileid, $dry_run);
            
            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
            
            $results['messages'][] = $result['message'];
        }

        return $results;
    }

    /**
     * Archive un fichier orphelin dans un dossier temporaire
     *
     * @param int $fileid ID du fichier
     * @return array ['success' => bool, 'message' => string, 'archive_path' => string]
     */
    public static function archive_orphan_file($fileid) {
        global $DB, $CFG;

        try {
            // Récupérer le fichier
            $fs = get_file_storage();
            $file = $fs->get_file_by_id($fileid);

            if (!$file) {
                return ['success' => false, 'message' => 'Fichier introuvable'];
            }

            // Créer le dossier d'archive si nécessaire
            $archive_base = $CFG->dataroot . '/temp/orphan_archive';
            $archive_date_dir = $archive_base . '/' . date('Y-m-d');
            
            if (!is_dir($archive_date_dir)) {
                mkdir($archive_date_dir, 0755, true);
            }

            // Copier le fichier dans l'archive
            $contenthash = $file->get_contenthash();
            $hash_dir = $archive_date_dir . '/' . substr($contenthash, 0, 2);
            
            if (!is_dir($hash_dir)) {
                mkdir($hash_dir, 0755, true);
            }

            $archive_path = $hash_dir . '/' . $contenthash;
            
            // Copier le contenu
            $content = $file->get_content();
            file_put_contents($archive_path, $content);

            // Sauvegarder les métadonnées
            self::save_archive_metadata($file, $archive_path);

            // Logger l'action
            self::log_operation('archive', $fileid, $file->get_filename(), $file->get_filesize());

            return [
                'success' => true,
                'message' => 'Fichier archivé avec succès',
                'archive_path' => $archive_path
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
        }
    }

    /**
     * Sauvegarde les métadonnées d'un fichier archivé
     *
     * @param object $file stored_file object
     * @param string $archive_path Chemin d'archive
     */
    private static function save_archive_metadata($file, $archive_path) {
        global $CFG, $USER;

        $metadata_file = dirname($archive_path) . '/metadata.json';
        
        // Charger les métadonnées existantes ou créer nouveau
        $metadata = [];
        if (file_exists($metadata_file)) {
            $metadata = json_decode(file_get_contents($metadata_file), true);
        }

        // Ajouter ce fichier
        $metadata[] = [
            'id' => $file->get_id(),
            'filename' => $file->get_filename(),
            'contenthash' => $file->get_contenthash(),
            'component' => $file->get_component(),
            'filearea' => $file->get_filearea(),
            'itemid' => $file->get_itemid(),
            'filesize' => $file->get_filesize(),
            'contextid' => $file->get_contextid(),
            'archived_at' => date('Y-m-d H:i:s'),
            'archived_by' => $USER->id,
            'archive_path' => $archive_path
        ];

        // Sauvegarder
        file_put_contents($metadata_file, json_encode($metadata, JSON_PRETTY_PRINT));
    }

    /**
     * Logger une opération sur fichier orphelin
     *
     * @param string $action Action effectuée (delete, archive)
     * @param int $fileid ID du fichier
     * @param string $filename Nom du fichier
     * @param int $filesize Taille du fichier
     */
    private static function log_operation($action, $fileid, $filename, $filesize) {
        global $USER;

        $log_message = sprintf(
            '[ORPHAN_FILE] Action: %s | File ID: %d | Filename: %s | Size: %s | User: %s (%d) | Time: %s',
            strtoupper($action),
            $fileid,
            $filename,
            self::format_filesize($filesize),
            fullname($USER),
            $USER->id,
            date('Y-m-d H:i:s')
        );

        // Logger dans les logs Moodle
        \core\event\base::create([
            'context' => \context_system::instance(),
            'other' => ['message' => $log_message]
        ]);

        // Logger aussi dans un fichier dédié
        debugging($log_message, DEBUG_NORMAL);
    }

    /**
     * Formate une taille de fichier de manière lisible
     *
     * @param int $bytes Taille en bytes
     * @return string Taille formatée
     */
    public static function format_filesize($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } else if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } else if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }

    /**
     * Purge le cache des fichiers orphelins
     *
     * @return bool Succès de l'opération
     */
    public static function purge_orphan_cache() {
        require_once(__DIR__ . '/cache_manager.php');
        return cache_manager::purge_cache(cache_manager::CACHE_ORPHANFILES);
    }

    /**
     * Export des fichiers orphelins en CSV
     *
     * @param array $orphans Tableau de fichiers orphelins
     * @return string Contenu CSV
     */
    public static function export_to_csv($orphans) {
        $csv = "\xEF\xBB\xBF"; // UTF-8 BOM pour Excel
        
        // En-têtes
        $headers = [
            'ID',
            'Filename',
            'Component',
            'Filearea',
            'Item ID',
            'Filesize',
            'Orphan Type',
            'Reason',
            'Created',
            'Age (days)',
            'Context ID'
        ];
        $csv .= '"' . implode('","', $headers) . '"' . "\n";

        // Données
        foreach ($orphans as $orphan) {
            $file = $orphan->file;
            $row = [
                $file->id,
                $file->filename,
                $file->component,
                $file->filearea,
                $file->itemid,
                $orphan->filesize_formatted,
                $orphan->orphan_type,
                $orphan->reason,
                $orphan->timecreated_formatted,
                $orphan->age_days,
                $file->contextid
            ];
            $csv .= '"' . implode('","', array_map(function($val) {
                return str_replace('"', '""', $val);
            }, $row)) . '"' . "\n";
        }

        return $csv;
    }
}

