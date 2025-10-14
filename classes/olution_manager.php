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
require_once(__DIR__ . '/../lib.php');

/**
 * Gestionnaire des doublons Olution
 * 
 * Détecte et gère les questions en doublon entre les catégories de cours normaux
 * et les catégories de questions des cours dans la catégorie de cours "Olution"
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class olution_manager {

    /**
     * Calcule la similarité entre deux textes (méthode simple)
     * 
     * @param string $text1 Premier texte
     * @param string $text2 Deuxième texte
     * @return float Similarité entre 0 et 1
     */
    private static function calculate_text_similarity($text1, $text2) {
        // Nettoyer les textes HTML
        $text1_clean = strip_tags($text1);
        $text2_clean = strip_tags($text2);
        
        // Normaliser (minuscules, espaces)
        $text1_clean = strtolower(trim(preg_replace('/\s+/', ' ', $text1_clean)));
        $text2_clean = strtolower(trim(preg_replace('/\s+/', ' ', $text2_clean)));
        
        // Si exactement identiques
        if ($text1_clean === $text2_clean) {
            return 1.0;
        }
        
        // Calculer la similarité avec similar_text
        similar_text($text1_clean, $text2_clean, $percent);
        
        return $percent / 100.0;
    }

    /**
     * Détecte les doublons entre catégories de cours normaux et cours Olution
     * 
     * 🔄 v1.10.7 : Logique CORRIGÉE
     * - Olution = catégorie de COURS (course_categories)
     * - Cherche doublons entre questions des cours normaux et questions des cours dans Olution
     * - Matche par nom de catégorie de questions
     * 
     * @param int $limit Limite du nombre de résultats (0 = tous)
     * @param int $offset Offset pour pagination
     * @return array Tableau de groupes de doublons
     */
    public static function find_course_to_olution_duplicates($limit = 0, $offset = 0) {
        global $DB;
        
        try {
            // Vérifier que la catégorie de cours Olution existe
            $olution_course_category = local_question_diagnostic_find_olution_category();
            if (!$olution_course_category) {
                return [];
            }
            
            // Récupérer toutes les catégories de questions des cours Olution (indexées par nom)
            $olution_question_cats = local_question_diagnostic_get_olution_question_categories();
            
            if (empty($olution_question_cats)) {
                return [];
            }
            
            // Indexer toutes les questions des cours Olution par signature (nom + type)
            $olution_questions_index = [];
            
            foreach ($olution_question_cats as $cat_name => $cat_entries) {
                foreach ($cat_entries as $entry) {
                    $cat = $entry['category'];
                    
                    // Récupérer les questions de cette catégorie
                    $sql_questions = "SELECT q.*, qbe.questioncategoryid
                                     FROM {question} q
                                     INNER JOIN {question_versions} qv ON qv.questionid = q.id
                                     INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                                     WHERE qbe.questioncategoryid = :catid
                                     ORDER BY q.name, q.qtype";
                    
                    $questions = $DB->get_records_sql($sql_questions, ['catid' => $cat->id]);
                    
                    foreach ($questions as $q) {
                        $signature = $q->name . '|||' . $q->qtype;
                        if (!isset($olution_questions_index[$signature])) {
                            $olution_questions_index[$signature] = [];
                        }
                        $olution_questions_index[$signature][] = [
                            'question' => $q,
                            'category' => $cat,
                            'course' => $entry['course']
                        ];
                    }
                }
            }
            
            // Récupérer tous les cours HORS Olution
            $sql_non_olution_courses = "SELECT c.*
                                        FROM {course} c
                                        WHERE c.category != :olution_cat_id
                                        AND c.id != 1
                                        ORDER BY c.fullname";
            
            $non_olution_courses = $DB->get_records_sql($sql_non_olution_courses, [
                'olution_cat_id' => $olution_course_category->id
            ]);
            
            $duplicates = [];
            
            // Pour chaque cours hors Olution
            foreach ($non_olution_courses as $course) {
                // Récupérer le contexte du cours
                $course_context = \context_course::instance($course->id);
                
                // Récupérer les catégories de questions de ce cours
                $course_question_cats = $DB->get_records('question_categories', [
                    'contextid' => $course_context->id
                ]);
                
                // Pour chaque catégorie de questions du cours
                foreach ($course_question_cats as $course_cat) {
                    // Récupérer les questions de cette catégorie
                    $sql_course_questions = "SELECT q.*, qbe.questioncategoryid
                                            FROM {question} q
                                            INNER JOIN {question_versions} qv ON qv.questionid = q.id
                                            INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                                            WHERE qbe.questioncategoryid = :catid
                                            ORDER BY q.name, q.qtype";
                    
                    $course_questions = $DB->get_records_sql($sql_course_questions, ['catid' => $course_cat->id]);
                    
                    // Chercher les doublons pour chaque question
                    foreach ($course_questions as $course_q) {
                        $signature = $course_q->name . '|||' . $course_q->qtype;
                        
                        // Vérifier si cette signature existe dans Olution
                        if (isset($olution_questions_index[$signature])) {
                            // Vérifier la similarité du contenu avec chaque candidat
                            foreach ($olution_questions_index[$signature] as $olution_entry) {
                                $olution_q = $olution_entry['question'];
                                
                                $similarity = self::calculate_text_similarity(
                                    $course_q->questiontext,
                                    $olution_q->questiontext
                                );
                                
                                // Seuil de 90% de similarité
                                if ($similarity >= 0.90) {
                                    // Vérifier si une catégorie Olution correspondante existe (même nom)
                                    $matching_olution_cats = isset($olution_question_cats[$course_cat->name]) 
                                                            ? $olution_question_cats[$course_cat->name] 
                                                            : false;
                                    
                                    $duplicates[] = [
                                        'course_question' => $course_q,
                                        'olution_question' => $olution_q,
                                        'course_category' => $course_cat,
                                        'course' => $course,
                                        'olution_target_categories' => $matching_olution_cats,
                                        'olution_course' => $olution_entry['course'],
                                        'similarity' => $similarity
                                    ];
                                    break; // Un seul match suffit par question
                                }
                            }
                        }
                    }
                }
            }
            
            // Appliquer pagination
            $total = count($duplicates);
            if ($limit > 0) {
                $duplicates = array_slice($duplicates, $offset, $limit);
            }
            
            return $duplicates;
            
        } catch (\Exception $e) {
            debugging('Error in find_course_to_olution_duplicates: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
    }

    /**
     * Compte le nombre total de doublons cours → Olution
     * 
     * @return int Nombre total de doublons
     */
    public static function count_course_to_olution_duplicates() {
        $all = self::find_course_to_olution_duplicates(0, 0);
        return count($all);
    }

    /**
     * Obtient les statistiques globales des doublons cours → Olution
     * 
     * 🔄 v1.10.7 : CORRECTION - Statistiques pour catégorie de COURS
     * 
     * @return object Statistiques
     */
    public static function get_duplicate_stats() {
        global $DB;
        
        $stats = new \stdClass();
        
        // Vérifier que la catégorie de cours Olution existe
        $olution = local_question_diagnostic_find_olution_category();
        $stats->olution_exists = ($olution !== false);
        
        if (!$stats->olution_exists) {
            $stats->olution_name = '';
            $stats->olution_courses_count = 0;
            $stats->total_duplicates = 0;
            $stats->movable_questions = 0;
            $stats->unmovable_questions = 0;
            return $stats;
        }
        
        $stats->olution_name = $olution->name;
        
        // Compter les cours dans Olution
        $stats->olution_courses_count = $DB->count_records('course', [
            'category' => $olution->id
        ]) - 1; // -1 pour exclure le site principal si présent
        
        // Récupérer tous les doublons (peut être lent, considérer cache)
        $all_duplicates = self::find_course_to_olution_duplicates(0, 0);
        $stats->total_duplicates = count($all_duplicates);
        
        // Compter ceux qui peuvent être déplacés (ont une catégorie cible)
        $movable = 0;
        $unmovable = 0;
        
        foreach ($all_duplicates as $dup) {
            if ($dup['olution_target_categories'] && !empty($dup['olution_target_categories'])) {
                $movable++;
            } else {
                $unmovable++;
            }
        }
        
        $stats->movable_questions = $movable;
        $stats->unmovable_questions = $unmovable;
        
        // Grouper par cours source
        $by_course = [];
        foreach ($all_duplicates as $dup) {
            $course_id = $dup['course']->id;
            if (!isset($by_course[$course_id])) {
                $by_course[$course_id] = [
                    'course' => $dup['course'],
                    'count' => 0
                ];
            }
            $by_course[$course_id]['count']++;
        }
        
        $stats->by_source_course = array_values($by_course);
        
        return $stats;
    }

    /**
     * Déplace une question vers une catégorie de questions d'un cours Olution
     * 
     * 🔄 v1.10.7 : CORRECTION - Déplace vers catégories des COURS Olution
     * 
     * Cette méthode utilise l'API Moodle pour déplacer proprement une question,
     * en mettant à jour question_bank_entries.questioncategoryid
     * 
     * @param int $questionid ID de la question à déplacer
     * @param int $target_category_id ID de la catégorie de questions Olution cible
     * @return bool|string True si succès, message d'erreur sinon
     */
    public static function move_question_to_olution($questionid, $target_category_id) {
        global $DB;
        
        try {
            // Vérifier que la question existe
            $question = $DB->get_record('question', ['id' => $questionid]);
            if (!$question) {
                return 'Question introuvable (ID: ' . $questionid . ')';
            }
            
            // Vérifier que la catégorie cible existe
            $target_category = $DB->get_record('question_categories', ['id' => $target_category_id]);
            if (!$target_category) {
                return 'Catégorie cible introuvable (ID: ' . $target_category_id . ')';
            }
            
            // Vérifier que la catégorie cible appartient à un cours dans Olution
            $target_context = $DB->get_record('context', ['id' => $target_category->contextid]);
            if (!$target_context || $target_context->contextlevel != CONTEXT_COURSE) {
                return 'La catégorie cible n\'est pas dans un contexte de cours';
            }
            
            // Vérifier que le cours est dans Olution
            $course_id = $DB->get_field('context', 'instanceid', ['id' => $target_context->id]);
            $course = $DB->get_record('course', ['id' => $course_id]);
            
            $olution_category = local_question_diagnostic_find_olution_category();
            if (!$olution_category || $course->category != $olution_category->id) {
                return 'Le cours cible n\'est pas dans la catégorie Olution';
            }
            
            // Démarrer une transaction
            $transaction = $DB->start_delegated_transaction();
            
            try {
                // Mettre à jour question_bank_entries (Moodle 4.x)
                $sql_update = "UPDATE {question_bank_entries}
                              SET questioncategoryid = :newcatid
                              WHERE id IN (
                                  SELECT questionbankentryid
                                  FROM {question_versions}
                                  WHERE questionid = :questionid
                              )";
                
                $DB->execute($sql_update, [
                    'newcatid' => $target_category_id,
                    'questionid' => $questionid
                ]);
                
                // Valider la transaction
                $transaction->allow_commit();
                
                // Log d'audit
                require_once(__DIR__ . '/audit_logger.php');
                audit_logger::log_action(
                    'question_moved_to_olution',
                    [
                        'question_id' => $questionid,
                        'target_category_id' => $target_category_id,
                        'target_category_name' => $target_category->name,
                        'target_course_id' => $course->id,
                        'target_course_name' => $course->fullname,
                        'message' => 'Question déplacée vers cours Olution: ' . $course->fullname . ' / ' . $target_category->name
                    ],
                    $questionid
                );
                
                return true;
                
            } catch (\Exception $inner_e) {
                debugging('Error in transaction: ' . $inner_e->getMessage(), DEBUG_DEVELOPER);
                throw $inner_e;
            }
            
        } catch (\Exception $e) {
            return 'Erreur lors du déplacement : ' . $e->getMessage();
        }
    }

    /**
     * Déplace plusieurs questions vers Olution en masse
     * 
     * @param array $move_operations Tableau d'opérations [['questionid' => X, 'target_category_id' => Y], ...]
     * @return array ['success' => count, 'failed' => count, 'errors' => []]
     */
    public static function move_questions_batch($move_operations) {
        $success = 0;
        $failed = 0;
        $errors = [];
        
        foreach ($move_operations as $op) {
            $result = self::move_question_to_olution($op['questionid'], $op['target_category_id']);
            
            if ($result === true) {
                $success++;
            } else {
                $failed++;
                $errors[] = "Question {$op['questionid']}: $result";
            }
        }
        
        // Purger les caches après déplacement en masse
        if ($success > 0) {
            require_once(__DIR__ . '/cache_manager.php');
            cache_manager::purge_all_caches();
        }
        
        return [
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors
        ];
    }
}
