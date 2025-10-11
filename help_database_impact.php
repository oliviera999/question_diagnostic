<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Page d'aide : Impact sur la base de données
 * 
 * 🆕 v1.9.28 : Création d'une vraie page HTML pour remplacer le lien mort vers DATABASE_IMPACT.md
 * 
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

if (!is_siteadmin()) {
    print_error('accessdenied', 'admin');
}

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/question_diagnostic/help_database_impact.php'));
$PAGE->set_title('Impact sur la Base de Données');
$PAGE->set_heading(local_question_diagnostic_get_heading_with_version('Impact sur la Base de Données'));
$PAGE->set_pagelayout('report');

$PAGE->requires->css('/local/question_diagnostic/styles/main.css');

echo $OUTPUT->header();

// Lien retour
echo html_writer::start_tag('div', ['style' => 'margin-bottom: 20px;']);
echo html_writer::link(
    new moodle_url('/local/question_diagnostic/index.php'),
    '← Retour au menu principal',
    ['class' => 'btn btn-secondary']
);
echo html_writer::end_tag('div');

// Contenu principal
echo html_writer::start_tag('div', ['class' => 'qd-help-page', 'style' => 'max-width: 900px;']);

echo html_writer::tag('h2', '🛡️ Impact sur la Base de Données');

echo html_writer::start_div('alert alert-warning', ['style' => 'margin: 20px 0; border-left: 4px solid #f0ad4e;']);
echo html_writer::tag('strong', '⚠️ ATTENTION');
echo html_writer::tag('p', 'Ce plugin peut modifier la base de données de Moodle. Il est crucial de comprendre les impacts avant toute action.');
echo html_writer::end_div();

// Section 1 : Opérations qui modifient la BDD
echo html_writer::tag('h3', '🔧 Opérations qui modifient la base de données');

echo html_writer::start_tag('table', ['class' => 'table table-bordered', 'style' => 'margin: 20px 0;']);
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr', ['style' => 'background: #f5f5f5;']);
echo html_writer::tag('th', 'Opération');
echo html_writer::tag('th', 'Tables Modifiées');
echo html_writer::tag('th', 'Réversible ?');
echo html_writer::tag('th', 'Risque');
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');

echo html_writer::start_tag('tbody');

// Suppression de catégorie
echo html_writer::start_tag('tr');
echo html_writer::tag('td', html_writer::tag('strong', 'Suppression de catégorie'));
echo html_writer::tag('td', html_writer::tag('code', 'question_categories'));
echo html_writer::tag('td', html_writer::tag('span', '❌ NON', ['style' => 'color: red; font-weight: bold;']));
echo html_writer::tag('td', html_writer::tag('span', 'FAIBLE', ['class' => 'badge badge-success']));
echo html_writer::end_tag('tr');

// Fusion de catégories
echo html_writer::start_tag('tr');
echo html_writer::tag('td', html_writer::tag('strong', 'Fusion de catégories'));
echo html_writer::tag('td', html_writer::tag('code', 'question_categories') . ', ' . html_writer::tag('code', 'question_bank_entries'));
echo html_writer::tag('td', html_writer::tag('span', '❌ NON', ['style' => 'color: red; font-weight: bold;']));
echo html_writer::tag('td', html_writer::tag('span', 'MOYEN', ['class' => 'badge badge-warning']));
echo html_writer::end_tag('tr');

// Suppression de question
echo html_writer::start_tag('tr');
echo html_writer::tag('td', html_writer::tag('strong', 'Suppression de question'));
echo html_writer::tag('td', html_writer::tag('code', 'question') . ', ' . html_writer::tag('code', 'question_bank_entries') . ', ' . html_writer::tag('code', 'question_versions') . ', ' . html_writer::tag('code', 'files') . ', etc.');
echo html_writer::tag('td', html_writer::tag('span', '❌ NON', ['style' => 'color: red; font-weight: bold;']));
echo html_writer::tag('td', html_writer::tag('span', 'FAIBLE', ['class' => 'badge badge-success']) . ' (protections actives)');
echo html_writer::end_tag('tr');

// Suppression lien cassé
echo html_writer::start_tag('tr');
echo html_writer::tag('td', html_writer::tag('strong', 'Suppression lien cassé'));
echo html_writer::tag('td', html_writer::tag('code', 'question') . ' ou ' . html_writer::tag('code', 'question_answers'));
echo html_writer::tag('td', html_writer::tag('span', '⚠️ PARTIEL', ['style' => 'color: orange; font-weight: bold;']) . ' (texte modifié)');
echo html_writer::tag('td', html_writer::tag('span', 'FAIBLE', ['class' => 'badge badge-success']));
echo html_writer::end_tag('tr');

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');

// Section 2 : Protections en place
echo html_writer::tag('h3', '🛡️ Protections en Place');

echo html_writer::start_tag('ul', ['style' => 'line-height: 1.8; margin: 20px 0;']);
echo html_writer::tag('li', html_writer::tag('strong', 'Catégories :') . ' Impossible de supprimer si contient des questions ou sous-catégories');
echo html_writer::tag('li', html_writer::tag('strong', 'Questions :') . ' Impossible de supprimer si utilisée dans un quiz ou a des tentatives');
echo html_writer::tag('li', html_writer::tag('strong', 'Questions uniques :') . ' Impossible de supprimer si pas de doublon');
echo html_writer::tag('li', html_writer::tag('strong', 'Confirmations :') . ' Toutes les opérations demandent une confirmation explicite');
echo html_writer::tag('li', html_writer::tag('strong', 'Session key :') . ' Protection CSRF sur toutes les actions');
echo html_writer::end_tag('ul');

// Section 3 : Procédures de backup
echo html_writer::tag('h3', '💾 Procédures de Backup Recommandées');

echo html_writer::start_div('alert alert-info', ['style' => 'margin: 20px 0;']);
echo html_writer::tag('h4', 'Avant toute opération de suppression/fusion en masse', ['style' => 'margin-top: 0;']);
echo html_writer::end_div();

echo html_writer::tag('h4', '1. Backup de la base de données');
echo html_writer::start_tag('pre', ['style' => 'background: #f5f5f5; padding: 15px; border-radius: 5px;']);
echo html_writer::tag('code', '# MySQL/MariaDB
mysqldump -u root -p moodle > backup_moodle_$(date +%Y%m%d_%H%M%S).sql

# PostgreSQL
pg_dump -U postgres moodle > backup_moodle_$(date +%Y%m%d_%H%M%S).sql');
echo html_writer::end_tag('pre');

echo html_writer::tag('h4', '2. Backup des fichiers (optionnel mais recommandé)');
echo html_writer::start_tag('pre', ['style' => 'background: #f5f5f5; padding: 15px; border-radius: 5px;']);
echo html_writer::tag('code', 'tar -czf backup_plugin_$(date +%Y%m%d_%H%M%S).tar.gz /var/www/moodle/local/question_diagnostic/');
echo html_writer::end_tag('pre');

echo html_writer::tag('h4', '3. Tester la restauration (optionnel)');
echo html_writer::start_tag('p');
echo 'Vérifiez que vous pouvez restaurer le backup avant de continuer.';
echo html_writer::end_tag('p');

// Section 4 : Que faire en cas de problème
echo html_writer::tag('h3', '🚨 En Cas de Problème');

echo html_writer::start_tag('ol', ['style' => 'line-height: 1.8; margin: 20px 0;']);
echo html_writer::tag('li', html_writer::tag('strong', 'NE PAS PANIQUER') . ' - Les données ne sont jamais perdues si vous avez un backup');
echo html_writer::tag('li', html_writer::tag('strong', 'Arrêter les opérations') . ' - Ne pas continuer les suppressions');
echo html_writer::tag('li', html_writer::tag('strong', 'Vérifier les logs') . ' - Admin > Rapports > Logs');
echo html_writer::tag('li', html_writer::tag('strong', 'Restaurer le backup') . ' si nécessaire');
echo html_writer::tag('li', html_writer::tag('strong', 'Contacter le support') . ' avec les détails de l\'erreur');
echo html_writer::end_tag('ol');

// Section 5 : Tables impactées
echo html_writer::tag('h3', '📊 Tables Moodle Impactées');

echo html_writer::start_tag('table', ['class' => 'table table-striped', 'style' => 'margin: 20px 0; font-size: 13px;']);
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr', ['style' => 'background: #f5f5f5;']);
echo html_writer::tag('th', 'Table');
echo html_writer::tag('th', 'Type Modification');
echo html_writer::tag('th', 'Opération(s)');
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');

echo html_writer::start_tag('tbody');
echo html_writer::start_tag('tr');
echo html_writer::tag('td', html_writer::tag('code', 'question_categories'));
echo html_writer::tag('td', 'DELETE');
echo html_writer::tag('td', 'Suppression catégorie vide');
echo html_writer::end_tag('tr');

echo html_writer::start_tag('tr');
echo html_writer::tag('td', html_writer::tag('code', 'question_bank_entries'));
echo html_writer::tag('td', 'UPDATE');
echo html_writer::tag('td', 'Fusion de catégories (champ questioncategoryid)');
echo html_writer::end_tag('tr');

echo html_writer::start_tag('tr');
echo html_writer::tag('td', html_writer::tag('code', 'question'));
echo html_writer::tag('td', 'DELETE');
echo html_writer::tag('td', 'Suppression de question (+ cascade sur tables liées)');
echo html_writer::end_tag('tr');

echo html_writer::start_tag('tr');
echo html_writer::tag('td', html_writer::tag('code', 'question_versions'));
echo html_writer::tag('td', 'DELETE (cascade)');
echo html_writer::tag('td', 'Suppression de question');
echo html_writer::end_tag('tr');

echo html_writer::start_tag('tr');
echo html_writer::tag('td', html_writer::tag('code', 'files'));
echo html_writer::tag('td', 'DELETE (cascade)');
echo html_writer::tag('td', 'Suppression de question');
echo html_writer::end_tag('tr');

echo html_writer::start_tag('tr');
echo html_writer::tag('td', html_writer::tag('code', 'question_answers'));
echo html_writer::tag('td', 'UPDATE');
echo html_writer::tag('td', 'Suppression lien cassé (texte modifié)');
echo html_writer::end_tag('tr');

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');

// Section 6 : Recommandations finales
echo html_writer::start_div('alert alert-success', ['style' => 'margin: 20px 0; border-left: 4px solid #5cb85c;']);
echo html_writer::tag('h4', '✅ Bonnes Pratiques', ['style' => 'margin-top: 0;']);
echo html_writer::start_tag('ul', ['style' => 'margin-bottom: 0;']);
echo html_writer::tag('li', 'Toujours faire un backup avant opérations en masse');
echo html_writer::tag('li', 'Tester d\'abord sur environnement de staging');
echo html_writer::tag('li', 'Commencer par de petites suppressions (10-20 items)');
echo html_writer::tag('li', 'Vérifier les résultats avant de continuer');
echo html_writer::tag('li', 'Garder les backups pendant au moins 30 jours');
echo html_writer::end_tag('ul');
echo html_writer::end_div();

echo html_writer::end_tag('div'); // fin qd-help-page

// Bouton retour
echo html_writer::start_tag('div', ['style' => 'margin-top: 30px; text-align: center;']);
echo html_writer::link(
    new moodle_url('/local/question_diagnostic/index.php'),
    '← Retour au Dashboard',
    ['class' => 'btn btn-primary btn-lg']
);
echo html_writer::end_tag('div');

echo $OUTPUT->footer();

