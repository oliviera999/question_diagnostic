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
require_once(__DIR__ . '/audit_logger.php');

/**
 * Gestionnaire de catégories de questions
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class category_manager {

    /**
     * Récupère toutes les catégories avec leurs statistiques
     *
     * @return array Tableau des catégories avec métadonnées
     */
    public static function get_all_categories_with_stats() {
        global $DB;

        try {
            // VERSION OPTIMISÉE AVEC FALLBACK
            // Essayer d'abord avec une requête simplifiée plus compatible
            
            // Étape 1 : Récupérer toutes les catégories
            $categories = $DB->get_records('question_categories', null, 'contextid, parent, name ASC');
            
            // Étape 2 : Compter les questions par catégorie (2 requêtes pour sécurité)
            // ⚠️ MOODLE 4.5 : Le statut caché est dans question_versions.status, PAS dans question.hidden
            $sql_questions = "SELECT qbe.questioncategoryid,
                                     COUNT(DISTINCT q.id) as total_questions,
                                     SUM(CASE WHEN qv.status != 'hidden' THEN 1 ELSE 0 END) as visible_questions
                              FROM {question_bank_entries} qbe
                              INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                              INNER JOIN {question} q ON q.id = qv.questionid
                              GROUP BY qbe.questioncategoryid";
            $questions_counts = $DB->get_records_sql($sql_questions);
            
            // ⚠️ MOODLE 4.5 : La table question n'a PAS de colonne 'category'
            // Utiliser question_bank_entries.questioncategoryid à la place
            $sql_all_questions = "SELECT questioncategoryid as id, COUNT(*) as question_count
                                  FROM {question_bank_entries}
                                  WHERE questioncategoryid IS NOT NULL
                                  GROUP BY questioncategoryid";
            $all_questions_counts = $DB->get_records_sql($sql_all_questions);
            
            // Étape 3 : Compter les sous-catégories par parent (1 requête)
            $sql_subcats = "SELECT parent, COUNT(*) as subcat_count
                            FROM {question_categories}
                            WHERE parent IS NOT NULL AND parent > 0
                            GROUP BY parent";
            $subcat_counts = $DB->get_records_sql($sql_subcats);
            
            // Étape 4 : Vérifier les contextes valides (1 requête)
            $sql_contexts = "SELECT qc.id, qc.contextid
                            FROM {question_categories} qc
                            LEFT JOIN {context} ctx ON ctx.id = qc.contextid
                            WHERE ctx.id IS NULL";
            $invalid_contexts = $DB->get_records_sql($sql_contexts);
            $invalid_context_ids = [];
            foreach ($invalid_contexts as $ctx_record) {
                $invalid_context_ids[] = $ctx_record->id;
            }
            
            // Étape 4.5 : Détecter les doublons (1 requête)
            // ⚠️ FIX v1.5.8 : Utiliser get_fieldset_sql au lieu de get_records_sql
            // car duplicate_id n'est PAS unique (une catégorie peut avoir plusieurs doublons)
            $sql_duplicates = "SELECT qc1.id
                              FROM {question_categories} qc1
                              INNER JOIN {question_categories} qc2 
                                  ON LOWER(TRIM(qc1.name)) = LOWER(TRIM(qc2.name))
                                  AND qc1.contextid = qc2.contextid
                                  AND qc1.parent = qc2.parent
                                  AND qc1.id != qc2.id";
            $duplicate_ids = $DB->get_fieldset_sql($sql_duplicates);
            if (!$duplicate_ids) {
                $duplicate_ids = [];
            } else {
                $duplicate_ids = array_unique($duplicate_ids); // Éliminer les doublons dans le résultat
            }
            
            // 🚀 OPTIMISATION : Pré-charger TOUS les contextes enrichis en batch (1 requête au lieu de N)
            // Étape 5.1 : Récupérer tous les contextids uniques
            $unique_contextids = array_unique(array_map(function($cat) { return $cat->contextid; }, $categories));
            
            // Étape 5.2 : Pré-charger tous les contextes enrichis d'un coup
            $contexts_enriched_map = [];
            foreach ($unique_contextids as $ctxid) {
                try {
                    $context_details = local_question_diagnostic_get_context_details($ctxid);
                    $contexts_enriched_map[$ctxid] = $context_details;
                } catch (\Exception $e) {
                    // En cas d'erreur, stocker un contexte par défaut
                    $contexts_enriched_map[$ctxid] = (object)[
                        'context_name' => 'Erreur',
                        'course_name' => null,
                        'module_name' => null,
                        'context_type' => null
                    ];
                }
            }
            
            // 🚀 OPTIMISATION : Pré-calculer les vérifications de contextes COURSE pour protection
            // Récupérer en batch tous les contextes de type COURSE
            $course_context_ids = [];
            if (!empty($unique_contextids)) {
                list($insql, $params) = $DB->get_in_or_equal($unique_contextids);
                $params[] = CONTEXT_COURSE;
                $course_contexts = $DB->get_records_sql(
                    "SELECT id FROM {context} WHERE id $insql AND contextlevel = ?",
                    $params
                );
                $course_context_ids = array_keys($course_contexts);
            }
            
            // Étape 5.3 : Construire le résultat avec données pré-chargées
            $result = [];
            foreach ($categories as $cat) {
                // Stats des questions (via question_bank_entries)
                $question_data = isset($questions_counts[$cat->id]) ? $questions_counts[$cat->id] : null;
                $total_questions = $question_data ? (int)$question_data->total_questions : 0;
                $visible_questions = $question_data ? (int)$question_data->visible_questions : 0;
                
                // ⚠️ SÉCURITÉ : Vérifier le nombre RÉEL de questions dans la table question
                $real_question_count = 0;
                if (isset($all_questions_counts[$cat->id])) {
                    $real_question_count = (int)$all_questions_counts[$cat->id]->question_count;
                }
                
                // Utiliser le maximum des deux comptages pour la sécurité
                $total_questions = max($total_questions, $real_question_count);
                
                // Stats des sous-catégories
                $subcat_data = isset($subcat_counts[$cat->id]) ? $subcat_counts[$cat->id] : null;
                $subcategories = $subcat_data ? (int)$subcat_data->subcat_count : 0;
                
                // Validité du contexte
                $context_valid = !in_array($cat->id, $invalid_context_ids);
                
                // Vérifier si la catégorie est protégée (utilise les données pré-calculées)
                $is_protected = false;
                $protection_reason = '';
                
                // Protection 1 : "Default for..." AVEC contexte valide
                // 🔧 v1.10.3 : Protection conditionnelle - protéger SEULEMENT si contexte valide
                // Les catégories "Default for" orphelines (contexte supprimé) sont supprimables
                if ((stripos($cat->name, 'Default for') !== false || stripos($cat->name, 'Par défaut pour') !== false) 
                    && $context_valid) {
                    $is_protected = true;
                    $protection_reason = 'Catégorie par défaut Moodle (contexte actif)';
                }
                // Protection 2 : Catégorie avec description
                else if (!empty($cat->info)) {
                    $is_protected = true;
                    $protection_reason = 'A une description';
                }
                // Protection 3 : TOUTES les catégories TOP (parent = 0)
                // 🔧 v1.9.29 : Protection renforcée pour toutes les catégories racine
                else if ($cat->parent == 0 && $context_valid) {
                    $is_protected = true;
                    $protection_reason = 'Catégorie racine (top-level)';
                }
                
                // Vérifier si c'est un doublon
                $is_duplicate = in_array($cat->id, $duplicate_ids);
                
                // Construire les stats
                $stats = (object)[
                    'total_questions' => $total_questions,
                    'visible_questions' => $visible_questions,
                    'subcategories' => $subcategories,
                    'context_valid' => $context_valid,
                    'is_empty' => ($total_questions == 0 && $subcategories == 0),
                    'is_orphan' => !$context_valid,
                    'is_duplicate' => $is_duplicate,
                    'is_protected' => $is_protected,
                    'protection_reason' => $protection_reason,
                ];
                
                // 🚀 OPTIMISATION : Utiliser les contextes enrichis pré-chargés
                if ($context_valid && isset($contexts_enriched_map[$cat->contextid])) {
                    $context_details = $contexts_enriched_map[$cat->contextid];
                    $stats->context_name = $context_details->context_name;
                    $stats->course_name = $context_details->course_name;
                    $stats->module_name = $context_details->module_name;
                    $stats->context_type = $context_details->context_type;
                } else if (!$context_valid) {
                    $stats->context_name = 'Contexte supprimé (ID: ' . $cat->contextid . ')';
                    $stats->course_name = null;
                    $stats->module_name = null;
                    $stats->context_type = null;
                } else {
                    // Fallback si pas dans la map (ne devrait pas arriver)
                    $stats->context_name = 'Erreur';
                    $stats->course_name = null;
                    $stats->module_name = null;
                    $stats->context_type = null;
                }
                
                $result[] = (object)[
                    'category' => $cat,
                    'stats' => $stats,
                ];
            }
            
            return $result;
            
        } catch (\Exception $e) {
            // FALLBACK : Si erreur SQL, utiliser l'ancienne méthode (lente mais fiable)
            debugging('Erreur dans get_all_categories_with_stats optimisé, utilisation fallback : ' . $e->getMessage(), DEBUG_DEVELOPER);
            return self::get_all_categories_with_stats_fallback();
        }
    }
    
    /**
     * Version fallback (ancienne méthode) si la version optimisée échoue
     * @return array
     */
    private static function get_all_categories_with_stats_fallback() {
        global $DB;

        $categories = $DB->get_records('question_categories', null, 'contextid, parent, name ASC');
        $result = [];

        foreach ($categories as $cat) {
            $stats = self::get_category_stats($cat);
            $result[] = (object)[
                'category' => $cat,
                'stats' => $stats,
            ];
        }

        return $result;
    }

    /**
     * Obtient les statistiques d'une catégorie
     *
     * @param object $category Objet catégorie
     * @return object Statistiques
     */
    public static function get_category_stats($category) {
        global $DB;

        $stats = new \stdClass();
        
        try {
            // Nombre de questions visibles - Compatible Moodle 4.5 avec question_bank_entries
            // ⚠️ MOODLE 4.5 : Le statut caché est dans question_versions.status, PAS dans question.hidden
            // IMPORTANT : Vérifier que la catégorie existe dans question_categories pour éviter les entries orphelines
            $sql = "SELECT COUNT(DISTINCT q.id) 
                    FROM {question} q
                    INNER JOIN {question_versions} qv ON qv.questionid = q.id
                    INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                    INNER JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                    WHERE qbe.questioncategoryid = :categoryid AND qv.status != 'hidden'";
            $stats->visible_questions = (int)$DB->count_records_sql($sql, ['categoryid' => $category->id]);
            
            // Nombre total de questions (incluant cachées) - Compatible Moodle 4.x
            $sql = "SELECT COUNT(DISTINCT q.id) 
                    FROM {question} q
                    INNER JOIN {question_versions} qv ON qv.questionid = q.id
                    INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                    INNER JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                    WHERE qbe.questioncategoryid = :categoryid";
            $stats->total_questions = (int)$DB->count_records_sql($sql, ['categoryid' => $category->id]);
            
            // Nombre de sous-catégories
            $stats->subcategories = $DB->count_records('question_categories', [
                'parent' => $category->id
            ]);
            
            // Contexte enrichi (avec cours et module)
            try {
                // Vérifier d'abord si le contexte existe dans la table context
                $context_exists = $DB->record_exists('context', ['id' => $category->contextid]);
                
                if ($context_exists) {
                    $context_details = local_question_diagnostic_get_context_details($category->contextid);
                    $stats->context_name = $context_details->context_name;
                    $stats->course_name = $context_details->course_name;
                    $stats->module_name = $context_details->module_name;
                    $stats->context_type = $context_details->context_type;
                    $stats->context_valid = true;
                } else {
                    $stats->context_name = 'Contexte supprimé (ID: ' . $category->contextid . ')';
                    $stats->course_name = null;
                    $stats->module_name = null;
                    $stats->context_type = null;
                    $stats->context_valid = false;
                }
            } catch (\Exception $e) {
                $stats->context_name = 'Erreur';
                $stats->course_name = null;
                $stats->module_name = null;
                $stats->context_type = null;
                $stats->context_valid = false;
            }
            
            // Statut
            $stats->is_empty = ($stats->total_questions == 0 && $stats->subcategories == 0);
            // Une catégorie est orpheline si son contexte n'existe PAS dans la table context
            $stats->is_orphan = !$stats->context_valid;
            
            // Vérifier si la catégorie est protégée
            $stats->is_protected = false;
            $stats->protection_reason = '';
            
            // Protection 1 : "Default for..." AVEC contexte valide
            // 🔧 v1.10.3 : Protection conditionnelle - protéger SEULEMENT si contexte valide
            // Les catégories "Default for" orphelines (contexte supprimé) sont supprimables
            if ((stripos($category->name, 'Default for') !== false || stripos($category->name, 'Par défaut pour') !== false) 
                && $stats->context_valid) {
                $stats->is_protected = true;
                $stats->protection_reason = 'Catégorie par défaut Moodle (contexte actif)';
            }
            // Protection 2 : Catégorie avec description
            else if (!empty($category->info)) {
                $stats->is_protected = true;
                $stats->protection_reason = 'A une description';
            }
            // Protection 3 : TOUTES les catégories TOP (parent = 0)
            // 🔧 v1.9.29 : Protection renforcée pour toutes les catégories racine
            else if ($category->parent == 0 && $stats->context_valid) {
                $stats->is_protected = true;
                $stats->protection_reason = 'Catégorie racine (top-level)';
            }
            
        } catch (\Exception $e) {
            // En cas d'erreur, retourner des stats par défaut
            $stats->visible_questions = 0;
            $stats->total_questions = 0;
            $stats->subcategories = 0;
            $stats->context_name = 'Erreur';
            $stats->context_valid = false;
            $stats->is_empty = true;
            $stats->is_orphan = true;
        }

        return $stats;
    }

    /**
     * Trouve les catégories en doublon - VERSION OPTIMISÉE
     *
     * @param int $limit Limite du nombre de doublons à retourner (0 = tous)
     * @return array Tableau des doublons [cat1, cat2]
     */
    public static function find_duplicates($limit = 100) {
        global $DB;

        // VERSION OPTIMISÉE : Utiliser SQL pour trouver les doublons directement
        // Au lieu de charger toutes les catégories en mémoire
        $sql = "SELECT qc1.id as id1, qc1.name as name1, qc1.contextid as context1,
                       qc2.id as id2, qc2.name as name2, qc2.contextid as context2,
                       qc1.parent
                FROM {question_categories} qc1
                INNER JOIN {question_categories} qc2 
                    ON LOWER(TRIM(qc1.name)) = LOWER(TRIM(qc2.name))
                    AND qc1.contextid = qc2.contextid
                    AND qc1.parent = qc2.parent
                    AND qc1.id < qc2.id
                ORDER BY qc1.name, qc1.id";
        
        if ($limit > 0) {
            $duplicates_raw = $DB->get_records_sql($sql, [], 0, $limit);
        } else {
            $duplicates_raw = $DB->get_records_sql($sql);
        }
        
        $duplicates = [];
        foreach ($duplicates_raw as $dup) {
            $cat1 = $DB->get_record('question_categories', ['id' => $dup->id1]);
            $cat2 = $DB->get_record('question_categories', ['id' => $dup->id2]);
            
            if ($cat1 && $cat2) {
                $duplicates[] = [$cat1, $cat2];
            }
        }
        
        return $duplicates;
    }
    
    // 🗑️ REMOVED v1.9.27 : find_duplicates_old() supprimée
    // Cette méthode était marquée deprecated et n'était jamais utilisée.
    // Utiliser find_duplicates($limit) à la place (version optimisée avec SQL)

    /**
     * Identifie les catégories "Default for" redondantes à nettoyer
     * 
     * 🆕 v1.11.18 : Feature "Nettoyage redondant"
     * 
     * @return array Structure: ['contextid' => ['context_name' => str, 'keep' => object, 'delete' => array]]
     */
    public static function get_redundant_default_categories() {
        global $DB;
        
        $redundant_groups = [];
        
        // 1. Récupérer toutes les catégories potentielles (vides + nom type défaut)
        // Optimisation : On filtre d'abord grossièrement par nom
        $likeen = $DB->sql_like('qc.name', '?', false);
        $likefr = $DB->sql_like('qc.name', '?', false);
        $sql = "SELECT qc.*, ctx.contextlevel, ctx.instanceid
                FROM {question_categories} qc
                JOIN {context} ctx ON ctx.id = qc.contextid
                WHERE ($likeen OR $likefr)
                ORDER BY qc.contextid, qc.id ASC";
                
        $candidates = $DB->get_records_sql($sql, ['%Default for%', '%Défaut pour%']);
        
        // Grouper par contexte
        $by_context = [];
        foreach ($candidates as $cat) {
            // Vérification stricte : doit être VIDE (0 questions, 0 sous-cats)
            $stats = self::get_category_stats($cat);
            if ($stats->is_empty) {
                if (!isset($by_context[$cat->contextid])) {
                    $by_context[$cat->contextid] = [];
                }
                $by_context[$cat->contextid][] = $cat;
            }
        }
        
        // Analyser chaque contexte pour trouver les redondances
        foreach ($by_context as $contextid => $cats) {
            // S'il n'y a qu'une seule catégorie défaut vide, on ne touche pas (c'est la normale)
            if (count($cats) < 2) {
                continue;
            }
            
            // S'il y a plusieurs candidats :
            // 1. On garde le premier (le plus ancien par ID, car ORDER BY id ASC)
            // 2. On marque les autres comme supprimables
            
            $keep = array_shift($cats); // Le premier est gardé
            $delete = $cats;            // Le reste est à supprimer
            
            // Enrichir les infos du contexte pour l'affichage
            $context_info = 'Contexte ID: ' . $contextid;
            try {
                $ctx_obj = \context::instance_by_id($contextid);
                $context_info = $ctx_obj->get_context_name();
            } catch (\Exception $e) {
                $context_info .= ' (Invalide)';
            }
            
            $redundant_groups[$contextid] = [
                'context_id' => $contextid,
                'context_name' => $context_info,
                'keep' => $keep,
                'delete' => $delete,
                'count' => count($delete)
            ];
        }
        
        return $redundant_groups;
    }

    /**
     * Supprime une catégorie vide (VERSION AVANCÉE avec bypass sécurité conditionnel)
     *
     * @param int $categoryid ID de la catégorie
     * @param bool $bypass_default_protection Si true, autorise la suppression d'une "Default for" (pour nettoyage redondance)
     * @return bool|string true si succès, message d'erreur sinon
     */
    public static function delete_category($categoryid, $bypass_default_protection = false) {
        global $DB, $CFG;

        try {
            $category = $DB->get_record('question_categories', ['id' => $categoryid], '*', MUST_EXIST);
            
            // 🛡️ PROTECTION 1 : Catégories "Default for..." AVEC contexte valide
            // 🔧 v1.10.3 : Protection conditionnelle - protéger SEULEMENT si contexte actif
            // Les catégories "Default for" orphelines (contexte supprimé) peuvent être supprimées
            if (!$bypass_default_protection && (stripos($category->name, 'Default for') !== false || stripos($category->name, 'Par défaut pour') !== false)) {
                // Vérifier si le contexte est valide
                try {
                    $context = \context::instance_by_id($category->contextid, IGNORE_MISSING);
                    if ($context) {
                        // Contexte valide → PROTÉGÉE
                        return "❌ PROTÉGÉE : Cette catégorie par défaut est liée à un contexte actif (cours, quiz, etc.) et ne doit pas être supprimée. Si vous devez vraiment la supprimer, supprimez d'abord le cours/contexte associé.";
                    }
                    // Sinon : contexte invalide/orphelin → SUPPRIMABLE (continuer les autres vérifications)
                } catch (\Exception $e) {
                    // Erreur de contexte → considéré comme orphelin → SUPPRIMABLE (continuer)
                }
            }
            
            // 🛡️ PROTECTION 2 : Catégories avec description (usage intentionnel)
            if (!empty($category->info)) {
                return "❌ PROTÉGÉE : Cette catégorie a une description, indiquant un usage intentionnel. Supprimez d'abord la description si vous êtes certain de vouloir la supprimer.";
            }
            
            // 🛡️ PROTECTION 3 : TOUTES les catégories TOP (parent = 0)
            // 🔧 v1.9.29 : Protection renforcée - Toutes les catégories racine sont protégées
            if ($category->parent == 0) {
                try {
                    $context = \context::instance_by_id($category->contextid, IGNORE_MISSING);
                    if ($context) {
                        // Protéger TOUTE catégorie racine avec contexte valide
                        return "❌ PROTÉGÉE : Cette catégorie est une catégorie racine (parent=0, top-level). Les catégories racine sont critiques pour la structure de Moodle et ne doivent jamais être supprimées.";
                    }
                } catch (\Exception $e) {
                    // Si erreur de contexte, continuer (peut-être une catégorie orpheline)
                }
            }
            
            // ⚠️ SÉCURITÉ CRITIQUE : Comptage via question_bank_entries (Moodle 4.x)
            // La table question n'a PAS de colonne 'category' dans Moodle 4.5+
            $questioncount = (int)$DB->count_records('question_bank_entries', ['questioncategoryid' => $categoryid]);
            
            if ($questioncount > 0) {
                debugging("Tentative de suppression catégorie $categoryid avec $questioncount questions", DEBUG_DEVELOPER);
                return "❌ IMPOSSIBLE : La catégorie contient $questioncount question(s). AUCUNE catégorie contenant des questions ne peut être supprimée.";
            }
            
            $subcatcount = $DB->count_records('question_categories', ['parent' => $categoryid]);
            
            if ($subcatcount > 0) {
                debugging("Tentative de suppression catégorie $categoryid avec $subcatcount sous-catégories", DEBUG_DEVELOPER);
                return "❌ IMPOSSIBLE : La catégorie contient $subcatcount sous-catégorie(s).";
            }
            
            // 🆕 v1.11.5 : Transaction et API Native Moodle
            $transaction = $DB->start_delegated_transaction();
            
            try {
                // Utiliser l'API native si disponible (recommandé Moodle 4.5)
                require_once($CFG->libdir . '/questionlib.php');
                
                if (function_exists('question_delete_category')) {
                    question_delete_category($categoryid);
                } else {
                    // Fallback manuel sécurisé (mais ne devrait pas arriver sur Moodle standard)
                    $DB->delete_records('question_categories', ['id' => $categoryid]);
                }
                
                $transaction->allow_commit();
                
            } catch (\Exception $e) {
                $transaction->rollback($e);
                throw $e;
            }
            
            // 🆕 v1.9.39 : Log d'audit pour traçabilité
            audit_logger::log_category_deletion($categoryid, $category->name, 0);
            
            return true;
            
        } catch (\Exception $e) {
            debugging("Exception suppression catégorie $categoryid: " . $e->getMessage(), DEBUG_DEVELOPER);
            return "❌ Erreur SQL : " . $e->getMessage() . " (Catégorie ID: $categoryid)";
        }
    }

    /**
     * Supprime plusieurs catégories vides
     *
     * @param array $categoryids Tableau d'IDs
     * @return array ['success' => count, 'errors' => []]
     */
    public static function delete_categories_bulk($categoryids) {
        $success = 0;
        $errors = [];

        foreach ($categoryids as $id) {
            $result = self::delete_category($id);
            if ($result === true) {
                $success++;
            } else {
                $errors[] = "Catégorie $id : $result";
            }
        }

        return ['success' => $success, 'errors' => $errors];
    }

    /**
     * Fusionne deux catégories (déplace les questions de source vers destination)
     *
     * @param int $sourceid ID de la catégorie source
     * @param int $destid ID de la catégorie destination
     * @return bool|string true si succès, message d'erreur sinon
     */
    /**
     * Fusionne deux catégories en déplaçant questions et sous-catégories
     * 
     * 🆕 v1.9.30 : TRANSACTION SQL avec rollback automatique si erreur
     * 
     * @param int $sourceid ID de la catégorie source (sera supprimée)
     * @param int $destid ID de la catégorie destination (recevra le contenu)
     * @return bool|string true si succès, message d'erreur sinon
     */
    public static function merge_categories($sourceid, $destid) {
        global $DB, $CFG;

        // 🛡️ v1.9.30 : Validation préalable (avant transaction)
        if ($sourceid == $destid) {
            return "Impossible de fusionner une catégorie avec elle-même.";
        }

        try {
            require_once($CFG->libdir . '/questionlib.php');

            // Vérifier que les catégories existent
            $source = $DB->get_record('question_categories', ['id' => $sourceid], '*', MUST_EXIST);
            $dest = $DB->get_record('question_categories', ['id' => $destid], '*', MUST_EXIST);
            
            // Vérifier qu'elles sont dans le même contexte
            if ($source->contextid != $dest->contextid) {
                return "Les catégories doivent être dans le même contexte pour être fusionnées.";
            }
            
            // 🛡️ v1.9.30 : Vérifier que la source n'est pas protégée
            $source_stats = self::get_category_stats($source);
            if ($source_stats->is_protected) {
                return "❌ PROTÉGÉE : La catégorie source est protégée et ne peut pas être fusionnée. Raison : " . $source_stats->protection_reason;
            }
            
            // 🆕 v1.9.30 : DÉBUT DE LA TRANSACTION SQL
            // Si une erreur survient, TOUT sera annulé automatiquement
            $transaction = $DB->start_delegated_transaction();
            
            try {
                // Étape 1 : Récupérer les IDs des questions à déplacer pour les événements
                // ⚠️ MOODLE 4.5 : Structure question -> question_versions -> question_bank_entries
                $sql_q_ids = "SELECT DISTINCT q.id 
                              FROM {question} q
                              JOIN {question_versions} qv ON qv.questionid = q.id
                              JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                              WHERE qbe.questioncategoryid = :sourceid";
                $question_ids = $DB->get_fieldset_sql($sql_q_ids, ['sourceid' => $sourceid]);

                // Étape 2 : Déplacer toutes les questions de source vers dest (SQL rapide)
                // Compatible Moodle 4.x (question_bank_entries)
                $sql = "UPDATE {question_bank_entries} SET questioncategoryid = :destid WHERE questioncategoryid = :sourceid";
                $moved_questions = $DB->execute($sql, ['destid' => $destid, 'sourceid' => $sourceid]);
                
                debugging('Fusion catégories v1.11.5 : ' . ($moved_questions ? 'Questions déplacées' : 'Aucune question') . ' de cat ' . $sourceid . ' vers ' . $destid, DEBUG_DEVELOPER);
                
                // Étape 3 : Déclencher les événements question_moved
                if (!empty($question_ids)) {
                    $context = \context::instance_by_id($source->contextid);
                    foreach ($question_ids as $qid) {
                        $event = \core\event\question_moved::create([
                            'objectid' => $qid,
                            'context' => $context,
                            'other' => [
                                'oldcategoryid' => $sourceid,
                                'newcategoryid' => $destid
                            ]
                        ]);
                        $event->trigger();
                    }
                }

                // Étape 4 : Déplacer les sous-catégories
                $subcats = $DB->get_records('question_categories', ['parent' => $sourceid]);
                $moved_subcats = 0;
                
                foreach ($subcats as $subcat) {
                    $subcat->parent = $destid;
                    $DB->update_record('question_categories', $subcat);
                    $moved_subcats++;
                    
                    // Trigger event for category update
                    $event = \core\event\question_category_updated::create([
                        'objectid' => $subcat->id,
                        'context' => \context::instance_by_id($subcat->contextid)
                    ]);
                    $event->trigger();
                }
                
                debugging('Fusion catégories v1.11.5 : ' . $moved_subcats . ' sous-catégorie(s) déplacée(s)', DEBUG_DEVELOPER);
                
                // Étape 5 : Supprimer la catégorie source (maintenant vide) via API Moodle
                question_delete_category($sourceid);
                
                debugging('Fusion catégories v1.11.5 : Catégorie source ' . $sourceid . ' supprimée', DEBUG_DEVELOPER);
                
                // ✅ TOUT S'EST BIEN PASSÉ : VALIDER LA TRANSACTION
                $transaction->allow_commit();
                
                // 🧹 v1.9.30 : Purger les caches après fusion réussie
                cache_manager::purge_all_caches();
                
                return true;
                
            } catch (\Exception $inner_e) {
                // 🔄 ERREUR DANS LA TRANSACTION : ROLLBACK AUTOMATIQUE
                // Toutes les modifications seront annulées
                debugging('Erreur dans transaction merge_categories : ' . $inner_e->getMessage(), DEBUG_DEVELOPER);
                throw $inner_e; // Re-lancer pour le catch externe
            }
            
        } catch (\Exception $e) {
            // Le rollback a déjà été effectué automatiquement par Moodle
            $error_msg = "Erreur lors de la fusion : " . $e->getMessage();
            debugging($error_msg, DEBUG_DEVELOPER);
            return $error_msg;
        }
    }

    /**
     * Déplace une catégorie vers un nouveau parent
     * 
     * 🆕 v1.9.30 : TRANSACTION SQL avec rollback automatique si erreur
     *
     * @param int $categoryid ID de la catégorie à déplacer
     * @param int $newparentid ID du nouveau parent (0 pour racine)
     * @return bool|string true si succès, message d'erreur sinon
     */
    public static function move_category($categoryid, $newparentid) {
        global $DB, $CFG;

        // 🛡️ v1.9.30 : Validation préalable (avant transaction)
        if ($categoryid == $newparentid) {
            return "Une catégorie ne peut pas être son propre parent.";
        }

        try {
            $category = $DB->get_record('question_categories', ['id' => $categoryid], '*', MUST_EXIST);
            
            // Vérifier que le nouveau parent existe (sauf si 0)
            if ($newparentid != 0) {
                $parent = $DB->get_record('question_categories', ['id' => $newparentid], '*', MUST_EXIST);
                
                // Vérifier que le parent est dans le même contexte
                if ($parent->contextid != $category->contextid) {
                    return "Le parent doit être dans le même contexte.";
                }
                
                // Vérifier qu'on ne crée pas de boucle
                if (self::is_ancestor($categoryid, $newparentid)) {
                    return "Impossible de déplacer : cela créerait une boucle.";
                }
            }
            
            // 🛡️ v1.9.30 : Vérifier que la catégorie n'est pas protégée
            $category_stats = self::get_category_stats($category);
            if ($category_stats->is_protected) {
                return "❌ PROTÉGÉE : Cette catégorie est protégée et ne peut pas être déplacée. Raison : " . $category_stats->protection_reason;
            }
            
            // 🆕 v1.9.30 : TRANSACTION SQL (même si une seule opération, pour cohérence)
            $transaction = $DB->start_delegated_transaction();
            
            try {
                $category->parent = $newparentid;
                $DB->update_record('question_categories', $category);
                
                // 🆕 v1.11.5 : Trigger event for Moodle consistency
                $event = \core\event\question_category_updated::create([
                    'objectid' => $category->id,
                    'context' => \context::instance_by_id($category->contextid)
                ]);
                $event->trigger();
                
                debugging('Déplacement catégorie v1.9.30 : Cat ' . $categoryid . ' déplacée vers parent ' . $newparentid, DEBUG_DEVELOPER);
                
                // ✅ Valider la transaction
                $transaction->allow_commit();
                
                // 🧹 Purger les caches
                cache_manager::purge_all_caches();
                
                return true;
                
            } catch (\Exception $inner_e) {
                // 🔄 ROLLBACK AUTOMATIQUE
                debugging('Erreur dans transaction move_category : ' . $inner_e->getMessage(), DEBUG_DEVELOPER);
                throw $inner_e;
            }
            
        } catch (\Exception $e) {
            $error_msg = "Erreur lors du déplacement : " . $e->getMessage();
            debugging($error_msg, DEBUG_DEVELOPER);
            return $error_msg;
        }
    }

    /**
     * Vérifie si une catégorie est ancêtre d'une autre
     *
     * @param int $ancestorid ID de l'ancêtre potentiel
     * @param int $childid ID de l'enfant
     * @return bool
     */
    private static function is_ancestor($ancestorid, $childid) {
        global $DB;

        $current = $childid;
        $visited = [];

        while ($current != 0) {
            if ($current == $ancestorid) {
                return true;
            }
            
            if (in_array($current, $visited)) {
                // Boucle détectée
                return false;
            }
            
            $visited[] = $current;
            $category = $DB->get_record('question_categories', ['id' => $current]);
            
            if (!$category) {
                break;
            }
            
            $current = $category->parent;
        }

        return false;
    }

    /**
     * Exporte les catégories au format CSV
     *
     * @param array $categories Tableau de catégories avec stats
     * @return string Contenu CSV
     */
    public static function export_to_csv($categories) {
        $csv = "ID,Nom,Contexte,Parent,Questions visibles,Questions totales,Sous-catégories,Statut\n";
        
        foreach ($categories as $item) {
            $cat = $item->category;
            $stats = $item->stats;
            
            $status = [];
            if ($stats->is_empty) $status[] = 'Vide';
            if ($stats->is_orphan) $status[] = 'Orpheline';
            $statusstr = empty($status) ? 'OK' : implode(', ', $status);
            
            $csv .= sprintf(
                "%d,\"%s\",\"%s\",%d,%d,%d,%d,\"%s\"\n",
                $cat->id,
                str_replace('"', '""', $cat->name),
                str_replace('"', '""', $stats->context_name),
                $cat->parent,
                $stats->visible_questions,
                $stats->total_questions,
                $stats->subcategories,
                $statusstr
            );
        }
        
        return $csv;
    }

    /**
     * Génère des statistiques globales
     *
     * @return object Statistiques globales
     */
    public static function get_global_stats() {
        global $DB;

        $stats = new \stdClass();
        $stats->total_categories = $DB->count_records('question_categories');
        
        // Compter UNIQUEMENT les questions liées à des catégories valides (Moodle 4.x)
        $sql = "SELECT COUNT(DISTINCT q.id)
                FROM {question} q
                INNER JOIN {question_versions} qv ON qv.questionid = q.id
                INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                INNER JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid";
        $stats->total_questions = (int)$DB->count_records_sql($sql);
        
        // ===================================================================
        // OPTIMISATION : Compter directement avec SQL au lieu de boucler
        // ===================================================================
        
        // Compter les catégories avec au moins une question (Moodle 4.x avec INNER JOIN)
        $sql_with_questions = "SELECT COUNT(DISTINCT qc.id)
                               FROM {question_categories} qc
                               INNER JOIN {question_bank_entries} qbe ON qbe.questioncategoryid = qc.id
                               INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                               INNER JOIN {question} q ON q.id = qv.questionid";
        $stats->categories_with_questions = (int)$DB->count_records_sql($sql_with_questions);
        
        // Compter les catégories orphelines (contexte invalide)
        $sql_orphan = "SELECT COUNT(qc.id)
                       FROM {question_categories} qc
                       LEFT JOIN {context} ctx ON ctx.id = qc.contextid
                       WHERE ctx.id IS NULL";
        $stats->orphan_categories = (int)$DB->count_records_sql($sql_orphan);
        
        // ⚠️ SÉCURITÉ v1.5.3+ : Compter les catégories vides avec double vérification
        // AVEC FALLBACK en cas d'erreur
        try {
            // Méthode 1 : Via question_bank_entries
            $sql_cat_with_q1 = "SELECT DISTINCT qbe.questioncategoryid
                                FROM {question_bank_entries} qbe
                                INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id";
            $cats_with_questions1 = $DB->get_fieldset_sql($sql_cat_with_q1);
            if (!$cats_with_questions1) {
                $cats_with_questions1 = [];
            }
            
            // ⚠️ MOODLE 4.5 : La table question n'a PAS de colonne 'category'
            // Utiliser UNIQUEMENT question_bank_entries
            $cats_with_questions = $cats_with_questions1;
            
            // Catégories avec sous-catégories
            $sql_cat_with_subs = "SELECT DISTINCT parent
                                  FROM {question_categories}
                                  WHERE parent IS NOT NULL AND parent > 0";
            $cats_with_subcats = $DB->get_fieldset_sql($sql_cat_with_subs);
            if (!$cats_with_subcats) {
                $cats_with_subcats = [];
            }
            
            // Compter avec SQL optimisé au lieu de charger tout en mémoire
            // ⚠️ MOODLE 4.5 : Utiliser question_bank_entries au lieu de question.category
            $sql_empty = "SELECT COUNT(qc.id)
                         FROM {question_categories} qc
                         WHERE qc.id NOT IN (
                             SELECT DISTINCT questioncategoryid FROM {question_bank_entries} WHERE questioncategoryid IS NOT NULL
                         )
                         AND qc.id NOT IN (
                             SELECT DISTINCT parent FROM {question_categories} WHERE parent IS NOT NULL AND parent > 0
                         )
                         AND qc.parent != 0
                         AND (qc.info IS NULL OR qc.info = '')
                         AND " . $DB->sql_like('qc.name', ':pattern', true, true, true);
            $stats->empty_categories = (int)$DB->count_records_sql($sql_empty, ['pattern' => '%Default for%']);
            
        } catch (\Exception $e) {
            // FALLBACK : En cas d'erreur, utiliser l'ancienne méthode simple
            debugging('Erreur dans get_global_stats() v1.5.3, utilisation fallback : ' . $e->getMessage(), DEBUG_DEVELOPER);
            $sql_empty_fallback = "SELECT COUNT(qc.id)
                                  FROM {question_categories} qc
                                  WHERE qc.parent != 0
                                  AND (qc.info IS NULL OR qc.info = '')";
            $stats->empty_categories = (int)$DB->count_records_sql($sql_empty_fallback);
        }
        
        // Compter les catégories protégées (pour information)
        // Protection type 1 : "Default for..."
        $stats->protected_default = (int)$DB->count_records_sql("
            SELECT COUNT(*)
            FROM {question_categories}
            WHERE " . $DB->sql_like('name', ':pattern', false), 
            ['pattern' => '%Default for%']);
        
        // Protection type 2 : Catégories avec description
        $stats->protected_with_info = (int)$DB->count_records_sql("
            SELECT COUNT(*)
            FROM {question_categories}
            WHERE info IS NOT NULL AND info != ''
        ");
        
        // Protection type 3 : TOUTES les catégories racine (parent=0)
        // 🔧 v1.9.29 : Protection étendue à TOUTES les catégories top-level
        $stats->protected_root_all = (int)$DB->count_records_sql("
            SELECT COUNT(qc.id)
            FROM {question_categories} qc
            INNER JOIN {context} ctx ON ctx.id = qc.contextid
            WHERE qc.parent = 0
        ");
        
        // Conserver aussi le compteur spécifique COURSE pour compatibilité
        $stats->protected_root_courses = (int)$DB->count_records_sql("
            SELECT COUNT(qc.id)
            FROM {question_categories} qc
            INNER JOIN {context} ctx ON ctx.id = qc.contextid
            WHERE qc.parent = 0
            AND ctx.contextlevel = " . CONTEXT_COURSE
        );
        
        // Total des catégories protégées (éviter les doublons en utilisant UNION)
        // 🔧 v1.9.29 : Inclure TOUTES les catégories racine (pas juste COURSE)
        $stats->total_protected = (int)$DB->count_records_sql("
            SELECT COUNT(DISTINCT qc.id)
            FROM {question_categories} qc
            LEFT JOIN {context} ctx ON ctx.id = qc.contextid
            WHERE (
                " . $DB->sql_like('qc.name', ':pattern1', false) . "
                OR (qc.info IS NOT NULL AND qc.info != '')
                OR (qc.parent = 0 AND ctx.id IS NOT NULL)
            )",
            ['pattern1' => '%Default for%']
        );
        
        // Compter les doublons - version compatible toutes BDD
        // ⚠️ COHÉRENCE v1.5.2+ : Compter le nombre TOTAL de catégories en doublon
        // (pas le nombre de groupes, mais le nombre de catégories individuelles)
        // Pour correspondre au filtre qui affiche chaque catégorie en doublon
        $sql_dup_ids = "SELECT qc1.id
                        FROM {question_categories} qc1
                        INNER JOIN {question_categories} qc2 
                            ON LOWER(TRIM(qc1.name)) = LOWER(TRIM(qc2.name))
                            AND qc1.contextid = qc2.contextid
                            AND qc1.parent = qc2.parent
                            AND qc1.id != qc2.id";
        try {
            $dup_ids = $DB->get_fieldset_sql($sql_dup_ids);
            $stats->duplicates = count(array_unique($dup_ids));
        } catch (\Exception $e) {
            // Si erreur, utiliser 0
            $stats->duplicates = 0;
        }
        
        return $stats;
    }

    /**
     * Génère l'URL pour accéder à une catégorie dans la banque de questions
     *
     * 🔧 REFACTORED: Cette méthode utilise maintenant la fonction centralisée dans lib.php
     * @see local_question_diagnostic_get_question_bank_url()
     * 
     * @param object $category Objet catégorie
     * @return \moodle_url URL vers la banque de questions
     */
    public static function get_question_bank_url($category) {
        return local_question_diagnostic_get_question_bank_url($category);
    }

    /**
     * 🆕 v1.11.3 : Récupère TOUTES les catégories du site (questions + cours)
     * 
     * Cette méthode étend la recherche pour inclure :
     * - Les catégories de questions (question_categories)
     * - Les catégories de cours (course_categories)
     * 
     * @return array Tableau unifié de toutes les catégories avec métadonnées
     */
    public static function get_all_site_categories_with_stats() {
        global $DB;

        try {
            $all_categories = [];

            // ==================================================================================
            // PARTIE 1 : Catégories de QUESTIONS (logique existante)
            // ==================================================================================
            debugging('🔍 Loading question categories...', DEBUG_DEVELOPER);
            
            $question_categories = self::get_all_categories_with_stats();
            
            // Marquer le type pour les catégories de questions
            foreach ($question_categories as $cat) {
                $cat->category_type = 'question';
                $cat->category_type_label = 'Catégorie de questions';
                $cat->can_delete = !$cat->is_protected && $cat->total_questions == 0 && $cat->subcategories == 0;
                $all_categories[] = $cat;
            }
            
            debugging('✅ Loaded ' . count($question_categories) . ' question categories', DEBUG_DEVELOPER);

            // ==================================================================================
            // PARTIE 2 : Catégories de COURS (nouvelle logique)
            // ==================================================================================
            debugging('🔍 Loading course categories...', DEBUG_DEVELOPER);
            
            // Récupérer toutes les catégories de cours
            $course_categories = $DB->get_records('course_categories', null, 'parent, name ASC');
            
            // Compter les cours par catégorie
            $sql_course_counts = "SELECT category, COUNT(*) as course_count
                                 FROM {course}
                                 WHERE category > 0
                                 GROUP BY category";
            $course_counts = $DB->get_records_sql($sql_course_counts);
            
            // Compter les sous-catégories de cours par parent
            $sql_subcat_counts = "SELECT parent, COUNT(*) as subcat_count
                                 FROM {course_categories}
                                 WHERE parent IS NOT NULL AND parent > 0
                                 GROUP BY parent";
            $subcat_counts = $DB->get_records_sql($sql_subcat_counts);
            
            foreach ($course_categories as $cat) {
                // Compter les cours dans cette catégorie
                $course_count = isset($course_counts[$cat->id]) ? (int)$course_counts[$cat->id]->course_count : 0;
                
                // Compter les sous-catégories
                $subcategories = isset($subcat_counts[$cat->id]) ? (int)$subcat_counts[$cat->id]->subcat_count : 0;
                
                // Déterminer le statut
                $status = 'ok';
                $status_label = 'OK';
                $is_protected = false;
                $protection_reason = '';
                
                if ($course_count == 0 && $subcategories == 0) {
                    $status = 'empty';
                    $status_label = 'Vide';
                } else if ($course_count == 0 && $subcategories > 0) {
                    $status = 'orphan';
                    $status_label = 'Orpheline';
                }
                
                // Protection : catégories système importantes
                if ($cat->id == 1 || stripos($cat->name, 'miscellaneous') !== false) {
                    $is_protected = true;
                    $protection_reason = 'Catégorie système protégée';
                }
                
                // Créer l'objet catégorie unifié
                $unified_category = (object)[
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'info' => $cat->description ?? '',
                    'parent' => $cat->parent ?? 0,
                    'contextid' => null, // Pas de contexte pour les catégories de cours
                    'category_type' => 'course',
                    'category_type_label' => 'Catégorie de cours',
                    
                    // Statistiques
                    'total_questions' => 0, // Pas de questions dans les catégories de cours
                    'visible_questions' => 0,
                    'course_count' => $course_count,
                    'subcategories' => $subcategories,
                    
                    // Statut et protection
                    'status' => $status,
                    'status_label' => $status_label,
                    'is_protected' => $is_protected,
                    'protection_reason' => $protection_reason,
                    'can_delete' => !$is_protected && $course_count == 0 && $subcategories == 0,
                    
                    // Contexte (pour les catégories de cours, pas de contexte Moodle)
                    'context_name' => 'Catégorie de cours',
                    'context_type' => 'course_category',
                    'course_name' => null,
                    'module_name' => null,
                    
                    // Doublons (pas applicable aux catégories de cours)
                    'has_duplicates' => false,
                    'duplicate_ids' => [],
                ];
                
                $all_categories[] = $unified_category;
            }
            
            debugging('✅ Loaded ' . count($course_categories) . ' course categories', DEBUG_DEVELOPER);

            // ==================================================================================
            // PARTIE 3 : Tri unifié
            // ==================================================================================
            usort($all_categories, function($a, $b) {
                // Tri par type d'abord (questions puis cours)
                $type_order = ['question' => 0, 'course' => 1];
                $type_cmp = $type_order[$a->category_type] - $type_order[$b->category_type];
                
                if ($type_cmp !== 0) {
                    return $type_cmp;
                }
                
                // Puis tri par nom
                return strcmp($a->name, $b->name);
            });

            debugging('🎯 Total categories loaded: ' . count($all_categories) . ' (Questions: ' . count($question_categories) . ', Courses: ' . count($course_categories) . ')', DEBUG_DEVELOPER);

            return $all_categories;

        } catch (Exception $e) {
            debugging('Error loading all site categories: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
    }
}

