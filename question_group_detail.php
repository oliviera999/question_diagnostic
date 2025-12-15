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
 * Page de détail d'un groupe de questions en doublon
 *
 * @package    local_question_diagnostic
 * @copyright  2025 Question Diagnostic Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/question_analyzer.php');

use local_question_diagnostic\question_analyzer;

// Charger les bibliothèques Moodle nécessaires.
require_login();

// Vérification stricte : seuls les administrateurs du site peuvent accéder à cette page.
if (!is_siteadmin()) {
    print_error('accessdenied', 'admin', '', 'Vous devez être administrateur du site pour accéder à cet outil.');
    exit;
}

// Récupérer les paramètres
$representative_id = optional_param('id', 0, PARAM_INT);
$question_name = optional_param('name', '', PARAM_TEXT);
$qtype = optional_param('qtype', '', PARAM_TEXT);

// Validation : privilégier l'ID représentatif (stable).
// Compat : si on ne reçoit que (name + qtype), on prend la plus ancienne comme représentative.
if (!$representative_id && ($question_name && $qtype)) {
    $representative_id = (int)$DB->get_field('question', 'MIN(id)', ['name' => $question_name, 'qtype' => $qtype]);
}

if (!$representative_id) {
    print_error('Paramètres manquants : ID requis');
    exit;
}

$representative = $DB->get_record('question', ['id' => $representative_id], '*', MUST_EXIST);
$question_name = $representative->name;
$qtype = $representative->qtype;

// Définir le contexte de la page (système).
$context = context_system::instance();

// Définir le titre et l'URL de la page.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/question_diagnostic/question_group_detail.php', [
    'id' => $representative_id
]));
$pagetitle = get_string('question_group_detail_title', 'local_question_diagnostic');
$PAGE->set_title($pagetitle);
$PAGE->set_heading(local_question_diagnostic_get_heading_with_version($pagetitle));
$PAGE->set_pagelayout('report');

// Ajouter les CSS et JavaScript personnalisés
$PAGE->requires->css('/local/question_diagnostic/styles/main.css');
$PAGE->requires->js('/local/question_diagnostic/scripts/main.js', true);

// ======================================================================
// Section d'en-tête Moodle standard.
echo $OUTPUT->header();

// Afficher le badge de version
echo local_question_diagnostic_render_version_badge();

// 🆕 v1.9.45 : Lien retour hiérarchique
echo html_writer::start_tag('div', ['style' => 'margin-bottom: 20px;']);
echo html_writer::link(
    new moodle_url('/local/question_diagnostic/questions_cleanup.php', ['loadstats' => 1]),
    '← ' . get_string('back_to_groups_list', 'local_question_diagnostic'),
    ['class' => 'btn btn-secondary']
);
echo html_writer::end_tag('div');

// Titre du groupe
echo html_writer::tag('h2', '🔀 ' . get_string('question_group_detail_title', 'local_question_diagnostic'));

// Récupérer toutes les questions du groupe selon la définition standard du plugin
// (doublons certains = même type + même texte).
$group_ids = question_analyzer::get_duplicate_group_question_ids_by_representative_id((int)$representative_id);
$all_questions = [];
if (!empty($group_ids)) {
    $all_questions = $DB->get_records_list('question', 'id', $group_ids, 'id ASC');
}

if (empty($all_questions)) {
    echo html_writer::start_tag('div', ['class' => 'alert alert-warning']);
    echo 'Aucune question trouvée pour ce groupe de doublons.';
    echo html_writer::end_tag('div');
    echo $OUTPUT->footer();
    exit;
}

// 🔧 Calculer le VRAI nombre de versions utilisées AVANT l'affichage
$group_question_ids = array_map(function($q) { return $q->id; }, $all_questions);
$group_usage_map = question_analyzer::get_questions_usage_by_ids($group_question_ids);

$used_count_preview = 0;
foreach ($all_questions as $q) {
    $quiz_count = 0;
    if (isset($group_usage_map[$q->id]) && is_array($group_usage_map[$q->id])) {
        $quiz_count = isset($group_usage_map[$q->id]['quiz_count']) ? $group_usage_map[$q->id]['quiz_count'] : 0;
    }
    if ($quiz_count > 0) {
        $used_count_preview++;
    }
}

$unused_count_preview = count($all_questions) - $used_count_preview;

// 🔍 Analyse de supprimabilité pour diagnostic
// 🆕 v1.9.6 : Vérifier la supprimabilité de toutes les questions du groupe en batch
$deletability_map = question_analyzer::can_delete_questions_batch($group_question_ids);

// 🆕 v1.9.55 : Récupérer les infos de versions (statut caché + nombre de versions)
$version_info_map = question_analyzer::get_questions_version_info_batch($group_question_ids);

$deletable_count = 0;
$protected_reasons = [];

foreach ($all_questions as $q) {
    if (isset($deletability_map[$q->id])) {
        $check = $deletability_map[$q->id];
        if ($check->can_delete) {
            $deletable_count++;
        } else {
            // Compter les raisons de protection
            if (!isset($protected_reasons[$check->reason])) {
                $protected_reasons[$check->reason] = 0;
            }
            $protected_reasons[$check->reason]++;
        }
    }
}

// En-tête du groupe
echo html_writer::start_tag('div', ['class' => 'alert alert-info', 'style' => 'margin: 20px 0;']);
echo html_writer::tag('h3', '📋 ' . get_string('group_summary', 'local_question_diagnostic'), ['style' => 'margin-top: 0;']);
echo html_writer::tag('p', '<strong>' . get_string('duplicate_group_name', 'local_question_diagnostic') . ' :</strong> ' . format_string($question_name));
echo html_writer::tag('p', '<strong>' . get_string('type', 'local_question_diagnostic') . ' :</strong> ' . $qtype);
echo html_writer::tag('p', '<strong>' . get_string('duplicate_instances_count', 'local_question_diagnostic') . ' :</strong> ' . count($all_questions) . ' question(s)');
echo html_writer::tag('p', '<strong>' . get_string('used_instances', 'local_question_diagnostic') . ' :</strong> ' . $used_count_preview);
echo html_writer::tag('p', '<strong>' . get_string('unused_instances', 'local_question_diagnostic') . ' :</strong> ' . $unused_count_preview);

// 🔍 Diagnostic de supprimabilité
echo html_writer::tag('p', '<strong>🔍 Instances supprimables :</strong> ' . $deletable_count . ' / ' . count($all_questions), 
    ['style' => $deletable_count > 0 ? 'color: #28a745; font-weight: bold;' : 'color: #d9534f; font-weight: bold;']);

// Afficher les raisons de protection si des questions sont protégées
if (!empty($protected_reasons)) {
    echo html_writer::start_tag('div', ['style' => 'margin-top: 10px; padding: 10px; background: #fff3cd; border-left: 3px solid #ffc107;']);
    echo html_writer::tag('strong', '⚠️ Raisons de protection :');
    echo html_writer::start_tag('ul', ['style' => 'margin: 5px 0 0 0; padding-left: 20px;']);
    foreach ($protected_reasons as $reason => $count) {
        echo html_writer::tag('li', $reason . ' : ' . $count . ' question(s)');
    }
    echo html_writer::end_tag('ul');
    echo html_writer::end_tag('div');
}

echo html_writer::end_tag('div');

// 🆕 v1.9.23 : Bouton de suppression en masse (au-dessus du tableau)
echo html_writer::start_tag('div', ['id' => 'bulk-actions-container', 'style' => 'margin-bottom: 15px; display: none;']);
echo html_writer::tag('button', '🗑️ Supprimer la sélection', [
    'id' => 'bulk-delete-btn',
    'class' => 'btn btn-danger',
    'onclick' => 'bulkDeleteQuestions()',
    'style' => 'margin-right: 10px;'
]);
echo html_writer::tag('span', '0 question(s) sélectionnée(s)', [
    'id' => 'selection-count',
    'style' => 'font-weight: bold; color: #666;'
]);
echo html_writer::end_tag('div');

// Tableau détaillé
echo html_writer::tag('h3', '📋 ' . get_string('all_duplicate_instances', 'local_question_diagnostic'));

// Légende pour le symbole 🎯
echo html_writer::start_tag('div', ['class' => 'alert alert-light', 'style' => 'margin: 10px 0; padding: 10px; border-left: 3px solid #0f6cbf;']);
echo html_writer::tag('small', get_string('representative_marker', 'local_question_diagnostic'), ['style' => 'color: #666;']);
echo html_writer::end_tag('div');

echo html_writer::start_tag('table', ['class' => 'qd-table qd-sortable-table', 'style' => 'width: 100%;', 'id' => 'group-detail-table']);

// En-tête avec tri
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
echo html_writer::tag('th', '<input type="checkbox" id="select-all-questions" title="Tout sélectionner/désélectionner">', ['style' => 'width: 40px;']);
echo html_writer::tag('th', 'ID ▲▼', ['class' => 'sortable', 'data-column' => 'id', 'style' => 'cursor: pointer;']);
echo html_writer::tag('th', 'Nom ▲▼', ['class' => 'sortable', 'data-column' => 'name', 'style' => 'cursor: pointer;']);
echo html_writer::tag('th', 'Type ▲▼', ['class' => 'sortable', 'data-column' => 'type', 'style' => 'cursor: pointer;']);
echo html_writer::tag('th', get_string('question_hidden_status', 'local_question_diagnostic') . ' ▲▼', ['class' => 'sortable', 'data-column' => 'visibility', 'style' => 'cursor: pointer;', 'title' => 'Statut de visibilité de la question']);
echo html_writer::tag('th', get_string('question_version_count', 'local_question_diagnostic') . ' ▲▼', ['class' => 'sortable', 'data-column' => 'versions', 'style' => 'cursor: pointer;', 'title' => get_string('question_version_count_tooltip', 'local_question_diagnostic')]);
echo html_writer::tag('th', 'Catégorie ▲▼', ['class' => 'sortable', 'data-column' => 'category', 'style' => 'cursor: pointer;']);
echo html_writer::tag('th', 'Contexte ▲▼', ['class' => 'sortable', 'data-column' => 'context', 'style' => 'cursor: pointer;']);
echo html_writer::tag('th', 'Cours ▲▼', ['class' => 'sortable', 'data-column' => 'course', 'style' => 'cursor: pointer;']);
echo html_writer::tag('th', '📊 Quiz ▲▼', ['class' => 'sortable', 'data-column' => 'quiz', 'style' => 'cursor: pointer;', 'title' => 'Nombre de quiz utilisant cette question']);
echo html_writer::tag('th', '🔢 Util. ▲▼', ['class' => 'sortable', 'data-column' => 'usages', 'style' => 'cursor: pointer;', 'title' => 'Nombre total d\'utilisations (dans différents quiz)']);
echo html_writer::tag('th', 'Statut ▲▼', ['class' => 'sortable', 'data-column' => 'status', 'style' => 'cursor: pointer;']);
echo html_writer::tag('th', 'Créée le ▲▼', ['class' => 'sortable', 'data-column' => 'created', 'style' => 'cursor: pointer;']);
echo html_writer::tag('th', 'Actions');
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');

echo html_writer::start_tag('tbody');

// Note : $deletability_map déjà calculé plus haut pour le diagnostic

foreach ($all_questions as $q) {
    $stats = question_analyzer::get_question_stats($q);
    
    // Récupérer les stats d'usage spécifiques
    $quiz_count = 0;
    $total_usages = 0;
    
    if (isset($group_usage_map[$q->id]) && is_array($group_usage_map[$q->id])) {
        $quiz_count = isset($group_usage_map[$q->id]['quiz_count']) ? $group_usage_map[$q->id]['quiz_count'] : 0;
        $total_usages = isset($group_usage_map[$q->id]['quiz_list']) ? count($group_usage_map[$q->id]['quiz_list']) : 0;
    }
    
    $is_used = $quiz_count > 0;
    
    // Mettre à jour les stats avec les vraies valeurs pour CETTE question
    $stats->quiz_count = $quiz_count;
    $stats->total_usages = $total_usages;
    
    // 🆕 v1.9.55 : Récupérer les infos de versions pour cette question
    $version_info = isset($version_info_map[$q->id]) ? $version_info_map[$q->id] : (object)[
        'is_hidden' => false,
        'version_count' => 0,
        'status' => 'unknown'
    ];
    
    $row_style = '';
    if ($representative_id && $q->id == $representative_id) {
        $row_style = 'background: #d4edda; font-weight: bold;';
    } else if ($is_used) {
        $row_style = 'background: #fff3cd;'; // Jaune pour les utilisées
    }
    
    // Attributs data-* pour le tri
    $row_attrs = [
        'style' => $row_style,
        'data-question-id' => $q->id,
        'data-id' => $q->id,
        'data-name' => format_string($q->name),
        'data-type' => $q->qtype,
        'data-visibility' => $version_info->is_hidden ? '0' : '1', // 0 = caché, 1 = visible (pour tri)
        'data-versions' => $version_info->version_count,
        'data-category' => isset($stats->category_name) ? $stats->category_name : 'N/A',
        'data-context' => isset($stats->context_name) ? strip_tags($stats->context_name) : '-',
        'data-course' => isset($stats->course_name) ? strip_tags($stats->course_name) : '-',
        'data-quiz' => $quiz_count,
        'data-usages' => $total_usages,
        'data-status' => $is_used ? '1' : '0',
        'data-created' => $q->timecreated
    ];
    
    echo html_writer::start_tag('tr', $row_attrs);
    
    // Checkbox de sélection (uniquement pour questions supprimables)
    $can_delete_check = isset($deletability_map[$q->id]) ? $deletability_map[$q->id] : null;
    
    echo html_writer::start_tag('td', ['style' => 'text-align: center;']);
    if ($can_delete_check && $can_delete_check->can_delete) {
        echo '<input type="checkbox" class="question-select-checkbox" value="' . $q->id . '" data-question-id="' . $q->id . '">';
    }
    echo html_writer::end_tag('td');
    
    echo html_writer::tag('td', $q->id . ($representative_id && $q->id == $representative_id ? ' 🎯' : ''));
    echo html_writer::tag('td', format_string($q->name));
    echo html_writer::tag('td', $q->qtype);
    
    // 🆕 v1.9.57 : Colonne Visibilité (visible/cachée/supprimée)
    $visibility_status = question_analyzer::get_question_visibility_status($q->id, $version_info, $group_usage_map);
    
    switch ($visibility_status) {
        case 'deleted':
            $visibility_text = get_string('question_deleted', 'local_question_diagnostic');
            $visibility_style = 'color: #d9534f; font-weight: bold;';
            $visibility_tooltip = get_string('question_deleted_tooltip', 'local_question_diagnostic');
            break;
        case 'hidden':
            $visibility_text = get_string('question_hidden', 'local_question_diagnostic');
            $visibility_style = 'color: #f0ad4e; font-weight: bold;';
            $visibility_tooltip = get_string('question_hidden_tooltip', 'local_question_diagnostic');
            break;
        default: // 'visible'
            $visibility_text = get_string('question_visible', 'local_question_diagnostic');
            $visibility_style = 'color: #5cb85c;';
            $visibility_tooltip = 'Question visible et active';
            break;
    }
    
    echo html_writer::tag('td', $visibility_text, [
        'style' => $visibility_style . ' text-align: center;',
        'title' => $visibility_tooltip . ' (Statut: ' . $version_info->status . ')'
    ]);
    
    // 🆕 v1.9.55 : Colonne Nombre de versions
    $version_count_style = $version_info->version_count > 1 
        ? 'font-weight: bold; color: #0f6cbf;' 
        : 'color: #666;';
    echo html_writer::tag('td', $version_info->version_count, [
        'style' => $version_count_style . ' text-align: center;',
        'title' => $version_info->version_count > 1 
            ? 'Cette question a ' . $version_info->version_count . ' versions' 
            : 'Version unique'
    ]);
    
    // Catégorie cliquable
    echo html_writer::start_tag('td');
    if (isset($stats->category_id) && $stats->category_id > 0 && isset($stats->context_id)) {
        $cat_url = new moodle_url('/question/edit.php', [
            'courseid' => 1,
            'cat' => $stats->category_id . ',' . $stats->context_id
        ]);
        $category_display = html_writer::link($cat_url, format_string($stats->category_name), ['target' => '_blank', 'title' => 'Ouvrir la catégorie dans la banque de questions']);
        $category_display .= ' <span style="color: #666; font-size: 11px;">(ID: ' . $stats->category_id . ')</span>';
        echo $category_display;
    } else {
        echo 'N/A';
    }
    echo html_writer::end_tag('td');
    
    // Afficher nom du contexte + ID
    $context_display = isset($stats->context_name) ? $stats->context_name : '-';
    if (isset($stats->context_id) && $stats->context_id > 0) {
        $context_display .= ' <span style="color: #666; font-size: 10px;">(ID: ' . $stats->context_id . ')</span>';
    }
    echo html_writer::tag('td', $context_display, ['style' => 'font-size: 12px;']);
    echo html_writer::tag('td', isset($stats->course_name) ? '📚 ' . $stats->course_name : '-');
    
    // Colonne "Dans Quiz" - Nombre de quiz différents
    $quiz_style = $quiz_count > 0 ? 'font-weight: bold; color: #28a745;' : 'color: #999;';
    echo html_writer::tag('td', $quiz_count, [
        'style' => $quiz_style,
        'title' => $quiz_count > 0 ? "Cette question est utilisée dans $quiz_count quiz" : "Non utilisée"
    ]);
    
    // Colonne "Utilisations" - Nombre total d'utilisations
    $usage_style = $total_usages > 0 ? 'font-weight: bold; color: #0f6cbf;' : 'color: #999;';
    echo html_writer::tag('td', $total_usages, [
        'style' => $usage_style,
        'title' => $total_usages > 0 ? "Total de $total_usages utilisation(s)" : "Aucune utilisation"
    ]);
    
    echo html_writer::tag('td', $is_used ? '✅ Utilisée' : '⚠️ Inutilisée');
    echo html_writer::tag('td', userdate($q->timecreated, '%d/%m/%Y %H:%M'));
    
    // Actions
    echo html_writer::start_tag('td', ['style' => 'white-space: nowrap;']);
    
    // Bouton Voir
    $view_url = question_analyzer::get_question_bank_url($q);
    if ($view_url) {
        echo html_writer::link($view_url, '👁️', [
            'class' => 'btn btn-sm btn-primary', 
            'target' => '_blank', 
            'title' => 'Voir',
            'style' => 'margin-right: 5px;'
        ]);
    }
    
    // Bouton Supprimer avec protection
    if ($can_delete_check && $can_delete_check->can_delete) {
        // Question supprimable
        $delete_url = new moodle_url('/local/question_diagnostic/actions/delete_question.php', [
            'id' => $q->id,
            'sesskey' => sesskey()
        ]);
        echo html_writer::link($delete_url, '🗑️', [
            'class' => 'btn btn-sm btn-danger',
            'title' => 'Supprimer ce doublon inutilisé',
            'style' => 'background: #d9534f; color: white; padding: 3px 8px; margin-right: 5px;'
        ]);
    } else {
        // Question protégée - Afficher la raison COMPLÈTE avec détails
        $reason = $can_delete_check ? $can_delete_check->reason : 'Vérification impossible';
        
        // 🔍 Mode diagnostic : ajouter les détails complets
        $debug_info = '';
        if ($can_delete_check && isset($can_delete_check->details) && !empty($can_delete_check->details)) {
            $debug_details = [];
            foreach ($can_delete_check->details as $key => $value) {
                if (is_array($value)) {
                    $debug_details[] = "$key: " . count($value) . " items";
                } else {
                    $debug_details[] = "$key: " . var_export($value, true);
                }
            }
            if (!empty($debug_details)) {
                $debug_info = "\n\nDétails:\n" . implode("\n", $debug_details);
            }
        }
        
        echo html_writer::tag('span', '🔒', [
            'class' => 'btn btn-sm btn-secondary',
            'title' => 'PROTÉGÉE : ' . $reason . $debug_info,
            'style' => 'background: #6c757d; color: white; padding: 3px 8px; cursor: not-allowed; margin-right: 5px;'
        ]);
    }
    
    echo html_writer::end_tag('td');
    
    echo html_writer::end_tag('tr');
}

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');

// 🆕 v1.9.23 : JavaScript pour gestion de la sélection en masse
echo html_writer::start_tag('script');
echo "
// Gestion sélection de toutes les checkboxes
document.getElementById('select-all-questions').addEventListener('change', function() {
    var checkboxes = document.querySelectorAll('.question-select-checkbox');
    checkboxes.forEach(function(cb) {
        cb.checked = this.checked;
    }.bind(this));
    updateSelectionCount();
});

// Gestion sélection individuelle
document.querySelectorAll('.question-select-checkbox').forEach(function(cb) {
    cb.addEventListener('change', updateSelectionCount);
});

// Mettre à jour le compteur de sélection
function updateSelectionCount() {
    var checked = document.querySelectorAll('.question-select-checkbox:checked');
    var count = checked.length;
    document.getElementById('selection-count').textContent = count + ' question(s) sélectionnée(s)';
    document.getElementById('bulk-actions-container').style.display = count > 0 ? 'block' : 'none';
}

// Suppression en masse
function bulkDeleteQuestions() {
    var checked = document.querySelectorAll('.question-select-checkbox:checked');
    var ids = Array.from(checked).map(function(cb) { return cb.value; });
    
    if (ids.length === 0) {
        alert('Aucune question sélectionnée');
        return;
    }
    
    // Confirmation
    var message = 'Êtes-vous sûr de vouloir supprimer ' + ids.length + ' question(s) ?\\n\\n';
    message += '⚠️ ATTENTION : Cette action est IRRÉVERSIBLE !\\n\\n';
    message += 'Questions à supprimer : ' + ids.join(', ');
    
    if (confirm(message)) {
        // Rediriger vers l'action de suppression en masse
        var url = '" . (new \moodle_url('/local/question_diagnostic/actions/delete_questions_bulk.php'))->out(false) . "';
        url += '?ids=' + ids.join(',') + '&sesskey=" . sesskey() . "';
        window.location.href = url;
    }
}

// ======================================================================
// TRI DES COLONNES
// ======================================================================

document.addEventListener('DOMContentLoaded', function() {
    const table = document.getElementById('group-detail-table');
    if (!table) return;
    
    const headers = table.querySelectorAll('th.sortable');
    let currentSort = { column: null, direction: 'asc' };
    
    headers.forEach(function(header) {
        header.addEventListener('click', function() {
            const column = this.getAttribute('data-column');
            
            if (currentSort.column === column) {
                currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
            } else {
                currentSort.column = column;
                currentSort.direction = 'asc';
            }
            
            sortTable(table, column, currentSort.direction);
            
            // Mettre à jour les indicateurs visuels
            headers.forEach(h => {
                h.classList.remove('sort-asc', 'sort-desc');
            });
            this.classList.add('sort-' + currentSort.direction);
        });
    });
});

function sortTable(table, column, direction) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    rows.sort(function(a, b) {
        let aVal = a.getAttribute('data-' + column) || '';
        let bVal = b.getAttribute('data-' + column) || '';
        
        // Tenter de convertir en nombre si possible
        const aNum = parseFloat(aVal);
        const bNum = parseFloat(bVal);
        
        if (!isNaN(aNum) && !isNaN(bNum)) {
            return direction === 'asc' ? aNum - bNum : bNum - aNum;
        } else {
            aVal = aVal.toLowerCase();
            bVal = bVal.toLowerCase();
            if (direction === 'asc') {
                return aVal < bVal ? -1 : aVal > bVal ? 1 : 0;
            } else {
                return aVal > bVal ? -1 : aVal < bVal ? 1 : 0;
            }
        }
    });
    
    rows.forEach(row => tbody.appendChild(row));
}
";
echo html_writer::end_tag('script');

// Résumé détaillé avec terminologie clarifiée
echo html_writer::start_tag('div', ['class' => 'alert alert-info', 'style' => 'margin-top: 20px;']);
echo html_writer::tag('h4', '📊 ' . get_string('duplicate_analysis', 'local_question_diagnostic'));
echo html_writer::tag('p', '<strong>' . get_string('total_instances', 'local_question_diagnostic') . ' :</strong> ' . count($all_questions) . ' question(s) distinctes');

$used_count = 0;
$unused_count = 0;
$total_quiz_count = 0;
$total_usages = 0;

foreach ($all_questions as $q) {
    $quiz_count = 0;
    $question_usages = 0;
    
    if (isset($group_usage_map[$q->id]) && is_array($group_usage_map[$q->id])) {
        $quiz_count = isset($group_usage_map[$q->id]['quiz_count']) ? $group_usage_map[$q->id]['quiz_count'] : 0;
        $question_usages = isset($group_usage_map[$q->id]['quiz_list']) ? count($group_usage_map[$q->id]['quiz_list']) : 0;
    }
    
    if ($quiz_count > 0) {
        $used_count++;
    } else {
        $unused_count++;
    }
    
    $total_quiz_count += $quiz_count;
    $total_usages += $question_usages;
}

echo html_writer::tag('p', '<strong>' . get_string('used_instances_desc', 'local_question_diagnostic') . ' :</strong> ' . $used_count);
echo html_writer::tag('p', '<strong>' . get_string('unused_instances_deletable', 'local_question_diagnostic') . ' :</strong> ' . $unused_count);
echo html_writer::tag('p', '<strong>' . get_string('total_quizzes_using', 'local_question_diagnostic') . ' :</strong> ' . $total_quiz_count);
echo html_writer::tag('p', '<strong>' . get_string('total_usages_count', 'local_question_diagnostic') . ' :</strong> ' . $total_usages);

echo html_writer::start_tag('div', ['style' => 'margin-top: 20px; padding: 15px; background: #e7f3ff; border-left: 4px solid #0f6cbf;']);
echo html_writer::tag('strong', '💡 Recommandation : ');
if ($unused_count > 0) {
    $recommendation_data = new stdClass();
    $recommendation_data->unused = $unused_count;
    $recommendation_data->used = $used_count;
    echo get_string('recommendation_unused', 'local_question_diagnostic', $recommendation_data);
} else {
    echo get_string('recommendation_all_used', 'local_question_diagnostic');
}
echo html_writer::end_tag('div');

echo html_writer::end_tag('div');

// Bouton retour
echo html_writer::start_tag('div', ['style' => 'margin-top: 30px; text-align: center;']);
echo html_writer::link(
    new moodle_url('/local/question_diagnostic/questions_cleanup.php', ['loadstats' => 1]),
    '← ' . get_string('back_to_groups_list', 'local_question_diagnostic'),
    ['class' => 'btn btn-secondary btn-lg']
);
echo html_writer::end_tag('div');

// CSS pour les indicateurs de tri
echo html_writer::start_tag('style');
?>
.sort-asc::after {
    content: ' ▲';
    font-size: 10px;
    color: #0f6cbf;
}

.sort-desc::after {
    content: ' ▼';
    font-size: 10px;
    color: #0f6cbf;
}

th.sortable:hover {
    background-color: #f5f5f5;
}
<?php
echo html_writer::end_tag('style');

// Pied de page Moodle standard
echo $OUTPUT->footer();

