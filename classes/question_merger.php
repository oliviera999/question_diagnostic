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
 * Fusion sûre de questions identiques (v1.15.0).
 *
 * Un groupe de doublons (même catégorie, même empreinte de contenu complet, voir question_analyzer) est réduit
 * à une seule entrée de banque, la RÉFÉRENCE :
 * - la référence est l'entrée visible (dernière version « prête ») qui a le plus de tentatives ;
 * - une entrée n'est fusionnable que si elle n'a aucun usage autre qu'une référence directe dans
 *   question_references (pas de tentative, pas d'usage déclaré par un autre plugin), qu'elle est dans le même
 *   contexte que la référence, que sa catégorie n'est pas un réservoir de tirage aléatoire, et que ses
 *   références figées visent sa dernière version (une version plus ancienne peut avoir un autre contenu) ;
 * - seules les lignes de question_references sont remappées vers la référence. Une référence figée reste figée,
 *   sur la dernière version de la référence ; une référence « toujours la dernière » le reste ;
 * - les entrées fusionnées sont ensuite supprimées en entier par question_deleter (export XML préalable,
 *   vérification) ; tout refus annule la fusion du groupe.
 *
 * Aucune autre table n'est modifiée. L'historique des tentatives n'est jamais touché.
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_question_diagnostic;

use local_question_diagnostic\local\question_deleter;
use local_question_diagnostic\local\usage_checker;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/question_analyzer.php');
require_once(__DIR__ . '/audit_logger.php');

/**
 * Fusion de questions identiques.
 */
class question_merger {

    /** Seule table remappée par la fusion. */
    const TARGET_TABLE = 'question_references';

    /**
     * Construit un plan de fusion (aperçu, sans écriture).
     *
     * @param int $representativeid question représentative du groupe
     * @param array $options inutilisé (compatibilité)
     * @return object plan
     */
    public static function build_merge_plan(int $representativeid, array $options = []): object {
        global $DB;

        $plan = (object)[
            'representative_id' => (int)$representativeid,
            'group' => null,
            'questions' => [],
            'reference_questionid' => 0,
            'reference_entryid' => 0,
            'reference_latestversion' => 0,
            'mergeable_questionids' => [],
            'skipped' => [
                'has_attempts' => [],
                'other_usage' => [],
                'other_context' => [],
                'random_pool' => [],
                'pinned_old_version' => [],
                'not_found' => [],
            ],
            'mappings' => (object)['questionbankentryid' => []],
            'impacts' => [],
            'warnings' => [],
            'errors' => [],
        ];

        if ($plan->representative_id <= 0) {
            $plan->errors[] = get_string('merge_error_invalid_group', 'local_question_diagnostic');
            return $plan;
        }

        // 1) Groupe : même catégorie, contenu complet identique (une question représentative par entrée).
        $questionids = question_analyzer::get_duplicate_group_question_ids_by_representative_id($plan->representative_id);
        $questionids = array_values(array_unique(array_filter(array_map('intval', (array)$questionids))));
        if (count($questionids) < 2) {
            $plan->errors[] = get_string('merge_error_no_duplicates', 'local_question_diagnostic');
            return $plan;
        }

        // 2) Une vue par entrée.
        [$insql, $params] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
        $rows = $DB->get_records_sql("
            SELECT q.id, q.name, q.qtype, q.questiontextformat,
                   qv.id AS versionid, qv.version, qv.status, qv.questionbankentryid AS entryid,
                   qc.id AS categoryid, qc.name AS categoryname, qc.contextid
              FROM {question} q
              JOIN {question_versions} qv ON qv.questionid = q.id
              JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
              JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
             WHERE q.id $insql", $params);

        $entryids = [];
        foreach ($questionids as $qid) {
            if (!isset($rows[$qid])) {
                $plan->skipped['not_found'][] = $qid;
                continue;
            }
            $entryids[(int)$rows[$qid]->entryid] = $qid;
        }
        if (count($entryids) < 2) {
            $plan->errors[] = get_string('merge_error_no_duplicates', 'local_question_diagnostic');
            return $plan;
        }

        $attempts = self::get_attempt_counts_by_entry_ids(array_keys($entryids));
        $refcounts = self::get_reference_counts_by_entry_ids(array_keys($entryids));
        $latest = self::get_latest_versions_by_entry_ids(array_keys($entryids));

        foreach ($entryids as $entryid => $qid) {
            $r = $rows[$qid];
            $plan->questions[$qid] = (object)[
                'id' => (int)$qid,
                'entryid' => (int)$entryid,
                'name' => (string)$r->name,
                'qtype' => (string)$r->qtype,
                'questiontextformat' => (int)$r->questiontextformat,
                'status' => (string)$r->status,
                'attempt_count' => (int)($attempts[$entryid] ?? 0),
                'quiz_count' => (int)($refcounts[$entryid] ?? 0),
                'latestversion' => (int)($latest[$entryid] ?? $r->version),
                'context' => (object)[
                    'categoryid' => (int)$r->categoryid,
                    'categoryname' => (string)$r->categoryname,
                    'contextid' => (int)$r->contextid,
                ],
                'version' => (object)[
                    'id' => (int)$r->versionid,
                    'questionid' => (int)$qid,
                    'questionbankentryid' => (int)$entryid,
                    'version' => (int)$r->version,
                ],
            ];
        }

        // 3) Référence : entrée visible avec le plus de tentatives (à égalité : la plus ancienne entrée).
        $best = null;
        foreach ($plan->questions as $info) {
            if ($info->status !== 'ready') {
                continue;
            }
            if ($best === null || $info->attempt_count > $best->attempt_count
                    || ($info->attempt_count === $best->attempt_count && $info->entryid < $best->entryid)) {
                $best = $info;
            }
        }
        if ($best === null) {
            $plan->errors[] = get_string('merge_error_no_visible_reference', 'local_question_diagnostic');
            return $plan;
        }
        $plan->reference_questionid = $best->id;
        $plan->reference_entryid = $best->entryid;
        $plan->reference_latestversion = $best->latestversion;

        // 4) Entrées fusionnables.
        $candidates = [];
        foreach ($plan->questions as $qid => $info) {
            if ($qid === $best->id) {
                continue;
            }
            if ($info->attempt_count > 0) {
                $plan->skipped['has_attempts'][] = $qid;
            } else if ($info->context->contextid !== $best->context->contextid) {
                $plan->skipped['other_context'][] = $qid;
            } else if (self::has_pinned_old_version($info->entryid, $info->latestversion)) {
                $plan->skipped['pinned_old_version'][] = $qid;
            } else {
                $candidates[] = $qid;
            }
        }
        if ($candidates) {
            $otherusage = usage_checker::entries_used_outside_references($candidates);
            $inpool = usage_checker::questions_in_random_pool($candidates);
            foreach ($candidates as $qid) {
                if (!empty($otherusage[$qid]) || !array_key_exists($qid, $otherusage)) {
                    $plan->skipped['other_usage'][] = $qid;
                } else if (!empty($inpool[$qid]) || !array_key_exists($qid, $inpool)) {
                    $plan->skipped['random_pool'][] = $qid;
                } else {
                    $plan->mergeable_questionids[] = $qid;
                    $plan->mappings->questionbankentryid[$plan->questions[$qid]->entryid] = $best->entryid;
                }
            }
        }
        if (!$plan->mergeable_questionids) {
            $plan->warnings[] = get_string('merge_warning_nothing_mergeable', 'local_question_diagnostic');
        }

        // 5) Impact : références qui seront remappées.
        $before = 0;
        if ($plan->mappings->questionbankentryid) {
            [$einsql, $eparams] = $DB->get_in_or_equal(array_keys($plan->mappings->questionbankentryid));
            $before = $DB->count_records_select(self::TARGET_TABLE, "questionbankentryid $einsql", $eparams);
        }
        $plan->impacts = [(object)[
            'table' => self::TARGET_TABLE,
            'column' => 'questionbankentryid',
            'type' => 'questionbankentryid',
            'before_count' => $before,
        ]];

        $plan->group = (object)[
            'qtype' => $best->qtype,
            'name' => $best->name,
            'size' => count($plan->questions),
        ];
        return $plan;
    }

    /**
     * Applique un plan de fusion. Le plan est recalculé depuis la base : on ne fait pas confiance au plan reçu.
     *
     * @param object $plan plan issu de build_merge_plan()
     * @param array $options inutilisé (compatibilité)
     * @return object {success: bool, message: string, details: array}
     */
    public static function apply_merge_plan(object $plan, array $options = []): object {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/question/engine/bank.php');

        $result = (object)[
            'success' => false,
            'message' => '',
            'details' => ['updated' => [], 'deleted' => [], 'skipped' => [], 'warnings' => [], 'exportfile' => null],
        ];
        if (empty($plan->representative_id)) {
            $result->message = get_string('merge_error_invalid_group', 'local_question_diagnostic');
            return $result;
        }

        $lockfactory = \core\lock\lock_config::get_lock_factory('local_question_diagnostic');
        $lock = $lockfactory->get_lock('question_merge', 60);
        if (!$lock) {
            // Sans verrou, on ne fusionne pas.
            $result->message = get_string('merge_error_locked', 'local_question_diagnostic');
            return $result;
        }

        try {
            $fresh = self::build_merge_plan((int)$plan->representative_id);
            $result->details['skipped'] = $fresh->skipped;
            $result->details['warnings'] = $fresh->warnings;
            if ($fresh->errors) {
                $result->message = implode(' ', $fresh->errors);
                return $result;
            }
            if (!$fresh->mergeable_questionids) {
                $result->success = true;
                $result->message = get_string('merge_nothing_to_do', 'local_question_diagnostic');
                return $result;
            }

            $transaction = $DB->start_delegated_transaction();
            try {
                // 1) Remapper les références.
                $result->details['updated'] = [
                    self::TARGET_TABLE . '.questionbankentryid' => self::remap_references($fresh),
                ];

                // 2) Contrôle : plus aucune référence vers les entrées fusionnées.
                [$einsql, $eparams] = $DB->get_in_or_equal(array_keys($fresh->mappings->questionbankentryid));
                $left = $DB->count_records_select(self::TARGET_TABLE, "questionbankentryid $einsql", $eparams);
                if ($left > 0) {
                    throw new \moodle_exception('merge_error_postcheck', 'local_question_diagnostic', '', $left);
                }

                // 3) Supprimer les entrées fusionnées (contrôle d'usage, export XML, vérification).
                $deletion = question_deleter::delete_entries($fresh->mergeable_questionids, 'merge-duplicates');
                $result->details['exportfile'] = $deletion->exportfile;
                if ($deletion->skipped) {
                    $labels = [];
                    foreach ($deletion->skipped as $qid => $skip) {
                        $labels[] = $qid . ' (' . question_deleter::skip_label($skip) . ')';
                    }
                    throw new \moodle_exception('merge_error_delete', 'local_question_diagnostic', '',
                        implode(', ', $labels));
                }
                $result->details['deleted'] = $deletion->deleted;

                $transaction->allow_commit();
            } catch (\Throwable $e) {
                try {
                    $transaction->rollback($e);
                } catch (\Throwable $rethrown) {
                    // Rollback() relance l'exception : elle est rapportée ci-dessous, jamais rejouée.
                    unset($rethrown);
                }
                usage_checker::reset_caches();
                $result->details['deleted'] = [];
                $result->message = get_string('merge_error', 'local_question_diagnostic', $e->getMessage());
                return $result;
            }

            \question_bank::notify_question_edited($fresh->reference_questionid);
            question_analyzer::purge_all_caches();
            audit_logger::log_questions_merge($fresh->reference_questionid, $fresh->mergeable_questionids, [
                'updated_targets' => $result->details['updated'],
                'exportfile' => $result->details['exportfile'] ? basename($result->details['exportfile']) : null,
            ]);
            $result->success = true;
            $result->message = get_string('merge_success', 'local_question_diagnostic');
            return $result;
        } finally {
            $lock->release();
        }
    }

    /**
     * Remappe les lignes de question_references des entrées fusionnées vers la référence.
     *
     * Une référence figée reste figée, sur la dernière version de la référence. Une ligne qui ferait doublon
     * avec une référence existante vers la référence (même usage) est supprimée.
     *
     * @param object $plan plan recalculé
     * @return int nombre de lignes traitées
     */
    private static function remap_references(object $plan): int {
        global $DB;
        $count = 0;
        foreach ($plan->mappings->questionbankentryid as $oldentryid => $refentryid) {
            $rows = $DB->get_records(self::TARGET_TABLE, ['questionbankentryid' => (int)$oldentryid], 'id ASC');
            foreach ($rows as $row) {
                $duplicate = $DB->record_exists(self::TARGET_TABLE, [
                    'questionbankentryid' => (int)$refentryid,
                    'usingcontextid' => (int)$row->usingcontextid,
                    'component' => (string)$row->component,
                    'questionarea' => (string)$row->questionarea,
                    'itemid' => (int)$row->itemid,
                ]);
                if ($duplicate) {
                    $DB->delete_records(self::TARGET_TABLE, ['id' => (int)$row->id]);
                } else {
                    $DB->update_record(self::TARGET_TABLE, (object)[
                        'id' => (int)$row->id,
                        'questionbankentryid' => (int)$refentryid,
                        'version' => $row->version === null ? null : (int)$plan->reference_latestversion,
                    ]);
                }
                $count++;
            }
        }
        return $count;
    }

    /**
     * L'entrée a-t-elle une référence figée sur une version autre que sa dernière ?
     *
     * @param int $entryid
     * @param int $latestversion
     * @return bool
     */
    private static function has_pinned_old_version(int $entryid, int $latestversion): bool {
        global $DB;
        return $DB->record_exists_select(self::TARGET_TABLE,
            'questionbankentryid = ? AND version IS NOT NULL AND version <> ?', [$entryid, $latestversion]);
    }

    /**
     * Nombre de tentatives par entrée, toutes versions confondues.
     *
     * @param int[] $entryids
     * @return array<int,int>
     */
    private static function get_attempt_counts_by_entry_ids(array $entryids): array {
        global $DB;
        if (!$entryids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($entryids, SQL_PARAMS_NAMED);
        return array_map('intval', $DB->get_records_sql_menu("
            SELECT qv.questionbankentryid, COUNT(qa.id)
              FROM {question_versions} qv
              JOIN {question_attempts} qa ON qa.questionid = qv.questionid
             WHERE qv.questionbankentryid $insql
          GROUP BY qv.questionbankentryid", $params));
    }

    /**
     * Nombre de références directes (question_references) par entrée.
     *
     * @param int[] $entryids
     * @return array<int,int>
     */
    private static function get_reference_counts_by_entry_ids(array $entryids): array {
        global $DB;
        if (!$entryids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($entryids, SQL_PARAMS_NAMED);
        return array_map('intval', $DB->get_records_sql_menu("
            SELECT questionbankentryid, COUNT(id)
              FROM {question_references}
             WHERE questionbankentryid $insql
          GROUP BY questionbankentryid", $params));
    }

    /**
     * Dernière version (numéro) de chaque entrée, brouillons compris.
     *
     * @param int[] $entryids
     * @return array<int,int>
     */
    private static function get_latest_versions_by_entry_ids(array $entryids): array {
        global $DB;
        if (!$entryids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($entryids, SQL_PARAMS_NAMED);
        return array_map('intval', $DB->get_records_sql_menu("
            SELECT questionbankentryid, MAX(version)
              FROM {question_versions}
             WHERE questionbankentryid $insql
          GROUP BY questionbankentryid", $params));
    }
}
