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
 * Suppression de catégories (version refactorisée)
 * 
 * 🆕 v1.9.33 : Utilise delete_category_action (factorisation TODO #12)
 * 
 * Avant : ~140 lignes de code
 * Après : ~30 lignes de code
 * Réduction : ~78% de code en moins !
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../classes/actions/delete_category_action.php');

use local_question_diagnostic\actions\delete_category_action;

// Créer et exécuter l'action
$action = new delete_category_action();
$action->execute();

