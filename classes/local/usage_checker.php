<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_question_diagnostic\local;

/**
 * Usage des questions et des catégories, selon les règles du cœur de Moodle 5.x.
 *
 * Une question est « utilisée » dès que l'une des versions de son entrée l'est :
 * - tentative (question_attempts, quel que soit le composant, aperçus exclus),
 * - référence directe (question_references : emplacement de test, version figée ou « toujours la dernière »),
 * - tout plugin qui implémente le rappel questions_in_use (offlinequiz, studentquiz…).
 * C'est la fonction questions_in_use() du cœur qui fait foi.
 *
 * Une catégorie est « utilisée par un tirage aléatoire » si une question_set_references la vise,
 * directement ou via un parent avec « inclure les sous-catégories ».
 *
 * En cas d'erreur, tout est considéré comme utilisé : le contrôle bloque, il n'autorise jamais.
 *
 * @package    local_question_diagnostic
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class usage_checker {

    /** @var array<int,bool>|null catégories visées par un tirage aléatoire (cache de requête) */
    private static ?array $randomcategories = null;

    /**
     * Charge les bibliothèques des questions.
     */
    private static function require_libs(): void {
        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');
    }

    /**
     * Vide les caches internes (après une écriture, ou entre deux tests).
     */
    public static function reset_caches(): void {
        self::$randomcategories = null;
    }

    /**
     * Toutes les versions (questionid) des entrées des questions données.
     *
     * @param int[] $questionids
     * @return array<int,int[]> questionid demandé => questionids de toutes les versions de son entrée
     */
    public static function get_entry_versions(array $questionids): array {
        global $DB;
        $questionids = self::clean_ids($questionids);
        if (!$questionids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
        $rows = $DB->get_recordset_sql("
            SELECT qv.questionid AS askedid, other.questionid AS versionid
              FROM {question_versions} qv
              JOIN {question_versions} other ON other.questionbankentryid = qv.questionbankentryid
             WHERE qv.questionid $insql", $params);
        $out = [];
        foreach ($rows as $row) {
            $out[(int)$row->askedid][] = (int)$row->versionid;
        }
        $rows->close();
        foreach ($questionids as $qid) {
            if (!isset($out[$qid])) {
                // Question sans version (donnée abîmée) : on ne regarde qu'elle-même.
                $out[$qid] = [$qid];
            }
        }
        return $out;
    }

    /**
     * L'une de ces versions a-t-elle une tentative enregistrée (hors aperçus, que le cœur supprime avec la question) ?
     *
     * question_engine::questions_in_use() sans condition renvoie toujours false : les tentatives ne sont vues par
     * le cœur que via les rappels des plugins (le test ne compte que ses tentatives hors aperçu). On regarde donc
     * directement question_attempts, quel que soit le composant.
     *
     * @param int[] $versionids
     * @return bool
     */
    public static function has_attempts(array $versionids): bool {
        global $DB;
        $versionids = self::clean_ids($versionids);
        if (!$versionids) {
            return false;
        }
        [$insql, $params] = $DB->get_in_or_equal($versionids, SQL_PARAMS_NAMED);
        $params['preview'] = 'core_question_preview';
        return $DB->record_exists_sql("
            SELECT 1
              FROM {question_attempts} qa
              JOIN {question_usages} qu ON qu.id = qa.questionusageid
             WHERE qa.questionid $insql
               AND qu.component <> :preview", $params);
    }

    /**
     * Indique, pour chaque question, si son entrée est utilisée (au sens du cœur, toutes versions confondues).
     *
     * @param int[] $questionids
     * @return array<int,bool> questionid => utilisée
     */
    public static function entries_in_use(array $questionids): array {
        self::require_libs();
        $questionids = self::clean_ids($questionids);
        $out = array_fill_keys($questionids, true);
        if (!$questionids) {
            return [];
        }
        try {
            $versions = self::get_entry_versions($questionids);
            foreach ($versions as $qid => $versionids) {
                $out[$qid] = self::has_attempts($versionids) || questions_in_use($versionids);
            }
        } catch (\Throwable $e) {
            debugging('usage_checker::entries_in_use : ' . $e->getMessage(), DEBUG_DEVELOPER);
            return array_fill_keys($questionids, true);
        }
        return $out;
    }

    /**
     * Raccourci pour une seule question.
     *
     * @param int $questionid
     * @return bool
     */
    public static function entry_in_use(int $questionid): bool {
        $res = self::entries_in_use([$questionid]);
        return $res[$questionid] ?? true;
    }

    /**
     * Pour chaque question, son entrée est-elle utilisée AUTREMENT que par une référence directe
     * (question_references) ? C'est-à-dire : tentative, ou usage déclaré par un plugin (rappel questions_in_use).
     *
     * Sert à la fusion, qui sait remapper les références directes mais rien d'autre.
     *
     * @param int[] $questionids
     * @return array<int,bool> questionid => utilisée autrement (true aussi en cas d'erreur)
     */
    public static function entries_used_outside_references(array $questionids): array {
        self::require_libs();
        $questionids = self::clean_ids($questionids);
        if (!$questionids) {
            return [];
        }
        try {
            $callbacks = [];
            foreach (get_plugins_with_function('questions_in_use') as $bytype) {
                foreach ($bytype as $function) {
                    $callbacks[] = $function;
                }
            }
            foreach (get_plugin_list_with_function('mod', 'question_list_instances') as $function) {
                // Ancien rappel : on ne sait pas l'interpréter finement, on bloque.
                return array_fill_keys($questionids, true);
            }
            $out = [];
            foreach (self::get_entry_versions($questionids) as $qid => $versionids) {
                $used = self::has_attempts($versionids);
                foreach ($callbacks as $function) {
                    if ($used) {
                        break;
                    }
                    $used = (bool)$function($versionids);
                }
                $out[$qid] = $used;
            }
            return $out;
        } catch (\Throwable $e) {
            debugging('usage_checker::entries_used_outside_references : ' . $e->getMessage(), DEBUG_DEVELOPER);
            return array_fill_keys($questionids, true);
        }
    }

    /**
     * Identifiant de la catégorie visée par une condition de tirage aléatoire, et l'option « sous-catégories ».
     *
     * @param string|null $filtercondition JSON de question_set_references.filtercondition
     * @return array{0:int,1:bool} [categoryid (0 si illisible), includesubcategories]
     */
    public static function decode_filter_condition(?string $filtercondition): array {
        self::require_libs();
        $filter = json_decode((string)$filtercondition, true);
        if (!is_array($filter)) {
            return [0, false];
        }
        if (isset($filter['questioncategoryid']) && !isset($filter['filter'])) {
            // Format antérieur à 4.3.
            return [(int)$filter['questioncategoryid'], !empty($filter['includingsubcategories'])];
        }
        $categoryid = (int)($filter['filter']['category']['values'][0] ?? 0);
        $sub = !empty($filter['filter']['category']['filteroptions']['includesubcategories'])
            || !empty($filter['filter']['category']['includesubcategories']);
        if ($categoryid <= 0 && !empty($filter['cat'])) {
            $categoryid = (int)explode(',', (string)$filter['cat'])[0];
        }
        return [$categoryid, $sub];
    }

    /**
     * Catégories visées par au moins un tirage aléatoire (sous-catégories comprises quand l'option est cochée).
     *
     * Si la lecture échoue, une exception est levée : l'appelant doit bloquer l'opération.
     *
     * @return array<int,bool> categoryid => true
     */
    public static function get_random_categories(): array {
        global $DB;
        if (self::$randomcategories !== null) {
            return self::$randomcategories;
        }
        $direct = [];
        $withsub = [];
        $rs = $DB->get_recordset('question_set_references', null, '', 'id, filtercondition');
        foreach ($rs as $ref) {
            [$catid, $sub] = self::decode_filter_condition($ref->filtercondition);
            if ($catid <= 0) {
                continue;
            }
            $direct[$catid] = true;
            if ($sub) {
                $withsub[$catid] = true;
            }
        }
        $rs->close();

        if ($withsub) {
            // Descendants des catégories visées avec « inclure les sous-catégories ».
            $children = [];
            $rs = $DB->get_recordset('question_categories', null, '', 'id, parent');
            foreach ($rs as $cat) {
                $children[(int)$cat->parent][] = (int)$cat->id;
            }
            $rs->close();
            $stack = array_keys($withsub);
            $seen = [];
            while ($stack) {
                $id = array_pop($stack);
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $direct[$id] = true;
                foreach ($children[$id] ?? [] as $child) {
                    $stack[] = $child;
                }
            }
        }
        self::$randomcategories = $direct;
        return $direct;
    }

    /**
     * La catégorie (ou, avec $recursive, l'un de ses descendants) est-elle visée par un tirage aléatoire ?
     *
     * @param int $categoryid
     * @param bool $recursive inclure les sous-catégories de $categoryid
     * @return bool true aussi en cas d'erreur de lecture
     */
    public static function category_used_by_random(int $categoryid, bool $recursive = false): bool {
        global $DB;
        try {
            $random = self::get_random_categories();
            if (isset($random[$categoryid])) {
                return true;
            }
            if (!$recursive) {
                return false;
            }
            $stack = [$categoryid];
            $seen = [];
            while ($stack) {
                $id = array_pop($stack);
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                if (isset($random[$id])) {
                    return true;
                }
                foreach ($DB->get_fieldset_select('question_categories', 'id', 'parent = ?', [$id]) as $child) {
                    $stack[] = (int)$child;
                }
            }
            return false;
        } catch (\Throwable $e) {
            debugging('usage_checker::category_used_by_random : ' . $e->getMessage(), DEBUG_DEVELOPER);
            return true;
        }
    }

    /**
     * Pour chaque question, sa catégorie est-elle un réservoir de tirage aléatoire ?
     *
     * @param int[] $questionids
     * @return array<int,bool> questionid => dans un réservoir (true aussi en cas d'erreur)
     */
    public static function questions_in_random_pool(array $questionids): array {
        global $DB;
        $questionids = self::clean_ids($questionids);
        if (!$questionids) {
            return [];
        }
        try {
            $random = self::get_random_categories();
            [$insql, $params] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
            $cats = $DB->get_records_sql_menu("
                SELECT qv.questionid, qbe.questioncategoryid
                  FROM {question_versions} qv
                  JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                 WHERE qv.questionid $insql", $params);
            $out = [];
            foreach ($questionids as $qid) {
                $out[$qid] = isset($cats[$qid]) ? isset($random[(int)$cats[$qid]]) : true;
            }
            return $out;
        } catch (\Throwable $e) {
            debugging('usage_checker::questions_in_random_pool : ' . $e->getMessage(), DEBUG_DEVELOPER);
            return array_fill_keys($questionids, true);
        }
    }

    /**
     * Nettoie une liste d'identifiants.
     *
     * @param array $ids
     * @return int[]
     */
    private static function clean_ids(array $ids): array {
        return array_values(array_unique(array_filter(array_map('intval', $ids), fn($id) => $id > 0)));
    }
}
