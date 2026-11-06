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

use local_question_diagnostic\local\notifier;
use local_question_diagnostic\question_analyzer;
use local_question_diagnostic\question_merger;

/**
 * Fusion de tous les groupes de doublons fusionnables du site, en tâche de fond.
 *
 * Parcours par curseur (identifiant de la question représentative croissant) ; chaque groupe est fusionné
 * dans sa propre transaction par question_merger (plan recalculé, verrou obligatoire). Un rapport est envoyé
 * à l'utilisateur qui a lancé l'opération.
 *
 * @package    local_question_diagnostic
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class merge_duplicates_task extends \core\task\adhoc_task {

    /** Taille d'une page de groupes. */
    const PAGE = 100;

    /**
     * Nom de la tâche.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_merge_duplicates', 'local_question_diagnostic');
    }

    /**
     * Exécute la fusion.
     */
    public function execute() {
        \core_php_time_limit::raise();
        raise_memory_limit(MEMORY_EXTRA);

        $data = $this->get_custom_data();
        $requester = (int)($data->userid ?? 0);
        $totals = ['groups' => 0, 'merged' => 0, 'deleted' => 0, 'failed' => 0];
        $messages = [];

        $after = 0;
        do {
            $groups = question_analyzer::get_duplicate_groups_seek(self::PAGE, $after);
            foreach ($groups as $group) {
                $repid = (int)$group->representative_id;
                $after = max($after, $repid);
                $plan = question_merger::build_merge_plan($repid);
                if ($plan->errors || !$plan->mergeable_questionids) {
                    continue;
                }
                $totals['groups']++;
                $res = question_merger::apply_merge_plan($plan);
                if ($res->success) {
                    $totals['merged']++;
                    $totals['deleted'] += count($res->details['deleted'] ?? []);
                } else {
                    $totals['failed']++;
                    $messages[] = '  - ' . $repid . ' : ' . $res->message;
                }
            }
        } while (!empty($groups));

        $body = implode("\n", array_merge([
            get_string('report_merge_groups', 'local_question_diagnostic', $totals['groups']),
            get_string('report_merge_merged', 'local_question_diagnostic', $totals['merged']),
            get_string('report_cleanup_deleted', 'local_question_diagnostic', $totals['deleted']),
            get_string('report_merge_failed', 'local_question_diagnostic', $totals['failed']),
        ], $messages));
        mtrace($body);
        if ($requester) {
            notifier::send($requester, get_string('task_merge_duplicates', 'local_question_diagnostic'), $body,
                new \moodle_url('/local/question_diagnostic/question_merge.php'));
        }
    }
}
