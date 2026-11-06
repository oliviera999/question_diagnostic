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

namespace local_question_diagnostic\task;

use local_question_diagnostic\category_manager;
use local_question_diagnostic\local\notifier;

/**
 * Suppression, en tâche de fond, d'une liste FIGÉE de catégories vides.
 *
 * La liste (customdata->categoryids) est calculée une seule fois, au lancement, et ne contient que des catégories
 * vides et sans sous-catégorie à cet instant. Aucune catégorie n'y est ajoutée pendant le traitement : un parent
 * devenu vide n'est pas supprimé (pas de cascade). Chaque catégorie est revérifiée par
 * category_manager::delete_category() juste avant sa suppression par l'API du cœur.
 *
 * @package    local_question_diagnostic
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_categories_task extends \core\task\adhoc_task {

    /** Nombre maximal de refus détaillés dans le rapport. */
    const REPORT_MAX_LINES = 200;

    /**
     * Nom de la tâche.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_cleanup_categories', 'local_question_diagnostic');
    }

    /**
     * Exécute la suppression de la liste figée.
     */
    public function execute() {
        global $CFG;
        require_once($CFG->dirroot . '/local/question_diagnostic/lib.php');
        require_once($CFG->dirroot . '/local/question_diagnostic/classes/category_manager.php');

        \core_php_time_limit::raise();
        raise_memory_limit(MEMORY_EXTRA);

        $data = $this->get_custom_data();
        $requester = (int)($data->userid ?? 0);
        $ids = array_map('intval', (array)($data->categoryids ?? []));

        $result = category_manager::delete_frozen_category_list($ids);
        if (!empty($result->deleted)) {
            \local_question_diagnostic\question_analyzer::purge_all_caches();
        }

        $lines = [
            get_string('report_cleanup_categories_planned', 'local_question_diagnostic', count($ids)),
            get_string('report_cleanup_categories_deleted', 'local_question_diagnostic', count($result->deleted)),
            get_string('report_cleanup_categories_skipped', 'local_question_diagnostic', count($result->skipped)),
        ];
        $shown = 0;
        foreach ($result->skipped as $id => $reason) {
            if ($shown++ >= self::REPORT_MAX_LINES) {
                $lines[] = '  - …';
                break;
            }
            $lines[] = '  - #' . (int)$id . ' : ' . $reason;
        }
        $body = implode("\n", $lines);
        mtrace($body);

        if ($requester) {
            notifier::send($requester, get_string('task_cleanup_categories', 'local_question_diagnostic'), $body,
                new \moodle_url('/local/question_diagnostic/categories.php'));
        }
    }

    /**
     * Programme la suppression d'une liste figée de catégories pour l'utilisateur courant.
     *
     * @param int[] $categoryids liste figée (déjà filtrée au lancement)
     * @param string $origin origine de la demande (journal d'audit)
     * @return void
     */
    public static function queue(array $categoryids, string $origin): void {
        global $USER;
        $ids = array_values(array_unique(array_filter(array_map('intval', $categoryids), fn($id) => $id > 0)));
        $task = new self();
        $task->set_userid((int)$USER->id);
        $task->set_custom_data([
            'userid' => (int)$USER->id,
            'categoryids' => $ids,
            'origin' => $origin,
            'frozenat' => time(),
        ]);
        \core\task\manager::queue_adhoc_task($task, true);
        \local_question_diagnostic\audit_logger::log_action('cleanup_categories_queued', [
            'origin' => $origin,
            'count' => count($ids),
        ]);
    }
}
