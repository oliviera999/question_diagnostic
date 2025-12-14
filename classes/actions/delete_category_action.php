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
 * Action de suppression de catégories
 * 
 * 🆕 v1.9.33 : Utilisation de base_action pour factorisation (TODO MOYENNE #12)
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_question_diagnostic\actions;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../base_action.php');
require_once(__DIR__ . '/../category_manager.php');
require_once(__DIR__ . '/../question_analyzer.php');

use local_question_diagnostic\base_action;
use local_question_diagnostic\category_manager;
use local_question_diagnostic\question_analyzer;

class delete_category_action extends base_action {

    /** @var int Limite pour suppression en masse */
    const MAX_BULK_DELETE = 100;

    /**
     * Exécute la suppression
     */
    protected function perform_action() {
        if ($this->is_bulk) {
            $this->perform_bulk_delete();
        } else {
            $this->perform_single_delete();
        }
    }

    /**
     * Suppression unique
     */
    private function perform_single_delete() {
        // Option avancée : ignorer la protection "catégorie avec description" (si demandée explicitement).
        $bypass_info = optional_param('bypass_info', 0, PARAM_INT);
        $result = category_manager::delete_category($this->item_id, false, (bool)$bypass_info);

        if ($result === true) {
            // Purger les caches après modification
            question_analyzer::purge_all_caches();
            $this->redirect_success('Catégorie supprimée avec succès.');
        } else {
            $this->redirect_error($result);
        }
    }

    /**
     * Suppression en masse
     */
    private function perform_bulk_delete() {
        $bypass_info = optional_param('bypass_info', 0, PARAM_INT);
        $result = category_manager::delete_categories_bulk($this->item_ids, false, (bool)$bypass_info);

        // Purger les caches si au moins une suppression a réussi
        if ($result['success'] > 0) {
            question_analyzer::purge_all_caches();
        }

        // Gérer les résultats
        if ($result['success'] > 0 && empty($result['errors'])) {
            // Tout a réussi
            $this->redirect_success("{$result['success']} catégorie(s) supprimée(s) avec succès.");
        } else if ($result['success'] > 0 && !empty($result['errors'])) {
            // Succès partiel
            $message = "{$result['success']} supprimée(s), mais {$result['failed']} erreur(s) :<br>";
            $message .= implode('<br>', array_slice($result['errors'], 0, 5)); // Limiter à 5 erreurs
            $this->redirect_warning($message);
        } else {
            // Aucune suppression
            $errors = implode('<br>', array_slice($result['errors'], 0, 5));
            $this->redirect_error("Aucune suppression effectuée. Erreurs :<br>$errors");
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function get_action_url() {
        return new \moodle_url('/local/question_diagnostic/actions/delete.php');
    }

    /**
     * {@inheritdoc}
     */
    protected function get_default_return_page() {
        return 'categories';
    }

    /**
     * {@inheritdoc}
     */
    protected function get_confirmation_title() {
        return 'Confirmation de suppression';
    }

    /**
     * {@inheritdoc}
     */
    protected function get_confirmation_heading() {
        return '⚠️ Confirmation de suppression';
    }

    /**
     * {@inheritdoc}
     */
    protected function get_confirmation_message() {
        if ($this->is_bulk) {
            $count = count($this->item_ids);
            return "Vous êtes sur le point de supprimer <strong>$count catégorie(s)</strong>.";
        } else {
            return "Vous êtes sur le point de supprimer cette catégorie.";
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function get_confirm_button_text() {
        return 'Oui, supprimer';
    }

    /**
     * {@inheritdoc}
     */
    protected function has_bulk_limit() {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function get_bulk_limit() {
        return self::MAX_BULK_DELETE;
    }

    /**
     * {@inheritdoc}
     */
    protected function get_confirmation_details() {
        if ($this->is_bulk) {
            return '<strong>Remarque</strong> : Les catégories protégées (par défaut, avec description, racines) seront automatiquement ignorées.';
        }
        return '';
    }
}

