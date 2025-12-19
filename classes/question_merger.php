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

/**
 * Fusion sécurisée de questions strictement identiques.
 *
 * Objectif :
 * - Choisir une question "référence" (celle avec le plus de tentatives).
 * - Remapper les références des doublons (sans tentatives) vers la référence.
 * - Supprimer les doublons via l'API Moodle (question_delete_question) via question_analyzer.
 *
 * ⚠️ Important :
 * - Ne JAMAIS modifier les tables d'historique de tentatives.
 * - Ne JAMAIS modifier les tables internes de définition de questions (question_* internes, qtype_*),
 *   on laisse Moodle gérer leur suppression lors du delete.
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_question_diagnostic;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/question_analyzer.php');
require_once(__DIR__ . '/audit_logger.php');

class question_merger {

    /**
     * Construit un plan de fusion (preview / dry-run).
     *
     * @param int $representativeid
     * @param array $options
     * @return object Plan structuré
     */
    public static function build_merge_plan(int $representativeid, array $options = []): object {
        global $DB;

        $opts = self::normalize_options($options);

        $plan = (object)[
            'representative_id' => (int)$representativeid,
            'group' => null,
            'questions' => [], // qid => info
            'reference_questionid' => 0,
            'mergeable_questionids' => [],
            'skipped' => [
                'has_attempts' => [],
                'in_quiz' => [],
                'not_found' => [],
            ],
            'mappings' => (object)[
                'questionid' => [],          // old => ref
                'questionbankentryid' => [], // old => ref
                'questionversionid' => [],   // old => ref
            ],
            'targets' => [], // list of {table, column, type}
            'impacts' => [], // list of {table, column, type, before_count}
            'warnings' => [],
            'errors' => [],
        ];

        if ($plan->representative_id <= 0) {
            $plan->errors[] = 'representative_id invalide';
            return $plan;
        }

        // Exiger la définition "doublons certains" (cible Moodle 4.5, mais on garde une validation).
        if (!self::can_use_strict_definition()) {
            $plan->errors[] = 'Définition "doublons stricts" indisponible sur ce site (colonnes manquantes).';
            return $plan;
        }

        // 1) Charger le groupe (strict).
        $questionids = question_analyzer::get_duplicate_group_question_ids_by_representative_id($plan->representative_id);
        $questionids = array_values(array_unique(array_map('intval', (array)$questionids)));
        $questionids = array_values(array_filter($questionids, function(int $id): bool {
            return $id > 0;
        }));
        if (count($questionids) < 2) {
            $plan->errors[] = 'Ce groupe ne contient pas (ou plus) de doublons stricts.';
            return $plan;
        }

        // 2) Charger questions + usage (tentatives / quiz) en batch.
        list($insql, $inparams) = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED, 'qid');
        $questions = $DB->get_records_select(
            'question',
            "id {$insql}",
            $inparams,
            '',
            'id,name,qtype,questiontext,questiontextformat,timecreated,timemodified'
        );
        $usage = question_analyzer::get_questions_usage_by_ids($questionids);
        $ctxmap = self::get_questions_context_info_by_ids($questionids);
        $versionmap = self::get_question_versions_by_question_ids($questionids);

        // 3) Construire une vue par question (et repérer les manquantes).
        foreach ($questionids as $qid) {
            $q = $questions[$qid] ?? null;
            if (!$q) {
                $plan->skipped['not_found'][] = (int)$qid;
                continue;
            }
            $u = $usage[$qid] ?? ['quiz_count' => 0, 'attempt_count' => 0, 'is_used' => false, 'quiz_list' => []];
            $c = $ctxmap[$qid] ?? null;
            $v = $versionmap[$qid] ?? null;

            $plan->questions[$qid] = (object)[
                'id' => (int)$qid,
                'name' => (string)($q->name ?? ''),
                'qtype' => (string)($q->qtype ?? ''),
                'questiontextformat' => (int)($q->questiontextformat ?? 0),
                'attempt_count' => (int)($u['attempt_count'] ?? 0),
                'quiz_count' => (int)($u['quiz_count'] ?? 0),
                'context' => $c,
                'version' => $v,
            ];
        }

        if (count($plan->questions) < 2) {
            $plan->errors[] = 'Impossible de charger suffisamment de questions dans ce groupe.';
            return $plan;
        }

        // 4) Choisir la référence : max attempt_count, tie-breaker min id.
        $bestid = 0;
        $bestattempts = -1;
        foreach ($plan->questions as $qid => $info) {
            $attempts = (int)($info->attempt_count ?? 0);
            if ($attempts > $bestattempts || ($attempts === $bestattempts && ($bestid === 0 || $qid < $bestid))) {
                $bestattempts = $attempts;
                $bestid = (int)$qid;
            }
        }
        $plan->reference_questionid = (int)$bestid;

        if ($plan->reference_questionid <= 0) {
            $plan->errors[] = 'Impossible de déterminer une question de référence.';
            return $plan;
        }

        // 5) Déterminer les doublons fusionnables : UNIQUEMENT attempt_count=0.
        // Les questions déjà utilisées dans des quiz mais sans tentatives restent fusionnables :
        // on remappe les références (quiz_slots) vers la référence.
        foreach ($plan->questions as $qid => $info) {
            if ((int)$qid === $plan->reference_questionid) {
                continue;
            }
            if ((int)($info->attempt_count ?? 0) > 0) {
                $plan->skipped['has_attempts'][] = (int)$qid;
                continue;
            }
            $plan->mergeable_questionids[] = (int)$qid;
        }

        if (empty($plan->mergeable_questionids)) {
            $plan->warnings[] = 'Aucun doublon fusionnable (tous ont des tentatives).';
            // Plan vide mais valide pour preview.
        }

        // 6) Construire les mappings (questionid/qbe/qv).
        foreach ($plan->mergeable_questionids as $oldqid) {
            $plan->mappings->questionid[(int)$oldqid] = (int)$plan->reference_questionid;
        }

        $refv = $plan->questions[$plan->reference_questionid]->version ?? null;
        $refqbeid = (int)($refv->questionbankentryid ?? 0);
        $refqvid = (int)($refv->id ?? 0);
        if ($refqbeid <= 0) {
            $plan->warnings[] = 'Impossible de déterminer le question_bank_entry de la référence (question_versions manquant ?).';
        }

        foreach ($plan->mergeable_questionids as $oldqid) {
            $oldv = $plan->questions[$oldqid]->version ?? null;
            $oldqbeid = (int)($oldv->questionbankentryid ?? 0);
            $oldqvid = (int)($oldv->id ?? 0);
            if ($oldqbeid > 0 && $refqbeid > 0) {
                $plan->mappings->questionbankentryid[$oldqbeid] = $refqbeid;
            }
            if ($oldqvid > 0 && $refqvid > 0) {
                $plan->mappings->questionversionid[$oldqvid] = $refqvid;
            }
        }

        // 7) Warnings cross-context.
        $refctxid = (int)($plan->questions[$plan->reference_questionid]->context->contextid ?? 0);
        foreach ($plan->mergeable_questionids as $oldqid) {
            $oldctxid = (int)($plan->questions[$oldqid]->context->contextid ?? 0);
            if ($refctxid > 0 && $oldctxid > 0 && $refctxid !== $oldctxid) {
                $plan->warnings[] = 'Fusion cross-context: ' . $oldqid . ' (contextid=' . $oldctxid . ') → '
                    . $plan->reference_questionid . ' (contextid=' . $refctxid . ').';
            }
        }

        // 8) Déterminer les cibles de remap (whitelist + découverte contrôlée).
        $targets = self::get_reference_update_targets($opts);
        $plan->targets = $targets;

        // 9) Calculer les impacts (COUNT avant) pour le preview.
        $plan->impacts = self::compute_impacts($targets, $plan->mappings);

        // Groupe affichable (nom/type) basé sur la référence.
        $refinfo = $plan->questions[$plan->reference_questionid] ?? null;
        $plan->group = (object)[
            'qtype' => $refinfo ? (string)$refinfo->qtype : '',
            'name' => $refinfo ? (string)$refinfo->name : '',
            'size' => count($plan->questions),
        ];

        return $plan;
    }

    /**
     * Applique un plan de fusion (exécution).
     *
     * @param object $plan
     * @param array $options
     * @return object Résultat {success:bool, message:string, details:array}
     */
    public static function apply_merge_plan(object $plan, array $options = []): object {
        global $DB;

        $opts = self::normalize_options($options);

        $result = (object)[
            'success' => false,
            'message' => '',
            'details' => [
                'updated' => [],
                'deleted' => [],
                'skipped' => $plan->skipped ?? [],
                'warnings' => $plan->warnings ?? [],
            ],
        ];

        if (!is_object($plan) || empty($plan->reference_questionid)) {
            $result->message = 'Plan invalide.';
            return $result;
        }

        if (empty((array)($plan->mappings->questionid ?? [])) && empty((array)($plan->mappings->questionbankentryid ?? []))) {
            $result->message = 'Rien à fusionner.';
            $result->success = true;
            return $result;
        }

        // Lock applicatif si disponible (évite double exécution concurrente).
        return self::with_lock(function() use ($DB, $plan, $opts, $result) {
            $transaction = $DB->start_delegated_transaction();
            try {
                // 1) Mettre à jour les références (tables externes).
                $targets = self::get_reference_update_targets($opts);
                $updated = self::apply_reference_updates($targets, $plan->mappings);
                $result->details['updated'] = $updated;

                // 2) Post-check : s'assurer qu'il ne reste aucune référence vers les anciens IDs (sur ces cibles).
                $after = self::compute_impacts_after($targets, $plan->mappings);
                if (!empty($after['still_referenced'])) {
                    $details = [];
                    foreach ((array)$after['still_referenced'] as $impact) {
                        $details[] = (string)($impact->table ?? '?') . '.' . (string)($impact->column ?? '?')
                            . ' (type=' . (string)($impact->type ?? '?') . ', count=' . (int)($impact->after_count ?? 0) . ')';
                    }
                    throw new \Exception('Post-check échoué : des références vers des doublons existent encore après remap. ' . implode(' | ', $details));
                }

                // 3) Supprimer les doublons (API Moodle via question_analyzer).
                foreach ((array)($plan->mergeable_questionids ?? []) as $oldqid) {
                    $oldqid = (int)$oldqid;
                    if ($oldqid <= 0) {
                        continue;
                    }
                    // Idempotence : si déjà supprimée, on ignore.
                    if (!$DB->record_exists('question', ['id' => $oldqid])) {
                        continue;
                    }
                    $msg = question_analyzer::delete_question_safe($oldqid);
                    if (!empty($msg) && is_string($msg) && stripos($msg, 'erreur') !== false) {
                        throw new \Exception('Suppression du doublon impossible : ' . $msg);
                    }
                    $result->details['deleted'][] = $oldqid;
                }

                // 4) Purger caches plugin + audit.
                question_analyzer::purge_all_caches();
                audit_logger::log_questions_merge(
                    (int)$plan->reference_questionid,
                    array_values(array_map('intval', (array)($plan->mergeable_questionids ?? []))),
                    [
                        'updated_targets' => $result->details['updated'],
                        'options' => (array)$opts,
                    ]
                );

                $transaction->allow_commit();
                $result->success = true;
                $result->message = 'Fusion effectuée avec succès.';
                return $result;

            } catch (\Throwable $e) {
                $transaction->rollback($e);
                $result->success = false;
                $result->message = 'Erreur pendant la fusion : ' . $e->getMessage();
                return $result;
            }
        });
    }

    /**
     * Définition "strict" disponible ?
     * @return bool
     */
    private static function can_use_strict_definition(): bool {
        global $DB;

        if (!method_exists('\\local_question_diagnostic\\question_analyzer', 'get_duplicate_group_question_ids_by_representative_id')) {
            return false;
        }
        try {
            $cols = $DB->get_columns('question');
            return isset($cols['qtype']) && isset($cols['questiontextformat']) && isset($cols['questiontext']);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Normalise options.
     * @param array $options
     * @return object
     */
    private static function normalize_options(array $options): object {
        return (object)[
            'include_quiz_references' => !empty($options['include_quiz_references']),
            'advanced_discovery' => !empty($options['advanced_discovery']),
        ];
    }

    /**
     * Récupère les infos de contexte (catégorie + context) par questionid en batch.
     *
     * @param int[] $questionids
     * @return array<int,object>
     */
    private static function get_questions_context_info_by_ids(array $questionids): array {
        global $DB;

        $questionids = array_values(array_unique(array_map('intval', (array)$questionids)));
        $questionids = array_values(array_filter($questionids, function(int $id): bool {
            return $id > 0;
        }));
        if (empty($questionids)) {
            return [];
        }

        // ⚠️ Important : ne pas réutiliser le même IN(:qid0,...) 2 fois dans une requête avec params nommés,
        // sinon Moodle compte les placeholders deux fois (ex: 14 attendus) alors que $params n'en contient que 7.
        list($insqlsub, $paramssub) = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED, 'qid');
        list($insqlmain, $paramsmain) = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED, 'qid2');
        $params = array_merge($paramssub, $paramsmain);

        // Moodle 4.5+ : utiliser la version la plus récente (version max), idéalement non-brouillon si la colonne status existe.
        $qvcols = [];
        try {
            $qvcols = $DB->get_columns('question_versions');
        } catch (\Throwable $e) {
            $qvcols = [];
        }
        $statusfilter = '';
        if (is_array($qvcols) && isset($qvcols['status'])) {
            // Les valeurs exactes de status sont gérées par Moodle; on exclut "draft" si présent.
            $statusfilter = " AND v.status <> 'draft' ";
        }

        $sql = "SELECT qv.questionid,
                       qc.id AS categoryid,
                       qc.name AS categoryname,
                       qc.contextid,
                       ctx.contextlevel
                  FROM {question_versions} qv
                  INNER JOIN (
                        SELECT v.questionid, MAX(v.version) AS maxversion
                          FROM {question_versions} v
                         WHERE v.questionid {$insqlsub} {$statusfilter}
                      GROUP BY v.questionid
                  ) mv ON mv.questionid = qv.questionid AND mv.maxversion = qv.version
                  INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                  INNER JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                  LEFT JOIN {context} ctx ON ctx.id = qc.contextid
                 WHERE qv.questionid {$insqlmain}";

        $records = $DB->get_records_sql($sql, $params);
        $out = [];
        foreach ($records as $r) {
            $qid = (int)$r->questionid;
            $out[$qid] = (object)[
                'categoryid' => (int)($r->categoryid ?? 0),
                'categoryname' => (string)($r->categoryname ?? ''),
                'contextid' => (int)($r->contextid ?? 0),
                'contextlevel' => isset($r->contextlevel) ? (int)$r->contextlevel : null,
            ];
        }
        return $out;
    }

    /**
     * Récupère question_versions par questionid en batch.
     *
     * @param int[] $questionids
     * @return array<int,object> questionid => {id, questionbankentryid}
     */
    private static function get_question_versions_by_question_ids(array $questionids): array {
        global $DB;

        $questionids = array_values(array_unique(array_map('intval', (array)$questionids)));
        $questionids = array_values(array_filter($questionids, function(int $id): bool {
            return $id > 0;
        }));
        if (empty($questionids)) {
            return [];
        }

        // Même contrainte : 2 IN(...) -> 2 jeux de placeholders + merge des params.
        list($insqlsub, $paramssub) = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED, 'qid');
        list($insqlmain, $paramsmain) = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED, 'qid2');
        $params = array_merge($paramssub, $paramsmain);
        // Moodle 4.5+ : prendre la version la plus récente (max(version)), idéalement non-brouillon si status existe.
        $qvcols = [];
        try {
            $qvcols = $DB->get_columns('question_versions');
        } catch (\Throwable $e) {
            $qvcols = [];
        }
        $statusfilter = '';
        if (is_array($qvcols) && isset($qvcols['status'])) {
            $statusfilter = " AND v.status <> 'draft' ";
        }

        $sql = "SELECT qv.id, qv.questionid, qv.questionbankentryid
                  FROM {question_versions} qv
                  INNER JOIN (
                        SELECT v.questionid, MAX(v.version) AS maxversion
                          FROM {question_versions} v
                         WHERE v.questionid {$insqlsub} {$statusfilter}
                      GROUP BY v.questionid
                  ) mv ON mv.questionid = qv.questionid AND mv.maxversion = qv.version
                 WHERE qv.questionid {$insqlmain}";
        $records = $DB->get_records_sql($sql, $params);
        $out = [];
        foreach ($records as $r) {
            $qid = (int)$r->questionid;
            $out[$qid] = (object)[
                'id' => (int)($r->id ?? 0),
                'questionid' => $qid,
                'questionbankentryid' => (int)($r->questionbankentryid ?? 0),
            ];
        }
        return $out;
    }

    /**
     * Détermine les tables/colonnes à mettre à jour.
     *
     * @param object $opts
     * @return array<int,object>
     */
    private static function get_reference_update_targets(object $opts): array {
        global $DB;

        $targets = [];

        // Whitelist (core, externes aux définitions de question).
        $whitelist = [
            'quiz_slots' => ['questionid', 'questionbankentryid'],
            // Moodle 4.5+ : les usages (ex: quiz) passent par question_references.
            // NB: on ne mappe pas la "version" en dur : on remet version/questionversionid à NULL
            // afin d'utiliser la dernière version non-brouillon (comportement core).
            'question_references' => ['questionbankentryid'],
            // Selon versions, question_set_references peut exposer un questionbankentryid.
            // On applique la même logique "latest" si des colonnes version/questionversionid existent.
            'question_set_references' => ['questionbankentryid'],
        ];

        foreach ($whitelist as $table => $cols) {
            try {
                $columns = $DB->get_columns($table);
            } catch (\Throwable $e) {
                continue;
            }
            foreach ($cols as $col) {
                if (!isset($columns[$col])) {
                    continue;
                }
                $type = self::guess_target_type($col);
                if ($type === null) {
                    continue;
                }
                $targets[] = (object)[
                    'table' => $table,
                    'column' => $col,
                    'type' => $type,
                ];
            }
        }

        // Découverte contrôlée : uniquement des tables NON internes, avec colonnes explicites.
        if (!empty($opts->advanced_discovery)) {
            $dbman = $DB->get_manager();
            $tables = [];
            try {
                $tables = $dbman->get_tables();
            } catch (\Throwable $e) {
                $tables = [];
            }

            $blocked = self::blocked_tables_for_discovery();
            foreach ($tables as $t) {
                $t = (string)$t;
                if ($t === '' || isset($blocked[$t])) {
                    continue;
                }
                // Exclure tables internes de définition.
                if (preg_match('/^question_/', $t) && $t !== 'question_references' && $t !== 'question_set_references') {
                    continue;
                }
                if (preg_match('/^qtype_/', $t)) {
                    continue;
                }

                try {
                    $cols = $DB->get_columns($t);
                } catch (\Throwable $e) {
                    continue;
                }

                foreach (['questionid', 'questionbankentryid', 'questionversionid'] as $col) {
                    if (!isset($cols[$col])) {
                        continue;
                    }
                    // Tables spéciales : éviter d'update des colonnes version/questionid inadaptées.
                    if ($t === 'question_references' && $col !== 'questionbankentryid') {
                        continue;
                    }
                    if ($t === 'question_set_references' && $col !== 'questionbankentryid') {
                        continue;
                    }
                    $type = self::guess_target_type($col);
                    if ($type === null) {
                        continue;
                    }
                    $targets[] = (object)[
                        'table' => $t,
                        'column' => $col,
                        'type' => $type,
                    ];
                }
            }
        }

        // Dédoublonner.
        $uniq = [];
        $out = [];
        foreach ($targets as $t) {
            $key = $t->table . '|' . $t->column;
            if (isset($uniq[$key])) {
                continue;
            }
            $uniq[$key] = true;
            $out[] = $t;
        }
        return $out;
    }

    /**
     * Tables explicitement interdites pour remap/discovery.
     * @return array<string,bool>
     */
    private static function blocked_tables_for_discovery(): array {
        return array_fill_keys([
            // Core : définition question / versioning (ne jamais UPDATE ici).
            'question',
            'question_versions',
            'question_bank_entries',
            'question_categories',
            // Historique tentatives (ne jamais modifier).
            'question_attempts',
            'question_attempt_steps',
            'question_attempt_step_data',
            'question_usages',
            // Logs / caches (inutile et risqué).
            'logstore_standard_log',
        ], true);
    }

    /**
     * Devine le type de mapping en fonction du nom de colonne.
     * @param string $column
     * @return string|null
     */
    private static function guess_target_type(string $column): ?string {
        switch ($column) {
            case 'questionid':
                return 'questionid';
            case 'questionbankentryid':
                return 'questionbankentryid';
            case 'questionversionid':
                return 'questionversionid';
            default:
                return null;
        }
    }

    /**
     * Calcule les impacts (COUNT) pour un set de targets.
     *
     * @param array $targets
     * @param object $mappings
     * @return array<int,object>
     */
    private static function compute_impacts(array $targets, object $mappings): array {
        global $DB;

        $out = [];
        foreach ($targets as $t) {
            $map = (array)($mappings->{$t->type} ?? []);
            $keys = array_values(array_unique(array_map('intval', array_keys($map))));
            $keys = array_values(array_filter($keys, function(int $id): bool {
                return $id > 0;
            }));
            if (empty($keys)) {
                $out[] = (object)[
                    'table' => $t->table,
                    'column' => $t->column,
                    'type' => $t->type,
                    'before_count' => 0,
                ];
                continue;
            }
            $cnt = 0;
            try {
                list($insql, $params) = $DB->get_in_or_equal($keys, SQL_PARAMS_NAMED, 'k');
                $cnt = (int)$DB->count_records_select($t->table, "{$t->column} {$insql}", $params);
            } catch (\Throwable $e) {
                $cnt = 0;
            }
            $out[] = (object)[
                'table' => $t->table,
                'column' => $t->column,
                'type' => $t->type,
                'before_count' => $cnt,
            ];
        }
        return $out;
    }

    /**
     * Recompte après mise à jour, et retourne les lignes encore référencées.
     *
     * @param array $targets
     * @param object $mappings
     * @return array{impacts: array<int,object>, still_referenced: array<int,object>}
     */
    private static function compute_impacts_after(array $targets, object $mappings): array {
        global $DB;

        $impacts = [];
        $still = [];
        foreach ($targets as $t) {
            $map = (array)($mappings->{$t->type} ?? []);
            $keys = array_values(array_unique(array_map('intval', array_keys($map))));
            $keys = array_values(array_filter($keys, function(int $id): bool {
                return $id > 0;
            }));
            $cnt = 0;
            if (!empty($keys)) {
                try {
                    list($insql, $params) = $DB->get_in_or_equal($keys, SQL_PARAMS_NAMED, 'k');
                    $cnt = (int)$DB->count_records_select($t->table, "{$t->column} {$insql}", $params);
                } catch (\Throwable $e) {
                    $cnt = 0;
                }
            }
            $impact = (object)[
                'table' => $t->table,
                'column' => $t->column,
                'type' => $t->type,
                'after_count' => $cnt,
            ];
            $impacts[] = $impact;
            if ($cnt > 0) {
                $still[] = $impact;
            }
        }

        return [
            'impacts' => $impacts,
            'still_referenced' => $still,
        ];
    }

    /**
     * Applique les updates (old => ref) sur les targets.
     *
     * @param array $targets
     * @param object $mappings
     * @return array<string,int> map "table.column" => nb de mappings appliqués (best-effort)
     */
    private static function apply_reference_updates(array $targets, object $mappings): array {
        global $DB;

        $updated = [];
        foreach ($targets as $t) {
            $map = (array)($mappings->{$t->type} ?? []);
            if (empty($map)) {
                continue;
            }

            // Colonnes présentes (pour traitements spécifiques).
            $tablecols = null;
            if ($t->table === 'question_references' || $t->table === 'question_set_references') {
                try {
                    $tablecols = $DB->get_columns($t->table);
                } catch (\Throwable $e) {
                    $tablecols = null;
                }
            }

            $sum = 0;
            foreach ($map as $old => $ref) {
                $old = (int)$old;
                $ref = (int)$ref;
                if ($old <= 0 || $ref <= 0 || $old === $ref) {
                    continue;
                }
                try {
                    // Cas spécial Moodle 4.5+ : question_references
                    // - on remap questionbankentryid vers la référence
                    // - on met version/questionversionid à NULL pour pointer vers la dernière version non-brouillon.
                    if (($t->table === 'question_references' || $t->table === 'question_set_references') && $t->column === 'questionbankentryid') {
                        // ⚠️ Important : certains drivers gèrent mal NULL en paramètre sur des colonnes int.
                        // On met donc NULL directement dans le SQL (comportement attendu : latest non-draft).
                        $set = ["questionbankentryid = :ref"];
                        $params = ['ref' => $ref, 'old' => $old];
                        if (is_array($tablecols) && isset($tablecols['version'])) {
                            $set[] = "version = NULL";
                        }
                        if (is_array($tablecols) && isset($tablecols['questionversionid'])) {
                            $set[] = "questionversionid = NULL";
                        }
                        $sql = "UPDATE {" . $t->table . "} SET " . implode(', ', $set) . " WHERE questionbankentryid = :old";
                        $DB->execute($sql, $params);
                    } else {
                        $sql = "UPDATE {" . $t->table . "} SET " . $t->column . " = :ref WHERE " . $t->column . " = :old";
                        $DB->execute($sql, ['ref' => $ref, 'old' => $old]);
                    }
                    $sum++;
                } catch (\Throwable $e) {
                    // Sur les tables d'usages Moodle 4.5, on ne peut pas continuer si l'UPDATE échoue.
                    if ($t->table === 'question_references' || $t->table === 'question_set_references') {
                        throw $e;
                    }
                    // Sinon, laisser le caller décider via post-check (plus fiable).
                }
            }
            $updated[$t->table . '.' . $t->column] = $sum;
        }
        return $updated;
    }

    /**
     * Exécute une fonction sous lock Moodle si disponible.
     *
     * @param callable $fn
     * @return mixed
     */
    private static function with_lock(callable $fn) {
        // Moodle lock API (si disponible).
        if (class_exists('\\core\\lock\\lock_config')) {
            try {
                $factory = \core\lock\lock_config::get_lock_factory('local_question_diagnostic');
                if ($factory) {
                    $lock = $factory->get_lock('question_merge', 60);
                    if ($lock) {
                        try {
                            return $fn();
                        } finally {
                            $lock->release();
                        }
                    }
                }
            } catch (\Throwable $e) {
                // fallback sans lock
            }
        }
        return $fn();
    }
}
