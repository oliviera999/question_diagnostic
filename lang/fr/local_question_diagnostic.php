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
$string['move_root_parent'] = 'Racine (parent=0)';
$string['export'] = 'Exporter en CSV';
$string['bulkdelete'] = 'Supprimer la sélection';
$string['selectall'] = 'Tout sélectionner';
$string['select'] = 'Sélection';
$string['selected_questions'] = 'Questions sélectionnées';
$string['select_group'] = 'Sélectionner ce groupe';

$string['current_category_path'] = 'Emplacement (catégorie)';
$string['move_selected_button'] = 'Déplacer la sélection';
$string['no_selected_questions'] = 'Aucune question sélectionnée';
$string['confirm_move_selected_to_olution'] = 'Confirmer le déplacement de la sélection vers Olution';
$string['move_selected_warning'] = 'Cette action va déplacer les questions sélectionnées vers leurs catégories Olution cibles. Vérifiez la liste avant de confirmer.';

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

// Libellés génériques.
$string['type'] = 'Type';

// Menu principal
$string['mainmenu'] = 'Menu principal';
$string['toolsmenu'] = 'Outils disponibles';
$string['backtomenu'] = 'Retour au menu principal';
$string['purge_caches'] = 'Purger les caches';
$string['purge_caches_tooltip'] = 'Purger les caches Moodle (recommandé après modifications)';

// 🆕 v1.12.2 : Diagnostic général du site
$string['tool_site_diagnostic_title'] = 'Diagnostic général du site';
$string['tool_site_diagnostic_desc'] = 'Fait un point sur la santé globale (BDD + ressources moodledata) et propose des checks rapides : intégrité cours/contextes, fichiers orphelins, contenu filedir manquant, et liens cassés (banque de questions).';
$string['tool_site_diagnostic_open'] = 'Ouvrir le diagnostic';

$string['site_diagnostic_title'] = 'Diagnostic général';
$string['site_diagnostic_heading'] = 'Diagnostic général du site';
$string['site_diagnostic_intro'] = 'Cette page exécute des contrôles <strong>read-only</strong> (aucune modification de la base). Certains tests sont réalisés sur un <strong>échantillon</strong> pour préserver les performances.';
$string['site_diagnostic_refresh'] = 'Rafraîchir l’analyse (purge caches locaux)';
$string['site_diagnostic_refresh_done'] = 'Caches locaux purgés, analyse relancée.';
$string['site_diagnostic_overview'] = 'Vue d’ensemble';
$string['site_diagnostic_health'] = 'Santé globale';
$string['site_diagnostic_issues_total'] = 'anomalie(s) détectée(s)';
$string['site_diagnostic_missing_tables'] = 'table(s) manquante(s)';
$string['site_diagnostic_courses'] = 'Cours';
$string['site_diagnostic_course_integrity'] = 'intégrité cours/contextes';
$string['site_diagnostic_resources'] = 'Ressources';
$string['site_diagnostic_files_health'] = 'fichiers / moodledata';

$string['site_diagnostic_db_section'] = 'Structure BDD';
$string['site_diagnostic_courses_section'] = 'Intégrité des cours et contextes';
$string['site_diagnostic_files_section'] = 'Ressources (moodledata / files)';
$string['site_diagnostic_questionbank_section'] = 'Banque de questions (existant)';

$string['site_diagnostic_check'] = 'Contrôle';
$string['site_diagnostic_status'] = 'Statut';
$string['site_diagnostic_value'] = 'Valeur';

$string['site_diagnostic_courses_missing_category'] = 'Cours rattachés à une catégorie de cours inexistante';
$string['site_diagnostic_coursecats_missing_parent'] = 'Catégories de cours avec parent manquant';
$string['site_diagnostic_courses_missing_context'] = 'Cours sans contexte (CONTEXT_COURSE)';
$string['site_diagnostic_coursecats_missing_context'] = 'Catégories de cours sans contexte (CONTEXT_COURSECAT)';
$string['site_diagnostic_orphan_course_contexts'] = 'Contextes de cours orphelins (cours supprimé)';
$string['site_diagnostic_orphan_coursecat_contexts'] = 'Contextes de catégorie de cours orphelins (catégorie supprimée)';

$string['site_diagnostic_disk_free'] = 'Espace libre';
$string['site_diagnostic_moodledata'] = 'moodledata';
$string['site_diagnostic_missing_filedir'] = 'Filedir manquant';
$string['site_diagnostic_missing_filedir_sub'] = 'sur {$a} fichier(s) vérifié(s)';
$string['site_diagnostic_open_orphans'] = 'Ouvrir les fichiers orphelins';
$string['site_diagnostic_missing_filedir_examples_title'] = 'Exemples de fichiers dont le contenu est introuvable dans filedir';
$string['site_diagnostic_missing_filedir_examples_desc'] = 'Ces entrées existent dans la table {files}, mais le fichier physique correspondant n’est pas lisible dans <code>moodledata/filedir</code>. Cela indique souvent un problème de stockage/backup/synchronisation.';

$string['site_diagnostic_recommendations'] = 'Recommandations';
$string['site_diagnostic_no_recommendations'] = '✅ Aucun point bloquant détecté par ces contrôles rapides.';
$string['site_diagnostic_rec_db'] = '❌ <strong>Structure BDD inattendue</strong> : {$a} table(s) attendue(s) n’existent pas. Vérifiez l’installation, le préfixe des tables et l’état de la base.';
$string['site_diagnostic_rec_courses'] = '⚠️ <strong>Intégrité cours/contextes</strong> : {$a} anomalie(s). Cela peut impacter des accès, des permissions ou la navigation. À investiguer côté administration.';
$string['site_diagnostic_rec_filedir'] = '❌ <strong>Filedir</strong> : {$a->missing} contenu(s) manquant(s) détecté(s) sur {$a->checked} fichier(s) échantillonnés. Vérifiez les sauvegardes/restaurations et l’intégrité de <code>moodledata/filedir</code>.';
$string['site_diagnostic_rec_orphans'] = 'ℹ️ <strong>Fichiers orphelins</strong> : {$a->count} fichier(s) ({$a->size}). Vous pouvez archiver/supprimer avec l’outil dédié.';
$string['site_diagnostic_rec_broken_links'] = '⚠️ <strong>Liens cassés dans les questions</strong> : {$a->qcount} question(s) affectée(s) ({$a->lcount} lien(s)). Utilisez l’outil de vérification des liens.';

// Purge des caches.
$string['purge_cache_title'] = 'Purge des caches';
$string['purge_cache_heading'] = 'Purge des Caches Moodle';
$string['purge_cache_why_title'] = 'Pourquoi purger les caches ?';
$string['purge_cache_why_desc'] = 'La purge des caches est nécessaire après :';
$string['purge_cache_reason_lib'] = 'Modification du fichier <code>lib.php</code>';
$string['purge_cache_reason_update'] = 'Mise à jour du plugin';
$string['purge_cache_reason_functions'] = 'Ajout de nouvelles fonctions';
$string['purge_cache_reason_bugs'] = 'Correction de bugs';
$string['purge_cache_warning_title'] = '⚠️ Avertissement';
$string['purge_cache_warning_desc'] = 'La purge des caches va :';
$string['purge_cache_effect_reload'] = '✅ Forcer le rechargement de tous les fichiers PHP';
$string['purge_cache_effect_fix'] = '✅ Corriger l\'erreur "Call to undefined function"';
$string['purge_cache_effect_slow'] = '⚠️ Ralentir temporairement le site (le temps de reconstruire les caches)';
$string['purge_cache_effect_logout'] = '⚠️ Déconnecter éventuellement certains utilisateurs';
$string['purge_cache_recommendation'] = '<strong>Recommandation :</strong> Effectuez cette opération en dehors des heures de pointe si possible.';
$string['purge_cache_confirm'] = 'Purger les Caches Maintenant';
$string['purge_cache_back_previous'] = 'Retour à la page précédente';
$string['purge_cache_running_title'] = '🔄 Purge en cours...';
$string['purge_cache_running_desc'] = 'Veuillez patienter, cette opération peut prendre quelques secondes.';
$string['purge_cache_success_title'] = '✅ Caches purgés avec succès !';
$string['purge_cache_success_desc'] = 'Tous les caches de Moodle ont été purgés.';
$string['purge_cache_next_steps_title'] = '📋 Prochaines étapes';
$string['purge_cache_step_browser_cache'] = '<strong>Videz le cache de votre navigateur</strong> (Ctrl+Shift+Delete ou Cmd+Shift+Delete)';
$string['purge_cache_step_restart_browser'] = '<strong>Fermez et rouvrez votre navigateur</strong> (optionnel mais recommandé)';
$string['purge_cache_step_test'] = '<strong>Testez la fonctionnalité</strong> : Essayez de supprimer une question';
$string['purge_cache_test_now'] = '🧪 Tester maintenant';
$string['purge_cache_test_functions'] = '🔍 Tester les Fonctions';
$string['purge_cache_questions_tool'] = '📊 Gestion des Questions';
$string['purge_cache_error_title'] = '❌ Erreur lors de la purge';
$string['purge_cache_error_desc'] = 'Une erreur s\'est produite : {$a}';
$string['purge_cache_error_alternative'] = '<strong>Solution alternative :</strong> Allez dans Administration du site → Développement → Purger les caches';
$string['overview'] = 'Vue d\'ensemble globale';
$string['welcomemessage'] = 'Bienvenue dans l\'outil de diagnostic de la banque de questions. Cet outil vous permet de détecter et de corriger les problèmes dans votre base de questions Moodle.';

// Outil 1 : Gestion des catégories à supprimer
$string['tool_categories_title'] = 'Gestion des Catégories à Supprimer';
$string['tool_categories_desc'] = 'Gérez les catégories de questions : détectez et corrigez les catégories orphelines, vides ou en doublon. Fusionnez, déplacez ou supprimez les catégories problématiques.';

// 🆕 Diagnostic cohérence catégories (Moodle best practices)
$string['categories_integrity_title'] = 'Diagnostic de cohérence des catégories';
$string['categories_integrity_desc'] = 'Vérifie la cohérence des catégories de questions (contextes, hiérarchie parent/enfant, idnumber, références orphelines) selon les bonnes pratiques Moodle. Aucun changement n’est effectué.';
$string['categories_integrity_run'] = 'Lancer le diagnostic';
$string['categories_integrity_stop'] = 'Masquer le diagnostic';
$string['categories_integrity_ok'] = 'Aucun problème critique détecté';
$string['categories_integrity_issues_found'] = 'Problèmes critiques détectés';
$string['categories_integrity_warnings_found'] = 'Avertissements détectés';
$string['categories_integrity_summary'] = '{$a->categories} catégorie(s) analysée(s) — erreurs : {$a->errors}, avertissements : {$a->warnings}.';
$string['categories_integrity_details'] = 'Détails du diagnostic';
$string['categories_integrity_fix'] = 'Corriger automatiquement';
$string['categories_integrity_fix_confirm_title'] = 'Correction des incohérences de catégories';
$string['categories_integrity_fix_confirm_intro'] = 'Cette action va proposer des corrections automatiques (avec confirmation) pour certaines incohérences structurelles détectées.';
$string['categories_integrity_fix_warning'] = '⚠️ Cette action MODIFIE la base de données. Faites un backup avant de confirmer.';
$string['categories_integrity_fix_operations'] = 'Modifications proposées';
$string['categories_integrity_fix_done'] = 'Correction terminée : {$a->success} succès, {$a->failed} échec(s).';
$string['categories_integrity_fix_failed'] = 'Certaines corrections ont échoué :';
$string['categories_integrity_fix_nothing'] = 'Aucune correction automatique applicable.';

// 🆕 Diagnostic cohérence questions
$string['questions_integrity_title'] = 'Diagnostic de cohérence des questions';
$string['questions_integrity_desc'] = 'Analyse la cohérence de la banque de questions (versioning, entrées orphelines, références cassées, types de questions manquants). Aucun changement n’est effectué.';
$string['questions_integrity_run'] = 'Lancer le diagnostic';
$string['questions_integrity_stop'] = 'Masquer le diagnostic';
$string['questions_integrity_ok'] = 'Aucun problème critique détecté';
$string['questions_integrity_issues_found'] = 'Problèmes critiques détectés';
$string['questions_integrity_warnings_found'] = 'Avertissements détectés';
$string['questions_integrity_summary'] = '{$a->questions} question(s) analysée(s) — erreurs : {$a->errors}, avertissements : {$a->warnings}.';
$string['questions_integrity_details'] = 'Détails du diagnostic';
$string['questions_integrity_fix'] = 'Corriger automatiquement';
$string['questions_integrity_fix_confirm_title'] = 'Correction des incohérences de questions';
$string['questions_integrity_fix_confirm_intro'] = 'Cette action va proposer des corrections automatiques (avec confirmation) pour certaines incohérences structurelles détectées dans la banque de questions.';
$string['questions_integrity_fix_warning'] = '⚠️ Cette action MODIFIE la base de données. Faites un backup avant de confirmer.';
$string['questions_integrity_fix_operations'] = 'Modifications proposées';
$string['questions_integrity_fix_done'] = 'Correction terminée : {$a->success} succès, {$a->failed} échec(s).';
$string['questions_integrity_fix_failed'] = 'Certaines corrections ont échoué :';
$string['questions_integrity_fix_nothing'] = 'Aucune correction automatique applicable.';

// Outil 2 : Vérification des liens
$string['tool_links_title'] = 'Vérification des Liens';
$string['tool_links_desc'] = 'Détectez les questions avec des liens cassés vers des images ou fichiers manquants dans moodledata. Supporte tous les types de questions, y compris les plugins tiers comme "drag and drop sur image".';

// Outil 3 : Rendre les questions cachées visibles
$string['tool_unhide_title'] = 'Rendre les Questions Visibles';
$string['tool_unhide_desc'] = 'Rendez toutes les questions cachées visibles en une seule fois. Seules les questions cachées manuellement (non utilisées) seront affectées. Les questions supprimées (soft delete) utilisées dans des quiz seront protégées.';
$string['unhide_questions'] = 'Rendre les questions visibles';
$string['unhide_questions_title'] = 'Gestion des Questions Cachées';
$string['unhide_questions_intro'] = 'Cette page vous permet de rendre toutes les questions cachées visibles en une seule fois. Seules les questions cachées manuellement (non utilisées dans des quiz) seront affectées. Les questions supprimées (soft delete) mais encore référencées dans des quiz seront automatiquement exclues pour préserver l\'intégrité des tentatives existantes.';
$string['total_hidden_questions'] = 'Questions Cachées';
$string['manually_hidden_only'] = 'Cachées manuellement (non utilisées)';

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
$string['question_content_excerpt'] = 'Extrait (contenu)';
$string['question_type'] = 'Type';
$string['question_hidden_status'] = 'Visibilité';
$string['question_hidden'] = '🔒 Cachée';
$string['question_visible'] = '👁️ Visible';
$string['question_deleted'] = '🗑️ Supprimée';
$string['question_deleted_tooltip'] = 'Question supprimée mais conservée car utilisée dans des quiz (soft delete)';
$string['question_hidden_tooltip'] = 'Question cachée manuellement (non utilisée)';
$string['question_version_count'] = 'Nb Versions';
$string['question_version_count_tooltip'] = 'Nombre de versions de cette question dans la banque';
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

// 🆕 v1.10.5 : Colonne Supprimable
$string['deletable'] = 'Supprimable';
$string['deletable_yes'] = 'OUI';
$string['deletable_no'] = 'NON';
$string['deletable_reason_category_questions'] = '{$a} question(s)';
$string['deletable_reason_category_subcategories'] = '{$a} sous-catégorie(s)';
$string['deletable_reason_category_protected'] = 'Catégorie protégée';
$string['deletable_reason_question_used'] = 'Question utilisée dans {$a} quiz';
$string['deletable_reason_question_hidden'] = 'Question cachée (protégée)';
$string['deletable_reason_question_unique'] = 'Question unique (pas de doublon)';
$string['deletable_reason_question_duplicate_unused'] = 'Doublon inutilisé';

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
$string['duplicate_group_deletable'] = 'Suppressibles'; // 🆕 v1.9.53
$string['duplicate_group_deletable_help'] = 'Nombre de versions réellement supprimables (doublons inutilisés et non protégés)'; // 🆕 v1.9.53
$string['duplicate_group_details'] = 'Détails';

// Détails du groupe de doublons - Terminologie clarifiée
$string['duplicate_instances_count'] = 'Nombre d\'instances dupliquées';
$string['used_instances'] = 'Instances utilisées';
$string['unused_instances'] = 'Instances inutilisées';
$string['all_duplicate_instances'] = 'Toutes les instances dupliquées de cette question';
$string['representative_marker'] = '🎯 Instance représentative (utilisée pour identifier ce groupe)';
$string['duplicate_analysis'] = 'Analyse du groupe de doublons';
$string['total_instances'] = 'Total d\'instances';
$string['used_instances_desc'] = 'Instances utilisées (présentes dans au moins 1 quiz)';
$string['unused_instances_deletable'] = 'Instances inutilisées (supprimables)';
$string['total_quizzes_using'] = 'Total de quiz utilisant ces instances';
$string['total_usages_count'] = 'Total d\'utilisations dans des quiz';
$string['recommendation_unused'] = 'Ce groupe contient <strong>{$a->unused} instance(s) inutilisée(s)</strong> qui pourrai(en)t être supprimée(s) pour nettoyer la base. Les instances utilisées ({$a->used}) doivent être conservées.';
$string['recommendation_all_used'] = 'Toutes les instances de cette question sont utilisées. Aucune suppression recommandée.';
$string['optimized_mode_enabled'] = 'Mode optimisé activé'; // 🆕 v1.9.53
$string['optimized_mode_desc'] = 'Seuls les groupes contenant au moins 1 version supprimable sont affichés. Les groupes où toutes les versions sont utilisées ou protégées sont automatiquement masqués pour accélérer l\'affichage.'; // 🆕 v1.9.53
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

// 🆕 v1.10.1 : Page des questions inutilisées
$string['unused_questions'] = 'Questions inutilisées';
$string['unused_questions_title'] = 'Questions inutilisées';
$string['unused_questions_heading'] = 'Gestion des questions inutilisées';
$string['unused_questions_info'] = 'Cette page affiche toutes les questions qui ne sont pas utilisées dans des quiz et qui n\'ont aucune tentative associée. Ces questions peuvent potentiellement être supprimées pour nettoyer votre base de données.';
$string['unused_questions_list'] = 'Liste des questions inutilisées';
$string['no_unused_questions'] = 'Aucune question inutilisée trouvée';
$string['no_unused_questions_desc'] = 'Toutes vos questions sont utilisées dans au moins un quiz ou possèdent des tentatives. Félicitations ! Votre banque de questions est parfaitement optimisée.';
$string['export_unused_csv'] = 'Exporter les questions inutilisées en CSV';
$string['load_more_questions'] = 'Charger 50 questions supplémentaires';
$string['statistics'] = 'Statistiques';
$string['tool_unused_questions_title'] = 'Questions inutilisées';
$string['tool_unused_questions_desc'] = 'Visualisez et gérez toutes les questions qui ne sont pas utilisées dans des quiz. Identifiez les questions obsolètes, supprimez-les en masse ou exportez-les pour archivage.';

// 🆕 v1.10.2 : Nettoyage global des catégories
$string['cleanup_all_categories'] = 'Nettoyage Global des Catégories';
$string['cleanup_all_categories_desc'] = 'Supprimer automatiquement TOUTES les catégories supprimables du site';
$string['cleanup_all_categories_preview_title'] = 'Prévisualisation du nettoyage global des catégories';
$string['cleanup_all_categories_preview_desc'] = 'Voici un aperçu de toutes les catégories qui seront supprimées lors du nettoyage global. Seules les catégories vides et non protégées seront supprimées.';
$string['cleanup_all_categories_nothing_desc'] = 'Toutes vos catégories sont soit utilisées, soit protégées. Aucun nettoyage n\'est nécessaire.';
$string['cleanup_all_categories_warning'] = '⚠️ ATTENTION : Cette action va supprimer {$a} catégorie(s) de manière IRRÉVERSIBLE !';
$string['cleanup_all_nothing_to_delete'] = 'Aucune catégorie à supprimer';
$string['cleanup_all_complete_title'] = 'Nettoyage global terminé';
$string['cleanup_all_complete_summary'] = '{$a->deleted} catégorie(s) supprimée(s)';
$string['total_categories'] = 'Total catégories';
$string['backtocategories'] = 'Retour aux catégories';

// Olution duplicates (v1.10.4+)
$string['olution_duplicates_title'] = 'Déplacement automatique vers Olution';
$string['olution_duplicates_heading'] = 'Gestion des doublons Cours → Olution';
$string['olution_not_found'] = 'Aucune catégorie système de questions partagées n\'a été trouvée';
$string['olution_not_found_help'] = 'Pour utiliser cette fonctionnalité, vous devez disposer d\'une catégorie de questions "Olution" déjà existante (le plugin ne la crée pas).<br>
Détection possible :<br>
• Contexte Système : catégorie de questions avec un nom contenant "Olution"<br>
• OU Contexte Cours (dans la catégorie de cours "Olution") : catégorie de questions avec une sous-catégorie "commun"<br>
<br>Ensuite, les doublons hors Olution pourront être déplacés vers la sous-catégorie Olution où le doublon existe déjà.';
$string['olution_total_duplicates'] = 'Doublons détectés';
$string['olution_movable_questions'] = 'Questions déplaçables';
$string['olution_unmovable_questions'] = 'Sans correspondance';
$string['olution_subcategories_count'] = 'Sous-catégories Olution';
$string['olution_courses_count'] = 'Cours dans Olution';
$string['source_course_and_category'] = 'Cours source / Catégorie';
$string['olution_target'] = 'Cours Olution cible / Catégorie';
$string['olution_no_duplicates_found'] = 'Aucun doublon détecté entre les catégories de cours et Olution';
$string['olution_move_all_button'] = 'Déplacer toutes les questions ({$a})';
$string['olution_duplicates_list'] = 'Liste des doublons détectés';
$string['olution_duplicates_strict_info'] = 'Mode “doublons certains” : mêmes type (qtype) + texte (questiontext) strictement identiques.';
$string['course_category'] = 'Catégorie du cours';
$string['olution_target_category'] = 'Catégorie Olution cible';
$string['similarity'] = 'Similarité';
$string['no_match'] = 'Pas de correspondance';
$string['confirm_move_to_olution'] = 'Confirmer le déplacement vers Olution';
$string['move_details'] = 'Détails du déplacement';
$string['from_category'] = 'De la catégorie';
$string['to_category'] = 'Vers la catégorie';
$string['move_warning'] = 'Cette action va déplacer la question de sa catégorie actuelle vers la catégorie Olution correspondante. Cette opération est réversible (vous pouvez la déplacer à nouveau manuellement si nécessaire).';
$string['move_success'] = 'Question déplacée avec succès vers Olution';
$string['move_error'] = 'Erreur lors du déplacement';
$string['confirm_move_all_to_olution'] = 'Confirmer le déplacement global vers Olution';
$string['move_all_details'] = 'Détails du déplacement global';
$string['total_questions_to_move'] = 'Nombre de questions à déplacer';
$string['affected_categories'] = 'Catégories sources concernées';
$string['affected_courses'] = 'Cours sources concernés';
$string['from_course_category'] = 'Cours source / Catégorie';
$string['to_course_category'] = 'Cours Olution cible / Catégorie';
$string['move_all_warning'] = 'Cette action va déplacer TOUTES les questions en doublon détectées vers leurs catégories Olution correspondantes. Bien que cette opération soit réversible (déplacement manuel), elle affecte potentiellement un grand nombre de questions. Assurez-vous d\'avoir vérifié la liste des doublons avant de continuer.';
$string['no_movable_questions'] = 'Aucune question déplaçable trouvée';
$string['move_batch_result'] = '{$a->success} question(s) déplacée(s) avec succès, {$a->failed} erreur(s)';

// Fusion de questions (doublons stricts) (v1.12.x)
$string['question_merge_title'] = 'Fusion de questions (doublons stricts)';
$string['question_merge_heading'] = 'Fusion de questions — doublons strictement identiques';
$string['question_merge_intro'] = 'Cette page liste les groupes de doublons “certains” (mêmes qtype + questiontextformat + questiontext). Pour chaque groupe, vous pouvez générer une prévisualisation (dry-run), puis exécuter la fusion : la question avec le plus de tentatives devient la référence, et les doublons sans tentatives peuvent être fusionnés.';
$string['question_merge_includequiz'] = 'Inclure les doublons utilisés dans des quiz (sans tentatives)';
$string['question_merge_discovery'] = 'Découverte contrôlée des tables à remapper (recommandé)';
$string['question_merge_no_groups'] = 'Aucun groupe de doublons stricts trouvé.';
$string['question_merge_groups_title'] = 'Groupes de doublons stricts';
$string['question_merge_group_size'] = 'Taille';
$string['question_merge_used_count'] = 'Versions utilisées';
$string['question_merge_unused_count'] = 'Versions inutilisées';
$string['question_merge_preview_button'] = 'Prévisualiser / Fusionner';
$string['question_merge_preview_heading'] = 'Prévisualisation — Fusion de doublons';
$string['question_merge_summary_title'] = 'Résumé';
$string['question_merge_summary'] = 'Groupe : <strong>{$a->groupname}</strong> ({$a->qtype}) — {$a->count} question(s). Référence : <strong>ID {$a->referenceid}</strong>. Doublons fusionnables : <strong>{$a->mergecount}</strong>.';
$string['question_merge_questions_list'] = 'Questions du groupe';
$string['question_merge_column_quiz_count'] = 'Quiz';
$string['question_merge_impacts_title'] = 'Impacts (références à mettre à jour)';
$string['question_merge_impacts_count'] = 'Références trouvées';
$string['question_merge_confirm_button'] = 'Confirmer la fusion';
$string['question_merge_open_in_context'] = 'Ouvrir';
$string['question_merge_open_in_context_help'] = 'Ouvrir la question dans la banque de questions (bon contexte)';
$string['question_merge_open_category_in_context_help'] = 'Ouvrir la catégorie dans la banque de questions (bon contexte)';

// Carte dashboard
$string['question_merge_tool_title'] = 'Fusion de questions';
$string['question_merge_tool_desc'] = 'Fusionne les doublons strictement identiques en conservant comme référence la question ayant des tentatives. Les doublons sans tentatives peuvent être remappés puis supprimés proprement via l’API Moodle.';
$string['question_merge_tool_open'] = 'Ouvrir la fusion';
$string['question_merge_tool_stat_groups'] = 'groupe(s) de doublons';
$string['question_merge_tool_stat_duplicates'] = 'doublon(s) totaux';

// Fusion de questions — batch (v1.12.x)
$string['question_merge_batch_title'] = 'Fusion batch (doublons stricts)';
$string['question_merge_batch_heading'] = 'Fusion batch — doublons strictement identiques';
$string['question_merge_batch_preview_button'] = 'Batch (prévisualisation)';
$string['question_merge_batch_no_groups'] = 'Aucun groupe à analyser pour ce batch.';
$string['question_merge_batch_summary_title'] = 'Résumé batch';
$string['question_merge_batch_summary'] = 'Fenêtre analysée : {$a->windowcount} groupe(s) (limit={$a->limit}, offset={$a->offset}). Éligibles : {$a->eligiblecount}. Ignorés (0 fusionnable) : {$a->skipped}. Erreurs : {$a->errors}.';
$string['question_merge_batch_impacts_title'] = 'Impacts agrégés (toutes fusions)';
$string['question_merge_batch_groups_title'] = 'Groupes éligibles dans ce batch';
$string['question_merge_batch_reference'] = 'Référence';
$string['question_merge_batch_mergeable'] = 'Fusionnables';
$string['question_merge_batch_warning'] = 'Cette opération va exécuter plusieurs fusions successives. Faites un backup avant, et testez sur un environnement de dev. Les groupes non éligibles (aucun doublon fusionnable) seront ignorés.';
$string['question_merge_batch_confirm_button'] = 'Confirmer la fusion batch';
$string['question_merge_batch_nothing'] = 'Aucun groupe éligible dans cette fenêtre (rien à fusionner).';
$string['question_merge_batch_done_title'] = 'Batch terminé';
$string['question_merge_batch_done_summary'] = 'Groupes fusionnés : {$a->success}, échecs : {$a->failed}, questions supprimées : {$a->deleted}.';
$string['question_merge_batch_messages_title'] = 'Messages / erreurs';

// Olution triage (commun > Question à trier).
$string['olution_triage_title'] = 'Triage des questions (commun → sous-catégories)';
$string['olution_triage_heading'] = 'Trier les questions de \"Question à trier\"';
$string['olution_triage_button'] = 'Trier \"Question à trier\" ({$a})';
$string['olution_triage_not_found'] = 'Catégorie \"Question à trier\" introuvable sous \"commun\".';
$string['olution_triage_not_found_help'] = 'Pour utiliser ce triage, créez une sous-catégorie de questions nommée \"Question à trier\" sous la catégorie \"commun\" (elle-même sous Olution).';
$string['olution_triage_detected'] = 'Catégorie de triage détectée :';
$string['olution_triage_signatures'] = '{$a} signature(s) (nom + type) avec une cible détectée';
$string['olution_triage_movable_questions'] = 'Questions triables';
$string['olution_triage_explain'] = 'Déplace les questions placées dans \"Question à trier\" vers la sous-catégorie de \"commun\" où un doublon (même nom + même type) existe déjà.';
$string['olution_triage_move_all_button'] = 'Déplacer toutes les questions triables ({$a})';
$string['olution_triage_no_movable'] = 'Aucune question à déplacer (aucune correspondance trouvée dans les autres sous-catégories de commun).';
$string['olution_triage_list_title'] = 'Questions de \"Question à trier\" avec une correspondance';
$string['olution_triage_no_candidates_page'] = 'Aucun résultat à afficher sur cette page.';
$string['confirm_move_all_triage_to_olution'] = 'Confirmer le triage (déplacement des questions)';
$string['triage_move_all_warning'] = 'Cette action va déplacer les questions situées dans \"Question à trier\" vers les sous-catégories correspondantes de \"commun\" lorsque des doublons (nom + type) existent. Vérifiez la liste avant de confirmer.';
$string['invalid_parameters'] = 'Paramètres invalides';
$string['invalid_action'] = 'Action invalide';

// Olution auto sort (commun > "Question à trier" → suggestion texte).
$string['olution_auto_sort_title'] = 'Tri automatisé (texte) — \"Question à trier\"';
$string['olution_auto_sort_heading'] = 'Tri automatisé des questions (titre + contenu)';
$string['olution_auto_sort_button'] = 'Tri automatisé (texte) ({$a})';
$string['olution_auto_sort_explain'] = 'Liste les questions placées dans \"Question à trier\" et propose une sous-catégorie cible EXISTANTE de \"commun\" dont l’intitulé (et le chemin) se rapproche le plus du titre + contenu. Si aucune cible ne ressort, une proposition de nouvelle catégorie est affichée (sans création).';
$string['olution_auto_sort_mode'] = 'Mode';
$string['olution_auto_sort_mode_heuristic'] = 'Heuristique';
$string['olution_auto_sort_mode_ai'] = 'IA (Moodle/OpenAI)';
$string['olution_auto_sort_ai_unavailable'] = 'IA Moodle non disponible (ou non configurée).';
$string['olution_auto_sort_fallback_active'] = 'Mode IA demandé, mais l’IA n’a pas répondu : le tri est effectué en fallback heuristique.';
$string['olution_auto_sort_partial_fallback'] = 'Mode IA demandé : {$a} ligne(s) ont basculé en fallback heuristique.';
$string['olution_auto_sort_used_mode'] = 'Mode utilisé';
$string['olution_auto_sort_used_mode_ai'] = 'IA';
$string['olution_auto_sort_used_mode_fallback'] = 'Fallback';
$string['olution_auto_sort_used_mode_heuristic'] = 'Heuristique';
$string['olution_auto_sort_threshold'] = 'Seuil (0–1)';
$string['olution_auto_sort_no_results'] = 'Aucune question à afficher.';
$string['olution_auto_sort_suggestion'] = 'Suggestion';
$string['olution_auto_sort_score'] = 'Score: {$a->score}';
$string['olution_auto_sort_no_match'] = 'Aucune catégorie existante ne correspond suffisamment.';
$string['olution_auto_sort_proposed_new_category'] = 'Proposition de nouvelle catégorie : {$a}';
$string['question_content'] = 'Contenu';

// IA debug.
$string['ai_debug_title'] = 'Diagnostic IA Moodle (OpenAI)';
$string['ai_debug_heading'] = 'Diagnostic IA';
$string['ai_debug_link'] = '🔎 Diagnostic IA (pour comprendre le fallback)';
$string['ai_debug_classes'] = 'Classes IA détectées';
$string['ai_debug_test'] = 'Test d’appel IA (best-effort)';
$string['ai_debug_methods'] = 'Méthodes disponibles (core_ai\\manager)';
$string['ai_debug_signatures'] = 'Signatures (core_ai\\manager)';
$string['ai_debug_actions'] = 'Actions IA supportées + providers';
$string['ai_debug_plugins'] = 'Plugins IA détectés (aiprovider / aiplacement)';

// 🆕 v1.11.5 : Filtre par catégorie de cours
$string['course_category_filter'] = 'Catégorie de cours';
$string['course_category_filter_desc'] = 'Filtrer les catégories de questions par catégorie de cours';
$string['all_course_categories'] = 'Toutes les catégories de cours';
$string['filter_active_course_category'] = 'Filtre actif : Catégorie de cours';
$string['show_all_course_categories'] = 'Voir toutes les catégories de cours';
$string['course_category_filter_info'] = 'Affichage des catégories de questions pour la catégorie de cours';

// 🆕 v1.11.29 : Catégories de questions par cours / activité (contenant des questions)
$string['tool_categories_by_context_title'] = 'Catégories par cours / activité';
$string['tool_categories_by_context_desc'] = 'Liste les catégories de questions liées à un cours (et/ou une activité comme un quiz) et ne garde que celles qui contiennent des questions (directement ou via des sous-catégories).';
$string['tool_categories_by_context_open'] = 'Ouvrir la liste';

$string['tool_categories_by_context_course_search'] = 'Rechercher un cours';
$string['tool_categories_by_context_course_search_placeholder'] = 'Nom du cours ou shortname (au moins 2 caractères)…';
$string['tool_categories_by_context_course'] = 'Cours';
$string['tool_categories_by_context_course_placeholder'] = '— Sélectionner un cours —';
$string['tool_categories_by_context_course_help'] = 'Choisissez une catégorie de cours pour obtenir une liste déroulante, ou tapez une recherche (nom/shortname) puis validez pour afficher une liste de résultats.';

$string['tool_categories_by_context_scope'] = 'Périmètre';
$string['tool_categories_by_context_scope_all'] = 'Cours + activités';
$string['tool_categories_by_context_scope_course'] = 'Cours uniquement';
$string['tool_categories_by_context_scope_activities'] = 'Activités uniquement';
$string['tool_categories_by_context_scope_quiz'] = 'Tests (quiz) uniquement';
$string['tool_categories_by_context_scope_activity'] = 'Une activité spécifique';

$string['tool_categories_by_context_activity'] = 'Activité (cmid)';
$string['tool_categories_by_context_activity_all'] = 'Toutes';
$string['tool_categories_by_context_include_system'] = 'Inclure le contexte système';
$string['tool_categories_by_context_apply'] = 'Afficher';

$string['tool_categories_by_context_intro_title'] = 'Sélectionnez un cours';
$string['tool_categories_by_context_intro'] = 'Choisissez une catégorie de cours puis un cours, ou utilisez la recherche pour sélectionner un cours, afin d’afficher les catégories de questions qui contiennent des questions.';
$string['tool_categories_by_context_activity_required'] = 'Veuillez sélectionner une activité (cmid) pour le périmètre « activité spécifique ».';
$string['tool_categories_by_context_activity_not_quiz'] = 'Le périmètre « Tests (quiz) uniquement » nécessite un quiz. Activité sélectionnée : {$a->modname}.';

$string['tool_categories_by_context_no_contexts'] = 'Aucun contexte à analyser avec les critères actuels.';
$string['tool_categories_by_context_no_categories'] = 'Aucune catégorie de questions trouvée pour ces contextes.';

$string['tool_categories_by_context_summary_title'] = 'Résumé';
$string['tool_categories_by_context_summary'] = 'Cours : <strong>{$a->course}</strong> — contextes analysés : <strong>{$a->contexts}</strong> — catégories avec questions : <strong>{$a->categories}</strong> — entrées directes : <strong>{$a->directquestions}</strong> (visibles : <strong>{$a->directvisible}</strong>).';
$string['tool_categories_by_context_none_with_questions'] = 'Aucune catégorie ne contient de questions avec les critères actuels.';

$string['tool_categories_by_context_direct'] = 'Direct';
$string['tool_categories_by_context_direct_help'] = 'Nombre d’entrées de questions directement dans cette catégorie.';
$string['tool_categories_by_context_total'] = 'Total (arbre)';
$string['tool_categories_by_context_total_help'] = 'Nombre d’entrées de questions dans cette catégorie + toutes ses sous-catégories (récursif).';
$string['tool_categories_by_context_visible_direct'] = 'Visibles (direct)';
$string['tool_categories_by_context_visible_total'] = 'Visibles (arbre)';
$string['tool_categories_by_context_move_root'] = '— Racine (parent = 0) —';
$string['tool_categories_by_context_move_to'] = 'Déplacer la catégorie vers un nouveau parent (même contexte)';
$string['tool_categories_by_context_move_button_help'] = 'Déplace la catégorie (change son parent). Une confirmation sera demandée.';
$string['tool_categories_by_context_move_to_olution_commun'] = 'Déplacer vers Olution / commun (même contexte)';
$string['tool_categories_by_context_move_button'] = 'Déplacer';
$string['tool_categories_by_context_move_olution_commun_context_mismatch'] = 'Olution/commun est dans un autre contexte : Moodle n’autorise pas le déplacement d’une catégorie entre contextes (même si la cible est accessible depuis le cours). Utilisez plutôt « 📥 Envoyer vers Catégories à trier » pour déplacer les questions.';
$string['tool_categories_by_context_move_questions_to_triage_title'] = 'Déplacer les questions vers Olution / commun / Catégories à trier';
$string['tool_categories_by_context_move_questions_to_triage_button'] = '📥 Envoyer vers “Catégories à trier”';
$string['tool_categories_by_context_move_questions_to_triage_button_help'] = 'Déplace toutes les questions (de cette catégorie) vers Olution/commun/Catégories à trier. Une confirmation sera demandée.';
$string['tool_categories_by_context_move_questions_to_triage_nothing'] = 'Aucune question à déplacer depuis cette catégorie.';
$string['tool_categories_by_context_move_questions_to_triage_summary_title'] = 'Résumé du déplacement';
$string['tool_categories_by_context_move_questions_to_triage_summary'] = 'Source : <strong>{$a->sourcecategory}</strong><br>Cible : <strong>{$a->targetcategory}</strong><br>Questions à déplacer : <strong>{$a->count}</strong>';
$string['tool_categories_by_context_move_questions_to_triage_warning'] = '⚠️ Cette action MODIFIE la base de données (changement de catégorie des questions). Assurez-vous que le contexte cible est bien autorisé pour ce cours.';
$string['tool_categories_by_context_move_questions_to_triage_not_accessible'] = 'La catégorie cible Olution/commun n’est pas accessible depuis le cours (courseid={$a->courseid}). Contexte cible={$a->targetcontextid}. Méthode={$a->method}.';
$string['tool_categories_by_context_categories_to_sort_not_found'] = 'Catégorie cible introuvable : créez une sous-catégorie de questions nommée « Catégories à trier » sous Olution → commun.';

// v1.15.0
$string['cleanup_all_background'] = 'Le nettoyage tourne en tâche de fond (cron) ; vous recevez un rapport à la fin. Chaque suppression est précédée d\'un export XML Moodle.';
$string['cleanup_all_queued'] = 'Le nettoyage est programmé. Il s\'exécutera au prochain passage du cron ; vous recevrez un rapport.';
$string['cleanup_skipped_question'] = 'question {$a->id} conservée : {$a->reason}';
$string['delete_error'] = 'Erreur pendant la suppression : {$a}';
$string['delete_refused'] = 'Suppression refusée : {$a}';
$string['deleter_skip_error'] = 'erreur pendant le contrôle ou la suppression';
$string['deleter_skip_hiddenbycore'] = 'cachée par Moodle au lieu d\'être supprimée (utilisée)';
$string['deleter_skip_missing'] = 'question introuvable';
$string['deleter_skip_randompool'] = 'sa catégorie sert à un tirage aléatoire';
$string['deleter_skip_used'] = 'utilisée (tentatives, tests ou autre plugin, toutes versions)';
$string['error_export_dir'] = 'Impossible de créer le dossier d\'export {$a} : rien n\'a été supprimé.';
$string['error_export_write'] = 'Impossible d\'écrire l\'export {$a} : rien n\'a été supprimé.';
$string['messageprovider:operationreport'] = 'Rapport d\'une opération sur la banque de questions';
$string['report_cleanup_deleted'] = 'Questions supprimées : {$a}';
$string['report_cleanup_groups'] = 'Groupes de doublons traités : {$a}';
$string['report_cleanup_kept'] = 'Questions conservées : {$a}';
$string['report_cleanup_skipped'] = 'Questions non supprimées : {$a}';
$string['report_exports'] = 'Exports XML Moodle écrits avant suppression (dans {$a}) :';
$string['task_cleanup_duplicates'] = 'Nettoyage de toutes les questions en doublon';
