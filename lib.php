<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Library functions for Question Diagnostic Tool
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add navigation links for the plugin
 *
 * @param global_navigation $nav
 */
function local_question_diagnostic_extend_navigation(global_navigation $nav) {
    global $PAGE, $USER;
    
    // Only show for site administrators
    if (!is_siteadmin()) {
        return;
    }
    
    $node = $nav->add(
        get_string('pluginname', 'local_question_diagnostic', null, true) ?: 'Gestion Questions',
        new moodle_url('/local/question_diagnostic/index.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'question_diagnostic',
        new pix_icon('i/questions', '')
    );
    
    $node->showinflatnavigation = true;
}

/**
 * Serve the plugin files
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function local_question_diagnostic_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    // No files to serve in this plugin
    return false;
}

