<?php
// ======================================================================
// Moodle Question Cleanup Tool - Statistiques et nettoyage des questions
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
$PAGE->set_url(new moodle_url('/local/question_diagnostic/questions_cleanup.php'));
$pagetitle = get_string('questions_cleanup_heading', 'local_question_diagnostic');
$PAGE->set_title(get_string('questions_cleanup', 'local_question_diagnostic'));
$PAGE->set_heading(local_question_diagnostic_get_heading_with_version($pagetitle));
$PAGE->set_pagelayout('report');

// Ajouter les CSS et JavaScript personnalisés
$PAGE->requires->css('/local/question_diagnostic/styles/main.css');
$PAGE->requires->js('/local/question_diagnostic/scripts/main.js', true);

// ======================================================================
// Section d'en-tête Moodle standard.
echo $OUTPUT->header();

// Lien retour vers le menu principal et bouton de purge de cache
echo html_writer::start_tag('div', ['style' => 'margin-bottom: 20px; display: flex; gap: 10px; align-items: center;']);
echo html_writer::link(
    new moodle_url('/local/question_diagnostic/index.php'),
    '← ' . get_string('backtomenu', 'local_question_diagnostic'),
    ['class' => 'btn btn-secondary']
);

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

echo html_writer::end_tag('div');

// ======================================================================
// STATISTIQUES GLOBALES (Dashboard)
// ======================================================================

echo html_writer::tag('h2', '📊 ' . get_string('questions_stats', 'local_question_diagnostic'));

// Afficher un message d'avertissement sur le temps de calcul (masqué si loadstats=0)
if (optional_param('loadstats', 0, PARAM_INT) == 1) {
    echo html_writer::start_tag('div', ['class' => 'alert alert-info', 'style' => 'margin-bottom: 20px;']);
    echo html_writer::tag('strong', '⚠️ Information : ');
    echo get_string('loading_stats', 'local_question_diagnostic') . ' ';
    echo html_writer::tag('span', get_string('loading_questions', 'local_question_diagnostic'), ['id' => 'loading-indicator', 'style' => 'font-weight: bold;']);
    echo html_writer::end_tag('div');
}

// 🚨 v1.6.1 : CHARGEMENT MINIMAL - Ne charger que le strict nécessaire
$load_stats = optional_param('loadstats', 0, PARAM_INT);

if (!$load_stats) {
    // Affichage minimal ultra-rapide
    echo html_writer::start_tag('div', ['class' => 'alert alert-info', 'style' => 'margin: 30px 0; padding: 40px; text-align: center;']);
    echo html_writer::tag('h2', '📊 Statistiques des Questions', ['style' => 'margin-top: 0;']);
    
    // Comptage ultra-simple
    try {
        global $DB;
        $total_questions = $DB->count_records('question');
        echo html_writer::tag('p', "Votre base contient <strong style='font-size: 24px; color: #0f6cbf;'>" . number_format($total_questions, 0, ',', ' ') . " questions</strong>.", ['style' => 'font-size: 18px; margin: 20px 0;']);
    } catch (Exception $e) {
        echo html_writer::tag('p', "Impossible de compter les questions.", ['style' => 'color: red;']);
    }
    
    echo html_writer::tag('p', '⚡ Pour optimiser les performances sur votre grande base de données,<br>les statistiques détaillées et la liste des questions ne sont pas chargées automatiquement.', ['style' => 'margin: 30px 0; font-size: 14px;']);
    
    $loadstats_url = new moodle_url('/local/question_diagnostic/questions_cleanup.php', ['loadstats' => 1, 'show' => 50]);
    echo html_writer::start_tag('div', ['style' => 'margin-top: 40px;']);
    echo html_writer::link(
        $loadstats_url,
        '🚀 Charger les statistiques et la liste des questions',
        ['class' => 'btn btn-lg btn-success', 'style' => 'font-size: 20px; padding: 20px 40px;']
    );
    echo html_writer::end_tag('div');
    
    echo html_writer::tag('p', '⏱️ Temps de chargement estimé : ~30 secondes', ['style' => 'margin-top: 20px; font-style: italic; color: #666;']);
    echo html_writer::end_tag('div');
    
    echo $OUTPUT->footer();
    exit;
}

// Charger les statistiques avec gestion d'erreurs
try {
    // 🚨 v1.6.1 : Désactiver la détection de doublons dans les stats globales (trop lourd)
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

// Masquer l'indicateur de chargement via JavaScript
echo html_writer::start_tag('script');
echo "document.getElementById('loading-indicator').style.display = 'none';";
echo html_writer::end_tag('script');

// Message si statistiques simplifiées
if (isset($globalstats->simplified) && $globalstats->simplified) {
    echo html_writer::start_tag('div', ['class' => 'alert alert-warning', 'style' => 'margin-bottom: 20px; border-left: 4px solid #f0ad4e;']);
    echo html_writer::tag('h4', '⚡ Mode Performance Activé', ['style' => 'margin-top: 0;']);
    echo html_writer::tag('p', 'Votre base contient <strong>' . number_format($globalstats->total_questions, 0, ',', ' ') . ' questions</strong>. Pour éviter les timeouts, certaines statistiques sont des <strong>approximations</strong> :', ['style' => 'margin-bottom: 15px;']);
    
    echo html_writer::start_tag('ul', ['style' => 'margin-bottom: 15px;']);
    echo html_writer::tag('li', '✅ <strong>Total questions</strong> et <strong>Répartition par type</strong> : Valeurs exactes');
    echo html_writer::tag('li', '✅ <strong>Questions Utilisées/Inutilisées</strong> : Valeurs exactes (comptage simplifié)');
    echo html_writer::tag('li', '⚠️ <strong>Questions Cachées</strong> : Affiché comme 0 (non calculé)');
    echo html_writer::tag('li', '⚠️ <strong>Doublons</strong> : Non calculés');
    echo html_writer::tag('li', '⚠️ <strong>Liens Cassés</strong> : Non calculés');
    echo html_writer::end_tag('ul');
    
    echo html_writer::tag('p', '💡 <strong>Pour voir les vraies utilisations</strong> : Consultez la colonne "Quiz" et "Tentatives" dans le tableau ci-dessous (données exactes pour les questions affichées).', ['style' => 'font-weight: bold; color: #0f6cbf;']);
    echo html_writer::end_tag('div');
}

echo html_writer::start_tag('div', ['class' => 'qd-dashboard']);

// Carte 1 : Total questions
echo html_writer::start_tag('div', ['class' => 'qd-card']);
echo html_writer::tag('div', get_string('total_questions_stats', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', $globalstats->total_questions, ['class' => 'qd-card-value']);
echo html_writer::tag('div', get_string('in_database', 'local_question_diagnostic'), ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

// Carte 2 : Questions utilisées (valeur exacte maintenant)
$is_simplified = isset($globalstats->simplified) && $globalstats->simplified;
echo html_writer::start_tag('div', ['class' => 'qd-card success']);
echo html_writer::tag('div', get_string('questions_used', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', $globalstats->used_questions, ['class' => 'qd-card-value']);
echo html_writer::tag('div', get_string('in_quizzes_or_attempts', 'local_question_diagnostic'), ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

// Carte 3 : Questions inutilisées (valeur exacte maintenant)
echo html_writer::start_tag('div', ['class' => 'qd-card warning']);
echo html_writer::tag('div', get_string('questions_unused', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', $globalstats->unused_questions, ['class' => 'qd-card-value']);
echo html_writer::tag('div', get_string('never_used', 'local_question_diagnostic'), ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

// Carte 4 : Doublons (approximé en mode simplifié)
$approx_style = $is_simplified ? 'opacity: 0.6; border: 2px dashed #f0ad4e;' : '';
echo html_writer::start_tag('div', ['class' => 'qd-card danger', 'style' => $approx_style]);
echo html_writer::tag('div', ($is_simplified ? '⚠️ ' : '') . get_string('questions_duplicates', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', ($is_simplified ? '~' : '') . $globalstats->duplicate_questions, ['class' => 'qd-card-value']);
echo html_writer::tag('div', $globalstats->total_duplicates . ' ' . get_string('total_duplicates_found', 'local_question_diagnostic') . ($is_simplified ? ' (non calculé)' : ''), ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

// Carte 5 : Questions cachées
echo html_writer::start_tag('div', ['class' => 'qd-card', 'style' => $approx_style]);
echo html_writer::tag('div', ($is_simplified ? '⚠️ ' : '') . get_string('questions_hidden', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', ($is_simplified ? '~' : '') . $globalstats->hidden_questions, ['class' => 'qd-card-value']);
echo html_writer::tag('div', get_string('not_visible', 'local_question_diagnostic') . ($is_simplified ? ' (non calculé)' : ''), ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

// Carte 6 : Liens cassés
if ($globalstats->questions_with_broken_links > 0) {
    echo html_writer::start_tag('div', ['class' => 'qd-card danger']);
} else {
    echo html_writer::start_tag('div', ['class' => 'qd-card success']);
}
echo html_writer::tag('div', get_string('questions_broken_links', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', $globalstats->questions_with_broken_links, ['class' => 'qd-card-value']);
echo html_writer::tag('div', get_string('questions_with_problems', 'local_question_diagnostic'), ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

echo html_writer::end_tag('div'); // fin dashboard

// ======================================================================
// RÉPARTITION PAR TYPE DE QUESTION
// ======================================================================

if (!empty($globalstats->by_type) && $globalstats->total_questions > 0) {
    echo html_writer::tag('h3', '📈 ' . get_string('distribution_by_type', 'local_question_diagnostic'), ['style' => 'margin-top: 30px;']);
    
    echo html_writer::start_tag('div', ['class' => 'qd-stats-by-type']);
    foreach ($globalstats->by_type as $qtype => $count) {
        $percentage = round(($count / $globalstats->total_questions) * 100, 1);
        echo html_writer::start_tag('div', ['class' => 'qd-stat-item']);
        echo html_writer::tag('span', ucfirst($qtype), ['class' => 'qd-stat-label']);
        echo html_writer::tag('span', $count . ' (' . $percentage . '%)', ['class' => 'qd-stat-value']);
        echo html_writer::end_tag('div');
    }
    echo html_writer::end_tag('div');
}

// ======================================================================
// BARRE D'ACTIONS ET EXPORT
// ======================================================================

echo html_writer::start_tag('div', ['style' => 'margin: 30px 0 20px 0; display: flex; gap: 10px; flex-wrap: wrap;']);

$exporturl = new moodle_url('/local/question_diagnostic/actions/export.php', [
    'type' => 'questions_csv',
    'sesskey' => sesskey()
]);
echo html_writer::link($exporturl, '📥 ' . get_string('export_questions_csv', 'local_question_diagnostic'), ['class' => 'btn btn-success']);

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
    'module' => 'Module',
    'context' => 'Contexte',
    'creator' => 'Créateur',
    'created' => 'Date création',
    'modified' => 'Date modification',
    'visible' => 'Visible',
    'quizzes' => 'Quiz',
    'attempts' => 'Tentatives',
    'duplicates' => 'Doublons',
    'excerpt' => 'Extrait',
    'actions' => 'Actions'
];

echo html_writer::start_tag('div', ['class' => 'qd-columns-grid']);
foreach ($columns as $col_id => $col_name) {
    // Par défaut : afficher id, name, type, category, course, quizzes, duplicates, actions
    $checked = in_array($col_id, ['id', 'name', 'type', 'category', 'course', 'quizzes', 'duplicates', 'actions']);
    echo html_writer::start_tag('label', ['class' => 'qd-column-toggle']);
    echo html_writer::checkbox('column_' . $col_id, 1, $checked, ' ' . $col_name, [
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

echo html_writer::start_tag('div', ['class' => 'qd-filters']);
echo html_writer::tag('h4', '🔍 Filtres et recherche', ['style' => 'margin-top: 0;']);

echo html_writer::start_tag('div', ['class' => 'qd-filters-row']);

// Recherche par nom/ID
echo html_writer::start_tag('div', ['class' => 'qd-filter-group']);
echo html_writer::tag('label', 'Rechercher', ['for' => 'filter-search']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'id' => 'filter-search',
    'placeholder' => 'Nom, ID, cours, module, texte...',
    'class' => 'form-control'
]);
echo html_writer::end_tag('div');

// Filtre par type
echo html_writer::start_tag('div', ['class' => 'qd-filter-group']);
echo html_writer::tag('label', 'Type de question', ['for' => 'filter-qtype']);
echo html_writer::start_tag('select', ['id' => 'filter-qtype', 'class' => 'form-control']);
echo html_writer::tag('option', 'Tous', ['value' => 'all']);
foreach ($globalstats->by_type as $qtype => $count) {
    echo html_writer::tag('option', ucfirst($qtype) . " ($count)", ['value' => $qtype]);
}
echo html_writer::end_tag('select');
echo html_writer::end_tag('div');

// Filtre par statut d'usage
echo html_writer::start_tag('div', ['class' => 'qd-filter-group']);
echo html_writer::tag('label', 'Usage', ['for' => 'filter-usage']);
echo html_writer::start_tag('select', ['id' => 'filter-usage', 'class' => 'form-control']);
echo html_writer::tag('option', 'Toutes', ['value' => 'all']);
echo html_writer::tag('option', 'Utilisées', ['value' => 'used']);
echo html_writer::tag('option', 'Inutilisées', ['value' => 'unused']);
echo html_writer::end_tag('select');
echo html_writer::end_tag('div');

// Filtre par doublon
echo html_writer::start_tag('div', ['class' => 'qd-filter-group']);
echo html_writer::tag('label', 'Doublons', ['for' => 'filter-duplicate']);
echo html_writer::start_tag('select', ['id' => 'filter-duplicate', 'class' => 'form-control']);
echo html_writer::tag('option', 'Toutes', ['value' => 'all']);
echo html_writer::tag('option', 'Avec doublons', ['value' => 'duplicate']);
echo html_writer::tag('option', 'Sans doublons', ['value' => 'no_duplicate']);
echo html_writer::end_tag('select');
echo html_writer::end_tag('div');

echo html_writer::end_tag('div'); // fin qd-filters-row

// Compteur de résultats
echo html_writer::tag('div', '', ['id' => 'filter-stats', 'style' => 'margin-top: 10px; font-size: 14px; color: #666;']);

echo html_writer::end_tag('div'); // fin qd-filters

// ======================================================================
// TABLEAU DES QUESTIONS
// ======================================================================

echo html_writer::tag('h3', '📝 Liste détaillée des questions', ['style' => 'margin-top: 30px;']);

echo html_writer::start_tag('div', ['id' => 'loading-questions', 'style' => 'text-align: center; padding: 40px;']);
echo html_writer::tag('p', '⏳ Chargement des questions en cours...', ['style' => 'font-size: 16px;']);
echo html_writer::tag('p', 'Cela peut prendre quelques instants pour les grandes bases de données.', ['style' => 'font-size: 14px; color: #666;']);
echo html_writer::end_tag('div');

// Charger les questions avec gestion d'erreurs optimisée
try {
    // 🚨 v1.6.0 : PROTECTION RENFORCÉE pour grandes bases (30 000+ questions)
    // Paramètre pour augmenter la limite si besoin
    $max_questions_display = optional_param('show', 10, PARAM_INT); // Par défaut : 10 questions (ultra rapide)
    $max_questions_display = min($max_questions_display, 5000); // Limite absolue : 5000
    
    $total_questions = $globalstats->total_questions;
    
    // 🚫 DÉSACTIVER la détection de doublons par défaut (trop lourd)
    $include_duplicates = false;
    
    // Message d'information pour les grandes bases
    echo html_writer::start_tag('div', ['class' => 'alert alert-info', 'style' => 'margin-bottom: 20px; border-left: 4px solid #0f6cbf;']);
    echo html_writer::tag('strong', '📊 Votre base de données : ');
    echo "<strong>" . number_format($total_questions, 0, ',', ' ') . " questions au total</strong>. ";
    echo '<br><br>';
    echo '🎯 <strong>Affichage actuel</strong> : Les <strong>' . min($max_questions_display, $total_questions) . ' premières questions</strong> sont affichées ci-dessous.';
    echo '<br><br>';
    echo '💡 <strong>Options</strong> :';
    echo '<ul style="margin-top: 10px;">';
    echo '<li>Utilisez les <strong>filtres</strong> pour affiner les résultats</li>';
    echo '<li>Changez le nombre de questions affichées : ';
    
    $url_10 = new moodle_url('/local/question_diagnostic/questions_cleanup.php', ['show' => 10]);
    $url_50 = new moodle_url('/local/question_diagnostic/questions_cleanup.php', ['show' => 50]);
    $url_100 = new moodle_url('/local/question_diagnostic/questions_cleanup.php', ['show' => 100]);
    $url_500 = new moodle_url('/local/question_diagnostic/questions_cleanup.php', ['show' => 500]);
    $url_1000 = new moodle_url('/local/question_diagnostic/questions_cleanup.php', ['show' => 1000]);
    
    echo html_writer::link($url_10, '10', ['class' => $max_questions_display == 10 ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-secondary']);
    echo ' ';
    echo html_writer::link($url_50, '50', ['class' => $max_questions_display == 50 ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-secondary']);
    echo ' ';
    echo html_writer::link($url_100, '100', ['class' => $max_questions_display == 100 ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-secondary']);
    echo ' ';
    echo html_writer::link($url_500, '500', ['class' => $max_questions_display == 500 ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-secondary']);
    echo ' ';
    echo html_writer::link($url_1000, '1000', ['class' => $max_questions_display == 1000 ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-secondary']);
    echo '</li>';
    echo '</ul>';
    echo '<p style="margin-top: 15px;"><em>Les statistiques globales ci-dessus concernent bien <strong>TOUTES les ' . number_format($total_questions, 0, ',', ' ') . ' questions</strong>.</em></p>';
    echo html_writer::end_tag('div');
    
    // 🔥 MODIFICATION CRITIQUE : Limiter le nombre de questions chargées
    $limit = min($max_questions_display, $total_questions);
    $questions_with_stats = question_analyzer::get_all_questions_with_stats($include_duplicates, $limit);
    
} catch (Exception $e) {
    echo html_writer::start_tag('script');
    echo "document.getElementById('loading-questions').style.display = 'none';";
    echo html_writer::end_tag('script');
    
    echo html_writer::start_tag('div', ['class' => 'alert alert-danger']);
    echo html_writer::tag('strong', '⚠️ Erreur : ');
    echo 'Impossible de charger les questions. Cela peut être dû à une base de données trop volumineuse ou à un timeout. ';
    echo html_writer::tag('p', 'Détails : ' . $e->getMessage(), ['style' => 'margin-top: 10px; font-size: 12px;']);
    echo html_writer::tag('p', 'Suggestions :', ['style' => 'margin-top: 10px; font-weight: bold;']);
    echo html_writer::start_tag('ul');
    echo html_writer::tag('li', 'Augmenter le timeout PHP dans votre configuration');
    echo html_writer::tag('li', 'Augmenter la limite de mémoire PHP');
    echo html_writer::tag('li', 'Vider le cache Moodle (Administration du site > Développement > Purger tous les caches)');
    echo html_writer::tag('li', 'Contacter votre administrateur système');
    echo html_writer::end_tag('ul');
    echo html_writer::end_tag('div');
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::start_tag('script');
echo "document.getElementById('loading-questions').style.display = 'none';";
echo html_writer::end_tag('script');

echo html_writer::start_tag('div', ['class' => 'qd-table-wrapper']);
echo html_writer::start_tag('table', ['class' => 'qd-table', 'id' => 'questions-table']);

// En-tête du tableau
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
echo html_writer::tag('th', 'ID', ['class' => 'sortable col-id', 'data-column' => 'id']);
echo html_writer::tag('th', 'Nom', ['class' => 'sortable col-name', 'data-column' => 'name']);
echo html_writer::tag('th', 'Type', ['class' => 'sortable col-type', 'data-column' => 'type']);
echo html_writer::tag('th', 'Catégorie', ['class' => 'sortable col-category', 'data-column' => 'category']);
echo html_writer::tag('th', 'Cours', ['class' => 'sortable col-course', 'data-column' => 'course']);
echo html_writer::tag('th', 'Module', ['class' => 'sortable col-module', 'data-column' => 'module', 'style' => 'display: none;']);
echo html_writer::tag('th', 'Contexte', ['class' => 'sortable col-context', 'data-column' => 'context', 'style' => 'display: none;']);
echo html_writer::tag('th', 'Créateur', ['class' => 'sortable col-creator', 'data-column' => 'creator', 'style' => 'display: none;']);
echo html_writer::tag('th', 'Créée le', ['class' => 'sortable col-created', 'data-column' => 'created', 'style' => 'display: none;']);
echo html_writer::tag('th', 'Modifiée le', ['class' => 'sortable col-modified', 'data-column' => 'modified', 'style' => 'display: none;']);
echo html_writer::tag('th', 'Visible', ['class' => 'sortable col-visible', 'data-column' => 'visible', 'style' => 'display: none;']);
echo html_writer::tag('th', 'Quiz', ['class' => 'sortable col-quizzes', 'data-column' => 'quizzes']);
echo html_writer::tag('th', 'Tentatives', ['class' => 'sortable col-attempts', 'data-column' => 'attempts', 'style' => 'display: none;']);
echo html_writer::tag('th', 'Doublons', ['class' => 'sortable col-duplicates', 'data-column' => 'duplicates']);
echo html_writer::tag('th', 'Extrait', ['class' => 'col-excerpt', 'style' => 'display: none;']);
echo html_writer::tag('th', 'Actions', ['class' => 'col-actions']);
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');

// Corps du tableau
echo html_writer::start_tag('tbody');

foreach ($questions_with_stats as $item) {
    $q = $item->question;
    $s = $item->stats;
    
    // Attributs data pour le filtrage et le tri
    $row_attrs = [
        'data-id' => $q->id,
        'data-name' => format_string($q->name),
        'data-type' => $q->qtype,
        'data-category' => $s->category_name,
        'data-course' => $s->course_name ?? '',
        'data-module' => $s->module_name ?? '',
        'data-context' => $s->context_name,
        'data-creator' => $s->creator_name,
        'data-created' => $s->created_date,
        'data-modified' => $s->modified_date,
        'data-visible' => $s->is_hidden ? '0' : '1',
        'data-quizzes' => $s->used_in_quizzes,
        'data-attempts' => $s->attempt_count,
        'data-duplicates' => $s->duplicate_count,
        'data-used' => $s->is_used ? '1' : '0',
        'data-is-duplicate' => $s->is_duplicate ? '1' : '0',
        'data-excerpt' => htmlspecialchars($s->questiontext_excerpt)
    ];
    
    echo html_writer::start_tag('tr', $row_attrs);
    
    // ID
    echo html_writer::tag('td', $q->id, ['class' => 'col-id']);
    
    // Nom
    echo html_writer::start_tag('td', ['class' => 'col-name']);
    echo html_writer::tag('strong', format_string($q->name));
    echo html_writer::end_tag('td');
    
    // Type
    echo html_writer::tag('td', html_writer::tag('span', 
        ucfirst($q->qtype), 
        ['class' => 'badge badge-info']
    ), ['class' => 'col-type']);
    
    // Catégorie
    echo html_writer::start_tag('td', ['class' => 'col-category']);
    $cat_url = new moodle_url('/question/edit.php', [
        'courseid' => 0,
        'cat' => $s->category_id . ',' . $s->context_id
    ]);
    echo html_writer::link($cat_url, $s->category_name, ['target' => '_blank', 'title' => 'Voir la catégorie']);
    echo html_writer::end_tag('td');
    
    // Cours
    echo html_writer::start_tag('td', ['class' => 'col-course']);
    if (!empty($s->course_name)) {
        echo html_writer::tag('span', '📚 ' . $s->course_name, [
            'style' => 'font-size: 13px;',
            'title' => $s->course_name
        ]);
    } else {
        echo html_writer::tag('span', '-', ['style' => 'color: #999;']);
    }
    echo html_writer::end_tag('td');
    
    // Module
    echo html_writer::start_tag('td', ['class' => 'col-module', 'style' => 'display: none;']);
    if (!empty($s->module_name)) {
        echo html_writer::tag('span', '📝 ' . $s->module_name, [
            'style' => 'font-size: 13px;',
            'title' => $s->module_name
        ]);
    } else {
        echo html_writer::tag('span', '-', ['style' => 'color: #999;']);
    }
    echo html_writer::end_tag('td');
    
    // Contexte (avec info cours/module)
    $context_display = $s->context_name;
    $tooltip_parts = [];
    if (!empty($s->course_name)) {
        $tooltip_parts[] = '📚 Cours : ' . $s->course_name;
    }
    if (!empty($s->module_name)) {
        $tooltip_parts[] = '📝 Module : ' . $s->module_name;
    }
    $tooltip = !empty($tooltip_parts) ? implode("\n", $tooltip_parts) : '';
    
    if ($tooltip) {
        $context_html = html_writer::tag('span', $context_display, [
            'title' => $tooltip,
            'style' => 'cursor: help; border-bottom: 1px dotted #666;'
        ]);
    } else {
        $context_html = $context_display;
    }
    echo html_writer::tag('td', $context_html, ['class' => 'col-context', 'style' => 'display: none;']);
    
    // Créateur
    echo html_writer::tag('td', $s->creator_name, ['class' => 'col-creator', 'style' => 'display: none;']);
    
    // Date création
    echo html_writer::tag('td', $s->created_formatted, ['class' => 'col-created', 'style' => 'display: none;']);
    
    // Date modification
    echo html_writer::tag('td', $s->modified_formatted, ['class' => 'col-modified', 'style' => 'display: none;']);
    
    // Visible
    echo html_writer::tag('td', 
        $s->is_hidden ? '❌ Non' : '✅ Oui',
        ['class' => 'col-visible', 'style' => 'display: none;']
    );
    
    // Quiz
    echo html_writer::start_tag('td', ['class' => 'col-quizzes']);
    if ($s->used_in_quizzes > 0) {
        echo html_writer::tag('span', $s->used_in_quizzes, [
            'class' => 'qd-badge qd-badge-ok',
            'title' => 'Utilisée dans ' . $s->used_in_quizzes . ' quiz'
        ]);
        
        // Afficher la liste des quiz au survol
        if (!empty($s->quiz_list)) {
            $quiz_titles = array_map(function($quiz) {
                return format_string($quiz->name);
            }, $s->quiz_list);
            echo html_writer::tag('span', ' ℹ️', [
                'title' => implode(', ', $quiz_titles),
                'style' => 'cursor: help;'
            ]);
        }
    } else {
        echo html_writer::tag('span', '0', ['class' => 'qd-badge qd-badge-empty']);
    }
    echo html_writer::end_tag('td');
    
    // Tentatives
    echo html_writer::start_tag('td', ['class' => 'col-attempts', 'style' => 'display: none;']);
    if ($s->attempt_count > 0) {
        echo html_writer::tag('span', $s->attempt_count, ['class' => 'qd-badge qd-badge-ok']);
    } else {
        echo html_writer::tag('span', '0', ['class' => 'qd-badge qd-badge-empty']);
    }
    echo html_writer::end_tag('td');
    
    // Doublons
    echo html_writer::start_tag('td', ['class' => 'col-duplicates']);
    if ($s->duplicate_count > 0) {
        echo html_writer::tag('button', $s->duplicate_count, [
            'class' => 'qd-badge qd-badge-danger duplicate-btn',
            'data-questionid' => $q->id,
            'data-duplicateids' => json_encode($s->duplicate_ids),
            'onclick' => 'showDuplicatesModal(' . $q->id . ', ' . json_encode(format_string($q->name)) . ', ' . json_encode($s->duplicate_ids) . ')',
            'style' => 'cursor: pointer; border: none;',
            'title' => 'Cliquer pour voir les doublons'
        ]);
    } else {
        echo html_writer::tag('span', '0', ['class' => 'qd-badge qd-badge-ok']);
    }
    echo html_writer::end_tag('td');
    
    // Extrait
    echo html_writer::tag('td', htmlspecialchars($s->questiontext_excerpt), ['class' => 'col-excerpt', 'style' => 'display: none; font-size: 12px; color: #666;']);
    
    // Actions
    echo html_writer::start_tag('td', ['class' => 'col-actions']);
    echo html_writer::start_tag('div', ['class' => 'qd-actions']);
    
    // Bouton voir
    $questionbank_url = question_analyzer::get_question_bank_url($q);
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
    
    echo html_writer::end_tag('div');
    echo html_writer::end_tag('td');
    
    echo html_writer::end_tag('tr');
}

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');
echo html_writer::end_tag('div'); // fin qd-table-wrapper

// ======================================================================
// MODAL DES DOUBLONS
// ======================================================================

echo html_writer::start_tag('div', ['class' => 'qd-modal', 'id' => 'duplicates-modal']);
echo html_writer::start_tag('div', ['class' => 'qd-modal-content', 'style' => 'max-width: 900px;']);

echo html_writer::start_tag('div', ['class' => 'qd-modal-header']);
echo html_writer::tag('h3', '🔀 Questions en doublon', ['class' => 'qd-modal-title']);
echo html_writer::tag('button', '&times;', ['class' => 'qd-modal-close', 'onclick' => 'closeDuplicatesModal()']);
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'qd-modal-body'], ['id' => 'duplicates-modal-body']);
echo html_writer::tag('p', 'Chargement...');
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'qd-modal-footer']);
echo html_writer::tag('button', 'Fermer', ['class' => 'btn btn-secondary', 'onclick' => 'closeDuplicatesModal()']);
echo html_writer::end_tag('div');

echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

// ======================================================================
// JavaScript pour les interactions
// ======================================================================

echo html_writer::start_tag('script');
?>
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
    const prefs = JSON.parse(localStorage.getItem('qd_column_prefs') || '{}');
    prefs[checkbox.getAttribute('data-column')] = checkbox.checked;
    localStorage.setItem('qd_column_prefs', JSON.stringify(prefs));
}

// Restaurer les préférences au chargement
document.addEventListener('DOMContentLoaded', function() {
    const prefs = JSON.parse(localStorage.getItem('qd_column_prefs') || '{}');
    
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
    const searchInput = document.getElementById('filter-search');
    const qtypeFilter = document.getElementById('filter-qtype');
    const usageFilter = document.getElementById('filter-usage');
    const duplicateFilter = document.getElementById('filter-duplicate');
    
    function applyFilters() {
        const searchValue = searchInput ? searchInput.value.toLowerCase() : '';
        const qtypeValue = qtypeFilter ? qtypeFilter.value : 'all';
        const usageValue = usageFilter ? usageFilter.value : 'all';
        const duplicateValue = duplicateFilter ? duplicateFilter.value : 'all';
        
        const rows = document.querySelectorAll('.qd-table tbody tr');
        let visibleCount = 0;
        
        rows.forEach(function(row) {
            const id = (row.getAttribute('data-id') || '').toLowerCase();
            const name = (row.getAttribute('data-name') || '').toLowerCase();
            const type = row.getAttribute('data-type') || '';
            const category = (row.getAttribute('data-category') || '').toLowerCase();
            const course = (row.getAttribute('data-course') || '').toLowerCase();
            const module = (row.getAttribute('data-module') || '').toLowerCase();
            const excerpt = (row.getAttribute('data-excerpt') || '').toLowerCase();
            const used = row.getAttribute('data-used') === '1';
            const isDuplicate = row.getAttribute('data-is-duplicate') === '1';
            
            const matchesSearch = searchValue === '' || 
                                 id.includes(searchValue) || 
                                 name.includes(searchValue) || 
                                 category.includes(searchValue) ||
                                 course.includes(searchValue) ||
                                 module.includes(searchValue) ||
                                 excerpt.includes(searchValue);
            const matchesQtype = qtypeValue === 'all' || type === qtypeValue;
            const matchesUsage = usageValue === 'all' || 
                                (usageValue === 'used' && used) || 
                                (usageValue === 'unused' && !used);
            const matchesDuplicate = duplicateValue === 'all' || 
                                    (duplicateValue === 'duplicate' && isDuplicate) || 
                                    (duplicateValue === 'no_duplicate' && !isDuplicate);
            
            if (matchesSearch && matchesQtype && matchesUsage && matchesDuplicate) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Mettre à jour le compteur
        const statsDiv = document.getElementById('filter-stats');
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
    if (qtypeFilter) qtypeFilter.addEventListener('change', applyFilters);
    if (usageFilter) usageFilter.addEventListener('change', applyFilters);
    if (duplicateFilter) duplicateFilter.addEventListener('change', applyFilters);
    
    // Appliquer les filtres initiaux
    applyFilters();
});

// ======================================================================
// TRI DES COLONNES
// ======================================================================

document.addEventListener('DOMContentLoaded', function() {
    const headers = document.querySelectorAll('.qd-table th.sortable');
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
            
            sortTable(column, currentSort.direction);
            
            // Mettre à jour les indicateurs visuels
            headers.forEach(h => {
                h.classList.remove('sort-asc', 'sort-desc');
            });
            this.classList.add('sort-' + currentSort.direction);
        });
    });
});

function sortTable(column, direction) {
    const tbody = document.querySelector('.qd-table tbody');
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

// ======================================================================
// MODAL DES DOUBLONS
// ======================================================================

function showDuplicatesModal(questionId, questionName, duplicateIds) {
    const modal = document.getElementById('duplicates-modal');
    const modalBody = document.getElementById('duplicates-modal-body');
    
    let content = '<h4>Question : ' + questionName + ' (ID: ' + questionId + ')</h4>';
    content += '<p><strong>' + duplicateIds.length + ' question(s) en doublon détectée(s)</strong></p>';
    content += '<p style="color: #666; font-size: 14px;">Ces questions ont un contenu similaire (nom, texte, type).</p>';
    
    content += '<div style="margin-top: 20px;">';
    content += '<table class="table table-bordered" style="width: 100%;">';
    content += '<thead><tr><th>ID</th><th>Actions</th></tr></thead>';
    content += '<tbody>';
    
    duplicateIds.forEach(function(dupId) {
        const dupRow = document.querySelector('tr[data-id="' + dupId + '"]');
        if (dupRow) {
            const dupName = dupRow.getAttribute('data-name');
            const dupType = dupRow.getAttribute('data-type');
            const dupCategory = dupRow.getAttribute('data-category');
            
            content += '<tr>';
            content += '<td>' + dupId + '</td>';
            content += '<td>';
            content += '<strong>' + dupName + '</strong><br>';
            content += '<small>Type: ' + dupType + ' | Catégorie: ' + dupCategory + '</small><br>';
            content += '<a href="' + M.cfg.wwwroot + '/question/edit.php?courseid=1&qid=' + dupId + '" target="_blank" class="btn btn-sm btn-primary" style="margin-top: 5px;">👁️ Voir</a>';
            content += '</td>';
            content += '</tr>';
        }
    });
    
    content += '</tbody></table>';
    content += '</div>';
    
    content += '<div style="margin-top: 20px; padding: 15px; background: #d9edf7; border: 1px solid #bce8f1; border-radius: 5px;">';
    content += '<strong>💡 Recommandation :</strong> ';
    content += 'Vérifiez manuellement ces questions pour confirmer qu\'il s\'agit bien de doublons. ';
    content += 'Vous pouvez ensuite supprimer ou fusionner les questions redondantes.';
    content += '</div>';
    
    modalBody.innerHTML = content;
    modal.style.display = 'block';
}

function closeDuplicatesModal() {
    const modal = document.getElementById('duplicates-modal');
    modal.style.display = 'none';
}

// Fermer le modal en cliquant en dehors
window.onclick = function(event) {
    const modal = document.getElementById('duplicates-modal');
    if (event.target === modal) {
        closeDuplicatesModal();
    }
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

.qd-stats-by-type {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.qd-stat-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 15px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 6px;
}

.qd-stat-label {
    font-weight: 600;
    color: #333;
}

.qd-stat-value {
    font-size: 16px;
    color: #0066cc;
    font-weight: bold;
}

.sort-asc::after {
    content: ' ▲';
    font-size: 10px;
}

.sort-desc::after {
    content: ' ▼';
    font-size: 10px;
}

.qd-badge-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #dc3545;
}

.qd-badge-danger:hover {
    background: #f5c6cb;
}

@media (max-width: 768px) {
    .qd-columns-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .qd-stats-by-type {
        grid-template-columns: 1fr;
    }
}
<?php
echo html_writer::end_tag('style');

// ======================================================================
// Pied de page Moodle standard
// ======================================================================
echo $OUTPUT->footer();

