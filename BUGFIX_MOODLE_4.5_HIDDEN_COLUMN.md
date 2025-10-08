# 🐛 BUGFIX : Colonnes `hidden` et `category` dans Moodle 4.5

**Date** : 8 octobre 2025  
**Version affectée** : v1.2.1  
**Sévérité** : 🔴 CRITIQUE  
**Status** : ✅ CORRIGÉ

---

## 📋 Résumé du Problème

### Symptôme
Toutes les catégories apparaissaient comme **vides ET orphelines** dans l'interface, alors qu'elles contenaient en réalité des questions.

### Cause Racine

Le plugin utilisait deux colonnes qui **n'existent plus dans Moodle 4.5** :

1. **`question.hidden`** ❌ (supprimée dans Moodle 4.0+)
   - Remplacée par `question_versions.status`
   - Valeurs possibles : `'ready'`, `'draft'`, `'hidden'`

2. **`question.category`** ❌ (supprimée dans Moodle 4.0+)
   - Remplacée par `question_bank_entries.questioncategoryid`

### Impact

Les requêtes SQL échouaient silencieusement avec "Erreur de lecture de la base de données", ce qui faisait que :
- Le compteur de questions retournait toujours **0**
- Toutes les catégories apparaissaient comme **vides**
- Les statistiques du dashboard étaient **fausses**

---

## 🔧 Modifications Apportées

### 1. `classes/category_manager.php`

#### Ligne 40-48 : Méthode `get_all_categories_with_stats()`

**Avant (❌ bugué)** :
```php
$sql_questions = "SELECT qbe.questioncategoryid,
                         COUNT(DISTINCT q.id) as total_questions,
                         SUM(CASE WHEN q.hidden = 0 THEN 1 ELSE 0 END) as visible_questions
                  FROM {question_bank_entries} qbe
                  INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                  INNER JOIN {question} q ON q.id = qv.questionid
                  GROUP BY qbe.questioncategoryid";
```

**Après (✅ corrigé)** :
```php
// ⚠️ MOODLE 4.5 : Le statut caché est dans question_versions.status, PAS dans question.hidden
$sql_questions = "SELECT qbe.questioncategoryid,
                         COUNT(DISTINCT q.id) as total_questions,
                         SUM(CASE WHEN qv.status != 'hidden' THEN 1 ELSE 0 END) as visible_questions
                  FROM {question_bank_entries} qbe
                  INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                  INNER JOIN {question} q ON q.id = qv.questionid
                  GROUP BY qbe.questioncategoryid";
```

#### Ligne 194-200 : Méthode `get_category_stats()`

**Avant (❌ bugué)** :
```php
WHERE qbe.questioncategoryid = :categoryid AND q.hidden = 0
```

**Après (✅ corrigé)** :
```php
WHERE qbe.questioncategoryid = :categoryid AND qv.status != 'hidden'
```

---

### 2. `MOODLE_4.5_DATABASE_REFERENCE.md`

Mise à jour de la documentation pour :
- ✅ Indiquer clairement que `question.category` n'existe plus
- ✅ Indiquer clairement que `question.hidden` n'existe plus
- ✅ Fournir les bonnes requêtes SQL pour Moodle 4.5
- ✅ Ajouter des exemples de code correct

---

### 3. `debug_categories.php` (nouveau fichier)

Script de diagnostic créé pour :
- ✅ Vérifier la structure des tables Moodle
- ✅ Tester les requêtes SQL
- ✅ Comparer les anciennes vs nouvelles méthodes
- ✅ Identifier rapidement les problèmes de compatibilité

---

## 🧪 Tests Effectués

### Diagnostic Initial
```
Table 'question' existe : OUI (29427 enregistrements)
Table 'question_bank_entries' existe : OUI (27187 enregistrements)
Table 'question_versions' existe : OUI (30161 enregistrements)

=== Test requête questions ===
ERREUR : Erreur de lecture de la base de données
```

### Après Correction
À tester : **Rechargez `debug_categories.php`** pour vérifier que :
- ✅ La requête globale fonctionne
- ✅ Les compteurs de questions sont corrects
- ✅ Les catégories affichent le bon statut

---

## 📝 Marche à Suivre (Pour l'Utilisateur)

### Étape 1 : Purger le Cache Moodle

```bash
# Via interface admin
Administration du site > Développement > Purger les caches

# Ou via CLI
php admin/cli/purge_caches.php
```

### Étape 2 : Tester le Script de Diagnostic

Accédez à :
```
https://votre-moodle/local/question_diagnostic/debug_categories.php
```

**Résultat attendu** :
```
=== Test requête questions (CORRIGÉE) ===
Nombre de catégories avec questions : [nombre > 0]
Exemples (5 premières) :
  - Catégorie X : Y questions (Z visibles)
```

### Étape 3 : Vérifier l'Interface Principale

Accédez à :
```
https://votre-moodle/local/question_diagnostic/categories.php
```

**Vérifications** :
- ✅ Les catégories avec questions ne sont plus marquées comme "Vides"
- ✅ Les compteurs de questions sont corrects
- ✅ Le dashboard affiche les bonnes statistiques
- ✅ Seulement 31 catégories orphelines (contextes invalides)

### Étape 4 : Nettoyage (Optionnel)

Une fois le problème vérifié, vous pouvez supprimer le fichier de diagnostic :
```bash
rm local/question_diagnostic/debug_categories.php
```

---

## 🎓 Leçons Apprises

### Règle d'Or : TOUJOURS Vérifier la Structure BDD

Avant d'utiliser une colonne dans une requête SQL :
1. ✅ Consulter la documentation Moodle de la version cible
2. ✅ Vérifier avec `$DB->get_columns('table_name')`
3. ✅ Tester sur un environnement de développement

### Changements Moodle 4.0+ à Retenir

| Ancienne Méthode (3.x) | Nouvelle Méthode (4.x) |
|------------------------|------------------------|
| `question.category` | `question_bank_entries.questioncategoryid` |
| `question.hidden = 0` | `question_versions.status != 'hidden'` |
| Simple `COUNT(*)` | Jointures avec `question_bank_entries` + `question_versions` |

---

## 📚 Références

- [Moodle 4.0 Release Notes](https://docs.moodle.org/dev/Moodle_4.0_release_notes)
- [Question Bank Architecture Changes](https://moodledev.io/docs/apis/subsystems/questionbank)
- [Database Schema Documentation](https://docs.moodle.org/dev/Database_Schema)

---

## ✅ Prochaines Étapes

1. ⏳ Tester les corrections avec `debug_categories.php`
2. ⏳ Vérifier l'interface `categories.php`
3. ⏳ Vérifier les autres pages (dashboard, broken_links, etc.)
4. ⏳ Incrémenter la version à **v1.2.2** si tout fonctionne
5. ⏳ Mettre à jour le CHANGELOG

---

**Mainteneur** : Plugin local_question_diagnostic  
**Contact** : Voir README.md
