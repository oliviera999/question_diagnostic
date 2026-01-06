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

// Charger les gestionnaires centralisés
require_once(__DIR__ . '/classes/debug_manager.php');
require_once(__DIR__ . '/classes/error_manager.php');

/**
 * Debug contrôlé du plugin (désactivé par défaut).
 *
 * Objectif : éviter de polluer l'UI en mode DEBUG_DEVELOPER, tout en gardant
 * la possibilité d'activer des traces au besoin.
 *
 * Activation :
 * - via config : get_config('local_question_diagnostic', 'debuglogs') = 1
 * - ou via URL : ?qddebug=1 (admin uniquement)
 *
 * @param string $message Message à logger
 * @param int $level Niveau Moodle (ex: DEBUG_DEVELOPER)
 * @return void
 */
function local_question_diagnostic_debug_log(string $message, int $level = DEBUG_DEVELOPER): void {
    // Sécurité : logs uniquement pour admin.
    if (!is_siteadmin()) {
        return;
    }

    $enabled = (bool)get_config('local_question_diagnostic', 'debuglogs');

    // Option de debug ponctuel via URL.
    $urlenabled = false;
    if (function_exists('optional_param')) {
        $urlenabled = (bool)optional_param('qddebug', 0, PARAM_BOOL);
    }

    if ($enabled || $urlenabled) {
        debugging($message, $level);
    }
}

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

    // Cache simple (évite N+1 sur les pages listes).
    static $cache = [];
    $cachekey = $contextid . '|' . (int)$include_id;
    if (isset($cache[$cachekey])) {
        return $cache[$cachekey];
    }
    
    $result = (object)[
        'context_name' => 'Inconnu',
        'course_name' => null,
        'course_id' => null,
        'module_name' => null,
        'module_id' => null,
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
                $result->course_id = (int)$course->id;
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
                $result->module_id = (int)$cm->id;
                // Obtenir le nom du cours parent
                $course = $DB->get_record('course', ['id' => $cm->course], 'id, fullname, shortname');
                if ($course) {
                    $result->course_id = (int)$course->id;
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

    $cache[$cachekey] = $result;
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
        local_question_diagnostic_debug_log('Erreur dans local_question_diagnostic_get_used_question_ids: ' . $e->getMessage(), DEBUG_DEVELOPER);
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
        // Si la catégorie est absente/incomplète mais qu'on a un questionid,
        // essayer de déduire la catégorie depuis l'architecture Moodle 4.x :
        // question_versions -> question_bank_entries -> question_categories.
        if ((!is_object($category) || empty($category->id) || empty($category->contextid)) && $questionid !== null) {
            $qid = (int)$questionid;
            if ($qid > 0) {
                $sql = "SELECT qc.id, qc.contextid
                          FROM {question_versions} qv
                          INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                          INNER JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                         WHERE qv.questionid = :qid";
                $derived = $DB->get_record_sql($sql, ['qid' => $qid], IGNORE_MISSING);
                if ($derived && !empty($derived->id) && !empty($derived->contextid)) {
                    $category = $derived;
                }
            }
        }

        // Déterminer le courseid à partir du contexte
        if (!is_object($category) || empty($category->contextid) || empty($category->id)) {
            // Fallback final : si on ne peut pas construire un URL "question bank", renvoyer
            // un lien direct vers l'édition de la question (évite les liens cassés).
            if ($questionid !== null && (int)$questionid > 0) {
                return new moodle_url('/question/bank/editquestion/question.php', [
                    'id' => (int)$questionid,
                ]);
            }
            return null;
        }

        $context = context::instance_by_id($category->contextid, IGNORE_MISSING);
        
        if (!$context) {
            // Si le contexte n'existe pas, retourner null
            if ($questionid !== null && (int)$questionid > 0) {
                return new moodle_url('/question/bank/editquestion/question.php', [
                    'id' => (int)$questionid,
                ]);
            }
            return null;
        }
        
        $courseid = 0; // Par défaut, système
        $cmid = 0;
        
        // Si c'est un contexte de cours, récupérer l'ID du cours
        if ($context->contextlevel == CONTEXT_COURSE) {
            $courseid = $context->instanceid;
        } else if ($context->contextlevel == CONTEXT_MODULE) {
            // Si c'est un module, remonter au cours parent
            $coursecontext = $context->get_course_context(false);
            if ($coursecontext) {
                $courseid = $coursecontext->instanceid;
            }
            // Pour ouvrir la banque de questions dans le contexte d'activité,
            // Moodle utilise souvent cmid.
            $cmid = (int)$context->instanceid;
        } else if ($context->contextlevel == CONTEXT_SYSTEM) {
            // 🔧 FIX: Pour contexte système, utiliser SITEID au lieu de 0
            // courseid=0 cause l'erreur "course not found"
            $courseid = SITEID;
        } else {
            // Autres contextes (ex: CONTEXT_COURSECAT) :
            // utiliser SITEID pour permettre l'ouverture de la banque de questions avec le paramètre cat=...
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

        if ($cmid > 0) {
            $params['cmid'] = $cmid;
        }
        
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
 * Extrait un texte court depuis un HTML (pour affichage en table).
 *
 * @param string $html
 * @param int $length
 * @return string
 */
function local_question_diagnostic_get_text_excerpt(string $html, int $length = 120): string {
    $text = strip_tags($html);
    $text = trim((string)preg_replace('/\s+/', ' ', $text));
    if ($length > 0 && strlen($text) > $length) {
        $text = substr($text, 0, $length) . '...';
    }
    return $text;
}

/**
 * Construit un chemin lisible d'une catégorie de questions (breadcrumb) pour l'affichage.
 *
 * - Utilise question_categories.path si disponible (Moodle standard)
 * - Fallback sur la relation parent (parcours) si path absent
 *
 * @param int $categoryid
 * @param string $separator
 * @return string
 */
function local_question_diagnostic_get_question_category_breadcrumb(int $categoryid, string $separator = ' / '): string {
    global $DB;

    $categoryid = (int)$categoryid;
    if ($categoryid <= 0) {
        return '';
    }

    static $haspath = null;
    static $pathcache = [];
    static $namecache = [];
    static $parentcache = [];
    static $crumbcache = [];

    $cachekey = $categoryid . '|' . $separator;
    if (isset($crumbcache[$cachekey])) {
        return $crumbcache[$cachekey];
    }

    if ($haspath === null) {
        try {
            $cols = $DB->get_columns('question_categories');
            $haspath = isset($cols['path']);
        } catch (\Exception $e) {
            $haspath = false;
        }
    }

    // Charger infos minimales.
    if (!isset($namecache[$categoryid]) || (!isset($parentcache[$categoryid]) && !$haspath)) {
        $fields = 'id,name,parent';
        if ($haspath) {
            $fields .= ',path';
        }
        $rec = $DB->get_record('question_categories', ['id' => $categoryid], $fields, IGNORE_MISSING);
        if (!$rec) {
            $crumbcache[$cachekey] = '';
            return '';
        }
        $namecache[$categoryid] = (string)$rec->name;
        $parentcache[$categoryid] = (int)$rec->parent;
        if ($haspath) {
            $pathcache[$categoryid] = (string)($rec->path ?? '');
        }
    }

    $ids = [];

    if ($haspath && !empty($pathcache[$categoryid])) {
        // Path du type "/12/34/56".
        $raw = trim((string)$pathcache[$categoryid]);
        $parts = array_values(array_filter(explode('/', $raw)));
        foreach ($parts as $p) {
            $pid = (int)$p;
            if ($pid > 0) {
                $ids[] = $pid;
            }
        }
    } else {
        // Fallback parent chain.
        $current = $categoryid;
        $visited = [];
        while ($current > 0) {
            if (isset($visited[$current])) {
                break;
            }
            $visited[$current] = true;
            $ids[] = $current;

            if (!isset($parentcache[$current])) {
                $p = $DB->get_record('question_categories', ['id' => $current], 'id,parent,name', IGNORE_MISSING);
                if (!$p) {
                    break;
                }
                $namecache[$current] = (string)$p->name;
                $parentcache[$current] = (int)$p->parent;
            }

            $current = (int)($parentcache[$current] ?? 0);
        }
        $ids = array_reverse($ids);
    }

    // Charger les noms manquants en batch.
    $missing = [];
    foreach ($ids as $id) {
        if (!isset($namecache[$id])) {
            $missing[] = (int)$id;
        }
    }
    if (!empty($missing)) {
        $recs = $DB->get_records_list('question_categories', 'id', $missing, '', 'id,name,parent');
        foreach ($recs as $r) {
            $namecache[(int)$r->id] = (string)$r->name;
            $parentcache[(int)$r->id] = (int)$r->parent;
        }
    }

    $names = [];
    foreach ($ids as $id) {
        $n = $namecache[$id] ?? null;
        if ($n === null || trim((string)$n) === '') {
            continue;
        }
        $names[] = format_string((string)$n);
    }

    $crumb = implode($separator, $names);
    $crumbcache[$cachekey] = $crumb;
    return $crumb;
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
        throw new \moodle_exception('nopermission', 'error', '', $permission);
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
        'categories_by_context.php' => 'index.php',
        'broken_links.php' => 'index.php',
        'questions_cleanup.php' => 'index.php',
        'help.php' => 'index.php',
        'audit_logs.php' => 'index.php',
        'monitoring.php' => 'index.php',
        'site_diagnostic.php' => 'index.php',
        'orphan_entries.php' => 'index.php',
        'test.php' => 'index.php',
        'debug_categories.php' => 'index.php',
        'quick_check_categories.php' => 'index.php',
        'check_default_categories.php' => 'index.php',
        'diagnose_dd_files.php' => 'index.php',

        // Olution triage → olution_duplicates.php
        'olution_triage.php' => 'olution_duplicates.php',
        'olution_auto_sort.php' => 'olution_duplicates.php',
        'ai_debug.php' => 'olution_auto_sort.php',
        
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
 * - categories_by_context.php ✅
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
        // Heuristique: sur certains sites, un "Olution" système existe mais n'est pas la source de vérité.
        // On score les candidats (présence de "commun", taille d'arbre) et on choisit le meilleur.
        // ==================================================================================
        $normalize = function(string $label): string {
            $label = trim($label);
            if (class_exists('\\core_text')) {
                if (method_exists('\\core_text', 'remove_accents')) {
                    $label = \core_text::remove_accents($label);
                } else if (method_exists('\\core_text', 'specialtoascii')) {
                    $label = \core_text::specialtoascii($label);
                }
                if (method_exists('\\core_text', 'strtolower')) {
                    $label = \core_text::strtolower($label);
                } else {
                    $label = strtolower($label);
                }
            } else {
                $label = strtolower($label);
            }
            $label = preg_replace('/\s+/', ' ', $label);
            return $label;
        };

        $scorecategory = function($cat) use ($DB, $normalize): int {
            if (!$cat || empty($cat->id)) {
                return 0;
            }

            $id = (int)$cat->id;
            $score = 0;

            try {
                $children = $DB->get_records('question_categories', ['parent' => $id], 'id ASC', 'id,name');
                $score += count($children);
                foreach ($children as $ch) {
                    if ($normalize((string)$ch->name) === 'commun') {
                        // Signal très fort : sur ce site, la racine Olution a une sous-catégorie directe "commun".
                        $score += 5000;
                        break;
                    }
                }

                // Taille d'arbre (si path dispo) = bon proxy de "richesse".
                $cols = $DB->get_columns('question_categories');
                if (isset($cols['path'])) {
                    $root = $DB->get_record('question_categories', ['id' => $id], 'id,path', IGNORE_MISSING);
                    if ($root && !empty($root->path)) {
                        $params = [
                            'id' => $id,
                            'path' => rtrim($root->path, '/') . '/%'
                        ];
                        $treecount = (int)$DB->count_records_select(
                            'question_categories',
                            'id = :id OR ' . $DB->sql_like('path', ':path', false, false),
                            $params
                        );
                        $score += $treecount;
                    }
                }
            } catch (\Exception $e) {
                // Score minimal.
            }

            return $score;
        };

        // ------------------------------------------------------------------------------
        // Validateur strict (Phase 2) : un candidat "Olution" doit avoir une sous-catégorie
        // directe nommée exactement "commun" (normalisée).
        //
        // Objectif: éviter les faux positifs (ex: sélectionner "Top" / "Default for ..." en contexte cours),
        // qui peuvent contenir un "commun" ailleurs dans l'arborescence mais pas comme enfant direct d'Olution.
        // ------------------------------------------------------------------------------
        $has_direct_commun_child = function(int $categoryid, int $contextid) use ($DB, $normalize): bool {
            $categoryid = (int)$categoryid;
            $contextid = (int)$contextid;
            if ($categoryid <= 0 || $contextid <= 0) {
                return false;
            }

            try {
                // Filtre LIKE pour limiter le volume, puis comparaison normalisée stricte.
                $sql = "SELECT id, name
                          FROM {question_categories}
                         WHERE contextid = :ctxid
                           AND parent = :parentid
                           AND " . $DB->sql_like('name', ':pattern', false, false);
                $children = $DB->get_records_sql($sql, [
                    'ctxid' => $contextid,
                    'parentid' => $categoryid,
                    'pattern' => '%commun%',
                ]);

                foreach ($children as $child) {
                    if ($normalize((string)$child->name) === 'commun') {
                        return true;
                    }
                }
            } catch (\Exception $e) {
                return false;
            }

            return false;
        };

        // ------------------------------------------------------------------------------
        // ANCRAGE (site spécifique) : si une catégorie de questions "commun" est connue par ID,
        // on peut retrouver la racine Olution via son parent.
        //
        // L'utilisateur indique : "commun" a très probablement l'ID 9472 et "olution" est en minuscules.
        // On ne hardcode pas une dépendance : si l'ID n'existe pas / n'est pas "commun", on ignore.
        // ------------------------------------------------------------------------------
        $forcedcommunid = 9472;
        try {
            $commun = $DB->get_record('question_categories', ['id' => (int)$forcedcommunid], 'id,name,parent,contextid', IGNORE_MISSING);
            if ($commun && (int)$commun->parent > 0 && $normalize((string)$commun->name) === 'commun') {
                $parent = $DB->get_record('question_categories', ['id' => (int)$commun->parent], '*', IGNORE_MISSING);
                if ($parent && (int)$parent->contextid === (int)$commun->contextid) {
                    // Vérification stricte : le parent doit bien avoir "commun" comme enfant direct.
                    if ($has_direct_commun_child((int)$parent->id, (int)$parent->contextid)) {
                        local_question_diagnostic_debug_log(
                            '✅ Forced detection via commun ID ' . (int)$forcedcommunid . ' → Olution root ID ' . (int)$parent->id,
                            DEBUG_DEVELOPER
                        );
                        return $parent;
                    }
                }
            }
        } catch (\Exception $e) {
            // Ignore: fallback to heuristics.
        }

        $systemcandidate = false;
        $coursecandidate = false;
        $bestcoursescore = 0;

        // ==================================================================================
        // PHASE 1 : Recherche dans les catégories de QUESTIONS système
        // ==================================================================================
        $systemcontext = context_system::instance();
        
        local_question_diagnostic_debug_log('🔍 Searching for Olution category in system context (ID: ' . $systemcontext->id . ')', DEBUG_DEVELOPER);
        
        // Dans Moodle, la catégorie racine visible est généralement "Top" (parent=0).
        // Les catégories réelles (ex: "Olution") sont souvent sous "Top" (parent != 0).
        // Ne pas supposer que parent = 0 pour "Olution".
        $roots = $DB->get_records('question_categories', [
            'contextid' => $systemcontext->id,
            'parent' => 0,
        ], 'sortorder ASC, id ASC', '*', 0, 1);
        $system_root_category = $roots ? reset($roots) : false;
        $system_top_id = $system_root_category ? (int)$system_root_category->id : 0;

        // ----------------------------------------------------------------------------------
        // RÈGLE PRIORITAIRE (robuste) : si on trouve une catégorie nommée exactement "commun"
        // dans le contexte système, alors son parent est un candidat "Olution" extrêmement probable.
        //
        // Cela corrige le cas fréquent où plusieurs catégories "Olution" existent au niveau système,
        // dont certaines sont vides / de test, et où la "vraie" arborescence est celle qui contient "commun".
        // ----------------------------------------------------------------------------------
        try {
            $communpattern = '%commun%';
            $sqlcommun = "SELECT id, name, parent, contextid
                            FROM {question_categories}
                           WHERE contextid = :ctxid
                             AND " . $DB->sql_like('name', ':pattern', false, false);
            $communrecs = $DB->get_records_sql($sqlcommun, [
                'ctxid' => (int)$systemcontext->id,
                'pattern' => $communpattern,
            ]);

            $bestparent = null;
            $bestparentscore = 0;
            foreach ($communrecs as $rec) {
                if (empty($rec->parent) || (int)$rec->parent <= 0) {
                    continue;
                }
                if ($normalize((string)$rec->name) !== 'commun') {
                    continue;
                }

                $parent = $DB->get_record('question_categories', ['id' => (int)$rec->parent], '*', IGNORE_MISSING);
                if (!$parent || (int)$parent->contextid !== (int)$systemcontext->id) {
                    continue;
                }

                // Bonus massif pour "parent de commun".
                $score = $scorecategory($parent) + 1000;
                if ($bestparent === null || $score > $bestparentscore) {
                    $bestparent = $parent;
                    $bestparentscore = $score;
                }
            }

            if ($bestparent) {
                local_question_diagnostic_debug_log(
                    '✅ Found system \"commun\"; selecting its parent as Olution candidate (ID: ' . (int)$bestparent->id . ', score=' . (int)$bestparentscore . ')',
                    DEBUG_DEVELOPER
                );
                $systemcandidate = $bestparent;
            }
        } catch (\Exception $e) {
            // Si la recherche échoue, on continue avec les heuristiques existantes.
        }
        
        // ==================================================================================
        // PRIORITÉ 1 : Nom EXACT "Olution" (case-sensitive) au niveau SYSTÈME
        // ==================================================================================
        // IMPORTANT : il peut exister plusieurs catégories "Olution" dans le même contexte.
        // On les SCORE et on choisit la meilleure (présence de "commun" / arbre le plus riche).
        $sql = "SELECT *
                  FROM {question_categories}
                 WHERE contextid = :contextid
                   AND name = :name
              ORDER BY CASE WHEN parent = :topid THEN 0 ELSE 1 END,
                       sortorder ASC,
                       id ASC";

        // Encapsuler la phase 1 pour pouvoir en sortir sans return().
        do {
            // Si on a déjà un candidat robuste via \"commun\", on évite de l'écraser.
            if ($systemcandidate) {
                break;
            }

            $records = $DB->get_records_sql($sql, [
                'contextid' => $systemcontext->id,
                'name' => 'Olution',
                'topid' => $system_top_id,
            ], 0, 50);

            if ($records) {
                $best = null;
                $bestscore = 0;
                foreach ($records as $cand) {
                    $score = $scorecategory($cand);
                    if ($best === null || $score > $bestscore) {
                        $best = $cand;
                        $bestscore = $score;
                    }
                }
                if ($best) {
                    local_question_diagnostic_debug_log('✅ Olution category found - EXACT match (best score=' . $bestscore . '): Olution (ID: ' . $best->id . ')', DEBUG_DEVELOPER);
                    $systemcandidate = $best;
                    break;
                }
            }
        
            local_question_diagnostic_debug_log('❌ No exact match for "Olution" found', DEBUG_DEVELOPER);
        
            // ==================================================================================
            // PRIORITÉ 2 : Variantes de casse exactes (mot seul)
            // ==================================================================================
            $variants = ['olution', 'OLUTION'];
        
            foreach ($variants as $variant) {
                $records = $DB->get_records_sql($sql, [
                    'contextid' => $systemcontext->id,
                    'name' => $variant,
                    'topid' => $system_top_id,
                ], 0, 50);

                if (!$records) {
                    continue;
                }

                $best = null;
                $bestscore = 0;
                foreach ($records as $cand) {
                    $score = $scorecategory($cand);
                    if ($best === null || $score > $bestscore) {
                        $best = $cand;
                        $bestscore = $score;
                    }
                }
                if ($best) {
                    local_question_diagnostic_debug_log('✅ Olution question category found - Case variant "' . $variant . '" (best score=' . $bestscore . ', ID: ' . $best->id . ')', DEBUG_DEVELOPER);
                    $systemcandidate = $best;
                    break;
                }
            }
            if ($systemcandidate) {
                break;
            }
        
            // ==================================================================================
            // PRIORITÉ 3 : Nom commençant par "Olution " (avec espace après)
            // Exemples : "Olution 2024", "Olution - Questions"
            // ==================================================================================
            $sql = "SELECT *
                    FROM {question_categories}
                    WHERE contextid = :contextid
                    AND " . $DB->sql_like('name', ':pattern', false, false) . "
                    ORDER BY CASE WHEN parent = :topid THEN 0 ELSE 1 END, LENGTH(name) ASC
                    LIMIT 1";
        
            $olution = $DB->get_record_sql($sql, [
                'contextid' => $systemcontext->id,
                'topid' => $system_top_id,
                'pattern' => 'Olution %'
            ]);
        
            if ($olution) {
                local_question_diagnostic_debug_log('✅ Olution category found - Starts with "Olution ": ' . $olution->name, DEBUG_DEVELOPER);
                $systemcandidate = $olution;
                break;
            }
        
            // ==================================================================================
            // PRIORITÉ 4 : Nom se terminant par " Olution" (avec espace avant)
            // Exemples : "Questions Olution", "Banque Olution"
            // ==================================================================================
            $sql = "SELECT *
                    FROM {question_categories}
                    WHERE contextid = :contextid
                    AND " . $DB->sql_like('name', ':pattern', false, false) . "
                    ORDER BY CASE WHEN parent = :topid THEN 0 ELSE 1 END, LENGTH(name) ASC
                    LIMIT 1";
        
            $olution = $DB->get_record_sql($sql, [
                'contextid' => $systemcontext->id,
                'topid' => $system_top_id,
                'pattern' => '% Olution'
            ]);
        
            if ($olution) {
                local_question_diagnostic_debug_log('✅ Olution category found - Ends with " Olution": ' . $olution->name, DEBUG_DEVELOPER);
                $systemcandidate = $olution;
                break;
            }
        
            // ==================================================================================
            // PRIORITÉ 5 : Nom contenant " Olution " (entouré d'espaces)
            // Exemples : "Banque Olution 2024", "Questions Olution Partagées"
            // ==================================================================================
            $sql = "SELECT *
                    FROM {question_categories}
                    WHERE contextid = :contextid
                    AND " . $DB->sql_like('name', ':pattern', false, false) . "
                    ORDER BY CASE WHEN parent = :topid THEN 0 ELSE 1 END, LENGTH(name) ASC
                    LIMIT 1";
        
            $olution = $DB->get_record_sql($sql, [
                'contextid' => $systemcontext->id,
                'topid' => $system_top_id,
                'pattern' => '% Olution %'
            ]);
        
            if ($olution) {
                local_question_diagnostic_debug_log('✅ Olution category found - Contains " Olution ": ' . $olution->name, DEBUG_DEVELOPER);
                $systemcandidate = $olution;
                break;
            }
        
            // ==================================================================================
            // PRIORITÉ 6 : Nom contenant "Olution" sans espaces (plus flexible)
            // Exemples : "OlutionQCM", "BanqueOlution"
            // ==================================================================================
            $sql = "SELECT *
                    FROM {question_categories}
                    WHERE contextid = :contextid
                    AND " . $DB->sql_like('name', ':pattern', false, false) . "
                    ORDER BY CASE WHEN parent = :topid THEN 0 ELSE 1 END, " . $DB->sql_position("'Olution'", 'name') . " ASC, LENGTH(name) ASC
                    LIMIT 1";
        
            $olution = $DB->get_record_sql($sql, [
                'contextid' => $systemcontext->id,
                'topid' => $system_top_id,
                'pattern' => '%Olution%'
            ]);
        
            if ($olution) {
                local_question_diagnostic_debug_log('⚠️ Olution category found - Contains "Olution" (flexible): ' . $olution->name, DEBUG_DEVELOPER);
                $systemcandidate = $olution;
                break;
            }
        
            // ==================================================================================
            // PRIORITÉ 7 : EN DERNIER RECOURS - Description contenant "olution"
            // SEULEMENT si le nom est court et potentiellement pertinent
            // ==================================================================================
            $sql = "SELECT *
                    FROM {question_categories}
                    WHERE contextid = :contextid
                    AND " . $DB->sql_like('info', ':pattern', false, false) . "
                    AND LENGTH(name) <= 50
                    ORDER BY CASE WHEN parent = :topid THEN 0 ELSE 1 END, " . $DB->sql_position("'olution'", 'info') . " ASC
                    LIMIT 1";
        
            $olution = $DB->get_record_sql($sql, [
                'contextid' => $systemcontext->id,
                'topid' => $system_top_id,
                'pattern' => '%olution%'
            ]);
        
            if ($olution) {
                local_question_diagnostic_debug_log('⚠️ Olution category found - Via description (last resort): ' . $olution->name, DEBUG_DEVELOPER);
                $systemcandidate = $olution;
                break;
            }
        
            local_question_diagnostic_debug_log('❌ No Olution category found in system context after all searches', DEBUG_DEVELOPER);
        } while (false);
        
        // ==================================================================================
        // IMPORTANT : Ne pas créer automatiquement la catégorie Olution.
        // La création automatique peut masquer un problème de configuration (ex: Olution existe sous "Top")
        // et fausser la détection des doublons / déplacements.
        // ==================================================================================
        local_question_diagnostic_debug_log('ℹ️ Not auto-creating Olution category (manual setup required)', DEBUG_DEVELOPER);
        
        // ==================================================================================
        // PHASE 2 : Recherche dans la CATÉGORIE DE COURS "Olution" (si besoin / meilleur score)
        // ==================================================================================
        local_question_diagnostic_debug_log('🔄 Trying Phase 2: Search in course category "Olution" for better match', DEBUG_DEVELOPER);
        
        // 1. Rechercher la catégorie de cours "Olution" (ID 78 selon l'utilisateur)
        $course_category_sql = "SELECT id, name
                                  FROM {course_categories}
                                 WHERE " . $DB->sql_like($DB->sql_lower('name'), ':pattern', false, false) . "
                                    OR id = 78
                              ORDER BY CASE WHEN id = 78 THEN 0 ELSE 1 END,
                                       " . $DB->sql_position("'olution'", $DB->sql_lower('name')) . " ASC,
                                       LENGTH(name) ASC
                                 LIMIT 1";

        $olution_course_category = $DB->get_record_sql($course_category_sql, ['pattern' => '%olution%']);
        
        if (!$olution_course_category) {
            local_question_diagnostic_debug_log('❌ No course category "Olution" found', DEBUG_DEVELOPER);
        } else {
        
            local_question_diagnostic_debug_log('✅ Found course category "Olution": ' . $olution_course_category->name . ' (ID: ' . $olution_course_category->id . ')', DEBUG_DEVELOPER);

        // 1.5) NOUVEAU : vérifier d'abord le CONTEXTE "catégorie de cours" (question bank partagée par catégorie).
        // C'est le cas attendu par l'utilisateur : Olution est lié à une catégorie de cours (pas à un cours).
        try {
            $coursecatcontext = $DB->get_record('context', [
                'contextlevel' => CONTEXT_COURSECAT,
                'instanceid' => (int)$olution_course_category->id,
            ], 'id,contextlevel,instanceid', IGNORE_MISSING);

            if ($coursecatcontext) {
                // Priorité A : ancrage via commun ID 9472 si ce "commun" est dans ce contexte.
                $commun = $DB->get_record('question_categories', ['id' => (int)$forcedcommunid], 'id,name,parent,contextid', IGNORE_MISSING);
                if ($commun && (int)$commun->contextid === (int)$coursecatcontext->id
                    && (int)$commun->parent > 0
                    && $normalize((string)$commun->name) === 'commun') {
                    $parent = $DB->get_record('question_categories', ['id' => (int)$commun->parent], '*', IGNORE_MISSING);
                    if ($parent && (int)$parent->contextid === (int)$coursecatcontext->id
                        && $has_direct_commun_child((int)$parent->id, (int)$coursecatcontext->id)) {
                        $parent->course_category_name = $olution_course_category->name;
                        $parent->course_category_id = (int)$olution_course_category->id;
                        $parent->context_type = 'course_category';
                        local_question_diagnostic_debug_log('✅ Olution detected in course CATEGORY context via commun ID ' . (int)$forcedcommunid . ' (root ID: ' . (int)$parent->id . ')', DEBUG_DEVELOPER);
                        $coursecandidate = $parent;
                        $bestcoursescore = max($bestcoursescore, $scorecategory($parent) + 20000);
                    }
                }

                // Priorité B : chercher des racines "olution" dans ce contexte (case-insensitive) qui ont "commun" enfant direct.
                $sqlqcats = "SELECT *
                               FROM {question_categories}
                              WHERE contextid = :ctxid
                                AND " . $DB->sql_like($DB->sql_lower('name'), ':pattern', false, false) . "
                           ORDER BY LENGTH(name) ASC, id ASC";
                $candidates = $DB->get_records_sql($sqlqcats, [
                    'ctxid' => (int)$coursecatcontext->id,
                    'pattern' => '%olution%',
                ], 0, 50);

                foreach ($candidates as $cand) {
                    if (!$has_direct_commun_child((int)$cand->id, (int)$coursecatcontext->id)) {
                        continue;
                    }
                    $cand->course_category_name = $olution_course_category->name;
                    $cand->course_category_id = (int)$olution_course_category->id;
                    $cand->context_type = 'course_category';
                    $score = $scorecategory($cand) + 15000;
                    if ($score > $bestcoursescore) {
                        $bestcoursescore = $score;
                        $coursecandidate = $cand;
                    }
                }
            }
        } catch (\Exception $e) {
            // Continuer : fallback cours ci-dessous.
        }
        
        // 2. Rechercher tous les cours dans cette catégorie (et ses sous-catégories).
        // 🔧 v1.11.8 : Utiliser la recherche récursive (la catégorie "Olution" peut contenir des sous-catégories).
        $courses = local_question_diagnostic_get_courses_in_category_recursive($olution_course_category->id);
        
        local_question_diagnostic_debug_log('🔍 Found ' . count($courses) . ' courses in Olution course category (recursive) (ID: ' . $olution_course_category->id . ')', DEBUG_DEVELOPER);
        
            $fallbackfirst = false;
            foreach ($courses as $course) {
                local_question_diagnostic_debug_log('🎯 Checking course: ' . $course->fullname . ' (ID: ' . $course->id . ')', DEBUG_DEVELOPER);
            
                // 3. Récupérer le contexte de ce cours
                $course_context = $DB->get_record('context', [
                    'contextlevel' => CONTEXT_COURSE,
                    'instanceid' => $course->id
                ]);
            
                if (!$course_context) {
                    continue;
                }

                // 4. Détection robuste dans le contexte de cours :
                // - Chercher une catégorie nommée exactement "commun" (case/accents-insensitive) et prendre son parent
                //   comme candidat "Olution" (signal fort, comme en Phase 1).
                // - Chercher toute catégorie dont le nom contient "olution" (sans supposer parent=0).
                // - Fallback: première catégorie racine du cours (souvent "Top").

                // 4.a) Heuristique "commun" → parent.
                try {
                    $communpattern = '%commun%';
                    $sqlcommun = "SELECT id, name, parent, contextid
                                    FROM {question_categories}
                                   WHERE contextid = :ctxid
                                     AND " . $DB->sql_like('name', ':pattern', false, false);
                    $communrecs = $DB->get_records_sql($sqlcommun, [
                        'ctxid' => (int)$course_context->id,
                        'pattern' => $communpattern,
                    ]);

                    foreach ($communrecs as $rec) {
                        if (empty($rec->parent) || (int)$rec->parent <= 0) {
                            continue;
                        }
                        if ($normalize((string)$rec->name) !== 'commun') {
                            continue;
                        }

                        $parent = $DB->get_record('question_categories', ['id' => (int)$rec->parent], '*', IGNORE_MISSING);
                        if (!$parent || (int)$parent->contextid !== (int)$course_context->id) {
                            continue;
                        }

                        // IMPORTANT: En contexte cours, "commun" peut exister sous d'autres catégories.
                        // Pour éviter de sélectionner une mauvaise racine (ex: "Top"), on exige que le parent
                        // ait un nom contenant "Olution" (normalisé).
                        $parentnorm = $normalize((string)$parent->name);
                        if (strpos($parentnorm, 'olution') === false) {
                            continue;
                        }
                        // Vérification stricte : "commun" doit être une sous-catégorie DIRECTE d'Olution.
                        if (!$has_direct_commun_child((int)$parent->id, (int)$course_context->id)) {
                            continue;
                        }

                        // Enrichir + scorer.
                        $parent->course_name = $course->fullname;
                        $parent->course_id = (int)$course->id;
                        $parent->course_category_name = $olution_course_category->name;
                        $parent->course_category_id = (int)$olution_course_category->id;
                        $parent->context_type = 'course_category';

                        $score = $scorecategory($parent) + 1000; // bonus massif pour "parent de commun"
                        if ($score > $bestcoursescore) {
                            $bestcoursescore = $score;
                            $coursecandidate = $parent;
                        }
                    }
                } catch (\Exception $e) {
                    // Continuer avec les heuristiques suivantes.
                }

                // 4.b) Recherche de catégories dont le nom contient "olution" (dans TOUT le contexte du cours).
                try {
                    $sql = "SELECT *
                              FROM {question_categories}
                             WHERE contextid = :contextid
                               AND " . $DB->sql_like('name', ':pattern', false, false) . "
                          ORDER BY LENGTH(name) ASC, id ASC";
                    $coursecats = $DB->get_records_sql($sql, [
                        'contextid' => (int)$course_context->id,
                        'pattern' => '%Olution%',
                    ], 0, 50);

                    local_question_diagnostic_debug_log('📂 Found ' . count($coursecats) . ' question categories matching "%Olution%" in course context', DEBUG_DEVELOPER);

                    foreach ($coursecats as $cat) {
                        // Vérification stricte : la racine Olution doit avoir "commun" comme enfant direct.
                        if (!$has_direct_commun_child((int)$cat->id, (int)$course_context->id)) {
                            continue;
                        }

                        // Ajouter des informations sur le cours et la catégorie de cours parent.
                        $cat->course_name = $course->fullname;
                        $cat->course_id = (int)$course->id;
                        $cat->course_category_name = $olution_course_category->name;
                        $cat->course_category_id = (int)$olution_course_category->id;
                        $cat->context_type = 'course_category';

                        $score = $scorecategory($cat);
                        if ($score > $bestcoursescore) {
                            $bestcoursescore = $score;
                            $coursecandidate = $cat;
                        }
                    }
                } catch (\Exception $e) {
                    // Continuer.
                }

                // 4.c) Fallback: première catégorie racine du cours (souvent "Top"), si aucune n'a été retenue.
                if (!$fallbackfirst) {
                    try {
                        $roots = $DB->get_records('question_categories', [
                            'contextid' => (int)$course_context->id,
                            'parent' => 0,
                        ], 'sortorder ASC, id ASC', '*', 0, 1);
                        $firstroot = $roots ? reset($roots) : false;
                        if ($firstroot) {
                            // Ne pas prendre un fallback générique ("Top"/"Default for ...") : on valide strictement.
                            $rootnorm = $normalize((string)$firstroot->name);
                            if (strpos($rootnorm, 'olution') === false) {
                                continue;
                            }
                            if (!$has_direct_commun_child((int)$firstroot->id, (int)$course_context->id)) {
                                continue;
                            }

                            local_question_diagnostic_debug_log('✅ Using first root question category from course in Olution (fallback): ' . $firstroot->name . ' (Course: ' . $course->fullname . ')', DEBUG_DEVELOPER);

                            $firstroot->course_name = $course->fullname;
                            $firstroot->course_id = (int)$course->id;
                            $firstroot->course_category_name = $olution_course_category->name;
                            $firstroot->course_category_id = (int)$olution_course_category->id;
                            $firstroot->context_type = 'course_category';

                            $fallbackfirst = $firstroot;
                        }
                    } catch (\Exception $e) {
                        // Ignorer.
                    }
                }
            }

            // Si aucune catégorie "Olution" n'a été trouvée mais un fallback existe, on le score.
            if (!$coursecandidate && $fallbackfirst) {
                $coursecandidate = $fallbackfirst;
                $bestcoursescore = $scorecategory($fallbackfirst);
            }
        }

        // ==================================================================================
        // Choix final : meilleur score entre système et cours.
        // ==================================================================================
        $systemscore = $systemcandidate ? $scorecategory($systemcandidate) : 0;
        $coursescore = $coursecandidate ? $scorecategory($coursecandidate) : 0;

        // Priorité absolue à un candidat qui respecte la structure attendue (enfant direct "commun").
        $systemhascommun = false;
        $coursehascommun = false;
        if ($systemcandidate && !empty($systemcandidate->contextid)) {
            $systemhascommun = $has_direct_commun_child((int)$systemcandidate->id, (int)$systemcandidate->contextid);
        }
        if ($coursecandidate && !empty($coursecandidate->contextid)) {
            $coursehascommun = $has_direct_commun_child((int)$coursecandidate->id, (int)$coursecandidate->contextid);
        }

        if ($coursecandidate && $coursehascommun && !$systemhascommun) {
            local_question_diagnostic_debug_log('✅ Selected Olution candidate from course category (valid commun child; system candidate invalid)', DEBUG_DEVELOPER);
            return $coursecandidate;
        }

        if ($coursecandidate && $coursescore > $systemscore) {
            local_question_diagnostic_debug_log('✅ Selected Olution candidate from course category (score ' . $coursescore . ' > ' . $systemscore . ')', DEBUG_DEVELOPER);
            return $coursecandidate;
        }

        if ($systemcandidate) {
            local_question_diagnostic_debug_log('✅ Selected Olution candidate from system (score ' . $systemscore . ')', DEBUG_DEVELOPER);
            return $systemcandidate;
        }

        local_question_diagnostic_debug_log('❌ No Olution category found in any context', DEBUG_DEVELOPER);
        return false;
        
    } catch (Exception $e) {
        local_question_diagnostic_debug_log('Error finding Olution category: ' . $e->getMessage(), DEBUG_DEVELOPER);
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
        local_question_diagnostic_debug_log('Error getting Olution subcategories: ' . $e->getMessage(), DEBUG_DEVELOPER);
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
        local_question_diagnostic_debug_log('Error getting course categories: ' . $e->getMessage(), DEBUG_DEVELOPER);
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
        // Standard Moodle param name used across the plugin.
        'returnurl' => qualified_me()
    ]);
    
    return html_writer::link(
        $purge_url,
        '🗑️ ' . get_string('purge_caches', 'local_question_diagnostic'),
        [
            'class' => 'btn btn-warning btn-sm',
            'title' => get_string('purge_caches_tooltip', 'local_question_diagnostic'),
            'style' => 'margin-left: 10px;'
        ]
    );
}

/**
 * Récupère les catégories de questions avec leur hiérarchie pour une catégorie de cours
 * 
 * 🔧 v1.11.11 : Vue hiérarchique des catégories de questions
 * Affiche les catégories organisées en arbre comme dans la banque de questions Moodle.
 * 
 * @param int $course_category_id ID de la catégorie de cours
 * @return array Structure hiérarchique des catégories
 */
function local_question_diagnostic_get_question_categories_hierarchy($course_category_id) {
    global $DB;
    
    try {
        // Utiliser la fonction existante qui fonctionne déjà
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
        local_question_diagnostic_debug_log('Error getting question categories hierarchy: ' . $e->getMessage(), DEBUG_DEVELOPER);
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
 * Crée automatiquement la catégorie Olution au niveau système
 * 
 * 🔧 v1.11.14 : NOUVELLE FONCTION - Création automatique de la catégorie Olution
 * Cette fonction crée automatiquement une catégorie système "Olution" si elle n'existe pas.
 * 
 * @return object|false Objet catégorie créée ou false en cas d'échec
 */
function local_question_diagnostic_create_olution_category() {
    global $DB;
    
    try {
        local_question_diagnostic_debug_log('🆕 Creating Olution category in system context', DEBUG_DEVELOPER);
        
        // Récupérer le contexte système
        $systemcontext = context_system::instance();
        
        // Déterminer la catégorie racine ("Top") pour créer Olution au bon endroit.
        $roots = $DB->get_records('question_categories', [
            'contextid' => $systemcontext->id,
            'parent' => 0,
        ], 'sortorder ASC, id ASC', '*', 0, 1);
        $system_root_category = $roots ? reset($roots) : false;
        $system_top_id = $system_root_category ? (int)$system_root_category->id : 0;
        
        // Vérifier qu'une catégorie Olution n'existe pas déjà
        $sql = "SELECT *
                  FROM {question_categories}
                 WHERE contextid = :contextid
                   AND name = :name
              ORDER BY CASE WHEN parent = :topid THEN 0 ELSE 1 END,
                       sortorder ASC,
                       id ASC";
        $records = $DB->get_records_sql($sql, [
            'contextid' => $systemcontext->id,
            'name' => 'Olution',
            'topid' => $system_top_id,
        ], 0, 1);
        $existing = $records ? reset($records) : false;
        
        if ($existing) {
            local_question_diagnostic_debug_log('⚠️ Olution category already exists (ID: ' . $existing->id . ')', DEBUG_DEVELOPER);
            return $existing;
        }
        
        // Créer la nouvelle catégorie
        $new_category = new stdClass();
        $new_category->name = 'Olution';
        $new_category->info = 'Catégorie système pour les questions partagées Olution. Créée automatiquement par le plugin Question Diagnostic.';
        $new_category->infoformat = FORMAT_HTML;
        $new_category->contextid = $systemcontext->id;
        // Créer sous "Top" si possible (comportement Moodle standard), sinon fallback racine.
        $new_category->parent = $system_top_id > 0 ? $system_top_id : 0;
        $new_category->sortorder = 999; // À la fin
        
        // Insérer dans la base de données
        $new_category->id = $DB->insert_record('question_categories', $new_category);
        
        if ($new_category->id) {
            local_question_diagnostic_debug_log('✅ Olution category created successfully (ID: ' . $new_category->id . ')', DEBUG_DEVELOPER);
            
            // Log d'audit
            require_once(__DIR__ . '/classes/audit_logger.php');
            if (class_exists('local_question_diagnostic\\audit_logger')) {
                audit_logger::log_action(
                    'olution_category_created',
                    [
                        'category_id' => $new_category->id,
                        'category_name' => $new_category->name,
                        'context_id' => $systemcontext->id,
                        'message' => 'Catégorie Olution créée automatiquement'
                    ],
                    $new_category->id
                );
            }
            
            return $new_category;
        } else {
            local_question_diagnostic_debug_log('❌ Failed to insert Olution category', DEBUG_DEVELOPER);
            return false;
        }
        
    } catch (Exception $e) {
        local_question_diagnostic_debug_log('❌ Error creating Olution category: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return false;
    }
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
        
        local_question_diagnostic_debug_log('Recursive search found ' . count($all_courses) . ' courses in category ID: ' . $course_category_id);
        
        return $all_courses;
        
    } catch (Exception $e) {
        local_question_diagnostic_debug_log('Error getting courses recursively: ' . $e->getMessage());
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
            local_question_diagnostic_debug_log('No courses found in course category ID: ' . $course_category_id . ' (including subcategories)');
            return [];
        }
        
        local_question_diagnostic_debug_log('Found ' . count($courses) . ' courses in course category ID: ' . $course_category_id . ' (including subcategories)');
        
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
            local_question_diagnostic_debug_log('No course contexts found for courses in category ID: ' . $course_category_id);
            return [];
        }
        
        local_question_diagnostic_debug_log('Found ' . count($contexts) . ' course contexts');
        
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
        
        local_question_diagnostic_debug_log('Found ' . count($module_contexts) . ' module contexts');
        
        // 4. Récupérer le contexte système (si accessible)
        $system_context = context_system::instance();
        
        // 5. Construire la liste de tous les contextes à inclure
        $all_context_ids = array_merge($context_ids, array_keys($module_contexts));
        $all_context_ids[] = $system_context->id; // Ajouter le contexte système
        
        $all_context_ids = array_unique($all_context_ids);
        list($all_context_ids_sql, $all_context_params) = $DB->get_in_or_equal($all_context_ids, SQL_PARAMS_NAMED);
        
        local_question_diagnostic_debug_log('Total contexts to search: ' . count($all_context_ids));
        
        // 6. Récupérer les catégories de questions avec informations de base (SANS CONCAT)
        $question_categories_sql = "SELECT qc.*, 
                                          ctx.contextlevel,
                                          ctx.instanceid
                                   FROM {question_categories} qc
                                   INNER JOIN {context} ctx ON ctx.id = qc.contextid
                                   WHERE qc.contextid " . $all_context_ids_sql . "
                                   ORDER BY ctx.contextlevel ASC, qc.name ASC";
        
        $question_categories = $DB->get_records_sql($question_categories_sql, $all_context_params);
        
        local_question_diagnostic_debug_log('Found ' . count($question_categories) . ' question categories');
        
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
            // ⚠️ Cohérence avec categories.php / category_manager:
            // - `categories.php` attend la propriété `subcategories` (et non `subcategory_count`)
            // - Certaines vues JS utilisent data-subcategories pour tri/filtre
            $cat->subcategories = (int)$subcat_count;
            // Conserver l'ancien nom pour compatibilité avec d'anciens scripts/tests.
            $cat->subcategory_count = (int)$subcat_count;
            
            // Déterminer le statut
            if ($cat->total_questions == 0 && $cat->subcategories == 0) {
                $cat->status = 'empty';
            } else {
                $cat->status = 'ok';
            }

            // Normaliser les flags attendus par l'UI (categories.php)
            $cat->is_empty = ($cat->status === 'empty');
            $cat->is_orphan = false; // on ne charge que des contextes existants (INNER JOIN context)
            $cat->is_duplicate = false; // non calculé dans cette vue filtrée
            
            // Vérifier si c'est une catégorie protégée
            $cat->is_protected = (
                stripos($cat->name, 'default for') === 0 ||
                $cat->parent == 0 ||
                !empty($cat->info)
            );
        }
        
        local_question_diagnostic_debug_log('Successfully processed ' . count($question_categories) . ' question categories');
        return $question_categories;
        
    } catch (Exception $e) {
        local_question_diagnostic_debug_log('Error getting question categories by course category: ' . $e->getMessage());
        
        // Fallback : essayer une requête plus simple (seulement contextes de cours)
        try {
            local_question_diagnostic_debug_log('Attempting fallback with course contexts only');
            
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
                $cat->subcategories = 0;
                $cat->subcategory_count = 0;
                $cat->status = 'ok';
                $cat->is_protected = false;
                $cat->is_empty = false;
                $cat->is_orphan = false;
                $cat->is_duplicate = false;
            }
            
            local_question_diagnostic_debug_log('Fallback successful: found ' . count($fallback_categories) . ' categories');
            return $fallback_categories;
            
        } catch (Exception $fallback_error) {
            local_question_diagnostic_debug_log('Fallback also failed: ' . $fallback_error->getMessage());
            return [];
        }
    }
}


