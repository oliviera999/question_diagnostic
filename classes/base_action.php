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
 * Classe abstraite de base pour les actions du plugin
 * 
 * 🆕 v1.9.33 : Factorisation du code commun des actions (TODO MOYENNE #12)
 * 
 * Cette classe abstraite factorise la logique commune à toutes les actions :
 * - Validation de sécurité (login, sesskey, admin)
 * - Gestion des paramètres (id, ids, confirm, return)
 * - Affichage des pages de confirmation
 * - Gestion des redirections avec messages
 * - Support suppression unique + en masse
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_question_diagnostic;

defined('MOODLE_INTERNAL') || die();

abstract class base_action {

    /** @var \moodle_url URL de retour par défaut */
    protected $default_return_url;

    /** @var int ID de l'élément unique à traiter */
    protected $item_id = 0;

    /** @var array IDs des éléments multiples à traiter */
    protected $item_ids = [];

    /** @var bool Confirmation utilisateur reçue */
    protected $confirmed = false;

    /** @var \moodle_url URL de retour effective */
    protected $return_url;

    /** @var bool Opération en masse */
    protected $is_bulk = false;

    /**
     * Constructeur
     * Initialise l'action et effectue les vérifications de sécurité
     */
    public function __construct() {
        global $PAGE;

        // Vérifications de sécurité obligatoires
        require_login();
        require_sesskey();

        if (!is_siteadmin()) {
            print_error('accessdenied', 'admin');
        }

        // Récupérer les paramètres communs
        $this->item_id = optional_param('id', 0, PARAM_INT);
        $item_ids_param = optional_param('ids', '', PARAM_TEXT);
        $this->confirmed = optional_param('confirm', 0, PARAM_INT) == 1;
        $return_param = optional_param('return', $this->get_default_return_page(), PARAM_ALPHA);

        // Parser les IDs multiples
        if (!empty($item_ids_param)) {
            $this->item_ids = array_filter(array_map('intval', explode(',', $item_ids_param)));
            $this->is_bulk = true;
        } else if ($this->item_id > 0) {
            $this->item_ids = [$this->item_id];
            $this->is_bulk = false;
        }

        // Construire l'URL de retour
        $this->default_return_url = $this->get_return_url($return_param);
        $this->return_url = $this->default_return_url;

        // Configurer la page
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url($this->get_action_url());
    }

    /**
     * Point d'entrée principal de l'action
     * Template Method Pattern
     */
    public function execute() {
        // Validation des paramètres
        if (empty($this->item_ids)) {
            $this->redirect_error('Aucun élément sélectionné');
            return;
        }

        // Vérifier les limites pour opérations en masse
        if ($this->is_bulk && $this->has_bulk_limit()) {
            $limit = $this->get_bulk_limit();
            if (count($this->item_ids) > $limit) {
                $this->redirect_error('Trop d\'éléments sélectionnés. Maximum autorisé : ' . $limit);
                return;
            }
        }

        // Si pas encore confirmé, afficher la page de confirmation
        if (!$this->confirmed) {
            $this->show_confirmation_page();
            return;
        }

        // Exécuter l'action
        $this->perform_action();
    }

    /**
     * Affiche la page de confirmation
     */
    protected function show_confirmation_page() {
        global $OUTPUT, $PAGE;

        $PAGE->set_title($this->get_confirmation_title());

        echo $OUTPUT->header();
        echo $OUTPUT->heading($this->get_confirmation_heading());

        // Message principal
        echo \html_writer::tag('p', $this->get_confirmation_message());

        // Avertissement si action irréversible
        if ($this->is_action_irreversible()) {
            echo \html_writer::start_tag('div', ['class' => 'alert alert-warning', 'style' => 'margin: 20px 0;']);
            echo '⚠️ ' . $this->get_irreversible_warning();
            echo \html_writer::end_tag('div');
        }

        // Détails supplémentaires (personnalisable par chaque action)
        $details = $this->get_confirmation_details();
        if (!empty($details)) {
            echo \html_writer::start_tag('div', ['class' => 'alert alert-info', 'style' => 'margin: 20px 0;']);
            echo $details;
            echo \html_writer::end_tag('div');
        }

        // Formulaire de confirmation (POST pour éviter Request-URI Too Long)
        echo \html_writer::start_tag('div', ['style' => 'margin-top: 20px;']);

        echo \html_writer::start_tag('form', [
            'method' => 'post',
            'action' => $this->get_action_url(),
            'style' => 'display: inline;'
        ]);

        // Paramètres cachés
        if ($this->is_bulk) {
            echo \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'ids', 'value' => implode(',', $this->item_ids)]);
        } else {
            echo \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $this->item_id]);
        }

        echo \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => '1']);
        echo \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

        // Bouton de confirmation
        $button_class = $this->is_action_dangerous() ? 'btn-danger' : 'btn-primary';
        echo \html_writer::empty_tag('input', [
            'type' => 'submit',
            'value' => $this->get_confirm_button_text(),
            'class' => 'btn ' . $button_class
        ]);

        echo \html_writer::end_tag('form');

        // Bouton annuler
        echo ' ';
        echo \html_writer::link($this->return_url, 'Annuler', ['class' => 'btn btn-secondary']);

        echo \html_writer::end_tag('div');

        echo $OUTPUT->footer();
        exit;
    }

    /**
     * Redirige avec un message de succès
     *
     * @param string $message Message à afficher
     * @param \moodle_url|null $url URL de redirection (null = URL par défaut)
     */
    protected function redirect_success($message, $url = null) {
        $url = $url ?? $this->return_url;
        redirect($url, '✅ ' . $message, null, \core\output\notification::NOTIFY_SUCCESS);
    }

    /**
     * Redirige avec un message d'erreur
     *
     * @param string $message Message d'erreur
     * @param \moodle_url|null $url URL de redirection (null = URL par défaut)
     */
    protected function redirect_error($message, $url = null) {
        $url = $url ?? $this->return_url;
        redirect($url, '⚠️ ' . $message, null, \core\output\notification::NOTIFY_ERROR);
    }

    /**
     * Redirige avec un message d'avertissement
     *
     * @param string $message Message d'avertissement
     * @param \moodle_url|null $url URL de redirection (null = URL par défaut)
     */
    protected function redirect_warning($message, $url = null) {
        $url = $url ?? $this->return_url;
        redirect($url, '⚠️ ' . $message, null, \core\output\notification::NOTIFY_WARNING);
    }

    // ===================================================================
    // MÉTHODES ABSTRAITES À IMPLÉMENTER PAR LES CLASSES FILLES
    // ===================================================================

    /**
     * Exécute l'action principale
     * À implémenter par chaque action concrète
     */
    abstract protected function perform_action();

    /**
     * Retourne l'URL de l'action
     *
     * @return \moodle_url
     */
    abstract protected function get_action_url();

    /**
     * Retourne le nom de la page de retour par défaut
     *
     * @return string
     */
    abstract protected function get_default_return_page();

    /**
     * Retourne le titre de la page de confirmation
     *
     * @return string
     */
    abstract protected function get_confirmation_title();

    /**
     * Retourne le titre (heading) de la page de confirmation
     *
     * @return string
     */
    abstract protected function get_confirmation_heading();

    /**
     * Retourne le message principal de confirmation
     *
     * @return string
     */
    abstract protected function get_confirmation_message();

    // ===================================================================
    // MÉTHODES AVEC IMPLÉMENTATION PAR DÉFAUT (PERSONNALISABLES)
    // ===================================================================

    /**
     * Retourne l'URL de retour selon le paramètre
     *
     * @param string $page Nom de la page
     * @return \moodle_url
     */
    protected function get_return_url($page) {
        $file = ($page === 'index') ? 'index.php' : 'categories.php';
        return new \moodle_url('/local/question_diagnostic/' . $file);
    }

    /**
     * Indique si l'action est irréversible
     *
     * @return bool
     */
    protected function is_action_irreversible() {
        return true;
    }

    /**
     * Retourne le message d'avertissement pour action irréversible
     *
     * @return string
     */
    protected function get_irreversible_warning() {
        return 'Cette action est irréversible. Assurez-vous d\'avoir sauvegardé vos données.';
    }

    /**
     * Retourne des détails supplémentaires pour la confirmation (HTML)
     *
     * @return string
     */
    protected function get_confirmation_details() {
        return '';
    }

    /**
     * Indique si l'action est dangereuse (affiche bouton rouge)
     *
     * @return bool
     */
    protected function is_action_dangerous() {
        return true;
    }

    /**
     * Retourne le texte du bouton de confirmation
     *
     * @return string
     */
    protected function get_confirm_button_text() {
        return 'Confirmer';
    }

    /**
     * Indique si l'opération en masse a une limite
     *
     * @return bool
     */
    protected function has_bulk_limit() {
        return false;
    }

    /**
     * Retourne la limite d'éléments pour opération en masse
     *
     * @return int
     */
    protected function get_bulk_limit() {
        return 100;
    }
}

