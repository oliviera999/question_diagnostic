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
 * Page de test pour le plugin Question Diagnostic
 *
 * @package    local_question_diagnostic
 * @copyright  2025 Question Diagnostic Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// Sécurité
require_login();
if (!is_siteadmin()) {
    print_error('accessdenied', 'admin');
}

// Configuration de la page
$PAGE->set_url(new moodle_url('/local/question_diagnostic/test.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('test_page_title', 'local_question_diagnostic'));
$PAGE->set_heading(get_string('test_page_heading', 'local_question_diagnostic'));

// Début de la page
echo $OUTPUT->header();

// Contenu principal
echo html_writer::tag('h2', get_string('test_page_heading', 'local_question_diagnostic'));
echo html_writer::tag('p', get_string('test_content', 'local_question_diagnostic'));

// Fin de la page
echo $OUTPUT->footer();

