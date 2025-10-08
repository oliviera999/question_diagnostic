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
            
            // Étape 2 : Compter les questions par catégorie (1 requête)
            $sql_questions = "SELECT qbe.questioncategoryid,
                                     COUNT(DISTINCT q.id) as total_questions,
                                     SUM(CASE WHEN q.hidden = 0 THEN 1 ELSE 0 END) as visible_questions
                              FROM {question_bank_entries} qbe
                              INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                              INNER JOIN {question} q ON q.id = qv.questionid
                              GROUP BY qbe.questioncategoryid";
            $questions_counts = $DB->get_records_sql($sql_questions);
            
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
            
            // Étape 5 : Construire le résultat
            $result = [];
            foreach ($categories as $cat) {
                // Stats des questions
                $question_data = isset($questions_counts[$cat->id]) ? $questions_counts[$cat->id] : null;
                $total_questions = $question_data ? (int)$question_data->total_questions : 0;
                $visible_questions = $question_data ? (int)$question_data->visible_questions : 0;
                
                // Stats des sous-catégories
                $subcat_data = isset($subcat_counts[$cat->id]) ? $subcat_counts[$cat->id] : null;
                $subcategories = $subcat_data ? (int)$subcat_data->subcat_count : 0;
                
                // Validité du contexte
                $context_valid = !in_array($cat->id, $invalid_context_ids);
                
                // Vérifier si la catégorie est protégée
                $is_protected = false;
                $protection_reason = '';
                
                // Protection 1 : "Default for..."
                if (stripos($cat->name, 'Default for') !== false || stripos($cat->name, 'Par défaut pour') !== false) {
                    $is_protected = true;
                    $protection_reason = 'Catégorie par défaut Moodle';
                }
                // Protection 2 : Catégorie avec description
                else if (!empty($cat->info)) {
                    $is_protected = true;
                    $protection_reason = 'A une description';
                }
                // Protection 3 : Racine de cours avec enfants
                else if ($cat->parent == 0 && $subcategories > 0 && $context_valid) {
                    try {
                        $context = \context::instance_by_id($cat->contextid, IGNORE_MISSING);
                        if ($context && $context->contextlevel == CONTEXT_COURSE) {
                            $is_protected = true;
                            $protection_reason = 'Racine de cours';
                        }
                    } catch (\Exception $e) {
                        // Ignorer
                    }
                }
                
                // Construire les stats
                $stats = (object)[
                    'total_questions' => $total_questions,
                    'visible_questions' => $visible_questions,
                    'subcategories' => $subcategories,
                    'context_valid' => $context_valid,
                    'is_empty' => ($total_questions == 0 && $subcategories == 0),
                    'is_orphan' => !$context_valid,
                    'is_protected' => $is_protected,
                    'protection_reason' => $protection_reason,
                ];
                
                // Nom du contexte
                try {
                    if ($context_valid) {
                        $context = \context::instance_by_id($cat->contextid, IGNORE_MISSING);
                        $stats->context_name = $context ? \context_helper::get_level_name($context->contextlevel) : 'Inconnu';
                    } else {
                        $stats->context_name = 'Contexte supprimé (ID: ' . $cat->contextid . ')';
                    }
                } catch (\Exception $e) {
                    $stats->context_name = 'Erreur';
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
            // Nombre de questions visibles - Compatible Moodle 4.x avec question_bank_entries
            // IMPORTANT : Vérifier que la catégorie existe dans question_categories pour éviter les entries orphelines
            $sql = "SELECT COUNT(DISTINCT q.id) 
                    FROM {question} q
                    INNER JOIN {question_versions} qv ON qv.questionid = q.id
                    INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                    INNER JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                    WHERE qbe.questioncategoryid = :categoryid AND q.hidden = 0";
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
            
            // Contexte
            try {
                // Vérifier d'abord si le contexte existe dans la table context
                $context_exists = $DB->record_exists('context', ['id' => $category->contextid]);
                
                if ($context_exists) {
                    $context = \context::instance_by_id($category->contextid, IGNORE_MISSING);
                    $stats->context_name = $context ? \context_helper::get_level_name($context->contextlevel) : 'Inconnu';
                    $stats->context_valid = true;
                } else {
                    $stats->context_name = 'Contexte supprimé (ID: ' . $category->contextid . ')';
                    $stats->context_valid = false;
                }
            } catch (\Exception $e) {
                $stats->context_name = 'Erreur';
                $stats->context_valid = false;
            }
            
            // Statut
            $stats->is_empty = ($stats->total_questions == 0 && $stats->subcategories == 0);
            // Une catégorie est orpheline si son contexte n'existe PAS dans la table context
            $stats->is_orphan = !$stats->context_valid;
            
            // Vérifier si la catégorie est protégée
            $stats->is_protected = false;
            $stats->protection_reason = '';
            
            // Protection 1 : "Default for..."
            if (stripos($category->name, 'Default for') !== false || stripos($category->name, 'Par défaut pour') !== false) {
                $stats->is_protected = true;
                $stats->protection_reason = 'Catégorie par défaut Moodle';
            }
            // Protection 2 : Catégorie avec description
            else if (!empty($category->info)) {
                $stats->is_protected = true;
                $stats->protection_reason = 'A une description';
            }
            // Protection 3 : Racine de cours avec enfants
            else if ($category->parent == 0 && $stats->subcategories > 0 && $stats->context_valid) {
                try {
                    $context_obj = \context::instance_by_id($category->contextid, IGNORE_MISSING);
                    if ($context_obj && $context_obj->contextlevel == CONTEXT_COURSE) {
                        $stats->is_protected = true;
                        $stats->protection_reason = 'Racine de cours';
                    }
                } catch (\Exception $e) {
                    // Ignorer
                }
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
    
    /**
     * ANCIENNE VERSION (gardée pour compatibilité) - À NE PLUS UTILISER
     * Trouve les catégories en doublon en chargeant tout en mémoire
     * 
     * @return array Tableau des doublons [cat1, cat2]
     * @deprecated Utiliser find_duplicates($limit) à la place
     */
    private static function find_duplicates_old() {
        global $DB;

        $categories = $DB->get_records('question_categories');
        $map = [];
        $duplicates = [];

        foreach ($categories as $cat) {
            $key = strtolower(trim($cat->name)) . '_' . $cat->contextid . '_' . $cat->parent;
            
            if (isset($map[$key])) {
                $duplicates[] = [$map[$key], $cat];
            } else {
                $map[$key] = $cat;
            }
        }

        return $duplicates;
    }

    /**
     * Supprime une catégorie vide
     *
     * @param int $categoryid ID de la catégorie
     * @return bool|string true si succès, message d'erreur sinon
     */
    public static function delete_category($categoryid) {
        global $DB;

        try {
            $category = $DB->get_record('question_categories', ['id' => $categoryid], '*', MUST_EXIST);
            
            // 🛡️ PROTECTION 1 : Catégories "Default for..." (créées automatiquement par Moodle)
            if (stripos($category->name, 'Default for') !== false || stripos($category->name, 'Par défaut pour') !== false) {
                return "❌ PROTÉGÉE : Cette catégorie est créée automatiquement par Moodle et ne doit jamais être supprimée.";
            }
            
            // 🛡️ PROTECTION 2 : Catégories avec description (usage intentionnel)
            if (!empty($category->info)) {
                return "❌ PROTÉGÉE : Cette catégorie a une description, indiquant un usage intentionnel. Supprimez d'abord la description si vous êtes certain de vouloir la supprimer.";
            }
            
            // 🛡️ PROTECTION 3 : Catégories racine (parent=0) dans un contexte de cours
            if ($category->parent == 0) {
                try {
                    $context = \context::instance_by_id($category->contextid, IGNORE_MISSING);
                    if ($context && $context->contextlevel == CONTEXT_COURSE) {
                        return "❌ PROTÉGÉE : Cette catégorie est à la racine d'un cours (parent=0). Moodle crée automatiquement une catégorie racine pour chaque cours. Ne pas supprimer.";
                    }
                } catch (\Exception $e) {
                    // Si erreur de contexte, continuer (peut-être une catégorie orpheline)
                }
            }
            
            // Vérifier que la catégorie est vide
            // Compatible Moodle 4.x avec question_bank_entries
            $sql = "SELECT COUNT(DISTINCT q.id) 
                    FROM {question} q
                    INNER JOIN {question_versions} qv ON qv.questionid = q.id
                    INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                    INNER JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                    WHERE qbe.questioncategoryid = :categoryid";
            $questioncount = (int)$DB->count_records_sql($sql, ['categoryid' => $categoryid]);
            
            $subcatcount = $DB->count_records('question_categories', ['parent' => $categoryid]);
            
            if ($questioncount > 0) {
                return "Impossible de supprimer : la catégorie contient $questioncount question(s).";
            }
            
            if ($subcatcount > 0) {
                return "Impossible de supprimer : la catégorie contient $subcatcount sous-catégorie(s).";
            }
            
            // Supprimer la catégorie
            $DB->delete_records('question_categories', ['id' => $categoryid]);
            
            return true;
            
        } catch (\Exception $e) {
            return "Erreur lors de la suppression : " . $e->getMessage();
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
    public static function merge_categories($sourceid, $destid) {
        global $DB;

        try {
            $source = $DB->get_record('question_categories', ['id' => $sourceid], '*', MUST_EXIST);
            $dest = $DB->get_record('question_categories', ['id' => $destid], '*', MUST_EXIST);
            
            // Déplacer toutes les questions de source vers dest - Compatible Moodle 4.x
            $sql = "UPDATE {question_bank_entries} SET questioncategoryid = :destid WHERE questioncategoryid = :sourceid";
            $DB->execute($sql, ['destid' => $destid, 'sourceid' => $sourceid]);
            
            // Déplacer les sous-catégories
            $subcats = $DB->get_records('question_categories', ['parent' => $sourceid]);
            foreach ($subcats as $subcat) {
                $subcat->parent = $destid;
                $DB->update_record('question_categories', $subcat);
            }
            
            // Supprimer la catégorie source
            $DB->delete_records('question_categories', ['id' => $sourceid]);
            
            return true;
            
        } catch (\Exception $e) {
            return "Erreur lors de la fusion : " . $e->getMessage();
        }
    }

    /**
     * Déplace une catégorie vers un nouveau parent
     *
     * @param int $categoryid ID de la catégorie à déplacer
     * @param int $newparentid ID du nouveau parent (0 pour racine)
     * @return bool|string true si succès, message d'erreur sinon
     */
    public static function move_category($categoryid, $newparentid) {
        global $DB;

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
            
            $category->parent = $newparentid;
            $DB->update_record('question_categories', $category);
            
            return true;
            
        } catch (\Exception $e) {
            return "Erreur lors du déplacement : " . $e->getMessage();
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
        
        // Compter les catégories vides (sans questions ET sans sous-catégories)
        // 🛡️ AVEC PROTECTIONS : Exclure les catégories qui ne doivent jamais être supprimées
        // Sous-requête pour catégories avec questions
        $sql_empty = "SELECT COUNT(qc.id)
                      FROM {question_categories} qc
                      WHERE qc.id NOT IN (
                          SELECT DISTINCT qbe.questioncategoryid
                          FROM {question_bank_entries} qbe
                          INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                      )
                      AND qc.id NOT IN (
                          SELECT DISTINCT parent
                          FROM {question_categories}
                          WHERE parent IS NOT NULL AND parent > 0
                      )
                      AND qc.parent != 0
                      AND (qc.info IS NULL OR qc.info = '')
                      AND " . $DB->sql_like('qc.name', ':pattern', true, true, true);
        $stats->empty_categories = (int)$DB->count_records_sql($sql_empty, ['pattern' => '%Default for%']);
        
        // Compter les catégories protégées (pour information)
        $stats->protected_default = (int)$DB->count_records_sql("
            SELECT COUNT(*)
            FROM {question_categories}
            WHERE " . $DB->sql_like('name', ':pattern', false), 
            ['pattern' => '%Default for%']);
        
        
        $stats->protected_with_info = (int)$DB->count_records_sql("
            SELECT COUNT(*)
            FROM {question_categories}
            WHERE info IS NOT NULL AND info != ''
        ");
        
        // Compter les doublons - version compatible toutes BDD
        // Compter les groupes de catégories qui ont des noms identiques (doublons)
        try {
            $dbfamily = $DB->get_dbfamily();
            if ($dbfamily === 'mysql' || $dbfamily === 'mariadb') {
                // MySQL/MariaDB : sous-requête dans FROM
                $sql_duplicates = "SELECT COUNT(*) as dup_count
                                   FROM (
                                       SELECT COUNT(*) as cnt
                                       FROM {question_categories}
                                       GROUP BY LOWER(TRIM(name)), contextid, parent
                                       HAVING COUNT(*) > 1
                                   ) AS dups";
            } else {
                // PostgreSQL et autres : syntaxe standard
                $sql_duplicates = "SELECT COUNT(*) as dup_count
                                   FROM (
                                       SELECT COUNT(*) as cnt
                                       FROM {question_categories}
                                       GROUP BY LOWER(TRIM(name)), contextid, parent
                                       HAVING COUNT(*) > 1
                                   ) dups";
            }
            $dup_result = $DB->get_record_sql($sql_duplicates);
            $stats->duplicates = $dup_result ? (int)$dup_result->dup_count : 0;
        } catch (\Exception $e) {
            // Si erreur, utiliser l'ancienne méthode (plus lente mais fiable)
            $stats->duplicates = count(self::find_duplicates());
        }
        
        return $stats;
    }

    /**
     * Génère l'URL pour accéder à une catégorie dans la banque de questions
     *
     * @param object $category Objet catégorie
     * @return \moodle_url URL vers la banque de questions
     */
    public static function get_question_bank_url($category) {
        try {
            // Déterminer le courseid à partir du contexte
            $context = \context::instance_by_id($category->contextid, IGNORE_MISSING);
            
            if (!$context) {
                // Si le contexte n'existe pas, retourner null
                return null;
            }
            
            $courseid = 0; // Par défaut, système
            
            // Si c'est un contexte de cours, récupérer l'ID du cours
            if ($context->contextlevel == CONTEXT_COURSE) {
                $courseid = $context->instanceid;
            } else if ($context->contextlevel == CONTEXT_MODULE) {
                // Si c'est un module, remonter au cours parent
                $coursecontext = $context->get_course_context(false);
                if ($coursecontext) {
                    $courseid = $coursecontext->instanceid;
                }
            }
            
            // Construire l'URL : /question/edit.php?courseid=X&cat=categoryid,contextid
            $url = new \moodle_url('/question/edit.php', [
                'courseid' => $courseid,
                'cat' => $category->id . ',' . $category->contextid
            ]);
            
            return $url;
            
        } catch (\Exception $e) {
            return null;
        }
    }
}

