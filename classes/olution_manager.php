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
     * 🔧 v1.11.13 : CORRECTION - Fonction publique et logique améliorée
     * Utilise la même logique que l'arborescence pour garantir la cohérence.
     * 
     * @param int $categoryid ID de la catégorie à vérifier
     * @return bool True si dans Olution
     */
    public static function is_in_olution($categoryid) {
        global $DB;
        
        // Utiliser la fonction existante qui fonctionne déjà
        $olution = local_question_diagnostic_find_olution_category();
        if (!$olution) {
            debugging('❌ Olution category not found in is_in_olution()', DEBUG_DEVELOPER);
            return false;
        }
        
        debugging('🔍 Checking if category ' . $categoryid . ' is in Olution (ID: ' . $olution->id . ')', DEBUG_DEVELOPER);
        
        // Remonter l'arborescence jusqu'à trouver Olution ou une racine
        $current_id = $categoryid;
        $visited = [];
        $path = []; // Pour le debug
        
        while ($current_id > 0) {
            // Éviter les boucles infinies
            if (in_array($current_id, $visited)) {
                debugging('⚠️ Loop detected in is_in_olution() for category ' . $categoryid, DEBUG_DEVELOPER);
                break;
            }
            $visited[] = $current_id;
            $path[] = $current_id;
            
            // Si on trouve Olution, c'est gagné !
            if ($current_id == $olution->id) {
                debugging('✅ Found Olution in path: ' . implode(' -> ', $path), DEBUG_DEVELOPER);
                return true;
            }
            
            // Récupérer la catégorie courante
            $cat = $DB->get_record('question_categories', ['id' => $current_id]);
            if (!$cat) {
                debugging('⚠️ Category not found: ' . $current_id, DEBUG_DEVELOPER);
                break;
            }
            
            // Si on arrive à une racine (parent = 0), on s'arrête
            if ($cat->parent == 0) {
                debugging('🔚 Reached root category: ' . $current_id . ' (path: ' . implode(' -> ', $path) . ')', DEBUG_DEVELOPER);
                break;
            }
            
            $current_id = $cat->parent;
        }
        
        debugging('❌ Category ' . $categoryid . ' is NOT in Olution (path: ' . implode(' -> ', $path) . ')', DEBUG_DEVELOPER);
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
     * 🔧 v1.11.13 : CORRECTION - Amélioration de la logique de déplacement
     * Utilise la même logique que l'arborescence et ajoute des vérifications robustes.
     * 
     * @param int $questionid ID de la question à déplacer
     * @param int $target_category_id ID de la catégorie Olution cible
     * @return bool|string True si succès, message d'erreur sinon
     */
    public static function move_question_to_olution($questionid, $target_category_id) {
        global $DB, $CFG;
        
        try {
            debugging('🚀 Starting move_question_to_olution: question=' . $questionid . ', target=' . $target_category_id, DEBUG_DEVELOPER);
            
            require_once($CFG->libroot . '/questionlib.php');

            // Vérifier que la question existe
            $question = $DB->get_record('question', ['id' => $questionid]);
            if (!$question) {
                return 'Question introuvable (ID: ' . $questionid . ')';
            }
            
            debugging('✅ Question found: ' . $question->name . ' (type: ' . $question->qtype . ')', DEBUG_DEVELOPER);
            
            // Vérifier que la catégorie cible existe et est dans Olution
            $target_category = $DB->get_record('question_categories', ['id' => $target_category_id]);
            if (!$target_category) {
                return 'Catégorie cible introuvable (ID: ' . $target_category_id . ')';
            }
            
            debugging('✅ Target category found: ' . $target_category->name, DEBUG_DEVELOPER);
            
            // Vérifier que la catégorie cible est bien dans Olution
            if (!self::is_in_olution($target_category_id)) {
                return 'La catégorie cible n\'est pas dans Olution (ID: ' . $target_category_id . ')';
            }
            
            debugging('✅ Target category is confirmed to be in Olution', DEBUG_DEVELOPER);
            
            // Récupérer la catégorie actuelle de la question
            $current_category_sql = "SELECT qc.*
                                    FROM {question_categories} qc
                                    INNER JOIN {question_bank_entries} qbe ON qbe.questioncategoryid = qc.id
                                    INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                                    WHERE qv.questionid = :questionid
                                    LIMIT 1";
            $current_category = $DB->get_record_sql($current_category_sql, ['questionid' => $questionid]);
            
            if (!$current_category) {
                return 'Impossible de déterminer la catégorie actuelle de la question';
            }
            
            debugging('✅ Current category: ' . $current_category->name . ' (ID: ' . $current_category->id . ')', DEBUG_DEVELOPER);
            
            // Vérifier si la question est déjà dans la catégorie cible
            if ($current_category->id == $target_category_id) {
                return 'La question est déjà dans la catégorie cible';
            }
            
            // Démarrer une transaction
            $transaction = $DB->start_delegated_transaction();
            
            try {
                // Utiliser l'API native Moodle pour déplacer la question
                // Cette fonction gère automatiquement les événements, les contextes et les entrées/versions
                if (function_exists('question_move_questions_to_category')) {
                    // Moodle 4.x standard API
                    // question_move_questions_to_category(array $questionids, int $newcategoryid)
                    question_move_questions_to_category([$questionid], $target_category_id);
                    debugging('✅ Moved using native question_move_questions_to_category', DEBUG_DEVELOPER);
                } else {
                    // Fallback manuel si la fonction n'existe pas (versions très anciennes ou modifiées)
                    // Mettre à jour question_bank_entries (Moodle 4.x)
                    $sql_update = "UPDATE {question_bank_entries}
                                  SET questioncategoryid = :newcatid
                                  WHERE id IN (
                                      SELECT questionbankentryid
                                      FROM {question_versions}
                                      WHERE questionid = :questionid
                                  )";
                    
                    $affected_rows = $DB->execute($sql_update, [
                        'newcatid' => $target_category_id,
                        'questionid' => $questionid
                    ]);
                    
                    debugging('✅ Updated ' . $affected_rows . ' question_bank_entries (Manual fallback)', DEBUG_DEVELOPER);
                    
                    // Déclencher l'événement manuellement car on n'a pas utilisé l'API
                    $event = \core\event\question_moved::create([
                        'objectid' => $questionid,
                        'context' => \context::instance_by_id($current_category->contextid),
                        'other' => [
                            'oldcategoryid' => $current_category->id,
                            'newcategoryid' => $target_category_id
                        ]
                    ]);
                    $event->trigger();
                }
                
                // Vérifier que la mise à jour a fonctionné
                $verify_sql = "SELECT qc.name as category_name
                              FROM {question_categories} qc
                              INNER JOIN {question_bank_entries} qbe ON qbe.questioncategoryid = qc.id
                              INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                              WHERE qv.questionid = :questionid
                              LIMIT 1";
                $verify_result = $DB->get_record_sql($verify_sql, ['questionid' => $questionid]);
                
                if (!$verify_result || $verify_result->category_name != $target_category->name) {
                    throw new Exception('Vérification échouée après déplacement');
                }
                
                debugging('✅ Verification successful: question is now in ' . $verify_result->category_name, DEBUG_DEVELOPER);
                
                // Valider la transaction
                $transaction->allow_commit();
                
                // Log d'audit
                require_once(__DIR__ . '/audit_logger.php');
                audit_logger::log_action(
                    'question_moved_to_olution',
                    [
                        'question_id' => $questionid,
                        'question_name' => $question->name,
                        'question_type' => $question->qtype,
                        'old_category_id' => $current_category->id,
                        'old_category_name' => $current_category->name,
                        'target_category_id' => $target_category_id,
                        'target_category_name' => $target_category->name,
                        'message' => 'Question déplacée vers Olution: ' . $target_category->name
                    ],
                    $questionid
                );
                
                debugging('✅ Question successfully moved to Olution: ' . $target_category->name, DEBUG_DEVELOPER);
                return true;
                
            } catch (\Exception $inner_e) {
                debugging('❌ Error in transaction: ' . $inner_e->getMessage(), DEBUG_DEVELOPER);
                throw $inner_e;
            }
            
        } catch (\Exception $e) {
            debugging('❌ Error in move_question_to_olution: ' . $e->getMessage(), DEBUG_DEVELOPER);
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

    /**
     * Teste le déplacement automatique vers Olution
     * 
     * 🔧 v1.11.13 : NOUVELLE FONCTION - Test du déplacement automatique
     * Cette fonction permet de tester le déplacement automatique vers Olution
     * en utilisant des questions réelles de la base de données.
     * 
     * @param int $limit Nombre maximum de questions à tester (défaut: 3)
     * @return array Résultats du test
     */
    public static function test_automatic_movement_to_olution($limit = 3) {
        global $DB;
        
        try {
            debugging('🧪 Starting test_automatic_movement_to_olution with limit: ' . $limit, DEBUG_DEVELOPER);
            
            // Vérifier que la catégorie Olution existe
            $olution = local_question_diagnostic_find_olution_category();
            if (!$olution) {
                return [
                    'success' => false,
                    'message' => 'Catégorie Olution non trouvée',
                    'tested_questions' => 0,
                    'moved_questions' => 0,
                    'failed_questions' => 0,
                    'details' => []
                ];
            }
            
            debugging('✅ Olution category found: ' . $olution->name . ' (ID: ' . $olution->id . ')', DEBUG_DEVELOPER);
            
            // Récupérer les sous-catégories d'Olution
            $olution_subcategories = local_question_diagnostic_get_olution_subcategories($olution->id);
            if (empty($olution_subcategories)) {
                return [
                    'success' => false,
                    'message' => 'Aucune sous-catégorie Olution trouvée',
                    'tested_questions' => 0,
                    'moved_questions' => 0,
                    'failed_questions' => 0,
                    'details' => []
                ];
            }
            
            debugging('✅ Found ' . count($olution_subcategories) . ' Olution subcategories', DEBUG_DEVELOPER);
            
            // Récupérer quelques questions qui ne sont PAS dans Olution
            $non_olution_questions_sql = "SELECT DISTINCT q.*
                                        FROM {question} q
                                        INNER JOIN {question_versions} qv ON qv.questionid = q.id
                                        INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                                        INNER JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                                        WHERE qc.id NOT IN (
                                            SELECT id FROM {question_categories} 
                                            WHERE parent = :olution_id OR id = :olution_id
                                        )
                                        AND q.name IS NOT NULL
                                        AND q.name != ''
                                        LIMIT :limit";
            
            $non_olution_questions = $DB->get_records_sql($non_olution_questions_sql, [
                'olution_id' => $olution->id,
                'limit' => $limit
            ]);
            
            if (empty($non_olution_questions)) {
                return [
                    'success' => false,
                    'message' => 'Aucune question hors Olution trouvée pour le test',
                    'tested_questions' => 0,
                    'moved_questions' => 0,
                    'failed_questions' => 0,
                    'details' => []
                ];
            }
            
            debugging('✅ Found ' . count($non_olution_questions) . ' questions outside Olution for testing', DEBUG_DEVELOPER);
            
            $test_results = [];
            $moved_count = 0;
            $failed_count = 0;
            
            foreach ($non_olution_questions as $question) {
                // Choisir une sous-catégorie Olution aléatoire comme cible
                $target_category = $olution_subcategories[array_rand($olution_subcategories)];
                
                debugging('🧪 Testing move: question ' . $question->id . ' (' . $question->name . ') to ' . $target_category->name . ' (ID: ' . $target_category->id . ')', DEBUG_DEVELOPER);
                
                $move_result = self::move_question_to_olution($question->id, $target_category->id);
                
                $test_result = [
                    'question_id' => $question->id,
                    'question_name' => $question->name,
                    'question_type' => $question->qtype,
                    'target_category_id' => $target_category->id,
                    'target_category_name' => $target_category->name,
                    'move_result' => $move_result,
                    'success' => ($move_result === true)
                ];
                
                if ($move_result === true) {
                    $moved_count++;
                    debugging('✅ Test successful: question ' . $question->id . ' moved to ' . $target_category->name, DEBUG_DEVELOPER);
                } else {
                    $failed_count++;
                    debugging('❌ Test failed: question ' . $question->id . ' - ' . $move_result, DEBUG_DEVELOPER);
                }
                
                $test_results[] = $test_result;
            }
            
            $overall_success = ($moved_count > 0);
            
            debugging('🏁 Test completed: ' . $moved_count . ' moved, ' . $failed_count . ' failed', DEBUG_DEVELOPER);
            
            return [
                'success' => $overall_success,
                'message' => 'Test terminé: ' . $moved_count . ' questions déplacées, ' . $failed_count . ' échecs',
                'tested_questions' => count($non_olution_questions),
                'moved_questions' => $moved_count,
                'failed_questions' => $failed_count,
                'olution_category' => [
                    'id' => $olution->id,
                    'name' => $olution->name
                ],
                'target_subcategories' => array_map(function($cat) {
                    return ['id' => $cat->id, 'name' => $cat->name];
                }, $olution_subcategories),
                'details' => $test_results
            ];
            
        } catch (Exception $e) {
            debugging('❌ Error in test_automatic_movement_to_olution: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [
                'success' => false,
                'message' => 'Erreur lors du test: ' . $e->getMessage(),
                'tested_questions' => 0,
                'moved_questions' => 0,
                'failed_questions' => 0,
                'details' => []
            ];
        }
    }
}
