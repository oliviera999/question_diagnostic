<?php
// ======================================================================
// Moodle Question Link Checker - Détection et réparation des liens cassés
// ======================================================================

// Inclure la configuration de Moodle.
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/question_link_checker.php');
require_once(__DIR__ . '/classes/question_analyzer.php');

use local_question_diagnostic\question_link_checker;
use local_question_diagnostic\question_analyzer;

// Charger les bibliothèques Moodle nécessaires.
require_login();

// Vérification stricte : seuls les administrateurs du site peuvent accéder à cette page.
if (!is_siteadmin()) {
    print_error('accesinterdit', 'admin', '', 'Vous devez être administrateur du site pour accéder à cet outil.');
    exit;
}

// Définir le contexte de la page (système).
$context = context_system::instance();

// Définir le titre et l'URL de la page.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/question_diagnostic/broken_links.php'));
$pagetitle = get_string('brokenlinks_heading', 'local_question_diagnostic');
$PAGE->set_title(get_string('brokenlinks', 'local_question_diagnostic'));
$PAGE->set_heading(local_question_diagnostic_get_heading_with_version($pagetitle));
$PAGE->set_pagelayout('report');

// Ajouter les CSS et JavaScript personnalisés
$PAGE->requires->css('/local/question_diagnostic/styles/main.css');
$PAGE->requires->js('/local/question_diagnostic/scripts/main.js', true);

// Traitement des actions
$action = optional_param('action', '', PARAM_ALPHA);
$questionid = optional_param('questionid', 0, PARAM_INT);
$field = optional_param('field', '', PARAM_TEXT);
$url = optional_param('url', '', PARAM_TEXT);
$refresh = optional_param('refresh', 0, PARAM_INT);

// Action de rafraîchissement du cache
if ($refresh) {
    require_sesskey();
    question_link_checker::purge_broken_links_cache();
    redirect($PAGE->url, '✅ Cache purgé. Analyse des liens en cours...', null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($action) {
    require_sesskey();
    if ($action === 'remove') {
        $result = question_link_checker::remove_broken_link($questionid, $field, $url);
        if ($result === true) {
            // Purger le cache après modification
            question_link_checker::purge_broken_links_cache();
            redirect($PAGE->url, 'Lien cassé supprimé avec succès.', null, \core\output\notification::NOTIFY_SUCCESS);
        } else {
            redirect($PAGE->url, $result, null, \core\output\notification::NOTIFY_ERROR);
        }
    } else if ($action === 'repair') {
        $repair_result = question_link_checker::attempt_repair($questionid, $field, $url);
        // Pour l'instant, on affiche juste les suggestions
        // La réparation automatique nécessiterait plus de logique
    }
}

// ======================================================================
// Section d'en-tête Moodle standard.
echo $OUTPUT->header();

// Afficher le badge de version
echo local_question_diagnostic_render_version_badge();

// 🆕 v1.9.44 : Lien retour hiérarchique + Bouton rafraîchir
echo html_writer::start_tag('div', ['style' => 'margin-bottom: 20px; display: flex; gap: 10px;']);
echo local_question_diagnostic_render_back_link('broken_links.php');
echo html_writer::link(
    new moodle_url('/local/question_diagnostic/broken_links.php', ['refresh' => 1, 'sesskey' => sesskey()]),
    '🔄 Rafraîchir l\'analyse',
    [
        'class' => 'btn btn-warning',
        'title' => 'Forcer une nouvelle analyse des liens (purge le cache)'
    ]
);

// 🆕 v1.9.35 : Lien vers le centre d'aide
echo html_writer::link(
    new moodle_url('/local/question_diagnostic/help.php'),
    '📚 Aide',
    ['class' => 'btn btn-outline-info']
);

echo html_writer::end_tag('div');

// ======================================================================
// STATISTIQUES GLOBALES
// ======================================================================

echo html_writer::tag('h2', '📊 ' . get_string('brokenlinks_stats', 'local_question_diagnostic'));

$globalstats = question_link_checker::get_global_stats();

echo html_writer::start_tag('div', ['class' => 'qd-dashboard']);

// Carte 1 : Total questions
echo html_writer::start_tag('div', ['class' => 'qd-card']);
echo html_writer::tag('div', get_string('total_questions_stats', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', $globalstats->total_questions, ['class' => 'qd-card-value']);
echo html_writer::tag('div', get_string('in_database', 'local_question_diagnostic'), ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

// Carte 2 : Questions avec liens cassés
echo html_writer::start_tag('div', ['class' => 'qd-card danger']);
echo html_writer::tag('div', get_string('questions_with_broken_links', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', $globalstats->questions_with_broken_links, ['class' => 'qd-card-value']);
echo html_writer::tag('div', get_string('questions_with_problems', 'local_question_diagnostic'), ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

// Carte 3 : Total liens cassés
echo html_writer::start_tag('div', ['class' => 'qd-card warning']);
echo html_writer::tag('div', get_string('total_broken_links', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', $globalstats->total_broken_links, ['class' => 'qd-card-value']);
echo html_writer::tag('div', get_string('questions_broken_links', 'local_question_diagnostic'), ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

// Carte 4 : Pourcentage de questions OK
$percentage_ok = $globalstats->total_questions > 0 
    ? round(($globalstats->total_questions - $globalstats->questions_with_broken_links) / $globalstats->total_questions * 100, 1)
    : 100;
$card_class = $percentage_ok >= 90 ? 'success' : ($percentage_ok >= 70 ? 'warning' : 'danger');
echo html_writer::start_tag('div', ['class' => 'qd-card ' . $card_class]);
echo html_writer::tag('div', get_string('global_health', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', $percentage_ok . '%', ['class' => 'qd-card-value']);
echo html_writer::tag('div', get_string('questions_ok', 'local_question_diagnostic'), ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

echo html_writer::end_tag('div'); // fin dashboard

// ======================================================================
// STATISTIQUES PAR TYPE DE QUESTION
// ======================================================================

if (!empty($globalstats->by_qtype)) {
    echo html_writer::tag('h3', '📈 ' . get_string('brokenlinks_by_type', 'local_question_diagnostic'));
    
    echo html_writer::start_tag('div', ['class' => 'qd-stats-by-type']);
    foreach ($globalstats->by_qtype as $qtype => $count) {
        echo html_writer::start_tag('div', ['class' => 'qd-stat-item']);
        echo html_writer::tag('span', ucfirst($qtype), ['class' => 'qd-stat-label']);
        echo html_writer::tag('span', $count, ['class' => 'qd-stat-value']);
        echo html_writer::end_tag('div');
    }
    echo html_writer::end_tag('div');
}

// ======================================================================
// FILTRES
// ======================================================================

echo html_writer::start_tag('div', ['class' => 'qd-filters', 'style' => 'margin-top: 30px;']);
echo html_writer::tag('h4', '🔍 ' . get_string('filters', 'local_question_diagnostic'), ['style' => 'margin-top: 0;']);

echo html_writer::start_tag('div', ['class' => 'qd-filters-row']);

// Recherche par nom
echo html_writer::start_tag('div', ['class' => 'qd-filter-group']);
echo html_writer::tag('label', get_string('search', 'local_question_diagnostic'), ['for' => 'filter-search']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'id' => 'filter-search',
    'placeholder' => get_string('filter_search_placeholder', 'local_question_diagnostic'),
    'class' => 'form-control'
]);
echo html_writer::end_tag('div');

// Filtre par type
echo html_writer::start_tag('div', ['class' => 'qd-filter-group']);
echo html_writer::tag('label', get_string('question_type', 'local_question_diagnostic'), ['for' => 'filter-qtype']);
echo html_writer::start_tag('select', ['id' => 'filter-qtype', 'class' => 'form-control']);
echo html_writer::tag('option', get_string('all', 'local_question_diagnostic'), ['value' => 'all']);
foreach ($globalstats->by_qtype as $qtype => $count) {
    echo html_writer::tag('option', ucfirst($qtype) . " ($count)", ['value' => $qtype]);
}
echo html_writer::end_tag('select');
echo html_writer::end_tag('div');

echo html_writer::end_tag('div'); // fin qd-filters-row
echo html_writer::end_tag('div'); // fin qd-filters

// ======================================================================
// TABLEAU DES QUESTIONS AVEC LIENS CASSÉS
// ======================================================================

echo html_writer::tag('h3', '🔗 ' . get_string('brokenlinks_table', 'local_question_diagnostic'), ['style' => 'margin-top: 30px;']);

// Utiliser le cache par défaut (limite de 1000 questions pour éviter timeout)
$broken_questions = question_link_checker::get_questions_with_broken_links(true, 1000);

// 🆕 v1.9.55 : Récupérer les infos de versions (statut caché + nombre de versions) pour toutes les questions
$question_ids = array_map(function($item) { return $item->question->id; }, $broken_questions);
$version_info_map = [];
$usage_map = []; // 🆕 v1.9.57
if (!empty($question_ids)) {
    $version_info_map = question_analyzer::get_questions_version_info_batch($question_ids);
    // 🆕 v1.9.57 : Charger aussi les infos d'usage pour distinguer cachée vs supprimée
    $usage_map = question_analyzer::get_questions_usage_by_ids($question_ids);
}

// Afficher un avertissement si limite atteinte
if (count($broken_questions) >= 1000) {
    echo html_writer::start_div('alert alert-info', ['style' => 'margin-bottom: 20px;']);
    echo '<strong>ℹ️ Note :</strong> L\'analyse est limitée aux 1000 questions les plus récentes pour des raisons de performance. ';
    echo 'Les résultats sont mis en cache pendant 1 heure. Utilisez le bouton "Rafraîchir" pour forcer une nouvelle analyse.';
    echo html_writer::end_div();
}

if (empty($broken_questions)) {
    echo html_writer::tag('div', '✅ ' . get_string('no_broken_links', 'local_question_diagnostic'), [
        'class' => 'alert alert-success',
        'style' => 'padding: 20px; text-align: center; font-size: 16px;'
    ]);
} else {
    echo html_writer::start_tag('div', ['class' => 'qd-table-wrapper']);
    echo html_writer::start_tag('table', ['class' => 'qd-table']);
    
    // En-tête du tableau
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('question_id', 'local_question_diagnostic'), ['class' => 'sortable', 'data-column' => 'id']);
    echo html_writer::tag('th', get_string('question_name', 'local_question_diagnostic'), ['class' => 'sortable', 'data-column' => 'name']);
    echo html_writer::tag('th', get_string('question_type', 'local_question_diagnostic'), ['class' => 'sortable', 'data-column' => 'qtype']);
    echo html_writer::tag('th', get_string('question_hidden_status', 'local_question_diagnostic'), ['class' => 'sortable', 'data-column' => 'visibility', 'title' => 'Statut de visibilité de la question']);
    echo html_writer::tag('th', get_string('question_version_count', 'local_question_diagnostic'), ['class' => 'sortable', 'data-column' => 'versions', 'title' => get_string('question_version_count_tooltip', 'local_question_diagnostic')]);
    echo html_writer::tag('th', get_string('question_category', 'local_question_diagnostic'), ['class' => 'sortable', 'data-column' => 'category']);
    echo html_writer::tag('th', get_string('broken_links_count', 'local_question_diagnostic'), ['class' => 'sortable', 'data-column' => 'brokencount']);
    echo html_writer::tag('th', get_string('broken_links_details', 'local_question_diagnostic'));
    echo html_writer::tag('th', get_string('actions', 'local_question_diagnostic'));
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    
    // Corps du tableau
    echo html_writer::start_tag('tbody');
    
    foreach ($broken_questions as $item) {
        $question = $item->question;
        $category = $item->category;
        $broken_links = $item->broken_links;
        
        // 🆕 v1.9.55 : Récupérer les infos de versions pour cette question
        $version_info = isset($version_info_map[$question->id]) ? $version_info_map[$question->id] : (object)[
            'is_hidden' => false,
            'version_count' => 0,
            'status' => 'unknown'
        ];
        
        // Attributs pour le filtrage et le tri
        $row_attrs = [
            'data-id' => $question->id,
            'data-qtype' => $question->qtype,
            'data-name' => format_string($question->name),
            'data-visibility' => $version_info->is_hidden ? '0' : '1',
            'data-versions' => $version_info->version_count,
            'data-category' => $category ? format_string($category->name) : 'Inconnue',
            'data-brokencount' => $item->broken_count
        ];
        
        echo html_writer::start_tag('tr', $row_attrs);
        
        // ID
        echo html_writer::tag('td', $question->id);
        
        // Nom
        echo html_writer::start_tag('td');
        echo html_writer::tag('strong', format_string($question->name));
        echo html_writer::end_tag('td');
        
        // Type
        echo html_writer::tag('td', html_writer::tag('span', 
            ucfirst($question->qtype), 
            ['class' => 'badge badge-info']
        ));
        
        // 🆕 v1.9.57 : Colonne Visibilité (visible/cachée/supprimée)
        $visibility_status = question_analyzer::get_question_visibility_status($question->id, $version_info, $usage_map);
        
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
        
        // Catégorie
        echo html_writer::start_tag('td');
        if ($category) {
            $cat_url = new moodle_url('/question/edit.php', [
                'courseid' => 0,
                'cat' => $category->id . ',' . $category->contextid
            ]);
            $cat_name = format_string($category->name) . ' <span style="color: #666; font-size: 11px;">(ID: ' . $category->id . ')</span>';
            echo html_writer::link($cat_url, $cat_name, ['target' => '_blank']);
        } else {
            echo '-';
        }
        echo html_writer::end_tag('td');
        
        // Nombre de liens cassés
        echo html_writer::tag('td', html_writer::tag('span', 
            $item->broken_count, 
            ['class' => 'qd-badge qd-badge-empty', 'style' => 'font-size: 14px;']
        ));
        
        // Détails des liens cassés
        echo html_writer::start_tag('td');
        echo html_writer::start_tag('div', ['class' => 'qd-broken-links-details']);
        foreach ($broken_links as $link) {
            echo html_writer::start_tag('div', ['class' => 'qd-broken-link-item', 'style' => 'margin-bottom: 8px; padding: 8px; background: #f9f9f9; border-left: 3px solid #d9534f;']);
            echo html_writer::tag('div', '📍 Champ: ' . htmlspecialchars($link->field), ['style' => 'font-size: 11px; color: #666; font-weight: bold;']);
            echo html_writer::tag('div', '🔗 URL: ' . htmlspecialchars(substr($link->url, 0, 80)) . (strlen($link->url) > 80 ? '...' : ''), ['style' => 'font-size: 10px; color: #666; word-break: break-all;']);
            echo html_writer::tag('div', '⚠️ ' . htmlspecialchars($link->reason), ['style' => 'font-size: 11px; color: #d9534f; margin-top: 4px;']);
            echo html_writer::end_tag('div');
        }
        echo html_writer::end_tag('div');
        echo html_writer::end_tag('td');
        
        // Actions
        echo html_writer::start_tag('td');
        echo html_writer::start_tag('div', ['class' => 'qd-actions', 'style' => 'display: flex; flex-direction: column; gap: 5px;']);
        
        // Bouton voir dans la banque
        $questionbank_url = question_link_checker::get_question_bank_url($question, $category);
        if ($questionbank_url) {
            echo html_writer::link(
                $questionbank_url, 
                '👁️ Voir',
                [
                    'class' => 'qd-btn qd-btn-view',
                    'title' => 'Voir dans la banque de questions',
                    'target' => '_blank'
                ]
            );
        }
        
        // Bouton tentative de réparation
        echo html_writer::tag('button', '🔧 ' . get_string('repair', 'local_question_diagnostic'), [
            'class' => 'qd-btn qd-btn-merge repair-btn',
            'data-questionid' => $question->id,
            'data-name' => format_string($question->name),
            'data-links' => json_encode($broken_links),
            'onclick' => 'showRepairModal(' . $question->id . ', ' . json_encode(format_string($question->name)) . ', ' . json_encode($broken_links) . ')'
        ]);
        
        echo html_writer::end_tag('div');
        echo html_writer::end_tag('td');
        
        echo html_writer::end_tag('tr');
    }
    
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_tag('div'); // fin qd-table-wrapper
}

// ======================================================================
// MODAL DE RÉPARATION
// ======================================================================

echo html_writer::start_tag('div', ['class' => 'qd-modal', 'id' => 'repair-modal']);
echo html_writer::start_tag('div', ['class' => 'qd-modal-content']);

echo html_writer::start_tag('div', ['class' => 'qd-modal-header']);
echo html_writer::tag('h3', '🔧 ' . get_string('repair_modal_title', 'local_question_diagnostic'), ['class' => 'qd-modal-title']);
echo html_writer::tag('button', '&times;', ['class' => 'qd-modal-close', 'onclick' => 'closeRepairModal()']);
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'qd-modal-body'], ['id' => 'repair-modal-body']);
echo html_writer::tag('p', get_string('loading_questions', 'local_question_diagnostic'));
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'qd-modal-footer']);
echo html_writer::tag('button', get_string('close', 'moodle'), ['class' => 'btn btn-secondary', 'onclick' => 'closeRepairModal()']);
echo html_writer::end_tag('div');

echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

// ======================================================================
// JavaScript pour le modal de réparation
// ======================================================================

echo html_writer::start_tag('script');
?>
function showRepairModal(questionId, questionName, brokenLinks) {
    const modal = document.getElementById('repair-modal');
    const modalBody = document.getElementById('repair-modal-body');
    
    // Construire le contenu du modal
    let content = '<h4>Question: ' + questionName + ' (ID: ' + questionId + ')</h4>';
    content += '<p>Liens cassés détectés dans cette question:</p>';
    
    content += '<div style="max-height: 400px; overflow-y: auto;">';
    brokenLinks.forEach(function(link, index) {
        content += '<div style="margin: 15px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; background: #f9f9f9;">';
        content += '<strong>Lien #' + (index + 1) + '</strong><br>';
        content += '<div style="margin: 10px 0;">';
        content += '<strong>Champ:</strong> ' + link.field + '<br>';
        content += '<strong>URL:</strong> <code style="word-break: break-all; background: #fff; padding: 2px 5px; border-radius: 3px;">' + link.url + '</code><br>';
        content += '<strong>Raison:</strong> <span style="color: #d9534f;">' + link.reason + '</span>';
        content += '</div>';
        
        // Options de réparation
        content += '<div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd;">';
        content += '<strong>Options:</strong><br>';
        content += '<div style="margin-top: 8px;">';
        
        // Bouton supprimer la référence
        const removeUrl = new URL(window.location.href);
        removeUrl.searchParams.set('action', 'remove');
        removeUrl.searchParams.set('questionid', questionId);
        removeUrl.searchParams.set('field', link.field);
        removeUrl.searchParams.set('url', link.url);
        removeUrl.searchParams.set('sesskey', M.cfg.sesskey);
        
        content += '<a href="' + removeUrl.toString() + '" class="btn btn-danger btn-sm" style="margin-right: 5px;" onclick="return confirm(\'Êtes-vous sûr de vouloir supprimer cette référence ?\')">🗑️ Supprimer la référence</a>';
        content += '<span style="font-size: 12px; color: #666;">(Remplace le lien par [Image supprimée])</span>';
        content += '</div>';
        content += '</div>';
        
        content += '</div>';
    });
    content += '</div>';
    
    content += '<div style="margin-top: 20px; padding: 15px; background: #d9edf7; border: 1px solid #bce8f1; border-radius: 5px;">';
    content += '<strong>💡 Recommandation:</strong> ';
    content += 'Vérifiez d\'abord la question dans la banque de questions pour voir si les fichiers peuvent être réuploadés manuellement. ';
    content += 'La suppression de la référence est une solution de dernier recours.';
    content += '</div>';
    
    modalBody.innerHTML = content;
    modal.style.display = 'block';
}

function closeRepairModal() {
    const modal = document.getElementById('repair-modal');
    modal.style.display = 'none';
}

// Fermer le modal en cliquant en dehors
window.onclick = function(event) {
    const modal = document.getElementById('repair-modal');
    if (event.target === modal) {
        closeRepairModal();
    }
}

// Filtres
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('filter-search');
    const qtypeFilter = document.getElementById('filter-qtype');
    
    function applyFilters() {
        const searchValue = searchInput ? searchInput.value.toLowerCase() : '';
        const qtypeValue = qtypeFilter ? qtypeFilter.value : 'all';
        
        const rows = document.querySelectorAll('.qd-table tbody tr');
        let visibleCount = 0;
        
        rows.forEach(function(row) {
            const name = (row.getAttribute('data-name') || '').toLowerCase();
            const category = (row.getAttribute('data-category') || '').toLowerCase();
            const qtype = row.getAttribute('data-qtype') || '';
            
            const matchesSearch = searchValue === '' || name.includes(searchValue) || category.includes(searchValue);
            const matchesQtype = qtypeValue === 'all' || qtype === qtypeValue;
            
            if (matchesSearch && matchesQtype) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
    }
    
    if (searchInput) {
        searchInput.addEventListener('input', applyFilters);
    }
    if (qtypeFilter) {
        qtypeFilter.addEventListener('change', applyFilters);
    }
});
<?php
echo html_writer::end_tag('script');

// ======================================================================
// Pied de page Moodle standard
// ======================================================================
echo $OUTPUT->footer();

