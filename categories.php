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

// ======================================================================
// GESTION DES PARAMÈTRES DE FILTRAGE
// ======================================================================

// Récupérer les paramètres de filtrage
$view_type = optional_param('view_type', 'questions', PARAM_TEXT);
$course_category_filter = optional_param('course_category', 'all', PARAM_INT);

// Validation des paramètres
if (!in_array($view_type, ['questions', 'all'])) {
    $view_type = 'questions';
}

if ($course_category_filter !== 'all' && $course_category_filter <= 0) {
    $course_category_filter = 'all';
}

// Ajouter les CSS et JavaScript personnalisés
$PAGE->requires->css('/local/question_diagnostic/styles/main.css');
$PAGE->requires->js('/local/question_diagnostic/scripts/progress.js', true);  // 🆕 v1.9.41 : Barres de progression
$PAGE->requires->js('/local/question_diagnostic/scripts/main.js', true);

// ======================================================================
// Section d'en-tête Moodle standard.
echo $OUTPUT->header();

// Afficher le badge de version
echo local_question_diagnostic_render_version_badge();

// Alerte sécurité pour les suppressions
echo html_writer::start_div('alert alert-warning', ['style' => 'margin-bottom: 20px; border-left: 4px solid #d9534f;']);
echo '<strong>🛡️ ATTENTION</strong> : Cette page permet de supprimer des catégories. ';
echo 'Consultez la ';
echo html_writer::link(
    new moodle_url('/local/question_diagnostic/help_database_impact.php'),
    'documentation sur l\'impact base de données',
    ['target' => '_blank', 'style' => 'font-weight: bold; text-decoration: underline;']
);
echo ' pour les procédures de backup avant toute suppression.';
echo html_writer::end_div();

// 🆕 v1.9.44 : Lien retour hiérarchique
echo html_writer::start_tag('div', ['style' => 'margin-bottom: 20px;']);
echo local_question_diagnostic_render_back_link('categories.php');
echo html_writer::end_tag('div');

// ======================================================================
// STATISTIQUES GLOBALES (Dashboard) - Calculées après détection du type de vue
// ======================================================================

// Calculer les statistiques selon le type de vue et le filtre
if ($view_type === 'all') {
    // Vue étendue : statistiques pour toutes les catégories
    $all_categories = category_manager::get_all_site_categories_with_stats();
    
    // Calculer les statistiques manuellement pour éviter les problèmes avec array_column
    $total_questions = 0;
    $empty_categories = 0;
    $orphan_categories = 0;
    $duplicate_categories = 0;
    $total_protected = 0;
    
    foreach ($all_categories as $cat) {
        $total_questions += isset($cat->total_questions) ? $cat->total_questions : 0;
        if (isset($cat->status) && $cat->status === 'empty') $empty_categories++;
        if (isset($cat->status) && $cat->status === 'orphan') $orphan_categories++;
        if (isset($cat->is_duplicate) && $cat->is_duplicate) $duplicate_categories++;
        if (isset($cat->is_protected) && $cat->is_protected) $total_protected++;
    }
    
    $globalstats = (object)[
        'total_categories' => count($all_categories),
        'total_questions' => $total_questions,
        'empty_categories' => $empty_categories,
        'orphan_categories' => $orphan_categories,
        'duplicate_categories' => $duplicate_categories,
        'total_protected' => $total_protected
    ];
} else {
    // Vue normale : statistiques pour les catégories de questions uniquement
    if ($course_category_filter !== 'all') {
        // Filtrer par catégorie de cours spécifique
        $filtered_categories = local_question_diagnostic_get_question_categories_by_course_category($course_category_filter);
        
        // Calculer les statistiques pour les catégories filtrées
        $total_questions = 0;
        $empty_categories = 0;
        $orphan_categories = 0;
        $duplicate_categories = 0;
        $total_protected = 0;
        
        foreach ($filtered_categories as $cat) {
            $total_questions += isset($cat->total_questions) ? $cat->total_questions : 0;
            if (isset($cat->status) && $cat->status === 'empty') $empty_categories++;
            if (isset($cat->status) && $cat->status === 'orphan') $orphan_categories++;
            if (isset($cat->is_duplicate) && $cat->is_duplicate) $duplicate_categories++;
            if (isset($cat->is_protected) && $cat->is_protected) $total_protected++;
        }
        
        $globalstats = (object)[
            'total_categories' => count($filtered_categories),
            'total_questions' => $total_questions,
            'empty_categories' => $empty_categories,
            'orphan_categories' => $orphan_categories,
            'duplicate_categories' => $duplicate_categories,
            'total_protected' => $total_protected
        ];
    } else {
        // Pas de filtre par catégorie de cours
        $globalstats = category_manager::get_global_stats();
    }
}

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
if (isset($globalstats->total_protected) && $globalstats->total_protected > 0) {
    echo html_writer::start_tag('div', ['class' => 'qd-card', 'style' => 'border: 2px solid #5bc0de;']);
    echo html_writer::tag('div', 'Catégories Protégées', ['class' => 'qd-card-title']);
    echo html_writer::tag('div', $globalstats->total_protected, ['class' => 'qd-card-value']);
    echo html_writer::tag('div', '🛡️ 3 types de protection (non supprimables)', ['class' => 'qd-card-subtitle']);
    echo html_writer::end_tag('div');
}

echo html_writer::end_tag('div'); // fin dashboard

// ======================================================================
// AVERTISSEMENT CATÉGORIES PROTÉGÉES
// ======================================================================

if (isset($globalstats->total_protected) && $globalstats->total_protected > 0) {
    echo html_writer::start_div('alert alert-info', ['style' => 'margin: 20px 0; border-left: 4px solid #5bc0de;']);
    echo '<strong>🛡️ PROTECTIONS ACTIVES</strong><br>';
    echo 'Le plugin protège automatiquement <strong>' . $globalstats->total_protected . ' catégorie(s)</strong> qui ne peuvent pas être supprimées :<br>';
    echo '<ul style="margin-top: 10px; margin-bottom: 5px;">';
    
    if (isset($globalstats->protected_default) && $globalstats->protected_default > 0) {
        echo '<li>📌 <strong>' . $globalstats->protected_default . '</strong> catégorie(s) "<strong>Default for...</strong>" (créées par Moodle)</li>';
    }
    
    if (isset($globalstats->protected_root_all) && $globalstats->protected_root_all > 0) {
        echo '<li>📂 <strong>' . $globalstats->protected_root_all . '</strong> catégorie(s) <strong>racine (top-level)</strong> (parent=0, toutes protégées)</li>';
    } else if (isset($globalstats->protected_root_courses) && $globalstats->protected_root_courses > 0) {
        echo '<li>📂 <strong>' . $globalstats->protected_root_courses . '</strong> catégorie(s) <strong>racine de cours</strong> (parent=0)</li>';
    }
    
    if (isset($globalstats->protected_with_info) && $globalstats->protected_with_info > 0) {
        echo '<li>📝 <strong>' . $globalstats->protected_with_info . '</strong> catégorie(s) avec <strong>description</strong> (usage documenté)</li>';
    }
    
    echo '</ul>';
    echo '<p style="margin-top: 15px;"><em>Ces protections évitent de casser la structure de votre Moodle.</em></p>';
    echo '<p style="margin-top: 10px;"><strong>💡 Conseil :</strong> Utilisez ';
    echo html_writer::link(
        new moodle_url('/local/question_diagnostic/quick_check_categories.php'),
        'quick_check_categories.php',
        ['style' => 'font-weight: bold; text-decoration: underline;']
    );
    echo ' pour voir le détail des catégories protégées.</p>';
    echo html_writer::end_div();
}

// ======================================================================
// BARRE D'ACTIONS ET EXPORT
// ======================================================================

echo html_writer::start_tag('div', ['style' => 'margin: 20px 0; display: flex; gap: 10px; flex-wrap: wrap;']);

// 🆕 v1.10.2 : Bouton de nettoyage global des catégories
$cleanup_all_url = new moodle_url('/local/question_diagnostic/actions/cleanup_all_categories.php', [
    'preview' => 1,
    'sesskey' => sesskey()
]);
echo html_writer::link(
    $cleanup_all_url, 
    '🧹 ' . get_string('cleanup_all_categories', 'local_question_diagnostic'), 
    [
        'class' => 'btn btn-warning btn-lg',
        'title' => get_string('cleanup_all_categories_desc', 'local_question_diagnostic'),
        'style' => 'font-weight: bold;'
    ]
);

$exporturl = new moodle_url('/local/question_diagnostic/actions/export.php', [
    'type' => 'csv',
    'sesskey' => sesskey()
]);
echo html_writer::link($exporturl, '📥 ' . get_string('export', 'local_question_diagnostic'), ['class' => 'btn btn-success']);

// 🆕 v1.9.35 : Lien vers le centre d'aide
echo html_writer::link(
    new moodle_url('/local/question_diagnostic/help.php'),
    '📚 Centre d\'Aide',
    ['class' => 'btn btn-info']
);

echo html_writer::end_tag('div');

// ======================================================================
// AIDE POUR LES OPÉRATIONS PAR LOT
// ======================================================================

echo html_writer::start_div('alert alert-info', ['style' => 'margin: 20px 0; border-left: 4px solid #667eea;']);
echo '<strong>💡 ASTUCE : Opérations par lot</strong><br>';
echo 'Cochez une ou plusieurs catégories dans le tableau ci-dessous pour faire apparaître la barre d\'actions groupées. ';
echo 'Vous pourrez alors :<br>';
echo '<ul style="margin-top: 10px; margin-bottom: 5px;">';
echo '<li>🗑️ <strong>Supprimer en masse</strong> les catégories vides sélectionnées</li>';
echo '<li>📤 <strong>Exporter en CSV</strong> uniquement les catégories sélectionnées</li>';
echo '<li>❌ <strong>Annuler</strong> la sélection en un clic</li>';
echo '</ul>';
echo html_writer::end_div();

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
echo html_writer::tag('option', 'Sans questions ni sous-catégories (supprimables)', ['value' => 'deletable']);
echo html_writer::tag('option', get_string('empty', 'local_question_diagnostic'), ['value' => 'empty']);
echo html_writer::tag('option', 'Doublons', ['value' => 'duplicate']);
echo html_writer::tag('option', get_string('orphan', 'local_question_diagnostic'), ['value' => 'orphan']);
echo html_writer::tag('option', get_string('ok', 'local_question_diagnostic'), ['value' => 'ok']);
echo html_writer::end_tag('select');
echo html_writer::end_tag('div');

// Filtre par contexte
echo html_writer::start_tag('div', ['class' => 'qd-filter-group']);
echo html_writer::tag('label', get_string('context', 'local_question_diagnostic'), ['for' => 'filter-context']);
echo html_writer::start_tag('select', ['id' => 'filter-context', 'class' => 'form-control']);
echo html_writer::tag('option', 'Tous', ['value' => 'all']);

// Récupérer les contextes uniques avec noms enrichis
global $DB;
$contexts = $DB->get_records_sql("
    SELECT DISTINCT qc.contextid
    FROM {question_categories} qc
    LEFT JOIN {context} ctx ON ctx.id = qc.contextid
    WHERE ctx.id IS NOT NULL
    ORDER BY qc.contextid
");

$context_options = [];
foreach ($contexts as $ctx_record) {
    try {
        $context_details = local_question_diagnostic_get_context_details($ctx_record->contextid);
        $label = $context_details->context_name;
        
        // Ajouter le nom du cours si disponible
        if (!empty($context_details->course_name)) {
            $label = $context_details->course_name . ' (' . $context_details->context_name . ')';
        }
        
        $context_options[$ctx_record->contextid] = $label;
    } catch (Exception $e) {
        // En cas d'erreur, afficher juste l'ID
        $context_options[$ctx_record->contextid] = "Context ID: {$ctx_record->contextid}";
    }
}

// Trier par label
asort($context_options);

// Afficher les options
foreach ($context_options as $contextid => $label) {
    echo html_writer::tag('option', $label, ['value' => $contextid]);
}
echo html_writer::end_tag('select');
echo html_writer::end_tag('div');

// 🆕 v1.11.3 : Filtre par type de catégories
echo html_writer::start_tag('div', ['class' => 'qd-filter-group']);
echo html_writer::tag('label', 'Type de catégories', ['for' => 'filter-type']);
echo html_writer::start_tag('select', ['id' => 'filter-type', 'class' => 'form-control']);
echo html_writer::tag('option', 'Questions uniquement (actuel)', ['value' => 'questions', 'selected' => ($view_type === 'questions')]);
echo html_writer::tag('option', 'Toutes les catégories (questions + cours)', ['value' => 'all', 'selected' => ($view_type === 'all')]);
echo html_writer::end_tag('select');
echo html_writer::end_tag('div');

// 🆕 v1.11.5 : Filtre par catégorie de cours
echo html_writer::start_tag('div', ['class' => 'qd-filter-group']);
echo html_writer::tag('label', get_string('course_category_filter', 'local_question_diagnostic'), ['for' => 'filter-course-category']);
echo html_writer::start_tag('select', ['id' => 'filter-course-category', 'class' => 'form-control']);
echo html_writer::tag('option', get_string('all_course_categories', 'local_question_diagnostic'), ['value' => 'all', 'selected' => ($course_category_filter === 'all')]);

// Récupérer toutes les catégories de cours
$course_categories = local_question_diagnostic_get_course_categories();

foreach ($course_categories as $course_cat) {
    $label = $course_cat->formatted_name;
    if ($course_cat->course_count > 0) {
        $label .= ' (' . $course_cat->course_count . ' cours)';
    }
    $selected = ($course_category_filter == $course_cat->id) ? 'selected' : '';
    echo html_writer::tag('option', $label, ['value' => $course_cat->id, 'selected' => $selected]);
}

echo html_writer::end_tag('select');
echo html_writer::end_tag('div');

echo html_writer::end_tag('div'); // fin qd-filters-row

echo html_writer::tag('div', '', ['id' => 'filter-stats', 'style' => 'margin-top: 10px; font-size: 14px; color: #666;']);

echo html_writer::end_tag('div'); // fin qd-filters

// ======================================================================
// BARRE D'ACTIONS GROUPÉES (apparaît lors de la sélection)
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

// Utiliser la variable déjà calculée plus haut
if ($view_type === 'all') {
    // Les catégories sont déjà récupérées dans $all_categories pour les statistiques
    // Convertir au format attendu par l'interface
    $categories_with_stats = [];
    foreach ($all_categories as $cat) {
        $categories_with_stats[] = (object)[
            'category' => $cat,
            'stats' => $cat // Les stats sont déjà intégrées dans l'objet unifié
        ];
    }
    
    echo html_writer::start_div('alert alert-info', ['style' => 'margin: 10px 0;']);
    echo html_writer::tag('strong', '🔍 Vue étendue activée : ');
    echo 'Affichage de toutes les catégories du site (questions + cours). ';
    echo html_writer::link(
        new moodle_url('/local/question_diagnostic/categories.php', ['type' => 'questions']),
        'Revenir à la vue questions uniquement',
        ['class' => 'btn btn-sm btn-secondary ml-2']
    );
    echo html_writer::end_div();
} else {
    // Vue normale : catégories de questions uniquement
    if ($course_category_filter !== 'all') {
        // Filtrer par catégorie de cours spécifique
        $categories_with_stats = local_question_diagnostic_get_question_categories_by_course_category($course_category_filter);
        
        // Ajouter un message informatif
        $course_category_name = '';
        foreach ($course_categories as $cat) {
            if ($cat->id == $course_category_filter) {
                $course_category_name = $cat->formatted_name;
                break;
            }
        }
        
        echo html_writer::start_div('alert alert-info', ['style' => 'margin: 20px 0; border-left: 4px solid #17a2b8;']);
        echo '<strong>' . get_string('filter_active_course_category', 'local_question_diagnostic') . '</strong><br>';
        echo get_string('course_category_filter_info', 'local_question_diagnostic') . ' : <strong>' . $course_category_name . '</strong><br>';
        echo html_writer::link(
            new moodle_url('/local/question_diagnostic/categories.php', ['course_category' => 'all']),
            get_string('show_all_course_categories', 'local_question_diagnostic'),
            ['class' => 'btn btn-sm btn-secondary ml-2']
        );
        echo html_writer::end_div();
    } else {
        // Pas de filtre par catégorie de cours
        $categories_with_stats = category_manager::get_all_categories_with_stats();
    }
}

echo html_writer::start_tag('div', ['class' => 'qd-table-wrapper']);
echo html_writer::start_tag('table', ['class' => 'qd-table']);

// En-tête du tableau
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
echo html_writer::tag('th', html_writer::checkbox('select-all', 1, false, '', ['id' => 'select-all']));
echo html_writer::tag('th', 'ID', ['class' => 'sortable', 'data-column' => 'id']);
echo html_writer::tag('th', 'Type', ['class' => 'sortable', 'data-column' => 'type', 'title' => 'Type de catégorie']);
echo html_writer::tag('th', 'Nom', ['class' => 'sortable', 'data-column' => 'name']);
echo html_writer::tag('th', 'Contexte', ['class' => 'sortable', 'data-column' => 'context']);
echo html_writer::tag('th', 'Parent', ['class' => 'sortable', 'data-column' => 'parent']);
echo html_writer::tag('th', 'Questions', ['class' => 'sortable', 'data-column' => 'questions']);
echo html_writer::tag('th', 'Cours', ['class' => 'sortable', 'data-column' => 'courses', 'title' => 'Nombre de cours (pour catégories de cours)']);
echo html_writer::tag('th', 'Sous-cat.', ['class' => 'sortable', 'data-column' => 'subcategories']);
echo html_writer::tag('th', 'Statut', ['class' => 'sortable', 'data-column' => 'status']);
echo html_writer::tag('th', '🗑️ Supprimable', ['class' => 'sortable', 'data-column' => 'deletable', 'title' => 'Peut-on supprimer cette catégorie ?']);
echo html_writer::tag('th', 'Actions');
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');

// Corps du tableau
echo html_writer::start_tag('tbody');

foreach ($categories_with_stats as $item) {
    // 🆕 v1.11.4 : Gérer les deux formats (vue normale vs vue étendue)
    if (isset($item->category) && isset($item->stats)) {
        // Format normal : {category: obj, stats: obj}
        $cat = $item->category;
        $stats = $item->stats;
    } else {
        // Format étendu : objet unifié avec toutes les propriétés
        $cat = $item;
        $stats = $item;
    }
    
    // Déterminer le statut principal pour le tri (priorité)
    // Ordre de priorité : Protégée (5) > Orpheline (4) > Doublon (3) > Vide (2) > OK (1)
    $status_priority = 1; // OK par défaut
    if ($stats->is_protected) {
        $status_priority = 5;
    } else if ($stats->is_orphan) {
        $status_priority = 4;
    } else if (isset($stats->is_duplicate) && $stats->is_duplicate) {
        $status_priority = 3;
    } else if ($stats->is_empty) {
        $status_priority = 2;
    }
    
    // Attributs data pour le filtrage et le tri
    $can_delete = $stats->is_empty && !$stats->is_protected;
    
    // 🆕 v1.11.3 : Gérer les deux types de catégories
    $category_type = isset($cat->category_type) ? $cat->category_type : 'question';
    $course_count = isset($cat->course_count) ? $cat->course_count : 0;
    
    $row_attrs = [
        'data-id' => $cat->id,
        'data-name' => format_string($cat->name),
        'data-type' => $category_type,
        'data-context' => $cat->contextid ?? '',
        'data-parent' => $cat->parent,
        'data-questions' => $stats->total_questions ?? 0,
        'data-visible-questions' => $stats->visible_questions ?? 0,
        'data-courses' => $course_count,
        'data-subcategories' => $stats->subcategories,
        'data-empty' => $stats->is_empty ? '1' : '0',
        'data-orphan' => $stats->is_orphan ? '1' : '0',
        'data-duplicate' => (isset($stats->is_duplicate) && $stats->is_duplicate) ? '1' : '0',
        'data-protected' => $stats->is_protected ? '1' : '0',
        'data-status' => $status_priority,
        'data-deletable' => $can_delete ? '1' : '0'
    ];
    
    // Débug : forcer les attributs si nécessaire
    if (!isset($row_attrs['data-empty'])) {
        $row_attrs['data-empty'] = '0';
    }
    if (!isset($row_attrs['data-orphan'])) {
        $row_attrs['data-orphan'] = '0';
    }
    if (!isset($row_attrs['data-duplicate'])) {
        $row_attrs['data-duplicate'] = '0';
    }
    if (!isset($row_attrs['data-protected'])) {
        $row_attrs['data-protected'] = '0';
    }
    
    echo html_writer::start_tag('tr', $row_attrs);
    
    // Checkbox
    echo html_writer::start_tag('td');
    echo html_writer::checkbox('category[]', $cat->id, false, '', ['class' => 'category-checkbox qd-checkbox']);
    echo html_writer::end_tag('td');
    
    // ID
    echo html_writer::tag('td', $cat->id);
    
    // 🆕 v1.11.3 : Type de catégorie
    echo html_writer::start_tag('td');
    if (isset($cat->category_type) && $cat->category_type === 'course') {
        echo html_writer::tag('span', '📚', ['title' => 'Catégorie de cours']);
    } else {
        echo html_writer::tag('span', '❓', ['title' => 'Catégorie de questions']);
    }
    echo html_writer::end_tag('td');
    
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
    
    // Contexte (avec tooltip si cours/module disponible)
    echo html_writer::start_tag('td');
    $context_display = $stats->context_name;
    $tooltip_parts = [];
    if (!empty($stats->course_name)) {
        $tooltip_parts[] = '📚 Cours : ' . $stats->course_name;
    }
    if (!empty($stats->module_name)) {
        $tooltip_parts[] = '📝 Module : ' . $stats->module_name;
    }
    $tooltip = !empty($tooltip_parts) ? implode("\n", $tooltip_parts) : '';
    
    if ($tooltip) {
        echo html_writer::tag('span', $context_display, [
            'title' => $tooltip,
            'style' => 'cursor: help; border-bottom: 1px dotted #666;'
        ]);
    } else {
        echo $context_display;
    }
    echo html_writer::end_tag('td');
    
    // Parent
    echo html_writer::tag('td', $cat->parent ?: '-');
    
    // Questions
    $questions_display = $stats->visible_questions ?? 0;
    if (($stats->total_questions ?? 0) > ($stats->visible_questions ?? 0)) {
        $questions_display .= " (+{$stats->total_questions} cachées)";
    }
    echo html_writer::tag('td', $questions_display);
    
    // 🆕 v1.11.3 : Cours (pour les catégories de cours)
    echo html_writer::start_tag('td');
    if (isset($cat->category_type) && $cat->category_type === 'course') {
        echo isset($cat->course_count) ? $cat->course_count : 0;
    } else {
        echo '-';
    }
    echo html_writer::end_tag('td');
    
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
    if (isset($stats->is_duplicate) && $stats->is_duplicate) {
        echo ' ' . html_writer::tag('span', 'Doublon', ['class' => 'qd-badge qd-badge-warning']);
    }
    if ($stats->is_orphan) {
        echo ' ' . html_writer::tag('span', 'Orpheline', ['class' => 'qd-badge qd-badge-orphan']);
    }
    if (!$stats->is_empty && !$stats->is_orphan && !$stats->is_protected && (!isset($stats->is_duplicate) || !$stats->is_duplicate)) {
        echo html_writer::tag('span', 'OK', ['class' => 'qd-badge qd-badge-ok']);
    }
    echo html_writer::end_tag('td');
    
    // 🆕 Colonne "Supprimable" avec raisons détaillées
    echo html_writer::start_tag('td');
    
    // Déterminer si supprimable
    $can_delete = $stats->is_empty && !$stats->is_protected;
    
    if ($can_delete) {
        // SUPPRIMABLE
        echo html_writer::tag('span', '✅ OUI', [
            'class' => 'qd-badge',
            'style' => 'background: #28a745; color: white; font-weight: bold;'
        ]);
    } else {
        // NON SUPPRIMABLE - Afficher la raison principale
        echo html_writer::tag('span', '❌ NON', [
            'class' => 'qd-badge',
            'style' => 'background: #dc3545; color: white; font-weight: bold;'
        ]);
        
        // Afficher les raisons détaillées
        echo '<br>';
        $reasons = [];
        
        if ($stats->is_protected) {
            $reasons[] = html_writer::tag('small', '🛡️ ' . $stats->protection_reason, [
                'style' => 'display: block; color: #5bc0de; margin-top: 3px;'
            ]);
        }
        if ($stats->total_questions > 0) {
            $reasons[] = html_writer::tag('small', '📚 ' . $stats->total_questions . ' question(s)', [
                'style' => 'display: block; color: #f0ad4e; margin-top: 3px;'
            ]);
        }
        if ($stats->subcategories > 0) {
            $reasons[] = html_writer::tag('small', '📂 ' . $stats->subcategories . ' sous-catégorie(s)', [
                'style' => 'display: block; color: #f0ad4e; margin-top: 3px;'
            ]);
        }
        
        echo implode('', $reasons);
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
    
    // 🆕 v1.9.36 : Bouton déplacer (si non protégée)
    if (!$stats->is_protected) {
        echo html_writer::tag('a', '📦 Déplacer', [
            'href' => '#',
            'class' => 'qd-btn qd-btn-move move-btn',
            'data-id' => $cat->id,
            'data-name' => format_string($cat->name),
            'data-contextid' => $cat->contextid,
            'style' => 'background: #5bc0de; color: white;'
        ]);
    }
    
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
// 🆕 v1.9.36 : MODAL DE DÉPLACEMENT
// ======================================================================

echo html_writer::start_tag('div', ['class' => 'qd-modal', 'id' => 'move-modal']);
echo html_writer::start_tag('div', ['class' => 'qd-modal-content']);

echo html_writer::start_tag('div', ['class' => 'qd-modal-header']);
echo html_writer::tag('h3', '📦 Déplacer une catégorie', ['class' => 'qd-modal-title']);
echo html_writer::tag('button', '&times;', ['class' => 'qd-modal-close', 'onclick' => 'closeMoveModal()']);
echo html_writer::end_tag('div');

echo html_writer::tag('div', '', ['class' => 'qd-modal-body', 'id' => 'move-modal-body']);
echo html_writer::tag('div', '', ['class' => 'qd-modal-footer', 'id' => 'move-modal-footer']);

echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

// ======================================================================
// 🆕 v1.9.36 : JAVASCRIPT MODAL DÉPLACEMENT
// ======================================================================

echo html_writer::start_tag('script');
?>
// Modal de déplacement
function openMoveModal(categoryId, categoryName, contextId) {
    const modal = document.getElementById('move-modal');
    const body = document.getElementById('move-modal-body');
    const footer = document.getElementById('move-modal-footer');
    
    // Récupérer toutes les catégories du même contexte (pour liste des parents possibles)
    const allCategories = <?php echo json_encode($categories_with_stats); ?>;
    const sameContext = allCategories.filter(c => c.category.contextid == contextId && c.category.id != categoryId);
    
    // Construire le contenu
    let html = '<p><strong>Catégorie à déplacer :</strong> ' + categoryName + ' (ID: ' + categoryId + ')</p>';
    html += '<p><strong>Choisir le nouveau parent :</strong></p>';
    html += '<form id="move-form" method="post" action="<?php echo new moodle_url('/local/question_diagnostic/actions/move.php'); ?>">';
    html += '<input type="hidden" name="id" value="' + categoryId + '">';
    html += '<input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">';
    html += '<select name="parent" class="form-control" style="width: 100%; padding: 8px; margin: 10px 0;">';
    html += '<option value="0">→ Racine (aucun parent)</option>';
    
    sameContext.forEach(function(item) {
        if (!item.stats.is_protected || item.category.parent == 0) {
            html += '<option value="' + item.category.id + '">';
            html += item.category.name + ' (ID: ' + item.category.id + ')';
            if (item.category.parent == 0) {
                html += ' - RACINE';
            }
            html += '</option>';
        }
    });
    
    html += '</select>';
    html += '<p style="margin-top: 15px; color: #666; font-size: 14px;"><em>💡 Seules les catégories du même contexte sont affichées.</em></p>';
    html += '</form>';
    
    body.innerHTML = html;
    
    // Boutons footer
    footer.innerHTML = '<button type="button" class="btn btn-secondary" onclick="closeMoveModal()">Annuler</button> ' +
                       '<button type="submit" form="move-form" class="btn btn-primary">Déplacer</button>';
    
    modal.style.display = 'block';
}

function closeMoveModal() {
    document.getElementById('move-modal').style.display = 'none';
}

// Gestionnaires d'événements pour les boutons "Déplacer"
document.addEventListener('DOMContentLoaded', function() {
    const moveButtons = document.querySelectorAll('.move-btn');
    moveButtons.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const id = parseInt(this.dataset.id);
            const name = this.dataset.name;
            const contextId = parseInt(this.dataset.contextid);
            openMoveModal(id, name, contextId);
        });
    });
    
    // Fermer le modal si clic en dehors
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('move-modal');
        if (event.target === modal) {
            closeMoveModal();
        }
    });
    
    // 🆕 v1.11.3 : Gestion du changement de type de catégories
    const filterType = document.getElementById('filter-type');
    if (filterType) {
        filterType.addEventListener('change', function() {
            const selectedType = this.value;
            const currentUrl = new URL(window.location);
            
            if (selectedType === 'all') {
                currentUrl.searchParams.set('type', 'all');
            } else {
                currentUrl.searchParams.delete('type');
            }
            
            // Rediriger vers la nouvelle URL
            window.location.href = currentUrl.toString();
        });
        
        // Pré-sélectionner la valeur actuelle
        const urlParams = new URLSearchParams(window.location.search);
        const currentType = urlParams.get('type') || 'questions';
        filterType.value = currentType;
    }
});
<?php
echo html_writer::end_tag('script');

// ======================================================================
// Pied de page Moodle standard
// ======================================================================
echo $OUTPUT->footer();

