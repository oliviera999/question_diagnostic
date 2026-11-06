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

/**
 * Purge des fichiers du plugin dans dataroot (exports XML, archives, anciens journaux) après la durée de conservation.
 *
 * @package    local_question_diagnostic
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class purge_retention extends \core\task\scheduled_task {

    /**
     * Nom de la tâche.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_purge_retention', 'local_question_diagnostic');
    }

    /**
     * Exécution.
     */
    public function execute() {
        $count = \local_question_diagnostic\audit_logger::cleanup_old_logs();
        mtrace('local_question_diagnostic : ' . $count . ' fichier(s) supprimé(s).');
    }
}
