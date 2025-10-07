# Correction de Compatibilité Moodle 4.5

## Problème Rencontré

**Erreur lors de la suppression de catégorie :**
```
Erreur de lecture de la base de données
Unknown column 'category' in 'WHERE'
SELECT COUNT(*) FROM doksvtal_question WHERE category = ?
```

## Cause

Dans **Moodle 4.0+**, la structure de la base de données pour les questions a été refactorisée :

### Ancienne Structure (Moodle 3.x)
- La table `question` contenait une colonne `category`
- Relation directe : `question.category` → `question_categories.id`

### Nouvelle Structure (Moodle 4.x)
- La colonne `category` n'existe plus dans la table `question`
- La relation passe désormais par deux tables intermédiaires :
  - `question_bank_entries` (contient `questioncategoryid`)
  - `question_versions` (lie les questions aux entrées)
- Relation : `question` → `question_versions` → `question_bank_entries` → `question_categories`

## Modifications Apportées

### 1. `classes/category_manager.php`

#### Fonction `get_category_stats()`
**Avant :**
```php
$sql = "SELECT COUNT(*) FROM {question} WHERE category = :categoryid AND hidden = 0";
```

**Après :**
```php
$sql = "SELECT COUNT(DISTINCT q.id) 
        FROM {question} q
        JOIN {question_versions} qv ON qv.questionid = q.id
        JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
        WHERE qbe.questioncategoryid = :categoryid AND q.hidden = 0";
```

#### Fonction `delete_category()`
**Avant :**
```php
$sql = "SELECT COUNT(*) FROM {question} WHERE category = :categoryid";
```

**Après :**
```php
$sql = "SELECT COUNT(DISTINCT q.id) 
        FROM {question} q
        JOIN {question_versions} qv ON qv.questionid = q.id
        JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
        WHERE qbe.questioncategoryid = :categoryid";
```

#### Fonction `merge_categories()`
**Avant :**
```php
$sql = "UPDATE {question} SET category = :destid WHERE category = :sourceid";
```

**Après :**
```php
$sql = "UPDATE {question_bank_entries} SET questioncategoryid = :destid WHERE questioncategoryid = :sourceid";
```

### 2. `classes/question_analyzer.php`

#### Fonction `get_question_stats()`
**Avant :**
```php
$category = $DB->get_record('question_categories', ['id' => $question->category]);
$stats->category_id = $question->category;
```

**Après :**
```php
$category_sql = "SELECT qc.* 
                FROM {question_categories} qc
                JOIN {question_bank_entries} qbe ON qbe.questioncategoryid = qc.id
                JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                WHERE qv.questionid = :questionid
                LIMIT 1";
$category = $DB->get_record_sql($category_sql, ['questionid' => $question->id]);
$stats->category_id = $category ? $category->id : 0;
```

#### Fonction `get_question_usage()`
**Avant :**
```php
OR qs.questioncategoryid IN (
    SELECT id FROM {question_categories} 
    WHERE id = (SELECT category FROM {question} WHERE id = :questionid2)
)
```

**Après :**
```php
OR qs.questioncategoryid IN (
    SELECT qbe.questioncategoryid 
    FROM {question_bank_entries} qbe
    JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
    WHERE qv.questionid = :questionid2
)
```

#### Fonction `calculate_question_similarity()`
**Avant :**
```php
if ($q1->category === $q2->category) {
    $score += $weights['category'];
}
```

**Après :**
```php
$cat1_id = self::get_question_category_id($q1->id);
$cat2_id = self::get_question_category_id($q2->id);
if ($cat1_id && $cat2_id && $cat1_id === $cat2_id) {
    $score += $weights['category'];
}
```

#### Nouvelle Fonction Helper `get_question_category_id()`
```php
private static function get_question_category_id($questionid) {
    global $DB;
    
    try {
        $sql = "SELECT qbe.questioncategoryid 
                FROM {question_bank_entries} qbe
                JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                WHERE qv.questionid = :questionid
                LIMIT 1";
        $result = $DB->get_record_sql($sql, ['questionid' => $questionid]);
        return $result ? $result->questioncategoryid : null;
    } catch (\Exception $e) {
        return null;
    }
}
```

#### Fonction `get_question_bank_url()`
**Avant :**
```php
$category = $DB->get_record('question_categories', ['id' => $question->category]);
```

**Après :**
```php
$category_sql = "SELECT qc.* 
                FROM {question_categories} qc
                JOIN {question_bank_entries} qbe ON qbe.questioncategoryid = qc.id
                JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                WHERE qv.questionid = :questionid
                LIMIT 1";
$category = $DB->get_record_sql($category_sql, ['questionid' => $question->id]);
```

## Tables de la Base de Données Moodle 4.x

### Structure des Questions

| Table | Colonnes Principales | Description |
|-------|---------------------|-------------|
| `question` | `id`, `qtype`, `name`, `questiontext`, `hidden` | Contient les versions des questions |
| `question_versions` | `questionid`, `questionbankentryid`, `version` | Lie les questions aux entrées de la banque |
| `question_bank_entries` | `id`, `questioncategoryid` | Entrées de la banque de questions avec lien vers catégorie |
| `question_categories` | `id`, `name`, `contextid`, `parent` | Catégories de questions |

### Diagramme de Relation
```
question_categories
       ↑
       | (questioncategoryid)
       |
question_bank_entries
       ↑
       | (questionbankentryid)
       |
question_versions
       ↑
       | (questionid)
       |
    question
```

## Tests à Effectuer

Après ces modifications, testez les fonctionnalités suivantes :

1. ✅ **Suppression de catégorie vide**
   - Aller dans `/local/question_diagnostic/categories.php`
   - Tenter de supprimer une catégorie vide
   - Vérifier qu'aucune erreur SQL ne se produit

2. ✅ **Fusion de catégories**
   - Tester la fusion de deux catégories
   - Vérifier que les questions sont bien déplacées

3. ✅ **Affichage des statistiques**
   - Vérifier que les compteurs de questions par catégorie sont corrects
   - Vérifier l'affichage des statistiques de questions

4. ✅ **Liste des questions**
   - Aller dans `/local/question_diagnostic/questions_cleanup.php`
   - Vérifier que les catégories des questions s'affichent correctement

5. ✅ **Détection de doublons**
   - Vérifier que la comparaison par catégorie fonctionne

## Compatibilité

Ces modifications rendent le plugin **compatible uniquement avec Moodle 4.0+**.

Si vous devez maintenir la compatibilité avec Moodle 3.x, il faudrait :
- Détecter la version de Moodle
- Utiliser des requêtes conditionnelles selon la version

## Recommandations

1. **Tester en environnement de développement** avant de déployer en production
2. **Faire une sauvegarde** de la base de données avant la mise à jour
3. **Vérifier les logs** Moodle après déploiement
4. **Tester toutes les fonctionnalités** du plugin

## Version du Plugin

Pensez à mettre à jour le fichier `version.php` pour refléter ces changements :

```php
$plugin->version = 2025100702;  // Nouvelle version
$plugin->requires = 2022112800; // Moodle 4.0 minimum requis
```

## Support

Si vous rencontrez d'autres problèmes liés à la compatibilité Moodle 4.5, vérifiez :
- Les logs Moodle : `Administration du site` > `Rapports` > `Journaux`
- Le mode débogage : `Administration du site` > `Développement` > `Débogage`
- La documentation Moodle : https://docs.moodle.org/4x/

---

**Date de correction :** 7 octobre 2025  
**Version Moodle ciblée :** 4.5  
**Fichiers modifiés :** 2 (category_manager.php, question_analyzer.php)

