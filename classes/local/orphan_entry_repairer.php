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
 * Entrées de banque orphelines (catégorie disparue) : réaffectation et suppression sûres.
 *
 * La catégorie d'origine n'existe plus, donc l'API du cœur (question_move_questions_to_category) ne peut pas
 * servir : on met à jour l'entrée, puis on déplace les fichiers de chaque version vers le contexte de la
 * catégorie cible avec l'API du type de question (move_files), on vide les caches et on déclenche l'événement.
 *
 * @package    local_question_diagnostic
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class orphan_entry_repairer {

    /**
     * Raison pour laquelle la catégorie ne peut pas recevoir d'entrée, ou null si elle convient.
     *
     * @param \stdClass $category
     * @return string|null
     */
    public static function target_problem(\stdClass $category): ?string {
        if ((int)$category->parent === 0) {
            return get_string('orphan_entry_target_top', 'local_question_diagnostic');
        }
        $context = \context::instance_by_id((int)$category->contextid, IGNORE_MISSING);
        if (!$context || $context->contextlevel != CONTEXT_MODULE) {
            return get_string('orphan_entry_target_context', 'local_question_diagnostic');
        }
        return null;
    }

    /**
     * Réaffecte une entrée orpheline à une catégorie.
     *
     * @param int $entryid
     * @param int $targetcategoryid
     * @return int nombre de versions déplacées
     * @throws \moodle_exception si l'opération est refusée (rien n'est modifié)
     */
    public static function reassign(int $entryid, int $targetcategoryid): int {
        global $CFG, $DB;
        require_once($CFG->libdir . '/questionlib.php');

        $target = $DB->get_record('question_categories', ['id' => $targetcategoryid], '*', MUST_EXIST);
        if ($problem = self::target_problem($target)) {
            throw new \moodle_exception('orphan_entry_refused', 'local_question_diagnostic', '', $problem);
        }
        $entry = $DB->get_record('question_bank_entries', ['id' => $entryid], '*', MUST_EXIST);
        if ($DB->record_exists('question_categories', ['id' => $entry->questioncategoryid])) {
            throw new \moodle_exception('orphan_entry_refused', 'local_question_diagnostic', '',
                get_string('orphan_entry_not_orphan', 'local_question_diagnostic'));
        }
        if ($entry->idnumber !== null && $entry->idnumber !== ''
                && $DB->record_exists('question_bank_entries',
                    ['questioncategoryid' => $target->id, 'idnumber' => $entry->idnumber])) {
            throw new \moodle_exception('orphan_entry_refused', 'local_question_diagnostic', '',
                get_string('orphan_entry_idnumber', 'local_question_diagnostic', s($entry->idnumber)));
        }

        $questions = $DB->get_records_sql('SELECT q.id, q.qtype
              FROM {question} q
              JOIN {question_versions} qv ON qv.questionid = q.id
             WHERE qv.questionbankentryid = ?', [$entryid]);

        $transaction = $DB->start_delegated_transaction();
        try {
            $DB->set_field('question_bank_entries', 'questioncategoryid', $target->id, ['id' => $entryid]);
            foreach ($questions as $q) {
                // Contexte où se trouvent encore les fichiers de la question (celui de l'ancienne catégorie).
                $oldcontextids = $DB->get_fieldset_sql("SELECT DISTINCT contextid FROM {files}
                     WHERE component = 'question' AND itemid = ? AND filearea IN ('questiontext', 'generalfeedback')
                       AND contextid <> ?", [$q->id, $target->contextid]);
                foreach ($oldcontextids as $oldcontextid) {
                    \question_bank::get_qtype($q->qtype, false)->move_files($q->id, (int)$oldcontextid, (int)$target->contextid);
                }
                \question_bank::notify_question_edited($q->id);
            }
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            // Rollback() annule tout puis relance l'exception.
            $transaction->rollback($e);
        }
        \local_question_diagnostic\audit_logger::log_action('orphan_entry_reassigned', [
            'entryid' => $entryid,
            'targetcategoryid' => (int)$target->id,
            'versions' => count($questions),
        ], $entryid);
        return count($questions);
    }

    /**
     * Supprime une entrée orpheline VIDE (aucune question) et non référencée.
     *
     * @param int $entryid
     * @return bool true si supprimée
     */
    public static function delete_empty(int $entryid): bool {
        global $DB;
        $entry = $DB->get_record('question_bank_entries', ['id' => $entryid]);
        if (!$entry || $DB->record_exists('question_categories', ['id' => $entry->questioncategoryid])) {
            return false;
        }
        $hasquestion = $DB->record_exists_sql('SELECT 1 FROM {question_versions} qv
              JOIN {question} q ON q.id = qv.questionid WHERE qv.questionbankentryid = ?', [$entryid]);
        if ($hasquestion || $DB->record_exists('question_references', ['questionbankentryid' => $entryid])) {
            return false;
        }
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('question_versions', ['questionbankentryid' => $entryid]);
        $DB->delete_records('question_bank_entries', ['id' => $entryid]);
        $transaction->allow_commit();
        \local_question_diagnostic\audit_logger::log_action('orphan_entry_deleted', ['entryid' => $entryid], $entryid);
        return true;
    }
}
