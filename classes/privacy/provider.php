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

namespace local_question_diagnostic\privacy;

/**
 * API de confidentialité : le plugin ne stocke aucune donnée personnelle.
 *
 * Ses opérations sont tracées par des événements, donc dans le journal standard de Moodle (dont le fournisseur
 * gère l'export et l'effacement). Les exports XML de questions et les archives de fichiers orphelins qu'il écrit
 * dans dataroot ne contiennent pas d'identifiant d'utilisateur et sont purgés après la durée de conservation
 * réglée (tâche planifiée purge_retention).
 *
 * @package    local_question_diagnostic
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\null_provider {

    /**
     * Raison de l'absence de données personnelles.
     *
     * @return string
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
