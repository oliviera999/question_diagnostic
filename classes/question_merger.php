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

        // 2) IMPORTANT Moodle 4.5 : travailler au niveau des "entries" (question_bank_entries),
        // pas au niveau des versions (question.id peut représenter une version).
        //
        // On regroupe donc les questionids détectés par leur questionbankentryid, puis on ne garde
        // qu'UNE "question représentative" (dernière version non-brouillon) par entry.
        $versionsbyqid = self::get_question_versions_by_question_ids($questionids);
        $qidsbyentry = [];
        foreach ($questionids as $qid) {
            $v = $versionsbyqid[(int)$qid] ?? null;
            $qbeid = (int)($v->questionbankentryid ?? 0);
            if ($qbeid <= 0) {
                continue;
            }
            if (!isset($qidsbyentry[$qbeid])) {
                $qidsbyentry[$qbeid] = [];
            }
            $qidsbyentry[$qbeid][] = (int)$qid;
        }

        $entryids = array_keys($qidsbyentry);
        if (count($entryids) < 2) {
            $entryid = !empty($entryids) ? (int)$entryids[0] : 0;
            $plan->errors[] = 'Ce groupe correspond à plusieurs versions d’une même question (questionbankentryid=' . $entryid . '). '
                . 'La fusion de doublons s’applique uniquement entre entrées différentes (question_bank_entries).';
            return $plan;
        }

        // Pour chaque entry, prendre la dernière version (non-draft si possible) afin de représenter l’entry.
        $repbyentry = self::get_latest_question_versions_by_entry_ids($entryids);
        $repqids = [];
        foreach ($repbyentry as $entryid => $r) {
            if (!empty($r->questionid)) {
                $repqids[] = (int)$r->questionid;
            }
        }
        $repqids = array_values(array_unique(array_filter($repqids, function(int $id): bool { return $id > 0; })));
        if (count($repqids) < 2) {
            $plan->errors[] = 'Impossible de déterminer une question représentative par entrée (question_versions).';
            return $plan;
        }

        // Charger la question "représentative" de chaque entry + usage.
        list($insql, $inparams) = $DB->get_in_or_equal($repqids, SQL_PARAMS_NAMED, 'qid');
        $questions = $DB->get_records_select(
            'question',
            "id {$insql}",
            $inparams,
            '',
            'id,name,qtype,questiontext,questiontextformat,timecreated,timemodified'
        );
        $usage = question_analyzer::get_questions_usage_by_ids($repqids);
        $ctxmap = self::get_questions_context_info_by_ids($repqids);

        // Tentatives au niveau ENTRY : si une ancienne version a des tentatives, l'entrée est protégée.
        $attemptsbyentry = self::get_attempt_counts_by_entry_ids($entryids);

        // 3) Construire une vue par question représentative (1 par entry).
        foreach ($repqids as $qid) {
            $q = $questions[$qid] ?? null;
            if (!$q) {
                $plan->skipped['not_found'][] = (int)$qid;
                continue;
            }
            $u = $usage[$qid] ?? ['quiz_count' => 0, 'attempt_count' => 0, 'is_used' => false, 'quiz_list' => []];
            $c = $ctxmap[$qid] ?? null;

            // entryid depuis repbyentry (par questionid).
            $entryid = 0;
            $qv = null;
            foreach ($repbyentry as $eid => $rep) {
                if ((int)($rep->questionid ?? 0) === (int)$qid) {
                    $entryid = (int)$eid;
                    $qv = $rep;
                    break;
                }
            }

            $plan->questions[$qid] = (object)[
                'id' => (int)$qid,
                'entryid' => (int)$entryid, // question_bank_entries.id (clé stable Moodle 4.5)
                'name' => (string)($q->name ?? ''),
                'qtype' => (string)($q->qtype ?? ''),
                'questiontextformat' => (int)($q->questiontextformat ?? 0),
                'attempt_count' => (int)($attemptsbyentry[$entryid] ?? 0),
                'quiz_count' => (int)($u['quiz_count'] ?? 0),
                'context' => $c,
                'version' => $qv, // {id(questionversionid), questionid, questionbankentryid}
            ];
        }

        if (count($plan->questions) < 2) {
            $plan->errors[] = 'Impossible de charger suffisamment de questions représentatives dans ce groupe.';
            return $plan;
        }

        // 4) Choisir la RÉFÉRENCE au niveau ENTRY : max attempt_count, tie-breaker min entryid.
        $bestentry = 0;
        $bestattempts = -1;
        foreach ($plan->questions as $qid => $info) {
            $attempts = (int)($info->attempt_count ?? 0);
            $entryid = (int)($info->entryid ?? 0);
            if ($attempts > $bestattempts || ($attempts === $bestattempts && ($bestentry === 0 || $entryid < $bestentry))) {
                $bestattempts = $attempts;
                $bestentry = $entryid;
            }
        }
        foreach ($plan->questions as $qid => $info) {
            if ((int)($info->entryid ?? 0) === (int)$bestentry) {
                $plan->reference_questionid = (int)$qid;
                break;
            }
        }

        if ($plan->reference_questionid <= 0) {
            $plan->errors[] = 'Impossible de déterminer une question de référence.';
            return $plan;
        }

        // 5) Déterminer les doublons fusionnables : au niveau ENTRY uniquement (attempt_count=0).
        // Les questions déjà utilisées dans des quiz mais sans tentatives restent fusionnables,
        // via remap question_references (+ quiz_slots si présent).
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

        // 6) Construire les mappings au niveau ENTRY (questionbankentryid).
        // On garde aussi un mapping questionid pour compat/anciennes tables (ex: quiz_slots.questionid).
        // IMPORTANT : certains usages peuvent pointer une ancienne version (question.id) de l’entry.
        // On mappe donc TOUS les questionids (toutes versions) d’une entry fusionnée vers la questionid
        // représentative de l’entry de référence.
        $refquestionid = (int)$plan->reference_questionid;
        $mergeableentryids = [];
        foreach ($plan->mergeable_questionids as $oldqid) {
            $oldqid = (int)$oldqid;
            $oldentryid = (int)($plan->questions[$oldqid]->entryid ?? 0);
            if ($oldentryid > 0) {
                $mergeableentryids[] = $oldentryid;
            }
        }
        $mergeableentryids = array_values(array_unique(array_filter($mergeableentryids, function(int $id): bool {
            return $id > 0;
        })));
        $qidsbyentry = self::get_question_ids_by_entry_ids($mergeableentryids);
        foreach ($qidsbyentry as $entryid => $qids) {
            foreach ((array)$qids as $qid) {
                $qid = (int)$qid;
                if ($qid > 0 && $qid !== $refquestionid) {
                    $plan->mappings->questionid[$qid] = $refquestionid;
                }
            }
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
            if ($oldqbeid > 0 && $refqbeid > 0 && $oldqbeid !== $refqbeid) {
                $plan->mappings->questionbankentryid[$oldqbeid] = $refqbeid;
            }
            if ($oldqvid > 0 && $refqvid > 0 && $oldqvid !== $refqvid) {
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

        list($insql, $params) = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED, 'qid');
        $sql = "SELECT qv.questionid,
                       qc.id AS categoryid,
                       qc.name AS categoryname,
                       qc.contextid,
                       ctx.contextlevel
                  FROM {question_versions} qv
                  INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                  INNER JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                  LEFT JOIN {context} ctx ON ctx.id = qc.contextid
                 WHERE qv.questionid {$insql}";

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

        list($insql, $params) = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED, 'qid');
        // Un questionid correspond à une ligne question_versions (version spécifique).
        $sql = "SELECT qv.id, qv.questionid, qv.questionbankentryid
                  FROM {question_versions} qv
                 WHERE qv.questionid {$insql}";
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
     * Récupère, pour chaque entry, la question/version la plus récente (non-draft si possible).
     *
     * @param int[] $entryids question_bank_entries.id
     * @return array<int,object> entryid => {id(questionversionid), questionid, questionbankentryid}
     */
    private static function get_latest_question_versions_by_entry_ids(array $entryids): array {
        global $DB;

        $entryids = array_values(array_unique(array_map('intval', (array)$entryids)));
        $entryids = array_values(array_filter($entryids, function(int $id): bool { return $id > 0; }));
        if (empty($entryids)) {
            return [];
        }

        list($insqlsub, $paramssub) = $DB->get_in_or_equal($entryids, SQL_PARAMS_NAMED, 'e');
        list($insqlmain, $paramsmain) = $DB->get_in_or_equal($entryids, SQL_PARAMS_NAMED, 'e2');
        $params = array_merge($paramssub, $paramsmain);

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
                        SELECT v.questionbankentryid, MAX(v.version) AS maxversion
                          FROM {question_versions} v
                         WHERE v.questionbankentryid {$insqlsub} {$statusfilter}
                      GROUP BY v.questionbankentryid
                  ) mv ON mv.questionbankentryid = qv.questionbankentryid AND mv.maxversion = qv.version
                 WHERE qv.questionbankentryid {$insqlmain}";

        $records = $DB->get_records_sql($sql, $params);
        $out = [];
        foreach ($records as $r) {
            $entryid = (int)($r->questionbankentryid ?? 0);
            if ($entryid <= 0) {
                continue;
            }
            $out[$entryid] = (object)[
                'id' => (int)($r->id ?? 0),
                'questionid' => (int)($r->questionid ?? 0),
                'questionbankentryid' => $entryid,
            ];
        }
        return $out;
    }

    /**
     * Compte les tentatives au niveau ENTRY : si une version a des tentatives, l'entry est protégée.
     *
     * @param int[] $entryids question_bank_entries.id
     * @return array<int,int> entryid => attempt_count
     */
    private static function get_attempt_counts_by_entry_ids(array $entryids): array {
        global $DB;

        $entryids = array_values(array_unique(array_map('intval', (array)$entryids)));
        $entryids = array_values(array_filter($entryids, function(int $id): bool { return $id > 0; }));
        if (empty($entryids)) {
            return [];
        }

        list($insql, $params) = $DB->get_in_or_equal($entryids, SQL_PARAMS_NAMED, 'e');
        $sql = "SELECT qv.questionbankentryid AS entryid, COUNT(DISTINCT qa.id) AS attempt_count
                  FROM {question_attempts} qa
                  INNER JOIN {question_versions} qv ON qv.questionid = qa.questionid
                 WHERE qv.questionbankentryid {$insql}
              GROUP BY qv.questionbankentryid";
        $records = $DB->get_records_sql($sql, $params);
        $out = [];
        foreach ($records as $r) {
            $out[(int)$r->entryid] = (int)($r->attempt_count ?? 0);
        }
        return $out;
    }

    /**
     * Liste tous les questionids (toutes versions) d’un ensemble d’entries.
     *
     * @param int[] $entryids question_bank_entries.id
     * @return array<int,int[]> entryid => [questionid...]
     */
    private static function get_question_ids_by_entry_ids(array $entryids): array {
        global $DB;

        $entryids = array_values(array_unique(array_map('intval', (array)$entryids)));
        $entryids = array_values(array_filter($entryids, function(int $id): bool { return $id > 0; }));
        if (empty($entryids)) {
            return [];
        }

        list($insql, $params) = $DB->get_in_or_equal($entryids, SQL_PARAMS_NAMED, 'e');
        $sql = "SELECT qv.questionbankentryid AS entryid, qv.questionid
                  FROM {question_versions} qv
                 WHERE qv.questionbankentryid {$insql}";
        $records = $DB->get_records_sql($sql, $params);
        $out = [];
        foreach ($records as $r) {
            $entryid = (int)($r->entryid ?? 0);
            $qid = (int)($r->questionid ?? 0);
            if ($entryid <= 0 || $qid <= 0) {
                continue;
            }
            if (!isset($out[$entryid])) {
                $out[$entryid] = [];
            }
            $out[$entryid][] = $qid;
        }
        foreach ($out as $entryid => $qids) {
            $out[$entryid] = array_values(array_unique(array_filter(array_map('intval', (array)$qids), function(int $id): bool {
                return $id > 0;
            })));
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
            // Exclure les mappings identitaires (old == ref), sinon le post-check comptera les références légitimes.
            $keys = [];
            foreach ($map as $old => $ref) {
                $old = (int)$old;
                $ref = (int)$ref;
                if ($old > 0 && $ref > 0 && $old !== $ref) {
                    $keys[] = $old;
                }
            }
            $keys = array_values(array_unique($keys));
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
            // Exclure les mappings identitaires (old == ref) : sinon on “voit” les références de la référence elle-même.
            $keys = [];
            foreach ($map as $old => $ref) {
                $old = (int)$old;
                $ref = (int)$ref;
                if ($old > 0 && $ref > 0 && $old !== $ref) {
                    $keys[] = $old;
                }
            }
            $keys = array_values(array_unique($keys));
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
                        // IMPORTANT (Moodle 4.5): il peut déjà exister une référence identique pointant vers $ref.
                        // Dans ce cas, l'UPDATE pur ne suffit pas (ou peut être bloqué par une contrainte unique),
                        // et on se retrouve avec des refs vers les anciens IDs (post-check échoue).
                        //
                        // On fait donc un traitement sûr, ligne par ligne :
                        // - si une ligne équivalente existe déjà pour $ref → supprimer la ligne $old
                        // - sinon → mettre à jour la ligne $old vers $ref (+ version/questionversionid = NULL si colonnes présentes)
                        $hasusingctx = is_array($tablecols) && isset($tablecols['usingcontextid']);
                        $hascomponent = is_array($tablecols) && isset($tablecols['component']);
                        $hasquestionarea = is_array($tablecols) && isset($tablecols['questionarea']);
                        $hasitemid = is_array($tablecols) && isset($tablecols['itemid']);

                        // Champs minimaux pour pouvoir dédupliquer proprement.
                        $fields = ['id', 'questionbankentryid'];
                        if ($hasusingctx) {
                            $fields[] = 'usingcontextid';
                        }
                        if ($hascomponent) {
                            $fields[] = 'component';
                        }
                        if ($hasquestionarea) {
                            $fields[] = 'questionarea';
                        }
                        if ($hasitemid) {
                            $fields[] = 'itemid';
                        }
                        if (is_array($tablecols) && isset($tablecols['version'])) {
                            $fields[] = 'version';
                        }
                        if (is_array($tablecols) && isset($tablecols['questionversionid'])) {
                            $fields[] = 'questionversionid';
                        }

                        $rows = $DB->get_records($t->table, ['questionbankentryid' => $old], 'id ASC', implode(',', $fields));
                        foreach ($rows as $row) {
                            $existsparams = ['questionbankentryid' => $ref];
                            if ($hasusingctx) {
                                $existsparams['usingcontextid'] = (int)($row->usingcontextid ?? 0);
                            }
                            if ($hascomponent) {
                                $existsparams['component'] = (string)($row->component ?? '');
                            }
                            if ($hasquestionarea) {
                                $existsparams['questionarea'] = (string)($row->questionarea ?? '');
                            }
                            if ($hasitemid) {
                                $existsparams['itemid'] = (int)($row->itemid ?? 0);
                            }

                            // Si on n'a pas les champs de déduplication, fallback : update global.
                            $can_dedupe = $hasusingctx && $hascomponent && $hasquestionarea && $hasitemid;
                            if ($can_dedupe && $DB->record_exists($t->table, $existsparams)) {
                                // La référence cible existe déjà → supprimer la ligne redondante.
                                $DB->delete_records($t->table, ['id' => (int)$row->id]);
                                continue;
                            }

                            // Mettre à jour la ligne.
                            $upd = new \stdClass();
                            $upd->id = (int)$row->id;
                            $upd->questionbankentryid = $ref;
                            if (is_array($tablecols) && isset($tablecols['version'])) {
                                $upd->version = null;
                            }
                            if (is_array($tablecols) && isset($tablecols['questionversionid'])) {
                                $upd->questionversionid = null;
                            }
                            $DB->update_record($t->table, $upd);
                        }
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
