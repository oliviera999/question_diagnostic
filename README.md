# 🔧 Moodle Question Bank Diagnostic Tool

![Tests](https://github.com/oliviera999/question_diagnostic/workflows/Tests/badge.svg)
![Moodle Plugin CI](https://github.com/oliviera999/question_diagnostic/workflows/Moodle%20Plugin%20CI/badge.svg)
![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue)
![Moodle](https://img.shields.io/badge/Moodle-5.1-orange)
![License](https://img.shields.io/badge/License-GPL%20v3-green)

Outil complet de gestion et diagnostic de la banque de questions pour Moodle 5.1+

**Version actuelle :** v1.13.0 | **Statut :** Production-Ready ✅ | **Qualité :** 9.9/10 ⭐⭐⭐⭐⭐

### 📌 Compatibilité Moodle

- **✅ Supporté** : Moodle **5.1** (requis)
- **❌ Non supporté** : Moodle 4.x et antérieurs (architecture incompatible)
- **📖 Détails** : [docs/technical/MOODLE_COMPATIBILITY_POLICY.md](docs/technical/MOODLE_COMPATIBILITY_POLICY.md)
- **⚠️ Prérequis** : PHP 8.2 minimum

---

## 📚 Documentation

**📖 [INDEX COMPLET DE LA DOCUMENTATION](docs/README.md)** ← *Commencez ici pour naviguer dans toute la documentation*

La documentation complète (79 fichiers) est maintenant organisée dans le dossier **[`docs/`](docs/)** par catégorie :
- **[Audits](docs/audits/)** : Analyses complètes du plugin
- **[Guides](docs/guides/)** : Guides d'utilisation et configuration
- **[Installation](docs/installation/)** : Installation et déploiement
- **[Performance](docs/performance/)** : Optimisations pour gros sites (v1.9.30)
- **[Bugfixes](docs/bugfixes/)** : Corrections de bugs
- **[Features](docs/features/)** : Documentation des fonctionnalités
- **[Technical](docs/technical/)** : Documentation technique (BDD, compatibilité)
- **[Releases](docs/releases/)** : Notes de version

---

## 🌟 Fonctionnalités Principales

Ce plugin offre un ensemble complet d'outils pour diagnostiquer et gérer votre banque de questions Moodle.

## 📋 Fonctionnalités

### 🎯 Outils de Diagnostic

#### 1. 📂 Gestion des Catégories
- Détection des catégories vides, orphelines ou en doublon
- Suppression individuelle ou en masse (avec confirmation)
- Fusion de catégories et déplacement de questions
- Export CSV pour audit et documentation

#### 2. 🔗 Vérification des Liens Cassés
- Détection automatique des images et fichiers manquants
- Support complet des plugins tiers (drag and drop, markers, etc.)
- Interface de réparation avec recommandations

#### 3. 📊 Statistiques des Questions
- Vue d'ensemble de l'utilisation des questions dans les tests
- Identification des questions jamais utilisées
- Optimisation du nettoyage de la banque

### 📊 Dashboard et Statistiques

#### Menu Principal
- Vue d'ensemble globale de la santé de votre banque de questions
- Statistiques rapides sur catégories et questions
- Accès rapide aux deux outils principaux

#### Gestion des Catégories
- Vue d'ensemble complète des catégories de questions
- Statistiques en temps réel (total, vides, orphelines, doublons)
- Cartes visuelles avec codes couleur

#### Vérification des Liens
- Statistiques sur les questions avec liens cassés
- Répartition par type de question
- Pourcentage de santé globale
- Liens directs vers la banque de questions
- Options de réparation automatisées

### 🔍 Filtres et Recherche

#### Sur les Catégories
- **Recherche par nom ou ID** de catégorie
- **Filtrage par statut** : Toutes, Vides, Orphelines, OK
- **Filtrage par contexte** : Système, Cours, Module, etc.
- Statistiques de filtrage dynamiques

#### Sur les Questions
- **Recherche en temps réel** par nom, ID ou catégorie
- **Filtrage par type** de question
- **Filtrage par utilisation** dans les tests
- Mise à jour instantanée des résultats

### ✅ Sélection Multiple et Actions Groupées
- Cases à cocher pour sélection individuelle
- Sélectionner/désélectionner tout
- Suppression groupée de catégories vides
- Barre d'actions contextuelle

### 🔗 Vérification des Liens Cassés

#### Détection Automatique
- **Analyse complète** de toutes les questions de la banque
- **Détection des images manquantes** (balises `<img>`)
- **Détection des fichiers pluginfile** manquants
- **Vérification des images de fond** pour drag and drop
- **Support des plugins tiers** (ddimageortext, ddmarker, ddwtos)

#### Types de Problèmes Détectés
- 🖼️ Images manquantes dans le texte des questions
- 📎 Fichiers manquants dans les réponses
- 🎯 Images de fond manquantes (drag and drop)
- 💬 Fichiers manquants dans les feedbacks
- 🔗 Tous les liens pluginfile.php cassés

#### Options de Réparation
- **Suppression de référence** : Remplace le lien cassé par "[Image supprimée]"
- **Recherche de fichiers similaires** : Infrastructure prête pour réparation intelligente
- **Liens directs** vers la banque de questions pour réparation manuelle
- **Recommandations** contextuelles pour chaque problème

#### Interface Dédiée
- Dashboard avec statistiques détaillées
- Tableau complet de toutes les questions problématiques
- Filtres par type de question
- Recherche en temps réel
- Modal de réparation interactive

### 🛠️ Gestion des Catégories

#### Navigation Directe
- **Liens vers la banque de questions** : Chaque catégorie dispose d'un lien direct
- Cliquez sur le **nom de la catégorie** ou le bouton **👁️ Voir**
- S'ouvre dans un nouvel onglet pour faciliter la navigation
- Accès direct à l'interface native de gestion des questions

#### Suppression
- Suppression individuelle de catégories vides
- Suppression en masse avec confirmation
- Vérifications de sécurité (catégories avec questions/sous-catégories protégées)

#### Fusion
- Fusionner deux catégories (déplace questions et sous-catégories)
- Interface modale intuitive
- Sélection de catégorie destination

#### Export
- Export CSV complet de toutes les catégories
- Inclut toutes les statistiques et métadonnées
- Format compatible Excel (UTF-8 BOM)

### 🎨 Interface Moderne
- Design responsive (mobile-friendly)
- **Badge de version visible** sur toutes les pages (🆕 v1.11.27)
- Tri par colonne (cliquer sur les en-têtes)
- Badges de statut colorés
- Animations et transitions fluides
- Modals pour les actions importantes

### 🔒 Sécurité
- Accès réservé aux administrateurs du site
- Protection CSRF avec sesskey
- Confirmations avant suppressions/fusions
- Validation côté serveur

## 📁 Structure des Fichiers

```
local/question_diagnostic/
├── index.php                       # Interface principale
├── categories.php                  # Gestion des catégories
├── broken_links.php                # Vérification des liens
├── questions_cleanup.php           # Statistiques des questions
├── version.php                     # Métadonnées du plugin
├── lib.php                         # Fonctions de bibliothèque
├── README.md                       # Documentation
├── classes/
│   ├── category_manager.php       # Gestion des catégories
│   ├── question_link_checker.php  # Vérification des liens
│   └── question_analyzer.php      # Analyse des questions
├── actions/
│   ├── delete.php                 # Suppression de catégories
│   ├── merge.php                  # Fusion de catégories
│   ├── move.php                   # Déplacement de catégories
│   └── export.php                 # Export CSV
├── lang/
│   ├── en/
│   │   └── local_question_diagnostic.php  # Chaînes en anglais
│   └── fr/
│       └── local_question_diagnostic.php  # Chaînes en français
├── styles/
│   └── main.css                   # Styles personnalisés
└── scripts/
    └── main.js                    # JavaScript interactif
```

## 🚀 Installation

1. **Copier le dossier** dans `moodle/local/question_diagnostic/`

2. **Se connecter en tant qu'administrateur** et accéder à :
   ```
   Administration du site > Notifications
   ```

3. **Suivre le processus d'installation** du plugin

4. **Accéder à l'outil** via :
   ```
   https://votre-moodle.com/local/question_diagnostic/index.php
   ```

## 💡 Utilisation

### Identifier les Catégories Problématiques

Le dashboard affiche immédiatement :
- 🟡 **Catégories vides** : Sans questions ni sous-catégories
- 🔴 **Catégories orphelines** : Contexte invalide ou manquant
- 🔴 **Doublons** : Catégories avec le même nom dans le même contexte

### Filtrer et Rechercher

1. Utiliser la barre de **recherche** pour trouver une catégorie par nom/ID
2. Sélectionner un **statut** dans le menu déroulant
3. Filtrer par **contexte** spécifique
4. Le tableau se met à jour en temps réel

### Supprimer des Catégories Vides

**Méthode 1 : Suppression individuelle**
- Cliquer sur le bouton "🗑️ Supprimer" dans la colonne Actions
- Confirmer la suppression

**Méthode 2 : Suppression groupée**
1. Cocher les catégories à supprimer
2. Cliquer sur "🗑️ Supprimer la sélection"
3. Confirmer la suppression en masse

### Fusionner des Catégories

1. Cliquer sur "🔀 Fusionner" pour la catégorie source
2. Sélectionner la catégorie destination dans le modal
3. Confirmer la fusion
4. Les questions et sous-catégories sont automatiquement déplacées

### Exporter les Données

1. Cliquer sur "📥 Exporter en CSV"
2. Le fichier est téléchargé avec toutes les statistiques
3. Compatible avec Excel, LibreOffice, Google Sheets

### Trier les Données

- Cliquer sur n'importe quel **en-tête de colonne** pour trier
- Cliquer à nouveau pour inverser l'ordre (ascendant ↔ descendant)

## 🔧 Configuration Requise

- **Moodle** : 4.0+ (testé sur 4.0, 4.1 LTS, 4.3, 4.4, **4.5 recommandé**)
- **PHP** : 7.4+ (8.0+ recommandé)
- **Permissions** : Administrateur du site uniquement
- **Navigateurs** : Chrome, Firefox, Safari, Edge (versions récentes)
- **Base de données** : MySQL 8.0+, MariaDB 10.6+, PostgreSQL 13+

**Note** : Moodle 3.x n'est pas supporté (architecture question_bank_entries requise)

## 🎯 Cas d'Usage

### Gestion des Catégories

#### Nettoyage de Base de Données
Supprimer les catégories vides créées par erreur ou inutilisées.

#### Consolidation
Fusionner des catégories en doublon après une migration ou import.

#### Audit
Identifier les catégories orphelines suite à la suppression de cours.

#### Documentation
Exporter la structure complète de la banque de questions.

### Vérification des Liens Cassés

#### Migration de Serveur
Après une migration, détecter les fichiers qui n'ont pas été transférés correctement.

#### Nettoyage Après Suppression
Identifier les questions avec fichiers manquants après suppression de cours.

#### Restauration Partielle
Vérifier l'intégrité des fichiers après une restauration de sauvegarde.

#### Maintenance Régulière
Contrôle mensuel de la santé de la banque de questions.

#### Questions Partagées
Détecter les liens cassés dans les questions importées d'un contexte à un autre.

## ⚠️ Avertissements

- ⚠️ **Toujours faire une sauvegarde** avant des opérations de suppression/fusion en masse
- ⚠️ Les suppressions et fusions sont **irréversibles**
- ⚠️ Seules les catégories **vides** peuvent être supprimées
- ⚠️ Les fusions déplacent **toutes** les questions et sous-catégories

## 🐛 Dépannage

### Les CSS/JS ne se chargent pas
1. Vider le cache Moodle : `Administration du site > Développement > Purger les caches`
2. Vérifier les permissions des fichiers (lecture pour le serveur web)

### Erreur "Access denied"
- Vérifier que vous êtes connecté en tant qu'**administrateur du site**
- Pas seulement administrateur de cours !

### Le tableau est vide
- Vérifier que des catégories de questions existent dans la base
- Vérifier les logs PHP pour d'éventuelles erreurs

## 📝 Changelog

### v1.11.27 (2025-12-14) - ACTUEL
- ✅ Corrections et améliorations sur le nettoyage de la banque de questions
- ✅ Pagination et détection d'utilisation améliorées (questions inutilisées)
- ✅ Nettoyage de doublons fiabilisé (sélection "garder une" + actions en lot)

### Versions Précédentes Majeures

**v1.9.30** (2025-10-11) - Optimisations Gros Sites
- Pagination serveur pour grandes bases (100k+ questions)
- Transactions SQL avec rollback automatique
- Cache amélioré avec gestion centralisée

**v1.9.0** (2025-10-10) - Suppression Sécurisée Questions
- Suppression individuelle et en masse de questions
- Vérifications d'utilisation dans les tests
- Protection des questions actives

**v1.4.0** (2025-10-08) - Corrections Critiques
- Compatibilité Moodle 4.5
- Corrections architecture question_bank_entries

**v1.1.0** (2025-10-07) - Détection Liens Cassés
- Analyse automatique des liens cassés
- Support plugins tiers

[→ Voir le CHANGELOG complet](CHANGELOG.md)

## 👨‍💻 Développement

### Architecture

Le code suit les bonnes pratiques Moodle :
- **Namespace** : `local_question_diagnostic`
- **API Moodle** : Utilisation de `$DB`, `html_writer`, `moodle_url`
- **Sécurité** : `require_sesskey()`, validation des entrées
- **Responsive** : Grid CSS, media queries

### Personnalisation

**Modifier les styles** : Éditer `styles/main.css`
**Modifier le comportement JS** : Éditer `scripts/main.js`
**Ajouter des actions** : Créer un nouveau fichier dans `actions/`

## 📄 Licence

GNU General Public License v3 or later (GPL-3.0-or-later)

Compatible avec Moodle.

## 📚 Documentation Complète

**📖 [INDEX COMPLET DE LA DOCUMENTATION](docs/README.md)** ← Commencez ici

### Démarrage Rapide
- [**Installation**](docs/installation/INSTALLATION.md) - Guide d'installation
- [**Guide Rapide**](docs/guides/QUICKSTART.md) - Démarrage rapide
- [**Tests**](tests/README.md) - Tests unitaires et PHPUnit

### Documentation Technique
- [**Audit Complet**](docs/audits/AUDIT_COMPLET_v1.9.42.md) - Audit de code v1.9.42
- [**Base de Données**](docs/technical/MOODLE_4.5_DATABASE_REFERENCE.md) - Structure BDD Moodle 4.5
- [**Performance**](docs/performance/GROS_SITES_OPTIMISATIONS_v1.9.30.md) - Optimisations gros sites
- [**Compatibilité**](docs/technical/MOODLE_COMPATIBILITY_POLICY.md) - Politique de compatibilité

### Guides Fonctionnels
- [**Opérations en masse**](docs/guides/GUIDE_OPERATIONS_PAR_LOT.md) - Suppression groupée
- [**Liens cassés**](docs/features/FEATURE_BROKEN_LINKS.md) - Détection et réparation
- [**Patterns de confirmation**](docs/guides/USER_CONSENT_PATTERNS.md) - Bonnes pratiques UX

## 🤝 Contribution

Les contributions sont les bienvenues !

1. Fork le projet
2. Créer une branche (`git checkout -b feature/amelioration`)
3. Commit les changements (`git commit -m 'Ajout fonctionnalité X'`)
4. Push sur la branche (`git push origin feature/amelioration`)
5. Ouvrir une Pull Request

## 📧 Support

Pour toute question ou problème, ouvrir une issue sur le dépôt.

---

**Développé avec ❤️ pour la communauté Moodle**

