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

