<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Interface de monitoring et health check
 * 
 * 🆕 v1.9.40 : TODO BASSE #5 - Interface monitoring
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/category_manager.php');
require_once(__DIR__ . '/classes/question_analyzer.php');
require_once(__DIR__ . '/classes/question_link_checker.php');
require_once(__DIR__ . '/classes/audit_logger.php');

use local_question_diagnostic\category_manager;
use local_question_diagnostic\question_analyzer;
use local_question_diagnostic\question_link_checker;
use local_question_diagnostic\audit_logger;

require_login();

if (!is_siteadmin()) {
    print_error('accessdenied', 'admin');
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/question_diagnostic/monitoring.php'));
$PAGE->set_title('Monitoring et Health Check');
$PAGE->set_heading('📊 Monitoring - Plugin Question Diagnostic');

// Auto-refresh toutes les 30 secondes (optionnel)
$refresh = optional_param('refresh', 0, PARAM_INT);
if ($refresh) {
    $PAGE->requires->js_init_code('setTimeout(function() { window.location.reload(); }, 30000);');
}

echo $OUTPUT->header();

// Afficher le badge de version
echo local_question_diagnostic_render_version_badge();

// Afficher le bouton de purge des caches
echo html_writer::start_div('text-right', ['style' => 'margin-bottom: 20px;']);
echo local_question_diagnostic_render_cache_purge_button();
echo html_writer::end_div();

// 🆕 v1.9.44 : Boutons d'action avec lien retour hiérarchique
echo html_writer::start_div('', ['style' => 'margin-bottom: 20px; display: flex; gap: 10px;']);
echo local_question_diagnostic_render_back_link('monitoring.php');

$refresh_url = new moodle_url('/local/question_diagnostic/monitoring.php', ['refresh' => $refresh ? 0 : 1]);
$refresh_text = $refresh ? '⏸️ Désactiver auto-refresh' : '🔄 Activer auto-refresh (30s)';
echo html_writer::link($refresh_url, $refresh_text, ['class' => 'btn btn-info']);

echo html_writer::end_div();

// Titre
echo html_writer::tag('h2', '📊 Health Check du Plugin');

echo html_writer::start_div('alert alert-info', ['style' => 'margin: 20px 0;']);
echo 'Cette page affiche l\'état de santé global du plugin et de votre banque de questions.';
if ($refresh) {
    echo '<br><strong>🔄 Auto-refresh activé</strong> : La page se recharge automatiquement toutes les 30 secondes.';
}
echo html_writer::end_div();

// =========================================================================
// SECTION 1 : ÉTAT GÉNÉRAL
// =========================================================================

echo html_writer::tag('h3', '🏥 État Général');

$cat_stats = category_manager::get_global_stats();
$quest_stats = question_analyzer::get_global_stats();
$link_stats = question_link_checker::get_global_stats();

echo html_writer::start_div('', ['style' => 'display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin: 20px 0;']);

// Carte Catégories
$cat_health = 'success';
if ($cat_stats->orphan_categories > 0 || $cat_stats->empty_categories > 10) {
    $cat_health = 'warning';
}

echo html_writer::start_div('qd-card ' . $cat_health, ['style' => 'padding: 20px;']);
echo html_writer::tag('h4', '📂 Catégories');
echo html_writer::tag('div', $cat_stats->total_categories, ['style' => 'font-size: 36px; font-weight: bold; margin: 10px 0;']);
echo html_writer::tag('div', 'Total catégories', ['style' => 'color: #666;']);
echo html_writer::start_tag('div', ['style' => 'margin-top: 15px; font-size: 13px;']);
echo '• Vides : ' . $cat_stats->empty_categories . '<br>';
echo '• Orphelines : ' . $cat_stats->orphan_categories . '<br>';
echo '• Protégées : ' . $cat_stats->total_protected;
echo html_writer::end_tag('div');
echo html_writer::end_div();

// Carte Questions
$quest_health = 'success';
if ($quest_stats->hidden_questions > ($quest_stats->total_questions * 0.2)) {
    $quest_health = 'warning';
}

echo html_writer::start_div('qd-card ' . $quest_health, ['style' => 'padding: 20px;']);
echo html_writer::tag('h4', '❓ Questions');
echo html_writer::tag('div', number_format($quest_stats->total_questions), ['style' => 'font-size: 36px; font-weight: bold; margin: 10px 0;']);
echo html_writer::tag('div', 'Total questions', ['style' => 'color: #666;']);
echo html_writer::start_tag('div', ['style' => 'margin-top: 15px; font-size: 13px;']);
echo '• Cachées : ' . $quest_stats->hidden_questions . '<br>';
echo '• Avec tentatives : ' . $quest_stats->questions_with_attempts . '<br>';
echo '• Utilisées dans quiz : ' . $quest_stats->questions_in_quiz;
echo html_writer::end_tag('div');
echo html_writer::end_div();

// Carte Liens Cassés
$link_health = $link_stats->questions_with_broken_links > 0 ? 'danger' : 'success';

echo html_writer::start_div('qd-card ' . $link_health, ['style' => 'padding: 20px;']);
echo html_writer::tag('h4', '🔗 Liens Cassés');
echo html_writer::tag('div', $link_stats->questions_with_broken_links, ['style' => 'font-size: 36px; font-weight: bold; margin: 10px 0;']);
echo html_writer::tag('div', 'Questions affectées', ['style' => 'color: #666;']);
echo html_writer::start_tag('div', ['style' => 'margin-top: 15px; font-size: 13px;']);
echo '• Total liens cassés : ' . $link_stats->total_broken_links . '<br>';
$percentage = $quest_stats->total_questions > 0 ? 
    round(($link_stats->questions_with_broken_links / $quest_stats->total_questions) * 100, 1) : 0;
echo '• Pourcentage : ' . $percentage . '%';
echo html_writer::end_tag('div');
echo html_writer::end_div();

// Carte Logs d'Audit
$recent_logs = audit_logger::get_recent_logs(10, 7);

echo html_writer::start_div('qd-card', ['style' => 'padding: 20px;']);
echo html_writer::tag('h4', '📋 Activité Récente');
echo html_writer::tag('div', count($recent_logs), ['style' => 'font-size: 36px; font-weight: bold; margin: 10px 0;']);
echo html_writer::tag('div', 'Actions cette semaine', ['style' => 'color: #666;']);
echo html_writer::tag('div', html_writer::link(
    new moodle_url('/local/question_diagnostic/audit_logs.php'),
    'Voir détails →',
    ['style' => 'margin-top: 15px; display: inline-block;']
));
echo html_writer::end_div();

echo html_writer::end_div(); // Fin grid

// =========================================================================
// SECTION 2 : RECOMMANDATIONS
// =========================================================================

echo html_writer::tag('h3', '💡 Recommandations', ['style' => 'margin-top: 40px;']);

$recommendations = [];

// Recommandation 1 : Catégories orphelines
if ($cat_stats->orphan_categories > 0) {
    $recommendations[] = [
        'type' => 'warning',
        'title' => 'Catégories orphelines détectées',
        'message' => 'Vous avez <strong>' . $cat_stats->orphan_categories . ' catégorie(s) orpheline(s)</strong> (contexte invalide). Consultez la page de gestion des catégories pour les identifier.',
        'action' => 'Gérer les catégories',
        'url' => new moodle_url('/local/question_diagnostic/categories.php')
    ];
}

// Recommandation 2 : Liens cassés
if ($link_stats->questions_with_broken_links > 0) {
    $recommendations[] = [
        'type' => 'danger',
        'title' => 'Liens cassés détectés',
        'message' => '<strong>' . $link_stats->questions_with_broken_links . ' question(s)</strong> contiennent des liens cassés. Cela peut affecter l\'expérience des étudiants.',
        'action' => 'Vérifier les liens',
        'url' => new moodle_url('/local/question_diagnostic/broken_links.php')
    ];
}

// Recommandation 3 : Trop de catégories vides
if ($cat_stats->empty_categories > 20) {
    $recommendations[] = [
        'type' => 'info',
        'title' => 'Nombreuses catégories vides',
        'message' => 'Vous avez <strong>' . $cat_stats->empty_categories . ' catégorie(s) vide(s)</strong>. Envisagez de les nettoyer pour simplifier votre banque de questions.',
        'action' => 'Nettoyer',
        'url' => new moodle_url('/local/question_diagnostic/categories.php')
    ];
}

// Recommandation 4 : Grosse base sans pagination
if ($quest_stats->total_questions > 10000) {
    $recommendations[] = [
        'type' => 'info',
        'title' => 'Grosse base de données détectée',
        'message' => 'Vous avez <strong>' . number_format($quest_stats->total_questions) . ' questions</strong>. Assurez-vous d\'utiliser la pagination serveur (v1.9.30) pour des performances optimales.',
        'action' => 'Voir optimisations',
        'url' => new moodle_url('/local/question_diagnostic/help.php')
    ];
}

// Afficher les recommandations
if (empty($recommendations)) {
    echo html_writer::start_div('alert alert-success', ['style' => 'margin: 20px 0;']);
    echo '✅ <strong>Tout va bien !</strong> Aucune recommandation particulière. Votre banque de questions est en bonne santé.';
    echo html_writer::end_div();
} else {
    foreach ($recommendations as $rec) {
        echo html_writer::start_div('alert alert-' . $rec['type'], ['style' => 'margin: 20px 0; padding: 20px;']);
        echo html_writer::tag('h4', '⚠️ ' . $rec['title'], ['style' => 'margin-top: 0;']);
        echo html_writer::tag('p', $rec['message']);
        echo html_writer::link($rec['url'], $rec['action'] . ' →', ['class' => 'btn btn-sm btn-' . $rec['type']]);
        echo html_writer::end_div();
    }
}

// =========================================================================
// SECTION 3 : INFORMATIONS SYSTÈME
// =========================================================================

echo html_writer::tag('h3', '⚙️ Informations Système', ['style' => 'margin-top: 40px;']);

echo html_writer::start_tag('table', ['class' => 'table table-striped', 'style' => 'margin: 20px 0;']);
echo html_writer::start_tag('tbody');

// Version plugin
echo html_writer::start_tag('tr');
echo html_writer::tag('td', '<strong>Version du plugin</strong>');
$plugin = new \stdClass();
require(__DIR__ . '/version.php');
echo html_writer::tag('td', $plugin->release);
echo html_writer::end_tag('tr');

// Version Moodle
echo html_writer::start_tag('tr');
echo html_writer::tag('td', '<strong>Version Moodle</strong>');
echo html_writer::tag('td', $CFG->release . ' (' . $CFG->version . ')');
echo html_writer::end_tag('tr');

// PHP
echo html_writer::start_tag('tr');
echo html_writer::tag('td', '<strong>Version PHP</strong>');
echo html_writer::tag('td', PHP_VERSION);
echo html_writer::end_tag('tr');

// Base de données
echo html_writer::start_tag('tr');
echo html_writer::tag('td', '<strong>Base de données</strong>');
echo html_writer::tag('td', $CFG->dbtype . ' ' . $DB->get_server_info()['version']);
echo html_writer::end_tag('tr');

// Mémoire PHP
echo html_writer::start_tag('tr');
echo html_writer::tag('td', '<strong>Mémoire PHP</strong>');
$memory_limit = ini_get('memory_limit');
$memory_usage = round(memory_get_usage(true) / 1024 / 1024, 2);
echo html_writer::tag('td', $memory_usage . ' MB utilisés / ' . $memory_limit . ' limite');
echo html_writer::end_tag('tr');

// Dernière exécution tâche planifiée
echo html_writer::start_tag('tr');
echo html_writer::tag('td', '<strong>Dernière tâche planifiée</strong>');
try {
    $task = $DB->get_record('task_scheduled', [
        'classname' => '\\local_question_diagnostic\\task\\scan_broken_links'
    ]);
    if ($task && $task->lastruntime) {
        $last_run = userdate($task->lastruntime);
        $next_run = userdate($task->nextruntime);
        echo html_writer::tag('td', 'Dernière : ' . $last_run . '<br>Prochaine : ' . $next_run);
    } else {
        echo html_writer::tag('td', 'Jamais exécutée');
    }
} catch (\Exception $e) {
    echo html_writer::tag('td', 'Information non disponible');
}
echo html_writer::end_tag('tr');

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');

// =========================================================================
// SECTION 4 : PERFORMANCE EN TEMPS RÉEL
// =========================================================================

echo html_writer::tag('h3', '⚡ Performance en Temps Réel', ['style' => 'margin-top: 40px;']);

$start_time = microtime(true);

// Test 1 : Stats catégories
$time_start = microtime(true);
$test_cat_stats = category_manager::get_global_stats();
$time_cat = round((microtime(true) - $time_start) * 1000, 2);

// Test 2 : Stats questions  
$time_start = microtime(true);
$test_quest_stats = question_analyzer::get_global_stats();
$time_quest = round((microtime(true) - $time_start) * 1000, 2);

// Test 3 : 10 questions avec stats
$time_start = microtime(true);
$test_10_questions = question_analyzer::get_all_questions_with_stats(false, 10, 0);
$time_10q = round((microtime(true) - $time_start) * 1000, 2);

$total_time = round((microtime(true) - $start_time) * 1000, 2);

echo html_writer::start_tag('table', ['class' => 'table table-bordered', 'style' => 'margin: 20px 0;']);
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
echo html_writer::tag('th', 'Opération');
echo html_writer::tag('th', 'Temps (ms)');
echo html_writer::tag('th', 'Performance');
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');

echo html_writer::start_tag('tbody');

// Ligne 1
echo html_writer::start_tag('tr');
echo html_writer::tag('td', 'Stats globales catégories');
echo html_writer::tag('td', $time_cat . ' ms');
$perf_cat = $time_cat < 100 ? '✅ Excellente' : ($time_cat < 500 ? '⚠️ Bonne' : '❌ Lente');
echo html_writer::tag('td', $perf_cat);
echo html_writer::end_tag('tr');

// Ligne 2
echo html_writer::start_tag('tr');
echo html_writer::tag('td', 'Stats globales questions');
echo html_writer::tag('td', $time_quest . ' ms');
$perf_quest = $time_quest < 200 ? '✅ Excellente' : ($time_quest < 1000 ? '⚠️ Bonne' : '❌ Lente');
echo html_writer::tag('td', $perf_quest);
echo html_writer::end_tag('tr');

// Ligne 3
echo html_writer::start_tag('tr');
echo html_writer::tag('td', 'Chargement 10 questions avec stats');
echo html_writer::tag('td', $time_10q . ' ms');
$perf_10q = $time_10q < 300 ? '✅ Excellente' : ($time_10q < 1500 ? '⚠️ Bonne' : '❌ Lente');
echo html_writer::tag('td', $perf_10q);
echo html_writer::end_tag('tr');

// Total
echo html_writer::start_tag('tr', ['style' => 'background: #f8f9fa; font-weight: bold;']);
echo html_writer::tag('td', 'Temps total page');
echo html_writer::tag('td', $total_time . ' ms');
$perf_total = $total_time < 1000 ? '✅ Excellente' : ($total_time < 3000 ? '⚠️ Acceptable' : '❌ Lente');
echo html_writer::tag('td', $perf_total);
echo html_writer::end_tag('tr');

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');

// =========================================================================
// SECTION 5 : ACTIONS RAPIDES
// =========================================================================

echo html_writer::tag('h3', '🚀 Actions Rapides', ['style' => 'margin-top: 40px;']);

echo html_writer::start_div('', ['style' => 'display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;']);

// Bouton Purger caches
echo html_writer::link(
    new moodle_url('/admin/purgecaches.php', ['confirm' => 1, 'sesskey' => sesskey(), 'returnurl' => $PAGE->url->out_as_local_url()]),
    '🔄 Purger tous les caches',
    ['class' => 'btn btn-warning btn-block', 'style' => 'padding: 15px;']
);

// Bouton Tests PHPUnit
echo html_writer::tag('div',
    '<strong>🧪 Tests PHPUnit</strong><br><small>Exécuter depuis CLI :<br><code>vendor/bin/phpunit local/question_diagnostic/tests/</code></small>',
    ['class' => 'alert alert-light', 'style' => 'padding: 15px; margin: 0;']
);

// Bouton Benchmarks
echo html_writer::tag('div',
    '<strong>📊 Benchmarks</strong><br><small>Exécuter depuis CLI :<br><code>php local/question_diagnostic/tests/performance_benchmarks.php</code></small>',
    ['class' => 'alert alert-light', 'style' => 'padding: 15px; margin: 0;']
);

echo html_writer::end_div();

// Footer
echo html_writer::start_div('', ['style' => 'margin: 40px 0; text-align: center; padding: 20px; background: #f8f9fa; border-radius: 5px;']);
echo html_writer::tag('p', '<strong>📖 Documentation complète</strong> : ' .
    html_writer::link(new moodle_url('/local/question_diagnostic/help.php'), 'Centre d\'Aide'));
echo html_writer::tag('p', 'Dernière mise à jour : ' . date('Y-m-d H:i:s'), ['style' => 'color: #666; font-size: 12px;']);
echo html_writer::end_div();

echo $OUTPUT->footer();

