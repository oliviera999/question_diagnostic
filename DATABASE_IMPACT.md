# 🗄️ Impact sur la Base de Données - Plugin Question Diagnostic

## ⚠️ INFORMATION CRITIQUE POUR LA SÉCURITÉ DES DONNÉES

Ce document liste **TOUTES** les tables de la base de données qui sont lues ou modifiées par le plugin `local_question_diagnostic`.

---

## 📊 Résumé des Impacts

### ✅ Tables en LECTURE SEULE (SELECT uniquement)
Ces tables sont **consultées** mais **JAMAIS modifiées** :

| Table | Usage | Fichiers concernés |
|-------|-------|-------------------|
| `question` | Lister les questions | `category_manager.php`, `question_analyzer.php`, `orphan_entries.php` |
| `question_versions` | Récupérer les versions des questions | Tous les fichiers d'analyse |
| `question_categories` | Lister les catégories existantes | Tous les fichiers |
| `context` | Vérifier la validité des contextes | `category_manager.php`, `orphan_entries.php` |
| `user` | Afficher les noms des propriétaires | `orphan_entries.php`, `question_analyzer.php` |
| `quiz` | Afficher l'usage dans les quiz | `question_analyzer.php` |
| `quiz_slots` | Vérifier si question utilisée dans quiz | `question_analyzer.php` |
| `question_attempts` | Compter les tentatives | `question_analyzer.php` |
| `question_usages` | Analyser l'utilisation | `question_analyzer.php` |
| `files` | Vérifier les liens cassés | `question_link_checker.php` |

### 🔴 Table MODIFIABLE (UPDATE) - ACTION UTILISATEUR REQUISE

| Table | Type de modification | Quand ? | Fichier | Confirmation requise |
|-------|---------------------|---------|---------|---------------------|
| **`question_bank_entries`** | **UPDATE** du champ `questioncategoryid` | Réassignation d'une entry orpheline | `orphan_entries.php` ligne 65 | ✅ OUI (page de confirmation) |

### ⛔ Table SUPPRIMABLE (DELETE) - ACTION UTILISATEUR REQUISE

| Table | Type de modification | Quand ? | Fichier | Confirmation requise |
|-------|---------------------|---------|---------|---------------------|
| **`question_categories`** | **DELETE** de catégories vides | Suppression de catégorie vide | `actions/delete.php` | ✅ OUI (page de confirmation) |

---

## 🔒 Détails des Modifications Possibles

### 1. Modification de `question_bank_entries`

**Opération :** Réassignation d'une entry orpheline vers une catégorie "Récupération"

**Requête SQL exacte :**
```sql
UPDATE mdl_question_bank_entries 
SET questioncategoryid = [ID_CATEGORIE_CIBLE] 
WHERE id = [ID_ENTRY];
```

**Déclenchement :**
- Fichier : `orphan_entries.php` (ligne 62-66)
- Action : Clic sur "Confirmer la réassignation" après avoir sélectionné une catégorie cible
- Protection : 
  - ✅ Confirmation utilisateur obligatoire
  - ✅ Validation `sesskey`
  - ✅ Vérification que la catégorie cible existe
  - ✅ Vérification que l'entry est bien orpheline

**Impact :**
- ✅ Les questions liées à cette entry deviennent visibles dans la nouvelle catégorie
- ✅ Aucune donnée supprimée
- ✅ Réversible (peut être réassigné à nouveau)

**Exemple de backup avant modification :**
```sql
-- Sauvegarder l'entry AVANT modification
SELECT * FROM mdl_question_bank_entries WHERE id = 272509;
-- Résultat : id=272509, questioncategoryid=5199 (ancienne valeur)

-- Après modification, pour restaurer :
UPDATE mdl_question_bank_entries 
SET questioncategoryid = 5199 
WHERE id = 272509;
```

### 2. Suppression dans `question_categories`

**Opération :** Suppression d'une catégorie vide (0 questions, 0 sous-catégories)

**Requête SQL exacte :**
```sql
DELETE FROM mdl_question_categories 
WHERE id = [ID_CATEGORIE];
```

**Déclenchement :**
- Fichier : `actions/delete.php`
- Action : Clic sur "Confirmer la suppression" dans `categories.php`
- Protection :
  - ✅ Confirmation utilisateur obligatoire
  - ✅ Validation `sesskey`
  - ✅ Vérification que la catégorie est vide (0 questions)
  - ✅ Vérification que la catégorie n'a pas de sous-catégories

**Impact :**
- ⚠️ La catégorie est supprimée définitivement
- ⚠️ **NON RÉVERSIBLE** sans backup
- ✅ Aucune question affectée (catégorie vide seulement)

**Exemple de backup avant suppression :**
```sql
-- Sauvegarder la catégorie AVANT suppression
SELECT * FROM mdl_question_categories WHERE id = 123;

-- Pour restaurer après suppression :
INSERT INTO mdl_question_categories 
(id, name, contextid, info, infoformat, stamp, parent, sortorder, idnumber) 
VALUES (...); -- Utiliser les valeurs sauvegardées
```

---

## 🛡️ Commandes de Backup Recommandées

### Option 1 : Backup COMPLET de la base de données (recommandé)

```bash
# MySQL/MariaDB
mysqldump -u [user] -p [database_name] > backup_avant_question_diagnostic_$(date +%Y%m%d_%H%M%S).sql

# PostgreSQL
pg_dump -U [user] -h [host] [database_name] > backup_avant_question_diagnostic_$(date +%Y%m%d_%H%M%S).sql
```

### Option 2 : Backup PARTIEL (tables impactées uniquement)

```bash
# MySQL/MariaDB - Backup des 2 tables potentiellement modifiées
mysqldump -u [user] -p [database_name] \
  mdl_question_bank_entries \
  mdl_question_categories \
  > backup_tables_question_diagnostic_$(date +%Y%m%d_%H%M%S).sql
```

### Option 3 : Backup SQL Manuel (avant chaque action)

#### Avant de réassigner des entries orphelines :
```sql
-- Sauvegarder toutes les entries orphelines AVANT modification
CREATE TABLE mdl_question_bank_entries_backup_20251007 AS 
SELECT qbe.* 
FROM mdl_question_bank_entries qbe
LEFT JOIN mdl_question_categories qc ON qc.id = qbe.questioncategoryid
WHERE qc.id IS NULL;

-- Vérifier le backup
SELECT COUNT(*) FROM mdl_question_bank_entries_backup_20251007;
```

#### Avant de supprimer des catégories vides :
```sql
-- Sauvegarder toutes les catégories vides AVANT suppression
CREATE TABLE mdl_question_categories_backup_20251007 AS
SELECT qc.*
FROM mdl_question_categories qc
LEFT JOIN mdl_question_bank_entries qbe ON qbe.questioncategoryid = qc.id
WHERE qbe.id IS NULL;

-- Vérifier le backup
SELECT COUNT(*) FROM mdl_question_categories_backup_20251007;
```

---

## 🔄 Procédures de Restauration

### Restaurer une entry réassignée par erreur

```sql
-- 1. Identifier l'entry modifiée
SELECT id, questioncategoryid 
FROM mdl_question_bank_entries 
WHERE id = [ID_ENTRY];

-- 2. Restaurer l'ancienne valeur (si vous avez noté l'ancien ID)
UPDATE mdl_question_bank_entries 
SET questioncategoryid = [ANCIEN_ID_CATEGORIE] 
WHERE id = [ID_ENTRY];
```

### Restaurer une catégorie supprimée

```sql
-- Si vous avez un backup de la table
INSERT INTO mdl_question_categories 
SELECT * FROM mdl_question_categories_backup_20251007 
WHERE id = [ID_CATEGORIE_SUPPRIMEE];
```

### Restauration complète depuis un dump

```bash
# MySQL/MariaDB
mysql -u [user] -p [database_name] < backup_avant_question_diagnostic_20251007_153045.sql

# PostgreSQL
psql -U [user] -h [host] -d [database_name] -f backup_avant_question_diagnostic_20251007_153045.sql
```

---

## 📋 Checklist de Sécurité AVANT toute modification

### Avant de réassigner des entries orphelines :

- [ ] ✅ Backup complet de la base de données OU
- [ ] ✅ Backup de la table `mdl_question_bank_entries`
- [ ] ✅ Noter les IDs des entries à modifier et leurs anciennes valeurs
- [ ] ✅ Vérifier que la catégorie "Récupération" existe
- [ ] ✅ Tester sur 1-2 entries d'abord
- [ ] ✅ Vérifier que les questions sont bien visibles après réassignation

### Avant de supprimer des catégories :

- [ ] ✅ Backup complet de la base de données OU
- [ ] ✅ Backup de la table `mdl_question_categories`
- [ ] ✅ Vérifier que les catégories sont bien VIDES (0 questions, 0 sous-catégories)
- [ ] ✅ Noter les IDs et noms des catégories à supprimer
- [ ] ✅ Être certain de ne pas avoir besoin de ces catégories

---

## 🎯 Tables JAMAIS Modifiées par le Plugin

**GARANTIE :** Ces tables sont en **LECTURE SEULE** - aucune modification n'est jamais effectuée :

- ✅ `question` - Aucun UPDATE, DELETE ou INSERT
- ✅ `question_versions` - Aucun UPDATE, DELETE ou INSERT
- ✅ `question_attempts` - Aucun UPDATE, DELETE ou INSERT
- ✅ `quiz` - Aucun UPDATE, DELETE ou INSERT
- ✅ `quiz_slots` - Aucun UPDATE, DELETE ou INSERT
- ✅ `context` - Aucun UPDATE, DELETE ou INSERT
- ✅ `user` - Aucun UPDATE, DELETE ou INSERT
- ✅ `files` - Aucun UPDATE, DELETE ou INSERT

---

## 📊 Statistiques Actuelles (Votre Installation)

D'après le diagnostic effectué :

| Métrique | Valeur | État |
|----------|--------|------|
| Total catégories | 5835 | ✅ |
| Total questions | 29512 | ✅ |
| **Entries orphelines** | **1610** | ⚠️ Peuvent être réassignées |
| Entries orphelines VIDES | ? | ℹ️ Peuvent être ignorées |
| Entries orphelines AVEC questions | ? | 🔴 À récupérer en priorité |
| Catégories vides | Variable | ℹ️ Peuvent être supprimées |

---

## 🚨 Garanties de Sécurité du Plugin

### ✅ Ce que le plugin fait TOUJOURS :

1. ✅ **Confirmation utilisateur obligatoire** avant toute modification de BDD
2. ✅ **Validation sesskey** sur toutes les actions
3. ✅ **Vérification admin** (is_siteadmin) sur toutes les pages
4. ✅ **Messages explicites** avant/après chaque action
5. ✅ **Validation des données** avant modification
6. ✅ **Journalisation** des actions via les notifications Moodle

### ⛔ Ce que le plugin ne fait JAMAIS :

1. ⛔ Modifier la BDD sans confirmation utilisateur
2. ⛔ Supprimer des questions
3. ⛔ Modifier le contenu des questions
4. ⛔ Supprimer des catégories non-vides
5. ⛔ Modifier les quiz ou les tentatives
6. ⛔ Créer de nouvelles tables dans la BDD

---

## 📞 En cas de Problème

### Si une modification ne produit pas l'effet attendu :

1. **Ne paniquez pas** - aucune donnée critique n'est supprimée
2. **Consultez vos backups** et notez les valeurs à restaurer
3. **Utilisez les requêtes de restauration** fournies ci-dessus
4. **Purgez le cache Moodle** : Administration → Développement → Purger tous les caches
5. **Rechargez la page** (Ctrl+F5)

### Support :

- 📖 Documentation : Ce fichier `DATABASE_IMPACT.md`
- 🐛 Issues GitHub : [Votre repo GitHub]
- 📧 Email : [Votre email]

---

## 📝 Historique des Modifications

| Version | Date | Changement |
|---------|------|------------|
| v1.3.0 | 2025-10-07 | Ajout de la réassignation des entries orphelines |
| v1.2.6 | 2025-10-07 | Correction du comptage (INNER JOIN) - Aucune modification BDD |
| v1.0.0 | 2025-10-01 | Version initiale - Suppression de catégories vides uniquement |

---

**Version du document :** 1.0  
**Date de création :** 7 octobre 2025  
**Auteur :** Plugin local_question_diagnostic  
**Licence :** GNU GPL v3+
