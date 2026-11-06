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

namespace local_question_diagnostic\event;

/**
 * Opération effectuée par l'outil sur la banque de questions (suppression, fusion, déplacement…).
 *
 * Remplace le journal en fichier : l'historique vit dans le journal standard de Moodle, qui gère la durée de
 * conservation, l'export et l'effacement des données personnelles.
 *
 * other : ['action' => string, 'details' => array]
 *
 * @package    local_question_diagnostic
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class operation_performed extends \core\event\base {

    /**
     * Initialisation.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Nom de l'événement.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_operation_performed', 'local_question_diagnostic');
    }

    /**
     * Description (non traduite, comme celles du cœur).
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' performed the question diagnostic operation '"
            . s($this->other['action'] ?? '') . "'.";
    }

    /**
     * Contrôle des données.
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['action'])) {
            throw new \coding_exception('The \'action\' value must be set in other.');
        }
    }

    /**
     * Correspondance des champs « other » à la restauration (aucun identifiant à remapper).
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
