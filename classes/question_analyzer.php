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
     * @return array Tableau des questions avec métadonnées
     */
    public static function get_all_questions_with_stats($include_duplicates = true, $limit = 0) {
        global $DB;

        // Récupérer les questions avec limite - Utiliser l'API Moodle pour compatibilité multi-SGBD
        if ($limit > 0) {
            $questions = $DB->get_records('question', null, 'id DESC', '*', 0, $limit);
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
                // Moodle 3.x/4.0 : utilise questionid directement
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
                    // Moodle 3.x/4.0 : utilise questionid directement
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
        $cache = \cache::make('local_question_diagnostic', 'questionusage');
        $cached_usage = $cache->get('usage_map');
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
                // Moodle 3.x/4.0 : utilise questionid directement
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
            $cache->set('usage_map', $usage_map);

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
     * 
     * @param int $limit Limite de questions à retourner
     * @return array Tableau des questions (objets simples)
     */
    public static function get_used_duplicates_questions($limit = 100) {
        global $DB;
        
        try {
            // 🆕 v1.9.4 : Approche simplifiée comme dans le test aléatoire
            // Étape 1 : Identifier directement les groupes de doublons (limité à 20 groupes max)
            $sql = "SELECT CONCAT(q.name, '|', q.qtype) as signature,
                           MIN(q.id) as sample_id,
                           COUNT(DISTINCT q.id) as question_count
                    FROM {question} q
                    GROUP BY q.name, q.qtype
                    HAVING COUNT(DISTINCT q.id) > 1
                    LIMIT 20";
            
            $duplicate_groups = $DB->get_records_sql($sql);
            
            if (empty($duplicate_groups)) {
                return [];
            }
            
            // Étape 2 : Pour chaque groupe, vérifier si au moins 1 version est utilisée
            $result_questions = [];
            
            foreach ($duplicate_groups as $group) {
                // Récupérer la question exemple
                $sample = $DB->get_record('question', ['id' => $group->sample_id]);
                if (!$sample) {
                    continue;
                }
                
                // Récupérer toutes les questions de ce groupe (même nom + même type)
                $questions_in_group = $DB->get_records('question', [
                    'name' => $sample->name,
                    'qtype' => $sample->qtype
                ]);
                
                if (count($questions_in_group) <= 1) {
                    continue; // Pas vraiment un groupe
                }
                
                // Vérifier l'usage en BATCH (1 seule requête pour tout le groupe)
                $group_ids = array_keys($questions_in_group);
                $usage_map = self::get_questions_usage_by_ids($group_ids);
                
                // Vérifier si au moins une version est utilisée
                $has_used = false;
                foreach ($group_ids as $qid) {
                    if (isset($usage_map[$qid]) && !empty($usage_map[$qid])) {
                        $has_used = true;
                        break;
                    }
                }
                
                // Si au moins une est utilisée, ajouter toutes les versions du groupe
                if ($has_used) {
                    foreach ($questions_in_group as $q) {
                        $result_questions[] = $q;
                        if (count($result_questions) >= $limit) {
                            break 2; // Sortir des deux boucles
                        }
                    }
                }
            }
            
            return $result_questions;
            
        } catch (\Exception $e) {
            debugging('Error in get_used_duplicates_questions: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
    }
    
    /**
     * Trouve les doublons EXACTS d'une question (même nom, type et texte)
     * 🆕 v1.7.0 : Pour le test aléatoire
     * 
     * @param object $question Objet question
     * @return array Tableau des questions en doublon strict
     */
    public static function find_exact_duplicates($question) {
        global $DB;
        
        try {
            // Recherche stricte : même nom ET même type ET même texte
            $sql = "SELECT q.*
                    FROM {question} q
                    WHERE q.name = :name
                    AND q.qtype = :qtype
                    AND q.questiontext = :questiontext
                    AND q.id != :questionid
                    ORDER BY q.id";
            
            $duplicates = $DB->get_records_sql($sql, [
                'name' => $question->name,
                'qtype' => $question->qtype,
                'questiontext' => $question->questiontext,
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
     * @param object $question Objet question
     * @param float $threshold Seuil de similarité (0-1)
     * @return array Tableau des questions en doublon
     */
    public static function find_question_duplicates($question, $threshold = 0.85) {
        global $DB;

        $duplicates = [];

        try {
            // Étape 1 : Recherche exacte par nom
            $exact_name_matches = $DB->get_records('question', [
                'name' => $question->name,
                'qtype' => $question->qtype
            ]);

            foreach ($exact_name_matches as $match) {
                if ($match->id != $question->id) {
                    $similarity = self::calculate_question_similarity($question, $match);
                    if ($similarity >= $threshold) {
                        $match->similarity_score = $similarity;
                        $duplicates[] = $match;
                    }
                }
            }

            // Étape 2 : Recherche par nom similaire (si pas trop de résultats)
            if (count($duplicates) < 10) {
                $name_pattern = '%' . $DB->sql_like_escape(substr($question->name, 0, 20)) . '%';
                $similar_name_matches = $DB->get_records_sql("
                    SELECT * FROM {question}
                    WHERE " . $DB->sql_like('name', ':pattern') . "
                    AND qtype = :qtype
                    AND id != :qid
                    LIMIT 50
                ", [
                    'pattern' => $name_pattern,
                    'qtype' => $question->qtype,
                    'qid' => $question->id
                ]);

                foreach ($similar_name_matches as $match) {
                    // Éviter les doublons dans le résultat
                    if (!in_array($match->id, array_column($duplicates, 'id'))) {
                        $similarity = self::calculate_question_similarity($question, $match);
                        if ($similarity >= $threshold) {
                            $match->similarity_score = $similarity;
                            $duplicates[] = $match;
                        }
                    }
                }
            }

        } catch (\Exception $e) {
            // En cas d'erreur, retourner un tableau vide
        }

        return $duplicates;
    }

    /**
     * Calcule le score de similarité entre deux questions
     *
     * @param object $q1 Question 1
     * @param object $q2 Question 2
     * @return float Score de similarité (0-1)
     */
    private static function calculate_question_similarity($q1, $q2) {
        $score = 0;
        $weights = [
            'name' => 0.3,
            'text' => 0.4,
            'type' => 0.2,
            'category' => 0.1
        ];

        // Similarité du nom
        $name1 = strtolower(trim($q1->name));
        $name2 = strtolower(trim($q2->name));
        $name_similarity = 0;
        
        if ($name1 === $name2) {
            $name_similarity = 1.0;
        } else {
            similar_text($name1, $name2, $name_similarity);
            $name_similarity = $name_similarity / 100;
        }
        $score += $name_similarity * $weights['name'];

        // Similarité du texte
        $text1 = strtolower(strip_tags(trim($q1->questiontext)));
        $text2 = strtolower(strip_tags(trim($q2->questiontext)));
        $text_similarity = 0;
        
        if (!empty($text1) && !empty($text2)) {
            similar_text($text1, $text2, $text_similarity);
            $text_similarity = $text_similarity / 100;
        }
        $score += $text_similarity * $weights['text'];

        // Même type
        if ($q1->qtype === $q2->qtype) {
            $score += $weights['type'];
        }

        // Même catégorie (récupérer via question_bank_entries pour Moodle 4.x)
        $cat1_id = self::get_question_category_id($q1->id);
        $cat2_id = self::get_question_category_id($q2->id);
        if ($cat1_id && $cat2_id && $cat1_id === $cat2_id) {
            $score += $weights['category'];
        }

        return round($score, 3);
    }

    /**
     * Récupère l'ID de catégorie d'une question (Moodle 4.x compatible)
     *
     * @param int $questionid ID de la question
     * @return int|null ID de la catégorie
     */
    private static function get_question_category_id($questionid) {
        global $DB;
        
        try {
            $sql = "SELECT qbe.questioncategoryid 
                    FROM {question_bank_entries} qbe
                    INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                    INNER JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                    WHERE qv.questionid = :questionid
                    LIMIT 1";
            $result = $DB->get_record_sql($sql, ['questionid' => $questionid]);
            return $result ? $result->questioncategoryid : null;
        } catch (\Exception $e) {
            return null;
        }
    }

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
        if ($use_cache) {
            $cache = \cache::make('local_question_diagnostic', 'duplicates');
            $cached_map = $cache->get('duplicates_map');
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
                $cache = \cache::make('local_question_diagnostic', 'duplicates');
                $cache->set('duplicates_map', $duplicates_map);
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
            $cache = \cache::make('local_question_diagnostic', 'duplicates');
            $cache->set('duplicates_map', $duplicates_map);
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
                // Moodle 3.x/4.0 : Comptage direct
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
        
        // Approximations pour stats moins critiques
        $stats->visible_questions = $total_questions; // Approximation
        $stats->hidden_questions = 0; // Non calculé (nécessite JOIN avec question_versions)
        $stats->duplicate_questions = 0; // Non calculé
        $stats->total_duplicates = 0; // Non calculé
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
        if ($use_cache) {
            $cache = \cache::make('local_question_diagnostic', 'globalstats');
            $cache_key = 'stats_' . ($include_duplicates ? 'full' : 'light');
            $cached_stats = $cache->get($cache_key);
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
                    // Moodle 3.x/4.0 : utilise questionid directement
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
                $cache = \cache::make('local_question_diagnostic', 'globalstats');
                $cache_key = 'stats_' . ($include_duplicates ? 'full' : 'light');
                $cache->set($cache_key, $stats);
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
            
            $context = \context::instance_by_id($category->contextid, IGNORE_MISSING);
            
            if (!$context) {
                return null;
            }
            
            $courseid = 0;
            
            if ($context->contextlevel == CONTEXT_COURSE) {
                $courseid = $context->instanceid;
            } else if ($context->contextlevel == CONTEXT_MODULE) {
                $coursecontext = $context->get_course_context(false);
                if ($coursecontext) {
                    $courseid = $coursecontext->instanceid;
                }
            } else if ($context->contextlevel == CONTEXT_SYSTEM) {
                // 🔧 FIX: Pour contexte système, utiliser SITEID au lieu de 0
                // courseid=0 cause l'erreur "course not found"
                $courseid = SITEID;
            }
            
            // ⚠️ v1.6.7 : Vérifier et corriger le courseid AVANT de générer l'URL
            // Si courseid = 0 ou cours n'existe pas, utiliser SITEID comme fallback
            if ($courseid <= 0 || !$DB->record_exists('course', ['id' => $courseid])) {
                $courseid = SITEID;
            }
            
            // Dernière vérification : si SITEID n'existe pas non plus (rare), retourner null
            if (!$DB->record_exists('course', ['id' => $courseid])) {
                return null;
            }
            
            $url = new \moodle_url('/question/edit.php', [
                'courseid' => $courseid,
                'cat' => $category->id . ',' . $category->contextid,
                'qid' => $question->id
            ]);
            
            return $url;
            
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
     * @return bool Succès de l'opération
     */
    public static function purge_all_caches() {
        try {
            $cache_duplicates = \cache::make('local_question_diagnostic', 'duplicates');
            $cache_duplicates->purge();
            
            $cache_stats = \cache::make('local_question_diagnostic', 'globalstats');
            $cache_stats->purge();
            
            $cache_usage = \cache::make('local_question_diagnostic', 'questionusage');
            $cache_usage->purge();
            
            return true;
        } catch (\Exception $e) {
            debugging('Error purging caches: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
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
            
            // ÉTAPE 3 : Trouver les doublons pour chaque question (groupé par signature)
            // Créer un map de signatures → liste de questions
            $signature_map = [];
            foreach ($questions as $q) {
                $signature = md5($q->name . '|' . $q->qtype . '|' . $q->questiontext);
                if (!isset($signature_map[$signature])) {
                    $signature_map[$signature] = [];
                }
                $signature_map[$signature][] = $q->id;
            }
            
            // ÉTAPE 4 : Analyser chaque question
            foreach ($questions as $q) {
                $qid = $q->id;
                
                // Vérification 1 : Question utilisée ?
                if (isset($usage_map[$qid])) {
                    $usage = $usage_map[$qid];
                    if (!empty($usage)) {
                        $quiz_count = 0;
                        foreach ($usage as $u) {
                            $quiz_count++;
                        }
                        
                        if ($quiz_count > 0) {
                            $results[$qid]->reason = 'Question utilisée dans ' . $quiz_count . ' quiz';
                            $results[$qid]->details['quiz_count'] = $quiz_count;
                            continue;
                        }
                    }
                }
                
                // Vérification 2 : Question a des doublons ?
                $signature = md5($q->name . '|' . $q->qtype . '|' . $q->questiontext);
                $duplicate_ids = $signature_map[$signature];
                
                // Enlever la question elle-même
                $duplicate_ids = array_filter($duplicate_ids, function($id) use ($qid) {
                    return $id != $qid;
                });
                
                if (count($duplicate_ids) == 0) {
                    $results[$qid]->reason = 'Question unique (pas de doublon)';
                    $results[$qid]->details['is_unique'] = true;
                    continue;
                }
                
                // Si on arrive ici : question inutilisée ET en doublon → SUPPRIMABLE
                $results[$qid]->can_delete = true;
                $results[$qid]->reason = 'Doublon inutilisé';
                $results[$qid]->details['duplicate_count'] = count($duplicate_ids);
                $results[$qid]->details['duplicate_ids'] = array_values($duplicate_ids);
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
     * 🚨 DEPRECATED : Utiliser can_delete_questions_batch() pour de meilleures performances
     * 
     * Règles de protection :
     * 1. Question utilisée dans un quiz → NON SUPPRIMABLE
     * 2. Question avec tentatives → NON SUPPRIMABLE
     * 3. Question unique (pas de doublon) → NON SUPPRIMABLE
     * 4. Question en doublon ET inutilisée → SUPPRIMABLE
     *
     * @param int $questionid ID de la question
     * @return object Objet avec can_delete (bool), reason (string), details (array)
     */
    public static function can_delete_question($questionid) {
        global $DB;
        
        $result = new \stdClass();
        $result->can_delete = false;
        $result->reason = '';
        $result->details = [];
        
        try {
            // Récupérer la question
            $question = $DB->get_record('question', ['id' => $questionid]);
            if (!$question) {
                $result->reason = 'Question introuvable';
                return $result;
            }
            
            // Vérification 1 : La question est-elle utilisée ?
            $usage = self::get_question_usage($questionid);
            
            if ($usage['is_used']) {
                $result->reason = 'Question utilisée';
                $result->details['quiz_count'] = $usage['quiz_count'];
                $result->details['attempt_count'] = $usage['attempt_count'];
                $result->details['quiz_list'] = $usage['quiz_list'];
                return $result;
            }
            
            // Vérification 2 : La question a-t-elle des doublons ?
            $duplicates = self::find_exact_duplicates($question);
            
            if (count($duplicates) == 0) {
                $result->reason = 'Question unique (pas de doublon)';
                $result->details['is_unique'] = true;
                return $result;
            }
            
            // Si on arrive ici : question inutilisée ET en doublon → SUPPRIMABLE
            $result->can_delete = true;
            $result->reason = 'Question supprimable (doublon inutilisé)';
            $result->details['duplicate_count'] = count($duplicates);
            $result->details['duplicate_ids'] = array_map(function($q) { return $q->id; }, $duplicates);
            
        } catch (\Exception $e) {
            $result->reason = 'Erreur lors de la vérification : ' . $e->getMessage();
        }
        
        return $result;
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
}

