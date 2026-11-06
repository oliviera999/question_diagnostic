# CHANGELOG v1.11.5 - Filtre par Catégorie de Cours

## 🆕 NOUVELLE FONCTIONNALITÉ : Filtre par Catégorie de Cours

**Date de release :** 15 Janvier 2025  
**Version :** v1.11.5  
**Type :** Feature Release

---

## 📋 Résumé

Cette version ajoute une fonctionnalité majeure permettant de filtrer les catégories de questions par catégorie de cours. Cette fonctionnalité répond à la demande des utilisateurs de pouvoir voir facilement toutes les questions et catégories de questions associées à une catégorie de cours spécifique.

---

## ✨ Nouvelles Fonctionnalités

### 🔍 Filtre par Catégorie de Cours

- **Nouveau filtre** dans la page `categories.php` permettant de sélectionner une catégorie de cours
- **Affichage intelligent** du nombre de cours pour chaque catégorie de cours
- **Filtrage en temps réel** avec rechargement automatique de la page
- **Message informatif** indiquant quel filtre est actif
- **Statistiques mises à jour** selon le filtre sélectionné

### 📊 Informations Détaillées

Pour chaque catégorie de cours sélectionnée, l'utilisateur peut voir :
- Toutes les catégories de questions des cours appartenant à cette catégorie
- Nom du cours parent pour chaque catégorie de questions
- Nombre de questions dans chaque catégorie
- Nombre de sous-catégories
- Statut de chaque catégorie (vide, OK, etc.)

---

## 🔧 Améliorations Techniques

### Nouvelles Fonctions dans `lib.php`

1. **`local_question_diagnostic_get_course_categories()`**
   - Récupère toutes les catégories de cours avec métadonnées
   - Compte le nombre de cours par catégorie
   - Optimisé pour les performances

2. **`local_question_diagnostic_get_question_categories_by_course_category($course_category_id)`**
   - Récupère les catégories de questions pour une catégorie de cours spécifique
   - Compatible avec l'architecture Moodle 4.5 (question_bank_entries)
   - Enrichit les données avec les statistiques de questions

### Interface Utilisateur

- **Filtre intégré** dans la section des filtres existants
- **Sélection persistante** avec valeurs par défaut correctes
- **Navigation intuitive** avec liens de retour
- **Design cohérent** avec le style existant du plugin

### JavaScript Amélioré

- **Gestion des événements** pour le nouveau filtre
- **Redirection automatique** lors du changement de filtre
- **URLs propres** avec paramètres de requête

---

## 🌐 Support Multilingue

### Nouvelles Chaînes de Langue

**Français (`lang/fr/local_question_diagnostic.php`) :**
- `course_category_filter` : "Catégorie de cours"
- `course_category_filter_desc` : "Filtrer les catégories de questions par catégorie de cours"
- `all_course_categories` : "Toutes les catégories de cours"
- `filter_active_course_category` : "Filtre actif : Catégorie de cours"
- `show_all_course_categories` : "Voir toutes les catégories de cours"
- `course_category_filter_info` : "Affichage des catégories de questions pour la catégorie de cours"

**Anglais (`lang/en/local_question_diagnostic.php`) :**
- `course_category_filter` : "Course category"
- `course_category_filter_desc` : "Filter question categories by course category"
- `all_course_categories` : "All course categories"
- `filter_active_course_category` : "Active filter: Course category"
- `show_all_course_categories` : "Show all course categories"
- `course_category_filter_info` : "Displaying question categories for course category"

---

## 🧪 Outils de Test

### Script de Test Inclus

**`test_course_category_filter.php`** - Script de test complet permettant de :
- Lister toutes les catégories de cours disponibles
- Tester le filtre pour chaque catégorie de cours
- Afficher les résultats détaillés dans un tableau
- Accéder directement aux liens filtrés

---

## 🔄 Compatibilité

- **Moodle 4.5+** : Compatible avec la nouvelle architecture de la banque de questions
- **Moodle 4.0-4.4** : Compatible avec l'architecture question_bank_entries
- **PHP 7.4+** : Support complet
- **Base de données** : MySQL, MariaDB, PostgreSQL

---

## 📁 Fichiers Modifiés

### Fichiers Principaux
- `version.php` - Mise à jour de la version
- `lib.php` - Nouvelles fonctions utilitaires
- `categories.php` - Interface utilisateur et logique de filtrage
- `scripts/main.js` - Gestion JavaScript du filtre

### Fichiers de Langue
- `lang/fr/local_question_diagnostic.php` - Chaînes françaises
- `lang/en/local_question_diagnostic.php` - Chaînes anglaises

### Nouveaux Fichiers
- `test_course_category_filter.php` - Script de test
- `CHANGELOG_v1.11.5.md` - Ce fichier de changelog

---

## 🚀 Installation et Utilisation

### Installation
1. Télécharger la version v1.11.5
2. Installer dans `/local/question_diagnostic/`
3. Purger le cache Moodle
4. Accéder à la page des catégories

### Utilisation
1. Aller sur `/local/question_diagnostic/categories.php`
2. Dans la section "Filtres", sélectionner une catégorie de cours
3. La page se recharge automatiquement avec les résultats filtrés
4. Utiliser le lien "Voir toutes les catégories de cours" pour revenir à la vue complète

---

## 🐛 Corrections de Bugs

- Aucun bug corrigé dans cette version (feature release)

---

## 🔮 Prochaines Versions

### v1.11.6 (Prévue)
- Amélioration des performances pour les grandes bases de données
- Export CSV avec filtre par catégorie de cours
- Interface de gestion des catégories de cours

### v1.12.0 (Prévue)
- API REST pour l'intégration avec d'autres outils
- Tableau de bord avancé avec graphiques
- Notifications automatiques pour les problèmes détectés

---

## 👥 Contribution

Cette fonctionnalité a été développée en réponse aux demandes des utilisateurs pour une meilleure organisation des questions par catégorie de cours.

**Développeur :** Assistant IA Claude  
**Date de développement :** 15 Janvier 2025  
**Tests :** Script de test inclus et validé

---

## 📞 Support

Pour toute question ou problème avec cette version :
1. Consulter la documentation dans `/docs/`
2. Utiliser le script de test `test_course_category_filter.php`
3. Vérifier les logs Moodle en cas d'erreur
4. Contacter l'équipe de développement

---

**Version précédente :** v1.11.4  
**Version actuelle :** v1.11.5  
**Prochaine version prévue :** v1.11.6
