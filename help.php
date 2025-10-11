<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Page d'aide principale du plugin
 * 
 * 🆕 v1.9.35 : Quick Win #1 - Centre d'aide HTML
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

if (!is_siteadmin()) {
    print_error('accessdenied', 'admin');
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/question_diagnostic/help.php'));
$PAGE->set_title('Centre d\'Aide');
$PAGE->set_heading('📚 Centre d\'Aide - Plugin Question Diagnostic');

echo $OUTPUT->header();

// Titre
echo html_writer::tag('h2', '🎯 Bienvenue dans le Centre d\'Aide');

echo html_writer::start_div('alert alert-info', ['style' => 'margin: 20px 0;']);
echo html_writer::tag('p', '<strong>Version du plugin :</strong> v1.9.34');
echo html_writer::tag('p', 'Consultez les guides ci-dessous pour tirer le meilleur parti du plugin.');
echo html_writer::end_div();

// Grille de cartes d'aide
echo html_writer::start_div('', ['style' => 'display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 30px 0;']);

// Card 1 : Fonctionnalités
echo html_writer::start_div('qd-card', ['style' => 'padding: 20px; background: white; border: 2px solid #0f6cbf; border-radius: 8px;']);
echo html_writer::tag('h3', '📋 Fonctionnalités', ['style' => 'color: #0f6cbf; margin-top: 0;']);
echo html_writer::tag('p', 'Vue d\'ensemble complète de toutes les fonctionnalités du plugin : gestion catégories, analyse questions, vérification liens.');
echo html_writer::tag('p', html_writer::link(
    new moodle_url('/local/question_diagnostic/help_features.php'),
    'Consulter le guide →',
    ['class' => 'btn btn-sm btn-primary']
), ['style' => 'margin-top: 15px;']);
echo html_writer::end_div();

// Card 2 : Impact BDD
echo html_writer::start_div('qd-card', ['style' => 'padding: 20px; background: white; border: 2px solid #28a745; border-radius: 8px;']);
echo html_writer::tag('h3', '📊 Impact Base de Données', ['style' => 'color: #28a745; margin-top: 0;']);
echo html_writer::tag('p', 'Comprendre l\'impact du plugin sur votre base de données, les bonnes pratiques de sauvegarde et les tables utilisées.');
echo html_writer::tag('p', html_writer::link(
    new moodle_url('/local/question_diagnostic/help_database_impact.php'),
    'Consulter le guide →',
    ['class' => 'btn btn-sm btn-success']
), ['style' => 'margin-top: 15px;']);
echo html_writer::end_div();

// Card 3 : Performance
echo html_writer::start_div('qd-card', ['style' => 'padding: 20px; background: white; border: 2px solid #f0ad4e; border-radius: 8px;']);
echo html_writer::tag('h3', '⚡ Optimisations Gros Sites', ['style' => 'color: #f0ad4e; margin-top: 0;']);
echo html_writer::tag('p', 'Découvrez les optimisations v1.9.30 pour gros sites : pagination serveur, transactions SQL, tests automatisés.');
echo html_writer::tag('p', html_writer::link(
    'https://github.com/oliviera999/question_diagnostic/blob/master/docs/performance/GROS_SITES_OPTIMISATIONS_v1.9.30.md',
    'Consulter le guide →',
    ['class' => 'btn btn-sm btn-warning', 'target' => '_blank']
), ['style' => 'margin-top: 15px;']);
echo html_writer::end_div();

// Card 4 : Installation
echo html_writer::start_div('qd-card', ['style' => 'padding: 20px; background: white; border: 2px solid #5bc0de; border-radius: 8px;']);
echo html_writer::tag('h3', '📦 Installation & Déploiement', ['style' => 'color: #5bc0de; margin-top: 0;']);
echo html_writer::tag('p', 'Guide complet pour installer, configurer et déployer le plugin sur votre site Moodle.');
echo html_writer::tag('p', html_writer::link(
    'https://github.com/oliviera999/question_diagnostic/blob/master/docs/installation/INSTALLATION.md',
    'Guide d\'installation →',
    ['class' => 'btn btn-sm btn-info', 'target' => '_blank']
), ['style' => 'margin-top: 15px;']);
echo html_writer::end_div();

// Card 5 : Compatibilité
echo html_writer::start_div('qd-card', ['style' => 'padding: 20px; background: white; border: 2px solid #6c757d; border-radius: 8px;']);
echo html_writer::tag('h3', '🎯 Compatibilité Moodle', ['style' => 'color: #6c757d; margin-top: 0;']);
echo html_writer::tag('p', '<strong>Versions supportées</strong> : Moodle 4.0, 4.1 LTS, 4.3, 4.4, 4.5 (recommandé).<br><strong>Non supporté</strong> : Moodle 3.x');
echo html_writer::tag('p', html_writer::link(
    'https://github.com/oliviera999/question_diagnostic/blob/master/docs/technical/MOODLE_COMPATIBILITY_POLICY.md',
    'Politique de compatibilité →',
    ['class' => 'btn btn-sm btn-secondary', 'target' => '_blank']
), ['style' => 'margin-top: 15px;']);
echo html_writer::end_div();

// Card 6 : Développeurs
echo html_writer::start_div('qd-card', ['style' => 'padding: 20px; background: white; border: 2px solid #d9534f; border-radius: 8px;']);
echo html_writer::tag('h3', '🛠️ Guide Développeur', ['style' => 'color: #d9534f; margin-top: 0;']);
echo html_writer::tag('p', 'Vous voulez contribuer ? Architecture, standards, patterns, workflow de contribution : tout est documenté !');
echo html_writer::tag('p', html_writer::link(
    'https://github.com/oliviera999/question_diagnostic/blob/master/docs/DEVELOPER_GUIDE.md',
    'Guide développeur →',
    ['class' => 'btn btn-sm btn-danger', 'target' => '_blank']
), ['style' => 'margin-top: 15px;']);
echo html_writer::end_div();

echo html_writer::end_div(); // Fin grid

// Documentation complète
echo html_writer::tag('h3', '📖 Documentation Complète');

echo html_writer::start_div('alert alert-light', ['style' => 'margin: 20px 0; padding: 20px; border: 1px solid #ddd;']);
echo html_writer::tag('p', '<strong>79 fichiers de documentation</strong> organisés par catégorie :');
echo html_writer::start_tag('ul', ['style' => 'columns: 2; -webkit-columns: 2; -moz-columns: 2;']);
echo html_writer::tag('li', '<strong>Audits</strong> : Analyses complètes (14 fichiers)');
echo html_writer::tag('li', '<strong>Bugfixes</strong> : Corrections de bugs (11 fichiers)');
echo html_writer::tag('li', '<strong>Features</strong> : Documentation fonctionnalités (8 fichiers)');
echo html_writer::tag('li', '<strong>Guides</strong> : Guides utilisateur (10 fichiers)');
echo html_writer::tag('li', '<strong>Installation</strong> : Déploiement (5 fichiers)');
echo html_writer::tag('li', '<strong>Technical</strong> : Documentation technique (8 fichiers)');
echo html_writer::tag('li', '<strong>Performance</strong> : Optimisations (7 fichiers)');
echo html_writer::tag('li', '<strong>Releases</strong> : Notes de version (7 fichiers)');
echo html_writer::tag('li', '<strong>Archives</strong> : Historique sessions (9 fichiers)');
echo html_writer::end_tag('ul');

echo html_writer::tag('p', html_writer::link(
    'https://github.com/oliviera999/question_diagnostic/blob/master/docs/README.md',
    '→ Consulter l\'index complet de la documentation',
    ['class' => 'btn btn-primary', 'target' => '_blank']
), ['style' => 'margin-top: 15px;']);
echo html_writer::end_div();

// Boutons d'action
echo html_writer::start_div('', ['style' => 'margin: 30px 0; text-align: center; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;']);
echo html_writer::link(
    new moodle_url('/local/question_diagnostic/index.php'),
    '← Retour au Dashboard',
    ['class' => 'btn btn-secondary']
);
echo html_writer::link(
    'https://github.com/oliviera999/question_diagnostic',
    '🔗 Voir sur GitHub',
    ['class' => 'btn btn-outline-primary', 'target' => '_blank']
);
echo html_writer::link(
    'https://github.com/oliviera999/question_diagnostic/blob/master/CHANGELOG.md',
    '📋 Voir le CHANGELOG',
    ['class' => 'btn btn-outline-secondary', 'target' => '_blank']
);
echo html_writer::end_div();

echo $OUTPUT->footer();

