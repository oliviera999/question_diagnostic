<?php
// ======================================================================
// Moodle Question Bank Management Tool - Gestion des catégories à supprimer
// ======================================================================

// Inclure la configuration de Moodle.
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/category_manager.php');

use local_question_diagnostic\category_manager;

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
$PAGE->set_url(new moodle_url('/local/question_diagnostic/categories.php'));
$pagetitle = get_string('tool_categories_title', 'local_question_diagnostic');
$PAGE->set_title($pagetitle);
$PAGE->set_heading(local_question_diagnostic_get_heading_with_version($pagetitle));
$PAGE->set_pagelayout('report');

// Ajouter les CSS et JavaScript personnalisés
$PAGE->requires->css('/local/question_diagnostic/styles/main.css');
$PAGE->requires->js('/local/question_diagnostic/scripts/main.js', true);

// ======================================================================
// Section d'en-tête Moodle standard.
echo $OUTPUT->header();

// Alerte sécurité pour les suppressions
echo html_writer::start_div('alert alert-warning', ['style' => 'margin-bottom: 20px; border-left: 4px solid #d9534f;']);
echo '<strong>🛡️ ATTENTION</strong> : Cette page permet de supprimer des catégories. ';
echo 'Consultez la ';
echo html_writer::link(
    new moodle_url('/local/question_diagnostic/DATABASE_IMPACT.md'),
    'documentation DATABASE_IMPACT.md',
    ['target' => '_blank', 'style' => 'font-weight: bold; text-decoration: underline;']
);
echo ' pour les procédures de backup avant toute suppression.';
echo html_writer::end_div();

// Lien retour vers le menu principal
echo html_writer::start_tag('div', ['style' => 'margin-bottom: 20px;']);
echo html_writer::link(
    new moodle_url('/local/question_diagnostic/index.php'),
    '← ' . get_string('backtomenu', 'local_question_diagnostic'),
    ['class' => 'btn btn-secondary']
);
echo html_writer::end_tag('div');

// ======================================================================
// STATISTIQUES GLOBALES (Dashboard)
// ======================================================================

$globalstats = category_manager::get_global_stats();

echo html_writer::start_tag('div', ['class' => 'qd-dashboard']);

// Carte 1 : Total catégories
echo html_writer::start_tag('div', ['class' => 'qd-card']);
echo html_writer::tag('div', 'Total Catégories', ['class' => 'qd-card-title']);
echo html_writer::tag('div', $globalstats->total_categories, ['class' => 'qd-card-value']);
echo html_writer::tag('div', 'Dans la base de données', ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

// Carte 2 : Catégories vides
echo html_writer::start_tag('div', ['class' => 'qd-card warning']);
echo html_writer::tag('div', 'Catégories Vides', ['class' => 'qd-card-title']);
echo html_writer::tag('div', $globalstats->empty_categories, ['class' => 'qd-card-value']);
echo html_writer::tag('div', 'Sans questions ni sous-catégories (supprimables)', ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

// Carte 3 : Catégories orphelines
echo html_writer::start_tag('div', ['class' => 'qd-card danger']);
echo html_writer::tag('div', 'Catégories Orphelines', ['class' => 'qd-card-title']);
echo html_writer::tag('div', $globalstats->orphan_categories, ['class' => 'qd-card-value']);
echo html_writer::tag('div', 'Contexte invalide', ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

// Carte 4 : Doublons
echo html_writer::start_tag('div', ['class' => 'qd-card danger']);
echo html_writer::tag('div', 'Doublons Détectés', ['class' => 'qd-card-title']);
echo html_writer::tag('div', $globalstats->duplicates, ['class' => 'qd-card-value']);
echo html_writer::tag('div', 'Catégories en double', ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

// Carte 5 : Total questions
echo html_writer::start_tag('div', ['class' => 'qd-card success']);
echo html_writer::tag('div', 'Total Questions', ['class' => 'qd-card-title']);
echo html_writer::tag('div', $globalstats->total_questions, ['class' => 'qd-card-value']);
echo html_writer::tag('div', 'Dans toutes les catégories', ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

// Carte 6 : Catégories protégées
if (isset($globalstats->protected_default) && $globalstats->protected_default > 0) {
    echo html_writer::start_tag('div', ['class' => 'qd-card', 'style' => 'border: 2px solid #5bc0de;']);
    echo html_writer::tag('div', 'Catégories Protégées', ['class' => 'qd-card-title']);
    echo html_writer::tag('div', $globalstats->protected_default, ['class' => 'qd-card-value']);
    echo html_writer::tag('div', '🛡️ "Default for..." (non supprimables)', ['class' => 'qd-card-subtitle']);
    echo html_writer::end_tag('div');
}

echo html_writer::end_tag('div'); // fin dashboard

// ======================================================================
// AVERTISSEMENT CATÉGORIES PROTÉGÉES
// ======================================================================

if (isset($globalstats->protected_default) && $globalstats->protected_default > 0) {
    echo html_writer::start_div('alert alert-info', ['style' => 'margin: 20px 0; border-left: 4px solid #5bc0de;']);
    echo '<strong>🛡️ PROTECTIONS ACTIVES</strong><br>';
    echo 'Le plugin protège automatiquement <strong>' . $globalstats->protected_default . ' catégorie(s)</strong> qui ne peuvent pas être supprimées :<br>';
    echo '<ul style="margin-top: 10px; margin-bottom: 5px;">';
    echo '<li>Catégories "<strong>Default for...</strong>" (créées par Moodle pour chaque cours)</li>';
    echo '<li>Catégories <strong>racine</strong> (parent=0) dans les contextes de cours</li>';
    echo '<li>Catégories avec une <strong>description</strong> (usage intentionnel)</li>';
    echo '</ul>';
    echo '<em>Ces protections évitent de casser la structure de votre Moodle.</em>';
    echo html_writer::end_div();
}

// ======================================================================
// BARRE D'ACTIONS ET EXPORT
// ======================================================================

echo html_writer::start_tag('div', ['style' => 'margin: 20px 0; display: flex; gap: 10px; flex-wrap: wrap;']);

$exporturl = new moodle_url('/local/question_diagnostic/actions/export.php', [
    'type' => 'csv',
    'sesskey' => sesskey()
]);
echo html_writer::link($exporturl, '📥 ' . get_string('export', 'local_question_diagnostic'), ['class' => 'btn btn-success']);

echo html_writer::end_tag('div');

// ======================================================================
// FILTRES ET RECHERCHE
// ======================================================================

echo html_writer::start_tag('div', ['class' => 'qd-filters']);
echo html_writer::tag('h4', '🔍 ' . get_string('filters', 'local_question_diagnostic'), ['style' => 'margin-top: 0;']);

echo html_writer::start_tag('div', ['class' => 'qd-filters-row']);

// Recherche par nom
echo html_writer::start_tag('div', ['class' => 'qd-filter-group']);
echo html_writer::tag('label', get_string('search', 'local_question_diagnostic'), ['for' => 'filter-search']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'id' => 'filter-search',
    'placeholder' => get_string('searchplaceholder', 'local_question_diagnostic'),
    'class' => 'form-control'
]);
echo html_writer::end_tag('div');

// Filtre par statut
echo html_writer::start_tag('div', ['class' => 'qd-filter-group']);
echo html_writer::tag('label', get_string('status', 'local_question_diagnostic'), ['for' => 'filter-status']);
echo html_writer::start_tag('select', ['id' => 'filter-status', 'class' => 'form-control']);
echo html_writer::tag('option', get_string('all', 'local_question_diagnostic'), ['value' => 'all']);
echo html_writer::tag('option', get_string('empty', 'local_question_diagnostic'), ['value' => 'empty']);
echo html_writer::tag('option', get_string('orphan', 'local_question_diagnostic'), ['value' => 'orphan']);
echo html_writer::tag('option', get_string('ok', 'local_question_diagnostic'), ['value' => 'ok']);
echo html_writer::end_tag('select');
echo html_writer::end_tag('div');

// Filtre par contexte
echo html_writer::start_tag('div', ['class' => 'qd-filter-group']);
echo html_writer::tag('label', get_string('context', 'local_question_diagnostic'), ['for' => 'filter-context']);
echo html_writer::start_tag('select', ['id' => 'filter-context', 'class' => 'form-control']);
echo html_writer::tag('option', 'Tous', ['value' => 'all']);

// Récupérer les contextes uniques avec JOIN pour éviter N+1 queries
global $DB;
$contexts = $DB->get_records_sql("
    SELECT DISTINCT qc.contextid, ctx.contextlevel
    FROM {question_categories} qc
    LEFT JOIN {context} ctx ON ctx.id = qc.contextid
    WHERE ctx.id IS NOT NULL
    ORDER BY qc.contextid
");
foreach ($contexts as $ctx) {
    try {
        if ($ctx->contextlevel) {
            $context_name = context_helper::get_level_name($ctx->contextlevel);
            echo html_writer::tag('option', "$context_name (ID: {$ctx->contextid})", ['value' => $ctx->contextid]);
        }
    } catch (Exception $e) {
        continue;
    }
}
echo html_writer::end_tag('select');
echo html_writer::end_tag('div');

echo html_writer::end_tag('div'); // fin qd-filters-row

echo html_writer::tag('div', '', ['id' => 'filter-stats', 'style' => 'margin-top: 10px; font-size: 14px; color: #666;']);

echo html_writer::end_tag('div'); // fin qd-filters

// ======================================================================
// BARRE D'ACTIONS GROUPÉES
// ======================================================================

echo html_writer::start_tag('div', ['class' => 'qd-bulk-actions', 'id' => 'bulk-actions-bar']);
echo html_writer::start_tag('div', ['class' => 'qd-bulk-actions-content']);

echo html_writer::tag('span', '📋 ', ['style' => 'font-size: 18px;']);
echo html_writer::tag('span', '', ['class' => 'qd-selected-count', 'id' => 'selected-count']);
echo html_writer::tag('span', ' ' . get_string('categoriesselected', 'local_question_diagnostic'));

echo html_writer::start_tag('div', ['class' => 'qd-bulk-actions-buttons']);

echo html_writer::tag('button', '🗑️ ' . get_string('bulkdelete', 'local_question_diagnostic'), [
    'id' => 'bulk-delete-btn',
    'class' => 'btn btn-danger',
    'title' => 'Supprimer les catégories vides sélectionnées'
]);

echo html_writer::tag('button', '📤 Exporter la sélection', [
    'id' => 'bulk-export-btn',
    'class' => 'btn btn-primary',
    'title' => 'Exporter les catégories sélectionnées en CSV'
]);

echo html_writer::tag('button', '❌ Annuler', [
    'id' => 'bulk-cancel-btn',
    'class' => 'btn btn-secondary',
    'title' => 'Désélectionner tout'
]);

echo html_writer::end_tag('div');
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

// ======================================================================
// TABLEAU DES CATÉGORIES
// ======================================================================

echo html_writer::tag('h3', '📂 Liste des catégories');

$categories_with_stats = category_manager::get_all_categories_with_stats();

echo html_writer::start_tag('div', ['class' => 'qd-table-wrapper']);
echo html_writer::start_tag('table', ['class' => 'qd-table']);

// En-tête du tableau
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
echo html_writer::tag('th', html_writer::checkbox('select-all', 1, false, '', ['id' => 'select-all']));
echo html_writer::tag('th', 'ID', ['class' => 'sortable', 'data-column' => 'id']);
echo html_writer::tag('th', 'Nom', ['class' => 'sortable', 'data-column' => 'name']);
echo html_writer::tag('th', 'Contexte', ['class' => 'sortable', 'data-column' => 'context']);
echo html_writer::tag('th', 'Parent', ['class' => 'sortable', 'data-column' => 'parent']);
echo html_writer::tag('th', 'Questions', ['class' => 'sortable', 'data-column' => 'questions']);
echo html_writer::tag('th', 'Sous-cat.', ['class' => 'sortable', 'data-column' => 'subcategories']);
echo html_writer::tag('th', 'Statut');
echo html_writer::tag('th', 'Actions');
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');

// Corps du tableau
echo html_writer::start_tag('tbody');

foreach ($categories_with_stats as $item) {
    $cat = $item->category;
    $stats = $item->stats;
    
    // Attributs data pour le filtrage et le tri
    $row_attrs = [
        'data-id' => $cat->id,
        'data-name' => format_string($cat->name),
        'data-context' => $cat->contextid,
        'data-parent' => $cat->parent,
        'data-questions' => $stats->visible_questions,
        'data-subcategories' => $stats->subcategories,
        'data-empty' => $stats->is_empty ? '1' : '0',
        'data-orphan' => $stats->is_orphan ? '1' : '0'
    ];
    
    echo html_writer::start_tag('tr', $row_attrs);
    
    // Checkbox
    echo html_writer::start_tag('td');
    echo html_writer::checkbox('category[]', $cat->id, false, '', ['class' => 'category-checkbox qd-checkbox']);
    echo html_writer::end_tag('td');
    
    // ID
    echo html_writer::tag('td', $cat->id);
    
    // Nom (avec lien vers la banque de questions)
    echo html_writer::start_tag('td');
    $questionbank_url = category_manager::get_question_bank_url($cat);
    if ($questionbank_url) {
        echo html_writer::link(
            $questionbank_url, 
            format_string($cat->name),
            ['title' => 'Voir dans la banque de questions', 'target' => '_blank']
        );
        echo ' ' . html_writer::tag('span', '🔗', ['style' => 'opacity: 0.5; font-size: 0.9em;']);
    } else {
        echo format_string($cat->name);
    }
    echo html_writer::end_tag('td');
    
    // Contexte
    echo html_writer::tag('td', $stats->context_name);
    
    // Parent
    echo html_writer::tag('td', $cat->parent ?: '-');
    
    // Questions
    $questions_display = $stats->visible_questions;
    if ($stats->total_questions > $stats->visible_questions) {
        $questions_display .= " (+{$stats->total_questions} cachées)";
    }
    echo html_writer::tag('td', $questions_display);
    
    // Sous-catégories
    echo html_writer::tag('td', $stats->subcategories);
    
    // Statut
    echo html_writer::start_tag('td');
    if ($stats->is_protected) {
        echo html_writer::tag('span', '🛡️ PROTÉGÉE', [
            'class' => 'qd-badge', 
            'style' => 'background: #5bc0de; color: white; font-weight: bold;',
            'title' => $stats->protection_reason
        ]);
        echo '<br><small style="color: #666;">' . $stats->protection_reason . '</small>';
    }
    if ($stats->is_empty) {
        echo html_writer::tag('span', 'Vide', ['class' => 'qd-badge qd-badge-empty']);
    }
    if ($stats->is_orphan) {
        echo ' ' . html_writer::tag('span', 'Orpheline', ['class' => 'qd-badge qd-badge-orphan']);
    }
    if (!$stats->is_empty && !$stats->is_orphan && !$stats->is_protected) {
        echo html_writer::tag('span', 'OK', ['class' => 'qd-badge qd-badge-ok']);
    }
    echo html_writer::end_tag('td');
    
    // Actions
    echo html_writer::start_tag('td');
    echo html_writer::start_tag('div', ['class' => 'qd-actions']);
    
    // Bouton voir dans la banque
    $questionbank_url = category_manager::get_question_bank_url($cat);
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
    
    // Bouton supprimer (seulement si vide ET NON protégée)
    if ($stats->is_empty && !$stats->is_protected) {
        $deleteurl = new moodle_url('/local/question_diagnostic/actions/delete.php', [
            'id' => $cat->id,
            'sesskey' => sesskey()
        ]);
        echo html_writer::link($deleteurl, '🗑️ Supprimer', ['class' => 'qd-btn qd-btn-delete']);
    } else if ($stats->is_protected) {
        echo html_writer::tag('span', '🛡️ Protégée', [
            'class' => 'qd-btn',
            'style' => 'background: #d9edf7; color: #31708f; cursor: not-allowed; opacity: 0.6;',
            'title' => $stats->protection_reason
        ]);
    }
    
    // Bouton fusionner
    echo html_writer::tag('a', '🔀 Fusionner', [
        'href' => '#',
        'class' => 'qd-btn qd-btn-merge merge-btn',
        'data-id' => $cat->id,
        'data-name' => format_string($cat->name)
    ]);
    
    echo html_writer::end_tag('div');
    echo html_writer::end_tag('td');
    
    echo html_writer::end_tag('tr');
}

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');
echo html_writer::end_tag('div'); // fin qd-table-wrapper

// ======================================================================
// MODAL DE FUSION
// ======================================================================

echo html_writer::start_tag('div', ['class' => 'qd-modal', 'id' => 'merge-modal']);
echo html_writer::start_tag('div', ['class' => 'qd-modal-content']);

echo html_writer::start_tag('div', ['class' => 'qd-modal-header']);
echo html_writer::tag('h3', '🔀 Fusionner des catégories', ['class' => 'qd-modal-title']);
echo html_writer::tag('button', '&times;', ['class' => 'qd-modal-close']);
echo html_writer::end_tag('div');

echo html_writer::tag('div', '', ['class' => 'qd-modal-body']);
echo html_writer::tag('div', '', ['class' => 'qd-modal-footer']);

echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

// ======================================================================
// Pied de page Moodle standard
// ======================================================================
echo $OUTPUT->footer();

