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
 * Détecte et gère les questions en doublon entre les cours normaux
 * et les cours dans la catégorie de cours "Olution"
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
     * Détecte les doublons de questions liées à Olution
     * 
     * 🔄 v1.10.7 : Logique CORRIGÉE
     * 🔧 v1.10.8 : Logique SIMPLIFIÉE - Détection ALL-TO-ALL
     * 
     * Détecte :
     * 1. Doublons entre cours HORS Olution → cours DANS Olution
     * 2. Doublons ENTRE les cours d'Olution eux-mêmes
     * 
     * @param int $limit Limite du nombre de résultats (0 = tous)
     * @param int $offset Offset pour pagination
     * @return array Tableau de doublons détectés
     */
    public static function find_course_to_olution_duplicates($limit = 0, $offset = 0) {
        global $DB;
        
        try {
            // Vérifier que la catégorie de cours Olution existe
            $olution_course_category = local_question_diagnostic_find_olution_category();
            if (!$olution_course_category) {
                debugging('Olution course category not found', DEBUG_DEVELOPER);
                return [];
            }
            
            debugging('✅ Olution course category found: ' . $olution_course_category->name . ' (ID: ' . $olution_course_category->id . ')', DEBUG_DEVELOPER);
            
            // Récupérer tous les cours dans Olution
            $olution_courses = $DB->get_records('course', ['category' => $olution_course_category->id]);
            
            if (empty($olution_courses)) {
                debugging('⚠️ No courses found in Olution category', DEBUG_DEVELOPER);
                return [];
            }
            
            debugging('📊 Found ' . count($olution_courses) . ' courses in Olution', DEBUG_DEVELOPER);
            
            // Récupérer les IDs des cours Olution
            $olution_course_ids = array_keys($olution_courses);
            
            // ÉTAPE 1 : Indexer TOUTES les questions des cours Olution par signature
            $olution_questions_index = [];
            
            foreach ($olution_course_ids as $course_id) {
                // Récupérer le contexte du cours
                $course_context = \context_course::instance($course_id);
                
                // Récupérer toutes les questions de ce cours
                $sql = "SELECT q.*, qbe.questioncategoryid, qc.name as category_name
                       FROM {question} q
                       INNER JOIN {question_versions} qv ON qv.questionid = q.id
                       INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                       INNER JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                       WHERE qc.contextid = :contextid
                       ORDER BY q.name, q.qtype";
                
                $questions = $DB->get_records_sql($sql, ['contextid' => $course_context->id]);
                
                foreach ($questions as $q) {
                    $signature = $q->name . '|||' . $q->qtype;
                    if (!isset($olution_questions_index[$signature])) {
                        $olution_questions_index[$signature] = [];
                    }
                    $olution_questions_index[$signature][] = [
                        'question' => $q,
                        'category_id' => $q->questioncategoryid,
                        'category_name' => $q->category_name,
                        'course_id' => $course_id,
                        'course' => $olution_courses[$course_id]
                    ];
                }
            }
            
            debugging('📊 Indexed ' . count($olution_questions_index) . ' unique question signatures in Olution', DEBUG_DEVELOPER);
            
            // ÉTAPE 2 : Chercher les doublons dans TOUS les autres cours
            $duplicates = [];
            
            // Récupérer TOUS les cours (pas seulement hors Olution, pour détecter aussi doublons internes)
            $all_courses = $DB->get_records('course', null, 'fullname ASC');
            
            foreach ($all_courses as $course) {
                // Ignorer le site principal
                if ($course->id == 1) {
                    continue;
                }
                
                // Récupérer le contexte du cours
                try {
                    $course_context = \context_course::instance($course->id);
                } catch (\Exception $e) {
                    continue;
                }
                
                // Récupérer les catégories de questions de ce cours
                $course_question_cats = $DB->get_records('question_categories', [
                    'contextid' => $course_context->id
                ]);
                
                // Pour chaque catégorie de questions
                foreach ($course_question_cats as $course_cat) {
                    // Récupérer les questions de cette catégorie
                    $sql = "SELECT q.*, qbe.questioncategoryid
                           FROM {question} q
                           INNER JOIN {question_versions} qv ON qv.questionid = q.id
                           INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                           WHERE qbe.questioncategoryid = :catid
                           ORDER BY q.name, q.qtype";
                    
                    $course_questions = $DB->get_records_sql($sql, ['catid' => $course_cat->id]);
                    
                    // Pour chaque question de ce cours
                    foreach ($course_questions as $course_q) {
                        $signature = $course_q->name . '|||' . $course_q->qtype;
                        
                        // Vérifier si cette signature existe dans l'index Olution
                        if (isset($olution_questions_index[$signature])) {
                            // Il y a au moins un doublon potentiel
                            foreach ($olution_questions_index[$signature] as $olution_entry) {
                                // Ne pas comparer une question avec elle-même
                                if ($course_q->id == $olution_entry['question']->id) {
                                    continue;
                                }
                                
                                // Calculer la similarité du contenu
                                $similarity = self::calculate_text_similarity(
                                    $course_q->questiontext,
                                    $olution_entry['question']->questiontext
                                );
                                
                                // Seuil de 90% de similarité
                                if ($similarity >= 0.90) {
                                    // Trouver une catégorie cible dans Olution avec le même nom
                                    $matching_targets = self::find_matching_olution_categories($course_cat->name, $olution_courses);
                                    
                                    $duplicates[] = [
                                        'course_question' => $course_q,
                                        'olution_question' => $olution_entry['question'],
                                        'course_category' => $course_cat,
                                        'course' => $course,
                                        'olution_target_categories' => $matching_targets,
                                        'olution_course' => $olution_entry['course'],
                                        'similarity' => $similarity,
                                        'is_internal_olution' => in_array($course->id, $olution_course_ids)
                                    ];
                                    break; // Un seul match suffit par question
                                }
                            }
                        }
                    }
                }
            }
            
            debugging('📊 Found ' . count($duplicates) . ' total duplicates', DEBUG_DEVELOPER);
            
            // Appliquer pagination
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
     * Trouve les catégories de questions Olution correspondant à un nom donné
     * 
     * 🆕 v1.10.8 : Helper pour trouver les catégories cibles
     * 
     * @param string $category_name Nom de la catégorie à chercher
     * @param array $olution_courses Tableau des cours Olution
     * @return array|false Tableau des catégories correspondantes ou false
     */
    private static function find_matching_olution_categories($category_name, $olution_courses) {
        global $DB;
        
        $matches = [];
        
        foreach ($olution_courses as $course) {
            try {
                $course_context = \context_course::instance($course->id);
                
                // Chercher une catégorie avec ce nom exact
                $cat = $DB->get_record('question_categories', [
                    'contextid' => $course_context->id,
                    'name' => $category_name
                ]);
                
                if ($cat) {
                    $matches[] = [
                        'category' => $cat,
                        'course' => $course,
                        'context_id' => $course_context->id
                    ];
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        
        // Si aucune correspondance exacte, chercher case-insensitive
        if (empty($matches)) {
            $category_name_lower = strtolower(trim($category_name));
            
            foreach ($olution_courses as $course) {
                try {
                    $course_context = \context_course::instance($course->id);
                    
                    $cats = $DB->get_records('question_categories', ['contextid' => $course_context->id]);
                    
                    foreach ($cats as $cat) {
                        if (strtolower(trim($cat->name)) === $category_name_lower) {
                            $matches[] = [
                                'category' => $cat,
                                'course' => $course,
                                'context_id' => $course_context->id
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }
        
        return empty($matches) ? false : $matches;
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
            $stats->by_source_course = [];
            return $stats;
        }
        
        $stats->olution_name = $olution->name;
        
        // Compter les cours dans Olution (tous, pas -1)
        $stats->olution_courses_count = $DB->count_records('course', [
            'category' => $olution->id
        ]);
        
        debugging('📊 Getting duplicate stats for Olution with ' . $stats->olution_courses_count . ' courses', DEBUG_DEVELOPER);
        
        // Récupérer tous les doublons
        $all_duplicates = self::find_course_to_olution_duplicates(0, 0);
        $stats->total_duplicates = count($all_duplicates);
        
        debugging('📊 Total duplicates found: ' . $stats->total_duplicates, DEBUG_DEVELOPER);
        
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
            $course_id = $target_context->instanceid;
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
