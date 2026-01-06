<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Page d'aide : Vue d'ensemble des fonctionnalités
 * 
 * 🆕 v1.9.35 : Quick Win #1 - Page HTML au lieu de lien .md
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

if (!is_siteadmin()) {
    throw new \moodle_exception('accessdenied', 'admin');
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/question_diagnostic/help_features.php'));
$PAGE->set_title('Fonctionnalités du Plugin');
$PAGE->set_heading('📚 Fonctionnalités du Plugin Question Diagnostic');

echo $OUTPUT->header();

// Afficher le badge de version
echo local_question_diagnostic_render_version_badge();

// Afficher le bouton de purge des caches
echo html_writer::start_div('text-right', ['style' => 'margin-bottom: 20px;']);
echo local_question_diagnostic_render_cache_purge_button();
echo html_writer::end_div();

// Titre et introduction
echo html_writer::tag('h2', '🎯 Vue d\'Ensemble des Fonctionnalités');

echo html_writer::start_div('alert alert-info', ['style' => 'margin: 20px 0;']);
echo html_writer::tag('p', 'Le plugin Question Diagnostic offre 3 outils principaux pour gérer et diagnostiquer votre banque de questions Moodle.');
echo html_writer::end_div();

// Fonctionnalité 1 : Gestion des Catégories
echo html_writer::tag('h3', '📂 1. Gestion des Catégories de Questions');

echo html_writer::start_div('qd-card', ['style' => 'padding: 20px; margin: 20px 0; background: white; border: 1px solid #ddd; border-radius: 8px;']);
echo html_writer::tag('h4', '✨ Fonctionnalités');
echo html_writer::start_tag('ul');
echo html_writer::tag('li', '<strong>Dashboard statistiques</strong> : Vues d\'ensemble rapides (total, vides, orphelines, doublons)');
echo html_writer::tag('li', '<strong>Détection automatique</strong> : Catégories vides, orphelines, en doublon');
echo html_writer::tag('li', '<strong>Filtres puissants</strong> : Par statut, contexte, nom, ID');
echo html_writer::tag('li', '<strong>Actions groupées</strong> : Supprimer plusieurs catégories en une fois');
echo html_writer::tag('li', '<strong>Protections</strong> : Catégories système protégées automatiquement');
echo html_writer::tag('li', '<strong>Export CSV</strong> : Exporter les statistiques');
echo html_writer::tag('li', '<strong>Fusion</strong> : Fusionner deux catégories (avec transactions SQL v1.9.30)');
echo html_writer::end_tag('ul');

echo html_writer::tag('h4', '🛡️ Protections Actives (v1.9.29+)');
echo html_writer::start_tag('ul');
echo html_writer::tag('li', '✅ Catégories "Default for..." (système Moodle)');
echo html_writer::tag('li', '✅ Catégories avec description (usage documenté)');
echo html_writer::tag('li', '✅ Catégories racine (parent = 0) - Toutes protégées');
echo html_writer::end_tag('ul');

echo html_writer::tag('p', html_writer::link(
    new moodle_url('/local/question_diagnostic/categories.php'),
    '→ Accéder à la Gestion des Catégories',
    ['class' => 'btn btn-primary']
));
echo html_writer::end_div();

// Fonctionnalité 2 : Analyse des Questions
echo html_writer::tag('h3', '🔍 2. Analyse et Nettoyage des Questions');

echo html_writer::start_div('qd-card', ['style' => 'padding: 20px; margin: 20px 0; background: white; border: 1px solid #ddd; border-radius: 8px;']);
echo html_writer::tag('h4', '✨ Fonctionnalités');
echo html_writer::start_tag('ul');
echo html_writer::tag('li', '<strong>Statistiques globales</strong> : Total, cachées, utilisées, avec tentatives');
echo html_writer::tag('li', '<strong>Détection de doublons</strong> : Questions identiques (nom + type) - v1.9.28');
echo html_writer::tag('li', '<strong>Usage dans quiz</strong> : Détection automatique (compatible Moodle 4.5+)');
echo html_writer::tag('li', '<strong>Suppression sécurisée</strong> : Règles strictes pour éviter pertes de données');
echo html_writer::tag('li', '<strong>Pagination serveur</strong> : Fonctionne avec 100k+ questions (v1.9.30)');
echo html_writer::tag('li', '<strong>Filtres avancés</strong> : Par usage, type, statut, catégorie');
echo html_writer::tag('li', '<strong>Actions groupées</strong> : Supprimer plusieurs questions (max 500)');
echo html_writer::end_tag('ul');

echo html_writer::tag('h4', '🛡️ Règles de Suppression');
echo html_writer::start_tag('ul');
echo html_writer::tag('li', '❌ <strong>INTERDITE</strong> : Question utilisée dans un quiz');
echo html_writer::tag('li', '❌ <strong>INTERDITE</strong> : Question avec tentatives d\'étudiants');
echo html_writer::tag('li', '❌ <strong>INTERDITE</strong> : Question unique (pas de doublon)');
echo html_writer::tag('li', '✅ <strong>AUTORISÉE</strong> : Question en doublon ET inutilisée');
echo html_writer::end_tag('ul');

echo html_writer::tag('p', html_writer::link(
    new moodle_url('/local/question_diagnostic/questions_cleanup.php', ['loadstats' => 1]),
    '→ Accéder à l\'Analyse des Questions',
    ['class' => 'btn btn-primary']
));
echo html_writer::end_div();

// Fonctionnalité 3 : Vérification Liens Cassés
echo html_writer::tag('h3', '🔗 3. Vérification des Liens Cassés');

echo html_writer::start_div('qd-card', ['style' => 'padding: 20px; margin: 20px 0; background: white; border: 1px solid #ddd; border-radius: 8px;']);
echo html_writer::tag('h4', '✨ Fonctionnalités');
echo html_writer::start_tag('ul');
echo html_writer::tag('li', '<strong>Scan automatique</strong> : Analyse toutes les questions');
echo html_writer::tag('li', '<strong>Détection multi-types</strong> : Images, fichiers, pluginfiles');
echo html_writer::tag('li', '<strong>Support plugins tiers</strong> : Drag and drop, markers, etc.');
echo html_writer::tag('li', '<strong>Statistiques détaillées</strong> : Par type de question');
echo html_writer::tag('li', '<strong>Filtres</strong> : Par type de problème, de question');
echo html_writer::tag('li', '<strong>Liens directs</strong> : Accès rapide à la banque de questions');
echo html_writer::end_tag('ul');

echo html_writer::tag('h4', '🔎 Types de Problèmes Détectés');
echo html_writer::start_tag('ul');
echo html_writer::tag('li', '🖼️ Images manquantes dans le texte');
echo html_writer::tag('li', '📎 Fichiers manquants dans les réponses');
echo html_writer::tag('li', '🎯 Images de fond manquantes (drag and drop)');
echo html_writer::tag('li', '💬 Fichiers manquants dans les feedbacks');
echo html_writer::tag('li', '🔗 Tous les liens pluginfile.php cassés');
echo html_writer::end_tag('ul');

echo html_writer::tag('p', html_writer::link(
    new moodle_url('/local/question_diagnostic/broken_links.php'),
    '→ Accéder à la Vérification des Liens',
    ['class' => 'btn btn-primary']
));
echo html_writer::end_div();

// Nouveautés v1.9.30+
echo html_writer::tag('h3', '🆕 Nouveautés v1.9.30+ : Optimisations Gros Sites');

echo html_writer::start_div('alert alert-success', ['style' => 'margin: 20px 0; padding: 20px; border-left: 4px solid #28a745;']);
echo html_writer::tag('h4', '⚡ Performance', ['style' => 'margin-top: 0; color: #28a745;']);
echo html_writer::start_tag('ul');
echo html_writer::tag('li', '<strong>Pagination serveur</strong> : Fonctionne avec 100k+ questions (v1.9.30)');
echo html_writer::tag('li', '<strong>Mémoire constante</strong> : O(per_page) au lieu de O(n)');
echo html_writer::tag('li', '<strong>Batch loading</strong> : -80% de requêtes SQL (v1.9.27)');
echo html_writer::end_tag('ul');

echo html_writer::tag('h4', '🔒 Robustesse', ['style' => 'color: #28a745;']);
echo html_writer::start_tag('ul');
echo html_writer::tag('li', '<strong>Transactions SQL</strong> : Rollback automatique si erreur (v1.9.30)');
echo html_writer::tag('li', '<strong>Intégrité garantie</strong> : Soit tout réussit, soit rien');
echo html_writer::tag('li', '<strong>Limites strictes</strong> : Max 100/500 éléments par opération (v1.9.27)');
echo html_writer::end_tag('ul');

echo html_writer::tag('h4', '✅ Qualité', ['style' => 'color: #28a745;']);
echo html_writer::start_tag('ul');
echo html_writer::tag('li', '<strong>21 tests PHPUnit</strong> : 70% de couverture de code (v1.9.30)');
echo html_writer::tag('li', '<strong>Architecture OO</strong> : Classe abstraite base_action (v1.9.33)');
echo html_writer::tag('li', '<strong>Documentation organisée</strong> : 79 fichiers dans /docs (v1.9.31)');
echo html_writer::end_tag('ul');
echo html_writer::end_div();

// Documentation et ressources
echo html_writer::tag('h3', '📖 Documentation et Ressources');

echo html_writer::start_div('qd-card', ['style' => 'padding: 20px; margin: 20px 0; background: white; border: 1px solid #ddd; border-radius: 8px;']);
echo html_writer::start_tag('ul');
echo html_writer::tag('li', html_writer::link(
    new moodle_url('/local/question_diagnostic/docs/README.md'),
    '📚 Index Complet de la Documentation'
) . ' (79 fichiers organisés)');

echo html_writer::tag('li', html_writer::link(
    'https://github.com/oliviera999/question_diagnostic',
    '🔗 Dépôt GitHub'
, ['target' => '_blank']));

echo html_writer::tag('li', html_writer::link(
    new moodle_url('/local/question_diagnostic/help_database_impact.php'),
    '📊 Impact sur la Base de Données'
));

echo html_writer::tag('li', html_writer::link(
    new moodle_url('/local/question_diagnostic/docs/technical/MOODLE_COMPATIBILITY_POLICY.md'),
    '🎯 Politique de Compatibilité Moodle'
));

echo html_writer::tag('li', html_writer::link(
    new moodle_url('/local/question_diagnostic/docs/DEVELOPER_GUIDE.md'),
    '🛠️ Guide du Développeur'
) . ' (v1.9.34)');

echo html_writer::end_tag('ul');
echo html_writer::end_div();

// 🆕 v1.9.44 : Lien retour hiérarchique (vers help.php)
echo html_writer::start_div('', ['style' => 'margin: 30px 0; text-align: center;']);
echo local_question_diagnostic_render_back_link('help_features.php');
echo html_writer::end_div();

echo $OUTPUT->footer();

