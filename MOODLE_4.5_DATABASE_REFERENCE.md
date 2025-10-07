# 📊 Référence Base de Données Moodle 4.5

## ⚠️ Document Critique

Ce document **DOIT être consulté** avant toute modification impliquant des requêtes sur la base de données.

**Version Moodle** : 4.5 (LTS)  
**Date de référence** : Octobre 2025

---

## 🎯 Tables Utilisées par le Plugin

### 1. `question_categories`

**Description** : Catégories de questions dans la banque de questions.

**Structure Moodle 4.5** :
```sql
CREATE TABLE mdl_question_categories (
    id BIGINT(10) PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    contextid BIGINT(10) NOT NULL,
    info LONGTEXT,
    infoformat TINYINT(2) DEFAULT 0,
    stamp VARCHAR(255) NOT NULL DEFAULT '',
    parent BIGINT(10) DEFAULT 0,
    sortorder BIGINT(10) DEFAULT 999,
    idnumber VARCHAR(100) DEFAULT NULL
);
```

**Colonnes importantes** :
- `id` : ID unique de la catégorie
- `name` : Nom de la catégorie
- `contextid` : Contexte Moodle (système, cours, module)
- `parent` : ID de la catégorie parente (0 = racine)
- `sortorder` : Ordre de tri
- `idnumber` : Numéro d'identification (optionnel, ajouté dans Moodle 4+)

**⚠️ Attention** : La colonne `idnumber` n'existe que depuis Moodle 4.0+

**Utilisation dans le plugin** :
```php
// Récupérer toutes les catégories
$categories = $DB->get_records('question_categories');

// Compter les catégories racines
$count = $DB->count_records('question_categories', ['parent' => 0]);

// Rechercher par contexte
$cats = $DB->get_records('question_categories', ['contextid' => $contextid]);
```

---

### 2. `question`

**Description** : Questions dans la banque de questions.

**Structure Moodle 4.5** :
```sql
CREATE TABLE mdl_question (
    id BIGINT(10) PRIMARY KEY AUTO_INCREMENT,
    category BIGINT(10) NOT NULL,
    parent BIGINT(10) DEFAULT 0,
    name TEXT NOT NULL,
    questiontext LONGTEXT NOT NULL,
    questiontextformat TINYINT(2) DEFAULT 1,
    generalfeedback LONGTEXT,
    generalfeedbackformat TINYINT(2) DEFAULT 1,
    defaultmark DECIMAL(12,7) DEFAULT 1.0000000,
    penalty DECIMAL(12,7) DEFAULT 0.3333333,
    qtype VARCHAR(20) NOT NULL,
    length BIGINT(10) DEFAULT 1,
    stamp VARCHAR(255) NOT NULL DEFAULT '',
    timecreated BIGINT(10) NOT NULL DEFAULT 0,
    timemodified BIGINT(10) NOT NULL DEFAULT 0,
    createdby BIGINT(10) DEFAULT NULL,
    modifiedby BIGINT(10) DEFAULT NULL
);
```

**Colonnes importantes** :
- `id` : ID de la question
- `category` : ID de la catégorie (FK vers question_categories)
- `qtype` : Type de question (multichoice, truefalse, essay, etc.)
- `questiontext` : Texte de la question (peut contenir du HTML avec images)
- `timecreated`, `timemodified` : Timestamps

**Utilisation dans le plugin** :
```php
// Compter les questions dans une catégorie
$count = $DB->count_records('question', ['category' => $categoryid]);

// Récupérer les questions d'une catégorie
$questions = $DB->get_records('question', ['category' => $categoryid]);

// Vérifier si une catégorie est vide
$hasquestions = $DB->record_exists('question', ['category' => $categoryid]);
```

---

### 3. `question_bank_entries` (Nouveau dans Moodle 4.0+)

**Description** : Entrées de la banque de questions (système de versioning).

**Structure Moodle 4.5** :
```sql
CREATE TABLE mdl_question_bank_entries (
    id BIGINT(10) PRIMARY KEY AUTO_INCREMENT,
    questioncategoryid BIGINT(10) NOT NULL,
    idnumber VARCHAR(100) DEFAULT NULL,
    ownerid BIGINT(10) DEFAULT NULL
);
```

**⚠️ Importance** : 
- Depuis Moodle 4.0, les questions sont liées aux catégories via `question_bank_entries`
- Une entrée peut avoir plusieurs versions (table `question_versions`)
- **Impact sur les requêtes** : Pour compter les questions dans une catégorie, il faut potentiellement joindre cette table

**Utilisation** :
```php
// Pour Moodle 4.0+, compter les entrées (pas directement les questions)
$sql = "SELECT COUNT(DISTINCT qbe.id)
        FROM {question_bank_entries} qbe
        WHERE qbe.questioncategoryid = :categoryid";
$count = $DB->count_records_sql($sql, ['categoryid' => $categoryid]);
```

---

### 4. `question_versions` (Nouveau dans Moodle 4.0+)

**Description** : Versions des questions (historique, révisions).

**Structure Moodle 4.5** :
```sql
CREATE TABLE mdl_question_versions (
    id BIGINT(10) PRIMARY KEY AUTO_INCREMENT,
    questionbankentryid BIGINT(10) NOT NULL,
    version BIGINT(10) NOT NULL,
    questionid BIGINT(10) NOT NULL,
    status VARCHAR(10) NOT NULL DEFAULT 'ready'
);
```

**Colonnes importantes** :
- `questionbankentryid` : FK vers question_bank_entries
- `version` : Numéro de version
- `questionid` : FK vers question (version spécifique)
- `status` : Statut (ready, draft, hidden)

**⚠️ Impact** :
- Une question peut avoir plusieurs versions
- Pour obtenir la dernière version, il faut filtrer ou trier

---

### 5. `context`

**Description** : Contextes Moodle (système, cours, activité, utilisateur).

**Structure Moodle 4.5** :
```sql
CREATE TABLE mdl_context (
    id BIGINT(10) PRIMARY KEY AUTO_INCREMENT,
    contextlevel BIGINT(10) NOT NULL,
    instanceid BIGINT(10) NOT NULL,
    path VARCHAR(255) NOT NULL DEFAULT '',
    depth TINYINT(2) NOT NULL DEFAULT 0
);
```

**Niveaux de contexte** :
- `10` : CONTEXT_SYSTEM (système)
- `40` : CONTEXT_COURSECAT (catégorie de cours)
- `50` : CONTEXT_COURSE (cours)
- `70` : CONTEXT_MODULE (activité/module)
- `80` : CONTEXT_BLOCK (bloc)
- `30` : CONTEXT_USER (utilisateur)

**Utilisation dans le plugin** :
```php
// Récupérer le contexte d'une catégorie
$category = $DB->get_record('question_categories', ['id' => $id]);
$context = $DB->get_record('context', ['id' => $category->contextid]);

// Vérifier si le contexte est valide
$contextexists = $DB->record_exists('context', ['id' => $contextid]);
```

---

### 6. `files`

**Description** : Fichiers stockés dans Moodle (moodledata).

**Structure Moodle 4.5** :
```sql
CREATE TABLE mdl_files (
    id BIGINT(10) PRIMARY KEY AUTO_INCREMENT,
    contenthash VARCHAR(40) NOT NULL,
    pathnamehash VARCHAR(40) NOT NULL,
    contextid BIGINT(10) NOT NULL,
    component VARCHAR(100) NOT NULL DEFAULT '',
    filearea VARCHAR(50) NOT NULL DEFAULT '',
    itemid BIGINT(10) NOT NULL,
    filepath VARCHAR(255) NOT NULL DEFAULT '/',
    filename VARCHAR(255) NOT NULL,
    userid BIGINT(10),
    filesize BIGINT(10) NOT NULL,
    mimetype VARCHAR(100),
    status BIGINT(10) DEFAULT 0,
    source LONGTEXT,
    author VARCHAR(255),
    license VARCHAR(255),
    timecreated BIGINT(10) NOT NULL,
    timemodified BIGINT(10) NOT NULL
);
```

**Utilisation pour vérification liens** :
```php
// Vérifier si un fichier existe par son contenthash
$fileexists = $DB->record_exists('files', ['contenthash' => $hash]);

// Récupérer les fichiers d'une question
$files = $DB->get_records('files', [
    'component' => 'question',
    'filearea' => 'questiontext',
    'itemid' => $questionid
]);
```

---

## 🔍 Relations Entre Tables

### Schema Moodle 4.5 (simplifié)

```
context
   ↓ (contextid)
question_categories
   ↓ (questioncategoryid)
question_bank_entries ←→ question_versions
                           ↓ (questionid)
                        question
```

### Relations importantes

1. **Catégories → Context**
   - `question_categories.contextid` → `context.id`
   - Une catégorie appartient à un contexte

2. **Questions → Catégories (Moodle 3.x)**
   - `question.category` → `question_categories.id`
   - Relation directe (ancienne méthode)

3. **Questions → Catégories (Moodle 4.x)**
   - `question_bank_entries.questioncategoryid` → `question_categories.id`
   - `question_versions.questionbankentryid` → `question_bank_entries.id`
   - `question_versions.questionid` → `question.id`
   - Relation indirecte avec versioning

---

## ⚠️ Pièges et Bonnes Pratiques

### Piège #1 : Compter les questions dans Moodle 4.x

```php
// ❌ MAUVAIS - Ne compte que les questions liées directement (ancien système)
$count = $DB->count_records('question', ['category' => $categoryid]);

// ✅ BON - Compatible Moodle 4.x (avec versioning)
$count = $DB->count_records_sql(
    "SELECT COUNT(DISTINCT qbe.id)
     FROM {question_bank_entries} qbe
     WHERE qbe.questioncategoryid = :categoryid",
    ['categoryid' => $categoryid]
);

// ✅ MEILLEUR - Compatible toutes versions
if ($CFG->version >= 2022041900) { // Moodle 4.0+
    // Utiliser question_bank_entries
    $count = $DB->count_records('question_bank_entries', ['questioncategoryid' => $categoryid]);
} else {
    // Utiliser l'ancien système
    $count = $DB->count_records('question', ['category' => $categoryid]);
}
```

### Piège #2 : Colonnes qui n'existent pas dans toutes les versions

```php
// ❌ MAUVAIS - idnumber n'existe pas dans Moodle 3.x
$categories = $DB->get_records('question_categories', ['idnumber' => $value]);

// ✅ BON - Vérifier l'existence de la colonne
$columns = $DB->get_columns('question_categories');
if (isset($columns['idnumber'])) {
    $categories = $DB->get_records('question_categories', ['idnumber' => $value]);
}
```

### Piège #3 : Contextes orphelins

```php
// ❌ MAUVAIS - Ne vérifie pas si le contexte existe
$category = $DB->get_record('question_categories', ['id' => $id]);
// Utilise directement $category->contextid sans vérification

// ✅ BON - Vérifier l'existence du contexte
$category = $DB->get_record('question_categories', ['id' => $id]);
$contextexists = $DB->record_exists('context', ['id' => $category->contextid]);
if (!$contextexists) {
    // Catégorie orpheline !
}
```

---

## 🛠️ Commandes Utiles pour Vérifier la Structure

### Via PHP (dans Moodle)

```php
// Obtenir la structure d'une table
$columns = $DB->get_columns('question_categories');
foreach ($columns as $column) {
    echo $column->name . ' - ' . $column->type . '<br>';
}

// Vérifier si une colonne existe
$columns = $DB->get_columns('question_categories');
if (isset($columns['idnumber'])) {
    echo "La colonne idnumber existe";
}

// Obtenir des informations sur les index
$indexes = $DB->get_indexes('question_categories');
print_r($indexes);
```

### Via MySQL/MariaDB

```sql
-- Voir la structure complète d'une table
DESCRIBE mdl_question_categories;

-- Ou plus détaillé
SHOW CREATE TABLE mdl_question_categories;

-- Lister toutes les colonnes
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'mdl_question_categories'
AND TABLE_SCHEMA = 'moodle';
```

---

## 📚 Ressources Officielles

### Documentation Moodle 4.5

1. **Database Schema** : https://docs.moodle.org/dev/Database_Schema
2. **Question Bank API** : https://moodledev.io/docs/apis/subsystems/questionbank
3. **Moodle 4.5 Release Notes** : https://docs.moodle.org/dev/Moodle_4.5_release_notes
4. **Upgrade Notes** : https://docs.moodle.org/dev/Moodle_4.5_release_notes/Upgrade_notes
5. **DB API** : https://moodledev.io/docs/apis/core/dml

### Changements importants dans Moodle 4.x

- **Moodle 4.0** : Introduction du versioning des questions (question_bank_entries, question_versions)
- **Moodle 4.1** : Amélioration de l'API Question Bank
- **Moodle 4.3** : Optimisations de performance
- **Moodle 4.5** : Corrections et stabilisation

---

## ✅ Checklist Avant Modification

Avant de modifier le code qui touche à la BDD :

- [ ] J'ai consulté ce document de référence
- [ ] J'ai vérifié la structure des tables concernées
- [ ] J'ai testé mes requêtes sur Moodle 4.5
- [ ] J'ai vérifié la compatibilité avec Moodle 4.0+ (versioning)
- [ ] J'ai géré les colonnes qui n'existent pas dans toutes les versions
- [ ] J'ai utilisé l'API $DB (pas de SQL brut)
- [ ] J'ai testé avec des contextes orphelins
- [ ] J'ai vérifié les performances (pas de N+1 queries)

---

**Version** : v1.0 (Octobre 2025)  
**Maintenu par** : Équipe de développement local_question_diagnostic

⚠️ **Ce document doit être mis à jour** lors de chaque nouvelle version majeure de Moodle.

