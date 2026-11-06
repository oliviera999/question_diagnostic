<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_question_diagnostic;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->libdir . '/questionlib.php');
require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/audit_logger.php');
require_once(__DIR__ . '/cache_manager.php');

/**
 * Gestionnaire de catégories de questions
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class category_manager {

    /** Raison de protection : la catégorie a un idnumber (usage intentionnel). */
    const PROTECTION_IDNUMBER = 'A un idnumber';

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
            // Set pour lookup O(1) dans la boucle.
            $course_context_id_map = [];
            if (!empty($course_context_ids)) {
                $course_context_id_map = array_fill_keys(array_map('intval', $course_context_ids), true);
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
                    // 🆕 v1.11.20 : préciser le type de racine (cours vs autre)
                    if (!empty($course_context_id_map[(int)$cat->contextid])) {
                        $protection_reason = 'Catégorie racine de cours (parent=0)';
                    } else {
                        $protection_reason = 'Catégorie racine (top-level)';
                    }
                }
                // Protection 4 : catégorie avec idnumber (usage intentionnel : imports, références)
                else if (isset($cat->idnumber) && trim((string)$cat->idnumber) !== '') {
                    $is_protected = true;
                    $protection_reason = self::PROTECTION_IDNUMBER;
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
                // 🆕 v1.11.20 : préciser si c'est une racine de cours (contexte COURSE)
                try {
                    $context = \context::instance_by_id($category->contextid, IGNORE_MISSING);
                    if ($context && (int)$context->contextlevel === CONTEXT_COURSE) {
                        $stats->protection_reason = 'Catégorie racine de cours (parent=0)';
                    }
                } catch (\Exception $e) {
                    // Ignorer : on garde le libellé générique.
                }
            }
            // Protection 4 : catégorie avec idnumber (usage intentionnel : imports, références)
            else if (self::has_idnumber($category)) {
                $stats->is_protected = true;
                $stats->protection_reason = self::PROTECTION_IDNUMBER;
            }
            
        } catch (\Exception $e) {
            // En cas d'erreur, retourner des stats par défaut
            $stats->visible_questions = 0;
            $stats->total_questions = 0;
            $stats->subcategories = 0;
            $stats->context_name = 'Erreur';
            $stats->context_valid = false;
            $stats->is_empty = false;
            $stats->is_orphan = true;
            // Échec fermé : une catégorie illisible est traitée comme protégée.
            $stats->is_protected = true;
            $stats->protection_reason = 'Erreur de lecture des statistiques';
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
        global $DB, $CFG;
        
        $redundant_groups = [];
        
        // 1. Récupérer toutes les catégories candidates par NOM (par défaut Moodle)
        // ⚠️ On ne filtre pas "vide" ici : on doit d'abord savoir quelle catégorie Moodle utilise réellement.
        // Ensuite seulement, on supprime les doublons réellement inutiles (vides, sans sous-catégories).
        //
        // Variantes :
        // - EN: "Default for ..."
        // - FR: "Par défaut pour ..." (avec/sans accent selon encodage/exports)
        $patterns = ['%Default for%', '%Par défaut pour%', '%Defaut pour%'];
        $likesql = [];
        $params = [];
        foreach ($patterns as $p) {
            $likesql[] = $DB->sql_like('qc.name', '?', false);
            $params[] = $p;
        }
        $sql = "SELECT qc.*, ctx.contextlevel, ctx.instanceid
                FROM {question_categories} qc
                JOIN {context} ctx ON ctx.id = qc.contextid
                WHERE (" . implode(' OR ', $likesql) . ")
                ORDER BY qc.contextid, qc.id ASC";
        $candidates = $DB->get_records_sql($sql, $params);
        
        // Grouper par contexte
        $by_context = [];
        foreach ($candidates as $cat) {
            if (!isset($by_context[$cat->contextid])) {
                $by_context[$cat->contextid] = [];
            }
            $by_context[$cat->contextid][] = $cat;
        }
        
        // Analyser chaque contexte pour trouver les redondances
        foreach ($by_context as $contextid => $cats) {
            // S'il n'y a qu'une seule catégorie "par défaut" dans ce contexte, rien à nettoyer.
            if (count($cats) < 2) {
                continue;
            }
            
            // Déterminer LA catégorie par défaut réellement utilisée par Moodle pour ce contexte.
            // Moodle (core) choisit la catégorie "Default for ..." du contexte (souvent la plus ancienne),
            // mais on s'aligne explicitement sur l'API pour éviter toute surprise.
            $keepid = null;
            $keep_reason = '';
            try {
                require_once($CFG->libdir . '/questionlib.php');
                if (function_exists('question_get_default_category')) {
                    $defaultcat = question_get_default_category((int)$contextid, false);
                    if ($defaultcat && !empty($defaultcat->id)) {
                        $keepid = (int)$defaultcat->id;
                        $keep_reason = 'Catégorie par défaut utilisée par Moodle (API question_get_default_category)';
                    }
                }
            } catch (\Exception $e) {
                // Fallback ci-dessous.
            }
            if (empty($keepid)) {
                // Fallback sûr : la liste est triée par id asc, donc le premier est le plus ancien.
                $keepid = (int)$cats[0]->id;
                $keep_reason = 'Fallback : plus ancienne (id minimal)';
            }
            
            // Déterminer quelles catégories supprimer :
            // - garder celle choisie par Moodle
            // - supprimer uniquement les doublons VIDES (0 questions, 0 sous-cats)
            // Seules les catégories qui passent TOUS les contrôles de suppression sont proposées
            // (vides, sans description/idnumber, non visées par une question aléatoire, pas « top »...).
            $keep = null;
            $delete = [];
            $blocked = [];
            foreach ($cats as $cat) {
                if ((int)$cat->id === (int)$keepid) {
                    $keep = $cat;
                    continue;
                }
                $stats = self::get_category_stats($cat);
                if (empty($stats->is_empty)) {
                    continue;
                }
                $refusal = self::get_deletion_refusal($cat, true);
                if ($refusal === null) {
                    $delete[] = $cat;
                } else {
                    $blocked[] = (object)['category' => $cat, 'reason' => $refusal];
                }
            }
            
            // Si rien n'est supprimable, ne pas afficher un groupe "bruyant".
            if (empty($delete)) {
                continue;
            }
            
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
                'blocked' => $blocked,
                'count' => count($delete),
                'total_in_context' => count($cats),
                'keep_reason' => $keep_reason,
            ];
        }
        
        return $redundant_groups;
    }

    /**
     * Le nom ressemble-t-il à une catégorie « par défaut » créée par Moodle ?
     *
     * @param string|null $name
     * @return bool
     */
    private static function is_default_name(?string $name): bool {
        $name = (string)$name;
        return stripos($name, 'Default for') !== false
            || stripos($name, 'Par défaut pour') !== false
            || stripos($name, 'Defaut pour') !== false;
    }

    /**
     * La catégorie a-t-elle un idnumber renseigné ?
     *
     * @param \stdClass $category
     * @return bool
     */
    private static function has_idnumber(\stdClass $category): bool {
        return isset($category->idnumber) && trim((string)$category->idnumber) !== '';
    }

    /**
     * Construit une exception de refus dont le message est affiché tel quel.
     *
     * @param string $message
     * @return \moodle_exception
     */
    private static function refusal(string $message): \moodle_exception {
        return new \moodle_exception('generalexceptionmessage', 'error', '', $message);
    }

    /**
     * Message lisible d'une exception (refus du plugin ou erreur Moodle).
     *
     * @param \Throwable $e
     * @return string
     */
    private static function exception_message(\Throwable $e): string {
        if ($e instanceof \moodle_exception && $e->errorcode === 'generalexceptionmessage' && is_string($e->a)) {
            return $e->a;
        }
        return $e->getMessage();
    }

    /**
     * Annule une transaction déléguée sans relancer l'exception.
     *
     * moodle_transaction::rollback() relance toujours l'exception reçue : on l'absorbe ici, car l'appelant
     * renvoie un message d'erreur. Sans ce rollback explicite, la transaction resterait ouverte et toutes les
     * écritures suivantes de la requête y seraient imbriquées, puis annulées en fin de requête.
     *
     * @param \moodle_transaction $transaction
     * @param \Throwable $e
     */
    private static function rollback_quietly(\moodle_transaction $transaction, \Throwable $e): void {
        if ($transaction->is_disposed()) {
            return;
        }
        try {
            $transaction->rollback($e);
        } catch (\Throwable $rethrown) {
            // Attendu : rollback() relance $e.
            unset($rethrown);
        }
    }

    /**
     * Gestionnaire de catégories du cœur de Moodle.
     *
     * @return \core_question\category_manager
     */
    private static function core_manager(): \core_question\category_manager {
        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');
        return new \core_question\category_manager();
    }

    /**
     * Purge les caches du plugin sans jamais faire échouer l'opération déjà validée.
     */
    private static function purge_plugin_caches(): void {
        try {
            cache_manager::purge_all_caches();
        } catch (\Throwable $e) {
            debugging('category_manager : purge des caches impossible : ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Indique pourquoi une catégorie ne peut pas être supprimée, ou null si elle peut l'être.
     *
     * Règles (toutes obligatoires) :
     * - contexte existant (le cœur ne sait pas supprimer une catégorie orpheline : script admin/cli dédié) ;
     * - jamais la catégorie « top » (parent = 0) ni la seule sous-catégorie de « top » ;
     * - jamais une catégorie « par défaut » (sauf nettoyage des doublons, et jamais celle que Moodle utilise) ;
     * - jamais une catégorie avec description ou idnumber (usage intentionnel) ;
     * - uniquement une catégorie VIDE : 0 question et 0 sous-catégorie ;
     * - jamais une catégorie visée par une question aléatoire (question_set_references).
     *
     * @param \stdClass $category enregistrement question_categories
     * @param bool $bypassdefault autorise une catégorie « par défaut » redondante (nettoyage des doublons)
     * @return string|null
     */
    public static function get_deletion_refusal(\stdClass $category, bool $bypassdefault = false): ?string {
        global $DB, $CFG;
        require_once($CFG->libdir . '/questionlib.php');

        $categoryid = (int)$category->id;
        $context = \context::instance_by_id((int)$category->contextid, IGNORE_MISSING);
        if (!$context) {
            return "❌ IMPOSSIBLE : le contexte de cette catégorie n'existe plus (catégorie orpheline). "
                . "Utilisez le script Moodle admin/cli/fix_orphaned_question_categories.php.";
        }

        if ((int)$category->parent === 0) {
            return "❌ PROTÉGÉE : Cette catégorie est la catégorie racine (« top », parent=0) de son contexte. "
                . "Elle ne doit jamais être supprimée.";
        }

        if (self::is_default_name($category->name)) {
            if (!$bypassdefault) {
                return "❌ PROTÉGÉE : Cette catégorie par défaut est liée à un contexte actif (cours, banque, etc.) "
                    . "et ne doit pas être supprimée.";
            }
            $defaultcat = question_get_default_category((int)$category->contextid, false);
            if (!$defaultcat || empty($defaultcat->id)) {
                return "❌ PROTÉGÉE : Impossible d'identifier la catégorie par défaut utilisée par Moodle pour ce contexte. "
                    . "Par sécurité, la suppression est bloquée.";
            }
            if ((int)$defaultcat->id === $categoryid) {
                return "❌ PROTÉGÉE : Cette catégorie est la catégorie par défaut utilisée par Moodle pour ce contexte.";
            }
        }

        if (!empty($category->info)) {
            return "❌ PROTÉGÉE : Cette catégorie a une description, indiquant un usage intentionnel. "
                . "Supprimez d'abord la description si vous êtes certain de vouloir la supprimer.";
        }

        if (self::has_idnumber($category)) {
            return "❌ PROTÉGÉE : Cette catégorie a un idnumber, indiquant un usage intentionnel. "
                . "Videz d'abord l'idnumber si vous êtes certain de vouloir la supprimer.";
        }

        if (self::core_manager()->is_only_child_of_top_category_in_context($categoryid)) {
            return "❌ PROTÉGÉE : C'est la seule catégorie sous la catégorie racine de ce contexte. "
                . "Moodle exige qu'il en reste au moins une.";
        }

        $questioncount = (int)$DB->count_records('question_bank_entries', ['questioncategoryid' => $categoryid]);
        if ($questioncount > 0) {
            return "❌ IMPOSSIBLE : La catégorie contient $questioncount question(s). "
                . "AUCUNE catégorie contenant des questions ne peut être supprimée.";
        }

        $subcatcount = (int)$DB->count_records('question_categories', ['parent' => $categoryid]);
        if ($subcatcount > 0) {
            return "❌ IMPOSSIBLE : La catégorie contient $subcatcount sous-catégorie(s).";
        }

        if (\local_question_diagnostic\local\usage_checker::category_used_by_random($categoryid, true)) {
            return "❌ PROTÉGÉE : Cette catégorie est utilisée par une question aléatoire d'un test "
                . "(directement ou via « inclure les sous-catégories »). La supprimer casserait ce tirage.";
        }

        return null;
    }

    /**
     * Supprime une catégorie VIDE, via l'API du cœur (\core_question\category_manager).
     *
     * Toutes les règles de get_deletion_refusal() sont vérifiées, puis re-vérifiées dans la transaction juste
     * avant l'appel au cœur (qui, sinon, supprimerait les questions restantes et remonterait les sous-catégories).
     *
     * @param int $categoryid ID de la catégorie
     * @param bool $bypass_default_protection autorise une catégorie « par défaut » REDONDANTE (nettoyage des doublons)
     * @return bool|string true si succès (ou catégorie déjà absente), message d'erreur sinon
     */
    public static function delete_category($categoryid, $bypass_default_protection = false) {
        global $DB;

        $categoryid = (int)$categoryid;
        try {
            // Idempotence : si la catégorie n'existe plus, l'opération est déjà faite.
            $category = $DB->get_record('question_categories', ['id' => $categoryid], '*', IGNORE_MISSING);
            if (!$category) {
                return true;
            }

            $refusal = self::get_deletion_refusal($category, (bool)$bypass_default_protection);
            if ($refusal !== null) {
                return $refusal;
            }

            $manager = self::core_manager();
            $transaction = $DB->start_delegated_transaction();
            try {
                // Re-vérification au plus près de l'écriture.
                if ($DB->record_exists('question_bank_entries', ['questioncategoryid' => $categoryid])
                        || $DB->record_exists('question_categories', ['parent' => $categoryid])) {
                    throw self::refusal("❌ IMPOSSIBLE : la catégorie n'est plus vide.");
                }
                $manager->require_can_delete_category($categoryid);
                $manager->delete_category($categoryid);
                $transaction->allow_commit();
            } catch (\Throwable $e) {
                self::rollback_quietly($transaction, $e);
                throw $e;
            }

            \local_question_diagnostic\local\usage_checker::reset_caches();
            audit_logger::log_category_deletion($categoryid, $category->name, 0);
            return true;

        } catch (\Throwable $e) {
            return "❌ Erreur : " . self::exception_message($e) . " (Catégorie ID: $categoryid)";
        }
    }

    /**
     * Supprime plusieurs catégories vides (chacune revérifiée par delete_category()).
     *
     * @param array $categoryids Tableau d'IDs
     * @param bool $bypass_default_protection cf. delete_category()
     * @return array ['success' => count, 'failed' => count, 'errors' => []]
     */
    public static function delete_categories_bulk($categoryids, $bypass_default_protection = false) {
        $success = 0;
        $errors = [];

        foreach ($categoryids as $id) {
            $result = self::delete_category((int)$id, $bypass_default_protection);
            if ($result === true) {
                $success++;
            } else {
                $errors[] = "Catégorie " . (int)$id . " : $result";
            }
        }

        return ['success' => $success, 'failed' => count($errors), 'errors' => $errors];
    }

    /**
     * Fige une liste de catégories à supprimer : ne garde que celles qui, MAINTENANT, sont supprimables
     * (vides, sans sous-catégorie, non protégées, non utilisées par un tirage aléatoire).
     *
     * Une catégorie qui deviendrait vide pendant le traitement (parent d'une catégorie supprimée) n'est donc
     * jamais ajoutée : pas de suppression en cascade.
     *
     * @param int[] $categoryids
     * @return array{0:int[],1:array<int,string>} [identifiants retenus, refus (id => raison)]
     */
    public static function freeze_deletable_ids(array $categoryids): array {
        global $DB;
        \local_question_diagnostic\local\usage_checker::reset_caches();
        $eligible = [];
        $refused = [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $categoryids), fn($id) => $id > 0)));
        foreach ($ids as $id) {
            $category = $DB->get_record('question_categories', ['id' => $id], '*', IGNORE_MISSING);
            if (!$category) {
                $refused[$id] = "Catégorie introuvable (déjà supprimée ?).";
                continue;
            }
            $refusal = self::get_deletion_refusal($category);
            if ($refusal !== null) {
                $refused[$id] = $refusal;
            } else {
                $eligible[] = $id;
            }
        }
        return [$eligible, $refused];
    }

    /**
     * Supprime une liste FIGÉE de catégories (calculée au lancement). Chaque catégorie est re-vérifiée
     * au moment de sa suppression ; aucune autre catégorie n'est ajoutée en cours de route.
     *
     * @param int[] $categoryids
     * @return \stdClass {deleted: int[], skipped: array<int,string>}
     */
    public static function delete_frozen_category_list(array $categoryids): \stdClass {
        global $DB;
        \local_question_diagnostic\local\usage_checker::reset_caches();
        $result = (object)['deleted' => [], 'skipped' => []];
        $ids = array_values(array_unique(array_filter(array_map('intval', $categoryids), fn($id) => $id > 0)));
        foreach ($ids as $id) {
            if (!$DB->record_exists('question_categories', ['id' => $id])) {
                $result->skipped[$id] = "Catégorie introuvable (déjà supprimée ?).";
                continue;
            }
            $res = self::delete_category($id);
            if ($res === true) {
                $result->deleted[] = $id;
            } else {
                $result->skipped[$id] = (string)$res;
            }
        }
        return $result;
    }

    /**
     * Liste des catégories supprimables par le nettoyage global, calculée UNE fois (au lancement).
     *
     * Ne retient que les catégories vides ET sans sous-catégorie à cet instant, non protégées (racine, par défaut,
     * description, idnumber), dont le contexte existe, qui ne sont pas la seule sous-catégorie de « top » et
     * qui ne sont visées par aucune question aléatoire.
     *
     * @return \stdClass {total, candidates: \stdClass[], kept, reasons: array<string,int>}
     */
    public static function get_cleanup_candidates(): \stdClass {
        \local_question_diagnostic\local\usage_checker::reset_caches();
        $all = self::get_all_categories_with_stats();

        $parentof = [];
        $childcount = [];
        foreach ($all as $item) {
            $id = (int)$item->category->id;
            $parent = (int)$item->category->parent;
            $parentof[$id] = $parent;
            if ($parent > 0) {
                $childcount[$parent] = ($childcount[$parent] ?? 0) + 1;
            }
        }

        try {
            $random = \local_question_diagnostic\local\usage_checker::get_random_categories();
            $randomok = true;
        } catch (\Throwable $e) {
            // Lecture impossible : on ne supprime rien (échec fermé).
            $random = [];
            $randomok = false;
        }

        $result = (object)['total' => count($all), 'candidates' => [], 'kept' => 0, 'reasons' => []];
        foreach ($all as $item) {
            $cat = $item->category;
            $stats = $item->stats;
            $id = (int)$cat->id;
            $parent = (int)$cat->parent;

            $reason = null;
            if (empty($stats->context_valid)) {
                $reason = 'orphan';
            } else if (!empty($stats->is_protected)) {
                $reason = 'protected';
            } else if ((int)$stats->total_questions > 0) {
                $reason = 'questions';
            } else if ((int)$stats->subcategories > 0) {
                $reason = 'subcategories';
            } else if ($parent > 0 && ($parentof[$parent] ?? -1) === 0 && ($childcount[$parent] ?? 0) <= 1) {
                $reason = 'onlychild';
            } else if (!$randomok || isset($random[$id])) {
                $reason = 'random';
            }

            if ($reason !== null) {
                $result->kept++;
                $result->reasons[$reason] = ($result->reasons[$reason] ?? 0) + 1;
                continue;
            }
            $result->candidates[] = (object)[
                'id' => $id,
                'name' => $cat->name,
                'contextid' => (int)$cat->contextid,
                'context_name' => $stats->context_name ?? '',
                'parent' => $parent,
            ];
        }
        return $result;
    }

    /**
     * Identifiants de questions (idnumber) de la source déjà présents dans la destination.
     *
     * @param int $sourceid
     * @param int $destid
     * @return string[]
     */
    private static function get_idnumber_collisions(int $sourceid, int $destid): array {
        global $DB;
        $sql = "SELECT DISTINCT s.idnumber
                  FROM {question_bank_entries} s
                  JOIN {question_bank_entries} d ON d.idnumber = s.idnumber AND d.questioncategoryid = :destid
                 WHERE s.questioncategoryid = :sourceid
                   AND s.idnumber IS NOT NULL";
        $values = $DB->get_fieldset_sql($sql, ['sourceid' => $sourceid, 'destid' => $destid]);
        return array_values(array_filter(array_map('strval', $values), fn($v) => $v !== ''));
    }

    /**
     * Indique pourquoi une fusion source → destination est refusée, ou null si elle est possible.
     *
     * @param \stdClass $source
     * @param \stdClass $dest
     * @return string|null
     */
    public static function get_merge_refusal(\stdClass $source, \stdClass $dest): ?string {
        $sourceid = (int)$source->id;
        $destid = (int)$dest->id;

        if ($sourceid === $destid) {
            return "Impossible de fusionner une catégorie avec elle-même.";
        }
        if ((int)$source->contextid !== (int)$dest->contextid) {
            return "Les catégories doivent être dans le même contexte pour être fusionnées.";
        }
        if (!\context::instance_by_id((int)$source->contextid, IGNORE_MISSING)) {
            return "❌ IMPOSSIBLE : le contexte de ces catégories n'existe plus (catégories orphelines).";
        }
        if ((int)$dest->parent === 0) {
            return "❌ La destination est la catégorie racine (« top ») du contexte : choisissez une catégorie visible.";
        }
        if (self::is_ancestor($sourceid, $destid)) {
            return "❌ Impossible de fusionner : la destination est une sous-catégorie de la source "
                . "(cela créerait une boucle et détacherait l'arborescence).";
        }
        $sourcestats = self::get_category_stats($source);
        if (!empty($sourcestats->is_protected)) {
            return "❌ PROTÉGÉE : La catégorie source est protégée et ne peut pas être fusionnée. Raison : "
                . $sourcestats->protection_reason;
        }
        if (self::core_manager()->is_only_child_of_top_category_in_context($sourceid)) {
            return "❌ PROTÉGÉE : La source est la seule catégorie sous la catégorie racine de ce contexte ; "
                . "Moodle ne permet pas de la supprimer.";
        }
        if (\local_question_diagnostic\local\usage_checker::category_used_by_random($sourceid, true)) {
            return "❌ PROTÉGÉE : La catégorie source (ou l'une de ses sous-catégories) est utilisée par une question "
                . "aléatoire d'un test. La fusion modifierait ou casserait ce tirage : elle est refusée.";
        }
        $collisions = self::get_idnumber_collisions($sourceid, $destid);
        if ($collisions) {
            return self::idnumber_collision_message($collisions);
        }
        return null;
    }

    /**
     * Message de refus pour des idnumber en collision.
     *
     * @param string[] $collisions
     * @return string
     */
    private static function idnumber_collision_message(array $collisions): string {
        $shown = array_slice($collisions, 0, 10);
        return "❌ IMPOSSIBLE : " . count($collisions) . " idnumber de questions existent déjà dans la destination ("
            . implode(', ', $shown) . (count($collisions) > 10 ? ', …' : '') . "). "
            . "Renommez-les avant de fusionner.";
    }

    /**
     * Fusionne deux catégories du MÊME contexte : questions et sous-catégories de la source vont dans la
     * destination, puis la source (vide) est supprimée.
     *
     * Tout passe par l'API du cœur (fichiers, idnumber, étiquettes, événements, caches) et dans une seule
     * transaction annulée en cas d'erreur.
     *
     * @param int $sourceid ID de la catégorie source (sera supprimée)
     * @param int $destid ID de la catégorie destination (recevra le contenu)
     * @return bool|string true si succès, message d'erreur sinon
     */
    public static function merge_categories($sourceid, $destid) {
        global $DB;

        $sourceid = (int)$sourceid;
        $destid = (int)$destid;
        if ($sourceid === $destid) {
            return "Impossible de fusionner une catégorie avec elle-même.";
        }

        try {
            \local_question_diagnostic\local\usage_checker::reset_caches();
            $source = $DB->get_record('question_categories', ['id' => $sourceid], '*', IGNORE_MISSING);
            $dest = $DB->get_record('question_categories', ['id' => $destid], '*', IGNORE_MISSING);
            if (!$source || !$dest) {
                return "Catégorie introuvable.";
            }

            $refusal = self::get_merge_refusal($source, $dest);
            if ($refusal !== null) {
                return $refusal;
            }

            $manager = self::core_manager();
            $manager->require_can_delete_category($sourceid);
            $questionids = $manager->get_real_question_ids_in_category($sourceid);
            $subcats = $DB->get_records('question_categories', ['parent' => $sourceid], 'sortorder ASC, id ASC');

            $transaction = $DB->start_delegated_transaction();
            try {
                // 1. Sous-catégories → destination (API du cœur, même contexte).
                foreach ($subcats as $subcat) {
                    if (self::is_ancestor((int)$subcat->id, $destid)) {
                        throw self::refusal("❌ Impossible de déplacer la sous-catégorie {$subcat->id} : cela créerait une boucle.");
                    }
                    $manager->update_category((int)$subcat->id, $destid . ',' . (int)$dest->contextid, (string)$subcat->name,
                        $subcat->info, (string)($subcat->infoformat ?? FORMAT_HTML), $subcat->idnumber);
                    if ((int)$DB->get_field('question_categories', 'parent', ['id' => $subcat->id]) !== $destid) {
                        throw self::refusal("❌ Moodle a refusé de déplacer la sous-catégorie {$subcat->id}.");
                    }
                }

                // 2. idnumber : revérifiés dans la transaction (le cœur les renommerait en silence).
                $collisions = self::get_idnumber_collisions($sourceid, $destid);
                if ($collisions) {
                    throw self::refusal(self::idnumber_collision_message($collisions));
                }

                // 3. Questions → destination (question_move_questions_to_category : fichiers, idnumber, événements).
                if (!empty($questionids)) {
                    $manager->move_questions($sourceid, $destid, $questionids);
                }

                // 4. La source doit être vide avant sa suppression (le cœur supprimerait sinon les questions restantes).
                if ($DB->record_exists('question_bank_entries', ['questioncategoryid' => $sourceid])
                        || $DB->record_exists('question_categories', ['parent' => $sourceid])) {
                    throw self::refusal("❌ La catégorie source n'est pas vide après le déplacement : fusion annulée.");
                }

                // 5. Suppression de la source vide (API du cœur).
                $manager->delete_category($sourceid);

                $transaction->allow_commit();
            } catch (\Throwable $e) {
                self::rollback_quietly($transaction, $e);
                throw $e;
            }

            \local_question_diagnostic\local\usage_checker::reset_caches();
            audit_logger::log_category_merge($sourceid, $destid, $source->name, $dest->name, count($questionids));
            self::purge_plugin_caches();
            return true;

        } catch (\Throwable $e) {
            return "Erreur lors de la fusion : " . self::exception_message($e);
        }
    }

    /**
     * Indique pourquoi un déplacement est refusé, ou null s'il est possible.
     *
     * @param \stdClass $category
     * @param int $newparentid
     * @return string|null
     */
    public static function get_move_refusal(\stdClass $category, int $newparentid): ?string {
        global $DB;

        $categoryid = (int)$category->id;
        if ($newparentid <= 0) {
            return "❌ Déplacement vers la racine (parent = 0) interdit : chaque contexte n'a qu'une seule "
                . "catégorie « top », une seconde serait invisible dans la banque de questions.";
        }
        if ($categoryid === $newparentid) {
            return "Une catégorie ne peut pas être son propre parent.";
        }
        if ((int)$category->parent === 0) {
            return "❌ PROTÉGÉE : La catégorie racine (« top ») d'un contexte ne peut pas être déplacée.";
        }
        $parent = $DB->get_record('question_categories', ['id' => $newparentid], '*', IGNORE_MISSING);
        if (!$parent) {
            return "Catégorie parente introuvable.";
        }
        if ((int)$parent->contextid !== (int)$category->contextid) {
            return "Le parent doit être dans le même contexte.";
        }
        if (!\context::instance_by_id((int)$category->contextid, IGNORE_MISSING)) {
            return "❌ IMPOSSIBLE : le contexte de cette catégorie n'existe plus (catégorie orpheline).";
        }
        if (self::is_ancestor($categoryid, $newparentid)) {
            return "Impossible de déplacer : cela créerait une boucle.";
        }
        if ((int)$category->parent === $newparentid) {
            return "La catégorie est déjà rangée sous ce parent.";
        }
        $stats = self::get_category_stats($category);
        // Un idnumber n'empêche pas un déplacement dans le même contexte.
        if (!empty($stats->is_protected) && $stats->protection_reason !== self::PROTECTION_IDNUMBER) {
            return "❌ PROTÉGÉE : Cette catégorie est protégée et ne peut pas être déplacée. Raison : "
                . $stats->protection_reason;
        }
        if (self::core_manager()->is_only_child_of_top_category_in_context($categoryid)) {
            return "❌ PROTÉGÉE : C'est la seule catégorie sous la catégorie racine de ce contexte ; "
                . "Moodle ne permet pas de la déplacer.";
        }
        if (\local_question_diagnostic\local\usage_checker::category_used_by_random($categoryid, true)) {
            return "❌ PROTÉGÉE : Cette catégorie (ou l'une de ses sous-catégories) fait partie du réservoir d'une question "
                . "aléatoire d'un test. La déplacer modifierait ce tirage : déplacement refusé.";
        }
        return null;
    }

    /**
     * Déplace une catégorie sous un autre parent du MÊME contexte, via l'API du cœur (update_category).
     *
     * Interdit : parent 0 (seconde racine invisible), déplacement d'une racine, changement de contexte,
     * boucle, catégorie protégée ou utilisée par une question aléatoire.
     *
     * @param int $categoryid ID de la catégorie à déplacer
     * @param int $newparentid ID du nouveau parent (> 0, même contexte)
     * @return bool|string true si succès, message d'erreur sinon
     */
    public static function move_category($categoryid, $newparentid) {
        global $DB;

        $categoryid = (int)$categoryid;
        $newparentid = (int)$newparentid;

        try {
            \local_question_diagnostic\local\usage_checker::reset_caches();
            $category = $DB->get_record('question_categories', ['id' => $categoryid], '*', IGNORE_MISSING);
            if (!$category) {
                return "Catégorie introuvable.";
            }
            $refusal = self::get_move_refusal($category, $newparentid);
            if ($refusal !== null) {
                return $refusal;
            }

            $oldparentid = (int)$category->parent;
            self::core_update_parent($category, $newparentid);

            \local_question_diagnostic\local\usage_checker::reset_caches();
            audit_logger::log_category_move($categoryid, $oldparentid, $newparentid, $category->name);
            self::purge_plugin_caches();
            return true;

        } catch (\Throwable $e) {
            return "Erreur lors du déplacement : " . self::exception_message($e);
        }
    }

    /**
     * Change le parent d'une catégorie dans son contexte, par l'API du cœur, dans une transaction.
     *
     * @param \stdClass $category enregistrement question_categories
     * @param int $newparentid nouveau parent (même contexte)
     * @throws \Throwable si le cœur refuse ou ignore le changement (transaction annulée)
     */
    private static function core_update_parent(\stdClass $category, int $newparentid): void {
        global $DB;
        $manager = self::core_manager();
        $transaction = $DB->start_delegated_transaction();
        try {
            $manager->update_category((int)$category->id, $newparentid . ',' . (int)$category->contextid,
                (string)$category->name, $category->info, (string)($category->infoformat ?? FORMAT_HTML),
                $category->idnumber ?? null);
            // update_category() ignore le nouveau parent dans certains cas (seule sous-catégorie de « top ») : vérifier.
            if ((int)$DB->get_field('question_categories', 'parent', ['id' => $category->id]) !== $newparentid) {
                throw self::refusal("❌ Moodle a refusé ce changement de parent.");
            }
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            self::rollback_quietly($transaction, $e);
            throw $e;
        }
    }

    /**
     * Ré-attache une racine en trop (parent=0) sous la racine conservée du même contexte.
     *
     * Objectif : corriger l'anomalie « Plusieurs catégories racine par contexte » (un contexte Moodle 5.x n'a
     * qu'une seule catégorie « top »). Garde-fous :
     * - la catégorie à déplacer et la cible sont toutes deux des racines du même contexte (existant) ;
     * - aucune boucle ;
     * - la racine déplacée (et son arborescence) n'est visée par aucune question aléatoire ;
     * - écriture par l'API du cœur (update_category), dans une transaction.
     *
     * @param int $categoryid ID de la racine à ré-attacher
     * @param int $newparentid ID de la racine à conserver
     * @return bool|string true si succès, message d'erreur sinon
     */
    public static function force_reparent_root_category(int $categoryid, int $newparentid) {
        global $DB;

        if ($categoryid <= 0 || $newparentid <= 0) {
            return 'Paramètres invalides.';
        }
        if ($categoryid === $newparentid) {
            return 'Une catégorie ne peut pas être son propre parent.';
        }

        try {
            \local_question_diagnostic\local\usage_checker::reset_caches();
            $category = $DB->get_record('question_categories', ['id' => $categoryid], '*', IGNORE_MISSING);
            $newparent = $DB->get_record('question_categories', ['id' => $newparentid], '*', IGNORE_MISSING);
            if (!$category || !$newparent) {
                return 'Catégorie introuvable.';
            }
            if ((int)$category->parent !== 0) {
                return 'La catégorie à ré-attacher n\'est pas une racine (parent != 0).';
            }
            if ((int)$newparent->parent !== 0) {
                return 'Le parent cible n\'est pas une racine (parent != 0).';
            }
            if ((int)$category->contextid !== (int)$newparent->contextid) {
                return 'Le parent doit être dans le même contexte.';
            }
            if (!\context::instance_by_id((int)$category->contextid, IGNORE_MISSING)) {
                return 'Contexte inexistant : correction impossible par l\'API Moodle.';
            }
            if (self::is_ancestor($categoryid, $newparentid)) {
                return 'Impossible de déplacer : cela créerait une boucle.';
            }
            if (\local_question_diagnostic\local\usage_checker::category_used_by_random($categoryid, true)) {
                return 'Cette racine (ou l\'une de ses sous-catégories) est utilisée par une question aléatoire : '
                    . 'correction refusée, à faire à la main.';
            }

            self::core_update_parent($category, $newparentid);
            \local_question_diagnostic\local\usage_checker::reset_caches();
            audit_logger::log_category_move($categoryid, 0, $newparentid, $category->name);
            self::purge_plugin_caches();
            return true;
        } catch (\Throwable $e) {
            return 'Erreur lors de la correction : ' . self::exception_message($e);
        }
    }

    /**
     * Ré-attache une catégorie dont le parent est dans un AUTRE contexte sous la racine de son propre contexte
     * (même correction que \core_question\category_manager::fix_restored_category_parents(), une catégorie à la fois
     * et par l'API update_category).
     *
     * @param int $categoryid catégorie à corriger
     * @param int $rootid racine (parent=0) du contexte de la catégorie
     * @return bool|string true si succès, message d'erreur sinon
     */
    public static function reattach_to_context_root(int $categoryid, int $rootid) {
        global $DB;

        try {
            \local_question_diagnostic\local\usage_checker::reset_caches();
            $category = $DB->get_record('question_categories', ['id' => $categoryid], '*', IGNORE_MISSING);
            $root = $DB->get_record('question_categories', ['id' => $rootid], '*', IGNORE_MISSING);
            if (!$category || !$root) {
                return 'Catégorie introuvable.';
            }
            if ((int)$root->parent !== 0 || (int)$root->contextid !== (int)$category->contextid) {
                return 'La cible n\'est pas la racine du contexte de la catégorie.';
            }
            if ((int)$DB->count_records('question_categories', ['contextid' => $category->contextid, 'parent' => 0]) !== 1) {
                return 'Le contexte n\'a pas exactement une racine : corrigez d\'abord les racines multiples.';
            }
            $currentparent = $DB->get_record('question_categories', ['id' => $category->parent], 'id, contextid',
                IGNORE_MISSING);
            if (!$currentparent || (int)$currentparent->contextid === (int)$category->contextid) {
                return 'Plus d\'incohérence de contexte pour cette catégorie : rien à faire.';
            }
            if (!\context::instance_by_id((int)$category->contextid, IGNORE_MISSING)) {
                return 'Contexte inexistant : correction impossible par l\'API Moodle.';
            }
            if (\local_question_diagnostic\local\usage_checker::category_used_by_random($categoryid, true)) {
                return 'Cette catégorie (ou l\'une de ses sous-catégories) est utilisée par une question aléatoire : '
                    . 'correction refusée, à faire à la main.';
            }

            $oldparentid = (int)$category->parent;
            self::core_update_parent($category, $rootid);
            \local_question_diagnostic\local\usage_checker::reset_caches();
            audit_logger::log_category_move($categoryid, $oldparentid, $rootid, $category->name);
            self::purge_plugin_caches();
            return true;
        } catch (\Throwable $e) {
            return 'Erreur lors de la correction : ' . self::exception_message($e);
        }
    }

    /**
     * Vérifie si une catégorie est ancêtre d'une autre (ou la même).
     *
     * @param int $ancestorid ID de l'ancêtre potentiel
     * @param int $childid ID de l'enfant
     * @return bool true aussi si une boucle est rencontrée (prudence)
     */
    private static function is_ancestor($ancestorid, $childid) {
        global $DB;

        $ancestorid = (int)$ancestorid;
        $current = (int)$childid;
        $visited = [];

        while ($current > 0) {
            if ($current === $ancestorid) {
                return true;
            }
            if (isset($visited[$current])) {
                // Boucle existante : on refuse par prudence.
                return true;
            }
            $visited[$current] = true;
            $parent = $DB->get_field('question_categories', 'parent', ['id' => $current]);
            if ($parent === false) {
                break;
            }
            $current = (int)$parent;
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
            
            $csv .= implode(',', array_map('local_question_diagnostic_csv_cell', [
                (int)$cat->id,
                $cat->name,
                $stats->context_name,
                (int)$cat->parent,
                (int)$stats->visible_questions,
                (int)$stats->total_questions,
                (int)$stats->subcategories,
                $statusstr,
            ])) . "\n";
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

        } catch (\Exception $e) {
            debugging('Error loading all site categories: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
    }

    /**
     * 🆕 Diagnostic : Vérifie la cohérence des catégories de questions (lecture seule).
     *
     * Objectif : détecter des incohérences structurelles susceptibles de casser la banque de questions,
     * en s'alignant sur les bonnes pratiques Moodle (contexte valide, hiérarchie saine, idnumber unique, etc.).
     *
     * Contrôles effectués (si structures disponibles en base) :
     * - Contexte manquant (contextid inexistant)
     * - Parent manquant (parent pointe vers une catégorie inexistante)
     * - Parent dans un autre contexte (incohérence Moodle)
     * - Boucles dans la hiérarchie (cycles)
     * - Zéro / multiples racines (parent=0) par contexte (anomalie structurelle)
     * - Noms vides
     * - idnumber dupliqués dans un même contexte (si colonne idnumber existe)
     * - question_bank_entries orphelines (questioncategoryid inexistant)
     *
     * @param int $samplelimit Nombre maximum d'éléments listés par check
     * @return \stdClass Rapport structuré
     */
    public static function get_categories_integrity_report(int $samplelimit = 50): \stdClass {
        global $DB;

        $report = (object)[
            'generatedat' => time(),
            'summary' => (object)[
                'categories' => 0,
                'contexts' => 0,
                'errors' => 0,
                'warnings' => 0,
            ],
            'checks' => [],
        ];

        // Charger les catégories (en vérifiant la structure réelle Moodle).
        $columns = $DB->get_columns('question_categories');
        $fields = ['id', 'name', 'contextid', 'parent'];
        if (isset($columns['idnumber'])) {
            $fields[] = 'idnumber';
        }
        if (isset($columns['sortorder'])) {
            $fields[] = 'sortorder';
        }

        $categories = $DB->get_records('question_categories', null, '', implode(',', $fields));
        $report->summary->categories = count($categories);

        if (empty($categories)) {
            return $report;
        }

        // Charger les contextes correspondants en batch.
        $contextids = [];
        foreach ($categories as $cat) {
            $contextids[(int)$cat->contextid] = true;
        }
        $contextids = array_keys($contextids);
        $report->summary->contexts = count($contextids);

        $contexts = [];
        if (!empty($contextids)) {
            list($insql, $params) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED);
            $contexts = $DB->get_records_sql(
                "SELECT id, contextlevel, instanceid
                   FROM {context}
                  WHERE id $insql",
                $params
            );
        }

        $addcheck = function(string $key, string $severity, string $title, string $description, array $items) use (&$report, $samplelimit) {
            $count = count($items);
            $sample = $items;
            if ($samplelimit > 0 && $count > $samplelimit) {
                $sample = array_slice($items, 0, $samplelimit);
            }
            $report->checks[$key] = (object)[
                'severity' => $severity, // error|warning|info
                'title' => $title,
                'description' => $description,
                'count' => $count,
                'sample' => $sample,
            ];
            if ($severity === 'error') {
                $report->summary->errors += $count;
            } else if ($severity === 'warning') {
                $report->summary->warnings += $count;
            }
        };

        // 1) Contexte manquant.
        $missingcontext = [];
        foreach ($categories as $cat) {
            if (!isset($contexts[(int)$cat->contextid])) {
                $missingcontext[] = (object)[
                    'categoryid' => (int)$cat->id,
                    'name' => $cat->name,
                    'contextid' => (int)$cat->contextid,
                    'parent' => (int)$cat->parent,
                ];
            }
        }
        $addcheck(
            'missing_context',
            'error',
            'Contexte manquant',
            'Catégories dont le contextid ne correspond à aucun contexte Moodle (risque de références orphelines).',
            $missingcontext
        );

        // 2) Parent manquant.
        $missingparent = [];
        foreach ($categories as $cat) {
            $parentid = (int)$cat->parent;
            if ($parentid > 0 && !isset($categories[$parentid])) {
                $missingparent[] = (object)[
                    'categoryid' => (int)$cat->id,
                    'name' => $cat->name,
                    'contextid' => (int)$cat->contextid,
                    'parent' => $parentid,
                ];
            }
        }
        $addcheck(
            'missing_parent',
            'error',
            'Parent manquant',
            'Catégories dont le parent pointe vers une catégorie inexistante (hiérarchie cassée).',
            $missingparent
        );

        // 3) Parent dans un autre contexte.
        $parentcontextmismatch = [];
        foreach ($categories as $cat) {
            $parentid = (int)$cat->parent;
            if ($parentid > 0 && isset($categories[$parentid])) {
                $parent = $categories[$parentid];
                if ((int)$parent->contextid !== (int)$cat->contextid) {
                    $parentcontextmismatch[] = (object)[
                        'categoryid' => (int)$cat->id,
                        'name' => $cat->name,
                        'contextid' => (int)$cat->contextid,
                        'parent' => $parentid,
                        'parentcontextid' => (int)$parent->contextid,
                    ];
                }
            }
        }
        $addcheck(
            'parent_context_mismatch',
            'error',
            'Parent dans un autre contexte',
            'Une catégorie doit avoir un parent dans le même contexte (bonne pratique Moodle).',
            $parentcontextmismatch
        );

        // 4) Noms vides.
        $emptyname = [];
        foreach ($categories as $cat) {
            if (trim((string)$cat->name) === '') {
                $emptyname[] = (object)[
                    'categoryid' => (int)$cat->id,
                    'contextid' => (int)$cat->contextid,
                    'parent' => (int)$cat->parent,
                ];
            }
        }
        $addcheck(
            'empty_name',
            'warning',
            'Nom vide',
            'Noms de catégories vides ou composés uniquement d’espaces (à corriger).',
            $emptyname
        );

        // 5) Boucles dans la hiérarchie.
        $cycles = [];
        $cycleids = [];
        $parentmap = [];
        foreach ($categories as $cat) {
            $parentmap[(int)$cat->id] = (int)$cat->parent;
        }
        foreach ($categories as $cat) {
            $startid = (int)$cat->id;
            // Parcours avec pile pour détecter un cycle.
            $stack = [$startid];
            $index = [$startid => 0];
            $current = $startid;
            while (true) {
                $next = $parentmap[$current] ?? 0;
                if ($next <= 0) {
                    break;
                }
                if (!isset($categories[$next])) {
                    break; // parent manquant déjà détecté.
                }
                if (isset($index[$next])) {
                    $loop = array_slice($stack, $index[$next]);
                    foreach ($loop as $cid) {
                        $cycleids[$cid] = true;
                    }
                    // Enregistrer un exemple de cycle (une seule fois par start).
                    $cycles[] = (object)[
                        'categoryid' => $startid,
                        'cycle' => $loop,
                    ];
                    break;
                }
                $stack[] = $next;
                $index[$next] = count($stack) - 1;
                $current = $next;
                // Sécurité anti-boucle infinie si données énormes.
                if (count($stack) > 2000) {
                    break;
                }
            }
        }
        // Réduire les exemples : 1 entrée par catégorie impliquée.
        $cycleitems = [];
        foreach (array_keys($cycleids) as $cid) {
            $c = $categories[$cid];
            $cycleitems[] = (object)[
                'categoryid' => (int)$c->id,
                'name' => $c->name,
                'contextid' => (int)$c->contextid,
                'parent' => (int)$c->parent,
            ];
        }
        $addcheck(
            'hierarchy_cycles',
            'error',
            'Boucles dans la hiérarchie',
            'Cycles détectés dans les relations parent/enfant (structure invalide).',
            $cycleitems
        );

        // 6) Racines (parent=0) par contexte.
        $rootsbycontext = [];
        foreach ($categories as $cat) {
            if ((int)$cat->parent === 0) {
                $ctxid = (int)$cat->contextid;
                if (!isset($rootsbycontext[$ctxid])) {
                    $rootsbycontext[$ctxid] = [];
                }
                $rootsbycontext[$ctxid][] = (int)$cat->id;
            }
        }
        $missingroot = [];
        $multipleroot = [];
        foreach ($contextids as $ctxid) {
            $rootids = $rootsbycontext[$ctxid] ?? [];
            if (empty($rootids)) {
                $missingroot[] = (object)[
                    'contextid' => (int)$ctxid,
                    'contextlevel' => isset($contexts[$ctxid]) ? (int)$contexts[$ctxid]->contextlevel : null,
                ];
            } else if (count($rootids) > 1) {
                $multipleroot[] = (object)[
                    'contextid' => (int)$ctxid,
                    'rootids' => $rootids,
                    'count' => count($rootids),
                ];
            }
        }
        $addcheck(
            'missing_root_per_context',
            'error',
            'Aucune catégorie racine par contexte',
            'Chaque contexte devrait avoir une catégorie racine (parent=0) pour une hiérarchie stable.',
            $missingroot
        );
        $addcheck(
            'multiple_roots_per_context',
            'warning',
            'Plusieurs catégories racine par contexte',
            'Plusieurs catégories parent=0 dans un même contexte : généralement non souhaité dans Moodle.',
            $multipleroot
        );

        // 7) idnumber dupliqués (si colonne présente).
        if (isset($columns['idnumber'])) {
            $idgroups = [];
            foreach ($categories as $cat) {
                $idnumber = trim((string)($cat->idnumber ?? ''));
                if ($idnumber === '') {
                    continue;
                }
                $ctxid = (int)$cat->contextid;
                if (!isset($idgroups[$ctxid])) {
                    $idgroups[$ctxid] = [];
                }
                if (!isset($idgroups[$ctxid][$idnumber])) {
                    $idgroups[$ctxid][$idnumber] = [];
                }
                $idgroups[$ctxid][$idnumber][] = (int)$cat->id;
            }
            $duplicates = [];
            foreach ($idgroups as $ctxid => $byid) {
                foreach ($byid as $idnumber => $ids) {
                    if (count($ids) > 1) {
                        $duplicates[] = (object)[
                            'contextid' => (int)$ctxid,
                            'idnumber' => $idnumber,
                            'categoryids' => $ids,
                            'count' => count($ids),
                        ];
                    }
                }
            }
            $addcheck(
                'duplicate_idnumber',
                'error',
                'idnumber dupliqués',
                'Le champ idnumber devrait être unique dans un même contexte (import/export, références).',
                $duplicates
            );
        } else {
            $addcheck(
                'duplicate_idnumber',
                'info',
                'idnumber dupliqués',
                'Colonne idnumber non présente sur cette installation Moodle.',
                []
            );
        }

        // 8) question_bank_entries orphelines (Moodle 4.x).
        $orphanqbe = [];
        try {
            $qbecols = $DB->get_columns('question_bank_entries');
            if (isset($qbecols['questioncategoryid'])) {
                $sqlcount = "SELECT COUNT(1)
                               FROM {question_bank_entries} qbe
                          LEFT JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                              WHERE qbe.questioncategoryid IS NOT NULL
                                AND qc.id IS NULL";
                $count = (int)$DB->count_records_sql($sqlcount);

                if ($count > 0) {
                    $sqlsample = "SELECT qbe.id, qbe.questioncategoryid
                                    FROM {question_bank_entries} qbe
                               LEFT JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                                   WHERE qbe.questioncategoryid IS NOT NULL
                                     AND qc.id IS NULL
                                ORDER BY qbe.id ASC";
                    $rows = $DB->get_records_sql($sqlsample, [], 0, $samplelimit > 0 ? $samplelimit : 50);
                    foreach ($rows as $row) {
                        $orphanqbe[] = (object)[
                            'questionbankentryid' => (int)$row->id,
                            'questioncategoryid' => (int)$row->questioncategoryid,
                        ];
                    }
                }

                // Injecter le count réel même si sample limité.
                $report->checks['orphan_question_bank_entries'] = (object)[
                    'severity' => $count > 0 ? 'error' : 'info',
                    'title' => 'Entrées de banque orphelines',
                    'description' => 'question_bank_entries référencant une catégorie inexistante (incohérence).',
                    'count' => $count,
                    'sample' => $orphanqbe,
                ];
                if ($count > 0) {
                    $report->summary->errors += $count;
                }
            } else {
                $addcheck(
                    'orphan_question_bank_entries',
                    'info',
                    'Entrées de banque orphelines',
                    'Colonne questioncategoryid absente sur question_bank_entries.',
                    []
                );
            }
        } catch (\Exception $e) {
            $addcheck(
                'orphan_question_bank_entries',
                'info',
                'Entrées de banque orphelines',
                'Table question_bank_entries non disponible sur cette installation.',
                []
            );
        }

        return $report;
    }
}

