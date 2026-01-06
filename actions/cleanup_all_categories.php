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
 * Action de nettoyage global de TOUTES les catégories supprimables du site
 * 
 * Ce fichier gère le nettoyage automatique de toutes les catégories qui respectent
 * les règles de suppression (vides, non protégées, orphelines OK) en une seule opération,
 * avec traitement par lots pour éviter les timeouts.
 * 
 * Modes de fonctionnement :
 * - preview : Affiche les statistiques et la page de confirmation
 * - download_csv : Génère le CSV de la liste des catégories à supprimer
 * - execute : Exécute le nettoyage par lots (avec batch number)
 * - complete : Affiche le résumé final
 *
 * @package    local_question_diagnostic
 * @copyright  2025 Question Diagnostic Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/../classes/category_manager.php');
require_once(__DIR__ . '/../classes/audit_logger.php');

use local_question_diagnostic\category_manager;
use local_question_diagnostic\audit_logger;

require_login();

if (!is_siteadmin()) {
    throw new \moodle_exception('accessdenied', 'admin');
}

require_sesskey();

// Définir le contexte de la page
$context = context_system::instance();
$PAGE->set_context($context);

// Détecter le mode
$preview = optional_param('preview', 0, PARAM_INT);
$download_csv = optional_param('download_csv', 0, PARAM_INT);
$execute = optional_param('execute', 0, PARAM_INT);
$batch = optional_param('batch', 0, PARAM_INT);
// 🆕 Option avancée : ignorer la protection "catégorie avec description".
// Sécurisé car admin-only + sesskey + confirmation via preview.
$bypass_info = optional_param('bypass_info', 0, PARAM_INT);

// Taille du lot (nombre de catégories à traiter par batch)
define('BATCH_SIZE', 20);

if ($download_csv) {
    // MODE TÉLÉCHARGEMENT CSV
    handle_csv_download((int)$bypass_info);
    exit;
} else if ($execute) {
    // MODE EXÉCUTION PAR LOTS
    execute_cleanup_batch($batch, (int)$bypass_info);
} else if ($preview) {
    // MODE PRÉVISUALISATION
    show_preview_page((int)$bypass_info);
} else {
    // Rediriger vers preview par défaut
    redirect(new moodle_url('/local/question_diagnostic/actions/cleanup_all_categories.php', 
             ['preview' => 1, 'sesskey' => sesskey(), 'bypass_info' => (int)$bypass_info]));
}

/**
 * Affiche la page de prévisualisation avec statistiques et confirmation
 */
function show_preview_page(int $bypass_info = 0) {
    global $OUTPUT, $PAGE;
    
    $PAGE->set_url(new moodle_url('/local/question_diagnostic/actions/cleanup_all_categories.php', 
                   ['preview' => 1, 'sesskey' => sesskey(), 'bypass_info' => (int)$bypass_info]));
    $PAGE->set_title(get_string('cleanup_all_categories_preview_title', 'local_question_diagnostic'));
    $PAGE->set_heading(local_question_diagnostic_get_heading_with_version(
        get_string('cleanup_all_categories_preview_title', 'local_question_diagnostic')
    ));
    $PAGE->set_pagelayout('admin');
    
    // Charger les statistiques de prévisualisation
    echo $OUTPUT->header();
    
    echo html_writer::start_tag('div', ['id' => 'loading-preview', 'style' => 'text-align: center; padding: 40px;']);
    echo html_writer::tag('h2', '⏳ Analyse en cours...');
    echo html_writer::tag('p', 'Calcul des statistiques pour toutes les catégories supprimables...', 
                          ['style' => 'font-size: 16px;']);
    echo html_writer::end_tag('div');
    
    try {
        $stats = get_cleanup_stats((int)$bypass_info);
    } catch (Exception $e) {
        echo html_writer::start_tag('script');
        echo "document.getElementById('loading-preview').style.display = 'none';";
        echo html_writer::end_tag('script');
        
        echo html_writer::start_tag('div', ['class' => 'alert alert-danger']);
        echo html_writer::tag('strong', '⚠️ Erreur : ');
        echo 'Impossible de calculer les statistiques de nettoyage. ';
        echo html_writer::tag('p', 'Détails : ' . $e->getMessage(), ['style' => 'margin-top: 10px; font-size: 12px;']);
        echo html_writer::end_tag('div');
        echo $OUTPUT->footer();
        exit;
    }
    
    // Masquer le spinner
    echo html_writer::start_tag('script');
    echo "document.getElementById('loading-preview').style.display = 'none';";
    echo html_writer::end_tag('script');
    
    // Afficher les statistiques
    echo html_writer::start_tag('div', ['style' => 'margin-bottom: 20px;']);
    echo local_question_diagnostic_render_back_link('cleanup_all_categories.php');
    echo html_writer::end_tag('div');
    
    echo html_writer::tag('h2', '📊 ' . get_string('cleanup_all_categories_preview_title', 'local_question_diagnostic'));
    
    echo html_writer::start_tag('div', ['class' => 'alert alert-info', 'style' => 'margin-bottom: 20px;']);
    echo get_string('cleanup_all_categories_preview_desc', 'local_question_diagnostic');
    echo html_writer::end_tag('div');
    
    // Option avancée : bypass protection description
    echo html_writer::start_tag('div', ['class' => 'alert alert-warning', 'style' => 'margin: 20px 0;']);
    echo html_writer::tag('strong', 'Option avancée : ');
    echo 'Inclure les catégories ayant une description (champ "info").';
    echo html_writer::start_tag('div', ['style' => 'margin-top: 10px;']);
    echo html_writer::start_tag('form', [
        'method' => 'get',
        'action' => new moodle_url('/local/question_diagnostic/actions/cleanup_all_categories.php'),
        'style' => 'margin: 0;'
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'preview', 'value' => '1']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'bypass_info', 'value' => $bypass_info ? '0' : '1']);
    $label = $bypass_info ? 'Désactiver (recommandé)' : 'Activer (à vos risques)';
    echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => $label, 'class' => 'btn btn-warning']);
    echo html_writer::end_tag('form');
    echo html_writer::end_tag('div');
    echo html_writer::end_tag('div');

    // Si aucune catégorie à supprimer
    if ($stats->total_to_delete == 0) {
        echo html_writer::start_tag('div', ['class' => 'alert alert-success', 'style' => 'padding: 30px; text-align: center;']);
        echo html_writer::tag('h3', '✅ ' . get_string('cleanup_all_nothing_to_delete', 'local_question_diagnostic'));
        echo html_writer::tag('p', get_string('cleanup_all_categories_nothing_desc', 'local_question_diagnostic'));
        echo html_writer::end_tag('div');
        
        $back_url = new moodle_url('/local/question_diagnostic/categories.php');
        echo html_writer::start_tag('div', ['style' => 'text-align: center; margin-top: 20px;']);
        echo html_writer::link($back_url, '← ' . get_string('back', 'core'), ['class' => 'btn btn-secondary']);
        echo html_writer::end_tag('div');
        
        echo $OUTPUT->footer();
        exit;
    }
    
    // Dashboard des statistiques
    echo html_writer::start_tag('div', ['class' => 'qd-dashboard', 'style' => 'margin-bottom: 30px;']);
    
    // Carte 1 : Total catégories
    echo html_writer::start_tag('div', ['class' => 'qd-card']);
    echo html_writer::tag('div', get_string('total_categories', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
    echo html_writer::tag('div', $stats->total_categories, ['class' => 'qd-card-value']);
    echo html_writer::tag('div', get_string('in_database', 'local_question_diagnostic'), ['class' => 'qd-card-subtitle']);
    echo html_writer::end_tag('div');
    
    // Carte 2 : Catégories à supprimer
    echo html_writer::start_tag('div', ['class' => 'qd-card danger']);
    echo html_writer::tag('div', get_string('cleanup_all_stats_to_delete', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
    echo html_writer::tag('div', $stats->total_to_delete, ['class' => 'qd-card-value']);
    echo html_writer::tag('div', round(($stats->total_to_delete / $stats->total_categories) * 100, 1) . '% du total', ['class' => 'qd-card-subtitle']);
    echo html_writer::end_tag('div');
    
    // Carte 3 : Catégories à conserver
    echo html_writer::start_tag('div', ['class' => 'qd-card success']);
    echo html_writer::tag('div', get_string('cleanup_all_stats_to_keep', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
    echo html_writer::tag('div', $stats->total_to_keep, ['class' => 'qd-card-value']);
    echo html_writer::tag('div', round(($stats->total_to_keep / $stats->total_categories) * 100, 1) . '% du total', ['class' => 'qd-card-subtitle']);
    echo html_writer::end_tag('div');
    
    // Carte 4 : Temps estimé
    $minutes = ceil($stats->estimated_time_seconds / 60);
    echo html_writer::start_tag('div', ['class' => 'qd-card']);
    echo html_writer::tag('div', get_string('cleanup_all_estimated_time', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
    echo html_writer::tag('div', $minutes . ' min', ['class' => 'qd-card-value']);
    echo html_writer::tag('div', $stats->estimated_batches . ' lot(s) de traitement', ['class' => 'qd-card-subtitle']);
    echo html_writer::end_tag('div');
    
    echo html_writer::end_tag('div'); // fin dashboard
    
    // Détails par type de catégorie
    echo html_writer::tag('h3', '📋 Détails des catégories à supprimer');
    
    echo html_writer::start_tag('div', ['class' => 'qd-table-wrapper']);
    echo html_writer::start_tag('table', ['class' => 'qd-table', 'style' => 'width: 100%;']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'Type');
    echo html_writer::tag('th', 'À supprimer');
    echo html_writer::tag('th', 'À conserver');
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');
    
    // Catégories vides
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', '🗑️ Catégories vides (0 questions, 0 sous-catégories)');
    echo html_writer::tag('td', $stats->empty_to_delete, ['style' => 'text-align: center; color: #d9534f; font-weight: bold;']);
    echo html_writer::tag('td', $stats->empty_to_keep, ['style' => 'text-align: center; color: #5cb85c;']);
    echo html_writer::end_tag('tr');
    
    // Catégories orphelines
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', '👻 Catégories orphelines (contexte invalide)');
    echo html_writer::tag('td', $stats->orphan_to_delete, ['style' => 'text-align: center; color: #d9534f; font-weight: bold;']);
    echo html_writer::tag('td', $stats->orphan_to_keep, ['style' => 'text-align: center; color: #5cb85c;']);
    echo html_writer::end_tag('tr');
    
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_tag('div');
    
    // Message d'avertissement
    echo html_writer::start_tag('div', ['class' => 'alert alert-danger', 'style' => 'margin: 30px 0; padding: 20px;']);
    echo html_writer::tag('h4', '⚠️ ' . get_string('action_irreversible', 'local_question_diagnostic'), ['style' => 'margin-top: 0;']);
    $warning_text = get_string('cleanup_all_categories_warning', 'local_question_diagnostic');
    $warning_text = str_replace('{$a}', $stats->total_to_delete, $warning_text);
    echo html_writer::tag('p', $warning_text, ['style' => 'font-size: 16px; margin-bottom: 15px;']);
    
    echo html_writer::tag('p', '🛡️ <strong>Règles de protection appliquées :</strong>');
    echo html_writer::start_tag('ul', ['style' => 'margin-top: 10px;']);
    echo html_writer::tag('li', '✅ Les catégories "Default for..." sont PROTÉGÉES');
    echo html_writer::tag('li', '✅ Les catégories avec description sont PROTÉGÉES');
    echo html_writer::tag('li', '✅ Les catégories racine (parent=0) sont PROTÉGÉES');
    echo html_writer::tag('li', '✅ Les catégories contenant des questions sont PROTÉGÉES');
    echo html_writer::tag('li', '✅ Les catégories contenant des sous-catégories sont PROTÉGÉES');
    echo html_writer::end_tag('ul');
    echo html_writer::end_tag('div');
    
    // Boutons d'action
    echo html_writer::start_tag('div', ['style' => 'text-align: center; margin: 30px 0; display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;']);
    
    // Bouton télécharger CSV
    $csv_url = new moodle_url('/local/question_diagnostic/actions/cleanup_all_categories.php', [
        'download_csv' => 1,
        'sesskey' => sesskey(),
        'bypass_info' => (int)$bypass_info,
    ]);
    echo html_writer::link($csv_url, '📥 ' . get_string('cleanup_all_download_csv', 'local_question_diagnostic'), 
                           ['class' => 'btn btn-info btn-lg']);
    
    // Bouton confirmer et lancer
    $execute_url = new moodle_url('/local/question_diagnostic/actions/cleanup_all_categories.php', [
        'execute' => 1,
        'batch' => 0,
        'sesskey' => sesskey(),
        'bypass_info' => (int)$bypass_info,
    ]);
    echo html_writer::link($execute_url, '🚀 ' . get_string('cleanup_all_confirm_button', 'local_question_diagnostic'), 
                           ['class' => 'btn btn-danger btn-lg', 'style' => 'font-weight: bold;']);
    
    // Bouton annuler
    $cancel_url = new moodle_url('/local/question_diagnostic/categories.php');
    echo html_writer::link($cancel_url, '← ' . get_string('cancel', 'core'), 
                           ['class' => 'btn btn-secondary btn-lg']);
    
    echo html_writer::end_tag('div');
    
    echo $OUTPUT->footer();
}

/**
 * Calcule les statistiques de nettoyage
 * 
 * @return object Statistiques
 */
function get_cleanup_stats(int $bypass_info = 0) {
    global $DB;
    
    $stats = new stdClass();
    
    // Récupérer toutes les catégories avec leurs stats
    $all_categories = category_manager::get_all_categories_with_stats();
    
    $stats->total_categories = count($all_categories);
    $stats->total_to_delete = 0;
    $stats->total_to_keep = 0;
    $stats->empty_to_delete = 0;
    $stats->empty_to_keep = 0;
    $stats->orphan_to_delete = 0;
    $stats->orphan_to_keep = 0;
    $stats->categories_list = [];
    
    foreach ($all_categories as $cat) {
        // category_manager::get_all_categories_with_stats() retourne des objets:
        // (object)['category' => <record question_categories>, 'stats' => <stats>]
        // Conserver un fallback si une autre source fournit déjà un record "plat".
        $category = (isset($cat->category) && is_object($cat->category)) ? $cat->category : $cat;

        $can_delete = can_delete_category($cat, (int)$bypass_info);
        
        if ($can_delete) {
            $stats->total_to_delete++;
            
            // Classifier
            if ($cat->stats->is_empty && !$cat->stats->is_orphan) {
                $stats->empty_to_delete++;
            } else if ($cat->stats->is_orphan) {
                $stats->orphan_to_delete++;
            }
            
            // Ajouter à la liste pour CSV
            $stats->categories_list[] = (object)[
                'id' => $category->id ?? 0,
                'name' => $category->name ?? '',
                'contextid' => $category->contextid ?? 0,
                'context_name' => $cat->stats->context_name ?? 'Inconnu',
                'parent' => $category->parent ?? 0,
                'is_empty' => $cat->stats->is_empty,
                'is_orphan' => $cat->stats->is_orphan,
                'action' => 'delete'
            ];
        } else {
            $stats->total_to_keep++;
            
            if ($cat->stats->is_empty && !$cat->stats->is_orphan) {
                $stats->empty_to_keep++;
            } else if ($cat->stats->is_orphan) {
                $stats->orphan_to_keep++;
            }
        }
    }
    
    // Estimation du temps (environ 0.3s par catégorie)
    $stats->estimated_time_seconds = $stats->total_to_delete * 0.3;
    $stats->estimated_batches = ceil($stats->total_to_delete / BATCH_SIZE);
    
    return $stats;
}

/**
 * Vérifie si une catégorie peut être supprimée
 * 
 * @param object $cat Objet catégorie avec stats
 * @return bool True si supprimable
 */
function can_delete_category($cat, int $bypass_info = 0) {
    // Ne pas supprimer si protégée
    if ($cat->stats->is_protected) {
        // Option avancée : si la SEULE raison est "A une description", autoriser.
        if ($bypass_info && ($cat->stats->protection_reason ?? '') === 'A une description') {
            // Continuer les autres vérifications (questions / sous-catégories).
        } else {
        return false;
        }
    }
    
    // Ne pas supprimer si contient des questions
    if ($cat->stats->total_questions > 0) {
        return false;
    }
    
    // Ne pas supprimer si contient des sous-catégories
    if ($cat->stats->subcategories > 0) {
        return false;
    }
    
    // Supprimable si vide OU orpheline
    return ($cat->stats->is_empty || $cat->stats->is_orphan);
}

/**
 * Exécute le nettoyage par lot
 * 
 * @param int $batch Numéro du lot
 */
function execute_cleanup_batch($batch, int $bypass_info = 0) {
    global $OUTPUT, $PAGE, $USER;
    
    $PAGE->set_url(new moodle_url('/local/question_diagnostic/actions/cleanup_all_categories.php', 
                   ['execute' => 1, 'batch' => $batch, 'sesskey' => sesskey(), 'bypass_info' => (int)$bypass_info]));
    $PAGE->set_title(get_string('cleanup_all_progress_title', 'local_question_diagnostic'));
    $PAGE->set_heading(local_question_diagnostic_get_heading_with_version(
        get_string('cleanup_all_progress_title', 'local_question_diagnostic')
    ));
    $PAGE->set_pagelayout('admin');
    
    echo $OUTPUT->header();
    
    // Récupérer toutes les catégories supprimables
    $all_categories = category_manager::get_all_categories_with_stats();
    $deletable_categories = [];
    
    foreach ($all_categories as $cat) {
        if (can_delete_category($cat, (int)$bypass_info)) {
            $deletable_categories[] = $cat;
        }
    }
    
    $total_to_delete = count($deletable_categories);
    $total_batches = ceil($total_to_delete / BATCH_SIZE);
    
    // Si c'est le premier lot, initialiser la session
    if ($batch == 0) {
        $_SESSION['cleanup_categories_deleted'] = 0;
        $_SESSION['cleanup_categories_errors'] = 0;
    }
    
    // Calculer les indices de début et fin pour ce lot
    $start = $batch * BATCH_SIZE;
    $end = min($start + BATCH_SIZE, $total_to_delete);
    $batch_categories = array_slice($deletable_categories, $start, BATCH_SIZE);
    
    // Afficher la progression
    echo html_writer::tag('h2', '🚀 ' . get_string('cleanup_all_progress_title', 'local_question_diagnostic'));
    
    $progress_obj = new stdClass();
    $progress_obj->current = $batch + 1;
    $progress_obj->total = $total_batches;
    echo html_writer::start_tag('div', ['class' => 'alert alert-info', 'style' => 'font-size: 16px;']);
    echo get_string('cleanup_all_progress_batch', 'local_question_diagnostic', $progress_obj);
    echo html_writer::end_tag('div');
    
    // Barre de progression
    $progress_percent = round((($batch + 1) / $total_batches) * 100, 1);
    echo html_writer::start_tag('div', ['class' => 'progress', 'style' => 'height: 30px; margin: 20px 0;']);
    echo html_writer::start_tag('div', [
        'class' => 'progress-bar progress-bar-striped progress-bar-animated',
        'role' => 'progressbar',
        'style' => 'width: ' . $progress_percent . '%;',
        'aria-valuenow' => $progress_percent,
        'aria-valuemin' => '0',
        'aria-valuemax' => '100'
    ]);
    echo $progress_percent . '%';
    echo html_writer::end_tag('div');
    echo html_writer::end_tag('div');
    
    // Traiter ce lot
    $deleted_in_batch = 0;
    $errors_in_batch = 0;
    
    echo html_writer::start_tag('div', ['style' => 'margin: 20px 0; padding: 15px; background: #f9f9f9; border-radius: 5px;']);
    echo html_writer::tag('h4', '📝 Traitement en cours...', ['style' => 'margin-top: 0;']);
    
    foreach ($batch_categories as $cat) {
        $category = (isset($cat->category) && is_object($cat->category)) ? $cat->category : $cat;
        $catid = $category->id ?? 0;
        $catname = $category->name ?? 'Inconnu';

        try {
            // Supprimer la catégorie
            $result = category_manager::delete_category($catid, false, (bool)$bypass_info);
            
            // Note : L'audit logging est géré automatiquement par category_manager::delete_category()
            // via audit_logger::log_category_deletion()

            if ($result === true) {
                $deleted_in_batch++;
                $_SESSION['cleanup_categories_deleted']++;
                
                echo html_writer::tag('p', '✅ Supprimée : ' . format_string($catname) . ' (ID: ' . $catid . ')', 
                                     ['style' => 'margin: 5px 0; color: #28a745;']);
            } else {
                // delete_category() renvoie une string explicative en cas d'échec (pas d'exception).
                $errors_in_batch++;
                $_SESSION['cleanup_categories_errors']++;
                
                $msg = is_string($result) ? $result : 'Erreur inconnue';
                echo html_writer::tag('p', '❌ Échec : ' . format_string($catname) . ' (ID: ' . $catid . ') - ' . $msg,
                                     ['style' => 'margin: 5px 0; color: #d9534f;']);
            }
        } catch (Exception $e) {
            $errors_in_batch++;
            $_SESSION['cleanup_categories_errors']++;
            
            echo html_writer::tag('p', '❌ Erreur : ' . format_string($catname) . ' (ID: ' . $catid . ') - ' . $e->getMessage(), 
                                 ['style' => 'margin: 5px 0; color: #d9534f;']);
        }
    }
    
    echo html_writer::end_tag('div');
    
    // Statistiques du lot
    $stats_obj = new stdClass();
    $stats_obj->deleted = $_SESSION['cleanup_categories_deleted'];
    $stats_obj->kept = $total_to_delete - $_SESSION['cleanup_categories_deleted'];
    
    echo html_writer::start_tag('div', ['class' => 'alert alert-success', 'style' => 'font-size: 16px;']);
    echo get_string('cleanup_all_progress_stats', 'local_question_diagnostic', $stats_obj);
    echo html_writer::tag('p', 'Erreurs : ' . $_SESSION['cleanup_categories_errors'], ['style' => 'margin-top: 5px;']);
    echo html_writer::end_tag('div');
    
    // Si ce n'est pas le dernier lot, rediriger vers le suivant
    if ($batch + 1 < $total_batches) {
        $next_batch_url = new moodle_url('/local/question_diagnostic/actions/cleanup_all_categories.php', [
            'execute' => 1,
            'batch' => $batch + 1,
            'sesskey' => sesskey(),
            'bypass_info' => (int)$bypass_info,
        ]);
        
        echo html_writer::start_tag('div', ['style' => 'text-align: center; margin: 30px 0;']);
        echo html_writer::tag('p', 'Redirection automatique vers le lot suivant dans 2 secondes...', 
                             ['style' => 'margin-bottom: 15px;']);
        echo html_writer::link($next_batch_url, '➡️ Continuer maintenant', ['class' => 'btn btn-primary btn-lg']);
        echo html_writer::end_tag('div');
        
        // Auto-redirection
        echo html_writer::start_tag('script');
        echo "setTimeout(function() { window.location.href = '" . $next_batch_url->out(false) . "'; }, 2000);";
        echo html_writer::end_tag('script');
    } else {
        // Dernier lot - Afficher le résumé final
        $complete_url = new moodle_url('/local/question_diagnostic/actions/cleanup_all_categories.php', [
            'complete' => 1,
            'deleted' => $_SESSION['cleanup_categories_deleted'],
            'errors' => $_SESSION['cleanup_categories_errors'],
            'sesskey' => sesskey(),
            'bypass_info' => (int)$bypass_info,
        ]);
        
        echo html_writer::start_tag('script');
        echo "setTimeout(function() { window.location.href = '" . $complete_url->out(false) . "'; }, 2000);";
        echo html_writer::end_tag('script');
        
        echo html_writer::start_tag('div', ['style' => 'text-align: center; margin: 30px 0;']);
        echo html_writer::tag('p', 'Nettoyage terminé ! Redirection vers le résumé...', 
                             ['style' => 'font-size: 18px; margin-bottom: 15px;']);
        echo html_writer::end_tag('div');
    }
    
    echo $OUTPUT->footer();
}

/**
 * Gère le téléchargement CSV
 */
function handle_csv_download(int $bypass_info = 0) {
    $stats = get_cleanup_stats((int)$bypass_info);
    
    $filename = 'cleanup_categories_' . date('Y-m-d_His') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    
    $output = fopen('php://output', 'w');
    
    // BOM UTF-8 pour Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // En-têtes
    fputcsv($output, [
        'ID',
        'Nom',
        'Context ID',
        'Contexte',
        'Parent ID',
        'Vide',
        'Orpheline',
        'Action'
    ], ';');
    
    // Données
    foreach ($stats->categories_list as $cat) {
        fputcsv($output, [
            $cat->id,
            $cat->name,
            $cat->contextid,
            $cat->context_name,
            $cat->parent,
            $cat->is_empty ? 'Oui' : 'Non',
            $cat->is_orphan ? 'Oui' : 'Non',
            'Supprimer'
        ], ';');
    }
    
    fclose($output);
}

// Gestion du mode "complete"
$complete = optional_param('complete', 0, PARAM_INT);
if ($complete) {
    $deleted = optional_param('deleted', 0, PARAM_INT);
    $errors = optional_param('errors', 0, PARAM_INT);
    
    $PAGE->set_url(new moodle_url('/local/question_diagnostic/actions/cleanup_all_categories.php', 
                   ['complete' => 1, 'deleted' => $deleted, 'errors' => $errors, 'sesskey' => sesskey(), 'bypass_info' => (int)$bypass_info]));
    $PAGE->set_title(get_string('cleanup_all_complete_title', 'local_question_diagnostic'));
    $PAGE->set_heading(local_question_diagnostic_get_heading_with_version(
        get_string('cleanup_all_complete_title', 'local_question_diagnostic')
    ));
    $PAGE->set_pagelayout('admin');
    
    echo $OUTPUT->header();
    
    echo html_writer::tag('h2', '✅ ' . get_string('cleanup_all_complete_title', 'local_question_diagnostic'));
    
    echo html_writer::start_tag('div', ['class' => 'alert alert-success', 'style' => 'padding: 30px; text-align: center;']);
    echo html_writer::tag('h3', '🎉 Nettoyage terminé avec succès !', ['style' => 'margin-top: 0;']);
    
    $summary_obj = new stdClass();
    $summary_obj->deleted = $deleted;
    $summary_obj->kept = 0;
    echo html_writer::tag('p', get_string('cleanup_all_complete_summary', 'local_question_diagnostic', $summary_obj), 
                         ['style' => 'font-size: 18px; margin: 20px 0;']);
    
    if ($errors > 0) {
        echo html_writer::tag('p', '⚠️ Erreurs rencontrées : ' . $errors, 
                             ['style' => 'color: #f0ad4e; font-weight: bold;']);
    }
    echo html_writer::end_tag('div');
    
    // Bouton retour
    $back_url = new moodle_url('/local/question_diagnostic/categories.php');
    echo html_writer::start_tag('div', ['style' => 'text-align: center; margin: 30px 0;']);
    echo html_writer::link($back_url, '← ' . get_string('backtocategories', 'local_question_diagnostic'), 
                           ['class' => 'btn btn-primary btn-lg']);
    echo html_writer::end_tag('div');
    
    // Nettoyer la session
    unset($_SESSION['cleanup_categories_deleted']);
    unset($_SESSION['cleanup_categories_errors']);
    
    echo $OUTPUT->footer();
}

