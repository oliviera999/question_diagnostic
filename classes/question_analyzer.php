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
 * Analyseur de questions - Statistiques et nettoyage
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_analyzer {

    /**
     * Cache des colonnes de la table {question} (runtime).
     *
     * @var array|null
     */
    private static $questiontablecolumns = null;

    /**
     * Retourne les colonnes de la table {question} (avec cache) pour compat Moodle 4.5+.
     *
     * @return array
     */
    private static function get_question_table_columns(): array {
        global $DB;

        if (self::$questiontablecolumns !== null) {
            return self::$questiontablecolumns;
        }

        try {
            self::$questiontablecolumns = (array)$DB->get_columns('question');
        } catch (\Throwable $e) {
            // En cas d'erreur, on force un fallback "safe" (pas de colonnes).
            self::$questiontablecolumns = [];
        }

        return self::$questiontablecolumns;
    }

    /**
     * Détermine si la détection "doublons certains" (qtype + questiontext + format) est disponible.
     *
     * @return bool
     */
    private static function can_use_certain_duplicates_definition(): bool {
        $cols = self::get_question_table_columns();
        return isset($cols['questiontext']) && isset($cols['questiontextformat']) && isset($cols['qtype']);
    }

    /**
     * Moodle 4.5+ : savoir si question_versions.status existe.
     *
     * @return bool
     */
    private static function question_versions_has_status(): bool {
        static $has = null;
        if ($has !== null) {
            return (bool)$has;
        }
        global $DB;
        try {
            $cols = $DB->get_columns('question_versions');
            $has = is_array($cols) && isset($cols['status']);
        } catch (\Throwable $e) {
            $has = false;
        }
        return (bool)$has;
    }

    /**
     * Récupère l'entryid (question_bank_entries.id) d'une questionid.
     *
     * @param int $questionid
     * @return int entryid (0 si inconnu)
     */
    private static function get_entryid_for_questionid(int $questionid): int {
        global $DB;
        $questionid = (int)$questionid;
        if ($questionid <= 0) {
            return 0;
        }
        try {
            $entryid = (int)$DB->get_field('question_versions', 'questionbankentryid', ['questionid' => $questionid], IGNORE_MISSING);
            return $entryid > 0 ? $entryid : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Récupère la question représentative (dernière version non-draft si possible) d'une entry.
     * Permet d'exclure les doublons de versions partout dans le plugin.
     *
     * @param int $questionid
     * @return int questionid représentative (fallback = questionid)
     */
    private static function get_representative_questionid_for_questionid(int $questionid): int {
        global $DB;

        $questionid = (int)$questionid;
        if ($questionid <= 0) {
            return 0;
        }

        $entryid = self::get_entryid_for_questionid($questionid);
        if ($entryid <= 0) {
            return $questionid;
        }

        $where = 'questionbankentryid = :entryid';
        $params = ['entryid' => (int)$entryid];
        if (self::question_versions_has_status()) {
            $where .= " AND status <> 'draft'";
        }

        try {
            $repqid = (int)$DB->get_field_select('question_versions', 'questionid', $where . ' ORDER BY version DESC', $params, IGNORE_MISSING);
            return $repqid > 0 ? $repqid : $questionid;
        } catch (\Throwable $e) {
            return $questionid;
        }
    }

    /**
     * Récupère la liste des questions représentatives (1 par entry).
     *
     * @param int $limit
     * @return array<int,object> questionid => question record
     */
    private static function get_representative_questions(int $limit = 0): array {
        global $DB;

        $statusfilter = '';
        if (self::question_versions_has_status()) {
            $statusfilter = " AND v.status <> 'draft' ";
        }

        $sql = "SELECT q.id, q.name, q.qtype, q.questiontext, q.questiontextformat
                  FROM {question_versions} qv
                  INNER JOIN (
                        SELECT v.questionbankentryid, MAX(v.version) AS maxversion
                          FROM {question_versions} v
                         WHERE v.questionbankentryid IS NOT NULL {$statusfilter}
                      GROUP BY v.questionbankentryid
                  ) mv ON mv.questionbankentryid = qv.questionbankentryid AND mv.maxversion = qv.version
                  INNER JOIN {question} q ON q.id = qv.questionid
              ORDER BY qv.questionbankentryid ASC, q.id ASC";

        try {
            if ($limit > 0) {
                return $DB->get_records_sql($sql, [], 0, (int)$limit);
            }
            return $DB->get_records_sql($sql);
        } catch (\Throwable $e) {
            debugging('Error in get_representative_questions: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
    }

    /**
     * Signature interne d'un groupe de doublons "certains" (debug et regroupement en mémoire).
     *
     * On n'inclut PAS le titre : l'objectif est d'éviter les faux positifs "même titre, contenu différent".
     *
     * @param object $q
     * @return string
     */
    private static function build_certain_duplicate_signature($q): string {
        $qtype = (string)($q->qtype ?? '');
        $fmt = (int)($q->questiontextformat ?? 0);
        $qtext = (string)($q->questiontext ?? '');

        // Hash court pour éviter de stocker/afficher un texte potentiellement très long.
        $texthash = sha1($qtext);

        return $qtype . '|||' . $fmt . '|||' . $texthash;
    }

    /**
     * Retourne les IDs de toutes les questions du même groupe "doublon certain" que la question donnée.
     *
     * Fallback (anciens sites / colonnes manquantes) : name + qtype.
     *
     * @param object $question
     * @return int[]
     */
    public static function get_duplicate_group_question_ids_for_question($question): array {
        global $DB;

        if (!is_object($question) || empty($question->id)) {
            return [];
        }

        // IMPORTANT Moodle 4.5+ :
        // - question.id peut être une VERSION.
        // - On ne doit jamais considérer 2 versions d'une même entry comme des "doublons".
        //
        // Donc on travaille uniquement sur 1 question représentative par entry (dernière version).
        $repqid = self::get_representative_questionid_for_questionid((int)$question->id);
        $rep = $DB->get_record('question', ['id' => (int)$repqid], 'id,name,qtype,questiontext,questiontextformat', IGNORE_MISSING);
        if (!$rep) {
            return [];
        }

        $statusfilter = '';
        if (self::question_versions_has_status()) {
            $statusfilter = " AND v.status <> 'draft' ";
        }

        // 🔒 v1.14.3 : Définition "doublons certains" STRICTE : qtype + questiontextformat + questiontext strictement identiques.
        // Cette définition est la plus stricte possible pour éviter les faux positifs.
        if (self::can_use_certain_duplicates_definition()) {
            $qtext = $DB->sql_compare_text('q.questiontext');
            $sql = "SELECT q.id
                      FROM {question_versions} qv
                      INNER JOIN (
                            SELECT v.questionbankentryid, MAX(v.version) AS maxversion
                              FROM {question_versions} v
                             WHERE v.questionbankentryid IS NOT NULL {$statusfilter}
                          GROUP BY v.questionbankentryid
                      ) mv ON mv.questionbankentryid = qv.questionbankentryid AND mv.maxversion = qv.version
                      INNER JOIN {question} q ON q.id = qv.questionid
                     WHERE q.qtype = :qtype
                       AND q.questiontextformat = :fmt
                       AND {$qtext} = " . $DB->sql_compare_text(':qtext') . "
                  ORDER BY q.id ASC";

            try {
                $ids = $DB->get_fieldset_sql($sql, [
                    'qtype' => (string)($rep->qtype ?? ''),
                    'fmt' => (int)($rep->questiontextformat ?? 0),
                    'qtext' => (string)($rep->questiontext ?? ''),
                ]);
                $ids = array_values(array_unique(array_map('intval', $ids)));
                
                // 🔒 v1.14.3 : Vérification croisée STRICTE pour éviter les faux positifs
                // Vérifier que toutes les questions trouvées sont vraiment identiques
                if (count($ids) >= 2) {
                    $verified_ids = self::verify_duplicates_strict($ids, $rep);
                    if (count($verified_ids) >= 2) {
                        debugging('get_duplicate_group_question_ids_for_question: Found ' . count($verified_ids) . ' verified duplicates (strict check)', DEBUG_DEVELOPER);
                        return $verified_ids;
                    } else {
                        debugging('get_duplicate_group_question_ids_for_question: SQL found ' . count($ids) . ' but strict verification reduced to ' . count($verified_ids) . ' - NOT returning as duplicates', DEBUG_DEVELOPER);
                        return [];
                    }
                }
                return [];
            } catch (\Throwable $e) {
                debugging('Error in get_duplicate_group_question_ids_for_question (certain/entry): ' . $e->getMessage(), DEBUG_DEVELOPER);
                // ⚠️ v1.14.3 : Ne PAS utiliser le fallback permissif si la définition stricte échoue
                // Mieux vaut ne pas détecter de doublons que de risquer des faux positifs
                return [];
            }
        }

        // ⚠️ v1.14.3 : Fallback DÉSACTIVÉ pour sécurité
        // Le fallback (name + qtype) est TROP permissif et peut créer des faux positifs.
        // Deux questions avec le même nom mais un contenu différent seraient considérées comme doublons.
        // Mieux vaut ne pas détecter de doublons que de risquer de supprimer des questions par erreur.
        debugging('get_duplicate_group_question_ids_for_question: Cannot use strict definition (columns missing) - returning empty to avoid false positives', DEBUG_DEVELOPER);
        return [];
    }

    /**
     * 🔒 v1.14.3 : Vérification stricte croisée des doublons pour éviter les faux positifs
     * 
     * Vérifie que toutes les questions dans le groupe sont vraiment identiques
     * en comparant tous les champs pertinents (qtype, questiontextformat, questiontext).
     * 
     * @param array $questionids Tableau d'IDs de questions à vérifier
     * @param object $reference Question de référence pour comparaison
     * @return array IDs des questions vraiment identiques (au moins 2)
     */
    private static function verify_duplicates_strict(array $questionids, $reference): array {
        global $DB;
        
        if (count($questionids) < 2) {
            return [];
        }
        
        if (!self::can_use_certain_duplicates_definition()) {
            // Si on ne peut pas utiliser la définition stricte, on ne peut pas vérifier
            // Mieux vaut retourner vide pour éviter les faux positifs
            return [];
        }
        
        try {
            // Charger toutes les questions pour comparaison
            list($insql, $params) = $DB->get_in_or_equal($questionids);
            $questions = $DB->get_records_select('question', "id $insql", $params, '', 'id,qtype,questiontext,questiontextformat');
            
            if (count($questions) < 2) {
                return [];
            }
            
            // Critères de référence
            $ref_qtype = (string)($reference->qtype ?? '');
            $ref_format = (int)($reference->questiontextformat ?? 0);
            $ref_text = (string)($reference->questiontext ?? '');
            
            // Vérifier chaque question contre la référence
            $verified_ids = [];
            foreach ($questions as $q) {
                $q_qtype = (string)($q->qtype ?? '');
                $q_format = (int)($q->questiontextformat ?? 0);
                $q_text = (string)($q->questiontext ?? '');
                
                // Vérification stricte : tous les champs doivent être identiques
                if ($q_qtype === $ref_qtype && 
                    $q_format === $ref_format && 
                    $q_text === $ref_text) {
                    $verified_ids[] = (int)$q->id;
                } else {
                    debugging('verify_duplicates_strict: Question ID ' . $q->id . ' does not match reference (qtype: ' . $q_qtype . ' vs ' . $ref_qtype . ', format: ' . $q_format . ' vs ' . $ref_format . ', text length: ' . strlen($q_text) . ' vs ' . strlen($ref_text) . ')', DEBUG_DEVELOPER);
                }
            }
            
            // Retourner uniquement si on a au moins 2 questions identiques
            return count($verified_ids) >= 2 ? $verified_ids : [];
            
        } catch (\Exception $e) {
            debugging('Error in verify_duplicates_strict: ' . $e->getMessage(), DEBUG_DEVELOPER);
            // En cas d'erreur, retourner vide pour éviter les faux positifs
            return [];
        }
    }
    
    /**
     * Retourne les IDs de toutes les questions du groupe de doublons associé à un representative_id.
     *
     * @param int $representativeid
     * @return int[]
     */
    public static function get_duplicate_group_question_ids_by_representative_id(int $representativeid): array {
        global $DB;

        $representativeid = (int)$representativeid;
        if ($representativeid <= 0) {
            return [];
        }

        $repfields = 'id, name, qtype';
        if (self::can_use_certain_duplicates_definition()) {
            $repfields .= ', questiontext, questiontextformat';
        }

        $rep = $DB->get_record('question', ['id' => $representativeid], $repfields, IGNORE_MISSING);
        if (!$rep) {
            return [];
        }

        return self::get_duplicate_group_question_ids_for_question($rep);
    }

    /**
     * Récupère toutes les questions avec leurs statistiques complètes
     *
     * @param bool $include_duplicates Inclure la détection de doublons (peut être lent)
     * @param int $limit Limite du nombre de questions (0 = toutes)
     * @param int $offset Offset pour la pagination serveur (🆕 v1.9.30)
     * @return array Tableau des questions avec métadonnées
     */
    public static function get_all_questions_with_stats($include_duplicates = true, $limit = 0, $offset = 0) {
        global $DB;

        // 🆕 v1.9.30 : Support de l'offset pour pagination serveur
        // Récupérer les questions avec limite et offset - Utiliser l'API Moodle pour compatibilité multi-SGBD
        if ($limit > 0) {
            $questions = $DB->get_records('question', null, 'id DESC', '*', $offset, $limit);
        } else {
            $questions = $DB->get_records('question', null, 'id DESC');
        }
        $result = [];

        // 🚀 OPTIMISATION CRITIQUE : Si limite appliquée, charger UNIQUEMENT les données pour ces questions
        if ($limit > 0 && count($questions) > 0) {
            // Extraire les IDs des questions à traiter
            $question_ids = array_keys($questions);
            
            // Charger l'usage UNIQUEMENT pour ces questions
            try {
                $usage_map = self::get_questions_usage_by_ids($question_ids);
            } catch (\Exception $e) {
                debugging('Error loading usage map: ' . $e->getMessage(), DEBUG_DEVELOPER);
                $usage_map = [];
            }
            
            // Charger les doublons UNIQUEMENT pour ces questions (si demandé)
            $duplicates_map = [];
            if ($include_duplicates && count($questions) < 5000) {
                try {
                    $duplicates_map = self::get_duplicates_for_questions($questions);
                } catch (\Exception $e) {
                    debugging('Error loading duplicates map: ' . $e->getMessage(), DEBUG_DEVELOPER);
                    $duplicates_map = [];
                }
            }
        } else {
            // Mode ancien : charger toutes les données (pour compatibilité)
            try {
                $usage_map = self::get_all_questions_usage();
            } catch (\Exception $e) {
                debugging('Error loading usage map: ' . $e->getMessage(), DEBUG_DEVELOPER);
                $usage_map = [];
            }
            
            $duplicates_map = [];
            if ($include_duplicates && count($questions) < 5000) {
                try {
                    $duplicates_map = self::get_duplicates_map(true);
                } catch (\Exception $e) {
                    debugging('Error loading duplicates map: ' . $e->getMessage(), DEBUG_DEVELOPER);
                    $duplicates_map = [];
                }
            }
        }

        foreach ($questions as $question) {
            try {
                $stats = self::get_question_stats($question, $usage_map, $duplicates_map);
                $result[] = (object)[
                    'question' => $question,
                    'stats' => $stats,
                ];
            } catch (\Exception $e) {
                debugging('Error loading stats for question ' . $question->id . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
                // Continuer avec la question suivante
            }
        }

        return $result;
    }

    /**
     * Obtient les statistiques d'une question
     *
     * @param object $question Objet question
     * @param array $usage_map Map des usages (pré-calculé)
     * @param array $duplicates_map Map des doublons (pré-calculé)
     * @return object Statistiques
     */
    public static function get_question_stats($question, $usage_map = null, $duplicates_map = null, $include_duplicates = true) {
        global $DB;

        $stats = new \stdClass();
        
        try {
            // Récupérer la catégorie via question_bank_entries (Moodle 4.x)
            $category_sql = "SELECT qc.* 
                            FROM {question_categories} qc
                            INNER JOIN {question_bank_entries} qbe ON qbe.questioncategoryid = qc.id
                            INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                            WHERE qv.questionid = :questionid
                            LIMIT 1";
            $category = $DB->get_record_sql($category_sql, ['questionid' => $question->id]);
            $stats->category_name = $category ? format_string($category->name) : 'Inconnue';
            $stats->category_id = $category ? $category->id : 0;
            
            // Contexte enrichi (avec cours et module)
            if ($category) {
                try {
                    $context_details = local_question_diagnostic_get_context_details($category->contextid);
                    $stats->context_name = $context_details->context_name;
                    $stats->course_name = $context_details->course_name;
                    $stats->course_id = $context_details->course_id;
                    $stats->module_name = $context_details->module_name;
                    $stats->module_id = $context_details->module_id;
                    $stats->context_type = $context_details->context_type;
                    $stats->context_id = $category->contextid;
                } catch (\Exception $e) {
                    $stats->context_name = 'Erreur';
                    $stats->course_name = null;
                    $stats->course_id = null;
                    $stats->module_name = null;
                    $stats->module_id = null;
                    $stats->context_type = null;
                    $stats->context_id = 0;
                }
            } else {
                $stats->context_name = 'Inconnu';
                $stats->course_name = null;
                $stats->course_id = null;
                $stats->module_name = null;
                $stats->module_id = null;
                $stats->context_type = null;
                $stats->context_id = 0;
            }
            
            // Créateur
            $creator = $DB->get_record('user', ['id' => $question->createdby]);
            $stats->creator_name = $creator ? fullname($creator) : 'Inconnu';
            $stats->creator_id = $question->createdby;
            
            // Modificateur
            $modifier = $DB->get_record('user', ['id' => $question->modifiedby]);
            $stats->modifier_name = $modifier ? fullname($modifier) : 'Inconnu';
            $stats->modifier_id = $question->modifiedby;
            
            // Dates
            $stats->created_date = $question->timecreated;
            $stats->modified_date = $question->timemodified;
            $stats->created_formatted = userdate($question->timecreated, '%d/%m/%Y %H:%M');
            $stats->modified_formatted = userdate($question->timemodified, '%d/%m/%Y %H:%M');
            
            // Usage (utiliser le cache si disponible)
            if ($usage_map !== null && isset($usage_map[$question->id])) {
                $usage = $usage_map[$question->id];
                $stats->used_in_quizzes = $usage['quiz_count'];
                $stats->quiz_list = $usage['quiz_list'];
                $stats->attempt_count = $usage['attempt_count'];
                $stats->is_used = $usage['is_used'];
            } else {
                $usage = self::get_question_usage($question->id);
                $stats->used_in_quizzes = $usage['quiz_count'];
                $stats->quiz_list = $usage['quiz_list'];
                $stats->attempt_count = $usage['attempt_count'];
                $stats->is_used = $usage['is_used'];
            }
            
            // Doublons (optionnel)
            if ($include_duplicates) {
                // Utiliser le cache si disponible
                if ($duplicates_map !== null) {
                    $dupkey = (int)$question->id;
                    if (!isset($duplicates_map[$dupkey])) {
                        // Entry-centric : si map calculée sur les représentantes, fallback sur la représentante de l'entry.
                        $dupkey = (int)self::get_representative_questionid_for_questionid((int)$question->id);
                    }
                    if (isset($duplicates_map[$dupkey])) {
                        $stats->duplicate_count = count($duplicates_map[$dupkey]);
                        $stats->duplicate_ids = $duplicates_map[$dupkey];
                        $stats->is_duplicate = $stats->duplicate_count > 0;
                    } else {
                        $duplicates = self::find_question_duplicates($question);
                        $stats->duplicate_count = count($duplicates);
                        $stats->duplicate_ids = array_map(function($q) { return $q->id; }, $duplicates);
                        $stats->is_duplicate = $stats->duplicate_count > 0;
                    }
                } else {
                    $duplicates = self::find_question_duplicates($question);
                    $stats->duplicate_count = count($duplicates);
                    $stats->duplicate_ids = array_map(function($q) { return $q->id; }, $duplicates);
                    $stats->is_duplicate = $stats->duplicate_count > 0;
                }
            } else {
                $stats->duplicate_count = 0;
                $stats->duplicate_ids = [];
                $stats->is_duplicate = false;
            }
            
            // Statut - ⚠️ MOODLE 4.5 : question.hidden n'existe plus, utiliser question_versions.status
            try {
                $sql_status = "SELECT qv.status
                               FROM {question_versions} qv
                               INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                               WHERE qv.questionid = :questionid
                               ORDER BY qv.version DESC
                               LIMIT 1";
                $status_record = $DB->get_record_sql($sql_status, ['questionid' => $question->id]);
                $stats->is_hidden = ($status_record && $status_record->status === 'hidden');
                $stats->status = $stats->is_hidden ? 'hidden' : 'visible';
            } catch (\Exception $e) {
                // Fallback si erreur
                $stats->is_hidden = false;
                $stats->status = 'visible';
            }
            
            // Extrait du texte
            $stats->questiontext_excerpt = self::get_text_excerpt($question->questiontext, 100);
            
        } catch (\Exception $e) {
            // En cas d'erreur, retourner des stats par défaut
            $stats->category_name = 'Erreur';
            $stats->context_name = 'Erreur';
            $stats->creator_name = 'Inconnu';
            $stats->is_used = false;
            $stats->is_duplicate = false;
        }

        return $stats;
    }

    /**
     * Obtient l'usage d'une question (quizzes et tentatives)
     *
     * @param int $questionid ID de la question
     * @return array Informations sur l'usage
     */
    public static function get_question_usage($questionid) {
        global $DB;

        $usage = [
            'quiz_count' => 0,
            'quiz_list' => [],
            'attempt_count' => 0,
            'is_used' => false
        ];

        try {
            // Vérifier si la question est dans des quiz via la table quiz_slots
            // ⚠️ v1.6.4 : Compatibilité multi-version Moodle
            $columns = $DB->get_columns('quiz_slots');
            $quizzes = [];
            
            if (isset($columns['questionbankentryid'])) {
                // Moodle 4.1-4.4 : utilise questionbankentryid
                $sql = "SELECT DISTINCT q.id, q.name, q.course
                        FROM {quiz} q
                        INNER JOIN {quiz_slots} qs ON qs.quizid = q.id
                        INNER JOIN {question_bank_entries} qbe ON qbe.id = qs.questionbankentryid
                        INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                        WHERE qv.questionid = :questionid";
                $quizzes = $DB->get_records_sql($sql, ['questionid' => $questionid]);
                } else if (isset($columns['questionid'])) {
                // Moodle 4.0 uniquement : utilise questionid directement
                // ⚠️ Note : Moodle 3.x NON supporté (architecture incompatible)
                $sql = "SELECT DISTINCT q.id, q.name, q.course
                        FROM {quiz} q
                        INNER JOIN {quiz_slots} qs ON qs.quizid = q.id
                        WHERE qs.questionid = :questionid";
                $quizzes = $DB->get_records_sql($sql, ['questionid' => $questionid]);
            } else {
                // 🔧 v1.9.22 FIX : Moodle 4.5+ utilise question_references
                $sql = "SELECT DISTINCT q.id, q.name, q.course
                        FROM {quiz} q
                        INNER JOIN {quiz_slots} qs ON qs.quizid = q.id
                        INNER JOIN {question_references} qr ON qr.itemid = qs.id 
                            AND qr.component = 'mod_quiz' 
                            AND qr.questionarea = 'slot'
                        INNER JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                        INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                        WHERE qv.questionid = :questionid";
                $quizzes = $DB->get_records_sql($sql, ['questionid' => $questionid]);
            }

            foreach ($quizzes as $quiz) {
                $usage['quiz_list'][] = (object)[
                    'id' => $quiz->id,
                    'name' => $quiz->name,
                    'course' => $quiz->course
                ];
            }
            $usage['quiz_count'] = count($quizzes);

            // Vérifier si la question a été utilisée dans des tentatives
            // Via la table question_attempts
            $attempt_count = $DB->count_records_sql("
                SELECT COUNT(DISTINCT qa.id)
                FROM {question_attempts} qa
                INNER JOIN {question_usages} qu ON qu.id = qa.questionusageid
                WHERE qa.questionid = :questionid
            ", ['questionid' => $questionid]);

            $usage['attempt_count'] = $attempt_count;
            
            // La question est utilisée si elle est dans un quiz OU a des tentatives
            $usage['is_used'] = ($usage['quiz_count'] > 0 || $usage['attempt_count'] > 0);

        } catch (\Exception $e) {
            // En cas d'erreur, retourner les valeurs par défaut
        }

        return $usage;
    }

    /**
     * Récupère l'usage pour un ensemble spécifique de questions (optimisé pour limite)
     * 🆕 v1.9.2 : Changed to public for external use (random test, batch operations)
     *
     * @param array $question_ids IDs des questions
     * @return array Map [question_id => usage_info]
     */
    public static function get_questions_usage_by_ids($question_ids) {
        global $DB;

        if (empty($question_ids)) {
            return [];
        }

        $usage_map = [];

        try {
            // Construire la clause IN pour filtrer uniquement les questions demandées
            list($insql, $params) = $DB->get_in_or_equal($question_ids, SQL_PARAMS_NAMED);
            
            // Quiz usage - UNIQUEMENT pour les IDs demandés
            // ⚠️ v1.6.4 : Vérifier quelle colonne existe dans quiz_slots
            try {
                // Vérifier si questionbankentryid existe (Moodle 4.1+)
                $columns = $DB->get_columns('quiz_slots');
                $quiz_usage_sql = null;
                
                if (isset($columns['questionbankentryid'])) {
                    // Moodle 4.1-4.4 : utilise questionbankentryid
                    $quiz_usage_sql = "
                        SELECT qv.questionid, qu.id as quiz_id, qu.name as quiz_name, qu.course
                        FROM {quiz_slots} qs
                        INNER JOIN {quiz} qu ON qu.id = qs.quizid
                        INNER JOIN {question_bank_entries} qbe ON qbe.id = qs.questionbankentryid
                        INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                        WHERE qv.questionid $insql
                        ORDER BY qv.questionid, qu.id
                    ";
                } else if (isset($columns['questionid'])) {
                    // Moodle 4.0 uniquement : utilise questionid directement
                    // ⚠️ Note : Moodle 3.x NON supporté (architecture incompatible)
                    $quiz_usage_sql = "
                        SELECT qs.questionid, qu.id as quiz_id, qu.name as quiz_name, qu.course
                        FROM {quiz_slots} qs
                        INNER JOIN {quiz} qu ON qu.id = qs.quizid
                        WHERE qs.questionid $insql
                        ORDER BY qs.questionid, qu.id
                    ";
                } else {
                    // 🔧 v1.9.22 FIX : Moodle 4.5+ utilise question_references
                    $quiz_usage_sql = "
                        SELECT qv.questionid, qu.id as quiz_id, qu.name as quiz_name, qu.course
                        FROM {quiz_slots} qs
                        INNER JOIN {quiz} qu ON qu.id = qs.quizid
                        INNER JOIN {question_references} qr ON qr.itemid = qs.id 
                            AND qr.component = 'mod_quiz' 
                            AND qr.questionarea = 'slot'
                        INNER JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                        INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                        WHERE qv.questionid $insql
                        ORDER BY qv.questionid, qu.id
                    ";
                }
                
                if (!empty($quiz_usage_sql)) {
                    // get_recordset_sql() ne nécessite pas de 1ère colonne unique (contrairement à get_records_sql()).
                    $quiz_usage_rs = null;
                    try {
                        $quiz_usage_rs = $DB->get_recordset_sql($quiz_usage_sql, $params);
                        foreach ($quiz_usage_rs as $record) {
                            $qid = $record->questionid;
                            
                            if (!isset($usage_map[$qid])) {
                                $usage_map[$qid] = [
                                    'quiz_count' => 0,
                                    'quiz_list' => [],
                                    'attempt_count' => 0,
                                    'is_used' => true
                                ];
                            }
                            
                            // Vérifier si ce quiz n'est pas déjà dans la liste
                            $already_added = false;
                            foreach ($usage_map[$qid]['quiz_list'] as $existing_quiz) {
                                if ($existing_quiz->id == $record->quiz_id) {
                                    $already_added = true;
                                    break;
                                }
                            }
                            
                            if (!$already_added) {
                                $usage_map[$qid]['quiz_list'][] = (object)[
                                    'id' => $record->quiz_id,
                                    'name' => $record->quiz_name,
                                    'course' => $record->course
                                ];
                                $usage_map[$qid]['quiz_count']++;
                            }
                        }
                    } finally {
                        if ($quiz_usage_rs) {
                            $quiz_usage_rs->close();
                        }
                    }
                }
            } catch (\Exception $e) {
                debugging('Error in get_questions_usage_by_ids: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }

            // Attempts - UNIQUEMENT pour les IDs demandés
            $attempts = $DB->get_records_sql("
                SELECT qa.questionid, COUNT(DISTINCT qa.id) as attempt_count
                FROM {question_attempts} qa
                WHERE qa.questionid $insql
                GROUP BY qa.questionid
            ", $params);

            foreach ($attempts as $record) {
                if (!isset($usage_map[$record->questionid])) {
                    $usage_map[$record->questionid] = [
                        'quiz_count' => 0,
                        'quiz_list' => [],
                        'attempt_count' => 0,
                        'is_used' => false
                    ];
                }
                $usage_map[$record->questionid]['attempt_count'] = $record->attempt_count;
                $usage_map[$record->questionid]['is_used'] = true;
            }

            // Initialiser les questions sans usage
            foreach ($question_ids as $qid) {
                if (!isset($usage_map[$qid])) {
                    $usage_map[$qid] = [
                        'quiz_count' => 0,
                        'quiz_list' => [],
                        'attempt_count' => 0,
                        'is_used' => false
                    ];
                }
            }

        } catch (\Exception $e) {
            debugging('Error in get_questions_usage_by_ids: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return $usage_map;
    }

    /**
     * Détecte les doublons pour un ensemble spécifique de questions (optimisé)
     *
     * @param array $questions Tableau d'objets questions
     * @return array Map [question_id => [duplicate_ids]]
     */
    private static function get_duplicates_for_questions($questions) {
        global $DB;

        $duplicates_map = [];
        
        if (empty($questions)) {
            return $duplicates_map;
        }

        try {
            // Entry-centric : regrouper les questions locales par entry (représentante),
            // puis appliquer les doublons au niveau des entries (1 questionid représentative par entry).
            $local_by_rep = [];
            foreach ($questions as $q) {
                $qid = (int)($q->id ?? 0);
                if ($qid <= 0) {
                    continue;
                }
                $repqid = self::get_representative_questionid_for_questionid($qid);
                if (!isset($local_by_rep[$repqid])) {
                    $local_by_rep[$repqid] = [];
                }
                $local_by_rep[$repqid][] = $qid;
            }

            foreach ($local_by_rep as $repqid => $localqids) {
                $rep = $DB->get_record('question', ['id' => (int)$repqid], 'id,name,qtype,questiontext,questiontextformat', IGNORE_MISSING);
                if (!$rep) {
                    continue;
                }
                $group_ids = self::get_duplicate_group_question_ids_for_question($rep); // représentantes (1/entry)
                $others = array_values(array_filter(array_map('intval', $group_ids), function(int $id) use ($repqid): bool {
                    return $id > 0 && $id !== (int)$repqid;
                }));
                if (empty($others)) {
                    continue;
                }
                foreach ($localqids as $qid) {
                    $duplicates_map[(int)$qid] = $others;
                }
            }

        } catch (\Exception $e) {
            debugging('Error in get_duplicates_for_questions: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return $duplicates_map;
    }

    /**
     * Pré-calcule l'usage de toutes les questions (optimisation)
     *
     * @return array Map [question_id => usage_info]
     */
    private static function get_all_questions_usage() {
        global $DB;

        // Essayer le cache d'abord
        require_once(__DIR__ . '/cache_manager.php');
        $cached_usage = cache_manager::get(cache_manager::CACHE_QUESTIONUSAGE, 'usage_map');
        if ($cached_usage !== false) {
            return $cached_usage;
        }

        $usage_map = [];

        try {
            // Approche compatible avec tous les SGBD: requête simple + traitement en PHP
            // ⚠️ v1.6.4 : Compatibilité multi-version Moodle
            $columns = $DB->get_columns('quiz_slots');
            $quiz_usage_sql = null;
            
            if (isset($columns['questionbankentryid'])) {
                // Moodle 4.1+ : utilise questionbankentryid
                $quiz_usage_sql = "
                    SELECT qv.questionid, qu.id as quiz_id, qu.name as quiz_name, qu.course
                    FROM {quiz_slots} qs
                    INNER JOIN {quiz} qu ON qu.id = qs.quizid
                    INNER JOIN {question_bank_entries} qbe ON qbe.id = qs.questionbankentryid
                    INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                    ORDER BY qv.questionid, qu.id
                ";
            } else if (isset($columns['questionid'])) {
                // Moodle 4.0 uniquement : utilise questionid directement
                // ⚠️ Note : Moodle 3.x NON supporté (architecture incompatible)
                $quiz_usage_sql = "
                    SELECT qs.questionid, qu.id as quiz_id, qu.name as quiz_name, qu.course
                    FROM {quiz_slots} qs
                    INNER JOIN {quiz} qu ON qu.id = qs.quizid
                    ORDER BY qs.questionid, qu.id
                ";
            }

            if (!empty($quiz_usage_sql)) {
                // get_recordset_sql() ne nécessite pas de 1ère colonne unique (contrairement à get_records_sql()).
                $quiz_usage_rs = null;
                try {
                    $quiz_usage_rs = $DB->get_recordset_sql($quiz_usage_sql);
                    foreach ($quiz_usage_rs as $record) {
                        $qid = $record->questionid;
                        
                        if (!isset($usage_map[$qid])) {
                            $usage_map[$qid] = [
                                'quiz_count' => 0,
                                'quiz_list' => [],
                                'attempt_count' => 0,
                                'is_used' => true
                            ];
                        }
                        
                        // Vérifier si ce quiz n'est pas déjà dans la liste
                        $already_added = false;
                        foreach ($usage_map[$qid]['quiz_list'] as $existing_quiz) {
                            if ($existing_quiz->id == $record->quiz_id) {
                                $already_added = true;
                                break;
                            }
                        }
                        
                        if (!$already_added) {
                            $usage_map[$qid]['quiz_list'][] = (object)[
                                'id' => $record->quiz_id,
                                'name' => $record->quiz_name,
                                'course' => $record->course
                            ];
                            $usage_map[$qid]['quiz_count']++;
                        }
                    }
                } finally {
                    if ($quiz_usage_rs) {
                        $quiz_usage_rs->close();
                    }
                }
            }

            // Récupérer le nombre de tentatives par question (requête optimisée)
            $attempts = $DB->get_records_sql("
                SELECT qa.questionid, COUNT(DISTINCT qa.id) as attempt_count
                FROM {question_attempts} qa
                GROUP BY qa.questionid
            ");

            foreach ($attempts as $record) {
                if (!isset($usage_map[$record->questionid])) {
                    $usage_map[$record->questionid] = [
                        'quiz_count' => 0,
                        'quiz_list' => [],
                        'attempt_count' => 0,
                        'is_used' => false
                    ];
                }
                $usage_map[$record->questionid]['attempt_count'] = $record->attempt_count;
                $usage_map[$record->questionid]['is_used'] = true;
            }

            // Mettre en cache pour 30 minutes
            cache_manager::set(cache_manager::CACHE_QUESTIONUSAGE, 'usage_map', $usage_map);

        } catch (\Exception $e) {
            debugging('Error in get_all_questions_usage: ' . $e->getMessage(), DEBUG_DEVELOPER);
            // En cas d'erreur, retourner un map vide
        }

        return $usage_map;
    }

    /**
     * Récupère les questions qui ont des doublons avec au moins 1 version utilisée
     * 🆕 v1.8.0 : Pour le chargement ciblé des doublons problématiques
     * 🆕 v1.9.4 : OPTIMIZED with batch verification to avoid N+1 queries
     * 🔧 v1.9.24 : REFONTE COMPLÈTE - Utilise la même logique robuste que "Test Doublons Utilisés"
     *              Ne se base plus sur !empty() qui donnait des faux positifs
     *              Utilise désormais la détection directe depuis quiz_slots
     * 
     * 🆕 v1.9.30 : Support pagination serveur (limit + offset)
     * 
     * @param int $limit Limite de questions à retourner par page
     * @param int $offset Offset pour la pagination serveur
     * @return array Tableau des questions (objets simples)
     */
    public static function get_used_duplicates_questions($limit = 100, $offset = 0) {
        global $DB;
        
        try {
            // 🔧 v1.9.24 REFONTE COMPLÈTE : Même logique que "Test Doublons Utilisés" (questions_cleanup.php lignes 242-362)
            // LOGIQUE CORRECTE :
            // 1. Trouver toutes les questions UTILISÉES (dans les quiz)
            // 2. Pour chaque question utilisée, chercher SES doublons
            // 3. Si doublons trouvés → Ajouter tout le groupe au résultat
            
            // Étape 1 : Récupérer TOUTES les questions utilisées (UNIQUEMENT dans les quiz)
            $used_question_ids = [];
            $debug_info = ['columns' => [], 'sql' => '', 'count' => 0, 'error' => ''];
            
            try {
                // Vérifier quelle colonne existe dans quiz_slots
                $columns = $DB->get_columns('quiz_slots');
                $debug_info['columns'] = array_keys($columns);
                
                if (isset($columns['questionbankentryid'])) {
                    // Moodle 4.1+ : utilise questionbankentryid
                    $debug_info['mode'] = 'Moodle 4.1+ (questionbankentryid)';
                    $sql_used = "SELECT DISTINCT qv.questionid
                                 FROM {quiz_slots} qs
                                 INNER JOIN {question_bank_entries} qbe ON qbe.id = qs.questionbankentryid
                                 INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id";
                    $debug_info['sql'] = $sql_used;
                    $used_question_ids = $DB->get_fieldset_sql($sql_used);
                } else if (isset($columns['questionid'])) {
                    // Moodle 4.0 uniquement : utilise questionid directement
                    // ⚠️ Note : Moodle 3.x NON supporté (architecture incompatible)
                    $debug_info['mode'] = 'Moodle 4.0 (questionid)';
                    $sql_used = "SELECT DISTINCT qs.questionid
                                 FROM {quiz_slots} qs";
                    $debug_info['sql'] = $sql_used;
                    $used_question_ids = $DB->get_fieldset_sql($sql_used);
                } else {
                    // 🔧 v1.9.24 : Moodle 4.5+ - Nouvelle architecture avec question_references
                    $debug_info['mode'] = 'Moodle 4.5+ (question_references)';
                    
                    // Dans Moodle 4.5+, quiz_slots ne contient plus de lien direct vers les questions
                    // Il faut passer par question_references
                    $sql_used = "SELECT DISTINCT qv.questionid
                                 FROM {quiz_slots} qs
                                 INNER JOIN {question_references} qr ON qr.itemid = qs.id 
                                     AND qr.component = 'mod_quiz' 
                                     AND qr.questionarea = 'slot'
                                 INNER JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                                 INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id 
                                     AND qv.version = (
                                         SELECT MAX(v.version)
                                         FROM {question_versions} v
                                         WHERE v.questionbankentryid = qbe.id
                                     )";
                    $debug_info['sql'] = $sql_used;
                    $used_question_ids = $DB->get_fieldset_sql($sql_used);
                }
                
                $debug_info['count'] = count($used_question_ids);
            } catch (\Exception $e) {
                $debug_info['error'] = $e->getMessage();
                debugging('Erreur récupération questions utilisées (get_used_duplicates_questions): ' . $e->getMessage(), DEBUG_DEVELOPER);
                return []; // Si erreur de détection, retourner vide plutôt que des faux positifs
            }
            
            debugging('CHARGER DOUBLONS UTILISÉS v1.9.24 - Questions utilisées détectées: ' . count($used_question_ids), DEBUG_DEVELOPER);
            
            if (empty($used_question_ids)) {
                // Aucune question utilisée dans la base
                debugging('CHARGER DOUBLONS UTILISÉS v1.9.24 - Aucune question utilisée trouvée', DEBUG_DEVELOPER);
                return [];
            }
            
            // Étape 2 : Pour chaque question utilisée, chercher ses doublons
            // 🆕 v1.9.30 : Charger TOUTES les questions d'abord, puis paginer
            $all_result_questions = [];
            $processed_signatures = []; // Pour éviter les doublons dans le résultat
            $groups_found = 0;
            
            foreach ($used_question_ids as $qid) {
                $question = $DB->get_record('question', ['id' => $qid]);
                if (!$question) {
                    continue;
                }
                
                // Créer une signature unique pour éviter de traiter le même groupe plusieurs fois.
                // Définition "doublons certains" si possible, sinon fallback name+qtype.
                $signature = self::can_use_certain_duplicates_definition()
                    ? self::build_certain_duplicate_signature($question)
                    : (strtolower(trim((string)$question->name)) . '|' . (string)$question->qtype);
                if (in_array($signature, $processed_signatures)) {
                    continue; // Déjà traité ce groupe
                }
                
                // Chercher les doublons de CETTE question selon la définition standard du plugin.
                $group_ids = self::get_duplicate_group_question_ids_for_question($question);
                
                // Si au moins 2 versions (= 1 original + 1 doublon minimum) → C'est un groupe de doublons utilisés !
                if (count($group_ids) > 1) {
                    $processed_signatures[] = $signature;
                    $groups_found++;
                    
                    // Ajouter TOUTES les versions du groupe au résultat
                    $all_versions = $DB->get_records_list('question', 'id', $group_ids);
                    foreach ($all_versions as $q) {
                        $all_result_questions[] = $q;
                    }
                }
            }
            
            // 🆕 v1.9.30 : Appliquer la pagination sur le résultat complet
            $total_count = count($all_result_questions);
            $paginated_result = array_slice($all_result_questions, $offset, $limit);
            
            debugging('CHARGER DOUBLONS UTILISÉS v1.9.30 - Total: ' . $total_count . ' questions dans ' . $groups_found . ' groupes | Page: ' . count($paginated_result) . ' questions (offset=' . $offset . ', limit=' . $limit . ')', DEBUG_DEVELOPER);
            
            return $paginated_result;
            
        } catch (\Exception $e) {
            debugging('Error in get_used_duplicates_questions: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
    }
    
    /**
     * Vérifie si deux questions sont des doublons selon la définition standard
     * 
     * 🆕 v1.9.28 : DÉFINITION UNIQUE DE "DOUBLON"
     * Cette méthode définit LA définition officielle utilisée partout dans le plugin.
     * 
     * CRITÈRES :
     * - Même nom (name)
     * - Même type (qtype)
     * 
     * Note : Le texte (questiontext) n'est PAS utilisé car peut avoir variations mineures
     * (espaces, formatage HTML) sans changer la nature de la question.
     * 
     * @param object $q1 Question 1
     * @param object $q2 Question 2
     * @return bool True si doublons, false sinon
     */
    public static function are_duplicates($q1, $q2) {
        // Même entry (donc versions d'une même question) = jamais un doublon.
        if (isset($q1->id) && isset($q2->id)) {
            $e1 = self::get_entryid_for_questionid((int)$q1->id);
            $e2 = self::get_entryid_for_questionid((int)$q2->id);
            if ($e1 > 0 && $e2 > 0 && $e1 === $e2) {
                return false;
            }
        }
        // Même ID = même question, pas un doublon.
        if (isset($q1->id) && isset($q2->id) && (int)$q1->id === (int)$q2->id) {
            return false;
        }

        // 🔒 v1.14.3 : Définition "doublons certains" STRICTE : qtype + questiontextformat + questiontext strictement identiques.
        // Cette définition est la plus stricte possible pour éviter les faux positifs.
        if (self::can_use_certain_duplicates_definition()) {
            if ((string)($q1->qtype ?? '') !== (string)($q2->qtype ?? '')) {
                return false;
            }
            if ((int)($q1->questiontextformat ?? 0) !== (int)($q2->questiontextformat ?? 0)) {
                return false;
            }
            if ((string)($q1->questiontext ?? '') !== (string)($q2->questiontext ?? '')) {
                return false;
            }
            return true;
        }

        // ⚠️ v1.14.3 : Fallback DÉSACTIVÉ pour sécurité
        // Le fallback (name + qtype) est TROP permissif et peut créer des faux positifs.
        // Deux questions avec le même nom mais un contenu différent seraient considérées comme doublons.
        // Mieux vaut retourner false (pas de doublon) que de risquer des faux positifs.
        debugging('are_duplicates: Cannot use strict definition (columns missing) - returning false to avoid false positives', DEBUG_DEVELOPER);
        return false;
    }
    
    /**
     * Trouve les doublons d'une question selon la définition standard
     * 
     * 🔧 REFACTORED v1.9.28 : Utilise la définition unique via are_duplicates()
     * Remplace find_exact_duplicates() qui utilisait nom + type + texte
     * 
     * @param object $question Objet question
     * @return array Tableau des questions en doublon
     */
    public static function find_exact_duplicates($question) {
        global $DB;
        
        try {
            $questionid = (int)($question->id ?? 0);
            if ($questionid <= 0) {
                return [];
            }

            // Entry-centric : on part de la représentante de l'entry, puis on récupère les autres entries.
            $repqid = self::get_representative_questionid_for_questionid($questionid);
            $rep = $DB->get_record('question', ['id' => (int)$repqid], 'id,name,qtype,questiontext,questiontextformat', IGNORE_MISSING);
            if (!$rep) {
                return [];
            }

            $group_ids = self::get_duplicate_group_question_ids_for_question($rep); // représentantes (1/entry)
            $otherids = array_values(array_filter(array_map('intval', $group_ids), function(int $id) use ($repqid): bool {
                return $id > 0 && $id !== (int)$repqid;
            }));
            if (empty($otherids)) {
                return [];
            }

            $duplicates = $DB->get_records_list('question', 'id', $otherids);
            return array_values($duplicates);
            
        } catch (\Exception $e) {
            debugging('Error finding exact duplicates: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
    }
    
    /**
     * Trouve les doublons d'une question basés sur plusieurs critères
     *
     * 🔧 REFACTORED v1.9.28 : Utilise maintenant la définition standard (nom + type)
     * Le paramètre $threshold est conservé pour compatibilité mais ignoré.
     * 
     * @param object $question Objet question
     * @param float $threshold Seuil de similarité (DEPRECATED, ignoré)
     * @return array Tableau des questions en doublon
     */
    public static function find_question_duplicates($question, $threshold = 0.85) {
        // 🔧 v1.9.28 : Utiliser la définition standard au lieu de la similarité
        // Pour la cohérence dans tout le plugin
        return self::find_exact_duplicates($question);
    }

    // 🗑️ REMOVED v1.9.31 : Méthodes dépréciées supprimées (code mort)
    //
    // Les méthodes suivantes ont été supprimées car jamais utilisées :
    //
    // - calculate_question_similarity($q1, $q2) : Calcul complexe de similarité (DEPRECATED v1.9.28)
    //   → Remplacée par are_duplicates() qui utilise une définition simple (nom + type)
    //
    // - get_question_category_id($questionid) : Helper utilisé uniquement par calculate_question_similarity()
    //   → Plus nécessaire après suppression de calculate_question_similarity()
    //
    // Ces suppressions réduisent ~82 lignes de code mort et améliorent la maintenabilité.

    /**
     * Pré-calcule la map des doublons pour toutes les questions
     * Optimisé avec cache et détection rapide basée sur hash
     *
     * @param bool $use_cache Utiliser le cache (défaut: true)
     * @param int $limit Limite de questions à traiter (0 = toutes)
     * @return array Map [question_id => [duplicate_ids]]
     */
    private static function get_duplicates_map($use_cache = true, $limit = 0) {
        global $DB;

        // Essayer de récupérer depuis le cache
        require_once(__DIR__ . '/cache_manager.php');
        if ($use_cache) {
            $cached_map = cache_manager::get(cache_manager::CACHE_DUPLICATES, 'duplicates_map');
            if ($cached_map !== false) {
                return $cached_map;
            }
        }

        $duplicates_map = [];

        try {
            // IMPORTANT : ne pas traiter toutes les versions (question.id) → uniquement 1 question représentative par entry.
            $questions = self::get_representative_questions($limit > 0 ? (int)$limit : 0);
            if (empty($questions)) {
                return [];
            }

            if (count($questions) > 5000) {
                // Pour les grandes bases, on applique une version rapide basée sur signatures.
                return self::get_duplicates_map_fast($questions, $use_cache);
            }

            // Grouper par signature sur les représentantes → exclut les "doublons de versions".
            $signature_groups = [];
            foreach ($questions as $question) {
                $key = self::can_use_certain_duplicates_definition()
                    ? self::build_certain_duplicate_signature($question)
                    : (strtolower(trim((string)$question->name)) . '|' . (string)$question->qtype);
                if (!isset($signature_groups[$key])) {
                    $signature_groups[$key] = [];
                }
                $signature_groups[$key][] = (int)$question->id;
            }

            foreach ($signature_groups as $group) {
                if (count($group) <= 1) {
                    continue;
                }
                foreach ($group as $qid) {
                    $others = array_values(array_filter($group, function($id) use ($qid) {
                        return (int)$id !== (int)$qid;
                    }));
                    if (!empty($others)) {
                        $duplicates_map[(int)$qid] = $others;
                    }
                }
            }

            // Mettre en cache pour 1 heure
            if ($use_cache && !empty($duplicates_map)) {
                cache_manager::set(cache_manager::CACHE_DUPLICATES, 'duplicates_map', $duplicates_map);
            }

        } catch (\Exception $e) {
            debugging('Error in get_duplicates_map: ' . $e->getMessage(), DEBUG_DEVELOPER);
            // En cas d'erreur, retourner un map vide
        }

        return $duplicates_map;
    }

    /**
     * Version rapide de la détection de doublons (nom exact uniquement)
     * Utilisée pour les grandes bases de données
     *
     * @param array $questions Tableau de questions
     * @param bool $use_cache Utiliser le cache
     * @return array Map des doublons
     */
    private static function get_duplicates_map_fast($questions, $use_cache = true) {
        $duplicates_map = [];
        $signature_groups = [];
        
        // Grouper par signature.
        foreach ($questions as $question) {
            $key = self::can_use_certain_duplicates_definition()
                ? self::build_certain_duplicate_signature($question)
                : (strtolower(trim((string)$question->name)) . '|' . (string)$question->qtype);

            if (!isset($signature_groups[$key])) {
                $signature_groups[$key] = [];
            }
            $signature_groups[$key][] = (int)$question->id;
        }
        
        // Ne garder que les groupes avec plus d'une question
        foreach ($signature_groups as $group) {
            if (count($group) > 1) {
                foreach ($group as $qid) {
                    $others = array_filter($group, function($id) use ($qid) {
                        return $id != $qid;
                    });
                    $duplicates_map[$qid] = array_values($others);
                }
            }
        }
        
        // Mettre en cache
        if ($use_cache && !empty($duplicates_map)) {
            cache_manager::set(cache_manager::CACHE_DUPLICATES, 'duplicates_map', $duplicates_map);
        }
        
        return $duplicates_map;
    }

    /**
     * Version ultra-simplifiée pour grandes bases (>10k questions)
     * ⚠️ v1.6.3 : Évite les requêtes lourdes avec JOIN sur grandes bases
     * @param int $total_questions Nombre total déjà compté
     * @return object Statistiques basiques
     */
    private static function get_global_stats_simple($total_questions) {
        global $DB;
        
        $stats = new \stdClass();
        $stats->total_questions = $total_questions;
        
        // Statistiques ultra-basiques (COUNT simples uniquement, pas de JOIN)
        try {
            $stats->by_type = [];
            $types = $DB->get_records_sql("
                SELECT qtype, COUNT(*) as count
                FROM {question}
                GROUP BY qtype
                ORDER BY count DESC
            ");
            foreach ($types as $type) {
                $stats->by_type[$type->qtype] = $type->count;
            }
        } catch (\Exception $e) {
            $stats->by_type = [];
        }
        
        // Calculer quand même l'usage (simplifié mais plus exact)
        try {
            // Comptage simple via quiz_slots (sans JOIN complexes)
            $columns = $DB->get_columns('quiz_slots');
            $used_in_quiz = 0;
            
            if (isset($columns['questionbankentryid'])) {
                // Moodle 4.1+ : Compter les questions via questionbankentryid (2 JOINs)
                $used_in_quiz = (int)$DB->count_records_sql("
                    SELECT COUNT(DISTINCT qv.questionid)
                    FROM {quiz_slots} qs
                    INNER JOIN {question_bank_entries} qbe ON qbe.id = qs.questionbankentryid
                    INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                ");
                } else if (isset($columns['questionid'])) {
                    // Moodle 4.0 uniquement : Comptage direct
                    // ⚠️ Note : Moodle 3.x NON supporté (architecture incompatible)
                    $used_in_quiz = (int)$DB->count_records_sql("
                    SELECT COUNT(DISTINCT qs.questionid) FROM {quiz_slots} qs
                ");
            }
            
            // Tentatives
            $used_in_attempts = (int)$DB->count_records_sql("
                SELECT COUNT(DISTINCT qa.questionid) FROM {question_attempts} qa
            ");
            
            $stats->used_questions = max($used_in_quiz, $used_in_attempts);
            $stats->unused_questions = $total_questions - $stats->used_questions;
        } catch (\Exception $e) {
            debugging('Error calculating usage in simple mode: ' . $e->getMessage(), DEBUG_DEVELOPER);
            // En cas d'erreur, approximation
            $stats->used_questions = 0;
            $stats->unused_questions = $total_questions;
        }
        
        // Questions cachées (calcul léger même pour grandes bases)
        try {
            $stats->hidden_questions = (int)$DB->count_records_sql("
                SELECT COUNT(DISTINCT qv.questionid)
                FROM {question_versions} qv
                WHERE qv.status = 'hidden'
            ");
            $stats->visible_questions = $total_questions - $stats->hidden_questions;
        } catch (\Exception $e) {
            debugging('Error calculating hidden questions in simple mode: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $stats->visible_questions = $total_questions; // Approximation
            $stats->hidden_questions = 0;
        }
        
        // Estimation rapide des doublons (GROUP BY simple, pas de calcul de similarité)
        try {
            $exact_name_dupes = $DB->get_records_sql("
                SELECT MIN(q.id) AS id, q.name, q.qtype, COUNT(*) AS count
                FROM {question} q
                GROUP BY q.name, q.qtype
                HAVING COUNT(*) > 1
            ");
            $stats->duplicate_questions = count($exact_name_dupes);
            $stats->total_duplicates = array_sum(array_map(function($d) { 
                return $d->count; 
            }, $exact_name_dupes));
        } catch (\Exception $e) {
            debugging('Error calculating duplicates in simple mode: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $stats->duplicate_questions = 0;
            $stats->total_duplicates = 0;
        }
        
        $stats->questions_with_broken_links = 0; // Non calculé (trop lourd)
        
        // Indicateur pour l'interface
        $stats->simplified = true;
        
        return $stats;
    }
    
    /**
     * Génère des statistiques globales
     *
     * @param bool $use_cache Utiliser le cache (défaut: true)
     * @param bool $include_duplicates Inclure les doublons (peut être lent)
     * @return object Statistiques globales
     */
    public static function get_global_stats($use_cache = true, $include_duplicates = true) {
        global $DB;

        // Essayer le cache d'abord
        require_once(__DIR__ . '/cache_manager.php');
        if ($use_cache) {
            $cache_key = 'stats_' . ($include_duplicates ? 'full' : 'light');
            $cached_stats = cache_manager::get(cache_manager::CACHE_GLOBALSTATS, $cache_key);
            if ($cached_stats !== false) {
                return $cached_stats;
            }
        }

        $stats = new \stdClass();
        
        try {
            // Total de questions
            $stats->total_questions = $DB->count_records('question');
            
            // 🚨 v1.6.3 : ULTRA-SIMPLIFICATION pour grandes bases
            // Si plus de 10 000 questions, on saute les calculs lourds
            if ($stats->total_questions > 10000) {
                return self::get_global_stats_simple($stats->total_questions);
            }
            
            // Questions par type
            $stats->by_type = [];
            $types = $DB->get_records_sql("
                SELECT qtype, COUNT(*) as count
                FROM {question}
                GROUP BY qtype
                ORDER BY count DESC
            ");
            foreach ($types as $type) {
                $stats->by_type[$type->qtype] = $type->count;
            }
            
            // Questions visibles/cachées - ⚠️ MOODLE 4.5 : utiliser question_versions.status
            $stats->visible_questions = (int)$DB->count_records_sql("
                SELECT COUNT(DISTINCT qv.questionid)
                FROM {question_versions} qv
                WHERE qv.status != 'hidden'
            ");
            $stats->hidden_questions = (int)$DB->count_records_sql("
                SELECT COUNT(DISTINCT qv.questionid)
                FROM {question_versions} qv
                WHERE qv.status = 'hidden'
            ");
            
            // Questions utilisées/inutilisées (calcul optimisé)
            // ⚠️ v1.6.4 : Compatibilité multi-version Moodle
            $used_in_quiz = 0;
            try {
                $columns = $DB->get_columns('quiz_slots');
                
                if (isset($columns['questionbankentryid'])) {
                    // Moodle 4.1+ : utilise questionbankentryid
                    $used_in_quiz = $DB->count_records_sql("
                        SELECT COUNT(DISTINCT qv.questionid)
                        FROM {quiz_slots} qs
                        INNER JOIN {question_bank_entries} qbe ON qbe.id = qs.questionbankentryid
                        INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                    ");
                } else if (isset($columns['questionid'])) {
                    // Moodle 4.0 uniquement : utilise questionid directement
                    // ⚠️ Note : Moodle 3.x NON supporté (architecture incompatible)
                    $used_in_quiz = $DB->count_records_sql("
                        SELECT COUNT(DISTINCT qs.questionid)
                        FROM {quiz_slots} qs
                    ");
                }
            } catch (\Exception $e) {
                debugging('Error counting quiz usage: ' . $e->getMessage(), DEBUG_DEVELOPER);
                $used_in_quiz = 0;
            }
            
            $used_in_attempts = $DB->count_records_sql("
                SELECT COUNT(DISTINCT qa.questionid)
                FROM {question_attempts} qa
            ");
            
            // Union des deux ensembles (approximation)
            $stats->used_questions = max($used_in_quiz, $used_in_attempts);
            $stats->unused_questions = $stats->total_questions - $stats->used_questions;
            
            // Questions en doublon (calcul lourd, optionnel)
            if ($include_duplicates && $stats->total_questions < 10000) {
                try {
                    $duplicates_map = self::get_duplicates_map($use_cache);
                    $stats->duplicate_questions = count($duplicates_map);
                    $stats->total_duplicates = array_sum(array_map('count', $duplicates_map));
                } catch (\Exception $e) {
                    debugging('Error calculating duplicates: ' . $e->getMessage(), DEBUG_DEVELOPER);
                    $stats->duplicate_questions = 0;
                    $stats->total_duplicates = 0;
                }
            } else {
                // Pour les grandes bases ou si non demandé, utiliser une estimation rapide
                $exact_name_dupes = $DB->get_records_sql("
                    SELECT MIN(q.id) AS id, q.name, q.qtype, COUNT(*) AS count
                    FROM {question} q
                    GROUP BY q.name, q.qtype
                    HAVING COUNT(*) > 1
                ");
                $stats->duplicate_questions = count($exact_name_dupes);
                $stats->total_duplicates = array_sum(array_map(function($d) { 
                    return $d->count; 
                }, $exact_name_dupes));
            }
            
            // Questions avec liens cassés (si la classe existe)
            if (class_exists('local_question_diagnostic\question_link_checker')) {
                try {
                    $broken_stats = question_link_checker::get_global_stats();
                    $stats->questions_with_broken_links = $broken_stats->questions_with_broken_links;
                } catch (\Exception $e) {
                    $stats->questions_with_broken_links = 0;
                }
            } else {
                $stats->questions_with_broken_links = 0;
            }
            
            // Mettre en cache
            if ($use_cache) {
                $cache_key = 'stats_' . ($include_duplicates ? 'full' : 'light');
                cache_manager::set(cache_manager::CACHE_GLOBALSTATS, $cache_key, $stats);
            }
            
        } catch (\Exception $e) {
            debugging('Error in get_global_stats: ' . $e->getMessage(), DEBUG_DEVELOPER);
            // Valeurs par défaut en cas d'erreur
            $stats->total_questions = 0;
            $stats->used_questions = 0;
            $stats->unused_questions = 0;
            $stats->duplicate_questions = 0;
            $stats->total_duplicates = 0;
            $stats->by_type = [];
            $stats->visible_questions = 0;
            $stats->hidden_questions = 0;
            $stats->questions_with_broken_links = 0;
        }

        return $stats;
    }

    /**
     * Génère l'URL pour accéder à une question dans la banque de questions
     *
     * 🔧 REFACTORED: Cette méthode utilise maintenant la fonction centralisée dans lib.php
     * @see local_question_diagnostic_get_question_bank_url()
     * 
     * @param object $question Objet question
     * @param object $category Objet catégorie (optionnel)
     * @return \moodle_url|null URL vers la banque de questions
     */
    public static function get_question_bank_url($question, $category = null) {
        global $DB;
        
        try {
            if (!$category) {
                // Récupérer la catégorie via question_bank_entries (Moodle 4.x)
                $category_sql = "SELECT qc.* 
                                FROM {question_categories} qc
                                INNER JOIN {question_bank_entries} qbe ON qbe.questioncategoryid = qc.id
                                INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                                WHERE qv.questionid = :questionid
                                LIMIT 1";
                $category = $DB->get_record_sql($category_sql, ['questionid' => $question->id]);
            }
            
            if (!$category) {
                return null;
            }
            
            // Utiliser la fonction centralisée avec l'ID de la question
            return local_question_diagnostic_get_question_bank_url($category, $question->id);
            
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Extrait un texte court depuis un HTML
     *
     * @param string $html Texte HTML
     * @param int $length Longueur maximale
     * @return string Extrait
     */
    private static function get_text_excerpt($html, $length = 100) {
        $text = strip_tags($html);
        $text = trim(preg_replace('/\s+/', ' ', $text));
        
        if (strlen($text) > $length) {
            $text = substr($text, 0, $length) . '...';
        }
        
        return $text;
    }

    /**
     * Purge tous les caches du plugin
     * 
     * 🔧 REFACTORED v1.9.27 : Utilise maintenant la classe CacheManager centralisée
     * @see \local_question_diagnostic\cache_manager::purge_all_caches()
     *
     * @return bool Succès de l'opération
     */
    public static function purge_all_caches() {
        require_once(__DIR__ . '/cache_manager.php');
        $results = cache_manager::purge_all_caches();
        
        // Retourner true si au moins un cache a été purgé
        return !empty(array_filter($results));
    }

    /**
     * Vérifie si PLUSIEURS questions peuvent être supprimées (VERSION BATCH OPTIMISÉE)
     * 🆕 v1.9.0 : Version batch pour éviter N+1 queries
     * 
     * @param array $questionids Tableau d'IDs de questions
     * @return array Map [question_id => {can_delete, reason, details}]
     */
    public static function can_delete_questions_batch($questionids) {
        global $DB;
        
        if (empty($questionids)) {
            return [];
        }
        
        $results = [];
        
        try {
            // Initialiser tous les résultats
            foreach ($questionids as $qid) {
                $results[$qid] = (object)[
                    'can_delete' => false,
                    'reason' => '',
                    'details' => []
                ];
            }
            
            // ÉTAPE 1 : Récupérer toutes les questions d'un coup
            list($insql, $params) = $DB->get_in_or_equal($questionids);
            $fields = 'id, name, qtype, questiontext';
            if (self::can_use_certain_duplicates_definition()) {
                $fields .= ', questiontextformat';
            }
            $questions = $DB->get_records_select('question', "id $insql", $params, '', $fields);
            
            // ÉTAPE 2 : Vérifier l'usage de TOUTES les questions en une seule requête
            $usage_map = self::get_questions_usage_by_ids($questionids);
            
            // 🆕 v1.14.1 : Charger les statuts cachés en batch pour toutes les questions
            $version_info_map = self::get_questions_version_info_batch($questionids);
            
            // ÉTAPE 3 : Analyser chaque question
            foreach ($questions as $q) {
                $qid = $q->id;
                
                // Vérification 1 : Question utilisée ?
                // 🔧 v1.9.43 FIX CRITIQUE : Utiliser la clé 'quiz_count' directement au lieu d'itérer sur l'array
                if (isset($usage_map[$qid]) && is_array($usage_map[$qid])) {
                    $quiz_count = isset($usage_map[$qid]['quiz_count']) ? $usage_map[$qid]['quiz_count'] : 0;
                    
                    if ($quiz_count > 0) {
                        $results[$qid]->reason = 'Question utilisée dans ' . $quiz_count . ' quiz';
                        $results[$qid]->details['quiz_count'] = $quiz_count;
                        continue;
                    }
                }
                
                // 🆕 v1.14.1 : Vérifier si la question est cachée (chargé en batch)
                $is_hidden = isset($version_info_map[$qid]) && $version_info_map[$qid]->is_hidden;
                
                // Vérification 2 : Question a des doublons ?
                // 🔧 v1.9.51 FIX CRITIQUE : Chercher TOUTES les questions avec ce nom+type dans la BASE
                // (pas seulement parmi les questions passées en paramètre !)
                $group_ids = self::get_duplicate_group_question_ids_for_question($q);
                
                // Compter combien il y en a (en excluant la question elle-même)
                $duplicate_count = 0;
                $duplicate_ids = [];
                foreach ($group_ids as $otherid) {
                    $otherid = (int)$otherid;
                    if ($otherid <= 0 || $otherid === (int)$qid) {
                        continue;
                    }
                    $duplicate_count++;
                    $duplicate_ids[] = $otherid;
                }
                
                // 🆕 v1.14.1 : Questions cachées uniques inutilisées peuvent être supprimées
                if ($duplicate_count == 0) {
                    // Si la question est cachée ET inutilisée, elle peut être supprimée
                    if ($is_hidden) {
                        $results[$qid]->can_delete = true;
                        $results[$qid]->reason = 'Question cachée unique inutilisée';
                        $results[$qid]->details['is_unique'] = true;
                        $results[$qid]->details['is_hidden'] = true;
                        $results[$qid]->details['debug_signature'] = self::can_use_certain_duplicates_definition()
                            ? self::build_certain_duplicate_signature($q)
                            : ((string)($q->name ?? '') . '|||' . (string)($q->qtype ?? ''));
                        $results[$qid]->details['debug_name'] = (string)($q->name ?? '');
                        $results[$qid]->details['debug_type'] = (string)($q->qtype ?? '');
                        continue;
                    }
                    // Question visible unique → PROTÉGÉE
                    $results[$qid]->reason = 'Question unique (pas de doublon)';
                    $results[$qid]->details['is_unique'] = true;
                    $results[$qid]->details['debug_signature'] = self::can_use_certain_duplicates_definition()
                        ? self::build_certain_duplicate_signature($q)
                        : ((string)($q->name ?? '') . '|||' . (string)($q->qtype ?? ''));
                    $results[$qid]->details['debug_name'] = (string)($q->name ?? '');
                    $results[$qid]->details['debug_type'] = (string)($q->qtype ?? '');
                    continue;
                }
                
                // Si on arrive ici : question inutilisée ET en doublon → SUPPRIMABLE
                $results[$qid]->can_delete = true;
                $results[$qid]->reason = $is_hidden ? 'Question cachée en doublon inutilisée' : 'Doublon inutilisé';
                $results[$qid]->details['duplicate_count'] = $duplicate_count;
                $results[$qid]->details['duplicate_ids'] = $duplicate_ids;
                $results[$qid]->details['is_hidden'] = $is_hidden;
                $results[$qid]->details['debug_signature'] = self::can_use_certain_duplicates_definition()
                    ? self::build_certain_duplicate_signature($q)
                    : ((string)($q->name ?? '') . '|||' . (string)($q->qtype ?? ''));
                $results[$qid]->details['debug_name'] = (string)($q->name ?? '');
                $results[$qid]->details['debug_type'] = (string)($q->qtype ?? '');
            }
            
        } catch (\Exception $e) {
            debugging('Error in can_delete_questions_batch: ' . $e->getMessage(), DEBUG_DEVELOPER);
            // En cas d'erreur, marquer toutes comme non supprimables
            foreach ($questionids as $qid) {
                if (!isset($results[$qid])) {
                    $results[$qid] = (object)[
                        'can_delete' => false,
                        'reason' => 'Erreur de vérification',
                        'details' => []
                    ];
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Vérifie si une question peut être supprimée en toute sécurité
     * 
     * 🗑️ REMOVED v1.9.27 : Méthode dépréciée supprimée
     * 
     * Cette méthode était marquée DEPRECATED et causait des problèmes de performance (N+1 queries).
     * Utiliser à la place : can_delete_questions_batch() qui est optimisée pour traiter
     * plusieurs questions en une seule fois.
     * 
     * @deprecated Utiliser can_delete_questions_batch() pour de meilleures performances
     * @param int $questionid ID de la question
     * @return object Objet avec can_delete (bool), reason (string), details (array)
     */
    public static function can_delete_question($questionid) {
        // 🔧 REFACTORED v1.9.27 : Appeler la version batch pour une seule question
        $results = self::can_delete_questions_batch([$questionid]);
        return isset($results[$questionid]) ? $results[$questionid] : (object)[
            'can_delete' => false,
            'reason' => 'Erreur de vérification',
            'details' => []
        ];
    }
    
    /**
     * Détermine si une question cachée a été "supprimée" (soft delete)
     * 
     * 🆕 v1.9.57 : Nouvelle méthode pour distinguer cachée manuelle vs supprimée
     * 
     * Une question est considérée comme "supprimée" si :
     * - Elle est cachée (status = 'hidden')
     * - ET elle est utilisée dans au moins 1 quiz
     * 
     * Raison : Moodle garde les questions supprimées mais utilisées pour l'intégrité des tentatives.
     * 
     * @param int $questionid ID de la question
     * @param object $version_info Infos de version (depuis get_questions_version_info_batch)
     * @param array $usage_info Infos d'usage (depuis get_questions_usage_by_ids)
     * @return string 'deleted'|'hidden'|'visible'
     */
    public static function get_question_visibility_status($questionid, $version_info, $usage_info) {
        // Si la question n'est pas cachée, elle est visible
        if (!$version_info || !$version_info->is_hidden) {
            return 'visible';
        }
        
        // La question est cachée, vérifier si elle est utilisée
        $is_used = false;
        if (isset($usage_info[$questionid]) && is_array($usage_info[$questionid])) {
            $quiz_count = isset($usage_info[$questionid]['quiz_count']) ? $usage_info[$questionid]['quiz_count'] : 0;
            $is_used = ($quiz_count > 0);
        }
        
        // Question cachée ET utilisée = probablement supprimée (soft delete)
        if ($is_used) {
            return 'deleted';
        }
        
        // Question cachée mais NON utilisée = cachée manuellement
        return 'hidden';
    }
    
    /**
     * Récupère les informations de versions pour un batch de questions
     * 
     * 🆕 v1.9.55 : Nouvelle méthode pour afficher statut caché et nombre de versions
     * 
     * @param array $questionids Tableau d'IDs de questions
     * @return array Map [question_id => (object)[is_hidden => bool, version_count => int, latest_version => int]]
     */
    public static function get_questions_version_info_batch($questionids) {
        global $DB;
        
        if (empty($questionids)) {
            return [];
        }
        
        $results = [];
        
        try {
            // Initialiser tous les résultats
            foreach ($questionids as $qid) {
                $results[$qid] = (object)[
                    'is_hidden' => false,
                    'version_count' => 0,
                    'latest_version' => 0,
                    'status' => 'unknown'
                ];
            }
            
            // ÉTAPE 1 : Récupérer le statut et le nombre de versions depuis question_versions
            // ⚠️ MOODLE 4.5 : Les versions sont dans question_versions avec status et version
            list($insql, $params) = $DB->get_in_or_equal($questionids);
            
            $version_sql = "SELECT qv.questionid,
                                   qv.status,
                                   qv.version,
                                   COUNT(*) OVER (PARTITION BY qbe.id) as version_count,
                                   MAX(qv.version) OVER (PARTITION BY qbe.id) as latest_version
                            FROM {question_versions} qv
                            INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                            WHERE qv.questionid $insql
                            ORDER BY qv.questionid, qv.version DESC";
            
            // get_recordset_sql() ne nécessite pas de 1ère colonne unique (contrairement à get_records_sql()).
            $version_rs = null;
            try {
                $version_rs = $DB->get_recordset_sql($version_sql, $params);
                foreach ($version_rs as $record) {
                    $qid = $record->questionid;
                    
                    // Ne prendre que la première occurrence (version la plus récente)
                    if (!isset($results[$qid]) || $results[$qid]->status === 'unknown') {
                        $results[$qid]->is_hidden = ($record->status === 'hidden');
                        $results[$qid]->status = $record->status;
                        $results[$qid]->version_count = (int)$record->version_count;
                        $results[$qid]->latest_version = (int)$record->latest_version;
                    }
                }
            } finally {
                if ($version_rs) {
                    $version_rs->close();
                }
            }
            
        } catch (\Exception $e) {
            debugging('Error in get_questions_version_info_batch: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        
        return $results;
    }
    
    /**
     * Récupère toutes les questions cachées (status = 'hidden')
     * 
     * 🆕 v1.9.58 : Nouvelle méthode pour gérer les questions cachées en masse
     * 
     * @param bool $exclude_used Si true, exclut les questions utilisées (soft delete)
     * @param int $limit Limite du nombre de résultats (0 = toutes)
     * @return array Tableau d'objets question avec infos supplémentaires
     */
    public static function get_hidden_questions($exclude_used = true, $limit = 0) {
        global $DB;
        
        try {
            // 🔧 v1.9.60 : Requête améliorée pour récupérer TOUTES les questions cachées
            // Chercher dans question_versions TOUTES les entrées avec status='hidden'
            // Utiliser DISTINCT pour éviter les doublons si une question a plusieurs versions cachées
            $sql = "SELECT DISTINCT qv.questionid
                    FROM {question_versions} qv
                    WHERE qv.status = 'hidden'
                    ORDER BY qv.questionid DESC";
            
            $hidden_question_ids = $DB->get_fieldset_sql($sql, [], 0, $limit > 0 ? $limit : 0);
            
            if (empty($hidden_question_ids)) {
                return [];
            }
            
            // Récupérer les détails des questions
            list($insql, $params) = $DB->get_in_or_equal($hidden_question_ids);
            $questions = $DB->get_records_select('question', "id $insql", $params);
            
            // Si on exclut les questions utilisées, charger les infos d'usage
            if ($exclude_used) {
                $usage_map = self::get_questions_usage_by_ids($hidden_question_ids);
                
                // Filtrer les questions non utilisées
                $filtered_questions = [];
                foreach ($questions as $q) {
                    $is_used = false;
                    if (isset($usage_map[$q->id]) && is_array($usage_map[$q->id])) {
                        $quiz_count = isset($usage_map[$q->id]['quiz_count']) ? $usage_map[$q->id]['quiz_count'] : 0;
                        $is_used = ($quiz_count > 0);
                    }
                    
                    if (!$is_used) {
                        $filtered_questions[] = $q;
                    }
                }
                return $filtered_questions;
            }
            
            return array_values($questions);
            
        } catch (\Exception $e) {
            debugging('Error in get_hidden_questions: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
    }
    
    /**
     * Rend une question visible (change status de 'hidden' à 'ready')
     * 
     * 🆕 v1.9.58 : Nouvelle méthode pour rendre une question visible
     * 🔧 v1.14.2 : Correction pour Moodle 5.1 - Utilisation de transaction et vérification améliorée
     * 
     * @param int $questionid ID de la question
     * @return bool|string True si succès, message d'erreur sinon
     */
    public static function unhide_question($questionid) {
        global $DB;
        
        try {
            // Vérifier que la question existe (avant de démarrer la transaction)
            $question = $DB->get_record('question', ['id' => $questionid]);
            if (!$question) {
                debugging('unhide_question: Question not found (ID: ' . $questionid . ')', DEBUG_DEVELOPER);
                return 'Question non trouvée (ID: ' . $questionid . ')';
            }
            
            // 🔧 v1.14.2 : Vérifier d'abord si la question est cachée (avant transaction)
            $hidden_versions = $DB->get_records('question_versions', [
                'questionid' => $questionid,
                'status' => 'hidden'
            ]);
            
            $versions_hidden = count($hidden_versions);
            debugging('unhide_question ID ' . $questionid . ': Found ' . $versions_hidden . ' hidden version(s)', DEBUG_DEVELOPER);
            
            if ($versions_hidden == 0) {
                return 'Question non cachée (ID: ' . $questionid . ')';
            }
            
            // Démarrer la transaction seulement si on a des versions à mettre à jour
            $transaction = $DB->start_delegated_transaction();
            
            // 🔧 v1.14.2 : Mettre à jour chaque version individuellement pour garantir la mise à jour
            $updated_count = 0;
            foreach ($hidden_versions as $version) {
                $version->status = 'ready';
                if ($DB->update_record('question_versions', $version)) {
                    $updated_count++;
                } else {
                    debugging('unhide_question ID ' . $questionid . ': Failed to update version ID ' . $version->id, DEBUG_DEVELOPER);
                }
            }
            
            // Valider la transaction
            $transaction->allow_commit();
            
            // Vérifier si la mise à jour a fonctionné
            $still_hidden = $DB->count_records('question_versions', [
                'questionid' => $questionid,
                'status' => 'hidden'
            ]);
            
            if ($still_hidden == 0 && $updated_count == $versions_hidden) {
                debugging('unhide_question ID ' . $questionid . ': SUCCESS - ' . $updated_count . ' version(s) made visible', DEBUG_DEVELOPER);
                // Purger le cache (la méthode purge_all_caches() est suffisante)
                return true;
            } else {
                debugging('unhide_question ID ' . $questionid . ': PARTIAL - Updated ' . $updated_count . ' of ' . $versions_hidden . ', still ' . $still_hidden . ' hidden', DEBUG_DEVELOPER);
                return 'Échec partiel : ' . $still_hidden . ' version(s) encore cachée(s) sur ' . $versions_hidden . ' trouvée(s)';
            }
            
        } catch (\Exception $e) {
            debugging('unhide_question ID ' . $questionid . ': ERROR - ' . $e->getMessage(), DEBUG_DEVELOPER);
            return 'Erreur lors du changement de statut : ' . $e->getMessage();
        }
    }

    /**
     * Rend plusieurs questions visibles en masse
     * 
     * 🆕 v1.9.58 : Opération en masse pour rendre visibles
     * 
     * @param array $questionids Tableau d'IDs de questions
     * @return array ['success' => int, 'failed' => int, 'protected' => int, 'errors' => array]
     */
    public static function unhide_questions_batch($questionids) {
        $success = 0;
        $failed = 0;
        // Conservé pour compatibilité avec l'appelant (ancienne logique "protégée").
        $protected = 0;
        $errors = [];

        // Normaliser / dédupliquer
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)$questionids))));
        if (empty($ids)) {
            return [
                'success' => 0,
                'failed' => 0,
                'protected' => 0,
                'errors' => []
            ];
        }

        foreach ($ids as $qid) {
            $result = self::unhide_question($qid);
            if ($result === true) {
                $success++;
            } else {
                $failed++;
                $errors[] = "Question $qid: $result";
            }
        }
        
        return [
            'success' => $success,
            'failed' => $failed,
            'protected' => $protected,
            'errors' => $errors
        ];
    }
    
    /**
     * Supprime une question en toute sécurité (avec vérifications)
     * Utilise l'API Moodle pour supprimer proprement
     *
     * @param int $questionid ID de la question
     * @return bool|string True si succès, message d'erreur sinon
     */
    public static function delete_question_safe($questionid) {
        global $DB, $CFG;
        
        require_once($CFG->dirroot . '/question/editlib.php');
        
        // Vérifier si la suppression est autorisée
        $check = self::can_delete_question($questionid);
        
        if (!$check->can_delete) {
            return 'Suppression interdite : ' . $check->reason;
        }
        
        try {
            // Récupérer la question et sa catégorie
            $question = $DB->get_record('question', ['id' => $questionid], '*', MUST_EXIST);
            
            // Récupérer la catégorie via question_bank_entries (Moodle 4.x)
            $category_sql = "SELECT qc.* 
                            FROM {question_categories} qc
                            INNER JOIN {question_bank_entries} qbe ON qbe.questioncategoryid = qc.id
                            INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                            WHERE qv.questionid = :questionid
                            LIMIT 1";
            $category = $DB->get_record_sql($category_sql, ['questionid' => $questionid]);
            
            if (!$category) {
                return 'Catégorie de la question introuvable';
            }
            
            // Utiliser l'API Moodle pour supprimer proprement la question
            // Cela gère automatiquement :
            // - Les entrées dans question_bank_entries
            // - Les versions dans question_versions
            // - Les fichiers associés
            // - Les données spécifiques au type de question
            question_delete_question($questionid);
            
            return true;
            
        } catch (\Exception $e) {
            return 'Erreur lors de la suppression : ' . $e->getMessage();
        }
    }

    /**
     * Exporte les questions au format CSV
     *
     * @param array $questions Tableau de questions avec stats
     * @return string Contenu CSV
     */
    public static function export_to_csv($questions) {
        $csv = "ID,Nom,Type,Catégorie,Contexte,Créateur,Date création,Date modification,Visible,Utilisée,Quiz,Tentatives,Doublons\n";
        
        foreach ($questions as $item) {
            $q = $item->question;
            $s = $item->stats;
            
            $csv .= sprintf(
                "%d,\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",%d,%d,%d\n",
                $q->id,
                str_replace('"', '""', $q->name),
                $q->qtype,
                str_replace('"', '""', $s->category_name),
                str_replace('"', '""', $s->context_name),
                str_replace('"', '""', $s->creator_name),
                $s->created_formatted,
                $s->modified_formatted,
                $s->is_hidden ? 'Non' : 'Oui',
                $s->is_used ? 'Oui' : 'Non',
                $s->used_in_quizzes,
                $s->attempt_count,
                $s->duplicate_count
            );
        }
        
        return $csv;
    }
    
    /**
     * Récupère les groupes de questions en doublon
     * 
     * Un groupe de doublons = questions avec même nom ET même type
     * 
     * 🆕 v1.9.53 : OPTIMISATION - Prioriser les groupes avec questions supprimables
     * 
     * @param int $limit Nombre de groupes à retourner (0 = tous)
     * @param int $offset Offset pour la pagination
     * @param bool $used_only Si true, ne retourner que les groupes avec au moins 1 version utilisée
     * @param bool $deletable_only Si true, ne retourner que les groupes avec au moins 1 version supprimable (priorise le nettoyage)
     * @return array Tableau d'objets représentant chaque groupe de doublons
     */
    public static function get_duplicate_groups($limit = 0, $offset = 0, $used_only = false, $deletable_only = false) {
        global $DB;
        
        // 🎯 v1.9.45 : Nouvelle méthode pour récupérer les groupes de doublons
        // 🆕 v1.9.53 : OPTIMISATION - Filtrage et priorisation des groupes supprimables
        // Grouper les questions par nom + type et ne garder que ceux qui ont des doublons (COUNT > 1)
        
        // Étape 1 : Récupérer tous les groupes avec doublons.
        // Définition "doublons certains" (si possible) : même qtype + questiontextformat + questiontext (compare_text) strictement identiques.
        // Fallback historique : même name + qtype.
        // IMPORTANT Moodle 4.5+ : exclure les "doublons de versions".
        // On ne considère qu'1 question représentative (dernière version) par entry (questionbankentryid).
        $statusfilter = '';
        if (self::question_versions_has_status()) {
            $statusfilter = " AND v.status <> 'draft' ";
        }
        if (self::can_use_certain_duplicates_definition()) {
            $qtext = $DB->sql_compare_text('q.questiontext');
            $sql = "SELECT q.qtype, q.questiontextformat, COUNT(*) as dup_count, MIN(q.id) as representative_id
                      FROM {question_versions} qv
                      INNER JOIN (
                            SELECT v.questionbankentryid, MAX(v.version) AS maxversion
                              FROM {question_versions} v
                             WHERE v.questionbankentryid IS NOT NULL {$statusfilter}
                          GROUP BY v.questionbankentryid
                      ) mv ON mv.questionbankentryid = qv.questionbankentryid AND mv.maxversion = qv.version
                      INNER JOIN {question} q ON q.id = qv.questionid
                  GROUP BY q.qtype, q.questiontextformat, {$qtext}
                    HAVING COUNT(*) > 1
                  ORDER BY dup_count DESC";
        } else {
            $sql = "SELECT q.name, q.qtype, COUNT(*) as dup_count, MIN(q.id) as representative_id
                      FROM {question_versions} qv
                      INNER JOIN (
                            SELECT v.questionbankentryid, MAX(v.version) AS maxversion
                              FROM {question_versions} v
                             WHERE v.questionbankentryid IS NOT NULL {$statusfilter}
                          GROUP BY v.questionbankentryid
                      ) mv ON mv.questionbankentryid = qv.questionbankentryid AND mv.maxversion = qv.version
                      INNER JOIN {question} q ON q.id = qv.questionid
                  GROUP BY q.name, q.qtype
                    HAVING COUNT(*) > 1
                  ORDER BY dup_count DESC";
        }
        
        // get_recordset_sql() ne nécessite pas de 1ère colonne unique (contrairement à get_records_sql()).
        $all_groups = [];
        $groups_rs = null;
        try {
            $groups_rs = $DB->get_recordset_sql($sql);
            foreach ($groups_rs as $group) {
                $all_groups[] = $group;
            }
        } finally {
            if ($groups_rs) {
                $groups_rs->close();
            }
        }
        
        if (empty($all_groups)) {
            return [];
        }
        
        // 🆕 v1.9.53 : Si deletable_only = true, on va trier les groupes
        // pour mettre en priorité ceux qui ont le plus de versions supprimables
        $groups_with_priority = [];
        
        // Étape 2 : Pour chaque groupe, récupérer les détails
        $groups = [];
        $current_index = 0;
        
        foreach ($all_groups as $group) {
            // Récupérer tous les IDs des questions de ce groupe via representative_id (même définition partout).
            $question_ids = self::get_duplicate_group_question_ids_by_representative_id((int)$group->representative_id);
            
            if (empty($question_ids)) {
                continue;
            }
            
            // Charger l'usage de toutes les questions de ce groupe en batch
            $usage_map = self::get_questions_usage_by_ids($question_ids);
            
            // 🆕 v1.9.53 : Vérifier la supprimabilité si demandé
            $deletability_map = [];
            if ($deletable_only) {
                $deletability_map = self::can_delete_questions_batch($question_ids);
            }
            
            // Compter combien sont utilisées vs inutilisées vs supprimables
            $used_count = 0;
            $unused_count = 0;
            $deletable_count = 0; // 🆕 v1.9.53
            
            foreach ($question_ids as $qid) {
                $is_used = isset($usage_map[$qid]) && isset($usage_map[$qid]['quiz_count']) && $usage_map[$qid]['quiz_count'] > 0;
                
                if ($is_used) {
                    $used_count++;
                } else {
                    $unused_count++;
                }
                
                // 🆕 v1.9.53 : Compter les questions réellement supprimables
                if ($deletable_only && isset($deletability_map[$qid]) && $deletability_map[$qid]->can_delete) {
                    $deletable_count++;
                }
            }
            
            // 🆕 v1.9.53 : Si deletable_only et aucune version supprimable, on skip ce groupe
            if ($deletable_only && $deletable_count == 0) {
                continue;
            }
            
            // Si filtre "used_only" et aucune version utilisée, on skip ce groupe
            if ($used_only && $used_count == 0) {
                continue;
            }
            
            // Créer l'objet groupe (nom affiché = celui de la question représentative).
            $repname = '';
            $repqtype = (string)($group->qtype ?? '');
            try {
                $rep = $DB->get_record('question', ['id' => (int)$group->representative_id], 'id,name,qtype', IGNORE_MISSING);
                if ($rep) {
                    $repname = (string)$rep->name;
                    $repqtype = (string)$rep->qtype;
                }
            } catch (\Exception $e) {
                // Ignore.
            }

            $group_obj = (object)[
                'question_name' => $repname !== '' ? $repname : (string)($group->name ?? ''),
                'qtype' => $repqtype,
                'duplicate_count' => $group->dup_count,
                'representative_id' => $group->representative_id,
                'all_question_ids' => $question_ids,
                'used_count' => $used_count,
                'unused_count' => $unused_count,
                'deletable_count' => $deletable_count, // 🆕 v1.9.53
                'priority_score' => $deletable_count // 🆕 v1.9.53 : Score pour tri
            ];
            
            $groups_with_priority[] = $group_obj;
        }
        
        // 🆕 v1.9.53 : Trier les groupes par nombre de versions supprimables (décroissant)
        // Les groupes avec le plus de doublons supprimables apparaissent en premier
        if ($deletable_only) {
            usort($groups_with_priority, function($a, $b) {
                // Priorité 1 : Nombre de versions supprimables (décroissant)
                if ($a->deletable_count != $b->deletable_count) {
                    return $b->deletable_count - $a->deletable_count;
                }
                // Priorité 2 : Nombre total de doublons (décroissant)
                return $b->duplicate_count - $a->duplicate_count;
            });
        }
        
        // Appliquer la pagination
        $total = count($groups_with_priority);
        $groups = array_slice($groups_with_priority, $offset, $limit > 0 ? $limit : null);
        
        return $groups;
    }

    /**
     * Récupère les groupes de doublons par lot, avec pagination stable (seek) basée sur representative_id.
     *
     * Objectif : permettre de traiter TOUT le site "par lots" sans utiliser offset sur un tri instable
     * (les groupes peuvent changer pendant une fusion).
     *
     * Retourne une liste de groupes, ordonnée par representative_id ASC, dont representative_id > $afterrepid.
     *
     * @param int $limit Nombre max de groupes à retourner
     * @param int $afterrepid Dernier representative_id traité (seek)
     * @return array Liste de groupes (même format que get_duplicate_groups, sans pagination offset)
     */
    public static function get_duplicate_groups_seek(int $limit = 100, int $afterrepid = 0): array {
        global $DB;

        $limit = max(1, min(1000, (int)$limit));
        $afterrepid = max(0, (int)$afterrepid);

        $statusfilter = '';
        if (self::question_versions_has_status()) {
            $statusfilter = " AND v.status <> 'draft' ";
        }

        // 1) Sélectionner une fenêtre de groupes avec HAVING MIN(q.id) > :after.
        if (self::can_use_certain_duplicates_definition()) {
            $qtext = $DB->sql_compare_text('q.questiontext');
            $sql = "SELECT q.qtype, q.questiontextformat, COUNT(*) as dup_count, MIN(q.id) as representative_id
                      FROM {question_versions} qv
                      INNER JOIN (
                            SELECT v.questionbankentryid, MAX(v.version) AS maxversion
                              FROM {question_versions} v
                             WHERE v.questionbankentryid IS NOT NULL {$statusfilter}
                          GROUP BY v.questionbankentryid
                      ) mv ON mv.questionbankentryid = qv.questionbankentryid AND mv.maxversion = qv.version
                      INNER JOIN {question} q ON q.id = qv.questionid
                  GROUP BY q.qtype, q.questiontextformat, {$qtext}
                    HAVING COUNT(*) > 1 AND MIN(q.id) > :after
                  ORDER BY MIN(q.id) ASC";
        } else {
            $sql = "SELECT q.name, q.qtype, COUNT(*) as dup_count, MIN(q.id) as representative_id
                      FROM {question_versions} qv
                      INNER JOIN (
                            SELECT v.questionbankentryid, MAX(v.version) AS maxversion
                              FROM {question_versions} v
                             WHERE v.questionbankentryid IS NOT NULL {$statusfilter}
                          GROUP BY v.questionbankentryid
                      ) mv ON mv.questionbankentryid = qv.questionbankentryid AND mv.maxversion = qv.version
                      INNER JOIN {question} q ON q.id = qv.questionid
                  GROUP BY q.name, q.qtype
                    HAVING COUNT(*) > 1 AND MIN(q.id) > :after
                  ORDER BY MIN(q.id) ASC";
        }

        $groups = array_values($DB->get_records_sql($sql, ['after' => $afterrepid], 0, $limit));
        if (empty($groups)) {
            return [];
        }

        // 2) Enrichir chaque groupe comme get_duplicate_groups() (ids, usage counts, etc.).
        // Pour éviter une refacto lourde, on réutilise la logique existante :
        // - on construit un "all_groups" temporaire et on exécute le même pipeline interne.
        //
        // NB : cette méthode ne supporte pas used_only / deletable_only car le besoin est le batch merge.
        $out = [];
        foreach ($groups as $group) {
            $question_ids = self::get_duplicate_group_question_ids_by_representative_id((int)$group->representative_id);
            if (empty($question_ids)) {
                continue;
            }
            $usage_map = self::get_questions_usage_by_ids($question_ids);

            $used_count = 0;
            $unused_count = 0;
            foreach ($question_ids as $qid) {
                $is_used = isset($usage_map[$qid]) && isset($usage_map[$qid]['quiz_count']) && $usage_map[$qid]['quiz_count'] > 0;
                if ($is_used) {
                    $used_count++;
                } else {
                    $unused_count++;
                }
            }

            $repname = '';
            $repqtype = (string)($group->qtype ?? '');
            try {
                $rep = $DB->get_record('question', ['id' => (int)$group->representative_id], 'id,name,qtype', IGNORE_MISSING);
                if ($rep) {
                    $repname = (string)$rep->name;
                    $repqtype = (string)$rep->qtype;
                }
            } catch (\Exception $e) {
                // Ignore.
            }

            $out[] = (object)[
                'question_name' => $repname !== '' ? $repname : (string)($group->name ?? ''),
                'qtype' => $repqtype,
                'duplicate_count' => $group->dup_count,
                'representative_id' => $group->representative_id,
                'all_question_ids' => $question_ids,
                'used_count' => $used_count,
                'unused_count' => $unused_count,
                // Compat avec l'ancien tri/priorité.
                'deletable_count' => 0,
                'priority_score' => 0,
            ];
        }

        return $out;
    }
    
    /**
     * Compte le nombre total de groupes de doublons
     * 
     * 🆕 v1.9.53 : Support du paramètre deletable_only
     * 
     * @param bool $used_only Si true, ne compter que les groupes avec au moins 1 version utilisée
     * @param bool $deletable_only Si true, ne compter que les groupes avec au moins 1 version supprimable
     * @return int Nombre de groupes de doublons
     */
    public static function count_duplicate_groups($used_only = false, $deletable_only = false) {
        global $DB;
        
        // 🎯 Compter le nombre total de groupes (même définition que get_duplicate_groups()).
        $statusfilter = '';
        if (self::question_versions_has_status()) {
            $statusfilter = " AND v.status <> 'draft' ";
        }
        if (self::can_use_certain_duplicates_definition()) {
            $qtext = $DB->sql_compare_text('q.questiontext');
            $sql = "SELECT q.qtype, q.questiontextformat, COUNT(*) as dup_count, MIN(q.id) as representative_id
                      FROM {question_versions} qv
                      INNER JOIN (
                            SELECT v.questionbankentryid, MAX(v.version) AS maxversion
                              FROM {question_versions} v
                             WHERE v.questionbankentryid IS NOT NULL {$statusfilter}
                          GROUP BY v.questionbankentryid
                      ) mv ON mv.questionbankentryid = qv.questionbankentryid AND mv.maxversion = qv.version
                      INNER JOIN {question} q ON q.id = qv.questionid
                  GROUP BY q.qtype, q.questiontextformat, {$qtext}
                    HAVING COUNT(*) > 1";
        } else {
            $sql = "SELECT q.name, q.qtype, COUNT(*) as dup_count, MIN(q.id) as representative_id
                      FROM {question_versions} qv
                      INNER JOIN (
                            SELECT v.questionbankentryid, MAX(v.version) AS maxversion
                              FROM {question_versions} v
                             WHERE v.questionbankentryid IS NOT NULL {$statusfilter}
                          GROUP BY v.questionbankentryid
                      ) mv ON mv.questionbankentryid = qv.questionbankentryid AND mv.maxversion = qv.version
                      INNER JOIN {question} q ON q.id = qv.questionid
                  GROUP BY q.name, q.qtype
                    HAVING COUNT(*) > 1";
        }
        
        // get_recordset_sql() ne nécessite pas de 1ère colonne unique (contrairement à get_records_sql()).
        $all_groups = [];
        $groups_rs = null;
        try {
            $groups_rs = $DB->get_recordset_sql($sql);
            foreach ($groups_rs as $group) {
                $all_groups[] = $group;
            }
        } finally {
            if ($groups_rs) {
                $groups_rs->close();
            }
        }
        
        // Si aucun filtre, retourner le total
        if (!$used_only && !$deletable_only) {
            return count($all_groups);
        }
        
        // Si filtre actif, on doit compter manuellement (plus lent mais nécessaire)
        $count = 0;
        foreach ($all_groups as $group) {
            // Récupérer les IDs des questions de ce groupe via representative_id.
            $question_ids = self::get_duplicate_group_question_ids_by_representative_id((int)($group->representative_id ?? 0));
            
            if (empty($question_ids)) {
                continue;
            }
            
            // Charger l'usage en batch
            $usage_map = self::get_questions_usage_by_ids($question_ids);
            
            // 🆕 v1.9.53 : Charger la supprimabilité si demandé
            $deletability_map = [];
            if ($deletable_only) {
                $deletability_map = self::can_delete_questions_batch($question_ids);
            }
            
            // Vérifier les conditions
            $has_used = false;
            $has_deletable = false;
            
            foreach ($question_ids as $qid) {
                // Vérifier si utilisée
                if (isset($usage_map[$qid]) && isset($usage_map[$qid]['quiz_count']) && $usage_map[$qid]['quiz_count'] > 0) {
                    $has_used = true;
                }
                
                // 🆕 v1.9.53 : Vérifier si supprimable
                if ($deletable_only && isset($deletability_map[$qid]) && $deletability_map[$qid]->can_delete) {
                    $has_deletable = true;
                }
                
                // Si on a trouvé ce qu'on cherche, on peut arrêter
                if ((!$used_only || $has_used) && (!$deletable_only || $has_deletable)) {
                    break;
                }
            }
            
            // Appliquer les filtres
            $include = true;
            if ($used_only && !$has_used) {
                $include = false;
            }
            if ($deletable_only && !$has_deletable) {
                $include = false;
            }
            
            if ($include) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Calcule le score d'accessibilité d'une question basé sur son contexte.
     *
     * Score plus élevé = contexte plus large/accessible.
     *
     * Priorité :
     * - CONTEXT_SYSTEM (50) > CONTEXT_COURSECAT (40) > CONTEXT_COURSE (30) > CONTEXT_MODULE (20) > défaut (10)
     *
     * @param object $question Objet question (doit contenir au moins id, timecreated)
     * @return object {score: int, contextlevel: ?int, contextid: ?int, timecreated: int, info: string}
     */
    public static function get_question_accessibility_score($question) {
        global $DB;
        
        $result = (object)[
            'score' => 0,
            'contextlevel' => null,
            'contextid' => null,
            'timecreated' => isset($question->timecreated) ? (int)$question->timecreated : 0,
            'info' => 'Contexte inconnu'
        ];
        
        if (!is_object($question) || empty($question->id)) {
            $result->score = 1;
            $result->info = 'Question invalide';
            return $result;
        }
        
        try {
            // Récupérer la catégorie et le contexte de la question via question_bank_entries / question_versions (Moodle 4.x+).
            $sql = "SELECT qc.contextid, ctx.contextlevel
                      FROM {question_categories} qc
                      INNER JOIN {question_bank_entries} qbe ON qbe.questioncategoryid = qc.id
                      INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                      INNER JOIN {context} ctx ON ctx.id = qc.contextid
                     WHERE qv.questionid = :questionid";
            
            $record = $DB->get_record_sql($sql, ['questionid' => (int)$question->id]);
            
            if (!$record) {
                $result->info = 'Catégorie/contexte introuvable';
                $result->score = 5;
                return $result;
            }
            
            $result->contextid = (int)$record->contextid;
            $result->contextlevel = (int)$record->contextlevel;
            
            switch ((int)$record->contextlevel) {
                case CONTEXT_SYSTEM:
                    $result->score = 50;
                    $result->info = '🌐 Site entier (le plus accessible)';
                    break;
                case CONTEXT_COURSECAT:
                    $result->score = 40;
                    $result->info = '📂 Catégorie de cours';
                    break;
                case CONTEXT_COURSE:
                    $result->score = 30;
                    $result->info = '📚 Cours spécifique';
                    break;
                case CONTEXT_MODULE:
                    $result->score = 20;
                    $result->info = '📝 Module d\'activité';
                    break;
                default:
                    $result->score = 10;
                    $result->info = 'Contexte non standard (niveau ' . (int)$record->contextlevel . ')';
                    break;
            }
        } catch (\Exception $e) {
            debugging('Erreur calcul accessibilité pour question ' . (int)$question->id . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            $result->score = 1;
            $result->info = 'Erreur: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Sélectionne la "meilleure" question à conserver quand un groupe de doublons
     * ne contient aucune version utilisée (sécurité : on conserve 1 version).
     *
     * Critères :
     * 1) Contexte le plus large (score d'accessibilité décroissant)
     * 2) Plus ancienne (timecreated croissant) en cas d'égalité
     *
     * @param array $questions Tableau d'objets question
     * @return object|null {question: object, score: int, timecreated: int, info: string}
     */
    public static function select_best_question_to_keep(array $questions) {
        $candidates = [];
        
        foreach ($questions as $q) {
            if (!is_object($q) || empty($q->id)) {
                continue;
            }
            $scoreinfo = self::get_question_accessibility_score($q);
            $candidates[] = (object)[
                'question' => $q,
                'score' => (int)$scoreinfo->score,
                'timecreated' => isset($q->timecreated) ? (int)$q->timecreated : (int)$scoreinfo->timecreated,
                'info' => $scoreinfo->info
            ];
        }
        
        if (empty($candidates)) {
            return null;
        }
        
        usort($candidates, function($a, $b) {
            if ($a->score !== $b->score) {
                return $b->score - $a->score; // Score décroissant.
            }
            return $a->timecreated - $b->timecreated; // Plus ancien d'abord.
        });
        
        return $candidates[0];
    }
    
    /**
     * Génère les statistiques de prévisualisation pour le nettoyage global des doublons
     * 
     * 🆕 v1.9.52 : Pour le nettoyage global
     * 
     * Analyse TOUS les groupes de doublons et calcule :
     * - Nombre total de groupes
     * - Nombre de questions à supprimer
     * - Nombre de questions à conserver
     * - Répartition par type de question
     * - Liste détaillée des questions à supprimer (pour export CSV)
     * 
     * @return object Statistiques de prévisualisation
     */
    public static function get_cleanup_preview_stats() {
        global $DB;
        
        $stats = new \stdClass();
        
        // 🆕 v1.9.53 : OPTIMISATION - Récupérer uniquement les groupes avec versions supprimables
        // Évite de traiter des groupes où toutes les versions sont protégées
        $all_groups = self::get_duplicate_groups(0, 0, false, true); // deletable_only = true
        
        $stats->total_groups = count($all_groups);
        $stats->total_questions_to_delete = 0;
        $stats->total_questions_to_keep = 0;
        $stats->by_type = []; // [qtype => ['to_delete' => N, 'to_keep' => M]]
        $stats->questions_list = []; // Liste détaillée pour export CSV
        
        foreach ($all_groups as $group) {
            // Analyser chaque groupe
            $question_ids = $group->all_question_ids;
            
            if (empty($question_ids)) {
                continue;
            }
            
            $usage_map = self::get_questions_usage_by_ids($question_ids);
            
            // Récupérer les questions complètes pour détails
            $questions = $DB->get_records_list('question', 'id', $question_ids, 'id ASC');
            
            $unused = [];
            $used = [];
            
            foreach ($questions as $q) {
                $quiz_count = isset($usage_map[$q->id]['quiz_count']) ? 
                             $usage_map[$q->id]['quiz_count'] : 0;
                
                if ($quiz_count > 0) {
                    $used[] = $q;
                } else {
                    $unused[] = $q;
                }
            }
            
            // Sécurité : Garder au moins 1 version si aucune utilisée
            if (empty($used) && !empty($unused)) {
                // 🆕 v1.11.20 : Sélection intelligente (contexte le plus large, puis plus ancienne).
                $best = self::select_best_question_to_keep($unused);
                if ($best) {
                    $used[] = $best->question;
                    $unused = array_values(array_filter($unused, function($q) use ($best) {
                        return (int)$q->id !== (int)$best->question->id;
                    }));
                } else {
                    // Fallback : garder la première (comportement historique).
                    $oldest = array_shift($unused);
                    $used[] = $oldest;
                }
            }
            
            $group_to_delete = count($unused);
            $group_to_keep = count($used);
            
            // Accumuler les totaux
            $stats->total_questions_to_delete += $group_to_delete;
            $stats->total_questions_to_keep += $group_to_keep;
            
            // Accumuler par type
            if (!isset($stats->by_type[$group->qtype])) {
                $stats->by_type[$group->qtype] = [
                    'to_delete' => 0,
                    'to_keep' => 0
                ];
            }
            $stats->by_type[$group->qtype]['to_delete'] += $group_to_delete;
            $stats->by_type[$group->qtype]['to_keep'] += $group_to_keep;
            
            // Liste détaillée pour CSV (seulement les questions à supprimer)
            foreach ($unused as $q) {
                $stats->questions_list[] = (object)[
                    'id' => $q->id,
                    'name' => $q->name,
                    'qtype' => $q->qtype,
                    'timecreated' => $q->timecreated,
                    'action' => 'delete'
                ];
            }
        }
        
        // Estimation du temps (environ 0.5s par groupe)
        $stats->estimated_time_seconds = $stats->total_groups * 0.5;
        $stats->estimated_batches = ceil($stats->total_groups / 10);
        
        return $stats;
    }

    /**
     * 🆕 Diagnostic : Vérifie la cohérence des QUESTIONS (lecture seule).
     *
     * Objectif : détecter des incohérences structurelles susceptibles de casser la banque de questions
     * (liens orphelins, versioning incohérent, types de questions inconnus, etc.).
     *
     * ⚠️ IMPORTANT : aucun changement n'est effectué. Le diagnostic s'adapte à la structure réelle
     * de la base (Moodle 4.5+) via $DB->get_manager() et $DB->get_columns().
     *
     * @param int $samplelimit Nombre maximum d'éléments listés par check
     * @return \stdClass Rapport structuré
     */
    public static function get_questions_integrity_report(int $samplelimit = 50): \stdClass {
        global $DB;

        $report = (object)[
            'generatedat' => time(),
            'summary' => (object)[
                'questions' => 0,
                'entries' => 0,
                'versions' => 0,
                'errors' => 0,
                'warnings' => 0,
            ],
            'checks' => [],
        ];

        $dbman = $DB->get_manager();

        // Vérifier que les tables de la nouvelle architecture existent (Moodle 4.0+).
        if (!$dbman->table_exists('question_bank_entries') || !$dbman->table_exists('question_versions')) {
            $report->checks['missing_question_bank_tables'] = (object)[
                'severity' => 'error',
                'title' => 'Architecture Question Bank manquante',
                'description' => 'Les tables question_bank_entries et/ou question_versions n’existent pas. Ce plugin cible Moodle 4.5+ (architecture de versioning).',
                'count' => 1,
                'sample' => [],
            ];
            $report->summary->errors = 1;
            return $report;
        }

        // Stats de base (rapides).
        try {
            $report->summary->questions = (int)$DB->count_records('question');
            $report->summary->entries = (int)$DB->count_records('question_bank_entries');
            $report->summary->versions = (int)$DB->count_records('question_versions');
        } catch (\Exception $e) {
            // Ne pas bloquer l'affichage.
        }

        $addcheck = function(string $key, string $severity, string $title, string $description, array $items, ?int $countoverride = null) use (&$report, $samplelimit) {
            $count = $countoverride !== null ? (int)$countoverride : count($items);
            $sample = $items;
            if ($samplelimit > 0 && count($items) > $samplelimit) {
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

        $qvcols = $DB->get_columns('question_versions');
        $hasqvstatus = isset($qvcols['status']);

        // 1) question_versions → question manquant.
        try {
            $count = (int)$DB->count_records_sql("
                SELECT COUNT(1)
                  FROM {question_versions} qv
             LEFT JOIN {question} q ON q.id = qv.questionid
                 WHERE q.id IS NULL
            ");

            $fields = "qv.id, qv.questionid, qv.questionbankentryid, qv.version";
            if ($hasqvstatus) {
                $fields .= ", qv.status";
            }
            $sample = array_values($DB->get_records_sql("
                SELECT $fields
                  FROM {question_versions} qv
             LEFT JOIN {question} q ON q.id = qv.questionid
                 WHERE q.id IS NULL
              ORDER BY qv.id ASC
            ", [], 0, $samplelimit));

            $addcheck(
                'orphan_question_versions_missing_question',
                'error',
                'Versions orphelines (question manquante)',
                'Des enregistrements dans question_versions pointent vers une question inexistante (incohérence critique).',
                $sample,
                $count
            );
        } catch (\Exception $e) {
            // Ignore.
        }

        // 2) question_versions → question_bank_entries manquante.
        try {
            $count = (int)$DB->count_records_sql("
                SELECT COUNT(1)
                  FROM {question_versions} qv
             LEFT JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                 WHERE qbe.id IS NULL
            ");

            $fields = "qv.id, qv.questionid, qv.questionbankentryid, qv.version";
            if ($hasqvstatus) {
                $fields .= ", qv.status";
            }
            $sample = array_values($DB->get_records_sql("
                SELECT $fields
                  FROM {question_versions} qv
             LEFT JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                 WHERE qbe.id IS NULL
              ORDER BY qv.id ASC
            ", [], 0, $samplelimit));

            $addcheck(
                'orphan_question_versions_missing_entry',
                'error',
                'Versions orphelines (entrée QBE manquante)',
                'Des enregistrements dans question_versions pointent vers une entrée inexistante (question_bank_entries).',
                $sample,
                $count
            );
        } catch (\Exception $e) {
            // Ignore.
        }

        // 3) question_bank_entries → catégorie manquante.
        try {
            $count = (int)$DB->count_records_sql("
                SELECT COUNT(1)
                  FROM {question_bank_entries} qbe
             LEFT JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                 WHERE qc.id IS NULL
            ");

            $sample = array_values($DB->get_records_sql("
                SELECT qbe.id AS entryid, qbe.questioncategoryid
                  FROM {question_bank_entries} qbe
             LEFT JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                 WHERE qc.id IS NULL
              ORDER BY qbe.id ASC
            ", [], 0, $samplelimit));

            $addcheck(
                'orphan_entries_missing_category',
                'error',
                'Entrées orphelines (catégorie manquante)',
                'Des entrées de banque de questions pointent vers une catégorie inexistante (question_categories).',
                $sample,
                $count
            );
        } catch (\Exception $e) {
            // Ignore.
        }

        // 4) Questions sans version (question non référencée dans question_versions).
        try {
            $count = (int)$DB->count_records_sql("
                SELECT COUNT(1)
                  FROM {question} q
             LEFT JOIN {question_versions} qv ON qv.questionid = q.id
                 WHERE qv.questionid IS NULL
            ");

            $sample = array_values($DB->get_records_sql("
                SELECT q.id, q.name, q.qtype, q.timecreated
                  FROM {question} q
             LEFT JOIN {question_versions} qv ON qv.questionid = q.id
                 WHERE qv.questionid IS NULL
              ORDER BY q.id ASC
            ", [], 0, $samplelimit));

            $addcheck(
                'orphan_questions_missing_version',
                'warning',
                'Questions orphelines (sans version)',
                'Des enregistrements dans {question} ne sont référencés par aucune version (question_versions). Cela peut venir d’imports/erreurs anciennes.',
                $sample,
                $count
            );
        } catch (\Exception $e) {
            // Ignore.
        }

        // 5) Entrées sans versions (question_bank_entries sans question_versions).
        try {
            $count = (int)$DB->count_records_sql("
                SELECT COUNT(1)
                  FROM {question_bank_entries} qbe
             LEFT JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                 WHERE qv.questionbankentryid IS NULL
            ");

            $sample = array_values($DB->get_records_sql("
                SELECT qbe.id AS entryid, qbe.questioncategoryid
                  FROM {question_bank_entries} qbe
             LEFT JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                 WHERE qv.questionbankentryid IS NULL
              ORDER BY qbe.id ASC
            ", [], 0, $samplelimit));

            $addcheck(
                'orphan_entries_missing_versions',
                'warning',
                'Entrées orphelines (sans versions)',
                'Des entrées de banque de questions ne possèdent aucune version (question_versions).',
                $sample,
                $count
            );
        } catch (\Exception $e) {
            // Ignore.
        }

        // 6) Plusieurs "dernières versions" pour une même entrée (version max dupliquée).
        try {
            // Base query (sans ORDER BY) pour rester portable dans les sous-requêtes.
            $basesql = "
                SELECT qv.questionbankentryid AS entryid,
                       mv.maxversion AS maxversion,
                       COUNT(1) AS cnt
                  FROM {question_versions} qv
                  JOIN (
                        SELECT questionbankentryid, MAX(version) AS maxversion
                          FROM {question_versions}
                      GROUP BY questionbankentryid
                       ) mv
                    ON mv.questionbankentryid = qv.questionbankentryid
                   AND mv.maxversion = qv.version
              GROUP BY qv.questionbankentryid, mv.maxversion
                HAVING COUNT(1) > 1
            ";

            $samplesql = $basesql . " ORDER BY cnt DESC, entryid ASC";

            $rows = array_values($DB->get_records_sql($samplesql, [], 0, $samplelimit));
            $count = (int)$DB->count_records_sql("SELECT COUNT(1) FROM ($basesql) t", []);

            $addcheck(
                'duplicate_latest_versions_per_entry',
                'error',
                'Versioning incohérent (plusieurs dernières versions)',
                'Pour une même entrée (question_bank_entries), plusieurs lignes partagent la version maximale. Cela peut provoquer des comportements inattendus.',
                $rows,
                $count
            );
        } catch (\Exception $e) {
            // Ignore.
        }

        // 7) question_references → entrée QBE manquante (Moodle 4.5+).
        if ($dbman->table_exists('question_references')) {
            try {
                $qrcols = $DB->get_columns('question_references');
                if (isset($qrcols['questionbankentryid'])) {
                    $count = (int)$DB->count_records_sql("
                        SELECT COUNT(1)
                          FROM {question_references} qr
                     LEFT JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                         WHERE qbe.id IS NULL
                    ");

                    $sample = array_values($DB->get_records_sql("
                        SELECT qr.id, qr.component, qr.questionarea, qr.itemid, qr.questionbankentryid
                          FROM {question_references} qr
                     LEFT JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                         WHERE qbe.id IS NULL
                      ORDER BY qr.id ASC
                    ", [], 0, $samplelimit));

                    $addcheck(
                        'orphan_question_references_missing_entry',
                        'error',
                        'Références orphelines (question_references → entrée manquante)',
                        'Des références déclaratives (question_references) pointent vers une entrée inexistante.',
                        $sample,
                        $count
                    );
                }

                if (isset($qrcols['contextid'])) {
                    $count = (int)$DB->count_records_sql("
                        SELECT COUNT(1)
                          FROM {question_references} qr
                     LEFT JOIN {context} ctx ON ctx.id = qr.contextid
                         WHERE ctx.id IS NULL
                    ");

                    $sample = array_values($DB->get_records_sql("
                        SELECT qr.id, qr.contextid, qr.component, qr.questionarea, qr.itemid, qr.questionbankentryid
                          FROM {question_references} qr
                     LEFT JOIN {context} ctx ON ctx.id = qr.contextid
                         WHERE ctx.id IS NULL
                      ORDER BY qr.id ASC
                    ", [], 0, $samplelimit));

                    $addcheck(
                        'orphan_question_references_missing_context',
                        'warning',
                        'Références avec contexte manquant',
                        'Des références déclaratives portent un contextid inexistant (souvent dû à suppression de cours/module).',
                        $sample,
                        $count
                    );
                }
            } catch (\Exception $e) {
                // Ignore.
            }
        }

        // 8) Types de questions inconnus (plugin qtype manquant).
        try {
            $installed = [];
            if (class_exists('\\core_component')) {
                $installed = array_keys((array)\core_component::get_plugin_list('qtype'));
            }

            $qtypes = $DB->get_records_sql("
                SELECT q.qtype, COUNT(1) AS cnt
                  FROM {question} q
              GROUP BY q.qtype
              ORDER BY cnt DESC, q.qtype ASC
            ");

            $unknown = [];
            foreach ($qtypes as $row) {
                $qtype = (string)($row->qtype ?? '');
                if ($qtype === '') {
                    continue;
                }
                if (!empty($installed) && !in_array($qtype, $installed, true)) {
                    $unknown[] = (object)[
                        'qtype' => $qtype,
                        'count' => (int)($row->cnt ?? 0),
                    ];
                }
            }

            $addcheck(
                'unknown_question_types',
                'warning',
                'Types de questions inconnus',
                'Des questions utilisent un qtype qui n’existe pas/plus sur le site (plugin désinstallé). Ces questions peuvent devenir impossibles à éditer/afficher.',
                $unknown
            );
        } catch (\Exception $e) {
            // Ignore.
        }

        // 9) Noms vides.
        try {
            $count = (int)$DB->count_records_sql("
                SELECT COUNT(1)
                  FROM {question} q
                 WHERE q.name IS NULL OR q.name = ''
            ");
            $sample = array_values($DB->get_records_sql("
                SELECT q.id, q.qtype, q.timecreated
                  FROM {question} q
                 WHERE q.name IS NULL OR q.name = ''
              ORDER BY q.id ASC
            ", [], 0, $samplelimit));
            $addcheck(
                'empty_question_name',
                'warning',
                'Nom de question vide',
                'Certaines questions ont un champ name vide (souvent dû à import/bug).',
                $sample,
                $count
            );
        } catch (\Exception $e) {
            // Ignore.
        }

        // 10) Valeurs de status (info) si la colonne existe.
        if ($hasqvstatus) {
            try {
                $statuses = $DB->get_records_sql("
                    SELECT qv.status, COUNT(1) AS cnt
                      FROM {question_versions} qv
                  GROUP BY qv.status
                  ORDER BY cnt DESC
                ", [], 0, $samplelimit);
                $items = [];
                foreach ($statuses as $row) {
                    $items[] = (object)[
                        'status' => $row->status,
                        'count' => (int)$row->cnt,
                    ];
                }
                $addcheck(
                    'question_version_status_values',
                    'info',
                    'Statuts de versions (information)',
                    'Répartition des valeurs de question_versions.status observées dans la base.',
                    $items
                );
            } catch (\Exception $e) {
                // Ignore.
            }
        }

        return $report;
    }
    
    /**
     * Récupère les questions inutilisées (avec limite et pagination)
     * 🆕 v1.10.1 : Nouvelle méthode pour la page des questions inutilisées
     *
     * @param int $limit Limite du nombre de questions (défaut: 50)
     * @param int $offset Offset pour la pagination (défaut: 0)
     * @return array Tableau des questions inutilisées
     */
    public static function get_unused_questions($limit = 50, $offset = 0) {
        global $DB;

        try {
            // 🔧 v1.12.x : IMPORTANT (Moodle 4.x) — Les questions sont versionnées.
            // Une "question logique" = question_bank_entries (QBE) ; plusieurs enregistrements existent dans {question}.
            //
            // L’ancien calcul en {question}.id pouvait classer à tort comme "inutilisée" une version
            // alors que l’entrée QBE est bien référencée (ex: quiz qui pointe l’entrée, puis question éditée).
            //
            // Solution : calculer l’inutilisation AU NIVEAU DES ENTRÉES (QBE), puis afficher la dernière version.
            $dbman = $DB->get_manager();

            if (!$dbman->table_exists('question_bank_entries') || !$dbman->table_exists('question_versions')) {
                // Architecture non attendue (pré-4.0) : on force le fallback PHP plus bas.
                throw new \moodle_exception('missingquestionbanktables', 'local_question_diagnostic');
            }

            // 1) Déterminer les entrées de questions "utilisées" (références déclaratives ou quiz_slots en fallback).
            $used_entries_sql = null;

            if ($dbman->table_exists('question_references')) {
                // Moodle 4.5+ : usages déclaratifs (quiz, leçon, etc.) au niveau de l’entrée.
                $used_entries_sql = "SELECT DISTINCT qr.questionbankentryid AS entryid
                                       FROM {question_references} qr";
            } else {
                // Moodle 4.0-4.4 : fallback via quiz_slots (selon colonnes).
                if ($dbman->table_exists('quiz_slots')) {
                    $columns = $DB->get_columns('quiz_slots');
                    if (isset($columns['questionbankentryid'])) {
                        // Moodle 4.1-4.4 : qs.questionbankentryid.
                        $used_entries_sql = "SELECT DISTINCT qs.questionbankentryid AS entryid
                                               FROM {quiz_slots} qs
                                              WHERE qs.questionbankentryid IS NOT NULL";
                    } else if (isset($columns['questionid'])) {
                        // Moodle 4.0 : qs.questionid (mapper vers entrée via question_versions).
                        $used_entries_sql = "SELECT DISTINCT qv.questionbankentryid AS entryid
                                               FROM {quiz_slots} qs
                                               JOIN {question_versions} qv ON qv.questionid = qs.questionid";
                    }
                }
            }

            if (empty($used_entries_sql)) {
                // Worst-case : pas de source d'usage déclaratif. On ne bloquera que via tentatives.
                $used_entries_sql = "SELECT 0 AS entryid";
            }

            // 2) Entrées "utilisées" via tentatives (les tentatives pointent une version {question}.id).
            $used_entries_in_attempts_sql = "SELECT DISTINCT qv.questionbankentryid AS entryid
                                               FROM {question_attempts} qa
                                               JOIN {question_versions} qv ON qv.questionid = qa.questionid";

            // 3) Entrées "inutilisées" = pas de référence + pas de tentative.
            //    Puis on renvoie la DERNIÈRE version (MAX(version)) pour affichage.
            $unused_sql = "SELECT q.*
                             FROM {question_bank_entries} qbe
                             JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                             JOIN {question} q ON q.id = qv.questionid
                            WHERE qv.version = (
                                    SELECT MAX(v.version)
                                      FROM {question_versions} v
                                     WHERE v.questionbankentryid = qbe.id
                                  )
                              AND qbe.id NOT IN ($used_entries_sql)
                              AND qbe.id NOT IN ($used_entries_in_attempts_sql)
                         ORDER BY q.id DESC";

            return $DB->get_records_sql($unused_sql, [], $offset, $limit);
            
        } catch (\Exception $e) {
            debugging('Erreur lors de la récupération des questions inutilisées : ' . $e->getMessage(), DEBUG_DEVELOPER);
            
            // Fallback : Retourner toutes les questions et filtrer côté PHP (moins optimal)
            $all_questions = $DB->get_records('question', null, 'id DESC', '*', $offset, $limit);
            $unused = [];
            
            foreach ($all_questions as $question) {
                $usage = self::get_question_usage($question->id);
                if (!$usage['is_used']) {
                    $unused[] = $question;
                }
            }
            
            return $unused;
        }
    }
}

