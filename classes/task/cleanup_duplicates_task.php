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
use local_question_diagnostic\local\question_deleter;
use local_question_diagnostic\question_analyzer;

/**
 * Nettoyage de tous les groupes de doublons du site, en tâche de fond.
 *
 * Les groupes sont parcourus par curseur (identifiant de la question représentative croissant) : aucun groupe
 * n'est sauté quand la liste change pendant le traitement. Chaque groupe passe par
 * question_analyzer::cleanup_duplicate_group() (contrôles, export XML, vérification). Un rapport est envoyé
 * à l'utilisateur qui a lancé l'opération.
 *
 * @package    local_question_diagnostic
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_duplicates_task extends \core\task\adhoc_task {

    /** Taille d'une page de groupes. */
    const PAGE = 100;

    /**
     * Nom de la tâche.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_cleanup_duplicates', 'local_question_diagnostic');
    }

    /**
     * Exécute le nettoyage.
     */
    public function execute() {
        \core_php_time_limit::raise();
        raise_memory_limit(MEMORY_EXTRA);

        $data = $this->get_custom_data();
        $requester = (int)($data->userid ?? 0);
        $totals = (object)['groups' => 0, 'deleted' => 0, 'kept' => 0, 'skipped' => 0, 'exports' => []];
        $skipreasons = [];

        $after = 0;
        do {
            $groups = question_analyzer::get_duplicate_groups_seek(self::PAGE, $after);
            foreach ($groups as $group) {
                $after = max($after, (int)$group->representative_id);
                $res = question_analyzer::cleanup_duplicate_group((int)$group->representative_id, 'cleanup-all-duplicates');
                if ($res->groupsize < 2) {
                    continue;
                }
                $totals->groups++;
                $totals->deleted += count($res->deleted);
                $totals->kept += count($res->kept);
                $totals->skipped += count($res->skipped);
                foreach ($res->skipped as $skip) {
                    $skipreasons[$skip] = ($skipreasons[$skip] ?? 0) + 1;
                }
                if ($res->exportfile) {
                    $totals->exports[] = basename($res->exportfile);
                }
            }
        } while (!empty($groups));

        question_analyzer::purge_all_caches();

        $lines = [
            get_string('report_cleanup_groups', 'local_question_diagnostic', $totals->groups),
            get_string('report_cleanup_deleted', 'local_question_diagnostic', $totals->deleted),
            get_string('report_cleanup_kept', 'local_question_diagnostic', $totals->kept),
            get_string('report_cleanup_skipped', 'local_question_diagnostic', $totals->skipped),
        ];
        foreach ($skipreasons as $skip => $count) {
            $lines[] = '  - ' . question_deleter::skip_label($skip) . ' : ' . $count;
        }
        if ($totals->exports) {
            $lines[] = get_string('report_exports', 'local_question_diagnostic', question_deleter::export_dir());
            foreach ($totals->exports as $file) {
                $lines[] = '  - ' . $file;
            }
        }
        $body = implode("\n", $lines);
        mtrace($body);
        if ($requester) {
            notifier::send($requester, get_string('task_cleanup_duplicates', 'local_question_diagnostic'), $body,
                new \moodle_url('/local/question_diagnostic/questions_cleanup.php'));
        }
    }
}
