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

// Version badge
$string['version_label'] = 'Version';
$string['version_tooltip'] = 'Plugin Question Diagnostic {$a->version} - Dernière mise à jour : {$a->date}';

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

// 🆕 v1.9.0 : Suppression sécurisée de questions
$string['delete_question_forbidden'] = 'Suppression interdite';
$string['cannot_delete_question'] = 'Cette question ne peut pas être supprimée';
$string['reason'] = 'Raison';
$string['protection_rules'] = 'Règles de Protection';
$string['protection_rules_desc'] = 'Pour garantir la sécurité de vos données pédagogiques, ce plugin applique des règles strictes :';
$string['rule_used_protected'] = 'Les questions utilisées dans des quiz ou ayant des tentatives sont PROTÉGÉES';
$string['rule_hidden_protected'] = 'Les questions cachées sont PROTÉGÉES';
$string['rule_unique_protected'] = 'Les questions uniques (sans doublon) sont PROTÉGÉES';
$string['rule_duplicate_deletable'] = 'Seules les questions en doublon ET inutilisées ET visibles peuvent être supprimées';
$string['backtoquestions'] = 'Retour à la liste des questions';
$string['confirm_delete_question'] = 'Confirmer la suppression';
$string['question_to_delete'] = 'Question à supprimer';
$string['duplicate_info'] = 'Informations sur les doublons';
$string['action_irreversible'] = 'Cette action est IRRÉVERSIBLE !';
$string['confirm_delete_message'] = 'Êtes-vous absolument certain de vouloir supprimer cette question ? Les autres versions (doublons) seront conservées.';
$string['confirm_delete'] = 'Oui, supprimer définitivement';
$string['question_deleted_success'] = 'Question supprimée avec succès';
$string['question_protected'] = 'Question protégée';
$string['question_hidden_protected'] = 'Question cachée protégée';
$string['question_hidden_info'] = 'Cette question est masquée dans la banque de questions. Les questions cachées sont protégées contre la suppression pour éviter toute perte accidentelle de contenu pédagogique.';

// 🆕 v1.9.40 : Tâche planifiée
$string['task_scan_broken_links'] = 'Scan automatique des liens cassés';

// 🆕 v1.9.41 : Capabilities (permissions granulaires)
$string['question_diagnostic:view'] = 'Voir le plugin Question Diagnostic';
$string['question_diagnostic:viewcategories'] = 'Voir les catégories';
$string['question_diagnostic:viewquestions'] = 'Voir les questions';
$string['question_diagnostic:viewbrokenlinks'] = 'Voir les liens cassés';
$string['question_diagnostic:viewauditlogs'] = 'Voir les logs d\'audit';
$string['question_diagnostic:viewmonitoring'] = 'Voir le monitoring';
$string['question_diagnostic:managecategories'] = 'Gérer les catégories';
$string['question_diagnostic:deletecategories'] = 'Supprimer des catégories';
$string['question_diagnostic:mergecategories'] = 'Fusionner des catégories';
$string['question_diagnostic:movecategories'] = 'Déplacer des catégories';
$string['question_diagnostic:deletequestions'] = 'Supprimer des questions';
$string['question_diagnostic:export'] = 'Exporter des données (CSV)';
$string['question_diagnostic:configureplugin'] = 'Configurer le plugin';

// 🆕 v1.9.45 : Tableau de synthèse des groupes de doublons
$string['duplicate_groups_table_title'] = 'Groupes de questions en doublon';
$string['duplicate_group_name'] = 'Intitulé de la question';
$string['duplicate_group_count'] = 'Nombre de doublons';
$string['duplicate_group_used'] = 'Versions utilisées';
$string['duplicate_group_unused'] = 'Versions inutilisées';
$string['duplicate_group_details'] = 'Détails';
$string['load_more_groups'] = 'Charger 5 groupes supplémentaires';
$string['showing_groups'] = 'Affichage de {$a->shown} groupe(s) sur {$a->total}';
$string['question_group_detail_title'] = 'Détails du groupe de doublons';
$string['back_to_groups_list'] = 'Retour à la liste des groupes';
$string['no_duplicate_groups_found'] = 'Aucun groupe de doublons trouvé';
$string['no_duplicate_groups_desc'] = 'Toutes vos questions sont uniques. Aucun doublon détecté.';
$string['group_summary'] = 'Résumé du groupe';
$string['all_versions_in_group'] = 'Toutes les versions de cette question';

// 🆕 v1.9.49 : Nettoyage automatique des doublons
$string['cleanup_group'] = 'Nettoyer';
$string['cleanup_selection'] = 'Nettoyer la sélection';
$string['cleanup_confirm_title'] = 'Confirmation du nettoyage';
$string['cleanup_confirm_message'] = 'Cette action va supprimer {$a} version(s) inutilisée(s)';
$string['cleanup_success'] = 'Nettoyage terminé : {$a->deleted} question(s) supprimée(s), {$a->kept} version(s) conservée(s)';
$string['cleanup_no_action'] = 'Aucune question à supprimer dans les groupes sélectionnés';

// 🆕 v1.9.52 : Nettoyage global des doublons
$string['cleanup_all_duplicates'] = 'Nettoyage Global des Doublons';
$string['cleanup_all_duplicates_desc'] = 'Supprimer automatiquement TOUS les doublons inutilisés du site';
$string['cleanup_all_preview_title'] = 'Prévisualisation du nettoyage global';
$string['cleanup_all_preview_desc'] = 'Voici un aperçu de ce qui sera supprimé lors du nettoyage global des doublons';
$string['cleanup_all_stats_groups'] = 'Groupes de doublons à nettoyer';
$string['cleanup_all_stats_to_delete'] = 'Questions à supprimer';
$string['cleanup_all_stats_to_keep'] = 'Questions à conserver';
$string['cleanup_all_estimated_time'] = 'Temps estimé';
$string['cleanup_all_estimated_batches'] = 'Nombre de lots de traitement';
$string['cleanup_all_download_csv'] = 'Télécharger la liste complète (CSV)';
$string['cleanup_all_confirm_button'] = 'Confirmer et lancer le nettoyage';
$string['cleanup_all_warning'] = '⚠️ ATTENTION : Cette action va supprimer {$a} question(s) de manière IRRÉVERSIBLE !';
$string['cleanup_all_progress_title'] = 'Nettoyage en cours...';
$string['cleanup_all_progress_batch'] = 'Traitement du lot {$a->current} sur {$a->total}';
$string['cleanup_all_progress_stats'] = 'Supprimées : {$a->deleted} | Conservées : {$a->kept}';
$string['cleanup_all_complete_title'] = 'Nettoyage global terminé';
$string['cleanup_all_complete_summary'] = 'Résumé : {$a->deleted} question(s) supprimée(s), {$a->kept} version(s) conservée(s) sur {$a->groups} groupe(s) traité(s)';
$string['cleanup_all_by_type_title'] = 'Répartition par type de question';
$string['cleanup_all_security_rules'] = 'Règles de sécurité appliquées';
$string['cleanup_all_no_duplicates'] = 'Aucun doublon à nettoyer';
$string['cleanup_all_no_duplicates_desc'] = 'Votre base de données ne contient aucun doublon de questions à supprimer. Toutes vos questions sont soit uniques, soit toutes les versions sont utilisées.';

// 🆕 v1.10.0 : Gestion des fichiers orphelins
$string['orphan_files'] = 'Fichiers Orphelins';
$string['orphan_files_heading'] = 'Gestion des Fichiers Orphelins';
$string['orphan_files_description'] = 'Détection et nettoyage des fichiers orphelins dans Moodle';
$string['orphan_files_tool_desc'] = 'Identifie les fichiers dans la base de données ou dans moodledata qui ne sont plus référencés par aucun contenu actif';
$string['orphan_db_records'] = 'Enregistrements BDD orphelins';
$string['orphan_physical_files'] = 'Fichiers physiques orphelins';
$string['total_orphan_files'] = 'Total fichiers orphelins';
$string['disk_space_used'] = 'Espace disque occupé';
$string['orphan_by_component'] = 'Répartition par composant';
$string['orphan_by_type'] = 'Répartition par type';
$string['orphan_file_id'] = 'ID Fichier';
$string['orphan_filename'] = 'Nom du fichier';
$string['orphan_component'] = 'Composant';
$string['orphan_filearea'] = 'Zone fichier';
$string['orphan_filesize'] = 'Taille';
$string['orphan_type'] = 'Type d\'orphelin';
$string['orphan_reason'] = 'Raison';
$string['orphan_age'] = 'Âge';
$string['orphan_created'] = 'Date création';
$string['orphan_reason_context'] = 'Contexte invalide';
$string['orphan_reason_parent'] = 'Élément parent supprimé';
$string['orphan_reason_unreferenced'] = 'Non référencé';
$string['confirm_delete_orphans'] = 'Confirmer la suppression des fichiers orphelins';
$string['confirm_delete_orphans_message'] = 'Êtes-vous sûr de vouloir supprimer {$a} fichier(s) orphelin(s) ?';
$string['delete_orphans_warning'] = '⚠️ ATTENTION : Cette action est IRRÉVERSIBLE ! Espace libéré : {$a}';
$string['delete_orphan_success'] = 'Fichier orphelin supprimé avec succès';
$string['delete_orphan_error'] = 'Erreur lors de la suppression du fichier orphelin';
$string['archive_orphan'] = 'Archiver';
$string['archive_orphans'] = 'Archiver la sélection';
$string['archive_success'] = 'Fichiers archivés avec succès dans {$a}';
$string['archive_error'] = 'Erreur lors de l\'archivage';
$string['export_orphans'] = 'Exporter les fichiers orphelins';
$string['no_orphan_files'] = 'Aucun fichier orphelin détecté';
$string['no_orphan_files_desc'] = 'Votre système de fichiers est sain. Tous les fichiers sont correctement référencés.';
$string['dry_run_mode'] = 'Mode Simulation (Dry-Run)';
$string['dry_run_enabled'] = 'Mode simulation activé - Aucune suppression réelle';
$string['dry_run_would_delete'] = 'SERAIT supprimé';
$string['filter_by_component'] = 'Filtrer par composant';
$string['filter_by_age'] = 'Filtrer par âge';
$string['age_recent'] = '< 1 mois';
$string['age_medium'] = '1-6 mois';
$string['age_old'] = '> 6 mois';
$string['filter_by_size'] = 'Filtrer par taille';
$string['size_small'] = '< 1 MB';
$string['size_medium'] = '1-10 MB';
$string['size_large'] = '> 10 MB';
$string['orphan_files_stats'] = 'Statistiques des fichiers orphelins';
$string['refresh_orphan_analysis'] = 'Rafraîchir l\'analyse';
$string['view_archives'] = 'Voir les archives';
$string['archive_retention_days'] = 'Durée de rétention : {$a} jours';
$string['orphan_files_limit_notice'] = 'L\'analyse est limitée à {$a} fichiers pour des raisons de performance';

// 🆕 v1.10.1 : Réparation automatique des fichiers orphelins
$string['repair_orphan'] = 'Réparer';
$string['repair_options'] = 'Options de réparation';
$string['repair_analysis'] = 'Analyse de réparation';
$string['repair_possible'] = 'Réparation possible';
$string['repairability'] = 'Réparabilité';
$string['repairability_high'] = 'Haute (>90%)';
$string['repairability_medium'] = 'Moyenne (60-90%)';
$string['repairability_low'] = 'Faible (<60%)';
$string['repair_contenthash'] = 'Réassociation par contenthash';
$string['repair_contenthash_desc'] = 'Fichier identique trouvé avec parent valide';
$string['repair_filename'] = 'Réattribution par nom';
$string['repair_filename_candidates'] = 'candidat(s) trouvé(s)';
$string['repair_filename_desc'] = 'Questions contenant ce nom de fichier';
$string['repair_context'] = 'Réassociation par contexte';
$string['repair_context_desc'] = 'Parents potentiels dans le même contexte';
$string['repair_recovery'] = 'Création question récupération';
$string['repair_recovery_desc'] = 'Créer une question "stub" pour préserver le fichier';
$string['repair_confidence'] = 'Niveau de confiance';
$string['repair_target'] = 'Cible de réparation';
$string['repair_modal_title'] = 'Réparation de fichier orphelin';
$string['repair_select_option'] = 'Sélectionnez une option de réparation';
$string['repair_confirm'] = 'Confirmer la réparation';
$string['repair_success_contenthash'] = 'Fichier réassocié avec succès (contenthash)';
$string['repair_success_filename'] = 'Fichier réattribué avec succès (nom)';
$string['repair_success_recovery'] = 'Question de récupération créée avec succès';
$string['repair_error'] = 'Erreur lors de la réparation';
$string['repair_file_not_found'] = 'Fichier introuvable';
$string['repair_no_target_found'] = 'Aucune cible de réparation trouvée';
$string['repair_no_target_selected'] = 'Aucune cible sélectionnée';
$string['repair_target_not_found'] = 'Cible de réparation introuvable';
$string['repair_context_not_found'] = 'Contexte introuvable';
$string['repair_unknown_type'] = 'Type de réparation inconnu';
$string['repair_would_execute'] = 'Réparation SERAIT exécutée';
$string['repair_dry_run'] = 'Tester (Dry-Run)';
$string['repair_execute'] = 'Réparer Maintenant';
$string['repair_bulk_analysis'] = 'Analyse en masse de réparabilité';
$string['repair_bulk_stats'] = 'Statistiques de réparation';
$string['repair_high_confidence_count'] = '{$a} fichier(s) haute fiabilité';
$string['repair_medium_confidence_count'] = '{$a} fichier(s) fiabilité moyenne';
$string['repair_low_confidence_count'] = '{$a} fichier(s) sans réparation évidente';
$string['repair_auto_recommended'] = 'Réparation automatique recommandée';
$string['repair_manual_recommended'] = 'Validation manuelle recommandée';
$string['repair_not_recommended'] = 'Archivage ou suppression recommandé';

