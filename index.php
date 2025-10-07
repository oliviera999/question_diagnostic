<?php
// ======================================================================
// Moodle Question Diagnostic - Menu principal
// ======================================================================

// Inclure la configuration de Moodle.
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/category_manager.php');
require_once(__DIR__ . '/classes/question_link_checker.php');

use local_question_diagnostic\category_manager;
use local_question_diagnostic\question_link_checker;

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
$PAGE->set_url(new moodle_url('/local/question_diagnostic/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_question_diagnostic'));
$PAGE->set_heading(get_string('pluginname', 'local_question_diagnostic'));
$PAGE->set_pagelayout('report');

// Ajouter les CSS et JavaScript personnalisés
$PAGE->requires->css('/local/question_diagnostic/styles/main.css');

// ======================================================================
// Section d'en-tête Moodle standard.
echo $OUTPUT->header();

// ======================================================================
// INTRODUCTION
// ======================================================================

echo html_writer::tag('div', 
    get_string('welcomemessage', 'local_question_diagnostic'),
    ['class' => 'alert alert-info', 'style' => 'margin-bottom: 30px;']
);

// ======================================================================
// STATISTIQUES GLOBALES RAPIDES
// ======================================================================

$category_stats = category_manager::get_global_stats();
$link_stats = question_link_checker::get_global_stats();

echo html_writer::tag('h3', '📊 ' . get_string('overview', 'local_question_diagnostic'));

echo html_writer::start_tag('div', ['class' => 'qd-dashboard', 'style' => 'margin-bottom: 40px;']);

// Carte 1 : Total catégories
echo html_writer::start_tag('div', ['class' => 'qd-card']);
echo html_writer::tag('div', 'Catégories', ['class' => 'qd-card-title']);
echo html_writer::tag('div', $category_stats->total_categories, ['class' => 'qd-card-value']);
echo html_writer::tag('div', 'Total', ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

// Carte 2 : Catégories orphelines
$orphan_class = $category_stats->orphan_categories > 0 ? 'danger' : 'success';
echo html_writer::start_tag('div', ['class' => 'qd-card ' . $orphan_class]);
echo html_writer::tag('div', 'Orphelines', ['class' => 'qd-card-title']);
echo html_writer::tag('div', $category_stats->orphan_categories, ['class' => 'qd-card-value']);
echo html_writer::tag('div', 'Catégories', ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

// Carte 3 : Total questions
echo html_writer::start_tag('div', ['class' => 'qd-card']);
echo html_writer::tag('div', 'Questions', ['class' => 'qd-card-title']);
echo html_writer::tag('div', $link_stats->total_questions, ['class' => 'qd-card-value']);
echo html_writer::tag('div', 'Total', ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

// Carte 4 : Questions avec liens cassés
$broken_class = $link_stats->questions_with_broken_links > 0 ? 'danger' : 'success';
echo html_writer::start_tag('div', ['class' => 'qd-card ' . $broken_class]);
echo html_writer::tag('div', 'Liens cassés', ['class' => 'qd-card-title']);
echo html_writer::tag('div', $link_stats->questions_with_broken_links, ['class' => 'qd-card-value']);
echo html_writer::tag('div', 'Questions', ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

echo html_writer::end_tag('div'); // fin dashboard

// ======================================================================
// MENU DES OUTILS
// ======================================================================

echo html_writer::tag('h3', '🔧 ' . get_string('toolsmenu', 'local_question_diagnostic'), ['style' => 'margin-top: 40px;']);

echo html_writer::start_tag('div', ['class' => 'qd-tools-menu']);

// ======================================================================
// OPTION 1 : Gestion des catégories orphelines
// ======================================================================

echo html_writer::start_tag('div', ['class' => 'qd-tool-card']);

echo html_writer::start_tag('div', ['class' => 'qd-tool-icon']);
echo '📂';
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'qd-tool-content']);

echo html_writer::tag('h4', get_string('tool_categories_title', 'local_question_diagnostic'), ['class' => 'qd-tool-title']);

echo html_writer::tag('p', 
    get_string('tool_categories_desc', 'local_question_diagnostic'),
    ['class' => 'qd-tool-description']
);

// Statistiques spécifiques
echo html_writer::start_tag('div', ['class' => 'qd-tool-stats']);
echo html_writer::tag('span', '📊 ' . $category_stats->total_categories . ' catégories', ['class' => 'qd-tool-stat-item']);
if ($category_stats->orphan_categories > 0) {
    echo html_writer::tag('span', '⚠️ ' . $category_stats->orphan_categories . ' orphelines', ['class' => 'qd-tool-stat-item qd-stat-warning']);
}
if ($category_stats->empty_categories > 0) {
    echo html_writer::tag('span', '🗑️ ' . $category_stats->empty_categories . ' vides', ['class' => 'qd-tool-stat-item qd-stat-info']);
}
if ($category_stats->duplicates > 0) {
    echo html_writer::tag('span', '🔀 ' . $category_stats->duplicates . ' doublons', ['class' => 'qd-tool-stat-item qd-stat-info']);
}
echo html_writer::end_tag('div');

$categories_url = new moodle_url('/local/question_diagnostic/categories.php');
echo html_writer::link($categories_url, 'Gérer les catégories →', ['class' => 'qd-tool-button']);

echo html_writer::end_tag('div'); // fin qd-tool-content

echo html_writer::end_tag('div'); // fin qd-tool-card

// ======================================================================
// OPTION 2 : Réparation des liens cassés
// ======================================================================

echo html_writer::start_tag('div', ['class' => 'qd-tool-card']);

echo html_writer::start_tag('div', ['class' => 'qd-tool-icon']);
echo '🔗';
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'qd-tool-content']);

echo html_writer::tag('h4', get_string('tool_links_title', 'local_question_diagnostic'), ['class' => 'qd-tool-title']);

echo html_writer::tag('p', 
    get_string('tool_links_desc', 'local_question_diagnostic'),
    ['class' => 'qd-tool-description']
);

// Statistiques spécifiques
echo html_writer::start_tag('div', ['class' => 'qd-tool-stats']);
echo html_writer::tag('span', '📊 ' . $link_stats->total_questions . ' questions', ['class' => 'qd-tool-stat-item']);
if ($link_stats->questions_with_broken_links > 0) {
    echo html_writer::tag('span', '⚠️ ' . $link_stats->questions_with_broken_links . ' avec liens cassés', ['class' => 'qd-tool-stat-item qd-stat-warning']);
    echo html_writer::tag('span', '🔗 ' . $link_stats->total_broken_links . ' liens cassés', ['class' => 'qd-tool-stat-item qd-stat-danger']);
} else {
    echo html_writer::tag('span', '✅ Aucun problème détecté', ['class' => 'qd-tool-stat-item qd-stat-success']);
}
echo html_writer::end_tag('div');

$broken_links_url = new moodle_url('/local/question_diagnostic/broken_links.php');
echo html_writer::link($broken_links_url, 'Vérifier les liens →', ['class' => 'qd-tool-button']);

echo html_writer::end_tag('div'); // fin qd-tool-content

echo html_writer::end_tag('div'); // fin qd-tool-card

// ======================================================================
// OPTION 3 : Statistiques et nettoyage des questions
// ======================================================================

echo html_writer::start_tag('div', ['class' => 'qd-tool-card']);

echo html_writer::start_tag('div', ['class' => 'qd-tool-icon']);
echo '📊';
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'qd-tool-content']);

echo html_writer::tag('h4', get_string('tool_questions_title', 'local_question_diagnostic'), ['class' => 'qd-tool-title']);

echo html_writer::tag('p', 
    get_string('tool_questions_desc', 'local_question_diagnostic'),
    ['class' => 'qd-tool-description']
);

// Statistiques spécifiques
echo html_writer::start_tag('div', ['class' => 'qd-tool-stats']);
echo html_writer::tag('span', '📊 ' . $link_stats->total_questions . ' questions', ['class' => 'qd-tool-stat-item']);
echo html_writer::tag('span', '🔍 Détection de doublons', ['class' => 'qd-tool-stat-item qd-stat-info']);
echo html_writer::tag('span', '📈 Statistiques d\'usage', ['class' => 'qd-tool-stat-item qd-stat-info']);
echo html_writer::tag('span', '🧹 Nettoyage intelligent', ['class' => 'qd-tool-stat-item qd-stat-success']);
echo html_writer::end_tag('div');

$questions_url = new moodle_url('/local/question_diagnostic/questions_cleanup.php');
echo html_writer::link($questions_url, 'Analyser les questions →', ['class' => 'qd-tool-button']);

echo html_writer::end_tag('div'); // fin qd-tool-content

echo html_writer::end_tag('div'); // fin qd-tool-card

echo html_writer::end_tag('div'); // fin qd-tools-menu

// ======================================================================
// INFORMATIONS SUPPLÉMENTAIRES
// ======================================================================

echo html_writer::start_tag('div', ['style' => 'margin-top: 40px; padding: 20px; background: #f9f9f9; border-radius: 8px;']);

echo html_writer::tag('h4', '💡 ' . get_string('usage_tips', 'local_question_diagnostic'));

echo html_writer::start_tag('ul', ['style' => 'line-height: 1.8;']);
echo html_writer::tag('li', get_string('tip_orphan_categories', 'local_question_diagnostic'));
echo html_writer::tag('li', get_string('tip_empty_categories', 'local_question_diagnostic'));
echo html_writer::tag('li', get_string('tip_broken_links', 'local_question_diagnostic'));
echo html_writer::tag('li', get_string('tip_backup', 'local_question_diagnostic'));
echo html_writer::end_tag('ul');

echo html_writer::end_tag('div');


// ======================================================================
// Pied de page Moodle standard
// ======================================================================
echo $OUTPUT->footer();
