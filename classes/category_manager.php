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

        // VERSION OPTIMISÉE : Une seule requête SQL avec agrégations
        // Au lieu de 5836 requêtes individuelles !
        $sql = "SELECT qc.id,
                       qc.name,
                       qc.contextid,
                       qc.parent,
                       qc.sortorder,
                       qc.info,
                       qc.infoformat,
                       qc.stamp,
                       qc.idnumber,
                       COUNT(DISTINCT q.id) as total_questions,
                       COUNT(DISTINCT CASE WHEN q.hidden = 0 THEN q.id END) as visible_questions,
                       COUNT(DISTINCT subcat.id) as subcategories,
                       CASE WHEN ctx.id IS NULL THEN 0 ELSE 1 END as context_valid
                FROM {question_categories} qc
                LEFT JOIN {question_bank_entries} qbe ON qbe.questioncategoryid = qc.id
                LEFT JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                LEFT JOIN {question} q ON q.id = qv.questionid
                LEFT JOIN {question_categories} subcat ON subcat.parent = qc.id
                LEFT JOIN {context} ctx ON ctx.id = qc.contextid
                GROUP BY qc.id, qc.name, qc.contextid, qc.parent, qc.sortorder, 
                         qc.info, qc.infoformat, qc.stamp, qc.idnumber, ctx.id
                ORDER BY qc.contextid, qc.parent, qc.name ASC";
        
        $categories_data = $DB->get_records_sql($sql);
        
        $result = [];
        foreach ($categories_data as $data) {
            // Reconstruire l'objet catégorie
            $category = (object)[
                'id' => $data->id,
                'name' => $data->name,
                'contextid' => $data->contextid,
                'parent' => $data->parent,
                'sortorder' => $data->sortorder,
                'info' => $data->info,
                'infoformat' => $data->infoformat,
                'stamp' => $data->stamp,
                'idnumber' => $data->idnumber,
            ];
            
            // Construire les stats directement depuis les agrégations SQL
            $stats = (object)[
                'total_questions' => (int)$data->total_questions,
                'visible_questions' => (int)$data->visible_questions,
                'subcategories' => (int)$data->subcategories,
                'context_valid' => (bool)$data->context_valid,
                'is_empty' => ($data->total_questions == 0 && $data->subcategories == 0),
                'is_orphan' => !$data->context_valid,
            ];
            
            // Nom du contexte (récupéré à la demande, léger)
            try {
                if ($stats->context_valid) {
                    $context = \context::instance_by_id($category->contextid, IGNORE_MISSING);
                    $stats->context_name = $context ? \context_helper::get_level_name($context->contextlevel) : 'Inconnu';
                } else {
                    $stats->context_name = 'Contexte supprimé (ID: ' . $category->contextid . ')';
                }
            } catch (\Exception $e) {
                $stats->context_name = 'Erreur';
            }
            
            $result[] = (object)[
                'category' => $category,
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
                      )";
        $stats->empty_categories = (int)$DB->count_records_sql($sql_empty);
        
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

