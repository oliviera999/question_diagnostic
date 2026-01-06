<?php
// ======================================================================
// Moodle Question Diagnostic - Menu principal
// ======================================================================

// Inclure la configuration de Moodle.
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/category_manager.php');
require_once(__DIR__ . '/classes/question_link_checker.php');
require_once(__DIR__ . '/classes/audit_logger.php');

use local_question_diagnostic\category_manager;
use local_question_diagnostic\question_link_checker;
use local_question_diagnostic\audit_logger;

// Charger les bibliothèques Moodle nécessaires.
require_login();

// Vérification stricte : seuls les administrateurs du site peuvent accéder à cette page.
if (!is_siteadmin()) {
    throw new \moodle_exception('accessdenied', 'admin', '', 'Vous devez être administrateur du site pour accéder à cet outil.');
}

// Définir le contexte de la page (système).
$context = context_system::instance();

// Définir le titre et l'URL de la page.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/question_diagnostic/index.php'));
$pagetitle = get_string('pluginname', 'local_question_diagnostic');
$PAGE->set_title($pagetitle);
$PAGE->set_heading(local_question_diagnostic_get_heading_with_version($pagetitle));
$PAGE->set_pagelayout('report');

// Ajouter les CSS et JavaScript personnalisés
$PAGE->requires->css('/local/question_diagnostic/styles/main.css');

// ======================================================================
// Section d'en-tête Moodle standard.
echo $OUTPUT->header();

// Afficher le badge de version
echo local_question_diagnostic_render_version_badge();

// Afficher le bouton de purge des caches
echo html_writer::start_div('text-right', ['style' => 'margin-bottom: 20px;']);
echo local_question_diagnostic_render_cache_purge_button();
echo html_writer::end_div();

// ======================================================================
// ALERTE SÉCURITÉ
// ======================================================================

echo html_writer::start_div('alert alert-warning', ['style' => 'margin-bottom: 20px; border-left: 4px solid #d9534f;']);
echo '<strong>🛡️ INFORMATION IMPORTANTE</strong><br>';
echo 'Ce plugin peut modifier la base de données. Avant toute action de suppression ou réassignation, consultez la ';
echo html_writer::link(
    new moodle_url('/local/question_diagnostic/help_database_impact.php'),
    'documentation sur l\'impact base de données',
    ['target' => '_blank', 'style' => 'font-weight: bold; text-decoration: underline;']
);
echo ' pour connaître les impacts et les procédures de backup.';
echo html_writer::end_div();

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

require_once(__DIR__ . '/classes/question_analyzer.php');
use local_question_diagnostic\question_analyzer;

$category_stats = category_manager::get_global_stats();
$link_stats = question_link_checker::get_global_stats();

// 🆕 Charger les statistiques des questions (doublons, cachées, etc.)
$question_stats = question_analyzer::get_global_stats(true, true);

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
echo html_writer::tag('div', $question_stats->total_questions, ['class' => 'qd-card-value']);
echo html_writer::tag('div', 'Total', ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

// Carte 4 : Questions en doublon
$duplicate_class = $question_stats->duplicate_questions > 0 ? 'warning' : 'success';
// Afficher le nombre de groupes et le total de doublons
$duplicate_label = $question_stats->duplicate_questions;
if ($question_stats->total_duplicates > 0) {
    $duplicate_subtitle = $question_stats->total_duplicates . ' doublons totaux';
} else {
    $duplicate_subtitle = 'Aucun doublon détecté';
}
echo html_writer::start_tag('div', ['class' => 'qd-card ' . $duplicate_class]);
echo html_writer::tag('div', '⚠️ Questions en Doublon', ['class' => 'qd-card-title']);
echo html_writer::tag('div', $duplicate_label, ['class' => 'qd-card-value']);
echo html_writer::tag('div', $duplicate_subtitle, ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

// Carte 5 : Questions cachées
$hidden_class = $question_stats->hidden_questions > 0 ? 'warning' : 'success';
$hidden_label = $question_stats->hidden_questions;
$hidden_subtitle = $question_stats->hidden_questions > 0 ? 'Non visibles' : 'Toutes visibles';
echo html_writer::start_tag('div', ['class' => 'qd-card ' . $hidden_class]);
echo html_writer::tag('div', '⚠️ Questions Cachées', ['class' => 'qd-card-title']);
echo html_writer::tag('div', $hidden_label, ['class' => 'qd-card-value']);
echo html_writer::tag('div', $hidden_subtitle, ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

// Carte 6 : Questions avec liens cassés
$broken_class = $link_stats->questions_with_broken_links > 0 ? 'danger' : 'success';
echo html_writer::start_tag('div', ['class' => 'qd-card ' . $broken_class]);
echo html_writer::tag('div', 'Liens cassés', ['class' => 'qd-card-title']);
echo html_writer::tag('div', $link_stats->questions_with_broken_links, ['class' => 'qd-card-value']);
echo html_writer::tag('div', 'Questions', ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

echo html_writer::end_tag('div'); // fin dashboard

// ======================================================================
// 🆕 v1.9.35 : LIEN VERS LE CENTRE D'AIDE
// ======================================================================

echo html_writer::start_div('', ['style' => 'margin: 30px 0; text-align: center;']);
echo html_writer::link(
    new moodle_url('/local/question_diagnostic/help.php'),
    '📚 Consulter le Centre d\'Aide et la Documentation',
    ['class' => 'btn btn-lg btn-info', 'style' => 'font-size: 16px; padding: 12px 24px;']
);
echo html_writer::end_div();

// ======================================================================
// MENU DES OUTILS
// ======================================================================

echo html_writer::tag('h3', '🔧 ' . get_string('toolsmenu', 'local_question_diagnostic'), ['style' => 'margin-top: 40px;']);

echo html_writer::start_tag('div', ['class' => 'qd-tools-menu']);

// ======================================================================
// OPTION 1 : Gestion des catégories à supprimer
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
// 🆕 Catégories par cours / activité (contenant des questions)
// ======================================================================

echo html_writer::start_tag('div', ['class' => 'qd-tool-card']);

echo html_writer::start_tag('div', ['class' => 'qd-tool-icon']);
echo '🧭';
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'qd-tool-content']);

echo html_writer::tag('h4', get_string('tool_categories_by_context_title', 'local_question_diagnostic'), ['class' => 'qd-tool-title']);

echo html_writer::tag('p',
    get_string('tool_categories_by_context_desc', 'local_question_diagnostic'),
    ['class' => 'qd-tool-description']
);

$catsbyctxurl = new moodle_url('/local/question_diagnostic/categories_by_context.php');
echo html_writer::link($catsbyctxurl, get_string('tool_categories_by_context_open', 'local_question_diagnostic') . ' →', ['class' => 'qd-tool-button']);

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
// OPTION 3 : Rendre les questions cachées visibles
// ======================================================================

echo html_writer::start_tag('div', ['class' => 'qd-tool-card']);

echo html_writer::start_tag('div', ['class' => 'qd-tool-icon']);
echo '👁️';
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'qd-tool-content']);

echo html_writer::tag('h4', get_string('tool_unhide_title', 'local_question_diagnostic'), ['class' => 'qd-tool-title']);

echo html_writer::tag('p', 
    get_string('tool_unhide_desc', 'local_question_diagnostic'),
    ['class' => 'qd-tool-description']
);

// Statistiques spécifiques
try {
    // Compter TOUTES les questions cachées (false = inclure soft delete)
    $all_hidden = question_analyzer::get_hidden_questions(false, 0);
    $hidden_questions_count = count($all_hidden);
    
    if ($hidden_questions_count > 0) {
        // Calculer combien sont rendables visibles
        $hidden_ids = array_map(function($q) { return $q->id; }, $all_hidden);
        $usage = question_analyzer::get_questions_usage_by_ids($hidden_ids);
        
        $unhideable = 0;
        foreach ($all_hidden as $q) {
            $quiz_count = isset($usage[$q->id]['quiz_count']) ? $usage[$q->id]['quiz_count'] : 0;
            if ($quiz_count == 0) {
                $unhideable++;
            }
        }
        
        echo html_writer::start_tag('div', ['class' => 'qd-tool-stats']);
        echo html_writer::tag('span', '🔒 ' . $hidden_questions_count . ' questions cachées', ['class' => 'qd-tool-stat-item qd-stat-warning']);
        if ($unhideable > 0) {
            echo html_writer::tag('span', '👁️ ' . $unhideable . ' rendables visibles', ['class' => 'qd-tool-stat-item qd-stat-success']);
        }
        echo html_writer::end_tag('div');
    }
} catch (Exception $e) {
    // Silently fail
}

$unhide_url = new moodle_url('/local/question_diagnostic/unhide_questions.php');
echo html_writer::link($unhide_url, 'Gérer les questions cachées →', ['class' => 'qd-tool-button']);

echo html_writer::end_tag('div'); // fin qd-tool-content

echo html_writer::end_tag('div'); // fin qd-tool-card

// ======================================================================
// OPTION 4 : Statistiques et nettoyage des questions
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
echo html_writer::tag('span', '📊 ' . $question_stats->total_questions . ' questions', ['class' => 'qd-tool-stat-item']);

// Doublons
if ($question_stats->duplicate_questions > 0) {
    echo html_writer::tag('span', 
        '🔍 ' . $question_stats->duplicate_questions . ' groupes de doublons', 
        ['class' => 'qd-tool-stat-item qd-stat-warning']
    );
}

// Questions cachées
if ($question_stats->hidden_questions > 0) {
    echo html_writer::tag('span', 
        '🙈 ' . $question_stats->hidden_questions . ' questions cachées', 
        ['class' => 'qd-tool-stat-item qd-stat-warning']
    );
}

// Questions inutilisées
if ($question_stats->unused_questions > 0) {
    echo html_writer::tag('span', 
        '💤 ' . $question_stats->unused_questions . ' inutilisées', 
        ['class' => 'qd-tool-stat-item qd-stat-info']
    );
}

// Si tout est OK
if ($question_stats->duplicate_questions == 0 && $question_stats->hidden_questions == 0) {
    echo html_writer::tag('span', '✅ Base de questions saine', ['class' => 'qd-tool-stat-item qd-stat-success']);
}

echo html_writer::end_tag('div');

$questions_url = new moodle_url('/local/question_diagnostic/questions_cleanup.php');
echo html_writer::link($questions_url, 'Analyser les questions →', ['class' => 'qd-tool-button']);

echo html_writer::end_tag('div'); // fin qd-tool-content

echo html_writer::end_tag('div'); // fin qd-tool-card

// ======================================================================
// 🆕 v1.10.1 : OPTION 3b : Questions inutilisées
// ======================================================================

echo html_writer::start_tag('div', ['class' => 'qd-tool-card']);

echo html_writer::start_tag('div', ['class' => 'qd-tool-icon']);
echo '🗑️';
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'qd-tool-content']);

echo html_writer::tag('h4', get_string('tool_unused_questions_title', 'local_question_diagnostic'), ['class' => 'qd-tool-title']);

echo html_writer::tag('p', 
    get_string('tool_unused_questions_desc', 'local_question_diagnostic'),
    ['class' => 'qd-tool-description']
);

// Statistiques spécifiques
echo html_writer::start_tag('div', ['class' => 'qd-tool-stats']);
echo html_writer::tag('span', '📊 ' . $question_stats->total_questions . ' questions', ['class' => 'qd-tool-stat-item']);

if ($question_stats->unused_questions > 0) {
    echo html_writer::tag('span', 
        '💤 ' . $question_stats->unused_questions . ' inutilisées', 
        ['class' => 'qd-tool-stat-item qd-stat-warning']
    );
    
    // Calculer le pourcentage
    $percentage = round(($question_stats->unused_questions / $question_stats->total_questions) * 100, 1);
    echo html_writer::tag('span', 
        '📈 ' . $percentage . '% du total', 
        ['class' => 'qd-tool-stat-item qd-stat-info']
    );
} else {
    echo html_writer::tag('span', '✅ Toutes les questions sont utilisées', ['class' => 'qd-tool-stat-item qd-stat-success']);
}

echo html_writer::end_tag('div');

$unused_url = new moodle_url('/local/question_diagnostic/unused_questions.php');
echo html_writer::link($unused_url, 'Voir les questions inutilisées →', ['class' => 'qd-tool-button']);

echo html_writer::end_tag('div'); // fin qd-tool-content

echo html_writer::end_tag('div'); // fin qd-tool-card

// ======================================================================
// 🆕 v1.9.39 : OPTION 4 : Logs d'Audit
// ======================================================================

echo html_writer::start_tag('div', ['class' => 'qd-tool-card']);

echo html_writer::start_tag('div', ['class' => 'qd-tool-icon']);
echo '📋';
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'qd-tool-content']);

echo html_writer::tag('h4', 'Logs d\'Audit', ['class' => 'qd-tool-title']);

echo html_writer::tag('p', 
    'Consultez l\'historique complet de toutes les modifications effectuées sur la base de données (suppressions, fusions, déplacements). Traçabilité et compliance garanties.',
    ['class' => 'qd-tool-description']
);

// Statistiques
$recent_logs = audit_logger::get_recent_logs(10, 7);
echo html_writer::start_tag('div', ['class' => 'qd-tool-stats']);
echo html_writer::tag('span', '📊 ' . count($recent_logs) . ' actions cette semaine', ['class' => 'qd-tool-stat-item']);
echo html_writer::tag('span', '🛡️ Traçabilité complète', ['class' => 'qd-tool-stat-item qd-stat-success']);
echo html_writer::tag('span', '⏱️ 90 jours de conservation', ['class' => 'qd-tool-stat-item qd-stat-info']);
echo html_writer::end_tag('div');

$audit_url = new moodle_url('/local/question_diagnostic/audit_logs.php');
echo html_writer::link($audit_url, 'Consulter les logs →', ['class' => 'qd-tool-button']);

echo html_writer::end_tag('div'); // fin qd-tool-content

echo html_writer::end_tag('div'); // fin qd-tool-card

// ======================================================================
// 🆕 v1.9.40 : OPTION 5 : Monitoring et Health Check
// ======================================================================

echo html_writer::start_tag('div', ['class' => 'qd-tool-card']);

echo html_writer::start_tag('div', ['class' => 'qd-tool-icon']);
echo '📊';
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'qd-tool-content']);

echo html_writer::tag('h4', 'Monitoring & Health Check', ['class' => 'qd-tool-title']);

echo html_writer::tag('p', 
    'Surveillance en temps réel de l\'état de santé du plugin et de votre banque de questions. Performance, recommandations, informations système.',
    ['class' => 'qd-tool-description']
);

// Statistiques
echo html_writer::start_tag('div', ['class' => 'qd-tool-stats']);
echo html_writer::tag('span', '🏥 Health check temps réel', ['class' => 'qd-tool-stat-item qd-stat-success']);
echo html_writer::tag('span', '⚡ Tests performance', ['class' => 'qd-tool-stat-item qd-stat-info']);
echo html_writer::tag('span', '💡 Recommandations auto', ['class' => 'qd-tool-stat-item qd-stat-info']);
echo html_writer::end_tag('div');

$monitoring_url = new moodle_url('/local/question_diagnostic/monitoring.php');
echo html_writer::link($monitoring_url, 'Ouvrir le monitoring →', ['class' => 'qd-tool-button']);

echo html_writer::end_tag('div'); // fin qd-tool-content

echo html_writer::end_tag('div'); // fin qd-tool-card

// ======================================================================
// 🆕 v1.12.2 : Diagnostic général du site (BDD + ressources)
// ======================================================================

echo html_writer::start_tag('div', ['class' => 'qd-tool-card']);

echo html_writer::start_tag('div', ['class' => 'qd-tool-icon']);
echo '🩺';
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'qd-tool-content']);

echo html_writer::tag('h4', get_string('tool_site_diagnostic_title', 'local_question_diagnostic'), ['class' => 'qd-tool-title']);

echo html_writer::tag('p',
    get_string('tool_site_diagnostic_desc', 'local_question_diagnostic'),
    ['class' => 'qd-tool-description']
);

echo html_writer::start_tag('div', ['class' => 'qd-tool-stats']);
echo html_writer::tag('span', '🗄️ BDD + 🗂️ ressources', ['class' => 'qd-tool-stat-item qd-stat-info']);
echo html_writer::tag('span', '🏫 cours + contextes', ['class' => 'qd-tool-stat-item qd-stat-info']);
echo html_writer::tag('span', '🔗 liens + 🗑️ orphelins', ['class' => 'qd-tool-stat-item qd-stat-info']);
echo html_writer::end_tag('div');

$site_diag_url = new moodle_url('/local/question_diagnostic/site_diagnostic.php');
echo html_writer::link($site_diag_url, get_string('tool_site_diagnostic_open', 'local_question_diagnostic') . ' →', ['class' => 'qd-tool-button']);

echo html_writer::end_tag('div'); // fin qd-tool-content

echo html_writer::end_tag('div'); // fin qd-tool-card

// ======================================================================
// 🆕 v1.10.0 : OPTION 6 : Fichiers Orphelins
// ======================================================================

require_once(__DIR__ . '/classes/orphan_file_detector.php');
use local_question_diagnostic\orphan_file_detector;

$orphan_stats = orphan_file_detector::get_global_stats();

echo html_writer::start_tag('div', ['class' => 'qd-tool-card']);

echo html_writer::start_tag('div', ['class' => 'qd-tool-icon']);
echo '🗑️';
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'qd-tool-content']);

echo html_writer::tag('h4', get_string('orphan_files', 'local_question_diagnostic'), ['class' => 'qd-tool-title']);

echo html_writer::tag('p', 
    get_string('orphan_files_tool_desc', 'local_question_diagnostic'),
    ['class' => 'qd-tool-description']
);

// Statistiques spécifiques
echo html_writer::start_tag('div', ['class' => 'qd-tool-stats']);
echo html_writer::tag('span', '📊 ' . $orphan_stats->total_orphans . ' fichiers orphelins', ['class' => 'qd-tool-stat-item']);
if ($orphan_stats->total_orphans > 0) {
    echo html_writer::tag('span', '💾 ' . $orphan_stats->total_filesize_formatted . ' d\'espace', ['class' => 'qd-tool-stat-item qd-stat-warning']);
    if ($orphan_stats->by_age['old'] > 0) {
        echo html_writer::tag('span', '⏰ ' . $orphan_stats->by_age['old'] . ' anciens (>6 mois)', ['class' => 'qd-tool-stat-item qd-stat-danger']);
    }
} else {
    echo html_writer::tag('span', '✅ Système de fichiers sain', ['class' => 'qd-tool-stat-item qd-stat-success']);
}
echo html_writer::end_tag('div');

$orphan_url = new moodle_url('/local/question_diagnostic/orphan_files.php');
echo html_writer::link($orphan_url, 'Gérer les fichiers orphelins →', ['class' => 'qd-tool-button']);

echo html_writer::end_tag('div'); // fin qd-tool-content

echo html_writer::end_tag('div'); // fin qd-tool-card

// ======================================================================
// 🆕 v1.10.4 : OPTION 7 : Doublons Cours → Olution
// ======================================================================

require_once(__DIR__ . '/classes/olution_manager.php');
use local_question_diagnostic\olution_manager;

$olution_stats = olution_manager::get_duplicate_stats();

echo html_writer::start_tag('div', ['class' => 'qd-tool-card']);

echo html_writer::start_tag('div', ['class' => 'qd-tool-icon']);
echo '🔄';
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'qd-tool-content']);

echo html_writer::tag('h4', get_string('olution_duplicates_title', 'local_question_diagnostic'), ['class' => 'qd-tool-title']);

echo html_writer::tag('p', 
    'Détecte tous les doublons du site et les déplace vers les sous-catégories d\'Olution (catégorie de questions système). Déplace chaque question vers la sous-catégorie la plus profonde où se trouve déjà un doublon.',
    ['class' => 'qd-tool-description']
);

// Statistiques spécifiques
echo html_writer::start_tag('div', ['class' => 'qd-tool-stats']);
if ($olution_stats->olution_exists) {
    echo html_writer::tag('span', '📚 ' . $olution_stats->olution_courses_count . ' sous-catégorie(s)', ['class' => 'qd-tool-stat-item']);
    if ($olution_stats->total_duplicates > 0) {
        echo html_writer::tag('span', '📊 ' . $olution_stats->total_duplicates . ' question(s) en doublon', ['class' => 'qd-tool-stat-item qd-stat-warning']);
    }
    if ($olution_stats->movable_questions > 0) {
        echo html_writer::tag('span', '✅ ' . $olution_stats->movable_questions . ' déplaçable(s)', ['class' => 'qd-tool-stat-item qd-stat-success']);
    }
} else {
    echo html_writer::tag('span', '⚠️ Catégorie Olution non trouvée', ['class' => 'qd-tool-stat-item qd-stat-warning']);
}
echo html_writer::end_tag('div');

$olution_url = new moodle_url('/local/question_diagnostic/olution_duplicates.php');
echo html_writer::link($olution_url, 'Gérer les doublons Olution →', ['class' => 'qd-tool-button']);

echo html_writer::end_tag('div'); // fin qd-tool-content

echo html_writer::end_tag('div'); // fin qd-tool-card

// ======================================================================
// 🆕 v1.12.x : OPTION 8 : Fusion de questions (doublons stricts)
// ======================================================================

echo html_writer::start_tag('div', ['class' => 'qd-tool-card']);

echo html_writer::start_tag('div', ['class' => 'qd-tool-icon']);
echo '🧬';
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'qd-tool-content']);

echo html_writer::tag('h4', get_string('question_merge_tool_title', 'local_question_diagnostic'), ['class' => 'qd-tool-title']);

echo html_writer::tag('p',
    get_string('question_merge_tool_desc', 'local_question_diagnostic'),
    ['class' => 'qd-tool-description']
);

echo html_writer::start_tag('div', ['class' => 'qd-tool-stats']);
echo html_writer::tag('span',
    '🔍 ' . (int)($question_stats->duplicate_questions ?? 0) . ' ' . get_string('question_merge_tool_stat_groups', 'local_question_diagnostic'),
    ['class' => 'qd-tool-stat-item ' . ((int)($question_stats->duplicate_questions ?? 0) > 0 ? 'qd-stat-warning' : 'qd-stat-success')]
);
if (!empty($question_stats->total_duplicates)) {
    echo html_writer::tag('span',
        '📊 ' . (int)$question_stats->total_duplicates . ' ' . get_string('question_merge_tool_stat_duplicates', 'local_question_diagnostic'),
        ['class' => 'qd-tool-stat-item qd-stat-warning']
    );
}
echo html_writer::end_tag('div');

$merge_url = new moodle_url('/local/question_diagnostic/question_merge.php');
echo html_writer::link($merge_url, get_string('question_merge_tool_open', 'local_question_diagnostic') . ' →', ['class' => 'qd-tool-button']);

echo html_writer::end_tag('div'); // fin qd-tool-content

echo html_writer::end_tag('div'); // fin qd-tool-card

// ======================================================================
// OPTION 9 : Page de test
// ======================================================================

echo html_writer::start_tag('div', ['class' => 'qd-tool-card']);

echo html_writer::start_tag('div', ['class' => 'qd-tool-icon']);
echo '🧪';
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'qd-tool-content']);

echo html_writer::tag('h4', get_string('test_page_title', 'local_question_diagnostic'), ['class' => 'qd-tool-title']);

echo html_writer::tag('p', 
    get_string('test_page_desc', 'local_question_diagnostic'),
    ['class' => 'qd-tool-description']
);

$test_url = new moodle_url('/local/question_diagnostic/test.php');
echo html_writer::link($test_url, 'Ouvrir la page de test →', ['class' => 'qd-tool-button']);

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
