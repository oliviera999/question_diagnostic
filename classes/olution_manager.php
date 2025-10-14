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
 * Détecte et gère les questions en doublon entre les catégories de cours
 * et la catégorie système "Olution"
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
     * Détecte les doublons entre catégories de cours et Olution
     * 
     * @param int $limit Limite du nombre de résultats (0 = tous)
     * @param int $offset Offset pour pagination
     * @return array Tableau de groupes de doublons
     */
    public static function find_course_to_olution_duplicates($limit = 0, $offset = 0) {
        global $DB;
        
        try {
            // Vérifier que la catégorie Olution existe
            $olution = local_question_diagnostic_find_olution_category();
            if (!$olution) {
                return [];
            }
            
            // Récupérer les sous-catégories d'Olution indexées par nom
            $olution_subcats = local_question_diagnostic_get_olution_subcategories();
            
            if (empty($olution_subcats)) {
                return [];
            }
            
            // Récupérer toutes les questions dans Olution
            $olution_cat_ids = array_keys($DB->get_records_sql(
                "SELECT id FROM {question_categories} WHERE parent = :parent",
                ['parent' => $olution->id]
            ));
            
            if (empty($olution_cat_ids)) {
                return [];
            }
            
            list($insql, $params) = $DB->get_in_or_equal($olution_cat_ids);
            
            // Récupérer toutes les questions d'Olution avec leurs catégories
            $sql_olution_questions = "SELECT q.*, qbe.questioncategoryid, qc.name as category_name
                                     FROM {question} q
                                     INNER JOIN {question_versions} qv ON qv.questionid = q.id
                                     INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                                     INNER JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                                     WHERE qbe.questioncategoryid $insql
                                     ORDER BY q.name, q.qtype";
            
            $olution_questions = $DB->get_records_sql($sql_olution_questions, $params);
            
            // Indexer les questions Olution par signature (nom + type)
            $olution_index = [];
            foreach ($olution_questions as $q) {
                $signature = $q->name . '|||' . $q->qtype;
                if (!isset($olution_index[$signature])) {
                    $olution_index[$signature] = [];
                }
                $olution_index[$signature][] = $q;
            }
            
            // Récupérer les catégories de cours (CONTEXT_COURSE)
            $sql_course_cats = "SELECT DISTINCT qc.id, qc.name, qc.contextid
                               FROM {question_categories} qc
                               INNER JOIN {context} ctx ON ctx.id = qc.contextid
                               WHERE ctx.contextlevel = :contextlevel
                               AND qc.parent != 0
                               ORDER BY qc.name";
            
            $course_categories = $DB->get_records_sql($sql_course_cats, ['contextlevel' => CONTEXT_COURSE]);
            
            $duplicates = [];
            
            // Pour chaque catégorie de cours
            foreach ($course_categories as $course_cat) {
                // Vérifier si une catégorie Olution correspondante existe
                $olution_target = local_question_diagnostic_find_olution_category_by_name($course_cat->name);
                
                if (!$olution_target) {
                    continue; // Ignorer si pas de correspondance
                }
                
                // Récupérer les questions de cette catégorie de cours
                $sql_course_questions = "SELECT q.*, qbe.questioncategoryid
                                        FROM {question} q
                                        INNER JOIN {question_versions} qv ON qv.questionid = q.id
                                        INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                                        WHERE qbe.questioncategoryid = :catid
                                        ORDER BY q.name, q.qtype";
                
                $course_questions = $DB->get_records_sql($sql_course_questions, ['catid' => $course_cat->id]);
                
                // Chercher les doublons pour chaque question du cours
                foreach ($course_questions as $course_q) {
                    $signature = $course_q->name . '|||' . $course_q->qtype;
                    
                    // Vérifier si cette signature existe dans Olution
                    if (isset($olution_index[$signature])) {
                        // Vérifier la similarité du contenu avec chaque candidat
                        foreach ($olution_index[$signature] as $olution_q) {
                            $similarity = self::calculate_text_similarity(
                                $course_q->questiontext,
                                $olution_q->questiontext
                            );
                            
                            // Seuil de 90% de similarité
                            if ($similarity >= 0.90) {
                                $duplicates[] = [
                                    'course_question' => $course_q,
                                    'olution_question' => $olution_q,
                                    'course_category' => $course_cat,
                                    'olution_target_category' => $olution_target,
                                    'similarity' => $similarity
                                ];
                                break; // Un seul match suffit par question
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
     * @return object Statistiques
     */
    public static function get_duplicate_stats() {
        global $DB;
        
        $stats = new \stdClass();
        
        // Vérifier que Olution existe
        $olution = local_question_diagnostic_find_olution_category();
        $stats->olution_exists = ($olution !== false);
        
        if (!$stats->olution_exists) {
            $stats->olution_subcategories_count = 0;
            $stats->total_duplicates = 0;
            $stats->movable_questions = 0;
            $stats->unmovable_questions = 0;
            return $stats;
        }
        
        // Compter les sous-catégories Olution
        $stats->olution_subcategories_count = $DB->count_records('question_categories', ['parent' => $olution->id]);
        
        // Récupérer tous les doublons
        $all_duplicates = self::find_course_to_olution_duplicates(0, 0);
        $stats->total_duplicates = count($all_duplicates);
        
        // Compter ceux qui peuvent être déplacés (ont une catégorie cible)
        $movable = 0;
        $unmovable = 0;
        
        foreach ($all_duplicates as $dup) {
            if ($dup['olution_target_category']) {
                $movable++;
            } else {
                $unmovable++;
            }
        }
        
        $stats->movable_questions = $movable;
        $stats->unmovable_questions = $unmovable;
        
        // Grouper par catégorie source
        $by_category = [];
        foreach ($all_duplicates as $dup) {
            $cat_id = $dup['course_category']->id;
            if (!isset($by_category[$cat_id])) {
                $by_category[$cat_id] = [
                    'category' => $dup['course_category'],
                    'count' => 0
                ];
            }
            $by_category[$cat_id]['count']++;
        }
        
        $stats->by_source_category = array_values($by_category);
        
        return $stats;
    }

    /**
     * Déplace une question vers une catégorie Olution
     * 
     * Cette méthode utilise l'API Moodle pour déplacer proprement une question,
     * en mettant à jour question_bank_entries.questioncategoryid
     * 
     * @param int $questionid ID de la question à déplacer
     * @param int $target_category_id ID de la catégorie Olution cible
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
            
            // Vérifier que la catégorie cible existe et est dans Olution
            $target_category = $DB->get_record('question_categories', ['id' => $target_category_id]);
            if (!$target_category) {
                return 'Catégorie cible introuvable (ID: ' . $target_category_id . ')';
            }
            
            // Vérifier que la catégorie cible est bien dans Olution (contexte système)
            $target_context = $DB->get_record('context', ['id' => $target_category->contextid]);
            if (!$target_context || $target_context->contextlevel != CONTEXT_SYSTEM) {
                return 'La catégorie cible n\'est pas dans le contexte système (Olution)';
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
                        'message' => 'Question déplacée vers Olution (catégorie: ' . $target_category->name . ')'
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

