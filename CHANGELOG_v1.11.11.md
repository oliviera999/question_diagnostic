# Changelog v1.11.11 - Vue Hiérarchique des Catégories

## 🎯 Objectif
Transformer la vue "banque de questions" en **arbre hiérarchique** pour reproduire l'expérience de la banque de questions Moodle native.

## 🔧 Modifications

### 1. Nouvelle fonction de récupération hiérarchique dans `lib.php`

#### Fonction `local_question_diagnostic_get_question_categories_hierarchy()`
- **Description** : Récupère les catégories de questions avec leur structure hiérarchique
- **Fonctionnalités** :
  - Recherche récursive dans les sous-catégories de cours
  - Récupération des contextes système, cours et modules
  - Construction de la hiérarchie parent-enfant
  - Comptage des questions par catégorie

#### Fonction `local_question_diagnostic_build_category_hierarchy()`
- **Description** : Construit la structure hiérarchique à partir d'une liste plate
- **Algorithme** :
  - Création d'un map pour accès rapide
  - Attribution des enfants à leurs parents
  - Retour de la structure arbre

#### Fonction `local_question_diagnostic_render_category_hierarchy()`
- **Description** : Rendu HTML de l'arbre hiérarchique
- **Fonctionnalités** :
  - Indentation visuelle par niveau
  - Icônes selon le type de contexte (🌐 Système, 📚 Cours, 📝 Module)
  - Badges colorés selon le nombre de questions
  - Boutons de purge intégrés
  - Affichage des descriptions
  - Rendu récursif des enfants

### 2. Modification de `categories.php`

#### Mode "banque" hiérarchique
- **Remplacement** : Liste plate → Arbre hiérarchique
- **Intégration** : Utilisation de `local_question_diagnostic_get_question_categories_hierarchy()`
- **Rendu** : Appel de `local_question_diagnostic_render_category_hierarchy()`
- **Container** : Style moderne avec bordure et ombre

### 3. Styles CSS dans `styles/main.css`

#### Classes ajoutées
- `.qd-hierarchy-container` : Container principal de l'arbre
- `.qd-category-item` : Élément de catégorie individuel
- `.qd-category-description` : Description des catégories

#### Fonctionnalités visuelles
- **Indentation** : Espacement progressif par niveau
- **Couleurs** : Bordures colorées par niveau de profondeur
- **Hover** : Effets de survol avec translation
- **Alternance** : Couleurs alternées pour la lisibilité
- **Responsive** : Adaptation mobile

#### Palette de couleurs par niveau
- **Niveau 0** : Gris (#e9ecef)
- **Niveau 1** : Vert (#28a745)
- **Niveau 2** : Jaune (#ffc107)
- **Niveau 3** : Rouge (#dc3545)
- **Niveau 4+** : Violet (#6f42c1)

## 🎨 Interface Utilisateur

### Structure de l'arbre
```
📁 Catégorie racine (5)
  📚 Sous-catégorie cours (12)
    📝 Sous-catégorie module (3)
  🌐 Catégorie système (0)
    📚 Autre sous-catégorie (8)
```

### Éléments visuels
- **Icônes contextuelles** : 🌐 Système, 📚 Cours, 📝 Module, 📁 Générique
- **Badges de comptage** : Couleur selon le nombre de questions
- **Boutons de purge** : Intégrés à chaque ligne
- **Descriptions** : Affichées en italique sous le nom

### Interactions
- **Hover** : Translation et changement de couleur
- **Responsive** : Adaptation aux écrans mobiles
- **Accessibilité** : Tooltips et contrastes appropriés

## 🧪 Tests

### Script de test créé
- **Fichier** : `test_hierarchical_view.php`
- **Fonctionnalités** :
  - Test de récupération de la hiérarchie
  - Affichage des statistiques
  - Rendu complet de l'arbre
  - Instructions de test utilisateur

### Pages testées
- [x] Récupération de la hiérarchie
- [x] Construction de l'arbre
- [x] Rendu HTML
- [x] Styles CSS
- [x] Responsive design

## 📋 Checklist de déploiement

- [x] Fonction de récupération hiérarchique créée
- [x] Fonction de construction d'arbre créée
- [x] Fonction de rendu HTML créée
- [x] Intégration dans `categories.php`
- [x] Styles CSS ajoutés
- [x] Version incrémentée vers `v1.11.11`
- [x] Script de test créé
- [x] Changelog documenté

## 🎯 Bénéfices

### Pour les utilisateurs
- **Vue familière** : Identique à la banque de questions Moodle
- **Navigation intuitive** : Structure hiérarchique claire
- **Information riche** : Icônes, compteurs, descriptions
- **Actions directes** : Purge accessible depuis chaque catégorie

### Pour les développeurs
- **Code modulaire** : Fonctions réutilisables
- **Performance** : Requêtes optimisées
- **Maintenabilité** : Code bien structuré
- **Extensibilité** : Facile d'ajouter de nouvelles fonctionnalités

## 🔮 Évolutions futures

### Améliorations possibles
- **Expansion/réduction** : Boutons pour plier/déplier les branches
- **Recherche** : Filtrage en temps réel dans l'arbre
- **Tri** : Options de tri par nom, nombre de questions, etc.
- **Drag & Drop** : Réorganisation par glisser-déposer

### Intégrations
- **API REST** : Endpoints pour manipulation de l'arbre
- **WebSocket** : Mise à jour en temps réel
- **Export** : Export de la structure hiérarchique

## 🚀 Utilisation

### Activation du mode hiérarchique
1. Aller sur la page "Gestion des catégories"
2. Sélectionner une catégorie de cours (ex: "olution")
3. Cliquer sur "Mode banque (liste)"
4. L'arbre hiérarchique s'affiche automatiquement

### Navigation dans l'arbre
- **Structure** : Indentation visuelle par niveau
- **Actions** : Bouton "Purge this category" sur chaque ligne
- **Informations** : Nom, compteur, description, icône de contexte

---

**Version** : v1.11.11  
**Date** : 15 octobre 2025  
**Statut** : ✅ Prêt pour déploiement  
**Impact** : 🟢 Amélioration UX majeure, aucune régression
