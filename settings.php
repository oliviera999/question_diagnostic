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
 * Réglages et entrée d'administration du plugin.
 *
 * @package    local_question_diagnostic
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_category('local_question_diagnostic_category',
        get_string('pluginname', 'local_question_diagnostic')));

    // Accès à l'outil depuis l'administration (la navigation de Moodle 5.3 n'affiche plus le nœud historique).
    $ADMIN->add('local_question_diagnostic_category', new admin_externalpage('local_question_diagnostic_tool',
        get_string('tool_open', 'local_question_diagnostic'),
        new moodle_url('/local/question_diagnostic/index.php'),
        'local/question_diagnostic:view'));

    $settings = new admin_settingpage('local_question_diagnostic',
        get_string('settings', 'local_question_diagnostic'));

    $settings->add(new admin_setting_configtext('local_question_diagnostic/olution_qbank_cmid',
        get_string('setting_olution_qbank_cmid', 'local_question_diagnostic'),
        get_string('setting_olution_qbank_cmid_desc', 'local_question_diagnostic'),
        '', PARAM_INT));

    $settings->add(new admin_setting_configtext('local_question_diagnostic/retentiondays',
        get_string('setting_retentiondays', 'local_question_diagnostic'),
        get_string('setting_retentiondays_desc', 'local_question_diagnostic'),
        180, PARAM_INT));

    $ADMIN->add('local_question_diagnostic_category', $settings);
}
