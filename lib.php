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
 * Get the plugin version for display
 *
 * @return string Version string (e.g., "v1.2.3")
 */
function local_question_diagnostic_get_version() {
    global $CFG;
    
    // Get plugin info from version.php
    $plugin = new stdClass();
    require($CFG->dirroot . '/local/question_diagnostic/version.php');
    
    return $plugin->release ?? 'v1.0.0';
}

/**
 * Get the page heading with version
 *
 * @param string $heading The page heading text
 * @return string Heading with version appended
 */
function local_question_diagnostic_get_heading_with_version($heading) {
    $version = local_question_diagnostic_get_version();
    return $heading . ' (' . $version . ')';
}

/**
 * Get detailed context information including course and module names
 *
 * @param int $contextid Context ID
 * @param bool $include_id Include context ID in the name
 * @return object Object with context_name, course_name, module_name, context_type
 */
function local_question_diagnostic_get_context_details($contextid, $include_id = false) {
    global $DB;
    
    $result = (object)[
        'context_name' => 'Inconnu',
        'course_name' => null,
        'module_name' => null,
        'context_type' => null,
        'context_level' => null
    ];
    
    try {
        $context = context::instance_by_id($contextid, IGNORE_MISSING);
        
        if (!$context) {
            $result->context_name = 'Contexte supprimé (ID: ' . $contextid . ')';
            return $result;
        }
        
        $result->context_level = $context->contextlevel;
        $result->context_type = context_helper::get_level_name($context->contextlevel);
        
        // Cas 1 : Contexte système
        if ($context->contextlevel == CONTEXT_SYSTEM) {
            $result->context_name = '🌐 Système';
            if ($include_id) {
                $result->context_name .= ' (ID: ' . $contextid . ')';
            }
        }
        // Cas 2 : Contexte de cours
        else if ($context->contextlevel == CONTEXT_COURSE) {
            $course = $DB->get_record('course', ['id' => $context->instanceid], 'id, fullname, shortname');
            if ($course) {
                $result->course_name = format_string($course->fullname);
                $result->context_name = '📚 Cours : ' . format_string($course->shortname);
                if ($include_id) {
                    $result->context_name .= ' (ID: ' . $course->id . ')';
                }
            } else {
                $result->context_name = '📚 Cours (supprimé)';
            }
        }
        // Cas 3 : Contexte de module (activité/quiz)
        else if ($context->contextlevel == CONTEXT_MODULE) {
            $cm = $DB->get_record_sql("
                SELECT cm.id, cm.instance, m.name as modname, cm.course
                FROM {course_modules} cm
                INNER JOIN {modules} m ON m.id = cm.module
                WHERE cm.id = :cmid
            ", ['cmid' => $context->instanceid]);
            
            if ($cm) {
                // Obtenir le nom du cours parent
                $course = $DB->get_record('course', ['id' => $cm->course], 'id, fullname, shortname');
                if ($course) {
                    $result->course_name = format_string($course->fullname);
                }
                
                // Obtenir le nom du module (quiz, etc.)
                $module_table = $cm->modname;
                $module_record = $DB->get_record($module_table, ['id' => $cm->instance], 'id, name');
                
                if ($module_record) {
                    $result->module_name = format_string($module_record->name);
                    $result->context_name = '📝 ' . ucfirst($cm->modname) . ' : ' . format_string($module_record->name);
                    if ($course) {
                        $result->context_name .= ' (Cours : ' . format_string($course->shortname) . ')';
                    }
                    if ($include_id) {
                        $result->context_name .= ' (Module ID: ' . $cm->id . ')';
                    }
                } else {
                    $result->context_name = '📝 Module (supprimé)';
                }
            } else {
                $result->context_name = '📝 Module (supprimé)';
            }
        }
        // Cas 4 : Autres contextes (user, coursecat, block...)
        else {
            $result->context_name = $result->context_type;
            if ($include_id) {
                $result->context_name .= ' (ID: ' . $contextid . ')';
            }
        }
        
    } catch (Exception $e) {
        $result->context_name = 'Erreur : ' . $e->getMessage();
    }
    
    return $result;
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

