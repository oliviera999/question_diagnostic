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
            $sql = "SELECT COUNT(DISTINCT q.id) 
                    FROM {question} q
                    JOIN {question_versions} qv ON qv.questionid = q.id
                    JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                    WHERE qbe.questioncategoryid = :categoryid AND q.hidden = 0";
            $stats->visible_questions = $DB->count_records_sql($sql, ['categoryid' => $category->id]);
            
            // Nombre total de questions (incluant cachées) - Compatible Moodle 4.x
            $sql = "SELECT COUNT(DISTINCT q.id) 
                    FROM {question} q
                    JOIN {question_versions} qv ON qv.questionid = q.id
                    JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                    WHERE qbe.questioncategoryid = :categoryid";
            $stats->total_questions = $DB->count_records_sql($sql, ['categoryid' => $category->id]);
            
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
     * Trouve les catégories en doublon
     *
     * @return array Tableau des doublons [cat1, cat2]
     */
    public static function find_duplicates() {
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
                    JOIN {question_versions} qv ON qv.questionid = q.id
                    JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                    WHERE qbe.questioncategoryid = :categoryid";
            $questioncount = $DB->count_records_sql($sql, ['categoryid' => $categoryid]);
            
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
        $stats->total_questions = $DB->count_records('question');
        
        $categories = $DB->get_records('question_categories');
        $empty = 0;
        $orphan = 0;
        $with_questions = 0;
        
        foreach ($categories as $cat) {
            $catstats = self::get_category_stats($cat);
            if ($catstats->is_empty) $empty++;
            if ($catstats->is_orphan) $orphan++;
            if ($catstats->total_questions > 0) $with_questions++;
        }
        
        $stats->empty_categories = $empty;
        $stats->orphan_categories = $orphan;
        $stats->categories_with_questions = $with_questions;
        $stats->duplicates = count(self::find_duplicates());
        
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

