<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_question_diagnostic;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/editlib.php');

/**
 * Analyseur de questions - Statistiques et nettoyage
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_analyzer {

    /**
     * Récupère toutes les questions avec leurs statistiques complètes
     *
     * @param bool $include_duplicates Inclure la détection de doublons (peut être lent)
     * @param int $limit Limite du nombre de questions (0 = toutes)
     * @param int $offset Offset pour la pagination serveur (🆕 v1.9.30)
     * @return array Tableau des questions avec métadonnées
     */
    public static function get_all_questions_with_stats($include_duplicates = true, $limit = 0, $offset = 0) {
        global $DB;

        // 🆕 v1.9.30 : Support de l'offset pour pagination serveur
        // Récupérer les questions avec limite et offset - Utiliser l'API Moodle pour compatibilité multi-SGBD
        if ($limit > 0) {
            $questions = $DB->get_records('question', null, 'id DESC', '*', $offset, $limit);
        } else {
            $questions = $DB->get_records('question', null, 'id DESC');
        }
        $result = [];

        // 🚀 OPTIMISATION CRITIQUE : Si limite appliquée, charger UNIQUEMENT les données pour ces questions
        if ($limit > 0 && count($questions) > 0) {
            // Extraire les IDs des questions à traiter
            $question_ids = array_keys($questions);
            
            // Charger l'usage UNIQUEMENT pour ces questions
            try {
                $usage_map = self::get_questions_usage_by_ids($question_ids);
            } catch (\Exception $e) {
                debugging('Error loading usage map: ' . $e->getMessage(), DEBUG_DEVELOPER);
                $usage_map = [];
            }
            
            // Charger les doublons UNIQUEMENT pour ces questions (si demandé)
            $duplicates_map = [];
            if ($include_duplicates && count($questions) < 5000) {
                try {
                    $duplicates_map = self::get_duplicates_for_questions($questions);
                } catch (\Exception $e) {
                    debugging('Error loading duplicates map: ' . $e->getMessage(), DEBUG_DEVELOPER);
                    $duplicates_map = [];
                }
            }
        } else {
            // Mode ancien : charger toutes les données (pour compatibilité)
            try {
                $usage_map = self::get_all_questions_usage();
            } catch (\Exception $e) {
                debugging('Error loading usage map: ' . $e->getMessage(), DEBUG_DEVELOPER);
                $usage_map = [];
            }
            
            $duplicates_map = [];
            if ($include_duplicates && count($questions) < 5000) {
                try {
                    $duplicates_map = self::get_duplicates_map(true);
                } catch (\Exception $e) {
                    debugging('Error loading duplicates map: ' . $e->getMessage(), DEBUG_DEVELOPER);
                    $duplicates_map = [];
                }
            }
        }

        foreach ($questions as $question) {
            try {
                $stats = self::get_question_stats($question, $usage_map, $duplicates_map);
                $result[] = (object)[
                    'question' => $question,
                    'stats' => $stats,
                ];
            } catch (\Exception $e) {
                debugging('Error loading stats for question ' . $question->id . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
                // Continuer avec la question suivante
            }
        }

        return $result;
    }

    /**
     * Obtient les statistiques d'une question
     *
     * @param object $question Objet question
     * @param array $usage_map Map des usages (pré-calculé)
     * @param array $duplicates_map Map des doublons (pré-calculé)
     * @return object Statistiques
     */
    public static function get_question_stats($question, $usage_map = null, $duplicates_map = null) {
        global $DB;

        $stats = new \stdClass();
        
        try {
            // Récupérer la catégorie via question_bank_entries (Moodle 4.x)
            $category_sql = "SELECT qc.* 
                            FROM {question_categories} qc
                            INNER JOIN {question_bank_entries} qbe ON qbe.questioncategoryid = qc.id
                            INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                            WHERE qv.questionid = :questionid
                            LIMIT 1";
            $category = $DB->get_record_sql($category_sql, ['questionid' => $question->id]);
            $stats->category_name = $category ? format_string($category->name) : 'Inconnue';
            $stats->category_id = $category ? $category->id : 0;
            
            // Contexte enrichi (avec cours et module)
            if ($category) {
                try {
                    $context_details = local_question_diagnostic_get_context_details($category->contextid);
                    $stats->context_name = $context_details->context_name;
                    $stats->course_name = $context_details->course_name;
                    $stats->module_name = $context_details->module_name;
                    $stats->context_type = $context_details->context_type;
                    $stats->context_id = $category->contextid;
                } catch (\Exception $e) {
                    $stats->context_name = 'Erreur';
                    $stats->course_name = null;
                    $stats->module_name = null;
                    $stats->context_type = null;
                    $stats->context_id = 0;
                }
            } else {
                $stats->context_name = 'Inconnu';
                $stats->course_name = null;
                $stats->module_name = null;
                $stats->context_type = null;
                $stats->context_id = 0;
            }
            
            // Créateur
            $creator = $DB->get_record('user', ['id' => $question->createdby]);
            $stats->creator_name = $creator ? fullname($creator) : 'Inconnu';
            $stats->creator_id = $question->createdby;
            
            // Modificateur
            $modifier = $DB->get_record('user', ['id' => $question->modifiedby]);
            $stats->modifier_name = $modifier ? fullname($modifier) : 'Inconnu';
            $stats->modifier_id = $question->modifiedby;
            
            // Dates
            $stats->created_date = $question->timecreated;
            $stats->modified_date = $question->timemodified;
            $stats->created_formatted = userdate($question->timecreated, '%d/%m/%Y %H:%M');
            $stats->modified_formatted = userdate($question->timemodified, '%d/%m/%Y %H:%M');
            
            // Usage (utiliser le cache si disponible)
            if ($usage_map !== null && isset($usage_map[$question->id])) {
                $usage = $usage_map[$question->id];
                $stats->used_in_quizzes = $usage['quiz_count'];
                $stats->quiz_list = $usage['quiz_list'];
                $stats->attempt_count = $usage['attempt_count'];
                $stats->is_used = $usage['is_used'];
            } else {
                $usage = self::get_question_usage($question->id);
                $stats->used_in_quizzes = $usage['quiz_count'];
                $stats->quiz_list = $usage['quiz_list'];
                $stats->attempt_count = $usage['attempt_count'];
                $stats->is_used = $usage['is_used'];
            }
            
            // Doublons (utiliser le cache si disponible)
            if ($duplicates_map !== null && isset($duplicates_map[$question->id])) {
                $stats->duplicate_count = count($duplicates_map[$question->id]);
                $stats->duplicate_ids = $duplicates_map[$question->id];
                $stats->is_duplicate = $stats->duplicate_count > 0;
            } else {
                $duplicates = self::find_question_duplicates($question);
                $stats->duplicate_count = count($duplicates);
                $stats->duplicate_ids = array_map(function($q) { return $q->id; }, $duplicates);
                $stats->is_duplicate = $stats->duplicate_count > 0;
            }
            
            // Statut - ⚠️ MOODLE 4.5 : question.hidden n'existe plus, utiliser question_versions.status
            try {
                $sql_status = "SELECT qv.status
                               FROM {question_versions} qv
                               INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                               WHERE qv.questionid = :questionid
                               ORDER BY qv.version DESC
                               LIMIT 1";
                $status_record = $DB->get_record_sql($sql_status, ['questionid' => $question->id]);
                $stats->is_hidden = ($status_record && $status_record->status === 'hidden');
                $stats->status = $stats->is_hidden ? 'hidden' : 'visible';
            } catch (\Exception $e) {
                // Fallback si erreur
                $stats->is_hidden = false;
                $stats->status = 'visible';
            }
            
            // Extrait du texte
            $stats->questiontext_excerpt = self::get_text_excerpt($question->questiontext, 100);
            
        } catch (\Exception $e) {
            // En cas d'erreur, retourner des stats par défaut
            $stats->category_name = 'Erreur';
            $stats->context_name = 'Erreur';
            $stats->creator_name = 'Inconnu';
            $stats->is_used = false;
            $stats->is_duplicate = false;
        }

        return $stats;
    }

    /**
     * Obtient l'usage d'une question (quizzes et tentatives)
     *
     * @param int $questionid ID de la question
     * @return array Informations sur l'usage
     */
    public static function get_question_usage($questionid) {
        global $DB;

        $usage = [
            'quiz_count' => 0,
            'quiz_list' => [],
            'attempt_count' => 0,
            'is_used' => false
        ];

        try {
            // Vérifier si la question est dans des quiz via la table quiz_slots
            // ⚠️ v1.6.4 : Compatibilité multi-version Moodle
            $columns = $DB->get_columns('quiz_slots');
            $quizzes = [];
            
            if (isset($columns['questionbankentryid'])) {
                // Moodle 4.1-4.4 : utilise questionbankentryid
                $sql = "SELECT DISTINCT q.id, q.name, q.course
                        FROM {quiz} q
                        INNER JOIN {quiz_slots} qs ON qs.quizid = q.id
                        INNER JOIN {question_bank_entries} qbe ON qbe.id = qs.questionbankentryid
                        INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                        WHERE qv.questionid = :questionid";
                $quizzes = $DB->get_records_sql($sql, ['questionid' => $questionid]);
                } else if (isset($columns['questionid'])) {
                // Moodle 4.0 uniquement : utilise questionid directement
                // ⚠️ Note : Moodle 3.x NON supporté (architecture incompatible)
                $sql = "SELECT DISTINCT q.id, q.name, q.course
                        FROM {quiz} q
                        INNER JOIN {quiz_slots} qs ON qs.quizid = q.id
                        WHERE qs.questionid = :questionid";
                $quizzes = $DB->get_records_sql($sql, ['questionid' => $questionid]);
            } else {
                // 🔧 v1.9.22 FIX : Moodle 4.5+ utilise question_references
                $sql = "SELECT DISTINCT q.id, q.name, q.course
                        FROM {quiz} q
                        INNER JOIN {quiz_slots} qs ON qs.quizid = q.id
                        INNER JOIN {question_references} qr ON qr.itemid = qs.id 
                            AND qr.component = 'mod_quiz' 
                            AND qr.questionarea = 'slot'
                        INNER JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                        INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                        WHERE qv.questionid = :questionid";
                $quizzes = $DB->get_records_sql($sql, ['questionid' => $questionid]);
            }

            foreach ($quizzes as $quiz) {
                $usage['quiz_list'][] = (object)[
                    'id' => $quiz->id,
                    'name' => $quiz->name,
                    'course' => $quiz->course
                ];
            }
            $usage['quiz_count'] = count($quizzes);

            // Vérifier si la question a été utilisée dans des tentatives
            // Via la table question_attempts
            $attempt_count = $DB->count_records_sql("
                SELECT COUNT(DISTINCT qa.id)
                FROM {question_attempts} qa
                INNER JOIN {question_usages} qu ON qu.id = qa.questionusageid
                WHERE qa.questionid = :questionid
            ", ['questionid' => $questionid]);

            $usage['attempt_count'] = $attempt_count;
            
            // La question est utilisée si elle est dans un quiz OU a des tentatives
            $usage['is_used'] = ($usage['quiz_count'] > 0 || $usage['attempt_count'] > 0);

        } catch (\Exception $e) {
            // En cas d'erreur, retourner les valeurs par défaut
        }

        return $usage;
    }

    /**
     * Récupère l'usage pour un ensemble spécifique de questions (optimisé pour limite)
     * 🆕 v1.9.2 : Changed to public for external use (random test, batch operations)
     *
     * @param array $question_ids IDs des questions
     * @return array Map [question_id => usage_info]
     */
    public static function get_questions_usage_by_ids($question_ids) {
        global $DB;

        if (empty($question_ids)) {
            return [];
        }

        $usage_map = [];

        try {
            // Construire la clause IN pour filtrer uniquement les questions demandées
            list($insql, $params) = $DB->get_in_or_equal($question_ids, SQL_PARAMS_NAMED);
            
            // Quiz usage - UNIQUEMENT pour les IDs demandés
            // ⚠️ v1.6.4 : Vérifier quelle colonne existe dans quiz_slots
            $quiz_usage = [];
            try {
                // Vérifier si questionbankentryid existe (Moodle 4.1+)
                $columns = $DB->get_columns('quiz_slots');
                
                if (isset($columns['questionbankentryid'])) {
                    // Moodle 4.1-4.4 : utilise questionbankentryid
                    $quiz_usage = $DB->get_records_sql("
                        SELECT qv.questionid, qu.id as quiz_id, qu.name as quiz_name, qu.course
                        FROM {quiz_slots} qs
                        INNER JOIN {quiz} qu ON qu.id = qs.quizid
                        INNER JOIN {question_bank_entries} qbe ON qbe.id = qs.questionbankentryid
                        INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                        WHERE qv.questionid $insql
                        ORDER BY qv.questionid, qu.id
                    ", $params);
                } else if (isset($columns['questionid'])) {
                    // Moodle 4.0 uniquement : utilise questionid directement
                    // ⚠️ Note : Moodle 3.x NON supporté (architecture incompatible)
                    $quiz_usage = $DB->get_records_sql("
                        SELECT qs.questionid, qu.id as quiz_id, qu.name as quiz_name, qu.course
                        FROM {quiz_slots} qs
                        INNER JOIN {quiz} qu ON qu.id = qs.quizid
                        WHERE qs.questionid $insql
                        ORDER BY qs.questionid, qu.id
                    ", $params);
                } else {
                    // 🔧 v1.9.22 FIX : Moodle 4.5+ utilise question_references
                    $quiz_usage = $DB->get_records_sql("
                        SELECT qv.questionid, qu.id as quiz_id, qu.name as quiz_name, qu.course
                        FROM {quiz_slots} qs
                        INNER JOIN {quiz} qu ON qu.id = qs.quizid
                        INNER JOIN {question_references} qr ON qr.itemid = qs.id 
                            AND qr.component = 'mod_quiz' 
                            AND qr.questionarea = 'slot'
                        INNER JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                        INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                        WHERE qv.questionid $insql
                        ORDER BY qv.questionid, qu.id
                    ", $params);
                }
            } catch (\Exception $e) {
                debugging('Error in get_questions_usage_by_ids: ' . $e->getMessage(), DEBUG_DEVELOPER);
                $quiz_usage = [];
            }

            foreach ($quiz_usage as $record) {
                $qid = $record->questionid;
                
                if (!isset($usage_map[$qid])) {
                    $usage_map[$qid] = [
                        'quiz_count' => 0,
                        'quiz_list' => [],
                        'attempt_count' => 0,
                        'is_used' => true
                    ];
                }
                
                // Vérifier si ce quiz n'est pas déjà dans la liste
                $already_added = false;
                foreach ($usage_map[$qid]['quiz_list'] as $existing_quiz) {
                    if ($existing_quiz->id == $record->quiz_id) {
                        $already_added = true;
                        break;
                    }
                }
                
                if (!$already_added) {
                    $usage_map[$qid]['quiz_list'][] = (object)[
                        'id' => $record->quiz_id,
                        'name' => $record->quiz_name,
                        'course' => $record->course
                    ];
                    $usage_map[$qid]['quiz_count']++;
                }
            }

            // Attempts - UNIQUEMENT pour les IDs demandés
            $attempts = $DB->get_records_sql("
                SELECT qa.questionid, COUNT(DISTINCT qa.id) as attempt_count
                FROM {question_attempts} qa
                WHERE qa.questionid $insql
                GROUP BY qa.questionid
            ", $params);

            foreach ($attempts as $record) {
                if (!isset($usage_map[$record->questionid])) {
                    $usage_map[$record->questionid] = [
                        'quiz_count' => 0,
                        'quiz_list' => [],
                        'attempt_count' => 0,
                        'is_used' => false
                    ];
                }
                $usage_map[$record->questionid]['attempt_count'] = $record->attempt_count;
                $usage_map[$record->questionid]['is_used'] = true;
            }

            // Initialiser les questions sans usage
            foreach ($question_ids as $qid) {
                if (!isset($usage_map[$qid])) {
                    $usage_map[$qid] = [
                        'quiz_count' => 0,
                        'quiz_list' => [],
                        'attempt_count' => 0,
                        'is_used' => false
                    ];
                }
            }

        } catch (\Exception $e) {
            debugging('Error in get_questions_usage_by_ids: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return $usage_map;
    }

    /**
     * Détecte les doublons pour un ensemble spécifique de questions (optimisé)
     *
     * @param array $questions Tableau d'objets questions
     * @return array Map [question_id => [duplicate_ids]]
     */
    private static function get_duplicates_for_questions($questions) {
        global $DB;

        $duplicates_map = [];
        
        if (empty($questions)) {
            return $duplicates_map;
        }

        try {
            // Créer un index des questions par nom pour recherche rapide
            $questions_by_name = [];
            foreach ($questions as $question) {
                $key = strtolower(trim($question->name)) . '|' . $question->qtype;
                if (!isset($questions_by_name[$key])) {
                    $questions_by_name[$key] = [];
                }
                $questions_by_name[$key][] = $question;
            }
            
            // Pour chaque groupe de noms, chercher les doublons dans la base complète
            foreach ($questions_by_name as $key => $local_questions) {
                list($name_part, $qtype_part) = explode('|', $key, 2);
                
                // Récupérer le nom original (non transformé) depuis une des questions
                $original_name = $local_questions[0]->name;
                
                // Trouver TOUTES les questions avec ce nom exact dans la base
                $all_with_name = $DB->get_records('question', [
                    'name' => $original_name,
                    'qtype' => $qtype_part
                ]);
                
                // Si plus d'une question avec ce nom existe
                if (count($all_with_name) > 1) {
                    // Pour chaque question locale, lister les autres comme doublons
                    foreach ($local_questions as $local_question) {
                        $others = [];
                        foreach ($all_with_name as $other) {
                            if ($other->id != $local_question->id) {
                                $others[] = $other->id;
                            }
                        }
                        if (!empty($others)) {
                            $duplicates_map[$local_question->id] = $others;
                        }
                    }
                }
            }
            
        } catch (\Exception $e) {
            debugging('Error in get_duplicates_for_questions: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return $duplicates_map;
    }

    /**
     * Pré-calcule l'usage de toutes les questions (optimisation)
     *
     * @return array Map [question_id => usage_info]
     */
    private static function get_all_questions_usage() {
        global $DB;

        // Essayer le cache d'abord
        require_once(__DIR__ . '/cache_manager.php');
        $cached_usage = cache_manager::get(cache_manager::CACHE_QUESTIONUSAGE, 'usage_map');
        if ($cached_usage !== false) {
            return $cached_usage;
        }

        $usage_map = [];

        try {
            // Approche compatible avec tous les SGBD: requête simple + traitement en PHP
            // ⚠️ v1.6.4 : Compatibilité multi-version Moodle
            $columns = $DB->get_columns('quiz_slots');
            $quiz_usage = [];
            
            if (isset($columns['questionbankentryid'])) {
                // Moodle 4.1+ : utilise questionbankentryid
                $quiz_usage = $DB->get_records_sql("
                    SELECT qv.questionid, qu.id as quiz_id, qu.name as quiz_name, qu.course
                    FROM {quiz_slots} qs
                    INNER JOIN {quiz} qu ON qu.id = qs.quizid
                    INNER JOIN {question_bank_entries} qbe ON qbe.id = qs.questionbankentryid
                    INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                    ORDER BY qv.questionid, qu.id
                ");
            } else if (isset($columns['questionid'])) {
                // Moodle 4.0 uniquement : utilise questionid directement
                // ⚠️ Note : Moodle 3.x NON supporté (architecture incompatible)
                $quiz_usage = $DB->get_records_sql("
                    SELECT qs.questionid, qu.id as quiz_id, qu.name as quiz_name, qu.course
                    FROM {quiz_slots} qs
                    INNER JOIN {quiz} qu ON qu.id = qs.quizid
                    ORDER BY qs.questionid, qu.id
                ");
            }

            foreach ($quiz_usage as $record) {
                $qid = $record->questionid;
                
                if (!isset($usage_map[$qid])) {
                    $usage_map[$qid] = [
                        'quiz_count' => 0,
                        'quiz_list' => [],
                        'attempt_count' => 0,
                        'is_used' => true
                    ];
                }
                
                // Vérifier si ce quiz n'est pas déjà dans la liste
                $already_added = false;
                foreach ($usage_map[$qid]['quiz_list'] as $existing_quiz) {
                    if ($existing_quiz->id == $record->quiz_id) {
                        $already_added = true;
                        break;
                    }
                }
                
                if (!$already_added) {
                    $usage_map[$qid]['quiz_list'][] = (object)[
                        'id' => $record->quiz_id,
                        'name' => $record->quiz_name,
                        'course' => $record->course
                    ];
                    $usage_map[$qid]['quiz_count']++;
                }
            }

            // Récupérer le nombre de tentatives par question (requête optimisée)
            $attempts = $DB->get_records_sql("
                SELECT qa.questionid, COUNT(DISTINCT qa.id) as attempt_count
                FROM {question_attempts} qa
                GROUP BY qa.questionid
            ");

            foreach ($attempts as $record) {
                if (!isset($usage_map[$record->questionid])) {
                    $usage_map[$record->questionid] = [
                        'quiz_count' => 0,
                        'quiz_list' => [],
                        'attempt_count' => 0,
                        'is_used' => false
                    ];
                }
                $usage_map[$record->questionid]['attempt_count'] = $record->attempt_count;
                $usage_map[$record->questionid]['is_used'] = true;
            }

            // Mettre en cache pour 30 minutes
            cache_manager::set(cache_manager::CACHE_QUESTIONUSAGE, 'usage_map', $usage_map);

        } catch (\Exception $e) {
            debugging('Error in get_all_questions_usage: ' . $e->getMessage(), DEBUG_DEVELOPER);
            // En cas d'erreur, retourner un map vide
        }

        return $usage_map;
    }

    /**
     * Récupère les questions qui ont des doublons avec au moins 1 version utilisée
     * 🆕 v1.8.0 : Pour le chargement ciblé des doublons problématiques
     * 🆕 v1.9.4 : OPTIMIZED with batch verification to avoid N+1 queries
     * 🔧 v1.9.24 : REFONTE COMPLÈTE - Utilise la même logique robuste que "Test Doublons Utilisés"
     *              Ne se base plus sur !empty() qui donnait des faux positifs
     *              Utilise désormais la détection directe depuis quiz_slots
     * 
     * 🆕 v1.9.30 : Support pagination serveur (limit + offset)
     * 
     * @param int $limit Limite de questions à retourner par page
     * @param int $offset Offset pour la pagination serveur
     * @return array Tableau des questions (objets simples)
     */
    public static function get_used_duplicates_questions($limit = 100, $offset = 0) {
        global $DB;
        
        try {
            // 🔧 v1.9.24 REFONTE COMPLÈTE : Même logique que "Test Doublons Utilisés" (questions_cleanup.php lignes 242-362)
            // LOGIQUE CORRECTE :
            // 1. Trouver toutes les questions UTILISÉES (dans les quiz)
            // 2. Pour chaque question utilisée, chercher SES doublons
            // 3. Si doublons trouvés → Ajouter tout le groupe au résultat
            
            // Étape 1 : Récupérer TOUTES les questions utilisées (UNIQUEMENT dans les quiz)
            $used_question_ids = [];
            $debug_info = ['columns' => [], 'sql' => '', 'count' => 0, 'error' => ''];
            
            try {
                // Vérifier quelle colonne existe dans quiz_slots
                $columns = $DB->get_columns('quiz_slots');
                $debug_info['columns'] = array_keys($columns);
                
                if (isset($columns['questionbankentryid'])) {
                    // Moodle 4.1+ : utilise questionbankentryid
                    $debug_info['mode'] = 'Moodle 4.1+ (questionbankentryid)';
                    $sql_used = "SELECT DISTINCT qv.questionid
                                 FROM {quiz_slots} qs
                                 INNER JOIN {question_bank_entries} qbe ON qbe.id = qs.questionbankentryid
                                 INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id";
                    $debug_info['sql'] = $sql_used;
                    $used_question_ids = $DB->get_fieldset_sql($sql_used);
                } else if (isset($columns['questionid'])) {
                    // Moodle 4.0 uniquement : utilise questionid directement
                    // ⚠️ Note : Moodle 3.x NON supporté (architecture incompatible)
                    $debug_info['mode'] = 'Moodle 4.0 (questionid)';
                    $sql_used = "SELECT DISTINCT qs.questionid
                                 FROM {quiz_slots} qs";
                    $debug_info['sql'] = $sql_used;
                    $used_question_ids = $DB->get_fieldset_sql($sql_used);
                } else {
                    // 🔧 v1.9.24 : Moodle 4.5+ - Nouvelle architecture avec question_references
                    $debug_info['mode'] = 'Moodle 4.5+ (question_references)';
                    
                    // Dans Moodle 4.5+, quiz_slots ne contient plus de lien direct vers les questions
                    // Il faut passer par question_references
                    $sql_used = "SELECT DISTINCT qv.questionid
                                 FROM {quiz_slots} qs
                                 INNER JOIN {question_references} qr ON qr.itemid = qs.id 
                                     AND qr.component = 'mod_quiz' 
                                     AND qr.questionarea = 'slot'
                                 INNER JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                                 INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id 
                                     AND qv.version = (
                                         SELECT MAX(v.version)
                                         FROM {question_versions} v
                                         WHERE v.questionbankentryid = qbe.id
                                     )";
                    $debug_info['sql'] = $sql_used;
                    $used_question_ids = $DB->get_fieldset_sql($sql_used);
                }
                
                $debug_info['count'] = count($used_question_ids);
            } catch (\Exception $e) {
                $debug_info['error'] = $e->getMessage();
                debugging('Erreur récupération questions utilisées (get_used_duplicates_questions): ' . $e->getMessage(), DEBUG_DEVELOPER);
                return []; // Si erreur de détection, retourner vide plutôt que des faux positifs
            }
            
            debugging('CHARGER DOUBLONS UTILISÉS v1.9.24 - Questions utilisées détectées: ' . count($used_question_ids), DEBUG_DEVELOPER);
            
            if (empty($used_question_ids)) {
                // Aucune question utilisée dans la base
                debugging('CHARGER DOUBLONS UTILISÉS v1.9.24 - Aucune question utilisée trouvée', DEBUG_DEVELOPER);
                return [];
            }
            
            // Étape 2 : Pour chaque question utilisée, chercher ses doublons
            // 🆕 v1.9.30 : Charger TOUTES les questions d'abord, puis paginer
            $all_result_questions = [];
            $processed_signatures = []; // Pour éviter les doublons dans le résultat
            $groups_found = 0;
            
            foreach ($used_question_ids as $qid) {
                $question = $DB->get_record('question', ['id' => $qid]);
                if (!$question) {
                    continue;
                }
                
                // Créer une signature unique pour éviter de traiter le même groupe plusieurs fois
                $signature = strtolower(trim($question->name)) . '|' . $question->qtype;
                if (in_array($signature, $processed_signatures)) {
                    continue; // Déjà traité ce groupe
                }
                
                // Chercher les doublons de CETTE question (même nom + même type, ID différent)
                $all_versions = $DB->get_records('question', [
                    'name' => $question->name,
                    'qtype' => $question->qtype
                ]);
                
                // Si au moins 2 versions (= 1 original + 1 doublon minimum) → C'est un groupe de doublons utilisés !
                if (count($all_versions) > 1) {
                    $processed_signatures[] = $signature;
                    $groups_found++;
                    
                    // Ajouter TOUTES les versions du groupe au résultat
                    foreach ($all_versions as $q) {
                        $all_result_questions[] = $q;
                    }
                }
            }
            
            // 🆕 v1.9.30 : Appliquer la pagination sur le résultat complet
            $total_count = count($all_result_questions);
            $paginated_result = array_slice($all_result_questions, $offset, $limit);
            
            debugging('CHARGER DOUBLONS UTILISÉS v1.9.30 - Total: ' . $total_count . ' questions dans ' . $groups_found . ' groupes | Page: ' . count($paginated_result) . ' questions (offset=' . $offset . ', limit=' . $limit . ')', DEBUG_DEVELOPER);
            
            return $paginated_result;
            
        } catch (\Exception $e) {
            debugging('Error in get_used_duplicates_questions: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
    }
    
    /**
     * Vérifie si deux questions sont des doublons selon la définition standard
     * 
     * 🆕 v1.9.28 : DÉFINITION UNIQUE DE "DOUBLON"
     * Cette méthode définit LA définition officielle utilisée partout dans le plugin.
     * 
     * CRITÈRES :
     * - Même nom (name)
     * - Même type (qtype)
     * 
     * Note : Le texte (questiontext) n'est PAS utilisé car peut avoir variations mineures
     * (espaces, formatage HTML) sans changer la nature de la question.
     * 
     * @param object $q1 Question 1
     * @param object $q2 Question 2
     * @return bool True si doublons, false sinon
     */
    public static function are_duplicates($q1, $q2) {
        // Même ID = même question, pas un doublon
        if ($q1->id === $q2->id) {
            return false;
        }
        
        // Critère 1 : Même nom (sensible à la casse)
        if ($q1->name !== $q2->name) {
            return false;
        }
        
        // Critère 2 : Même type
        if ($q1->qtype !== $q2->qtype) {
            return false;
        }
        
        // Si les deux critères sont remplis → C'est un doublon
        return true;
    }
    
    /**
     * Trouve les doublons d'une question selon la définition standard
     * 
     * 🔧 REFACTORED v1.9.28 : Utilise la définition unique via are_duplicates()
     * Remplace find_exact_duplicates() qui utilisait nom + type + texte
     * 
     * @param object $question Objet question
     * @return array Tableau des questions en doublon
     */
    public static function find_exact_duplicates($question) {
        global $DB;
        
        try {
            // Utiliser la définition standard : nom + type uniquement
            $sql = "SELECT q.*
                    FROM {question} q
                    WHERE q.name = :name
                    AND q.qtype = :qtype
                    AND q.id != :questionid
                    ORDER BY q.id";
            
            $duplicates = $DB->get_records_sql($sql, [
                'name' => $question->name,
                'qtype' => $question->qtype,
                'questionid' => $question->id
            ]);
            
            return array_values($duplicates);
            
        } catch (\Exception $e) {
            debugging('Error finding exact duplicates: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
    }
    
    /**
     * Trouve les doublons d'une question basés sur plusieurs critères
     *
     * 🔧 REFACTORED v1.9.28 : Utilise maintenant la définition standard (nom + type)
     * Le paramètre $threshold est conservé pour compatibilité mais ignoré.
     * 
     * @param object $question Objet question
     * @param float $threshold Seuil de similarité (DEPRECATED, ignoré)
     * @return array Tableau des questions en doublon
     */
    public static function find_question_duplicates($question, $threshold = 0.85) {
        // 🔧 v1.9.28 : Utiliser la définition standard au lieu de la similarité
        // Pour la cohérence dans tout le plugin
        return self::find_exact_duplicates($question);
    }

    // 🗑️ REMOVED v1.9.31 : Méthodes dépréciées supprimées (code mort)
    //
    // Les méthodes suivantes ont été supprimées car jamais utilisées :
    //
    // - calculate_question_similarity($q1, $q2) : Calcul complexe de similarité (DEPRECATED v1.9.28)
    //   → Remplacée par are_duplicates() qui utilise une définition simple (nom + type)
    //
    // - get_question_category_id($questionid) : Helper utilisé uniquement par calculate_question_similarity()
    //   → Plus nécessaire après suppression de calculate_question_similarity()
    //
    // Ces suppressions réduisent ~82 lignes de code mort et améliorent la maintenabilité.

    /**
     * Pré-calcule la map des doublons pour toutes les questions
     * Optimisé avec cache et détection rapide basée sur hash
     *
     * @param bool $use_cache Utiliser le cache (défaut: true)
     * @param int $limit Limite de questions à traiter (0 = toutes)
     * @return array Map [question_id => [duplicate_ids]]
     */
    private static function get_duplicates_map($use_cache = true, $limit = 0) {
        global $DB;

        // Essayer de récupérer depuis le cache
        require_once(__DIR__ . '/cache_manager.php');
        if ($use_cache) {
            $cached_map = cache_manager::get(cache_manager::CACHE_DUPLICATES, 'duplicates_map');
            if ($cached_map !== false) {
                return $cached_map;
            }
        }

        $duplicates_map = [];

        try {
            // Optimisation: utiliser un hash du nom pour grouper les candidats potentiels
            // Cela réduit considérablement le nombre de comparaisons
            $sql = "SELECT id, name, qtype, questiontext 
                    FROM {question} 
                    ORDER BY name, id ASC";
            
            if ($limit > 0) {
                $sql .= " LIMIT " . intval($limit);
            }
            
            $questions = $DB->get_records_sql($sql);
            
            if (count($questions) > 5000) {
                // Pour les grandes bases, on ne traite que les noms exacts
                return self::get_duplicates_map_fast($questions, $use_cache);
            }
            
            $processed = [];
            $count = 0;
            // Timeout configurable : 60s par défaut, peut être augmenté via config.php
            $max_time = get_config('local_question_diagnostic', 'duplicate_detection_timeout');
            if (!$max_time || $max_time < 10) {
                $max_time = 60; // 60 secondes par défaut (augmenté de 30s)
            }
            $start_time = time();

            foreach ($questions as $question) {
                // Vérifier le timeout
                if (time() - $start_time > $max_time) {
                    debugging('Duplicate detection timeout - processed ' . $count . ' questions', DEBUG_DEVELOPER);
                    break;
                }
                
                if (in_array($question->id, $processed)) {
                    continue;
                }

                $duplicates = self::find_question_duplicates($question, 0.85);
                
                if (!empty($duplicates)) {
                    $duplicate_ids = array_map(function($q) { return $q->id; }, $duplicates);
                    $duplicates_map[$question->id] = $duplicate_ids;
                    
                    // Marquer les doublons comme traités pour éviter les calculs redondants
                    foreach ($duplicate_ids as $dup_id) {
                        if (!isset($duplicates_map[$dup_id])) {
                            $duplicates_map[$dup_id] = array_merge([$question->id], 
                                array_filter($duplicate_ids, function($id) use ($dup_id) { 
                                    return $id != $dup_id; 
                                })
                            );
                        }
                        $processed[] = $dup_id;
                    }
                }
                
                $processed[] = $question->id;
                $count++;
            }

            // Mettre en cache pour 1 heure
            if ($use_cache && !empty($duplicates_map)) {
                cache_manager::set(cache_manager::CACHE_DUPLICATES, 'duplicates_map', $duplicates_map);
            }

        } catch (\Exception $e) {
            debugging('Error in get_duplicates_map: ' . $e->getMessage(), DEBUG_DEVELOPER);
            // En cas d'erreur, retourner un map vide
        }

        return $duplicates_map;
    }

    /**
     * Version rapide de la détection de doublons (nom exact uniquement)
     * Utilisée pour les grandes bases de données
     *
     * @param array $questions Tableau de questions
     * @param bool $use_cache Utiliser le cache
     * @return array Map des doublons
     */
    private static function get_duplicates_map_fast($questions, $use_cache = true) {
        $duplicates_map = [];
        $name_groups = [];
        
        // Grouper par nom et type
        foreach ($questions as $question) {
            $key = strtolower(trim($question->name)) . '|' . $question->qtype;
            if (!isset($name_groups[$key])) {
                $name_groups[$key] = [];
            }
            $name_groups[$key][] = $question->id;
        }
        
        // Ne garder que les groupes avec plus d'une question
        foreach ($name_groups as $group) {
            if (count($group) > 1) {
                foreach ($group as $qid) {
                    $others = array_filter($group, function($id) use ($qid) {
                        return $id != $qid;
                    });
                    $duplicates_map[$qid] = array_values($others);
                }
            }
        }
        
        // Mettre en cache
        if ($use_cache && !empty($duplicates_map)) {
            cache_manager::set(cache_manager::CACHE_DUPLICATES, 'duplicates_map', $duplicates_map);
        }
        
        return $duplicates_map;
    }

    /**
     * Version ultra-simplifiée pour grandes bases (>10k questions)
     * ⚠️ v1.6.3 : Évite les requêtes lourdes avec JOIN sur grandes bases
     * @param int $total_questions Nombre total déjà compté
     * @return object Statistiques basiques
     */
    private static function get_global_stats_simple($total_questions) {
        global $DB;
        
        $stats = new \stdClass();
        $stats->total_questions = $total_questions;
        
        // Statistiques ultra-basiques (COUNT simples uniquement, pas de JOIN)
        try {
            $stats->by_type = [];
            $types = $DB->get_records_sql("
                SELECT qtype, COUNT(*) as count
                FROM {question}
                GROUP BY qtype
                ORDER BY count DESC
            ");
            foreach ($types as $type) {
                $stats->by_type[$type->qtype] = $type->count;
            }
        } catch (\Exception $e) {
            $stats->by_type = [];
        }
        
        // Calculer quand même l'usage (simplifié mais plus exact)
        try {
            // Comptage simple via quiz_slots (sans JOIN complexes)
            $columns = $DB->get_columns('quiz_slots');
            $used_in_quiz = 0;
            
            if (isset($columns['questionbankentryid'])) {
                // Moodle 4.1+ : Compter les questions via questionbankentryid (2 JOINs)
                $used_in_quiz = (int)$DB->count_records_sql("
                    SELECT COUNT(DISTINCT qv.questionid)
                    FROM {quiz_slots} qs
                    INNER JOIN {question_bank_entries} qbe ON qbe.id = qs.questionbankentryid
                    INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                ");
                } else if (isset($columns['questionid'])) {
                    // Moodle 4.0 uniquement : Comptage direct
                    // ⚠️ Note : Moodle 3.x NON supporté (architecture incompatible)
                    $used_in_quiz = (int)$DB->count_records_sql("
                    SELECT COUNT(DISTINCT qs.questionid) FROM {quiz_slots} qs
                ");
            }
            
            // Tentatives
            $used_in_attempts = (int)$DB->count_records_sql("
                SELECT COUNT(DISTINCT qa.questionid) FROM {question_attempts} qa
            ");
            
            $stats->used_questions = max($used_in_quiz, $used_in_attempts);
            $stats->unused_questions = $total_questions - $stats->used_questions;
        } catch (\Exception $e) {
            debugging('Error calculating usage in simple mode: ' . $e->getMessage(), DEBUG_DEVELOPER);
            // En cas d'erreur, approximation
            $stats->used_questions = 0;
            $stats->unused_questions = $total_questions;
        }
        
        // Questions cachées (calcul léger même pour grandes bases)
        try {
            $stats->hidden_questions = (int)$DB->count_records_sql("
                SELECT COUNT(DISTINCT qv.questionid)
                FROM {question_versions} qv
                WHERE qv.status = 'hidden'
            ");
            $stats->visible_questions = $total_questions - $stats->hidden_questions;
        } catch (\Exception $e) {
            debugging('Error calculating hidden questions in simple mode: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $stats->visible_questions = $total_questions; // Approximation
            $stats->hidden_questions = 0;
        }
        
        // Estimation rapide des doublons (GROUP BY simple, pas de calcul de similarité)
        try {
            $exact_name_dupes = $DB->get_records_sql("
                SELECT name, qtype, COUNT(*) as count
                FROM {question}
                GROUP BY name, qtype
                HAVING COUNT(*) > 1
            ");
            $stats->duplicate_questions = count($exact_name_dupes);
            $stats->total_duplicates = array_sum(array_map(function($d) { 
                return $d->count; 
            }, $exact_name_dupes));
        } catch (\Exception $e) {
            debugging('Error calculating duplicates in simple mode: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $stats->duplicate_questions = 0;
            $stats->total_duplicates = 0;
        }
        
        $stats->questions_with_broken_links = 0; // Non calculé (trop lourd)
        
        // Indicateur pour l'interface
        $stats->simplified = true;
        
        return $stats;
    }
    
    /**
     * Génère des statistiques globales
     *
     * @param bool $use_cache Utiliser le cache (défaut: true)
     * @param bool $include_duplicates Inclure les doublons (peut être lent)
     * @return object Statistiques globales
     */
    public static function get_global_stats($use_cache = true, $include_duplicates = true) {
        global $DB;

        // Essayer le cache d'abord
        require_once(__DIR__ . '/cache_manager.php');
        if ($use_cache) {
            $cache_key = 'stats_' . ($include_duplicates ? 'full' : 'light');
            $cached_stats = cache_manager::get(cache_manager::CACHE_GLOBALSTATS, $cache_key);
            if ($cached_stats !== false) {
                return $cached_stats;
            }
        }

        $stats = new \stdClass();
        
        try {
            // Total de questions
            $stats->total_questions = $DB->count_records('question');
            
            // 🚨 v1.6.3 : ULTRA-SIMPLIFICATION pour grandes bases
            // Si plus de 10 000 questions, on saute les calculs lourds
            if ($stats->total_questions > 10000) {
                return self::get_global_stats_simple($stats->total_questions);
            }
            
            // Questions par type
            $stats->by_type = [];
            $types = $DB->get_records_sql("
                SELECT qtype, COUNT(*) as count
                FROM {question}
                GROUP BY qtype
                ORDER BY count DESC
            ");
            foreach ($types as $type) {
                $stats->by_type[$type->qtype] = $type->count;
            }
            
            // Questions visibles/cachées - ⚠️ MOODLE 4.5 : utiliser question_versions.status
            $stats->visible_questions = (int)$DB->count_records_sql("
                SELECT COUNT(DISTINCT qv.questionid)
                FROM {question_versions} qv
                WHERE qv.status != 'hidden'
            ");
            $stats->hidden_questions = (int)$DB->count_records_sql("
                SELECT COUNT(DISTINCT qv.questionid)
                FROM {question_versions} qv
                WHERE qv.status = 'hidden'
            ");
            
            // Questions utilisées/inutilisées (calcul optimisé)
            // ⚠️ v1.6.4 : Compatibilité multi-version Moodle
            $used_in_quiz = 0;
            try {
                $columns = $DB->get_columns('quiz_slots');
                
                if (isset($columns['questionbankentryid'])) {
                    // Moodle 4.1+ : utilise questionbankentryid
                    $used_in_quiz = $DB->count_records_sql("
                        SELECT COUNT(DISTINCT qv.questionid)
                        FROM {quiz_slots} qs
                        INNER JOIN {question_bank_entries} qbe ON qbe.id = qs.questionbankentryid
                        INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                    ");
                } else if (isset($columns['questionid'])) {
                    // Moodle 4.0 uniquement : utilise questionid directement
                    // ⚠️ Note : Moodle 3.x NON supporté (architecture incompatible)
                    $used_in_quiz = $DB->count_records_sql("
                        SELECT COUNT(DISTINCT qs.questionid)
                        FROM {quiz_slots} qs
                    ");
                }
            } catch (\Exception $e) {
                debugging('Error counting quiz usage: ' . $e->getMessage(), DEBUG_DEVELOPER);
                $used_in_quiz = 0;
            }
            
            $used_in_attempts = $DB->count_records_sql("
                SELECT COUNT(DISTINCT qa.questionid)
                FROM {question_attempts} qa
            ");
            
            // Union des deux ensembles (approximation)
            $stats->used_questions = max($used_in_quiz, $used_in_attempts);
            $stats->unused_questions = $stats->total_questions - $stats->used_questions;
            
            // Questions en doublon (calcul lourd, optionnel)
            if ($include_duplicates && $stats->total_questions < 10000) {
                try {
                    $duplicates_map = self::get_duplicates_map($use_cache);
                    $stats->duplicate_questions = count($duplicates_map);
                    $stats->total_duplicates = array_sum(array_map('count', $duplicates_map));
                } catch (\Exception $e) {
                    debugging('Error calculating duplicates: ' . $e->getMessage(), DEBUG_DEVELOPER);
                    $stats->duplicate_questions = 0;
                    $stats->total_duplicates = 0;
                }
            } else {
                // Pour les grandes bases ou si non demandé, utiliser une estimation rapide
                $exact_name_dupes = $DB->get_records_sql("
                    SELECT name, qtype, COUNT(*) as count
                    FROM {question}
                    GROUP BY name, qtype
                    HAVING COUNT(*) > 1
                ");
                $stats->duplicate_questions = count($exact_name_dupes);
                $stats->total_duplicates = array_sum(array_map(function($d) { 
                    return $d->count; 
                }, $exact_name_dupes));
            }
            
            // Questions avec liens cassés (si la classe existe)
            if (class_exists('local_question_diagnostic\question_link_checker')) {
                try {
                    $broken_stats = question_link_checker::get_global_stats();
                    $stats->questions_with_broken_links = $broken_stats->questions_with_broken_links;
                } catch (\Exception $e) {
                    $stats->questions_with_broken_links = 0;
                }
            } else {
                $stats->questions_with_broken_links = 0;
            }
            
            // Mettre en cache
            if ($use_cache) {
                $cache_key = 'stats_' . ($include_duplicates ? 'full' : 'light');
                cache_manager::set(cache_manager::CACHE_GLOBALSTATS, $cache_key, $stats);
            }
            
        } catch (\Exception $e) {
            debugging('Error in get_global_stats: ' . $e->getMessage(), DEBUG_DEVELOPER);
            // Valeurs par défaut en cas d'erreur
            $stats->total_questions = 0;
            $stats->used_questions = 0;
            $stats->unused_questions = 0;
            $stats->duplicate_questions = 0;
            $stats->total_duplicates = 0;
            $stats->by_type = [];
            $stats->visible_questions = 0;
            $stats->hidden_questions = 0;
            $stats->questions_with_broken_links = 0;
        }

        return $stats;
    }

    /**
     * Génère l'URL pour accéder à une question dans la banque de questions
     *
     * 🔧 REFACTORED: Cette méthode utilise maintenant la fonction centralisée dans lib.php
     * @see local_question_diagnostic_get_question_bank_url()
     * 
     * @param object $question Objet question
     * @param object $category Objet catégorie (optionnel)
     * @return \moodle_url|null URL vers la banque de questions
     */
    public static function get_question_bank_url($question, $category = null) {
        global $DB;
        
        try {
            if (!$category) {
                // Récupérer la catégorie via question_bank_entries (Moodle 4.x)
                $category_sql = "SELECT qc.* 
                                FROM {question_categories} qc
                                INNER JOIN {question_bank_entries} qbe ON qbe.questioncategoryid = qc.id
                                INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                                WHERE qv.questionid = :questionid
                                LIMIT 1";
                $category = $DB->get_record_sql($category_sql, ['questionid' => $question->id]);
            }
            
            if (!$category) {
                return null;
            }
            
            // Utiliser la fonction centralisée avec l'ID de la question
            return local_question_diagnostic_get_question_bank_url($category, $question->id);
            
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Extrait un texte court depuis un HTML
     *
     * @param string $html Texte HTML
     * @param int $length Longueur maximale
     * @return string Extrait
     */
    private static function get_text_excerpt($html, $length = 100) {
        $text = strip_tags($html);
        $text = trim(preg_replace('/\s+/', ' ', $text));
        
        if (strlen($text) > $length) {
            $text = substr($text, 0, $length) . '...';
        }
        
        return $text;
    }

    /**
     * Purge tous les caches du plugin
     * 
     * 🔧 REFACTORED v1.9.27 : Utilise maintenant la classe CacheManager centralisée
     * @see \local_question_diagnostic\cache_manager::purge_all_caches()
     *
     * @return bool Succès de l'opération
     */
    public static function purge_all_caches() {
        require_once(__DIR__ . '/cache_manager.php');
        $results = cache_manager::purge_all_caches();
        
        // Retourner true si au moins un cache a été purgé
        return !empty(array_filter($results));
    }

    /**
     * Vérifie si PLUSIEURS questions peuvent être supprimées (VERSION BATCH OPTIMISÉE)
     * 🆕 v1.9.0 : Version batch pour éviter N+1 queries
     * 
     * @param array $questionids Tableau d'IDs de questions
     * @return array Map [question_id => {can_delete, reason, details}]
     */
    public static function can_delete_questions_batch($questionids) {
        global $DB;
        
        if (empty($questionids)) {
            return [];
        }
        
        $results = [];
        
        try {
            // Initialiser tous les résultats
            foreach ($questionids as $qid) {
                $results[$qid] = (object)[
                    'can_delete' => false,
                    'reason' => '',
                    'details' => []
                ];
            }
            
            // ÉTAPE 1 : Récupérer toutes les questions d'un coup
            list($insql, $params) = $DB->get_in_or_equal($questionids);
            $questions = $DB->get_records_select('question', "id $insql", $params, '', 'id, name, qtype, questiontext');
            
            // ÉTAPE 2 : Vérifier l'usage de TOUTES les questions en une seule requête
            $usage_map = self::get_questions_usage_by_ids($questionids);
            
            // ÉTAPE 2.5 : Vérifier le statut caché de TOUTES les questions en une seule requête
            // 🆕 v1.9.52 : Protection des questions cachées
            // ⚠️ MOODLE 4.5 : Le statut est dans question_versions.status (pas question.hidden)
            $hidden_map = [];
            try {
                list($insql_status, $params_status) = $DB->get_in_or_equal($questionids);
                $status_sql = "SELECT qv.questionid, qv.status
                              FROM {question_versions} qv
                              WHERE qv.questionid $insql_status";
                $status_records = $DB->get_records_sql($status_sql, $params_status);
                
                foreach ($status_records as $record) {
                    $hidden_map[$record->questionid] = ($record->status === 'hidden');
                }
            } catch (\Exception $e) {
                debugging('Error fetching question status: ' . $e->getMessage(), DEBUG_DEVELOPER);
                // En cas d'erreur, considérer toutes comme visibles (pas de protection excessive)
            }
            
            // ÉTAPE 3 : Trouver les doublons pour chaque question en cherchant dans TOUTE la base
            // 🔧 v1.9.51 FIX CRITIQUE : Ne PAS se limiter aux questions en paramètre !
            // Pour chaque question à vérifier, on doit chercher dans TOUTE la base de données
            // pour voir s'il existe d'autres questions avec le même nom+type
            
            // ÉTAPE 4 : Analyser chaque question
            foreach ($questions as $q) {
                $qid = $q->id;
                
                // Vérification 1 : Question utilisée ?
                // 🔧 v1.9.43 FIX CRITIQUE : Utiliser la clé 'quiz_count' directement au lieu d'itérer sur l'array
                // L'ancien code itérait sur les clés de l'array associatif (['quiz_count', 'quiz_list', ...])
                // ce qui comptait toujours 4 même pour les questions inutilisées !
                if (isset($usage_map[$qid]) && is_array($usage_map[$qid])) {
                    $quiz_count = isset($usage_map[$qid]['quiz_count']) ? $usage_map[$qid]['quiz_count'] : 0;
                    
                    if ($quiz_count > 0) {
                        $results[$qid]->reason = 'Question utilisée dans ' . $quiz_count . ' quiz';
                        $results[$qid]->details['quiz_count'] = $quiz_count;
                        continue;
                    }
                }
                
                // Vérification 2 : Question cachée ?
                // 🆕 v1.9.52 : Protéger TOUTES les questions cachées contre la suppression
                if (isset($hidden_map[$qid]) && $hidden_map[$qid] === true) {
                    $results[$qid]->reason = 'Question cachée (protégée)';
                    $results[$qid]->details['is_hidden'] = true;
                    $results[$qid]->details['debug_name'] = $q->name;
                    $results[$qid]->details['debug_type'] = $q->qtype;
                    continue;
                }
                
                // Vérification 3 : Question a des doublons ?
                // 🔧 v1.9.51 FIX CRITIQUE : Chercher TOUTES les questions avec ce nom+type dans la BASE
                // (pas seulement parmi les questions passées en paramètre !)
                $all_with_same_signature = $DB->get_records('question', [
                    'name' => $q->name,
                    'qtype' => $q->qtype
                ]);
                
                // Compter combien il y en a (en excluant la question elle-même)
                $duplicate_count = 0;
                $duplicate_ids = [];
                foreach ($all_with_same_signature as $other) {
                    if ($other->id != $qid) {
                        $duplicate_count++;
                        $duplicate_ids[] = $other->id;
                    }
                }
                
                if ($duplicate_count == 0) {
                    $results[$qid]->reason = 'Question unique (pas de doublon)';
                    $results[$qid]->details['is_unique'] = true;
                    $results[$qid]->details['debug_signature'] = $q->name . '|||' . $q->qtype;
                    $results[$qid]->details['debug_name'] = $q->name;
                    $results[$qid]->details['debug_type'] = $q->qtype;
                    continue;
                }
                
                // Si on arrive ici : question inutilisée ET en doublon → SUPPRIMABLE
                $results[$qid]->can_delete = true;
                $results[$qid]->reason = 'Doublon inutilisé';
                $results[$qid]->details['duplicate_count'] = $duplicate_count;
                $results[$qid]->details['duplicate_ids'] = $duplicate_ids;
                $results[$qid]->details['debug_signature'] = $q->name . '|||' . $q->qtype;
                $results[$qid]->details['debug_name'] = $q->name;
                $results[$qid]->details['debug_type'] = $q->qtype;
            }
            
        } catch (\Exception $e) {
            debugging('Error in can_delete_questions_batch: ' . $e->getMessage(), DEBUG_DEVELOPER);
            // En cas d'erreur, marquer toutes comme non supprimables
            foreach ($questionids as $qid) {
                if (!isset($results[$qid])) {
                    $results[$qid] = (object)[
                        'can_delete' => false,
                        'reason' => 'Erreur de vérification',
                        'details' => []
                    ];
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Vérifie si une question peut être supprimée en toute sécurité
     * 
     * 🗑️ REMOVED v1.9.27 : Méthode dépréciée supprimée
     * 
     * Cette méthode était marquée DEPRECATED et causait des problèmes de performance (N+1 queries).
     * Utiliser à la place : can_delete_questions_batch() qui est optimisée pour traiter
     * plusieurs questions en une seule fois.
     * 
     * @deprecated Utiliser can_delete_questions_batch() pour de meilleures performances
     * @param int $questionid ID de la question
     * @return object Objet avec can_delete (bool), reason (string), details (array)
     */
    public static function can_delete_question($questionid) {
        // 🔧 REFACTORED v1.9.27 : Appeler la version batch pour une seule question
        $results = self::can_delete_questions_batch([$questionid]);
        return isset($results[$questionid]) ? $results[$questionid] : (object)[
            'can_delete' => false,
            'reason' => 'Erreur de vérification',
            'details' => []
        ];
    }
    
    /**
     * Supprime une question en toute sécurité (avec vérifications)
     * Utilise l'API Moodle pour supprimer proprement
     *
     * @param int $questionid ID de la question
     * @return bool|string True si succès, message d'erreur sinon
     */
    public static function delete_question_safe($questionid) {
        global $DB, $CFG;
        
        require_once($CFG->dirroot . '/question/editlib.php');
        
        // Vérifier si la suppression est autorisée
        $check = self::can_delete_question($questionid);
        
        if (!$check->can_delete) {
            return 'Suppression interdite : ' . $check->reason;
        }
        
        try {
            // Récupérer la question et sa catégorie
            $question = $DB->get_record('question', ['id' => $questionid], '*', MUST_EXIST);
            
            // Récupérer la catégorie via question_bank_entries (Moodle 4.x)
            $category_sql = "SELECT qc.* 
                            FROM {question_categories} qc
                            INNER JOIN {question_bank_entries} qbe ON qbe.questioncategoryid = qc.id
                            INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                            WHERE qv.questionid = :questionid
                            LIMIT 1";
            $category = $DB->get_record_sql($category_sql, ['questionid' => $questionid]);
            
            if (!$category) {
                return 'Catégorie de la question introuvable';
            }
            
            // Utiliser l'API Moodle pour supprimer proprement la question
            // Cela gère automatiquement :
            // - Les entrées dans question_bank_entries
            // - Les versions dans question_versions
            // - Les fichiers associés
            // - Les données spécifiques au type de question
            question_delete_question($questionid);
            
            return true;
            
        } catch (\Exception $e) {
            return 'Erreur lors de la suppression : ' . $e->getMessage();
        }
    }

    /**
     * Exporte les questions au format CSV
     *
     * @param array $questions Tableau de questions avec stats
     * @return string Contenu CSV
     */
    public static function export_to_csv($questions) {
        $csv = "ID,Nom,Type,Catégorie,Contexte,Créateur,Date création,Date modification,Visible,Utilisée,Quiz,Tentatives,Doublons\n";
        
        foreach ($questions as $item) {
            $q = $item->question;
            $s = $item->stats;
            
            $csv .= sprintf(
                "%d,\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",%d,%d,%d\n",
                $q->id,
                str_replace('"', '""', $q->name),
                $q->qtype,
                str_replace('"', '""', $s->category_name),
                str_replace('"', '""', $s->context_name),
                str_replace('"', '""', $s->creator_name),
                $s->created_formatted,
                $s->modified_formatted,
                $s->is_hidden ? 'Non' : 'Oui',
                $s->is_used ? 'Oui' : 'Non',
                $s->used_in_quizzes,
                $s->attempt_count,
                $s->duplicate_count
            );
        }
        
        return $csv;
    }
    
    /**
     * Récupère les groupes de questions en doublon
     * 
     * Un groupe de doublons = questions avec même nom ET même type
     * 
     * 🆕 v1.9.53 : OPTIMISATION - Prioriser les groupes avec questions supprimables
     * 
     * @param int $limit Nombre de groupes à retourner (0 = tous)
     * @param int $offset Offset pour la pagination
     * @param bool $used_only Si true, ne retourner que les groupes avec au moins 1 version utilisée
     * @param bool $deletable_only Si true, ne retourner que les groupes avec au moins 1 version supprimable (priorise le nettoyage)
     * @return array Tableau d'objets représentant chaque groupe de doublons
     */
    public static function get_duplicate_groups($limit = 0, $offset = 0, $used_only = false, $deletable_only = false) {
        global $DB;
        
        // 🎯 v1.9.45 : Nouvelle méthode pour récupérer les groupes de doublons
        // 🆕 v1.9.53 : OPTIMISATION - Filtrage et priorisation des groupes supprimables
        // Grouper les questions par nom + type et ne garder que ceux qui ont des doublons (COUNT > 1)
        
        // Étape 1 : Récupérer tous les groupes avec doublons (name + qtype + count)
        $sql = "SELECT q.name, q.qtype, COUNT(*) as dup_count, MIN(q.id) as representative_id
                FROM {question} q
                GROUP BY q.name, q.qtype
                HAVING COUNT(*) > 1
                ORDER BY dup_count DESC";
        
        $all_groups = $DB->get_records_sql($sql);
        
        if (empty($all_groups)) {
            return [];
        }
        
        // 🆕 v1.9.53 : Si deletable_only = true, on va trier les groupes
        // pour mettre en priorité ceux qui ont le plus de versions supprimables
        $groups_with_priority = [];
        
        // Étape 2 : Pour chaque groupe, récupérer les détails
        $groups = [];
        $current_index = 0;
        
        foreach ($all_groups as $group) {
            // Récupérer tous les IDs des questions de ce groupe
            $question_ids = $DB->get_fieldset_select('question', 'id', 
                'name = :name AND qtype = :qtype',
                ['name' => $group->name, 'qtype' => $group->qtype]
            );
            
            if (empty($question_ids)) {
                continue;
            }
            
            // Charger l'usage de toutes les questions de ce groupe en batch
            $usage_map = self::get_questions_usage_by_ids($question_ids);
            
            // 🆕 v1.9.53 : Vérifier la supprimabilité si demandé
            $deletability_map = [];
            if ($deletable_only) {
                $deletability_map = self::can_delete_questions_batch($question_ids);
            }
            
            // Compter combien sont utilisées vs inutilisées vs supprimables
            $used_count = 0;
            $unused_count = 0;
            $deletable_count = 0; // 🆕 v1.9.53
            
            foreach ($question_ids as $qid) {
                $is_used = isset($usage_map[$qid]) && isset($usage_map[$qid]['quiz_count']) && $usage_map[$qid]['quiz_count'] > 0;
                
                if ($is_used) {
                    $used_count++;
                } else {
                    $unused_count++;
                }
                
                // 🆕 v1.9.53 : Compter les questions réellement supprimables
                if ($deletable_only && isset($deletability_map[$qid]) && $deletability_map[$qid]->can_delete) {
                    $deletable_count++;
                }
            }
            
            // 🆕 v1.9.53 : Si deletable_only et aucune version supprimable, on skip ce groupe
            if ($deletable_only && $deletable_count == 0) {
                continue;
            }
            
            // Si filtre "used_only" et aucune version utilisée, on skip ce groupe
            if ($used_only && $used_count == 0) {
                continue;
            }
            
            // Créer l'objet groupe
            $group_obj = (object)[
                'question_name' => $group->name,
                'qtype' => $group->qtype,
                'duplicate_count' => $group->dup_count,
                'representative_id' => $group->representative_id,
                'all_question_ids' => $question_ids,
                'used_count' => $used_count,
                'unused_count' => $unused_count,
                'deletable_count' => $deletable_count, // 🆕 v1.9.53
                'priority_score' => $deletable_count // 🆕 v1.9.53 : Score pour tri
            ];
            
            $groups_with_priority[] = $group_obj;
        }
        
        // 🆕 v1.9.53 : Trier les groupes par nombre de versions supprimables (décroissant)
        // Les groupes avec le plus de doublons supprimables apparaissent en premier
        if ($deletable_only) {
            usort($groups_with_priority, function($a, $b) {
                // Priorité 1 : Nombre de versions supprimables (décroissant)
                if ($a->deletable_count != $b->deletable_count) {
                    return $b->deletable_count - $a->deletable_count;
                }
                // Priorité 2 : Nombre total de doublons (décroissant)
                return $b->duplicate_count - $a->duplicate_count;
            });
        }
        
        // Appliquer la pagination
        $total = count($groups_with_priority);
        $groups = array_slice($groups_with_priority, $offset, $limit > 0 ? $limit : null);
        
        return $groups;
    }
    
    /**
     * Compte le nombre total de groupes de doublons
     * 
     * 🆕 v1.9.53 : Support du paramètre deletable_only
     * 
     * @param bool $used_only Si true, ne compter que les groupes avec au moins 1 version utilisée
     * @param bool $deletable_only Si true, ne compter que les groupes avec au moins 1 version supprimable
     * @return int Nombre de groupes de doublons
     */
    public static function count_duplicate_groups($used_only = false, $deletable_only = false) {
        global $DB;
        
        // 🎯 v1.9.45 : Compter le nombre total de groupes
        $sql = "SELECT q.name, q.qtype, COUNT(*) as dup_count
                FROM {question} q
                GROUP BY q.name, q.qtype
                HAVING COUNT(*) > 1";
        
        $all_groups = $DB->get_records_sql($sql);
        
        // Si aucun filtre, retourner le total
        if (!$used_only && !$deletable_only) {
            return count($all_groups);
        }
        
        // Si filtre actif, on doit compter manuellement (plus lent mais nécessaire)
        $count = 0;
        foreach ($all_groups as $group) {
            // Récupérer les IDs des questions de ce groupe
            $question_ids = $DB->get_fieldset_select('question', 'id',
                'name = :name AND qtype = :qtype',
                ['name' => $group->name, 'qtype' => $group->qtype]
            );
            
            if (empty($question_ids)) {
                continue;
            }
            
            // Charger l'usage en batch
            $usage_map = self::get_questions_usage_by_ids($question_ids);
            
            // 🆕 v1.9.53 : Charger la supprimabilité si demandé
            $deletability_map = [];
            if ($deletable_only) {
                $deletability_map = self::can_delete_questions_batch($question_ids);
            }
            
            // Vérifier les conditions
            $has_used = false;
            $has_deletable = false;
            
            foreach ($question_ids as $qid) {
                // Vérifier si utilisée
                if (isset($usage_map[$qid]) && isset($usage_map[$qid]['quiz_count']) && $usage_map[$qid]['quiz_count'] > 0) {
                    $has_used = true;
                }
                
                // 🆕 v1.9.53 : Vérifier si supprimable
                if ($deletable_only && isset($deletability_map[$qid]) && $deletability_map[$qid]->can_delete) {
                    $has_deletable = true;
                }
                
                // Si on a trouvé ce qu'on cherche, on peut arrêter
                if ((!$used_only || $has_used) && (!$deletable_only || $has_deletable)) {
                    break;
                }
            }
            
            // Appliquer les filtres
            $include = true;
            if ($used_only && !$has_used) {
                $include = false;
            }
            if ($deletable_only && !$has_deletable) {
                $include = false;
            }
            
            if ($include) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Génère les statistiques de prévisualisation pour le nettoyage global des doublons
     * 
     * 🆕 v1.9.52 : Pour le nettoyage global
     * 
     * Analyse TOUS les groupes de doublons et calcule :
     * - Nombre total de groupes
     * - Nombre de questions à supprimer
     * - Nombre de questions à conserver
     * - Répartition par type de question
     * - Liste détaillée des questions à supprimer (pour export CSV)
     * 
     * @return object Statistiques de prévisualisation
     */
    public static function get_cleanup_preview_stats() {
        global $DB;
        
        $stats = new \stdClass();
        
        // Récupérer TOUS les groupes de doublons (limit = 0)
        $all_groups = self::get_duplicate_groups(0, 0, false);
        
        $stats->total_groups = count($all_groups);
        $stats->total_questions_to_delete = 0;
        $stats->total_questions_to_keep = 0;
        $stats->by_type = []; // [qtype => ['to_delete' => N, 'to_keep' => M]]
        $stats->questions_list = []; // Liste détaillée pour export CSV
        
        foreach ($all_groups as $group) {
            // Analyser chaque groupe
            $question_ids = $group->all_question_ids;
            
            if (empty($question_ids)) {
                continue;
            }
            
            $usage_map = self::get_questions_usage_by_ids($question_ids);
            
            // Récupérer les questions complètes pour détails
            $questions = $DB->get_records_list('question', 'id', $question_ids, 'id ASC');
            
            $unused = [];
            $used = [];
            
            foreach ($questions as $q) {
                $quiz_count = isset($usage_map[$q->id]['quiz_count']) ? 
                             $usage_map[$q->id]['quiz_count'] : 0;
                
                if ($quiz_count > 0) {
                    $used[] = $q;
                } else {
                    $unused[] = $q;
                }
            }
            
            // Sécurité : Garder au moins 1 version si aucune utilisée
            if (empty($used) && !empty($unused)) {
                $oldest = array_shift($unused);
                $used[] = $oldest;
            }
            
            $group_to_delete = count($unused);
            $group_to_keep = count($used);
            
            // Accumuler les totaux
            $stats->total_questions_to_delete += $group_to_delete;
            $stats->total_questions_to_keep += $group_to_keep;
            
            // Accumuler par type
            if (!isset($stats->by_type[$group->qtype])) {
                $stats->by_type[$group->qtype] = [
                    'to_delete' => 0,
                    'to_keep' => 0
                ];
            }
            $stats->by_type[$group->qtype]['to_delete'] += $group_to_delete;
            $stats->by_type[$group->qtype]['to_keep'] += $group_to_keep;
            
            // Liste détaillée pour CSV (seulement les questions à supprimer)
            foreach ($unused as $q) {
                $stats->questions_list[] = (object)[
                    'id' => $q->id,
                    'name' => $q->name,
                    'qtype' => $q->qtype,
                    'timecreated' => $q->timecreated,
                    'action' => 'delete'
                ];
            }
        }
        
        // Estimation du temps (environ 0.5s par groupe)
        $stats->estimated_time_seconds = $stats->total_groups * 0.5;
        $stats->estimated_batches = ceil($stats->total_groups / 10);
        
        return $stats;
    }
}

