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
require_once($CFG->libdir . '/questionlib.php');

/**
 * Réparateur intelligent de fichiers orphelins
 *
 * Cette classe analyse les fichiers orphelins et propose des réparations
 * automatiques sûres et fiables.
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class orphan_file_repairer {

    /**
     * Analyse les options de réparation pour un fichier orphelin
     *
     * @param object $orphan_file Fichier orphelin
     * @return array Options de réparation triées par confiance
     */
    public static function analyze_repair_options($orphan_file) {
        $options = [];
        
        // Option 1 : Réassociation par contenthash (haute fiabilité)
        $contenthash_match = self::find_by_contenthash($orphan_file);
        if ($contenthash_match) {
            $options[] = [
                'type' => 'contenthash',
                'confidence' => 95,
                'target' => $contenthash_match,
                'description' => get_string('repair_contenthash_desc', 'local_question_diagnostic'),
                'action' => 'reassociate',
                'icon' => '🟢'
            ];
        }
        
        // Option 2 : Réattribution par nom de fichier
        $name_matches = self::find_by_filename($orphan_file);
        if (!empty($name_matches)) {
            $confidence = count($name_matches) == 1 ? 80 : 65;
            $options[] = [
                'type' => 'filename',
                'confidence' => $confidence,
                'targets' => $name_matches,
                'description' => count($name_matches) . ' ' . get_string('repair_filename_candidates', 'local_question_diagnostic'),
                'action' => 'reassign',
                'icon' => count($name_matches) == 1 ? '🟡' : '🟠'
            ];
        }
        
        // Option 3 : Réassociation par contexte
        if (!empty($orphan_file->contextid)) {
            $context_matches = self::find_by_context($orphan_file);
            if (!empty($context_matches)) {
                $options[] = [
                    'type' => 'context',
                    'confidence' => 70,
                    'targets' => $context_matches,
                    'description' => get_string('repair_context_desc', 'local_question_diagnostic'),
                    'action' => 'reassign',
                    'icon' => '🟡'
                ];
            }
        }
        
        // Option 4 : Création question de récupération (toujours possible)
        $options[] = [
            'type' => 'recovery_stub',
            'confidence' => 100,
            'description' => get_string('repair_recovery_desc', 'local_question_diagnostic'),
            'action' => 'create_stub',
            'icon' => '🟢'
        ];
        
        // Trier par confiance décroissante
        usort($options, function($a, $b) {
            return $b['confidence'] - $a['confidence'];
        });
        
        return $options;
    }

    /**
     * Détermine le niveau de réparabilité d'un fichier
     *
     * @param object $orphan_file Fichier orphelin
     * @return string 'high', 'medium', 'low'
     */
    public static function get_repairability_level($orphan_file) {
        $options = self::analyze_repair_options($orphan_file);
        
        if (empty($options)) {
            return 'low';
        }
        
        $best_confidence = $options[0]['confidence'];
        
        if ($best_confidence >= 90) {
            return 'high';
        } else if ($best_confidence >= 60) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Cherche un fichier identique par contenthash
     *
     * @param object $orphan_file Fichier orphelin
     * @return object|null Fichier correspondant ou null
     */
    public static function find_by_contenthash($orphan_file) {
        global $DB;
        
        // Chercher un autre fichier avec le même contenthash mais avec parent valide
        $sql = "SELECT f.*, q.name as parent_name, q.id as parent_id, 'question' as parent_type
                FROM {files} f
                INNER JOIN {question} q ON f.component = 'question' 
                    AND f.itemid = q.id
                WHERE f.contenthash = :hash
                  AND f.id != :fileid
                  AND f.filename != '.'
                  AND f.component = 'question'
                LIMIT 1";
        
        $result = $DB->get_record_sql($sql, [
            'hash' => $orphan_file->contenthash,
            'fileid' => $orphan_file->id
        ]);
        
        if ($result) {
            return $result;
        }
        
        // Essayer avec d'autres composants (mod_label, etc.)
        $sql = "SELECT f.*, l.name as parent_name, l.id as parent_id, 'mod_label' as parent_type
                FROM {files} f
                INNER JOIN {label} l ON f.component = 'mod_label' 
                    AND f.itemid = l.id
                WHERE f.contenthash = :hash
                  AND f.id != :fileid
                  AND f.filename != '.'
                  AND f.component = 'mod_label'
                LIMIT 1";
        
        return $DB->get_record_sql($sql, [
            'hash' => $orphan_file->contenthash,
            'fileid' => $orphan_file->id
        ]);
    }

    /**
     * Cherche des questions/ressources contenant le nom du fichier dans leur HTML
     *
     * @param object $orphan_file Fichier orphelin
     * @return array Tableau de candidats
     */
    private static function find_by_filename($orphan_file) {
        global $DB;
        
        $filename = $orphan_file->filename;
        
        // Échapper pour LIKE
        $filename_like = '%' . $DB->sql_like_escape($filename) . '%';
        
        // Recherche dans les questions
        $sql = "SELECT id, name, questiontext,
                       CASE 
                           WHEN " . $DB->sql_like('questiontext', ':exact', false) . " THEN 100
                           WHEN " . $DB->sql_like('questiontext', ':partial', false) . " THEN 70
                           ELSE 50
                       END as match_score
                FROM {question}
                WHERE (" . $DB->sql_like('questiontext', ':search1', false) . " 
                       OR " . $DB->sql_like('generalfeedback', ':search2', false) . ")
                ORDER BY match_score DESC
                LIMIT 5";
        
        $exact_pattern = '%src="' . $DB->sql_like_escape($filename) . '"%';
        
        $results = $DB->get_records_sql($sql, [
            'exact' => $exact_pattern,
            'partial' => $filename_like,
            'search1' => $filename_like,
            'search2' => $filename_like
        ]);
        
        return array_values($results);
    }

    /**
     * Cherche des éléments candidats dans le même contexte
     *
     * @param object $orphan_file Fichier orphelin
     * @return array Tableau de candidats
     */
    private static function find_by_context($orphan_file) {
        global $DB;
        
        if ($orphan_file->component !== 'question') {
            return [];
        }
        
        // Chercher des questions dans le même contexte sans fichier associé
        $sql = "SELECT q.id, q.name, qc.name as category_name, qc.contextid
                FROM {question} q
                INNER JOIN {question_bank_entries} qbe ON qbe.id = q.parent
                INNER JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                WHERE qc.contextid = :contextid
                  AND q.id NOT IN (
                      SELECT DISTINCT itemid FROM {files}
                      WHERE component = 'question' 
                        AND filearea = 'questiontext'
                        AND filename != '.'
                        AND itemid IS NOT NULL
                  )
                ORDER BY q.timemodified DESC
                LIMIT 5";
        
        return array_values($DB->get_records_sql($sql, ['contextid' => $orphan_file->contextid]));
    }

    /**
     * Exécute une réparation
     *
     * @param int $orphan_file_id ID du fichier orphelin
     * @param string $repair_type Type de réparation
     * @param array $params Paramètres spécifiques à la réparation
     * @param bool $dry_run Mode simulation
     * @return array Résultat [success, message, details]
     */
    public static function execute_repair($orphan_file_id, $repair_type, $params = [], $dry_run = false) {
        global $DB;
        
        // Récupérer le fichier
        $orphan_file = $DB->get_record('files', ['id' => $orphan_file_id]);
        if (!$orphan_file) {
            return [
                'success' => false,
                'message' => get_string('repair_file_not_found', 'local_question_diagnostic')
            ];
        }
        
        // Mode dry-run
        if ($dry_run) {
            return [
                'success' => true,
                'message' => '[DRY-RUN] ' . get_string('repair_would_execute', 'local_question_diagnostic'),
                'details' => ['type' => $repair_type, 'params' => $params]
            ];
        }
        
        // Sauvegarder l'état actuel (pour rollback potentiel)
        self::backup_file_state($orphan_file);
        
        try {
            switch ($repair_type) {
                case 'reassociate_contenthash':
                    return self::repair_by_contenthash($orphan_file, $params);
                    
                case 'reassign_filename':
                    return self::repair_by_filename($orphan_file, $params);
                    
                case 'reassign_context':
                    return self::repair_by_context($orphan_file, $params);
                    
                case 'create_recovery':
                    return self::repair_create_recovery($orphan_file);
                    
                default:
                    return [
                        'success' => false,
                        'message' => get_string('repair_unknown_type', 'local_question_diagnostic')
                    ];
            }
        } catch (\Exception $e) {
            // En cas d'erreur, tenter de restaurer
            self::restore_file_state($orphan_file_id);
            return [
                'success' => false,
                'message' => get_string('repair_error', 'local_question_diagnostic') . ': ' . $e->getMessage()
            ];
        }
    }

    /**
     * Réparation par contenthash
     */
    private static function repair_by_contenthash($orphan_file, $params) {
        global $DB;
        
        $target = $params['target'] ?? null;
        if (!$target) {
            // Chercher automatiquement
            $target = self::find_by_contenthash($orphan_file);
        }
        
        if (!$target) {
            return [
                'success' => false,
                'message' => get_string('repair_no_target_found', 'local_question_diagnostic')
            ];
        }
        
        // Mettre à jour le fichier orphelin
        $update = new \stdClass();
        $update->id = $orphan_file->id;
        $update->component = $target->component ?? 'question';
        $update->filearea = $target->filearea ?? 'questiontext';
        $update->itemid = $target->parent_id ?? $target->itemid;
        $update->contextid = $target->contextid;
        
        $DB->update_record('files', $update);
        
        // Logger
        self::log_repair('contenthash', $orphan_file->id, $target);
        
        return [
            'success' => true,
            'message' => get_string('repair_success_contenthash', 'local_question_diagnostic'),
            'details' => $target
        ];
    }

    /**
     * Réparation par nom de fichier
     */
    private static function repair_by_filename($orphan_file, $params) {
        global $DB;
        
        $target_id = $params['target_id'] ?? null;
        if (!$target_id) {
            return [
                'success' => false,
                'message' => get_string('repair_no_target_selected', 'local_question_diagnostic')
            ];
        }
        
        // Récupérer la question cible
        $question = $DB->get_record('question', ['id' => $target_id]);
        if (!$question) {
            return [
                'success' => false,
                'message' => get_string('repair_target_not_found', 'local_question_diagnostic')
            ];
        }
        
        // Récupérer le contexte de la question
        $sql = "SELECT qc.contextid
                FROM {question_categories} qc
                INNER JOIN {question_bank_entries} qbe ON qbe.questioncategoryid = qc.id
                INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                WHERE qv.questionid = :questionid
                LIMIT 1";
        $context = $DB->get_record_sql($sql, ['questionid' => $target_id]);
        
        if (!$context) {
            return [
                'success' => false,
                'message' => get_string('repair_context_not_found', 'local_question_diagnostic')
            ];
        }
        
        // Mettre à jour le fichier
        $update = new \stdClass();
        $update->id = $orphan_file->id;
        $update->component = 'question';
        $update->filearea = 'questiontext';
        $update->itemid = $target_id;
        $update->contextid = $context->contextid;
        
        $DB->update_record('files', $update);
        
        // Logger
        self::log_repair('filename', $orphan_file->id, $question);
        
        return [
            'success' => true,
            'message' => get_string('repair_success_filename', 'local_question_diagnostic'),
            'details' => $question
        ];
    }

    /**
     * Réparation par contexte
     */
    private static function repair_by_context($orphan_file, $params) {
        // Similaire à repair_by_filename
        return self::repair_by_filename($orphan_file, $params);
    }

    /**
     * Crée une question de récupération pour héberger le fichier
     */
    private static function repair_create_recovery($orphan_file) {
        global $DB, $USER;
        
        // Créer ou récupérer la catégorie "Fichiers Récupérés"
        $category = self::get_or_create_recovery_category();
        
        // Créer une question de type "description"
        $question = new \stdClass();
        $question->category = $category->id;
        $question->name = 'Recovered: ' . $orphan_file->filename . ' [' . date('Y-m-d H:i') . ']';
        $question->questiontext = '<p><strong>Fichier récupéré automatiquement</strong></p>' .
                                  '<p>Nom original : ' . htmlspecialchars($orphan_file->filename) . '</p>' .
                                  '<p>Date de récupération : ' . date('Y-m-d H:i:s') . '</p>';
        $question->questiontextformat = FORMAT_HTML;
        $question->generalfeedback = '';
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->qtype = 'description';
        $question->defaultmark = 0;
        $question->penalty = 0;
        $question->length = 0;
        $question->stamp = make_unique_id_code();
        $question->timecreated = time();
        $question->timemodified = time();
        $question->createdby = $USER->id ?? 2;
        $question->modifiedby = $USER->id ?? 2;
        
        // Sauvegarder la question
        $question->id = $DB->insert_record('question', $question);
        
        // Créer l'entrée dans question_bank_entries
        $entry = new \stdClass();
        $entry->questioncategoryid = $category->id;
        $entry->idnumber = null;
        $entry->ownerid = $USER->id ?? 2;
        $entry->id = $DB->insert_record('question_bank_entries', $entry);
        
        // Créer la version
        $version = new \stdClass();
        $version->questionbankentryid = $entry->id;
        $version->version = 1;
        $version->questionid = $question->id;
        $version->status = 'ready';
        $DB->insert_record('question_versions', $version);
        
        // Mettre à jour question.parent
        $question->parent = $entry->id;
        $DB->update_record('question', $question);
        
        // Réassocier le fichier
        $update = new \stdClass();
        $update->id = $orphan_file->id;
        $update->component = 'question';
        $update->filearea = 'questiontext';
        $update->itemid = $question->id;
        $update->contextid = $category->contextid;
        
        $DB->update_record('files', $update);
        
        // Logger
        self::log_repair('recovery', $orphan_file->id, $question);
        
        return [
            'success' => true,
            'message' => get_string('repair_success_recovery', 'local_question_diagnostic'),
            'question_id' => $question->id,
            'category' => $category->name
        ];
    }

    /**
     * Récupère ou crée la catégorie de récupération
     */
    private static function get_or_create_recovery_category() {
        global $DB;
        
        $context = \context_system::instance();
        
        // Chercher la catégorie
        $category_name = 'Recovered Files [' . date('Y-m') . ']';
        $category = $DB->get_record('question_categories', [
            'name' => $category_name,
            'contextid' => $context->id
        ]);
        
        if (!$category) {
            // Créer la catégorie
            $category = new \stdClass();
            $category->name = $category_name;
            $category->contextid = $context->id;
            $category->info = 'Catégorie automatique pour les fichiers récupérés';
            $category->infoformat = FORMAT_HTML;
            $category->stamp = make_unique_id_code();
            $category->parent = 0;
            $category->sortorder = 999;
            $category->idnumber = 'recovered_files_' . date('Ym');
            
            $category->id = $DB->insert_record('question_categories', $category);
        }
        
        return $category;
    }

    /**
     * Sauvegarde l'état d'un fichier avant modification
     */
    private static function backup_file_state($file) {
        global $DB;
        
        $backup = new \stdClass();
        $backup->fileid = $file->id;
        $backup->component = $file->component;
        $backup->filearea = $file->filearea;
        $backup->itemid = $file->itemid;
        $backup->contextid = $file->contextid;
        $backup->backup_time = time();
        
        // Créer une table temporaire si elle n'existe pas
        // Note: En production, utiliser une vraie table ou un système de versioning
        $DB->insert_record('local_qd_file_backups', $backup);
    }

    /**
     * Restaure l'état d'un fichier
     */
    private static function restore_file_state($fileid) {
        global $DB;
        
        $backup = $DB->get_record('local_qd_file_backups', ['fileid' => $fileid], '*', IGNORE_MULTIPLE);
        if ($backup) {
            $restore = new \stdClass();
            $restore->id = $fileid;
            $restore->component = $backup->component;
            $restore->filearea = $backup->filearea;
            $restore->itemid = $backup->itemid;
            $restore->contextid = $backup->contextid;
            
            $DB->update_record('files', $restore);
        }
    }

    /**
     * Logger une opération de réparation
     */
    private static function log_repair($type, $fileid, $target) {
        global $USER;
        
        $log_message = sprintf(
            '[ORPHAN_FILE_REPAIR] Type: %s | File ID: %d | Target: %s | User: %s (%d) | Time: %s',
            strtoupper($type),
            $fileid,
            is_object($target) ? ($target->name ?? $target->id ?? 'unknown') : 'N/A',
            fullname($USER),
            $USER->id,
            date('Y-m-d H:i:s')
        );
        
        debugging($log_message, DEBUG_NORMAL);
    }

    /**
     * Analyse en masse les fichiers orphelins pour réparation
     *
     * @param array $orphan_files Tableau de fichiers orphelins
     * @return array Statistiques de réparabilité
     */
    public static function analyze_bulk_repairability($orphan_files) {
        // Tolérance: sécuriser l'entrée pour éviter count(null) / foreach(null).
        if ($orphan_files instanceof \Traversable) {
            $orphan_files = iterator_to_array($orphan_files, false);
        } else if (!is_array($orphan_files)) {
            $orphan_files = [];
        }

        $stats = [
            'high_confidence' => 0,
            'medium_confidence' => 0,
            'low_confidence' => 0,
            'total' => count($orphan_files)
        ];
        
        foreach ($orphan_files as $orphan) {
            $level = self::get_repairability_level($orphan->file);
            
            if ($level === 'high') {
                $stats['high_confidence']++;
            } else if ($level === 'medium') {
                $stats['medium_confidence']++;
            } else {
                $stats['low_confidence']++;
            }
        }
        
        return $stats;
    }
}

