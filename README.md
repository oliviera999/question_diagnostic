# 🔧 Moodle Question Bank Diagnostic Tool

Outil complet de gestion et diagnostic de la banque de questions pour Moodle 4.0+

**Version actuelle :** v1.9.34 | **Statut :** Production-Ready ✅

### 📌 Compatibilité Moodle

- **✅ Supporté** : Moodle 4.0, 4.1 LTS, 4.3, 4.4, **4.5** (recommandé)
- **❌ Non supporté** : Moodle 3.x (architecture incompatible)
- **📖 Détails** : [docs/technical/MOODLE_COMPATIBILITY_POLICY.md](docs/technical/MOODLE_COMPATIBILITY_POLICY.md)

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

## 🌟 Nouveautés v1.1.0

### 🔗 Détection des Liens Cassés (NOUVEAU !)
- **Analyse automatique** de toutes les questions pour détecter les liens cassés
- **Support complet** des plugins tiers (drag and drop sur image, markers, etc.)
- **Interface dédiée** avec dashboard statistiques et filtres
- **Options de réparation** pour chaque problème détecté
- **Documentation complète** et guides d'utilisation

[→ Voir la documentation détaillée](FEATURE_BROKEN_LINKS.md)

## 📋 Fonctionnalités

### 🎯 Deux Outils Principaux

#### 1. 📂 Gestion des Catégories
Gérez les catégories de questions : détectez et corrigez les catégories orphelines, vides ou en doublon.

#### 2. 🔗 Vérification des Liens (NOUVEAU v1.1.0)
Détectez les questions avec des liens cassés vers des images ou fichiers manquants dans moodledata.

### 📊 Dashboard et Statistiques

#### Menu Principal
- Vue d'ensemble globale de la santé de votre banque de questions
- Statistiques rapides sur catégories et questions
- Accès rapide aux deux outils principaux

#### Gestion des Catégories
- Vue d'ensemble complète des catégories de questions
- Statistiques en temps réel (total, vides, orphelines, doublons)
- Cartes visuelles avec codes couleur

#### Vérification des Liens (NOUVEAU)
- Statistiques sur les questions avec liens cassés
- Répartition par type de question
- Pourcentage de santé globale
- Liens directs vers la banque de questions

### 🔍 Filtres et Recherche

#### Sur les Catégories
- **Recherche par nom ou ID** de catégorie
- **Filtrage par statut** : Toutes, Vides, Orphelines, OK
- **Filtrage par contexte** : Système, Cours, Module, etc.
- Statistiques de filtrage dynamiques

#### Sur les Questions (NOUVEAU)
- **Recherche en temps réel** par nom, ID ou catégorie
- **Filtrage par type** de question
- Mise à jour instantanée des résultats

### ✅ Sélection Multiple et Actions Groupées
- Cases à cocher pour sélection individuelle
- Sélectionner/désélectionner tout
- Suppression groupée de catégories vides
- Barre d'actions contextuelle

### 🔗 Vérification des Liens (NOUVEAU v1.1.0)

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

- **Moodle** : 3.9 ou supérieur (testé sur 4.3, 4.4, 4.5)
- **PHP** : 7.4 ou supérieur
- **Permissions** : Administrateur du site uniquement
- **Navigateurs** : Chrome, Firefox, Safari, Edge (versions récentes)
- **Base de données** : MySQL, MariaDB ou PostgreSQL

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

### Vérification des Liens (NOUVEAU)

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

### v1.1.0 (2025-10-07) - ACTUEL
- 🎉 **Nouvelle fonctionnalité majeure** : Détection des liens cassés
- ✨ Classe `question_link_checker` pour analyse complète
- ✨ Interface dédiée avec dashboard et filtres
- ✨ Support des plugins tiers (drag and drop, etc.)
- ✨ Options de réparation pour chaque problème
- 🔄 Menu principal restructuré (2 outils)
- 📚 Documentation extensive (4 nouveaux docs)
- 🌐 40+ nouvelles chaînes de langue (FR/EN)

[→ Voir le CHANGELOG complet](CHANGELOG.md)

### v1.0.1 (2025-01-07)
- ✨ Liens directs vers la banque de questions
- 🎨 Bouton "👁️ Voir" dans les actions
- 🔗 Navigation améliorée

### v1.0.0 (2025-01-07)
- 🎉 Version initiale
- ✅ Dashboard avec statistiques complètes
- ✅ Filtres et recherche avancés
- ✅ Suppression individuelle et groupée
- ✅ Fusion de catégories
- ✅ Export CSV
- ✅ Interface responsive et moderne
- ✅ Tri par colonne
- ✅ Sélection multiple

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

## 📚 Documentation

### Guides d'utilisation
- [**README.md**](README.md) - Ce fichier (vue d'ensemble)
- [**QUICKSTART.md**](QUICKSTART.md) - Guide de démarrage rapide
- [**INSTALLATION.md**](INSTALLATION.md) - Installation détaillée

### Fonctionnalités
- [**FEATURE_NAVIGATION.md**](FEATURE_NAVIGATION.md) - Navigation et banque de questions
- [**FEATURE_BROKEN_LINKS.md**](FEATURE_BROKEN_LINKS.md) - ✨ Détection des liens cassés (NOUVEAU)
- [**FEATURE_SUMMARY_v1.1.md**](FEATURE_SUMMARY_v1.1.md) - ✨ Résumé complet v1.1.0 (NOUVEAU)

### Mise à jour et maintenance
- [**CHANGELOG.md**](CHANGELOG.md) - Historique des versions
- [**UPGRADE_v1.1.md**](UPGRADE_v1.1.md) - ✨ Guide de mise à jour v1.0 → v1.1 (NOUVEAU)
- [**IMPLEMENTATION_COMPLETE.md**](IMPLEMENTATION_COMPLETE.md) - ✨ Récapitulatif implémentation (NOUVEAU)

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

