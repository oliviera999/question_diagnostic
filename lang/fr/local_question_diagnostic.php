<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * French language strings for Question Diagnostic Tool
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Gestion des Catégories de Questions à Supprimer';
$string['managequestions'] = 'Gérer les catégories à supprimer';
$string['accessdenied'] = 'Accès refusé. Vous devez être administrateur du site.';

// Dashboard
$string['dashboard'] = 'Tableau de bord';
$string['totalcategories'] = 'Total catégories';
$string['emptycategories'] = 'Catégories vides';
$string['orphancategories'] = 'Catégories orphelines';
$string['duplicates'] = 'Doublons détectés';
$string['totalquestions'] = 'Total questions';

// Filtres
$string['filters'] = 'Filtres et recherche';
$string['search'] = 'Rechercher';
$string['searchplaceholder'] = 'Nom ou ID de catégorie...';
$string['status'] = 'Statut';
$string['context'] = 'Contexte';
$string['all'] = 'Tous';
$string['empty'] = 'Vides';
$string['orphan'] = 'Orphelines';
$string['ok'] = 'OK';

// Actions
$string['actions'] = 'Actions';
$string['delete'] = 'Supprimer';
$string['merge'] = 'Fusionner';
$string['move'] = 'Déplacer';
$string['export'] = 'Exporter en CSV';
$string['bulkdelete'] = 'Supprimer la sélection';
$string['selectall'] = 'Tout sélectionner';

// Messages
$string['deleteconfirm'] = 'Êtes-vous sûr de vouloir supprimer cette catégorie ?';
$string['deletesuccess'] = 'Catégorie supprimée avec succès';
$string['deleteerror'] = 'Erreur lors de la suppression';
$string['mergesuccess'] = 'Catégories fusionnées avec succès';
$string['mergeerror'] = 'Erreur lors de la fusion';
$string['movesuccess'] = 'Catégorie déplacée avec succès';
$string['moveerror'] = 'Erreur lors du déplacement';
$string['categoriesselected'] = 'catégorie(s) sélectionnée(s)';

// Tableau
$string['categoryid'] = 'ID';
$string['categoryname'] = 'Nom';
$string['categorycontext'] = 'Contexte';
$string['categoryparent'] = 'Parent';
$string['categoryquestions'] = 'Questions';
$string['categorysubcats'] = 'Sous-catégories';
$string['categorystatus'] = 'Statut';

// Menu principal
$string['mainmenu'] = 'Menu principal';
$string['toolsmenu'] = 'Outils disponibles';
$string['backtomenu'] = 'Retour au menu principal';
$string['overview'] = 'Vue d\'ensemble globale';
$string['welcomemessage'] = 'Bienvenue dans l\'outil de diagnostic de la banque de questions. Cet outil vous permet de détecter et de corriger les problèmes dans votre base de questions Moodle.';

// Outil 1 : Gestion des catégories à supprimer
$string['tool_categories_title'] = 'Gestion des Catégories à Supprimer';
$string['tool_categories_desc'] = 'Gérez les catégories de questions : détectez et corrigez les catégories orphelines, vides ou en doublon. Fusionnez, déplacez ou supprimez les catégories problématiques.';

// Outil 2 : Vérification des liens
$string['tool_links_title'] = 'Vérification des Liens';
$string['tool_links_desc'] = 'Détectez les questions avec des liens cassés vers des images ou fichiers manquants dans moodledata. Supporte tous les types de questions, y compris les plugins tiers comme "drag and drop sur image".';

// Liens cassés
$string['brokenlinks'] = 'Vérification des liens dans les questions';
$string['brokenlinks_heading'] = 'Outil de Diagnostic - Questions avec liens cassés';
$string['brokenlinks_stats'] = 'Statistiques globales';
$string['questions_with_broken_links'] = 'Questions Problématiques';
$string['total_broken_links'] = 'Liens Cassés';
$string['global_health'] = 'Santé Globale';
$string['questions_ok'] = 'Questions sans problème';
$string['brokenlinks_by_type'] = 'Répartition par type de question';
$string['brokenlinks_table'] = 'Questions avec liens cassés';
$string['no_broken_links'] = 'Aucune question avec lien cassé détectée !';
$string['question_id'] = 'ID Question';
$string['question_name'] = 'Nom de la question';
$string['question_type'] = 'Type';
$string['question_category'] = 'Catégorie';
$string['broken_links_count'] = 'Liens cassés';
$string['broken_links_details'] = 'Détails';
$string['field'] = 'Champ';
$string['url'] = 'URL';
$string['reason'] = 'Raison';
$string['repair_options'] = 'Options de réparation';
$string['repair'] = 'Réparer';
$string['remove_reference'] = 'Supprimer la référence';
$string['remove_reference_confirm'] = 'Êtes-vous sûr de vouloir supprimer cette référence ?';
$string['remove_reference_desc'] = 'Remplace le lien par [Image supprimée]';
$string['repair_modal_title'] = 'Options de réparation';
$string['repair_recommendation'] = 'Vérifiez d\'abord la question dans la banque de questions pour voir si les fichiers peuvent être réuploadés manuellement. La suppression de la référence est une solution de dernier recours.';
$string['file_not_found'] = 'Fichier image introuvable';
$string['pluginfile_not_found'] = 'Fichier pluginfile introuvable';
$string['bgimage_missing'] = 'Image de fond manquante';
$string['link_removed_success'] = 'Lien cassé supprimé avec succès.';
$string['link_removed_error'] = 'Erreur lors de la suppression du lien.';

// Conseils
$string['usage_tips'] = 'Conseils d\'utilisation';
$string['tip_orphan_categories'] = 'Catégories orphelines : Ce sont des catégories dont le contexte (cours, module) n\'existe plus. Elles doivent être fusionnées ou supprimées.';
$string['tip_empty_categories'] = 'Catégories vides : Catégories sans questions ni sous-catégories. Elles peuvent être supprimées en toute sécurité.';
$string['tip_broken_links'] = 'Liens cassés : Images ou fichiers référencés dans les questions mais absents de moodledata. Cela peut affecter l\'affichage des questions.';
$string['tip_backup'] = 'Sauvegarde recommandée : Avant toute opération de suppression ou de fusion, il est recommandé de faire une sauvegarde de votre base de données.';

// Outil 3 : Statistiques des questions
$string['tool_questions_title'] = 'Statistiques des Questions';
$string['tool_questions_desc'] = 'Analysez en détail toutes vos questions : identifiez les questions utilisées/inutilisées, détectez les doublons avec calcul de similarité, et accédez à des statistiques complètes. Filtrez et triez facilement pour un nettoyage efficace.';

// Page de statistiques des questions
$string['questions_cleanup'] = 'Statistiques et Nettoyage des Questions';
$string['questions_cleanup_heading'] = 'Outil d\'Analyse - Statistiques Complètes des Questions';
$string['questions_stats'] = 'Statistiques globales des questions';
$string['loading_stats'] = 'Le calcul des statistiques peut prendre du temps si vous avez beaucoup de questions.';
$string['loading_questions'] = 'Chargement des questions en cours...';
$string['loading_large_db'] = 'Cela peut prendre quelques instants pour les grandes bases de données.';

// Statistiques
$string['total_questions_stats'] = 'Total Questions';
$string['questions_used'] = 'Questions Utilisées';
$string['questions_unused'] = 'Questions Inutilisées';
$string['questions_duplicates'] = 'Questions en Doublon';
$string['questions_hidden'] = 'Questions Cachées';
$string['questions_broken_links'] = 'Liens Cassés';
$string['questions_with_problems'] = 'Questions avec problèmes';
$string['in_database'] = 'Dans la base de données';
$string['in_quizzes_or_attempts'] = 'Dans quiz ou avec tentatives';
$string['close'] = 'Fermer';
$string['never_used'] = 'Jamais utilisées';
$string['total_duplicates_found'] = 'doublons totaux';
$string['not_visible'] = 'Non visibles';

// Répartition
$string['distribution_by_type'] = 'Répartition par type de question';

// Colonnes
$string['columns_to_display'] = 'Colonnes à afficher';
$string['column_id'] = 'ID';
$string['column_name'] = 'Nom';
$string['column_type'] = 'Type';
$string['column_category'] = 'Catégorie';
$string['column_context'] = 'Contexte';
$string['column_creator'] = 'Créateur';
$string['column_created'] = 'Date création';
$string['column_modified'] = 'Date modification';
$string['column_visible'] = 'Visible';
$string['column_quizzes'] = 'Quiz';
$string['column_attempts'] = 'Tentatives';
$string['column_duplicates'] = 'Doublons';
$string['column_excerpt'] = 'Extrait';
$string['column_actions'] = 'Actions';

// Filtres avancés
$string['filter_search_placeholder'] = 'Nom, ID, texte...';
$string['filter_usage'] = 'Usage';
$string['filter_all'] = 'Toutes';
$string['filter_used'] = 'Utilisées';
$string['filter_unused'] = 'Inutilisées';
$string['filter_duplicates'] = 'Doublons';
$string['filter_with_duplicates'] = 'Avec doublons';
$string['filter_no_duplicates'] = 'Sans doublons';

// Tableau
$string['questions_list'] = 'Liste détaillée des questions';
$string['view_category'] = 'Voir la catégorie';
$string['used_in_quiz'] = 'Utilisée dans {$a} quiz';
$string['view_question'] = 'Voir';
$string['view_in_bank'] = 'Voir dans la banque de questions';

// Doublons
$string['duplicates_modal_title'] = 'Questions en doublon';
$string['duplicates_detected'] = 'question(s) en doublon détectée(s)';
$string['duplicates_similar'] = 'Ces questions ont un contenu similaire (nom, texte, type).';
$string['duplicates_recommendation'] = 'Vérifiez manuellement ces questions pour confirmer qu\'il s\'agit bien de doublons. Vous pouvez ensuite supprimer ou fusionner les questions redondantes.';
$string['click_to_view_duplicates'] = 'Cliquer pour voir les doublons';

// Export
$string['export_questions_csv'] = 'Exporter les questions en CSV';

// Messages de résultats
$string['questions_displayed'] = '{$a->visible} question(s) affichée(s) sur {$a->total}';

// Boutons
$string['toggle_columns'] = 'Colonnes';
$string['analyze_questions'] = 'Analyser les questions';

// Page de test
$string['test_page_title'] = 'Page de test';
$string['test_page_heading'] = 'Page de test';
$string['test_page_desc'] = 'Page de test pour effectuer des vérifications et des tests de fonctionnalités.';
$string['test_content'] = 'Test';

