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
    /** @var array<int,int>|null Map catégorie_id => parent_id (cache requête) */
    private static $categoryparentmap = null;

    /** @var int|null Cache de l'ID de la catégorie racine Olution */
    private static $olutioncategoryid = null;

    /** @var array<int,bool> Cache catégorie_id => is_in_olution */
    private static $inolutioncache = [];

    /** @var array<int,int> Cache catégorie_id => depth */
    private static $depthcache = [];

    /** @var array|null Cache des groupes Olution (non paginés) */
    private static $allolutionduplicategroupscache = null;

    /**
     * Construit une condition SQL "catégorie dans Olution" (racine ou sous-catégories),
     * avec paramètres associés.
     *
     * @param string $qcalias Alias SQL de {question_categories} (ex: 'qc')
     * @param array $params Params SQL (référence)
     * @param object|null $olutioncat Objet catégorie Olution (optionnel)
     * @return string Condition SQL
     */
    private static function build_in_olution_condition(string $qcalias, array &$params, $olutioncat = null): string {
        global $DB;

        $olutionid = self::get_olution_category_id();
        if ($olutionid <= 0) {
            // Condition impossible.
            $params['olutionid'] = 0;
            return "1=0";
        }

        $params['olutionid'] = $olutionid;

        // Essayer d'utiliser qc.path si disponible (évite une énorme clause IN).
        try {
            $cols = $DB->get_columns('question_categories');
            if (isset($cols['path'])) {
                if ($olutioncat === null) {
                    $olutioncat = local_question_diagnostic_find_olution_category();
                }
                if ($olutioncat && isset($olutioncat->path) && !empty($olutioncat->path)) {
                    // Descendants = path commence par "<path>/"
                    $params['olutionpath'] = rtrim($olutioncat->path, '/') . '/%';
                    return "({$qcalias}.id = :olutionid OR " . $DB->sql_like("{$qcalias}.path", ':olutionpath', false, false) . ")";
                }
            }
        } catch (\Exception $e) {
            // Fallback ci-dessous.
        }

        // Fallback: calculer la liste des descendants en PHP.
        $parentmap = self::get_category_parent_map();
        $children = [];
        foreach ($parentmap as $id => $parent) {
            if (!isset($children[$parent])) {
                $children[$parent] = [];
            }
            $children[$parent][] = $id;
        }

        $ids = [];
        $queue = [$olutionid];
        $seen = [];
        while (!empty($queue)) {
            $current = array_shift($queue);
            if (isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;
            $ids[] = $current;
            foreach (($children[$current] ?? []) as $childid) {
                $queue[] = (int)$childid;
            }
        }

        list($insql, $inparams) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'olcat');
        $params = array_merge($params, $inparams);
        return "{$qcalias}.id {$insql}";
    }

    /**
     * Récupère les groupes de doublons (name+qtype) qui ont une présence dans Olution, paginés.
     *
     * @param int $limit Nombre de groupes à retourner
     * @param int $offset Offset de groupes
     * @param int|null $totalgroups (OUT) Nombre total de groupes matchés
     * @return array Tableau d'objets (name,qtype,dup_count)
     */
    private static function get_olution_duplicate_group_rows_paginated(int $limit, int $offset, &$totalgroups = null): array {
        global $DB;

        $olution = local_question_diagnostic_find_olution_category();
        if (!$olution) {
            $totalgroups = 0;
            return [];
        }

        $params = [];
        $inolutioncond = self::build_in_olution_condition('qc', $params, $olution);

        $sqlgroups = "SELECT q.name,
                             q.qtype,
                             COUNT(DISTINCT q.id) AS dup_count
                        FROM {question} q
                        INNER JOIN {question_versions} qv ON qv.questionid = q.id
                        INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                        INNER JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                    GROUP BY q.name, q.qtype
                      HAVING COUNT(DISTINCT q.id) > 1
                         AND COUNT(DISTINCT CASE WHEN {$inolutioncond} THEN q.id ELSE NULL END) > 0
                    ORDER BY dup_count DESC, q.name ASC, q.qtype ASC";

        // Total groups.
        $sqlcount = "SELECT COUNT(1)
                       FROM (
                             SELECT q.name, q.qtype
                               FROM {question} q
                               INNER JOIN {question_versions} qv ON qv.questionid = q.id
                               INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                               INNER JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                           GROUP BY q.name, q.qtype
                             HAVING COUNT(DISTINCT q.id) > 1
                                AND COUNT(DISTINCT CASE WHEN {$inolutioncond} THEN q.id ELSE NULL END) > 0
                            ) t";

        $totalgroups = (int)$DB->count_records_sql($sqlcount, $params);
        if ($totalgroups === 0) {
            return [];
        }

        return array_values($DB->get_records_sql($sqlgroups, $params, $offset, $limit));
    }

    /**
     * Retourne l'ID de la catégorie Olution détectée (cache en mémoire).
     *
     * @return int ID catégorie Olution, ou 0 si non trouvée
     */
    private static function get_olution_category_id(): int {
        if (self::$olutioncategoryid !== null) {
            return (int)self::$olutioncategoryid;
        }

        $olution = local_question_diagnostic_find_olution_category();
        self::$olutioncategoryid = $olution ? (int)$olution->id : 0;

        return (int)self::$olutioncategoryid;
    }

    /**
     * Charge une map (id => parent) pour TOUTES les catégories de questions.
     * Permet d'éviter les requêtes N+1 lors des parcours d'arborescence.
     *
     * @return array<int,int>
     */
    private static function get_category_parent_map(): array {
        global $DB;

        if (self::$categoryparentmap !== null) {
            return self::$categoryparentmap;
        }

        $map = [];
        $records = $DB->get_records('question_categories', null, '', 'id,parent');
        foreach ($records as $rec) {
            $map[(int)$rec->id] = (int)$rec->parent;
        }

        self::$categoryparentmap = $map;
        return self::$categoryparentmap;
    }

    /**
     * Charge en batch la catégorie de questions de chaque question (Moodle 4.x).
     *
     * @param int[] $questionids
     * @return array<int,object> Map questionid => (object) catégorie (id,name,parent,contextid)
     */
    private static function get_categories_for_questions(array $questionids): array {
        global $DB;

        $questionids = array_values(array_unique(array_map('intval', $questionids)));
        if (empty($questionids)) {
            return [];
        }

        list($insql, $params) = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
        $sql = "SELECT qv.questionid,
                       qc.id AS categoryid,
                       qc.name AS categoryname,
                       qc.parent AS categoryparent,
                       qc.contextid AS categorycontextid
                  FROM {question_versions} qv
                  INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                  INNER JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                 WHERE qv.questionid $insql";

        // questionid est unique ici, donc get_records_sql est OK.
        $records = $DB->get_records_sql($sql, $params);

        $result = [];
        foreach ($records as $rec) {
            $qid = (int)$rec->questionid;
            $cat = (object)[
                'id' => (int)$rec->categoryid,
                'name' => $rec->categoryname,
                'parent' => (int)$rec->categoryparent,
                'contextid' => (int)$rec->categorycontextid,
            ];
            $result[$qid] = $cat;
        }

        return $result;
    }

    /**
     * Obtient la profondeur d'une catégorie de questions dans l'arborescence
     * 
     * @param int $categoryid ID de la catégorie
     * @return int Profondeur (0 = racine, 1 = niveau 1, etc.)
     */
    private static function get_category_depth($categoryid) {
        $categoryid = (int)$categoryid;
        if ($categoryid <= 0) {
            return 0;
        }

        if (isset(self::$depthcache[$categoryid])) {
            return (int)self::$depthcache[$categoryid];
        }

        $parentmap = self::get_category_parent_map();
        $visited = [];
        $path = [];
        $current = $categoryid;
        $depth = 0;

        while ($current > 0) {
            if (isset(self::$depthcache[$current])) {
                $depth += (int)self::$depthcache[$current];
                break;
            }

            if (isset($visited[$current])) {
                // Boucle détectée.
                break;
            }
            $visited[$current] = true;
            $path[] = $current;

            $parent = $parentmap[$current] ?? null;
            if ($parent === null || (int)$parent === 0) {
                break;
            }

            $depth++;
            $current = (int)$parent;
        }

        // Mémoriser les profondeurs sur le chemin (meilleure perf).
        $d = $depth;
        foreach ($path as $id) {
            if (!isset(self::$depthcache[$id])) {
                self::$depthcache[$id] = $d;
            }
            $d = max(0, $d - 1);
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
        $categoryid = (int)$categoryid;
        if ($categoryid <= 0) {
            return false;
        }

        if (isset(self::$inolutioncache[$categoryid])) {
            return (bool)self::$inolutioncache[$categoryid];
        }

        $olutionid = self::get_olution_category_id();
        if ($olutionid <= 0) {
            local_question_diagnostic_debug_log('❌ Olution category not found in is_in_olution()', DEBUG_DEVELOPER);
            self::$inolutioncache[$categoryid] = false;
            return false;
        }

        $parentmap = self::get_category_parent_map();
        $current = $categoryid;
        $visited = [];
        $path = [];

        while ($current > 0) {
            if (isset($visited[$current])) {
                local_question_diagnostic_debug_log('⚠️ Loop detected in is_in_olution() for category ' . $categoryid, DEBUG_DEVELOPER);
                break;
            }
            $visited[$current] = true;
            $path[] = $current;

            if ($current === $olutionid) {
                self::$inolutioncache[$categoryid] = true;
                return true;
            }

            $parent = $parentmap[$current] ?? null;
            if ($parent === null || (int)$parent === 0) {
                break;
            }

            $current = (int)$parent;
        }

        local_question_diagnostic_debug_log('❌ Category ' . $categoryid . ' is NOT in Olution (path: ' . implode(' -> ', $path) . ')', DEBUG_DEVELOPER);
        self::$inolutioncache[$categoryid] = false;
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
            // Cache simple (dans la même requête HTTP) pour éviter double calcul (page + stats).
            if ((int)$limit === 0 && (int)$offset === 0 && self::$allolutionduplicategroupscache !== null) {
                return self::$allolutionduplicategroupscache;
            }

            // Vérifier que la catégorie de questions Olution existe
            $olution = local_question_diagnostic_find_olution_category();
            if (!$olution) {
                local_question_diagnostic_debug_log('❌ Olution question category not found', DEBUG_DEVELOPER);
                return [];
            }
            
            local_question_diagnostic_debug_log('✅ Olution question category found: ' . $olution->name . ' (ID: ' . $olution->id . ')', DEBUG_DEVELOPER);
            
            // Utiliser la détection de doublons existante (nom + type)
            // Récupérer TOUS les groupes de doublons du site
            $duplicate_groups = question_analyzer::get_duplicate_groups(0, 0, false, false);
            
            local_question_diagnostic_debug_log('📊 Found ' . count($duplicate_groups) . ' duplicate groups', DEBUG_DEVELOPER);
            
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

                // Récupérer en batch la catégorie de chaque question (évite N+1).
                $categoriesbyquestion = self::get_categories_for_questions($question_ids);
                
                // Récupérer les catégories de chaque question
                $questions_with_categories = [];
                $olution_questions = [];
                $non_olution_questions = [];
                
                foreach ($questions as $q) {
                    $cat = $categoriesbyquestion[(int)$q->id] ?? null;
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
                    // Choisir la catégorie cible de façon stable :
                    // - Priorité 1 : Catégorie Olution la plus fréquente dans le groupe (majorité)
                    // - Priorité 2 : Profondeur la plus élevée (plus spécifique)
                    // - Priorité 3 : ID le plus petit (déterministe)
                    $countsbycatid = [];
                    $depthbycatid = [];
                    $catbyid = [];

                    foreach ($olution_questions as $oq) {
                        $cid = (int)$oq['category']->id;
                        if (!isset($countsbycatid[$cid])) {
                            $countsbycatid[$cid] = 0;
                        }
                        $countsbycatid[$cid]++;
                        $depthbycatid[$cid] = max($depthbycatid[$cid] ?? -1, (int)$oq['depth']);
                        $catbyid[$cid] = $oq['category'];
                    }

                    $targetcat = null;
                    $targetdepth = -1;
                    $bestcount = -1;
                    $bestid = PHP_INT_MAX;

                    foreach ($countsbycatid as $cid => $count) {
                        $depth = $depthbycatid[$cid] ?? -1;
                        if ($count > $bestcount
                            || ($count === $bestcount && $depth > $targetdepth)
                            || ($count === $bestcount && $depth === $targetdepth && $cid < $bestid)) {
                            $bestcount = $count;
                            $bestid = $cid;
                            $targetdepth = $depth;
                            $targetcat = $catbyid[$cid] ?? null;
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
                        'target_category' => $targetcat,
                        'target_depth' => $targetdepth
                    ];
                }
            }
            
            local_question_diagnostic_debug_log('📊 Found ' . count($results) . ' duplicate groups with Olution presence', DEBUG_DEVELOPER);
            
            // Appliquer pagination
            if ($limit > 0) {
                $results = array_slice($results, $offset, $limit);
            }
            if ((int)$limit === 0 && (int)$offset === 0) {
                self::$allolutionduplicategroupscache = $results;
            }

            return $results;
            
        } catch (\Exception $e) {
            local_question_diagnostic_debug_log('Error in find_all_duplicates_for_olution: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
    }

    /**
     * Détecte tous les groupes de doublons Olution avec pagination, et retourne le total avant pagination.
     *
     * @param int $limit Limite du nombre de groupes (0 = tous)
     * @param int $offset Offset pour pagination
     * @param int|null $totalgroups (OUT) Nombre total de groupes avant pagination
     * @return array Tableau de groupes de doublons paginés
     */
    public static function find_all_duplicates_for_olution_paginated($limit = 0, $offset = 0, &$totalgroups = null) {
        global $DB;

        $limit = (int)$limit;
        $offset = (int)$offset;
        if ($limit <= 0) {
            $limit = 50;
        }
        if ($offset < 0) {
            $offset = 0;
        }

        $rows = self::get_olution_duplicate_group_rows_paginated($limit, $offset, $totalgroups);
        if (empty($rows)) {
            return [];
        }

        // Construire les mêmes structures que find_all_duplicates_for_olution(), mais seulement pour la page demandée.
        $results = [];
        foreach ($rows as $row) {
            $question_ids = $DB->get_fieldset_select('question', 'id',
                'name = :name AND qtype = :qtype',
                ['name' => $row->name, 'qtype' => $row->qtype]
            );

            if (empty($question_ids)) {
                continue;
            }

            list($insql, $params) = $DB->get_in_or_equal($question_ids);
            $questions = $DB->get_records_select('question', "id $insql", $params);
            $categoriesbyquestion = self::get_categories_for_questions($question_ids);

            $questions_with_categories = [];
            $olution_questions = [];
            $non_olution_questions = [];

            foreach ($questions as $q) {
                $cat = $categoriesbyquestion[(int)$q->id] ?? null;
                if (!$cat) {
                    continue;
                }

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

            if (empty($olution_questions)) {
                // Par sécurité: la requête SQL dit qu'il y a présence Olution, mais si nos données ne le voient pas, on ignore.
                continue;
            }

            // Réutiliser la logique de choix de cible (majorité puis profondeur).
            $countsbycatid = [];
            $depthbycatid = [];
            $catbyid = [];
            foreach ($olution_questions as $oq) {
                $cid = (int)$oq['category']->id;
                $countsbycatid[$cid] = ($countsbycatid[$cid] ?? 0) + 1;
                $depthbycatid[$cid] = max($depthbycatid[$cid] ?? -1, (int)$oq['depth']);
                $catbyid[$cid] = $oq['category'];
            }

            $targetcat = null;
            $targetdepth = -1;
            $bestcount = -1;
            $bestid = PHP_INT_MAX;
            foreach ($countsbycatid as $cid => $count) {
                $depth = $depthbycatid[$cid] ?? -1;
                if ($count > $bestcount
                    || ($count === $bestcount && $depth > $targetdepth)
                    || ($count === $bestcount && $depth === $targetdepth && $cid < $bestid)) {
                    $bestcount = $count;
                    $bestid = $cid;
                    $targetdepth = $depth;
                    $targetcat = $catbyid[$cid] ?? null;
                }
            }

            $results[] = [
                'group_name' => $row->name,
                'group_type' => $row->qtype,
                'total_count' => count($questions_with_categories),
                'olution_count' => count($olution_questions),
                'non_olution_count' => count($non_olution_questions),
                'all_questions' => $questions_with_categories,
                'olution_questions' => $olution_questions,
                'non_olution_questions' => $non_olution_questions,
                'target_category' => $targetcat,
                'target_depth' => $targetdepth
            ];
        }

        return $results;
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

        // Stats "fast" basées SQL pour éviter recalcul complet.
        $params = [];
        $inolutioncond = self::build_in_olution_condition('qc', $params, $olution);

        // Total de questions dans les groupes avec présence Olution.
        $sqlsum = "SELECT COALESCE(SUM(g.dup_count), 0) AS total_questions
                     FROM (
                           SELECT q.name, q.qtype, COUNT(DISTINCT q.id) AS dup_count
                             FROM {question} q
                             INNER JOIN {question_versions} qv ON qv.questionid = q.id
                             INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                             INNER JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                         GROUP BY q.name, q.qtype
                           HAVING COUNT(DISTINCT q.id) > 1
                              AND COUNT(DISTINCT CASE WHEN {$inolutioncond} THEN q.id ELSE NULL END) > 0
                          ) g";
        $stats->total_duplicates = (int)$DB->get_field_sql($sqlsum, $params);

        // Questions "déplaçables" = questions hors Olution, mais appartenant à un groupe avec présence Olution.
        // On reconstruit les params pour éviter tout effet de bord.
        $params2 = [];
        $inolutioncond1 = self::build_in_olution_condition('qc', $params2, $olution);
        $inolutioncond2 = self::build_in_olution_condition('qc2', $params2, $olution);
        $sqlmovable = "SELECT COUNT(DISTINCT q.id)
                         FROM {question} q
                         INNER JOIN {question_versions} qv ON qv.questionid = q.id
                         INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                         INNER JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                        WHERE NOT ({$inolutioncond1})
                          AND EXISTS (
                              SELECT 1
                                FROM {question} q2
                                INNER JOIN {question_versions} qv2 ON qv2.questionid = q2.id
                                INNER JOIN {question_bank_entries} qbe2 ON qbe2.id = qv2.questionbankentryid
                                INNER JOIN {question_categories} qc2 ON qc2.id = qbe2.questioncategoryid
                               WHERE q2.name = q.name
                                 AND q2.qtype = q.qtype
                               GROUP BY q2.name, q2.qtype
                                 HAVING COUNT(DISTINCT q2.id) > 1
                                    AND COUNT(DISTINCT CASE WHEN {$inolutioncond2} THEN q2.id ELSE NULL END) > 0
                          )";
        $stats->movable_questions = (int)$DB->count_records_sql($sqlmovable, $params2);
        $stats->unmovable_questions = max(0, (int)$stats->total_duplicates - (int)$stats->movable_questions);
        
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
            local_question_diagnostic_debug_log('🚀 Starting move_question_to_olution: question=' . $questionid . ', target=' . $target_category_id, DEBUG_DEVELOPER);
            
            require_once($CFG->libdir . '/questionlib.php');

            // Vérifier que la question existe
            $question = $DB->get_record('question', ['id' => $questionid]);
            if (!$question) {
                return 'Question introuvable (ID: ' . $questionid . ')';
            }
            
            local_question_diagnostic_debug_log('✅ Question found: ' . $question->name . ' (type: ' . $question->qtype . ')', DEBUG_DEVELOPER);
            
            // Vérifier que la catégorie cible existe et est dans Olution
            $target_category = $DB->get_record('question_categories', ['id' => $target_category_id]);
            if (!$target_category) {
                return 'Catégorie cible introuvable (ID: ' . $target_category_id . ')';
            }
            
            local_question_diagnostic_debug_log('✅ Target category found: ' . $target_category->name, DEBUG_DEVELOPER);
            
            // Vérifier que la catégorie cible est bien dans Olution
            if (!self::is_in_olution($target_category_id)) {
                return 'La catégorie cible n\'est pas dans Olution (ID: ' . $target_category_id . ')';
            }
            
            local_question_diagnostic_debug_log('✅ Target category is confirmed to be in Olution', DEBUG_DEVELOPER);
            
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
            
            local_question_diagnostic_debug_log('✅ Current category: ' . $current_category->name . ' (ID: ' . $current_category->id . ')', DEBUG_DEVELOPER);
            
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
                    local_question_diagnostic_debug_log('✅ Moved using native question_move_questions_to_category', DEBUG_DEVELOPER);
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
                    
                    local_question_diagnostic_debug_log('✅ Updated ' . $affected_rows . ' question_bank_entries (Manual fallback)', DEBUG_DEVELOPER);
                    
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
                $verify_sql = "SELECT qc.id as category_id, qc.name as category_name
                              FROM {question_categories} qc
                              INNER JOIN {question_bank_entries} qbe ON qbe.questioncategoryid = qc.id
                              INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                              WHERE qv.questionid = :questionid
                              LIMIT 1";
                $verify_result = $DB->get_record_sql($verify_sql, ['questionid' => $questionid]);
                
                if (!$verify_result || (int)$verify_result->category_id !== (int)$target_category_id) {
                    throw new \Exception('Vérification échouée après déplacement');
                }
                
                local_question_diagnostic_debug_log('✅ Verification successful: question is now in ' . $verify_result->category_name . ' (ID: ' . $verify_result->category_id . ')', DEBUG_DEVELOPER);
                
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
                
                local_question_diagnostic_debug_log('✅ Question successfully moved to Olution: ' . $target_category->name, DEBUG_DEVELOPER);
                return true;
                
            } catch (\Exception $inner_e) {
                local_question_diagnostic_debug_log('❌ Error in transaction: ' . $inner_e->getMessage(), DEBUG_DEVELOPER);
                throw $inner_e;
            }
            
        } catch (\Exception $e) {
            local_question_diagnostic_debug_log('❌ Error in move_question_to_olution: ' . $e->getMessage(), DEBUG_DEVELOPER);
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
            local_question_diagnostic_debug_log('🧪 Starting test_automatic_movement_to_olution with limit: ' . $limit, DEBUG_DEVELOPER);
            
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
            
            local_question_diagnostic_debug_log('✅ Olution category found: ' . $olution->name . ' (ID: ' . $olution->id . ')', DEBUG_DEVELOPER);
            
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
            
            local_question_diagnostic_debug_log('✅ Found ' . count($olution_subcategories) . ' Olution subcategories', DEBUG_DEVELOPER);
            
            // Récupérer quelques questions candidates, puis filtrer en PHP avec is_in_olution()
            // (évite le faux "hors Olution" quand la question est dans une sous-sous-catégorie).
            $candidateslimit = max(20, (int)$limit * 20);
            $candidates_sql = "SELECT DISTINCT q.*
                                 FROM {question} q
                                WHERE q.name IS NOT NULL
                                  AND q.name != ''
                             ORDER BY q.id DESC";

            $candidate_questions = $DB->get_records_sql($candidates_sql, [], 0, $candidateslimit);
            $candidate_ids = array_keys($candidate_questions);
            $catsbyqid = self::get_categories_for_questions($candidate_ids);

            $non_olution_questions = [];
            foreach ($candidate_questions as $q) {
                $qid = (int)$q->id;
                $cat = $catsbyqid[$qid] ?? null;
                if (!$cat) {
                    continue;
                }
                if (!self::is_in_olution((int)$cat->id)) {
                    $non_olution_questions[$qid] = $q;
                }
                if (count($non_olution_questions) >= (int)$limit) {
                    break;
                }
            }
            
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
            
            local_question_diagnostic_debug_log('✅ Found ' . count($non_olution_questions) . ' questions outside Olution for testing', DEBUG_DEVELOPER);
            
            $test_results = [];
            $moved_count = 0;
            $failed_count = 0;
            
            foreach ($non_olution_questions as $question) {
                // Choisir une sous-catégorie Olution aléatoire comme cible
                $target_category = $olution_subcategories[array_rand($olution_subcategories)];
                
                local_question_diagnostic_debug_log('🧪 Testing move: question ' . $question->id . ' (' . $question->name . ') to ' . $target_category->name . ' (ID: ' . $target_category->id . ')', DEBUG_DEVELOPER);
                
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
                    local_question_diagnostic_debug_log('✅ Test successful: question ' . $question->id . ' moved to ' . $target_category->name, DEBUG_DEVELOPER);
                } else {
                    $failed_count++;
                    local_question_diagnostic_debug_log('❌ Test failed: question ' . $question->id . ' - ' . $move_result, DEBUG_DEVELOPER);
                }
                
                $test_results[] = $test_result;
            }
            
            $overall_success = ($moved_count > 0);
            
            local_question_diagnostic_debug_log('🏁 Test completed: ' . $moved_count . ' moved, ' . $failed_count . ' failed', DEBUG_DEVELOPER);
            
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
            
        } catch (\Exception $e) {
            local_question_diagnostic_debug_log('❌ Error in test_automatic_movement_to_olution: ' . $e->getMessage(), DEBUG_DEVELOPER);
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
