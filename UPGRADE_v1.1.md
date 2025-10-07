# Guide de Mise à Jour vers la version 1.1.0

## 📦 Vue d'ensemble

La version 1.1.0 introduit une nouvelle fonctionnalité majeure : **la détection et réparation des questions avec liens cassés**. Cette mise à jour nécessite quelques étapes simples pour profiter des nouvelles fonctionnalités.

## ⚠️ Avant de commencer

### Prérequis
- Version actuelle : 1.0.x
- Accès administrateur au serveur Moodle
- Accès à la base de données (recommandé pour sauvegarde)

### Sauvegarde (CRITIQUE)
```bash
# 1. Sauvegarde de la base de données
mysqldump -u username -p database_name > backup_before_1.1.0.sql

# 2. Sauvegarde des fichiers du plugin
cd /path/to/moodle/local/
tar -czf question_diagnostic_v1.0.backup.tar.gz question_diagnostic/
```

## 🚀 Processus de mise à jour

### Option 1 : Mise à jour manuelle (Recommandée)

#### Étape 1 : Télécharger les nouveaux fichiers
```bash
cd /path/to/moodle/local/question_diagnostic
```

#### Étape 2 : Ajouter les nouveaux fichiers

**Nouveaux fichiers à ajouter :**
```
classes/question_link_checker.php
broken_links.php
categories.php
FEATURE_BROKEN_LINKS.md
FEATURE_SUMMARY_v1.1.md
UPGRADE_v1.1.md
```

**Fichiers à remplacer :**
```
index.php (modifié - devient menu principal)
lang/fr/local_question_diagnostic.php (nouvelles chaînes)
lang/en/local_question_diagnostic.php (nouvelles chaînes)
CHANGELOG.md (mis à jour)
```

**Fichiers inchangés (pas de modification nécessaire) :**
```
classes/category_manager.php
actions/*.php
scripts/main.js
styles/main.css
lib.php
version.php
```

#### Étape 3 : Mettre à jour la base de données

**Aucune modification de la base de données requise !** ✅

Cette version n'ajoute pas de nouvelles tables ni de nouveaux champs. Elle utilise uniquement les tables existantes de Moodle.

#### Étape 4 : Vider les caches Moodle

```bash
# Via CLI
php admin/cli/purge_caches.php

# Ou via l'interface web
# Administration du site > Développement > Purger tous les caches
```

#### Étape 5 : Vérifier l'installation

1. Se connecter en tant qu'administrateur
2. Aller sur `/local/question_diagnostic/index.php`
3. Vérifier que le **nouveau menu principal** s'affiche
4. Cliquer sur **"Vérification des Liens"**
5. Vérifier que la page de détection des liens cassés fonctionne

### Option 2 : Mise à jour via Git

```bash
cd /path/to/moodle/local/question_diagnostic

# Sauvegarder les modifications locales si nécessaire
git stash

# Récupérer la nouvelle version
git pull origin main
# ou
git checkout v1.1.0

# Restaurer les modifications locales si nécessaire
git stash pop

# Vider les caches
php ../../admin/cli/purge_caches.php
```

## 🔍 Vérification post-installation

### Tests à effectuer

#### 1. Menu principal
- [ ] Le menu principal s'affiche avec 2 cartes
- [ ] Les statistiques globales sont correctes
- [ ] Les liens vers les outils fonctionnent

#### 2. Gestion des catégories
- [ ] La page categories.php fonctionne
- [ ] Toutes les fonctionnalités existantes sont présentes
- [ ] Le lien retour fonctionne

#### 3. Vérification des liens
- [ ] La page broken_links.php s'affiche
- [ ] Les statistiques sont calculées
- [ ] Le tableau des questions s'affiche
- [ ] Les filtres fonctionnent
- [ ] Le modal de réparation s'ouvre

#### 4. Traductions
- [ ] Les nouvelles chaînes FR s'affichent
- [ ] Les nouvelles chaînes EN s'affichent
- [ ] Pas de chaînes manquantes

## 📊 Changements de navigation

### Avant (v1.0.x)
```
index.php → Tableau des catégories directement
```

### Après (v1.1.0)
```
index.php → Menu principal
  ├── categories.php → Tableau des catégories
  └── broken_links.php → Vérification des liens
```

**Important :** Si vous avez des liens directs vers `index.php` dans votre documentation ou favoris, ils continueront de fonctionner mais afficheront maintenant le menu principal.

## 🔄 Rétrocompatibilité

### Fonctionnalités conservées
✅ Toutes les fonctionnalités de la v1.0.x sont conservées  
✅ Les actions existantes fonctionnent identiquement  
✅ Les exports CSV sont inchangés  
✅ Les filtres et recherches fonctionnent pareil  

### Nouveaux comportements
- `index.php` affiche maintenant un menu au lieu du tableau
- Le tableau des catégories est accessible via `categories.php`
- Nouvelle page `broken_links.php` pour les liens cassés

## 🎨 Personnalisations

### Si vous avez personnalisé le CSS

Le CSS existant (`styles/main.css`) n'a **pas été modifié**.

Les nouvelles pages utilisent :
- Les mêmes classes CSS existantes
- Quelques nouvelles classes inline pour le menu
- Compatibilité totale avec vos personnalisations

### Si vous avez personnalisé le JavaScript

Le JavaScript existant (`scripts/main.js`) n'a **pas été modifié**.

La nouvelle fonctionnalité utilise du JavaScript inline pour :
- Les filtres de la page broken_links.php
- Le modal de réparation

### Si vous avez personnalisé les traductions

Ajoutez simplement les nouvelles chaînes à vos fichiers de langue personnalisés. Consultez `lang/fr/local_question_diagnostic.php` pour la liste complète.

## 🐛 Problèmes connus et solutions

### Problème : "Page blanche" après mise à jour

**Cause :** Cache non vidé

**Solution :**
```bash
php admin/cli/purge_caches.php
```

### Problème : Chaînes de langue manquantes

**Cause :** Fichiers de langue non mis à jour

**Solution :**
1. Vérifier que `lang/fr/local_question_diagnostic.php` contient bien les nouvelles chaînes
2. Vider le cache de langue : Administration > Langue > Cache de langue

### Problème : Erreur "Class not found: question_link_checker"

**Cause :** Nouveau fichier de classe non copié

**Solution :**
1. Vérifier la présence de `classes/question_link_checker.php`
2. Vérifier les permissions (doit être lisible par le serveur web)
3. Vider les caches

### Problème : Page 404 sur broken_links.php

**Cause :** Fichier non copié ou permissions incorrectes

**Solution :**
```bash
# Vérifier la présence du fichier
ls -l /path/to/moodle/local/question_diagnostic/broken_links.php

# Corriger les permissions si nécessaire
chmod 644 broken_links.php
```

## 📈 Performance

### Impact sur les performances

La nouvelle fonctionnalité peut être **intensive** lors de l'analyse initiale si vous avez beaucoup de questions.

**Recommandations :**
- Première analyse : Prévoir 1-2 minutes pour 1000 questions
- Analyses suivantes : Plus rapides grâce au cache
- Sur serveur de production : Lancer pendant heures creuses

### Optimisations possibles

Si les analyses sont trop lentes :

1. **Augmenter les limites PHP** (dans php.ini) :
```ini
max_execution_time = 300
memory_limit = 256M
```

2. **Utiliser la CLI** (future fonctionnalité) :
```bash
php admin/cli/check_broken_links.php
```

## 🔐 Sécurité

### Nouvelles vérifications

La v1.1.0 ajoute les mêmes vérifications de sécurité :
- ✅ Accès réservé aux administrateurs
- ✅ Protection CSRF (sesskey)
- ✅ Validation des paramètres
- ✅ Gestion des erreurs

### Permissions requises

Aucune nouvelle permission requise. Les mêmes que v1.0.x :
- `is_siteadmin()` pour accéder aux pages
- Contexte système pour les opérations

## 📚 Documentation mise à jour

Nouveaux documents à consulter :
- `FEATURE_BROKEN_LINKS.md` : Documentation technique de la nouvelle fonctionnalité
- `FEATURE_SUMMARY_v1.1.md` : Résumé complet de la version
- `UPGRADE_v1.1.md` : Ce guide de mise à jour
- `CHANGELOG.md` : Historique détaillé des modifications

## ✅ Checklist de mise à jour complète

### Avant mise à jour
- [ ] Sauvegarde de la base de données effectuée
- [ ] Sauvegarde des fichiers effectuée
- [ ] Version actuelle notée (1.0.x)
- [ ] Downtime planifié si nécessaire

### Pendant mise à jour
- [ ] Nouveaux fichiers copiés
- [ ] Fichiers existants remplacés
- [ ] Permissions vérifiées
- [ ] Caches Moodle vidés

### Après mise à jour
- [ ] Menu principal fonctionne
- [ ] Page catégories fonctionne
- [ ] Page liens cassés fonctionne
- [ ] Traductions correctes
- [ ] Tests des fonctionnalités effectués
- [ ] Documentation lue

## 🆘 Support

### En cas de problème

1. **Consulter la documentation** :
   - Ce guide (UPGRADE_v1.1.md)
   - Documentation technique (FEATURE_BROKEN_LINKS.md)
   - CHANGELOG.md

2. **Activer le mode débogage** :
   - Administration > Développement > Débogage
   - Définir sur "DÉVELOPPEUR"
   - Afficher les messages de débogage

3. **Vérifier les logs Moodle** :
   - Administration > Rapports > Journaux
   - Filtrer par "local_question_diagnostic"

4. **Restaurer la version précédente** si nécessaire :
```bash
# Restaurer les fichiers
cd /path/to/moodle/local/
rm -rf question_diagnostic/
tar -xzf question_diagnostic_v1.0.backup.tar.gz

# Vider les caches
php admin/cli/purge_caches.php
```

## 🎉 Profiter des nouvelles fonctionnalités

Une fois la mise à jour terminée :

1. **Lancer une première analyse** :
   - Aller sur broken_links.php
   - Attendre le chargement des statistiques
   - Explorer les questions problématiques

2. **Planifier la maintenance** :
   - Noter les questions avec liens cassés
   - Prioriser les réparations
   - Documenter les actions

3. **Intégrer dans le workflow** :
   - Vérifier les liens mensuellement
   - Après chaque migration
   - Après restauration de cours

## 📊 Statistiques de mise à jour

**Temps estimé :** 10-15 minutes  
**Difficulté :** Facile ⭐⭐☆☆☆  
**Impact :** Aucun sur les données existantes  
**Downtime :** Optionnel (recommandé quelques minutes)  

---

**Version du guide :** 1.0  
**Date :** Octobre 2025  
**Compatible avec :** Moodle 3.9+  

**Bon upgrade ! 🚀**

