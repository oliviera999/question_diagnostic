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
 * Render version badge HTML
 * 
 * 🆕 v1.9.50 : Badge de version visible sur toutes les pages
 * 
 * Cette fonction génère un badge HTML élégant affichant la version actuelle du plugin.
 * Le badge est conçu pour être affiché dans le header de chaque page.
 * 
 * Style : Badge flottant en haut à droite, responsive, avec tooltip
 * 
 * @param bool $with_tooltip Si true, ajoute un tooltip avec la date de version
 * @return string HTML du badge de version
 */
function local_question_diagnostic_render_version_badge($with_tooltip = true) {
    global $CFG;
    
    $version = local_question_diagnostic_get_version();
    
    // Récupérer la version timestamp pour le tooltip
    $plugin = new stdClass();
    require($CFG->dirroot . '/local/question_diagnostic/version.php');
    $version_date = $plugin->version ?? '0';
    
    // Formater la date depuis le timestamp YYYYMMDDXX
    $year = substr($version_date, 0, 4);
    $month = substr($version_date, 4, 2);
    $day = substr($version_date, 6, 2);
    $formatted_date = "$day/$month/$year";
    
    $tooltip_text = get_string('version_tooltip', 'local_question_diagnostic', [
        'version' => $version,
        'date' => $formatted_date
    ]);
    
    $html = html_writer::start_div('qd-version-badge', [
        'title' => $with_tooltip ? $tooltip_text : '',
        'data-version' => $version
    ]);
    
    $html .= html_writer::tag('span', get_string('version_label', 'local_question_diagnostic'), [
        'class' => 'qd-version-label'
    ]);
    
    $html .= html_writer::tag('span', $version, [
        'class' => 'qd-version-number'
    ]);
    
    $html .= html_writer::end_div();
    
    return $html;
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
 * Get used question IDs from quiz_slots
 * 
 * 🔧 FONCTION UTILITAIRE CENTRALE : Détection des questions utilisées pour Moodle 4.5
 * Cette fonction centralise la logique de détection qui était dupliquée dans :
 * - questions_cleanup.php (lignes 242-299)
 * - question_analyzer.php get_question_usage() (lignes 243-275)
 * - question_analyzer.php get_questions_usage_by_ids() (lignes 328-368)
 * - question_analyzer.php get_all_questions_usage() (lignes 528-549)
 * - question_analyzer.php get_global_stats() (lignes 1202-1218)
 * - question_analyzer.php get_used_duplicates_questions() (lignes 639-679)
 * 
 * ⚠️ MOODLE 4.5 : La table quiz_slots a changé !
 * - Moodle 3.x/4.0 : quiz_slots.questionid existe
 * - Moodle 4.1-4.4 : quiz_slots.questionbankentryid existe
 * - Moodle 4.5+ : Ni l'un ni l'autre ! Utilise question_references
 * 
 * @return array IDs des questions utilisées dans des quiz
 * @throws dml_exception Si erreur de base de données
 */
function local_question_diagnostic_get_used_question_ids() {
    global $DB;
    
    try {
        // Vérifier quelle colonne existe dans quiz_slots
        $columns = $DB->get_columns('quiz_slots');
        
        if (isset($columns['questionbankentryid'])) {
            // Moodle 4.1-4.4 : utilise questionbankentryid
            $sql = "SELECT DISTINCT qv.questionid
                    FROM {quiz_slots} qs
                    INNER JOIN {question_bank_entries} qbe ON qbe.id = qs.questionbankentryid
                    INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id";
            return $DB->get_fieldset_sql($sql);
            
        } else if (isset($columns['questionid'])) {
            // Moodle 4.0 uniquement : utilise questionid directement
            // ⚠️ Note : Moodle 3.x NON supporté par ce plugin (architecture incompatible)
            $sql = "SELECT DISTINCT qs.questionid
                    FROM {quiz_slots} qs";
            return $DB->get_fieldset_sql($sql);
            
        } else {
            // Moodle 4.5+ : Nouvelle architecture avec question_references
            // Dans Moodle 4.5+, quiz_slots ne contient plus de lien direct vers les questions
            // Il faut passer par question_references
            $sql = "SELECT DISTINCT qv.questionid
                    FROM {quiz_slots} qs
                    INNER JOIN {question_references} qr ON qr.itemid = qs.id 
                        AND qr.component = 'mod_quiz' 
                        AND qr.questionarea = 'slot'
                    INNER JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                    INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id 
                        AND qv.version = (
                            SELECT MAX(v.version)
                            FROM {question_versions} v
                            WHERE v.questionbankentryid = qbe.id
                        )";
            return $DB->get_fieldset_sql($sql);
        }
    } catch (Exception $e) {
        debugging('Erreur dans local_question_diagnostic_get_used_question_ids: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return [];
    }
}

/**
 * Generate URL to access a category or question in the question bank
 * 
 * 🔧 FONCTION UTILITAIRE CENTRALE : Génération d'URL vers la banque de questions
 * Cette fonction centralise la logique qui était dupliquée dans :
 * - category_manager.php::get_question_bank_url() (ligne 779)
 * - question_analyzer.php::get_question_bank_url() (ligne 1301)
 * - question_link_checker.php::get_question_bank_url() (ligne 508)
 * 
 * @param object $category Category object with id and contextid
 * @param int|null $questionid Optional question ID to link to
 * @return moodle_url|null URL to question bank, or null if context invalid
 */
function local_question_diagnostic_get_question_bank_url($category, $questionid = null) {
    global $DB;
    
    try {
        // Déterminer le courseid à partir du contexte
        $context = context::instance_by_id($category->contextid, IGNORE_MISSING);
        
        if (!$context) {
            // Si le contexte n'existe pas, retourner null
            return null;
        }
        
        $courseid = 0; // Par défaut, système
        
        // Si c'est un contexte de cours, récupérer l'ID du cours
        if ($context->contextlevel == CONTEXT_COURSE) {
            $courseid = $context->instanceid;
        } else if ($context->contextlevel == CONTEXT_MODULE) {
            // Si c'est un module, remonter au cours parent
            $coursecontext = $context->get_course_context(false);
            if ($coursecontext) {
                $courseid = $coursecontext->instanceid;
            }
        } else if ($context->contextlevel == CONTEXT_SYSTEM) {
            // 🔧 FIX: Pour contexte système, utiliser SITEID au lieu de 0
            // courseid=0 cause l'erreur "course not found"
            $courseid = SITEID;
        }
        
        // Vérifier que le cours existe avant de générer l'URL
        if ($courseid > 0 && !$DB->record_exists('course', ['id' => $courseid])) {
            // Si le cours n'existe pas, utiliser SITEID comme fallback
            $courseid = SITEID;
        }
        
        // Dernière vérification : si SITEID n'existe pas non plus (rare), retourner null
        if (!$DB->record_exists('course', ['id' => $courseid])) {
            return null;
        }
        
        // Construire l'URL : /question/edit.php?courseid=X&cat=categoryid,contextid
        $params = [
            'courseid' => $courseid,
            'cat' => $category->id . ',' . $category->contextid
        ];
        
        // Si un ID de question est fourni, l'ajouter
        if ($questionid !== null) {
            $params['qid'] = $questionid;
        }
        
        $url = new moodle_url('/question/edit.php', $params);
        
        return $url;
        
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Generate pagination controls HTML
 * 
 * 🆕 v1.9.30 : Pagination serveur pour gros sites
 * 
 * @param int $total_items Total number of items
 * @param int $current_page Current page number (1-based)
 * @param int $per_page Items per page
 * @param moodle_url $base_url Base URL for pagination links
 * @param array $extra_params Additional URL parameters to preserve
 * @return string HTML for pagination controls
 */
function local_question_diagnostic_render_pagination($total_items, $current_page, $per_page, $base_url, $extra_params = []) {
    if ($total_items <= $per_page) {
        return ''; // No pagination needed
    }
    
    $total_pages = ceil($total_items / $per_page);
    $current_page = max(1, min($current_page, $total_pages));
    
    $html = html_writer::start_div('qd-pagination', ['style' => 'margin: 20px 0; text-align: center;']);
    
    // Info texte
    $start = ($current_page - 1) * $per_page + 1;
    $end = min($current_page * $per_page, $total_items);
    $html .= html_writer::tag('div', 
        sprintf('Affichage de %d à %d sur %d éléments', $start, $end, $total_items),
        ['style' => 'margin-bottom: 10px; color: #666; font-size: 14px;']
    );
    
    $html .= html_writer::start_div('qd-pagination-buttons', ['style' => 'display: flex; justify-content: center; gap: 5px; flex-wrap: wrap;']);
    
    // Bouton Première page
    if ($current_page > 1) {
        $url = clone $base_url;
        $url->params(array_merge($extra_params, ['page' => 1]));
        $html .= html_writer::link($url, '« Premier', ['class' => 'btn btn-sm btn-secondary']);
    }
    
    // Bouton Précédent
    if ($current_page > 1) {
        $url = clone $base_url;
        $url->params(array_merge($extra_params, ['page' => $current_page - 1]));
        $html .= html_writer::link($url, '‹ Précédent', ['class' => 'btn btn-sm btn-secondary']);
    }
    
    // Numéros de pages (avec ellipses si beaucoup de pages)
    $range = 2; // Montrer 2 pages avant et après
    $start_page = max(1, $current_page - $range);
    $end_page = min($total_pages, $current_page + $range);
    
    // Ellipse au début si nécessaire
    if ($start_page > 1) {
        $url = clone $base_url;
        $url->params(array_merge($extra_params, ['page' => 1]));
        $html .= html_writer::link($url, '1', ['class' => 'btn btn-sm btn-secondary']);
        
        if ($start_page > 2) {
            $html .= html_writer::tag('span', '...', ['style' => 'padding: 0 10px; line-height: 30px;']);
        }
    }
    
    // Pages du milieu
    for ($i = $start_page; $i <= $end_page; $i++) {
        $url = clone $base_url;
        $url->params(array_merge($extra_params, ['page' => $i]));
        
        if ($i == $current_page) {
            $html .= html_writer::tag('span', $i, [
                'class' => 'btn btn-sm btn-primary',
                'style' => 'font-weight: bold;'
            ]);
        } else {
            $html .= html_writer::link($url, $i, ['class' => 'btn btn-sm btn-secondary']);
        }
    }
    
    // Ellipse à la fin si nécessaire
    if ($end_page < $total_pages) {
        if ($end_page < $total_pages - 1) {
            $html .= html_writer::tag('span', '...', ['style' => 'padding: 0 10px; line-height: 30px;']);
        }
        
        $url = clone $base_url;
        $url->params(array_merge($extra_params, ['page' => $total_pages]));
        $html .= html_writer::link($url, $total_pages, ['class' => 'btn btn-sm btn-secondary']);
    }
    
    // Bouton Suivant
    if ($current_page < $total_pages) {
        $url = clone $base_url;
        $url->params(array_merge($extra_params, ['page' => $current_page + 1]));
        $html .= html_writer::link($url, 'Suivant ›', ['class' => 'btn btn-sm btn-secondary']);
    }
    
    // Bouton Dernière page
    if ($current_page < $total_pages) {
        $url = clone $base_url;
        $url->params(array_merge($extra_params, ['page' => $total_pages]));
        $html .= html_writer::link($url, 'Dernier »', ['class' => 'btn btn-sm btn-secondary']);
    }
    
    $html .= html_writer::end_div(); // qd-pagination-buttons
    $html .= html_writer::end_div(); // qd-pagination
    
    return $html;
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

// ============================================================================
// 🆕 v1.9.41 : Fonctions helper pour permissions granulaires (capabilities)
// ============================================================================

/**
 * Vérifie si l'utilisateur peut accéder au plugin
 * 
 * @return bool
 */
function local_question_diagnostic_can_view() {
    $context = context_system::instance();
    return has_capability('local/question_diagnostic:view', $context) || is_siteadmin();
}

/**
 * Vérifie si l'utilisateur peut voir les catégories
 * 
 * @return bool
 */
function local_question_diagnostic_can_view_categories() {
    $context = context_system::instance();
    return has_capability('local/question_diagnostic:viewcategories', $context) || is_siteadmin();
}

/**
 * Vérifie si l'utilisateur peut voir les questions
 * 
 * @return bool
 */
function local_question_diagnostic_can_view_questions() {
    $context = context_system::instance();
    return has_capability('local/question_diagnostic:viewquestions', $context) || is_siteadmin();
}

/**
 * Vérifie si l'utilisateur peut voir les liens cassés
 * 
 * @return bool
 */
function local_question_diagnostic_can_view_broken_links() {
    $context = context_system::instance();
    return has_capability('local/question_diagnostic:viewbrokenlinks', $context) || is_siteadmin();
}

/**
 * Vérifie si l'utilisateur peut voir les logs d'audit
 * 
 * @return bool
 */
function local_question_diagnostic_can_view_audit_logs() {
    $context = context_system::instance();
    return has_capability('local/question_diagnostic:viewauditlogs', $context) || is_siteadmin();
}

/**
 * Vérifie si l'utilisateur peut voir le monitoring
 * 
 * @return bool
 */
function local_question_diagnostic_can_view_monitoring() {
    $context = context_system::instance();
    return has_capability('local/question_diagnostic:viewmonitoring', $context) || is_siteadmin();
}

/**
 * Vérifie si l'utilisateur peut gérer les catégories (supprimer, fusionner, déplacer)
 * 
 * @return bool
 */
function local_question_diagnostic_can_manage_categories() {
    $context = context_system::instance();
    return has_capability('local/question_diagnostic:managecategories', $context) || is_siteadmin();
}

/**
 * Vérifie si l'utilisateur peut supprimer des catégories
 * 
 * @return bool
 */
function local_question_diagnostic_can_delete_categories() {
    $context = context_system::instance();
    return has_capability('local/question_diagnostic:deletecategories', $context) || is_siteadmin();
}

/**
 * Vérifie si l'utilisateur peut fusionner des catégories
 * 
 * @return bool
 */
function local_question_diagnostic_can_merge_categories() {
    $context = context_system::instance();
    return has_capability('local/question_diagnostic:mergecategories', $context) || is_siteadmin();
}

/**
 * Vérifie si l'utilisateur peut déplacer des catégories
 * 
 * @return bool
 */
function local_question_diagnostic_can_move_categories() {
    $context = context_system::instance();
    return has_capability('local/question_diagnostic:movecategories', $context) || is_siteadmin();
}

/**
 * Vérifie si l'utilisateur peut supprimer des questions
 * 
 * @return bool
 */
function local_question_diagnostic_can_delete_questions() {
    $context = context_system::instance();
    return has_capability('local/question_diagnostic:deletequestions', $context) || is_siteadmin();
}

/**
 * Vérifie si l'utilisateur peut exporter des données
 * 
 * @return bool
 */
function local_question_diagnostic_can_export() {
    $context = context_system::instance();
    return has_capability('local/question_diagnostic:export', $context) || is_siteadmin();
}

/**
 * Vérifie si l'utilisateur peut configurer le plugin
 * 
 * @return bool
 */
function local_question_diagnostic_can_configure_plugin() {
    $context = context_system::instance();
    return has_capability('local/question_diagnostic:configureplugin', $context) || is_siteadmin();
}

/**
 * Génère un message d'erreur de permission et redirige
 * 
 * @param string $permission Nom de la permission manquante
 * @throws moodle_exception
 */
function local_question_diagnostic_require_capability_or_die($permission) {
    $context = context_system::instance();
    
    if (!is_siteadmin() && !has_capability($permission, $context)) {
        print_error('nopermission', 'error', '', $permission);
    }
}

// ============================================================================
// 🆕 v1.9.44 : Fonctions de navigation hiérarchique
// ============================================================================

/**
 * Obtient l'URL de la page parente dans la hiérarchie de navigation
 * 
 * 🆕 v1.9.44 : Hiérarchie de navigation logique
 * 
 * Hiérarchie :
 * - index.php (racine)
 *   ├── categories.php
 *   │   ├── actions/delete.php
 *   │   ├── actions/merge.php
 *   │   ├── actions/move.php
 *   │   └── actions/export.php
 *   ├── broken_links.php
 *   ├── questions_cleanup.php
 *   │   ├── actions/delete_question.php
 *   │   └── actions/delete_questions_bulk.php
 *   ├── help.php
 *   │   ├── help_features.php
 *   │   └── help_database_impact.php
 *   ├── audit_logs.php
 *   ├── monitoring.php
 *   ├── orphan_entries.php
 *   └── test.php
 *
 * @param string $current_page Nom du fichier actuel (ex: 'categories.php', 'actions/delete.php')
 * @return moodle_url URL de la page parente
 */
function local_question_diagnostic_get_parent_url($current_page) {
    // Normaliser le chemin (remplacer backslash par slash)
    $current_page = str_replace('\\', '/', $current_page);
    
    // Définir la hiérarchie
    $hierarchy = [
        // Actions catégories → categories.php
        'actions/delete.php' => 'categories.php',
        'actions/merge.php' => 'categories.php',
        'actions/move.php' => 'categories.php',
        'actions/export.php' => 'categories.php',
        
        // Actions questions → questions_cleanup.php
        'actions/delete_question.php' => 'questions_cleanup.php',
        'actions/delete_questions_bulk.php' => 'questions_cleanup.php',
        
        // Pages d'aide → help.php
        'help_features.php' => 'help.php',
        'help_database_impact.php' => 'help.php',
        
        // Pages principales → index.php
        'categories.php' => 'index.php',
        'broken_links.php' => 'index.php',
        'questions_cleanup.php' => 'index.php',
        'help.php' => 'index.php',
        'audit_logs.php' => 'index.php',
        'monitoring.php' => 'index.php',
        'orphan_entries.php' => 'index.php',
        'test.php' => 'index.php',
        'debug_categories.php' => 'index.php',
        'quick_check_categories.php' => 'index.php',
        'check_default_categories.php' => 'index.php',
        'diagnose_dd_files.php' => 'index.php',
        
        // index.php n'a pas de parent (racine)
        'index.php' => null,
    ];
    
    // Trouver le parent
    $parent = isset($hierarchy[$current_page]) ? $hierarchy[$current_page] : 'index.php';
    
    if ($parent === null) {
        // Page racine, retourner vers le tableau de bord Moodle
        return new moodle_url('/my/');
    }
    
    return new moodle_url('/local/question_diagnostic/' . $parent);
}

/**
 * Génère le HTML du lien de retour vers la page parente
 * 
 * 🆕 v1.9.44 : Hiérarchie de navigation logique
 * 
 * ⚠️ IMPORTANT : Pour utiliser cette fonction, le fichier appelant DOIT inclure lib.php :
 * 
 * ```php
 * require_once(__DIR__ . '/lib.php');
 * ```
 * 
 * ⚠️ FICHIERS UTILISANT CETTE FONCTION (v1.9.49) :
 * - index.php ✅
 * - categories.php ✅
 * - questions_cleanup.php ✅
 * - broken_links.php ✅
 * - audit_logs.php ✅
 * - monitoring.php ✅
 * - orphan_entries.php ✅
 * - help_features.php ✅
 * - help_database_impact.php ✅
 * 
 * 🔧 Si vous ajoutez un nouvel appel à cette fonction dans un nouveau fichier,
 * pensez à inclure lib.php ET à mettre à jour cette liste !
 * 
 * 🐛 Bugfix : v1.9.49 - Correction inclusion manquante dans audit_logs, monitoring, help_features
 *
 * @param string $current_page Nom du fichier actuel
 * @param string $custom_text Texte personnalisé pour le lien (optionnel)
 * @param array $extra_params Paramètres supplémentaires à conserver dans l'URL (ex: ['page' => 2])
 * @return string HTML du lien de retour
 */
function local_question_diagnostic_render_back_link($current_page, $custom_text = null, $extra_params = []) {
    $parent_url = local_question_diagnostic_get_parent_url($current_page);
    
    // Ajouter les paramètres supplémentaires si fournis
    if (!empty($extra_params)) {
        foreach ($extra_params as $key => $value) {
            $parent_url->param($key, $value);
        }
    }
    
    // Déterminer le texte du lien
    if ($custom_text === null) {
        // Texte par défaut basé sur la page parente
        $parent_file = basename($parent_url->get_path());
        
        $default_texts = [
            'index.php' => get_string('backtomenu', 'local_question_diagnostic'),
            'categories.php' => '← Retour aux catégories',
            'questions_cleanup.php' => '← Retour aux questions',
            'help.php' => '← Retour au centre d\'aide',
            'my' => '← Retour au tableau de bord',
        ];
        
        // Cas spécial pour /my/ (tableau de bord)
        if (strpos($parent_url->get_path(), '/my/') !== false) {
            $text = $default_texts['my'];
        } else {
            $text = isset($default_texts[$parent_file]) ? $default_texts[$parent_file] : '← Retour';
        }
    } else {
        $text = $custom_text;
    }
    
    return html_writer::link($parent_url, $text, ['class' => 'btn btn-secondary']);
}

/**
 * Trouve la catégorie "Olution" - Support multi-contextes
 * 
 * 🆕 v1.10.4 : Fonction pour identifier la catégorie Olution
 * 🔧 v1.10.5 : Recherche intelligente et flexible
 * 🎯 v1.10.6 : PRIORITÉ MAXIMALE à "Olution" - Recherche stricte et ciblée
 * 🔄 v1.10.7 : CORRECTION MAJEURE - Olution est une catégorie de COURS, pas de questions
 * 🎯 v1.10.9 : CORRECTION FINALE - Olution est une catégorie de QUESTIONS système
 * 🔧 v1.11.1 : CORRECTION DÉFINITIVE - Olution peut être catégorie de COURS ou QUESTIONS
 * 🔧 v1.11.2 : CORRECTION FINALE - Olution est une CATÉGORIE DE COURS (ID 78) contenant d'autres cours
 * 
 * Stratégie de recherche MULTI-CONTEXTES :
 * 
 * PHASE 1 - Catégories de QUESTIONS système :
 * 1. Nom EXACT "Olution" (case-sensitive) - PRIORITÉ ABSOLUE
 * 2. Variantes de casse : "olution", "OLUTION"
 * 3. Nom commençant par "Olution " (avec espace)
 * 4. Nom se terminant par " Olution"
 * 5. Nom contenant " Olution " (entouré d'espaces)
 * 6. Nom contenant "Olution" (plus flexible)
 * 7. En dernier recours : description contenant "olution"
 * 
 * PHASE 2 - CATÉGORIE DE COURS "Olution" (si Phase 1 échoue) :
 * 1. Rechercher la catégorie de cours "Olution" (ID 78 prioritaire)
 * 2. Récupérer tous les cours dans cette catégorie de cours
 * 3. Chercher les catégories de questions dans les contextes de ces cours
 * 4. Priorité : catégorie de questions nommée "Olution" puis première catégorie du cours
 * 
 * @return object|false Objet catégorie de questions Olution ou false si non trouvée
 */
function local_question_diagnostic_find_olution_category() {
    global $DB;
    
    try {
        // ==================================================================================
        // PHASE 1 : Recherche dans les catégories de QUESTIONS système
        // ==================================================================================
        $systemcontext = context_system::instance();
        
        // ==================================================================================
        // PRIORITÉ 1 : Nom EXACT "Olution" (case-sensitive) au niveau SYSTÈME
        // ==================================================================================
        $olution = $DB->get_record('question_categories', [
            'contextid' => $systemcontext->id,
            'parent' => 0,
            'name' => 'Olution'
        ]);
        
        if ($olution) {
            debugging('✅ Olution category found - EXACT match: Olution', DEBUG_DEVELOPER);
            return $olution;
        }
        
        // ==================================================================================
        // PRIORITÉ 2 : Variantes de casse exactes (mot seul)
        // ==================================================================================
        $variants = ['olution', 'OLUTION'];
        
        foreach ($variants as $variant) {
            $olution = $DB->get_record('question_categories', [
                'contextid' => $systemcontext->id,
                'parent' => 0,
                'name' => $variant
            ]);
            
            if ($olution) {
                debugging('✅ Olution question category found - Case variant: ' . $variant, DEBUG_DEVELOPER);
                return $olution;
            }
        }
        
        // ==================================================================================
        // PRIORITÉ 3 : Nom commençant par "Olution " (avec espace après)
        // Exemples : "Olution 2024", "Olution - Questions"
        // ==================================================================================
        $sql = "SELECT *
                FROM {question_categories}
                WHERE contextid = :contextid
                AND parent = 0
                AND " . $DB->sql_like('name', ':pattern', false, false) . "
                ORDER BY LENGTH(name) ASC
                LIMIT 1";
        
        $olution = $DB->get_record_sql($sql, [
            'contextid' => $systemcontext->id,
            'pattern' => 'Olution %'
        ]);
        
        if ($olution) {
            debugging('✅ Olution category found - Starts with "Olution ": ' . $olution->name, DEBUG_DEVELOPER);
            return $olution;
        }
        
        // ==================================================================================
        // PRIORITÉ 4 : Nom se terminant par " Olution" (avec espace avant)
        // Exemples : "Questions Olution", "Banque Olution"
        // ==================================================================================
        $sql = "SELECT *
                FROM {question_categories}
                WHERE contextid = :contextid
                AND parent = 0
                AND " . $DB->sql_like('name', ':pattern', false, false) . "
                ORDER BY LENGTH(name) ASC
                LIMIT 1";
        
        $olution = $DB->get_record_sql($sql, [
            'contextid' => $systemcontext->id,
            'pattern' => '% Olution'
        ]);
        
        if ($olution) {
            debugging('✅ Olution category found - Ends with " Olution": ' . $olution->name, DEBUG_DEVELOPER);
            return $olution;
        }
        
        // ==================================================================================
        // PRIORITÉ 5 : Nom contenant " Olution " (entouré d'espaces)
        // Exemples : "Banque Olution 2024", "Questions Olution Partagées"
        // ==================================================================================
        $sql = "SELECT *
                FROM {question_categories}
                WHERE contextid = :contextid
                AND parent = 0
                AND " . $DB->sql_like('name', ':pattern', false, false) . "
                ORDER BY LENGTH(name) ASC
                LIMIT 1";
        
        $olution = $DB->get_record_sql($sql, [
            'contextid' => $systemcontext->id,
            'pattern' => '% Olution %'
        ]);
        
        if ($olution) {
            debugging('✅ Olution category found - Contains " Olution ": ' . $olution->name, DEBUG_DEVELOPER);
            return $olution;
        }
        
        // ==================================================================================
        // PRIORITÉ 6 : Nom contenant "Olution" sans espaces (plus flexible)
        // Exemples : "OlutionQCM", "BanqueOlution"
        // ==================================================================================
        $sql = "SELECT *
                FROM {question_categories}
                WHERE contextid = :contextid
                AND parent = 0
                AND " . $DB->sql_like('name', ':pattern', false, false) . "
                ORDER BY " . $DB->sql_position("'Olution'", 'name') . " ASC, LENGTH(name) ASC
                LIMIT 1";
        
        $olution = $DB->get_record_sql($sql, [
            'contextid' => $systemcontext->id,
            'pattern' => '%Olution%'
        ]);
        
        if ($olution) {
            debugging('⚠️ Olution category found - Contains "Olution" (flexible): ' . $olution->name, DEBUG_DEVELOPER);
            return $olution;
        }
        
        // ==================================================================================
        // PRIORITÉ 7 : EN DERNIER RECOURS - Description contenant "olution"
        // SEULEMENT si le nom est court et potentiellement pertinent
        // ==================================================================================
        $sql = "SELECT *
                FROM {question_categories}
                WHERE contextid = :contextid
                AND parent = 0
                AND " . $DB->sql_like('info', ':pattern', false, false) . "
                AND LENGTH(name) <= 50
                ORDER BY " . $DB->sql_position("'olution'", 'info') . " ASC
                LIMIT 1";
        
        $olution = $DB->get_record_sql($sql, [
            'contextid' => $systemcontext->id,
            'pattern' => '%olution%'
        ]);
        
        if ($olution) {
            debugging('⚠️ Olution category found - Via description (last resort): ' . $olution->name, DEBUG_DEVELOPER);
            return $olution;
        }
        
        // ==================================================================================
        // PHASE 2 : Recherche dans la CATÉGORIE DE COURS "Olution" (si Phase 1 échoue)
        // ==================================================================================
        debugging('🔄 Phase 1 failed, trying Phase 2: Search in course category "Olution"', DEBUG_DEVELOPER);
        
        // 1. Rechercher la catégorie de cours "Olution" (ID 78 selon l'utilisateur)
        $course_category_sql = "SELECT id, name 
                               FROM {course_categories} 
                               WHERE " . $DB->sql_like('name', ':pattern', false, false) . "
                               OR id = 78
                               ORDER BY CASE WHEN id = 78 THEN 0 ELSE 1 END, " . $DB->sql_position("'Olution'", 'name') . " ASC, LENGTH(name) ASC
                               LIMIT 1";
        
        $olution_course_category = $DB->get_record_sql($course_category_sql, ['pattern' => '%Olution%']);
        
        if (!$olution_course_category) {
            debugging('❌ No course category "Olution" found', DEBUG_DEVELOPER);
            return false;
        }
        
        debugging('✅ Found course category "Olution": ' . $olution_course_category->name . ' (ID: ' . $olution_course_category->id . ')', DEBUG_DEVELOPER);
        
        // 2. Rechercher tous les cours dans cette catégorie
        $courses_sql = "SELECT c.id, c.fullname, c.shortname, c.category
                       FROM {course} c 
                       WHERE c.category = :category_id
                       ORDER BY c.fullname ASC";
        
        $courses = $DB->get_records_sql($courses_sql, ['category_id' => $olution_course_category->id]);
        
        debugging('🔍 Found ' . count($courses) . ' courses in Olution category (ID: ' . $olution_course_category->id . ')', DEBUG_DEVELOPER);
        
        foreach ($courses as $course) {
            debugging('🎯 Checking course: ' . $course->fullname . ' (ID: ' . $course->id . ')', DEBUG_DEVELOPER);
            
            // 3. Récupérer le contexte de ce cours
            $course_context = $DB->get_record('context', [
                'contextlevel' => CONTEXT_COURSE,
                'instanceid' => $course->id
            ]);
            
            if (!$course_context) {
                continue;
            }
            
            // 4. Chercher les catégories de questions dans ce contexte de cours
            $course_categories_sql = "SELECT *
                                     FROM {question_categories}
                                     WHERE contextid = :contextid
                                     AND parent = 0
                                     ORDER BY name ASC";
            
            $course_categories = $DB->get_records_sql($course_categories_sql, [
                'contextid' => $course_context->id
            ]);
            
            debugging('📂 Found ' . count($course_categories) . ' question categories in course context', DEBUG_DEVELOPER);
            
            // 5. Vérifier si une de ces catégories contient "Olution"
            foreach ($course_categories as $cat) {
                if (stripos($cat->name, 'olution') !== false) {
                    debugging('✅ Olution question category found in course: ' . $cat->name . ' (Course: ' . $course->fullname . ')', DEBUG_DEVELOPER);
                    
                    // Ajouter des informations sur le cours et la catégorie de cours parent
                    $cat->course_name = $course->fullname;
                    $cat->course_id = $course->id;
                    $cat->course_category_name = $olution_course_category->name;
                    $cat->course_category_id = $olution_course_category->id;
                    $cat->context_type = 'course_category';
                    
                    return $cat;
                }
            }
            
            // 6. Si pas de catégorie nommée Olution, prendre la première catégorie du cours
            if (!empty($course_categories)) {
                $first_category = reset($course_categories);
                debugging('✅ Using first question category from course in Olution: ' . $first_category->name . ' (Course: ' . $course->fullname . ')', DEBUG_DEVELOPER);
                
                // Ajouter des informations sur le cours et la catégorie de cours parent
                $first_category->course_name = $course->fullname;
                $first_category->course_id = $course->id;
                $first_category->course_category_name = $olution_course_category->name;
                $first_category->course_category_id = $olution_course_category->id;
                $first_category->context_type = 'course_category';
                
                return $first_category;
            }
        }
        
        // Aucune catégorie Olution trouvée dans aucun contexte
        debugging('❌ No Olution category found in system, course, or course category contexts', DEBUG_DEVELOPER);
        return false;
        
    } catch (Exception $e) {
        debugging('Error finding Olution category: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return false;
    }
}

/**
 * Récupère toutes les sous-catégories d'Olution (récursif)
 * 
 * 🆕 v1.10.4 : Récupère la structure complète d'Olution
 * 🔄 v1.10.9 : CORRECTION FINALE - Récupère les sous-catégories de QUESTIONS
 * 
 * @param int|null $parent_id ID de la catégorie parente (null = Olution racine)
 * @return array Tableau de toutes les sous-catégories (récursif)
 */
function local_question_diagnostic_get_olution_subcategories($parent_id = null) {
    global $DB;
    
    try {
        if ($parent_id === null) {
            $olution = local_question_diagnostic_find_olution_category();
            if (!$olution) {
                return [];
            }
            $parent_id = $olution->id;
        }
        
        // Récupérer les sous-catégories directes
        $direct_children = $DB->get_records('question_categories', ['parent' => $parent_id]);
        
        $all_subcategories = [];
        
        foreach ($direct_children as $child) {
            $all_subcategories[] = $child;
            
            // Récupérer récursivement les sous-catégories de cette catégorie
            $children_of_child = local_question_diagnostic_get_olution_subcategories($child->id);
            $all_subcategories = array_merge($all_subcategories, $children_of_child);
        }
        
        return $all_subcategories;
        
    } catch (Exception $e) {
        debugging('Error getting Olution subcategories: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return [];
    }
}

/**
 * Récupère toutes les catégories de cours disponibles
 * 
 * 🆕 v1.11.5 : Fonction pour lister les catégories de cours
 * Cette fonction permet de récupérer toutes les catégories de cours
 * pour permettre le filtrage des questions par catégorie de cours.
 * 
 * @return array Tableau des catégories de cours avec métadonnées
 */
function local_question_diagnostic_get_course_categories() {
    global $DB;
    
    try {
        $sql = "SELECT cc.id, cc.name, cc.description, cc.parent,
                       COUNT(c.id) as course_count
                FROM {course_categories} cc
                LEFT JOIN {course} c ON c.category = cc.id
                GROUP BY cc.id, cc.name, cc.description, cc.parent
                ORDER BY cc.name ASC";
        
        $course_categories = $DB->get_records_sql($sql);
        
        // Enrichir avec les informations de contexte
        foreach ($course_categories as $cat) {
            $cat->formatted_name = format_string($cat->name);
            $cat->has_courses = $cat->course_count > 0;
        }
        
        return $course_categories;
        
    } catch (Exception $e) {
        debugging('Error getting course categories: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return [];
    }
}

/**
 * Affiche le bouton de purge des caches
 * 
 * 🔧 v1.11.10 : Fonction utilitaire pour ajouter le bouton de purge des caches
 * à toutes les pages du plugin pour faciliter le débogage et la maintenance.
 * 
 * @return string HTML du bouton de purge des caches
 */
function local_question_diagnostic_render_cache_purge_button() {
    global $OUTPUT;
    
    $purge_url = new moodle_url('/local/question_diagnostic/purge_cache.php', [
        'sesskey' => sesskey(),
        'return_url' => qualified_me()
    ]);
    
    return html_writer::link(
        $purge_url,
        '🗑️ Purger les caches',
        [
            'class' => 'btn btn-warning btn-sm',
            'title' => 'Purger tous les caches du plugin (recommandé après modifications)',
            'style' => 'margin-left: 10px;'
        ]
    );
}

/**
 * Récupère les catégories de questions avec leur hiérarchie pour une catégorie de cours
 * 
 * 🔧 v1.11.13 : CORRECTION MAJEURE - Utilise la même logique que le déplacement vers Olution
 * Au lieu de chercher dans les cours de la catégorie "olution", cherche directement
 * la catégorie de QUESTIONS "Olution" (système) et ses sous-catégories.
 * 
 * @param int $course_category_id ID de la catégorie de cours (utilisé pour déterminer la logique)
 * @return array Structure hiérarchique des catégories
 */
function local_question_diagnostic_get_question_categories_hierarchy($course_category_id) {
    global $DB;
    
    try {
        // ==================================================================================
        // LOGIQUE SPÉCIALE POUR LA CATÉGORIE "OLUTION"
        // ==================================================================================
        
        // Récupérer le nom de la catégorie de cours
        $course_category = $DB->get_record('course_categories', ['id' => $course_category_id]);
        if (!$course_category) {
            debugging('Course category not found: ' . $course_category_id, DEBUG_DEVELOPER);
            return [];
        }
        
        $course_category_name = strtolower(trim($course_category->name));
        
        // Si c'est la catégorie "olution", utiliser la logique spéciale
        if ($course_category_name === 'olution') {
            debugging('🔍 Using special Olution logic for course category: ' . $course_category_name, DEBUG_DEVELOPER);
            
            // Chercher la catégorie de QUESTIONS "Olution" (système)
            $olution_category = local_question_diagnostic_find_olution_category();
            if (!$olution_category) {
                debugging('❌ Olution question category not found', DEBUG_DEVELOPER);
                return [];
            }
            
            debugging('✅ Found Olution question category: ' . $olution_category->name . ' (ID: ' . $olution_category->id . ')', DEBUG_DEVELOPER);
            
            // Récupérer TOUTES les catégories dans la hiérarchie d'Olution (racine + sous-catégories)
            $all_olution_categories = [];
            
            // Ajouter la catégorie racine Olution
            $all_olution_categories[] = $olution_category;
            
            // Récupérer toutes les sous-catégories d'Olution
            $olution_subcategories = local_question_diagnostic_get_olution_subcategories($olution_category->id);
            $all_olution_categories = array_merge($all_olution_categories, $olution_subcategories);
            
            debugging('📊 Found ' . count($all_olution_categories) . ' categories in Olution hierarchy', DEBUG_DEVELOPER);
            
            // Enrichir avec les statistiques et informations de contexte
            $categories = [];
            foreach ($all_olution_categories as $cat) {
                $category = new stdClass();
                $category->id = $cat->id;
                $category->name = $cat->name;
                $category->info = $cat->info ?? '';
                $category->parent = $cat->parent;
                $category->sortorder = $cat->sortorder ?? 0;
                
                // Compter les questions pour cette catégorie
                $question_count = $DB->count_records_sql(
                    "SELECT COUNT(DISTINCT q.id) 
                     FROM {question} q
                     INNER JOIN {question_versions} qv ON qv.questionid = q.id
                     INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                     WHERE qbe.questioncategoryid = :categoryid",
                    ['categoryid' => $cat->id]
                );
                $category->total_questions = $question_count;
                
                // Déterminer le type de contexte
                $context = $DB->get_record('context', ['id' => $cat->contextid]);
                if ($context) {
                    switch ($context->contextlevel) {
                        case CONTEXT_SYSTEM:
                            $category->context_type = 'system';
                            $category->context_display_name = 'Système';
                            break;
                        case CONTEXT_COURSE:
                            $course = $DB->get_record('course', ['id' => $context->instanceid]);
                            $category->context_type = 'course';
                            $category->context_display_name = $course ? $course->fullname : 'Cours inconnu';
                            break;
                        case CONTEXT_MODULE:
                            $category->context_type = 'module';
                            $category->context_display_name = 'Module';
                            break;
                        default:
                            $category->context_type = 'unknown';
                            $category->context_display_name = 'Inconnu';
                    }
                } else {
                    $category->context_type = 'unknown';
                    $category->context_display_name = 'Contexte invalide';
                }
                
                $categories[] = $category;
            }
            
            // Construire la hiérarchie
            return local_question_diagnostic_build_category_hierarchy($categories);
        }
        
        // ==================================================================================
        // LOGIQUE STANDARD POUR LES AUTRES CATÉGORIES DE COURS
        // ==================================================================================
        
        // Pour les autres catégories, utiliser la logique existante
        $categories_with_stats = local_question_diagnostic_get_question_categories_by_course_category($course_category_id);
        
        if (empty($categories_with_stats)) {
            return [];
        }
        
        // Convertir en objets simples pour la construction de la hiérarchie
        $categories = [];
        foreach ($categories_with_stats as $item) {
            $category = new stdClass();
            $category->id = $item->id;
            $category->name = $item->name;
            $category->info = $item->info ?? '';
            $category->parent = $item->parent;
            $category->sortorder = $item->sortorder ?? 0;
            $category->total_questions = $item->total_questions ?? 0;
            $category->context_display_name = $item->context_display_name ?? '';
            $category->context_type = $item->context_type ?? 'unknown';
            $categories[] = $category;
        }
        
        // Construire la hiérarchie
        return local_question_diagnostic_build_category_hierarchy($categories);
        
    } catch (Exception $e) {
        debugging('Error getting question categories hierarchy: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return [];
    }
}

/**
 * Construit la structure hiérarchique des catégories
 * 
 * @param array $categories Liste plate des catégories
 * @return array Structure hiérarchique
 */
function local_question_diagnostic_build_category_hierarchy($categories) {
    $hierarchy = [];
    $category_map = [];
    
    // Créer un map pour accès rapide
    foreach ($categories as $category) {
        $category_map[$category->id] = $category;
        $category->children = [];
    }
    
    // Construire la hiérarchie
    foreach ($categories as $category) {
        if ($category->parent == 0) {
            // Catégorie racine
            $hierarchy[] = $category;
        } else {
            // Catégorie enfant
            if (isset($category_map[$category->parent])) {
                $category_map[$category->parent]->children[] = $category;
            }
        }
    }
    
    return $hierarchy;
}

/**
 * Rendu hiérarchique des catégories de questions
 * 
 * 🔧 v1.11.11 : Affiche les catégories en arbre comme dans la banque de questions Moodle
 * 
 * @param array $hierarchy Structure hiérarchique des catégories
 * @param int $level Niveau d'indentation (0 = racine)
 * @return string HTML du rendu hiérarchique
 */
function local_question_diagnostic_render_category_hierarchy($hierarchy, $level = 0) {
    $html = '';
    
    foreach ($hierarchy as $category) {
        $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);
        $count = (int)($category->total_questions ?? 0);
        
        // Icône selon le type de contexte
        $icon = '';
        switch ($category->context_type ?? 'unknown') {
            case 'system':
                $icon = '🌐';
                break;
            case 'course':
                $icon = '📚';
                break;
            case 'module':
                $icon = '📝';
                break;
            default:
                $icon = '📁';
        }
        
        // Couleur selon le nombre de questions
        $badge_class = 'badge-secondary';
        if ($count > 0) {
            $badge_class = $count > 10 ? 'badge-success' : 'badge-primary';
        }
        
        // Lien de purge
        $purge_url = new moodle_url('/local/question_diagnostic/actions/delete.php', [
            'id' => $category->id,
            'preview' => 1,
            'sesskey' => sesskey()
        ]);
        
        $html .= html_writer::start_div('qd-category-item', [
            'style' => 'margin: 4px 0; padding: 8px; border-left: 3px solid #e9ecef; background: ' . ($level % 2 == 0 ? '#f8f9fa' : '#ffffff') . ';'
        ]);
        
        $html .= $indent . $icon . ' ';
        $html .= html_writer::tag('strong', format_string($category->name));
        $html .= ' ';
        $html .= html_writer::tag('span', '(' . $count . ')', ['class' => 'badge ' . $badge_class]);
        $html .= ' ';
        $html .= html_writer::link($purge_url, 'Purge this category', [
            'class' => 'btn btn-xs btn-danger',
            'style' => 'margin-left: 8px;'
        ]);
        
        // Description si disponible
        if (!empty($category->info)) {
            $html .= html_writer::start_div('qd-category-description', [
                'style' => 'margin-left: ' . (($level + 1) * 20) . 'px; font-size: 0.9em; color: #6c757d; margin-top: 4px;'
            ]);
            $html .= format_string($category->info);
            $html .= html_writer::end_div();
        }
        
        $html .= html_writer::end_div();
        
        // Rendu récursif des enfants
        if (!empty($category->children)) {
            $html .= local_question_diagnostic_render_category_hierarchy($category->children, $level + 1);
        }
    }
    
    return $html;
}

/**
 * Récupère tous les cours dans une catégorie de cours et ses sous-catégories (récursif)
 * 
 * 🔧 v1.11.8 : CORRECTION MAJEURE - Inclut les sous-catégories de cours
 * Cette fonction résout le problème où une catégorie parent (comme "Olution") 
 * ne contient pas de cours directement mais a des sous-catégories avec des cours.
 * 
 * @param int $course_category_id ID de la catégorie de cours
 * @return array Tableau des cours avec métadonnées
 */
function local_question_diagnostic_get_courses_in_category_recursive($course_category_id) {
    global $DB;
    
    try {
        $all_courses = [];
        
        // Fonction récursive pour parcourir les sous-catégories
        $get_courses_recursive = function($category_id) use (&$get_courses_recursive, &$all_courses, $DB) {
            // 1. Récupérer les cours directement dans cette catégorie
            $direct_courses = $DB->get_records('course', ['category' => $category_id], 'fullname ASC');
            foreach ($direct_courses as $course) {
                $all_courses[$course->id] = $course;
            }
            
            // 2. Récupérer les sous-catégories de cette catégorie
            $subcategories = $DB->get_records('course_categories', ['parent' => $category_id], 'name ASC');
            
            // 3. Récursivement traiter chaque sous-catégorie
            foreach ($subcategories as $subcategory) {
                $get_courses_recursive($subcategory->id);
            }
        };
        
        // Démarrer la récursion
        $get_courses_recursive($course_category_id);
        
        debugging('Recursive search found ' . count($all_courses) . ' courses in category ID: ' . $course_category_id, DEBUG_DEVELOPER);
        
        return $all_courses;
        
    } catch (Exception $e) {
        debugging('Error getting courses recursively: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return [];
    }
}

/**
 * Récupère les catégories de questions associées à une catégorie de cours
 * 
 * 🆕 v1.11.5 : Fonction pour filtrer les questions par catégorie de cours
 * 🔧 v1.11.6 : CORRECTION MAJEURE - Reproduit exactement la vue de la banque de questions Moodle
 * 🔧 v1.11.7 : CORRECTION SQL - Simplification pour compatibilité multi-SGBD
 * 
 * Cette fonction reproduit exactement ce que l'utilisateur voit dans la banque de questions Moodle
 * quand il sélectionne une catégorie de cours. Elle inclut :
 * - Les catégories de questions des cours dans la catégorie de cours sélectionnée
 * - Les catégories de questions système (si visibles)
 * - Les catégories de questions des modules des cours dans la catégorie
 * 
 * @param int $course_category_id ID de la catégorie de cours
 * @return array Tableau des catégories de questions avec métadonnées
 */
function local_question_diagnostic_get_question_categories_by_course_category($course_category_id) {
    global $DB;
    
    try {
        // 1. Récupérer tous les cours dans cette catégorie de cours ET ses sous-catégories
        $courses = local_question_diagnostic_get_courses_in_category_recursive($course_category_id);
        
        if (empty($courses)) {
            debugging('No courses found in course category ID: ' . $course_category_id . ' (including subcategories)', DEBUG_DEVELOPER);
            return [];
        }
        
        debugging('Found ' . count($courses) . ' courses in course category ID: ' . $course_category_id . ' (including subcategories)', DEBUG_DEVELOPER);
        
        $course_ids = array_keys($courses);
        list($course_ids_sql, $course_params) = $DB->get_in_or_equal($course_ids, SQL_PARAMS_NAMED);
        
        // 2. Récupérer les contextes de cours
        $contexts_sql = "SELECT id, instanceid
                        FROM {context}
                        WHERE contextlevel = :contextlevel
                        AND instanceid " . $course_ids_sql;
        
        $contexts = $DB->get_records_sql($contexts_sql, array_merge(
            ['contextlevel' => CONTEXT_COURSE],
            $course_params
        ));
        
        if (empty($contexts)) {
            debugging('No course contexts found for courses in category ID: ' . $course_category_id, DEBUG_DEVELOPER);
            return [];
        }
        
        debugging('Found ' . count($contexts) . ' course contexts', DEBUG_DEVELOPER);
        
        $context_ids = array_keys($contexts);
        list($context_ids_sql, $context_params) = $DB->get_in_or_equal($context_ids);
        
        // 3. Récupérer les contextes de modules des cours dans cette catégorie
        $module_contexts_sql = "SELECT ctx.id, ctx.instanceid, cm.course
                                FROM {context} ctx
                                INNER JOIN {course_modules} cm ON cm.id = ctx.instanceid
                                WHERE ctx.contextlevel = :contextlevel
                                AND cm.course " . $course_ids_sql;
        
        $module_contexts = $DB->get_records_sql($module_contexts_sql, array_merge(
            ['contextlevel' => CONTEXT_MODULE],
            $course_params
        ));
        
        debugging('Found ' . count($module_contexts) . ' module contexts', DEBUG_DEVELOPER);
        
        // 4. Récupérer le contexte système (si accessible)
        $system_context = context_system::instance();
        
        // 5. Construire la liste de tous les contextes à inclure
        $all_context_ids = array_merge($context_ids, array_keys($module_contexts));
        $all_context_ids[] = $system_context->id; // Ajouter le contexte système
        
        $all_context_ids = array_unique($all_context_ids);
        list($all_context_ids_sql, $all_context_params) = $DB->get_in_or_equal($all_context_ids, SQL_PARAMS_NAMED);
        
        debugging('Total contexts to search: ' . count($all_context_ids), DEBUG_DEVELOPER);
        
        // 6. Récupérer les catégories de questions avec informations de base (SANS CONCAT)
        $question_categories_sql = "SELECT qc.*, 
                                          ctx.contextlevel,
                                          ctx.instanceid
                                   FROM {question_categories} qc
                                   INNER JOIN {context} ctx ON ctx.id = qc.contextid
                                   WHERE qc.contextid " . $all_context_ids_sql . "
                                   ORDER BY ctx.contextlevel ASC, qc.name ASC";
        
        $question_categories = $DB->get_records_sql($question_categories_sql, $all_context_params);
        
        debugging('Found ' . count($question_categories) . ' question categories', DEBUG_DEVELOPER);
        
        // 7. Enrichir les données en PHP (plus robuste que SQL)
        foreach ($question_categories as $cat) {
            // Déterminer le type de contexte et construire les informations
            $context_type = 'unknown';
            $context_display_name = 'Inconnu';
            $course_name = '';
            $course_id = 0;
            
            switch ($cat->contextlevel) {
                case CONTEXT_SYSTEM:
                    $context_type = 'system';
                    $context_display_name = 'Système';
                    break;
                    
                case CONTEXT_COURSE:
                    $context_type = 'course';
                    $course_id = $cat->instanceid;
                    $course = $DB->get_record('course', ['id' => $course_id]);
                    if ($course) {
                        $course_name = $course->fullname;
                        $context_display_name = $course->fullname;
                    } else {
                        $context_display_name = 'Cours ID: ' . $course_id;
                    }
                    break;
                    
                case CONTEXT_MODULE:
                    $context_type = 'module';
                    $module_id = $cat->instanceid;
                    
                    // Récupérer les informations du module
                    $module_info = $DB->get_record_sql("
                        SELECT cm.id, cm.course, m.name as module_name, 
                               CASE 
                                   WHEN m.name = 'quiz' THEN q.name
                                   WHEN m.name = 'lesson' THEN l.name
                                   ELSE 'Module'
                               END as activity_name
                        FROM {course_modules} cm
                        INNER JOIN {modules} m ON m.id = cm.module
                        LEFT JOIN {quiz} q ON q.id = cm.instance AND m.name = 'quiz'
                        LEFT JOIN {lesson} l ON l.id = cm.instance AND m.name = 'lesson'
                        WHERE cm.id = :module_id
                    ", ['module_id' => $module_id]);
                    
                    if ($module_info) {
                        $course_name = $DB->get_field('course', 'fullname', ['id' => $module_info->course]);
                        $context_display_name = $module_info->module_name . ': ' . $module_info->activity_name;
                        if ($course_name) {
                            $context_display_name .= ' (' . $course_name . ')';
                        }
                        $course_id = $module_info->course;
                    } else {
                        $context_display_name = 'Module ID: ' . $module_id;
                    }
                    break;
            }
            
            // Assigner les propriétés enrichies
            $cat->context_type = $context_type;
            $cat->context_display_name = $context_display_name;
            $cat->course_name = $course_name;
            $cat->course_id = $course_id;
            
            // Compter les questions dans cette catégorie (Moodle 4.5)
            $questions_sql = "SELECT COUNT(DISTINCT q.id) as total_questions,
                                     SUM(CASE WHEN qv.status != 'hidden' THEN 1 ELSE 0 END) as visible_questions
                              FROM {question_bank_entries} qbe
                              INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                              INNER JOIN {question} q ON q.id = qv.questionid
                              WHERE qbe.questioncategoryid = :categoryid";
            
            $question_stats = $DB->get_record_sql($questions_sql, ['categoryid' => $cat->id]);
            
            $cat->total_questions = $question_stats ? $question_stats->total_questions : 0;
            $cat->visible_questions = $question_stats ? $question_stats->visible_questions : 0;
            
            // Compter les sous-catégories
            $subcat_count = $DB->count_records('question_categories', ['parent' => $cat->id]);
            $cat->subcategory_count = $subcat_count;
            
            // Déterminer le statut
            if ($cat->total_questions == 0 && $cat->subcategory_count == 0) {
                $cat->status = 'empty';
            } else {
                $cat->status = 'ok';
            }
            
            // Vérifier si c'est une catégorie protégée
            $cat->is_protected = (
                stripos($cat->name, 'default for') === 0 ||
                $cat->parent == 0 ||
                !empty($cat->info)
            );
        }
        
        debugging('Successfully processed ' . count($question_categories) . ' question categories', DEBUG_DEVELOPER);
        return $question_categories;
        
    } catch (Exception $e) {
        debugging('Error getting question categories by course category: ' . $e->getMessage(), DEBUG_DEVELOPER);
        
        // Fallback : essayer une requête plus simple (seulement contextes de cours)
        try {
            debugging('Attempting fallback with course contexts only', DEBUG_DEVELOPER);
            
            $courses = $DB->get_records('course', ['category' => $course_category_id], 'fullname ASC');
            if (empty($courses)) {
                return [];
            }
            
            $course_ids = array_keys($courses);
            list($course_ids_sql, $course_params) = $DB->get_in_or_equal($course_ids, SQL_PARAMS_NAMED);
            
            $fallback_sql = "SELECT qc.*, c.fullname as course_name, c.id as course_id
                             FROM {question_categories} qc
                             INNER JOIN {context} ctx ON ctx.id = qc.contextid
                             INNER JOIN {course} c ON c.id = ctx.instanceid
                             WHERE ctx.contextlevel = :contextlevel
                             AND c.id " . $course_ids_sql;
            
            $fallback_categories = $DB->get_records_sql($fallback_sql, array_merge(
                ['contextlevel' => CONTEXT_COURSE],
                $course_params
            ));
            
            // Enrichir avec les propriétés de base
            foreach ($fallback_categories as $cat) {
                $cat->context_type = 'course';
                $cat->context_display_name = $cat->course_name;
                $cat->total_questions = 0;
                $cat->visible_questions = 0;
                $cat->subcategory_count = 0;
                $cat->status = 'ok';
                $cat->is_protected = false;
            }
            
            debugging('Fallback successful: found ' . count($fallback_categories) . ' categories', DEBUG_DEVELOPER);
            return $fallback_categories;
            
        } catch (Exception $fallback_error) {
            debugging('Fallback also failed: ' . $fallback_error->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
    }
}


