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
require_once(__DIR__ . '/question_analyzer.php');

/**
 * Gestionnaire des doublons Olution
 * 
 * Détecte les doublons de questions et les déplace vers les sous-catégories
 * de la catégorie de questions "Olution" (système)
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class olution_manager {

    /**
     * Obtient la profondeur d'une catégorie de questions dans l'arborescence
     * 
     * @param int $categoryid ID de la catégorie
     * @return int Profondeur (0 = racine, 1 = niveau 1, etc.)
     */
    private static function get_category_depth($categoryid) {
        global $DB;
        
        $depth = 0;
        $current_id = $categoryid;
        $visited = [];
        
        while ($current_id > 0) {
            // Éviter les boucles infinies
            if (in_array($current_id, $visited)) {
                break;
            }
            $visited[] = $current_id;
            
            $cat = $DB->get_record('question_categories', ['id' => $current_id]);
            if (!$cat) {
                break;
            }
            
            if ($cat->parent == 0) {
                break; // Racine atteinte
            }
            
            $depth++;
            $current_id = $cat->parent;
        }
        
        return $depth;
    }

    /**
     * Vérifie si une catégorie est dans Olution ou une de ses sous-catégories
     * 
     * @param int $categoryid ID de la catégorie à vérifier
     * @return bool True si dans Olution
     */
    private static function is_in_olution($categoryid) {
        global $DB;
        
        $olution = local_question_diagnostic_find_olution_category();
        if (!$olution) {
            return false;
        }
        
        // Remonter l'arborescence jusqu'à trouver Olution ou une racine
        $current_id = $categoryid;
        $visited = [];
        
        while ($current_id > 0) {
            if ($current_id == $olution->id) {
                return true; // Trouvé !
            }
            
            // Éviter les boucles
            if (in_array($current_id, $visited)) {
                break;
            }
            $visited[] = $current_id;
            
            $cat = $DB->get_record('question_categories', ['id' => $current_id]);
            if (!$cat) {
                break;
            }
            
            if ($cat->parent == 0) {
                break; // Racine atteinte sans trouver Olution
            }
            
            $current_id = $cat->parent;
        }
        
        return false;
    }

    /**
     * Détecte tous les groupes de doublons du site
     * Utilise la même logique que questions_cleanup.php (nom + type)
     * 
     * 🆕 v1.10.9 : Logique CORRECTE basée sur question_analyzer::get_duplicate_groups()
     * 
     * @param int $limit Limite du nombre de groupes (0 = tous)
     * @param int $offset Offset pour pagination
     * @return array Tableau de groupes de doublons avec infos Olution
     */
    public static function find_all_duplicates_for_olution($limit = 0, $offset = 0) {
        global $DB;
        
        try {
            // Vérifier que la catégorie de questions Olution existe
            $olution = local_question_diagnostic_find_olution_category();
            if (!$olution) {
                debugging('❌ Olution question category not found', DEBUG_DEVELOPER);
                return [];
            }
            
            debugging('✅ Olution question category found: ' . $olution->name . ' (ID: ' . $olution->id . ')', DEBUG_DEVELOPER);
            
            // Utiliser la détection de doublons existante (nom + type)
            // Récupérer TOUS les groupes de doublons du site
            $duplicate_groups = question_analyzer::get_duplicate_groups(0, 0, false, false);
            
            debugging('📊 Found ' . count($duplicate_groups) . ' duplicate groups', DEBUG_DEVELOPER);
            
            $results = [];
            
            // Pour chaque groupe de doublons
            foreach ($duplicate_groups as $group) {
                $question_ids = $group->all_question_ids;
                
                if (empty($question_ids)) {
                    continue;
                }
                
                // Récupérer les détails de toutes les questions du groupe
                list($insql, $params) = $DB->get_in_or_equal($question_ids);
                $questions = $DB->get_records_select('question', "id $insql", $params);
                
                // Récupérer les catégories de chaque question
                $questions_with_categories = [];
                $olution_questions = [];
                $non_olution_questions = [];
                
                foreach ($questions as $q) {
                    // Récupérer la catégorie via question_bank_entries
                    $sql_cat = "SELECT qc.*
                               FROM {question_categories} qc
                               INNER JOIN {question_bank_entries} qbe ON qbe.questioncategoryid = qc.id
                               INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                               WHERE qv.questionid = :qid
                               LIMIT 1";
                    $cat = $DB->get_record_sql($sql_cat, ['qid' => $q->id]);
                    
                    if ($cat) {
                        $is_in_olution = self::is_in_olution($cat->id);
                        $depth = self::get_category_depth($cat->id);
                        
                        $questions_with_categories[] = [
                            'question' => $q,
                            'category' => $cat,
                            'is_in_olution' => $is_in_olution,
                            'depth' => $depth
                        ];
                        
                        if ($is_in_olution) {
                            $olution_questions[] = [
                                'question' => $q,
                                'category' => $cat,
                                'depth' => $depth
                            ];
                        } else {
                            $non_olution_questions[] = [
                                'question' => $q,
                                'category' => $cat
                            ];
                        }
                    }
                }
                
                // Si au moins UN doublon est dans Olution, c'est intéressant
                if (!empty($olution_questions)) {
                    // Trouver la catégorie Olution la plus profonde
                    $deepest_olution_cat = null;
                    $max_depth = -1;
                    
                    foreach ($olution_questions as $oq) {
                        if ($oq['depth'] > $max_depth) {
                            $max_depth = $oq['depth'];
                            $deepest_olution_cat = $oq['category'];
                        }
                    }
                    
                    $results[] = [
                        'group_name' => $group->question_name,
                        'group_type' => $group->qtype,
                        'total_count' => count($questions_with_categories),
                        'olution_count' => count($olution_questions),
                        'non_olution_count' => count($non_olution_questions),
                        'all_questions' => $questions_with_categories,
                        'olution_questions' => $olution_questions,
                        'non_olution_questions' => $non_olution_questions,
                        'target_category' => $deepest_olution_cat,
                        'target_depth' => $max_depth
                    ];
                }
            }
            
            debugging('📊 Found ' . count($results) . ' duplicate groups with Olution presence', DEBUG_DEVELOPER);
            
            // Appliquer pagination
            if ($limit > 0) {
                $results = array_slice($results, $offset, $limit);
            }
            
            return $results;
            
        } catch (\Exception $e) {
            debugging('Error in find_all_duplicates_for_olution: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
    }

    /**
     * Obtient les statistiques globales des doublons Olution
     * 
     * @return object Statistiques
     */
    public static function get_duplicate_stats() {
        global $DB;
        
        $stats = new \stdClass();
        
        // Vérifier que la catégorie de questions Olution existe
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
        
        // Compter les sous-catégories d'Olution
        $stats->olution_courses_count = $DB->count_records('question_categories', [
            'parent' => $olution->id
        ]);
        
        // Récupérer tous les groupes de doublons
        $all_groups = self::find_all_duplicates_for_olution(0, 0);
        
        // Compter le total de questions en doublon
        $total_questions = 0;
        $movable = 0;
        
        foreach ($all_groups as $group) {
            // Toutes les questions du groupe sauf celles déjà dans la catégorie cible
            foreach ($group['all_questions'] as $q_info) {
                $total_questions++;
                
                // Une question est déplaçable si elle n'est pas déjà dans la catégorie cible
                if ($group['target_category'] && $q_info['category']->id != $group['target_category']->id) {
                    $movable++;
                }
            }
        }
        
        $stats->total_duplicates = $total_questions;
        $stats->movable_questions = $movable;
        $stats->unmovable_questions = $total_questions - $movable;
        
        return $stats;
    }

    /**
     * Déplace une question vers la catégorie Olution cible
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
            
            if (!self::is_in_olution($target_category_id)) {
                return 'La catégorie cible n\'est pas dans Olution';
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
                        'message' => 'Question déplacée vers Olution: ' . $target_category->name
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
