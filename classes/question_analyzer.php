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
     * @return array Tableau des questions avec métadonnées
     */
    public static function get_all_questions_with_stats() {
        global $DB;

        // Récupérer toutes les questions
        $questions = $DB->get_records('question', null, 'id ASC');
        $result = [];

        // Préparer les données en masse pour optimiser les performances
        $usage_map = self::get_all_questions_usage();
        $duplicates_map = self::get_duplicates_map();

        foreach ($questions as $question) {
            $stats = self::get_question_stats($question, $usage_map, $duplicates_map);
            $result[] = (object)[
                'question' => $question,
                'stats' => $stats,
            ];
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
                            JOIN {question_bank_entries} qbe ON qbe.questioncategoryid = qc.id
                            JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                            WHERE qv.questionid = :questionid
                            LIMIT 1";
            $category = $DB->get_record_sql($category_sql, ['questionid' => $question->id]);
            $stats->category_name = $category ? format_string($category->name) : 'Inconnue';
            $stats->category_id = $category ? $category->id : 0;
            
            // Contexte
            if ($category) {
                try {
                    $context = \context::instance_by_id($category->contextid, IGNORE_MISSING);
                    $stats->context_name = $context ? \context_helper::get_level_name($context->contextlevel) : 'Inconnu';
                    $stats->context_id = $category->contextid;
                } catch (\Exception $e) {
                    $stats->context_name = 'Erreur';
                    $stats->context_id = 0;
                }
            } else {
                $stats->context_name = 'Inconnu';
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
            
            // Statut
            $stats->is_hidden = $question->hidden == 1;
            $stats->status = $stats->is_hidden ? 'hidden' : 'visible';
            
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
            // Compatible Moodle 4.x avec question_bank_entries
            $sql = "SELECT DISTINCT q.id, q.name, q.course
                    FROM {quiz} q
                    INNER JOIN {quiz_slots} qs ON qs.quizid = q.id
                    WHERE qs.questionid = :questionid
                    OR qs.questioncategoryid IN (
                        SELECT qbe.questioncategoryid 
                        FROM {question_bank_entries} qbe
                        JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                        WHERE qv.questionid = :questionid2
                    )";
            
            $quizzes = $DB->get_records_sql($sql, [
                'questionid' => $questionid,
                'questionid2' => $questionid
            ]);

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
     * Pré-calcule l'usage de toutes les questions (optimisation)
     *
     * @return array Map [question_id => usage_info]
     */
    private static function get_all_questions_usage() {
        global $DB;

        $usage_map = [];

        try {
            // Récupérer toutes les questions dans des quiz
            $quiz_usage = $DB->get_records_sql("
                SELECT q.id, qu.id as quiz_id, qu.name as quiz_name, qu.course
                FROM {question} q
                LEFT JOIN {quiz_slots} qs ON qs.questionid = q.id
                LEFT JOIN {quiz} qu ON qu.id = qs.quizid
                WHERE qu.id IS NOT NULL
            ");

            foreach ($quiz_usage as $record) {
                if (!isset($usage_map[$record->id])) {
                    $usage_map[$record->id] = [
                        'quiz_count' => 0,
                        'quiz_list' => [],
                        'attempt_count' => 0,
                        'is_used' => false
                    ];
                }
                
                if ($record->quiz_id && !in_array($record->quiz_id, array_column($usage_map[$record->id]['quiz_list'], 'id'))) {
                    $usage_map[$record->id]['quiz_list'][] = (object)[
                        'id' => $record->quiz_id,
                        'name' => $record->quiz_name,
                        'course' => $record->course
                    ];
                    $usage_map[$record->id]['quiz_count']++;
                }
            }

            // Récupérer le nombre de tentatives par question
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
            }

            // Calculer is_used pour chaque question
            foreach ($usage_map as $qid => $usage) {
                $usage_map[$qid]['is_used'] = ($usage['quiz_count'] > 0 || $usage['attempt_count'] > 0);
            }

        } catch (\Exception $e) {
            // En cas d'erreur, retourner un map vide
        }

        return $usage_map;
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
                    JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
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
     *
     * @return array Map [question_id => [duplicate_ids]]
     */
    private static function get_duplicates_map() {
        global $DB;

        $duplicates_map = [];

        try {
            // Récupérer toutes les questions
            $questions = $DB->get_records('question', null, 'id ASC');
            
            $processed = [];

            foreach ($questions as $question) {
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
            }

        } catch (\Exception $e) {
            // En cas d'erreur, retourner un map vide
        }

        return $duplicates_map;
    }

    /**
     * Génère des statistiques globales
     *
     * @return object Statistiques globales
     */
    public static function get_global_stats() {
        global $DB;

        $stats = new \stdClass();
        
        try {
            // Total de questions
            $stats->total_questions = $DB->count_records('question');
            
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
            
            // Questions visibles/cachées
            $stats->visible_questions = $DB->count_records('question', ['hidden' => 0]);
            $stats->hidden_questions = $DB->count_records('question', ['hidden' => 1]);
            
            // Questions utilisées/inutilisées (calcul optimisé)
            $used_in_quiz = $DB->count_records_sql("
                SELECT COUNT(DISTINCT qs.questionid)
                FROM {quiz_slots} qs
            ");
            
            $used_in_attempts = $DB->count_records_sql("
                SELECT COUNT(DISTINCT qa.questionid)
                FROM {question_attempts} qa
            ");
            
            // Union des deux ensembles (approximation)
            $stats->used_questions = max($used_in_quiz, $used_in_attempts);
            $stats->unused_questions = $stats->total_questions - $stats->used_questions;
            
            // Questions en doublon (calcul lourd, on fait une estimation)
            $duplicates_map = self::get_duplicates_map();
            $stats->duplicate_questions = count($duplicates_map);
            $stats->total_duplicates = array_sum(array_map('count', $duplicates_map));
            
            // Questions avec liens cassés (si la classe existe)
            if (class_exists('local_question_diagnostic\question_link_checker')) {
                $broken_stats = question_link_checker::get_global_stats();
                $stats->questions_with_broken_links = $broken_stats->questions_with_broken_links;
            } else {
                $stats->questions_with_broken_links = 0;
            }
            
        } catch (\Exception $e) {
            // Valeurs par défaut en cas d'erreur
            $stats->total_questions = 0;
            $stats->used_questions = 0;
            $stats->unused_questions = 0;
            $stats->duplicate_questions = 0;
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
                                JOIN {question_bank_entries} qbe ON qbe.questioncategoryid = qc.id
                                JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
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

