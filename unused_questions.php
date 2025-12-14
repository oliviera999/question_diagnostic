<?php
// ======================================================================
// Moodle Question Diagnostic - Questions inutilisées
// ======================================================================

// Inclure la configuration de Moodle.
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/question_analyzer.php');

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
$PAGE->set_url(new moodle_url('/local/question_diagnostic/unused_questions.php'));
$pagetitle = get_string('unused_questions_title', 'local_question_diagnostic');
$PAGE->set_title(get_string('unused_questions', 'local_question_diagnostic'));
$PAGE->set_heading(local_question_diagnostic_get_heading_with_version($pagetitle));
$PAGE->set_pagelayout('report');

// Ajouter les CSS et JavaScript personnalisés
$PAGE->requires->css('/local/question_diagnostic/styles/main.css');
$PAGE->requires->js('/local/question_diagnostic/scripts/main.js', true);
$PAGE->requires->js('/local/question_diagnostic/scripts/questions.js', true);

// ======================================================================
// Section d'en-tête Moodle standard.
echo $OUTPUT->header();

// Afficher le badge de version
echo local_question_diagnostic_render_version_badge();

// 🆕 Lien retour hiérarchique et bouton de purge de cache
echo html_writer::start_tag('div', ['style' => 'margin-bottom: 20px; display: flex; gap: 10px; align-items: center;']);
echo local_question_diagnostic_render_back_link('unused_questions.php');

// Traiter la purge de cache si demandée
$purgecache = optional_param('purgecache', 0, PARAM_INT);
if ($purgecache && confirm_sesskey()) {
    question_analyzer::purge_all_caches();
    redirect($PAGE->url, '✅ Cache purgé avec succès.', null, \core\output\notification::NOTIFY_SUCCESS);
}

// Bouton de purge de cache
$purgecache_url = new moodle_url($PAGE->url, ['purgecache' => 1, 'sesskey' => sesskey()]);
echo html_writer::link(
    $purgecache_url,
    '🔄 Purger le cache',
    [
        'class' => 'btn btn-warning',
        'title' => 'Vider le cache pour forcer le recalcul des statistiques'
    ]
);

// Lien vers le centre d'aide
echo html_writer::link(
    new moodle_url('/local/question_diagnostic/help.php'),
    '📚 Aide',
    ['class' => 'btn btn-outline-info']
);

echo html_writer::end_tag('div');

// ======================================================================
// INTRODUCTION ET MESSAGE D'INFORMATION
// ======================================================================

echo html_writer::tag('h2', '🗑️ ' . get_string('unused_questions_heading', 'local_question_diagnostic'));

echo html_writer::start_tag('div', ['class' => 'alert alert-info', 'style' => 'margin-bottom: 20px;']);
echo html_writer::tag('strong', '💡 Information : ');
echo get_string('unused_questions_info', 'local_question_diagnostic');
echo html_writer::end_tag('div');

// ======================================================================
// STATISTIQUES GLOBALES
// ======================================================================

echo html_writer::tag('h3', '📊 ' . get_string('statistics', 'local_question_diagnostic'));

// Charger les statistiques avec gestion d'erreurs
try {
    $globalstats = question_analyzer::get_global_stats(true, false);
} catch (Exception $e) {
    echo html_writer::start_tag('div', ['class' => 'alert alert-danger']);
    echo html_writer::tag('strong', '⚠️ Erreur : ');
    echo 'Impossible de charger les statistiques globales. ';
    echo html_writer::tag('p', 'Détails : ' . $e->getMessage(), ['style' => 'margin-top: 10px; font-size: 12px;']);
    echo html_writer::end_tag('div');
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::start_tag('div', ['class' => 'qd-dashboard']);

// Carte 1 : Total questions
echo html_writer::start_tag('div', ['class' => 'qd-card']);
echo html_writer::tag('div', get_string('total_questions_stats', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', $globalstats->total_questions, ['class' => 'qd-card-value']);
echo html_writer::tag('div', get_string('in_database', 'local_question_diagnostic'), ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

// Carte 2 : Questions utilisées
echo html_writer::start_tag('div', ['class' => 'qd-card success']);
echo html_writer::tag('div', get_string('questions_used', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', $globalstats->used_questions, ['class' => 'qd-card-value']);
echo html_writer::tag('div', get_string('in_quizzes_or_attempts', 'local_question_diagnostic'), ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

// Carte 3 : Questions inutilisées (focus principal)
echo html_writer::start_tag('div', ['class' => 'qd-card danger']);
echo html_writer::tag('div', get_string('questions_unused', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', $globalstats->unused_questions, ['class' => 'qd-card-value']);
echo html_writer::tag('div', get_string('never_used', 'local_question_diagnostic'), ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

echo html_writer::end_tag('div'); // fin dashboard

// ======================================================================
// BARRE D'ACTIONS ET EXPORT
// ======================================================================

echo html_writer::start_tag('div', ['style' => 'margin: 30px 0 20px 0; display: flex; gap: 10px; flex-wrap: wrap;']);

$exporturl = new moodle_url('/local/question_diagnostic/actions/export.php', [
    'type' => 'unused_questions_csv',
    'sesskey' => sesskey()
]);
echo html_writer::link($exporturl, '📥 ' . get_string('export_unused_csv', 'local_question_diagnostic'), ['class' => 'btn btn-success']);

echo html_writer::tag('button', '⚙️ ' . get_string('toggle_columns', 'local_question_diagnostic'), [
    'id' => 'toggle-columns-btn',
    'class' => 'btn btn-info',
    'onclick' => 'toggleColumnsPanel()'
]);

echo html_writer::end_tag('div');

// ======================================================================
// PANNEAU DE GESTION DES COLONNES
// ======================================================================

echo html_writer::start_tag('div', ['id' => 'columns-panel', 'class' => 'qd-columns-panel', 'style' => 'display: none;']);
echo html_writer::tag('h4', get_string('columns_to_display', 'local_question_diagnostic'), ['style' => 'margin-top: 0;']);

$columns = [
    'id' => 'ID',
    'name' => 'Nom',
    'type' => 'Type',
    'category' => 'Catégorie',
    'course' => 'Cours',
    'context' => 'Contexte',
    'creator' => 'Créateur',
    'created' => 'Date création',
    'modified' => 'Date modification',
    'visible' => 'Visible',
    'excerpt' => 'Extrait',
    'actions' => 'Actions'
];

echo html_writer::start_tag('div', ['class' => 'qd-columns-grid']);
foreach ($columns as $col_id => $col_name) {
    // Par défaut : afficher id, name, type, category, course, created, actions
    $checked = in_array($col_id, ['id', 'name', 'type', 'category', 'course', 'created', 'actions']);
    echo html_writer::start_tag('label', ['class' => 'qd-column-toggle', 'for' => 'column_' . $col_id]);
    echo html_writer::checkbox('column_' . $col_id, 1, $checked, ' ' . $col_name, [
        'id' => 'column_' . $col_id,
        'class' => 'column-toggle-checkbox',
        'data-column' => $col_id,
        'onchange' => 'toggleColumn(this)'
    ]);
    echo html_writer::end_tag('label');
}
echo html_writer::end_tag('div');

echo html_writer::end_tag('div');

// ======================================================================
// FILTRES ET RECHERCHE
// ======================================================================

echo html_writer::start_tag('div', ['class' => 'qd-filters', 'style' => 'margin-top: 30px;']);
echo html_writer::tag('h4', '🔍 Filtres et recherche', ['style' => 'margin-top: 0;']);

echo html_writer::start_tag('div', ['class' => 'qd-filters-row']);

// Recherche par nom/ID/texte
echo html_writer::start_tag('div', ['class' => 'qd-filter-group']);
echo html_writer::tag('label', 'Rechercher', ['for' => 'filter-search-unused']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'id' => 'filter-search-unused',
    'placeholder' => 'Nom, ID, cours, texte...',
    'class' => 'form-control'
]);
echo html_writer::end_tag('div');

// Filtre par type de question
echo html_writer::start_tag('div', ['class' => 'qd-filter-group']);
echo html_writer::tag('label', 'Type de question', ['for' => 'filter-type-unused']);
echo html_writer::start_tag('select', ['id' => 'filter-type-unused', 'class' => 'form-control']);
echo html_writer::tag('option', 'Tous', ['value' => 'all']);

// Récupérer les types uniques
$types_list = [];
if (isset($globalstats->by_type)) {
    foreach ($globalstats->by_type as $qtype => $count) {
        $types_list[$qtype] = $qtype . ' (' . $count . ')';
    }
    asort($types_list);
    foreach ($types_list as $qtype => $label) {
        echo html_writer::tag('option', $label, ['value' => $qtype]);
    }
}
echo html_writer::end_tag('select');
echo html_writer::end_tag('div');

// Filtre par visibilité
echo html_writer::start_tag('div', ['class' => 'qd-filter-group']);
echo html_writer::tag('label', 'Visibilité', ['for' => 'filter-visible-unused']);
echo html_writer::start_tag('select', ['id' => 'filter-visible-unused', 'class' => 'form-control']);
echo html_writer::tag('option', 'Toutes', ['value' => 'all']);
echo html_writer::tag('option', 'Visibles', ['value' => 'visible']);
echo html_writer::tag('option', 'Cachées', ['value' => 'hidden']);
echo html_writer::tag('option', 'Supprimées', ['value' => 'deleted']);
echo html_writer::end_tag('select');
echo html_writer::end_tag('div');

echo html_writer::end_tag('div'); // fin qd-filters-row

echo html_writer::tag('div', '', ['id' => 'filter-stats-unused', 'style' => 'margin-top: 10px; font-size: 14px; color: #666;']);

echo html_writer::end_tag('div'); // fin qd-filters

// ======================================================================
// TABLEAU DES QUESTIONS INUTILISÉES
// ======================================================================

echo html_writer::tag('h3', '📝 ' . get_string('unused_questions_list', 'local_question_diagnostic'), ['style' => 'margin-top: 30px;']);

// Pagination
$perpage = optional_param('perpage', 50, PARAM_INT);
$perpage = max(10, min($perpage, 500)); // Entre 10 et 500
$page = optional_param('page', 0, PARAM_INT);
$page = max(0, $page);
$offset = $page * $perpage;

echo html_writer::start_tag('div', ['class' => 'alert alert-warning', 'style' => 'margin-bottom: 15px;']);
echo '⚡ Par défaut, <strong>' . $perpage . ' questions inutilisées</strong> sont affichées par page. ';
echo 'Utilisez la pagination en bas de page pour naviguer.';
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['id' => 'loading-unused-questions', 'style' => 'text-align: center; padding: 40px;']);
echo html_writer::tag('p', '⏳ Chargement des questions inutilisées en cours...', ['style' => 'font-size: 16px;']);
echo html_writer::tag('p', 'Cela peut prendre quelques instants.', ['style' => 'font-size: 14px; color: #666;']);
echo html_writer::end_tag('div');

// Charger les questions inutilisées avec pagination
try {
    $unused_questions = question_analyzer::get_unused_questions($perpage, $offset);
    $total_unused = $globalstats->unused_questions;
    
    // Normaliser la structure : on veut une liste de vrais objets question.
    // (Compat si une méthode retourne (object)['question' => ...]).
    $unused_questions = array_values($unused_questions);
    $normalized_questions = [];
    foreach ($unused_questions as $item) {
        if (!is_object($item)) {
            continue;
        }
        if (isset($item->id) && !empty($item->id)) {
            $normalized_questions[] = $item;
        } elseif (isset($item->question) && is_object($item->question) && isset($item->question->id) && !empty($item->question->id)) {
            $normalized_questions[] = $item->question;
        }
    }
    $unused_questions = $normalized_questions;

    // 🆕 v1.9.55 : Récupérer les infos de versions (statut caché + nombre de versions) pour toutes les questions
    $question_ids = array_values(array_unique(array_map(function($q) { return (int)$q->id; }, $unused_questions)));
    $version_info_map = [];
    $usage_map = []; // 🆕 v1.9.57
    if (!empty($question_ids)) {
        $version_info_map = question_analyzer::get_questions_version_info_batch($question_ids);
        // 🆕 v1.9.57 : Charger aussi les infos d'usage pour distinguer cachée vs supprimée
        // Note : Normalement vide car ce sont des questions inutilisées, mais on charge pour cohérence
        $usage_map = question_analyzer::get_questions_usage_by_ids($question_ids);
    }
} catch (Exception $e) {
    echo html_writer::start_tag('script');
    echo "document.getElementById('loading-unused-questions').style.display = 'none';";
    echo html_writer::end_tag('script');
    
    echo html_writer::start_tag('div', ['class' => 'alert alert-danger']);
    echo html_writer::tag('strong', '⚠️ Erreur : ');
    echo 'Impossible de charger les questions inutilisées. ';
    echo html_writer::tag('p', 'Détails : ' . $e->getMessage(), ['style' => 'margin-top: 10px; font-size: 12px;']);
    echo html_writer::end_tag('div');
    echo $OUTPUT->footer();
    exit;
}

// Masquer le spinner de chargement
echo html_writer::start_tag('script');
echo "document.getElementById('loading-unused-questions').style.display = 'none';";
echo html_writer::end_tag('script');

// Message si aucune question inutilisée trouvée
if (empty($unused_questions)) {
    echo html_writer::start_tag('div', ['class' => 'alert alert-success', 'style' => 'margin: 30px 0; padding: 40px; text-align: center;']);
    echo html_writer::tag('h3', '✅ ' . get_string('no_unused_questions', 'local_question_diagnostic'), ['style' => 'margin-top: 0; color: #28a745;']);
    echo html_writer::tag('p', get_string('no_unused_questions_desc', 'local_question_diagnostic'), ['style' => 'font-size: 16px;']);
    echo html_writer::end_tag('div');
    echo $OUTPUT->footer();
    exit;
}

// Afficher le compteur
echo html_writer::start_tag('div', ['style' => 'margin-bottom: 15px; font-size: 14px; color: #666;']);
$from = $offset + 1;
$to = $offset + count($unused_questions);
if ($total_unused == 0) {
    $from = 0;
    $to = 0;
} else if ($to > $total_unused) {
    $to = $total_unused;
}
echo '📊 Affichage <strong>' . $from . '–' . $to . '</strong> (page ' . ($page + 1) . ') sur <strong>' . $total_unused . '</strong> au total.';
echo html_writer::end_tag('div');

// Bouton de suppression en masse (au-dessus du tableau)
echo html_writer::start_tag('div', ['id' => 'bulk-actions-container-unused', 'style' => 'margin-bottom: 15px; display: none;']);
echo html_writer::tag('button', '🗑️ Supprimer la sélection', [
    'id' => 'bulk-delete-unused-btn',
    'class' => 'btn btn-danger',
    'onclick' => 'bulkDeleteUnusedQuestions()',
    'style' => 'margin-right: 10px;'
]);
echo html_writer::tag('span', '0 question(s) sélectionnée(s)', [
    'id' => 'selection-count-unused',
    'style' => 'font-weight: bold; color: #666;'
]);
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'qd-table-wrapper']);
echo html_writer::start_tag('table', ['class' => 'qd-table qd-sortable-table', 'style' => 'width: 100%;', 'id' => 'unused-questions-table']);

// En-tête avec tri
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
echo html_writer::tag('th', '<input type="checkbox" id="select-all-unused" title="Tout sélectionner/désélectionner">', ['style' => 'width: 40px;']);
echo html_writer::tag('th', 'ID ▲▼', ['class' => 'sortable col-id', 'data-column' => 'id', 'style' => 'cursor: pointer;']);
echo html_writer::tag('th', 'Nom ▲▼', ['class' => 'sortable col-name', 'data-column' => 'name', 'style' => 'cursor: pointer;']);
echo html_writer::tag('th', 'Type ▲▼', ['class' => 'sortable col-type', 'data-column' => 'type', 'style' => 'cursor: pointer;']);
echo html_writer::tag('th', get_string('question_hidden_status', 'local_question_diagnostic') . ' ▲▼', ['class' => 'sortable col-visibility', 'data-column' => 'visibility', 'style' => 'cursor: pointer;', 'title' => 'Statut de visibilité de la question']);
echo html_writer::tag('th', get_string('question_version_count', 'local_question_diagnostic') . ' ▲▼', ['class' => 'sortable col-versions', 'data-column' => 'versions', 'style' => 'cursor: pointer;', 'title' => get_string('question_version_count_tooltip', 'local_question_diagnostic')]);
echo html_writer::tag('th', 'Catégorie ▲▼', ['class' => 'sortable col-category', 'data-column' => 'category', 'style' => 'cursor: pointer;']);
echo html_writer::tag('th', 'Cours ▲▼', ['class' => 'sortable col-course', 'data-column' => 'course', 'style' => 'cursor: pointer;']);
echo html_writer::tag('th', 'Contexte ▲▼', ['class' => 'sortable col-context', 'data-column' => 'context', 'style' => 'cursor: pointer; display: none;']);
echo html_writer::tag('th', 'Créateur ▲▼', ['class' => 'sortable col-creator', 'data-column' => 'creator', 'style' => 'cursor: pointer; display: none;']);
echo html_writer::tag('th', 'Créée le ▲▼', ['class' => 'sortable col-created', 'data-column' => 'created', 'style' => 'cursor: pointer;']);
echo html_writer::tag('th', 'Modifiée le ▲▼', ['class' => 'sortable col-modified', 'data-column' => 'modified', 'style' => 'cursor: pointer; display: none;']);
echo html_writer::tag('th', 'Visible ▲▼', ['class' => 'sortable col-visible', 'data-column' => 'visible', 'style' => 'cursor: pointer; display: none;']);
echo html_writer::tag('th', 'Extrait', ['class' => 'col-excerpt', 'style' => 'display: none;']);
echo html_writer::tag('th', 'Actions', ['class' => 'col-actions']);
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');

echo html_writer::start_tag('tbody');

// Récupérer les IDs pour charger les stats en batch
$deletability_map = question_analyzer::can_delete_questions_batch($question_ids);

// Afficher chaque question inutilisée
foreach ($unused_questions as $question) {
    // ⚡ Optimisation : on injecte $usage_map déjà chargé + on désactive la détection de doublons (inutile ici).
    $stats = question_analyzer::get_question_stats($question, $usage_map, null, false);
    
    // Vérifier si supprimable
    $can_delete_check = isset($deletability_map[$question->id]) ? $deletability_map[$question->id] : null;
    
    // 🆕 v1.9.55 : Récupérer les infos de versions pour cette question
    $version_info = isset($version_info_map[$question->id]) ? $version_info_map[$question->id] : (object)[
        'is_hidden' => false,
        'version_count' => 0,
        'status' => 'unknown'
    ];
    
    // 🆕 v1.9.57 : Statut de visibilité (visible/cachée/supprimée)
    $visibility_status = question_analyzer::get_question_visibility_status($question->id, $version_info, $usage_map);
    $is_visible = ($visibility_status === 'visible');

    // Extrait du texte de la question (sans HTML) - UTF-8 safe
    $plain = strip_tags($question->questiontext);
    $excerpt = core_text::substr($plain, 0, 150);
    if (core_text::strlen($plain) > 150) {
        $excerpt .= '...';
    }

    // Créateur (déjà calculé dans get_question_stats)
    $creator_name = isset($stats->creator_name) ? $stats->creator_name : 'Inconnu';

    // Texte simple pour la colonne optionnelle "Visible"
    if ($visibility_status === 'hidden') {
        $visible_text = '🙈 Cachée';
    } elseif ($visibility_status === 'deleted') {
        $visible_text = '🗑️ Supprimée';
    } else {
        $visible_text = '✅ Visible';
    }
    
    // Attributs data-* pour le tri et le filtrage
    $row_attrs = [
        'data-question-id' => $question->id,
        'data-id' => $question->id,
        'data-name' => format_string($question->name),
        'data-type' => $question->qtype,
        'data-visibility-status' => $visibility_status, // visible|hidden|deleted
        'data-visibility' => $version_info->is_hidden ? '0' : '1', // 🆕 v1.9.55
        'data-versions' => $version_info->version_count, // 🆕 v1.9.55
        'data-category' => isset($stats->category_name) ? $stats->category_name : 'N/A',
        'data-course' => isset($stats->course_name) ? strip_tags($stats->course_name) : '-',
        'data-context' => isset($stats->context_name) ? strip_tags($stats->context_name) : '-',
        'data-creator' => $creator_name,
        'data-created' => $question->timecreated,
        'data-modified' => $question->timemodified,
        // Utilisé par le filtre "Visibilité"
        'data-visible' => $is_visible ? '1' : '0',
        'data-excerpt' => $excerpt
    ];
    
    echo html_writer::start_tag('tr', $row_attrs);
    
    // Checkbox de sélection (uniquement pour questions supprimables)
    echo html_writer::start_tag('td', ['style' => 'text-align: center;']);
    if ($can_delete_check && $can_delete_check->can_delete) {
        echo '<input type="checkbox" class="unused-select-checkbox" value="' . $question->id . '" data-question-id="' . $question->id . '">';
    }
    echo html_writer::end_tag('td');
    
    echo html_writer::tag('td', $question->id, ['class' => 'col-id']);
    echo html_writer::tag('td', format_string($question->name), ['class' => 'col-name']);
    echo html_writer::tag('td', $question->qtype, ['class' => 'col-type']);
    
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
        'class' => 'col-visibility',
        'style' => $visibility_style . ' text-align: center;',
        'title' => $visibility_tooltip . ' (Statut: ' . $version_info->status . ')'
    ]);
    
    // 🆕 v1.9.55 : Colonne Nombre de versions
    $version_count_style = $version_info->version_count > 1 
        ? 'font-weight: bold; color: #0f6cbf;' 
        : 'color: #666;';
    echo html_writer::tag('td', $version_info->version_count, [
        'class' => 'col-versions',
        'style' => $version_count_style . ' text-align: center;',
        'title' => $version_info->version_count > 1 
            ? 'Cette question a ' . $version_info->version_count . ' versions' 
            : 'Version unique'
    ]);
    
    // Catégorie cliquable
    echo html_writer::start_tag('td', ['class' => 'col-category']);
    if (isset($stats->category_id) && $stats->category_id > 0 && isset($stats->context_id)) {
        $courseid = isset($stats->course_id) && $stats->course_id ? (int)$stats->course_id : (defined('SITEID') ? SITEID : 1);
        $cat_url = new moodle_url('/question/edit.php', [
            'courseid' => $courseid,
            'cat' => $stats->category_id . ',' . $stats->context_id
        ]);
        $category_display = html_writer::link($cat_url, format_string($stats->category_name), ['target' => '_blank', 'title' => 'Ouvrir la catégorie dans la banque de questions']);
        $category_display .= ' <span style="color: #666; font-size: 11px;">(ID: ' . $stats->category_id . ')</span>';
        echo $category_display;
    } else {
        echo 'N/A';
    }
    echo html_writer::end_tag('td');
    
    echo html_writer::tag('td', isset($stats->course_name) ? '📚 ' . $stats->course_name : '-', ['class' => 'col-course']);
    
    $context_display = isset($stats->context_name) ? $stats->context_name : '-';
    if (isset($stats->context_id) && $stats->context_id > 0) {
        $context_display .= ' <span style="color: #666; font-size: 10px;">(ID: ' . $stats->context_id . ')</span>';
    }
    echo html_writer::tag('td', $context_display, ['class' => 'col-context', 'style' => 'font-size: 12px; display: none;']);
    
    echo html_writer::tag('td', $creator_name, ['class' => 'col-creator', 'style' => 'display: none;']);
    echo html_writer::tag('td', userdate($question->timecreated, '%d/%m/%Y %H:%M'), ['class' => 'col-created']);
    echo html_writer::tag('td', userdate($question->timemodified, '%d/%m/%Y %H:%M'), ['class' => 'col-modified', 'style' => 'display: none;']);
    echo html_writer::tag('td', $visible_text, ['class' => 'col-visible', 'style' => 'display: none;']);
    echo html_writer::tag('td', $excerpt, ['class' => 'col-excerpt', 'style' => 'font-size: 12px; color: #666; display: none;']);
    
    // Actions
    echo html_writer::start_tag('td', ['class' => 'col-actions', 'style' => 'white-space: nowrap;']);
    
    // Bouton Voir
    $view_url = question_analyzer::get_question_bank_url($question);
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
            'id' => $question->id,
            'sesskey' => sesskey()
        ]);
        echo html_writer::link($delete_url, '🗑️', [
            'class' => 'btn btn-sm btn-danger',
            'title' => 'Supprimer cette question inutilisée',
            'style' => 'background: #d9534f; color: white; padding: 3px 8px;'
        ]);
    } else {
        // Question protégée
        $reason = $can_delete_check ? $can_delete_check->reason : 'Vérification impossible';
        echo html_writer::tag('span', '🔒', [
            'class' => 'btn btn-sm btn-secondary',
            'title' => 'PROTÉGÉE : ' . $reason,
            'style' => 'background: #6c757d; color: white; padding: 3px 8px; cursor: not-allowed;'
        ]);
    }
    
    echo html_writer::end_tag('td');
    
    echo html_writer::end_tag('tr');
}

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');
echo html_writer::end_tag('div'); // fin qd-table-wrapper

// Pagination
$pagecount = $perpage > 0 ? (int)ceil($total_unused / $perpage) : 1;
if ($pagecount < 1) {
    $pagecount = 1;
}
echo html_writer::start_tag('div', ['style' => 'display:flex; gap:10px; justify-content:center; align-items:center; margin: 30px 0; flex-wrap: wrap;']);

// Page précédente
if ($page > 0) {
    $prevurl = new moodle_url('/local/question_diagnostic/unused_questions.php', ['page' => $page - 1, 'perpage' => $perpage]);
    echo html_writer::link($prevurl, '⬅️ Page précédente', ['class' => 'btn btn-secondary']);
}

// Page suivante
if (($page + 1) < $pagecount) {
    $nexturl = new moodle_url('/local/question_diagnostic/unused_questions.php', ['page' => $page + 1, 'perpage' => $perpage]);
    echo html_writer::link($nexturl, '➡️ Page suivante', ['class' => 'btn btn-primary']);
}

// Info
echo html_writer::tag('span', 'Page ' . ($page + 1) . ' / ' . $pagecount . ' — ' . $perpage . ' par page', ['style' => 'color:#666; font-size: 14px;']);
echo html_writer::end_tag('div');

// ======================================================================
// JavaScript pour les interactions
// ======================================================================

echo html_writer::start_tag('script');
?>
// ======================================================================
// GESTION DE LA SÉLECTION EN MASSE
// ======================================================================

// Gestion sélection de toutes les checkboxes
document.getElementById('select-all-unused').addEventListener('change', function() {
    var checkboxes = document.querySelectorAll('.unused-select-checkbox');
    checkboxes.forEach(function(cb) {
        cb.checked = this.checked;
    }.bind(this));
    updateUnusedSelectionCount();
});

// Gestion sélection individuelle
document.querySelectorAll('.unused-select-checkbox').forEach(function(cb) {
    cb.addEventListener('change', updateUnusedSelectionCount);
});

// Mettre à jour le compteur de sélection
function updateUnusedSelectionCount() {
    var checked = document.querySelectorAll('.unused-select-checkbox:checked');
    var count = checked.length;
    document.getElementById('selection-count-unused').textContent = count + ' question(s) sélectionnée(s)';
    document.getElementById('bulk-actions-container-unused').style.display = count > 0 ? 'block' : 'none';
}

// Suppression en masse
function bulkDeleteUnusedQuestions() {
    var checked = document.querySelectorAll('.unused-select-checkbox:checked');
    var ids = Array.from(checked).map(function(cb) { return cb.value; });
    
    if (ids.length === 0) {
        alert('Aucune question sélectionnée');
        return;
    }
    
    // Confirmation
    var message = 'Êtes-vous sûr de vouloir supprimer ' + ids.length + ' question(s) inutilisée(s) ?\n\n';
    message += '⚠️ ATTENTION : Cette action est IRRÉVERSIBLE !\n\n';
    message += 'Questions à supprimer : ' + ids.join(', ');
    
    if (confirm(message)) {
        // Rediriger vers l'action de suppression en masse
        var url = '<?php echo (new \moodle_url('/local/question_diagnostic/actions/delete_questions_bulk.php'))->out(false); ?>';
        url += '?ids=' + encodeURIComponent(ids.join(',')) + '&sesskey=<?php echo sesskey(); ?>';
        window.location.href = url;
    }
}

// ======================================================================
// GESTION DES COLONNES
// ======================================================================

function toggleColumnsPanel() {
    const panel = document.getElementById('columns-panel');
    if (panel.style.display === 'none') {
        panel.style.display = 'block';
    } else {
        panel.style.display = 'none';
    }
}

function toggleColumn(checkbox) {
    const columnClass = 'col-' + checkbox.getAttribute('data-column');
    const cells = document.querySelectorAll('.' + columnClass);
    
    cells.forEach(function(cell) {
        if (checkbox.checked) {
            cell.style.display = '';
        } else {
            cell.style.display = 'none';
        }
    });
    
    // Sauvegarder les préférences dans localStorage
    const prefs = JSON.parse(localStorage.getItem('qd_unused_column_prefs') || '{}');
    prefs[checkbox.getAttribute('data-column')] = checkbox.checked;
    localStorage.setItem('qd_unused_column_prefs', JSON.stringify(prefs));
}

// Restaurer les préférences au chargement
document.addEventListener('DOMContentLoaded', function() {
    const prefs = JSON.parse(localStorage.getItem('qd_unused_column_prefs') || '{}');
    
    document.querySelectorAll('.column-toggle-checkbox').forEach(function(checkbox) {
        const col = checkbox.getAttribute('data-column');
        if (prefs.hasOwnProperty(col)) {
            checkbox.checked = prefs[col];
            toggleColumn(checkbox);
        }
    });
});

// ======================================================================
// FILTRES
// ======================================================================

document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('filter-search-unused');
    const typeFilter = document.getElementById('filter-type-unused');
    const visibleFilter = document.getElementById('filter-visible-unused');
    
    function applyFilters() {
        const searchValue = searchInput ? searchInput.value.toLowerCase() : '';
        const typeValue = typeFilter ? typeFilter.value : 'all';
        const visibleValue = visibleFilter ? visibleFilter.value : 'all';
        
        const rows = document.querySelectorAll('#unused-questions-table tbody tr');
        let visibleCount = 0;
        
        rows.forEach(function(row) {
            const id = (row.getAttribute('data-id') || '').toLowerCase();
            const name = (row.getAttribute('data-name') || '').toLowerCase();
            const type = row.getAttribute('data-type') || '';
            const category = (row.getAttribute('data-category') || '').toLowerCase();
            const course = (row.getAttribute('data-course') || '').toLowerCase();
            const excerpt = (row.getAttribute('data-excerpt') || '').toLowerCase();
            const visibilityStatus = row.getAttribute('data-visibility-status') || '';
            const visible = row.getAttribute('data-visible') === '1';
            
            const matchesSearch = searchValue === '' || 
                                 id.includes(searchValue) || 
                                 name.includes(searchValue) || 
                                 category.includes(searchValue) ||
                                 course.includes(searchValue) ||
                                 excerpt.includes(searchValue);
            const matchesType = typeValue === 'all' || type === typeValue;
            const matchesVisible = visibleValue === 'all' ||
                                  (visibleValue === 'visible' && visibilityStatus === 'visible') ||
                                  (visibleValue === 'hidden' && visibilityStatus === 'hidden') ||
                                  (visibleValue === 'deleted' && visibilityStatus === 'deleted');
            
            if (matchesSearch && matchesType && matchesVisible) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Mettre à jour le compteur
        const statsDiv = document.getElementById('filter-stats-unused');
        if (statsDiv) {
            statsDiv.innerHTML = visibleCount + ' question(s) affichée(s) sur ' + rows.length;
        }
    }
    
    if (searchInput) {
        let debounceTimer;
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(applyFilters, 300);
        });
    }
    if (typeFilter) typeFilter.addEventListener('change', applyFilters);
    if (visibleFilter) visibleFilter.addEventListener('change', applyFilters);
    
    // Appliquer les filtres initiaux
    applyFilters();
});

// ======================================================================
// TRI DES COLONNES
// ======================================================================

document.addEventListener('DOMContentLoaded', function() {
    const table = document.getElementById('unused-questions-table');
    if (!table) return;
    
    const headers = table.querySelectorAll('th.sortable');
    let currentSort = { column: null, direction: 'asc' };
    
    headers.forEach(function(header) {
        header.style.cursor = 'pointer';
        header.addEventListener('click', function() {
            const column = this.getAttribute('data-column');
            
            if (currentSort.column === column) {
                currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
            } else {
                currentSort.column = column;
                currentSort.direction = 'asc';
            }
            
            sortTableByColumn(table, column, currentSort.direction);
            
            // Mettre à jour les indicateurs visuels
            headers.forEach(h => {
                h.classList.remove('sort-asc', 'sort-desc');
            });
            this.classList.add('sort-' + currentSort.direction);
        });
    });
});

function sortTableByColumn(table, column, direction) {
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

<?php
echo html_writer::end_tag('script');

// ======================================================================
// CSS supplémentaire pour cette page
// ======================================================================

echo html_writer::start_tag('style');
?>
.qd-columns-panel {
    background: #f9f9f9;
    border: 2px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.qd-columns-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 10px;
}

.qd-column-toggle {
    display: flex;
    align-items: center;
    cursor: pointer;
    padding: 5px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    transition: background 0.2s;
}

.qd-column-toggle:hover {
    background: #e9ecef;
}

.qd-column-toggle input {
    margin-right: 8px;
}

.sort-asc::after {
    content: ' ▲';
    font-size: 10px;
}

.sort-desc::after {
    content: ' ▼';
    font-size: 10px;
}

@media (max-width: 768px) {
    .qd-columns-grid {
        grid-template-columns: 1fr 1fr;
    }
}
<?php
echo html_writer::end_tag('style');

// ======================================================================
// Pied de page Moodle standard
// ======================================================================
echo $OUTPUT->footer();

