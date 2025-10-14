# 📋 Visibilité des Catégories de Questions dans Moodle 4.5

**Date** : 14 octobre 2025  
**Version Moodle** : 4.5+  
**Sujet** : Les catégories peuvent-elles être cachées ?

---

## ❓ Question posée

> "Est-ce que des catégories peuvent être cachées également ?"

---

## ✅ Réponse courte

**NON**, dans Moodle 4.5, les **catégories de questions ne peuvent PAS être cachées** nativement.

Seules les **questions** ont un statut de visibilité (via `question_versions.status`).

---

## 🔍 Analyse de la Structure BDD

### Table `question_categories`

**Colonnes principales (Moodle 4.5) :**

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT | ID unique de la catégorie |
| `name` | VARCHAR | Nom de la catégorie |
| `contextid` | INT | Contexte Moodle (course, system, etc.) |
| `info` | TEXT | Description de la catégorie |
| `infoformat` | INT | Format du texte (HTML, plain, etc.) |
| `stamp` | VARCHAR | Tampon unique |
| `parent` | INT | ID de la catégorie parente (hiérarchie) |
| `sortorder` | INT | Ordre d'affichage |
| `idnumber` | VARCHAR | Numéro ID externe |

### ❌ Champs absents

- **PAS de champ `hidden`**
- **PAS de champ `status`**
- **PAS de champ `visible`**

---

## 📊 Comparaison : Questions vs Catégories

### Questions (table `question` + `question_versions`)

```
✅ Peuvent être cachées
   • Via question_versions.status = 'hidden'
   • 3 statuts possibles : 'ready', 'hidden', 'draft'
```

### Catégories (table `question_categories`)

```
❌ NE peuvent PAS être cachées
   • Pas de champ de statut
   • Toutes les catégories sont visibles (selon permissions)
```

---

## 🤔 Pourquoi cette différence ?

### Architecture Moodle

1. **Questions** : Objets individuels réutilisables
   - Peuvent être supprimées mais conservées (soft delete)
   - Versioning depuis Moodle 4.0+
   - Nécessitent un statut pour gérer l'intégrité des quiz

2. **Catégories** : Structures organisationnelles
   - Simple conteneur hiérarchique
   - Pas de versioning
   - Gérées par permissions et contextes

---

## 🔐 Comment restreindre l'accès aux catégories ?

### Solution 1 : Utiliser les contextes

Les catégories dans différents contextes sont automatiquement isolées :

- **Contexte SYSTEM** : Visible par tous les utilisateurs autorisés
- **Contexte COURSE** : Visible uniquement dans le cours
- **Contexte MODULE** : Visible uniquement dans l'activité

### Solution 2 : Permissions (capabilities)

Restreindre les capabilities :
- `moodle/question:viewall`
- `moodle/question:editall`
- `moodle/question:managecategory`

### Solution 3 : Supprimer la catégorie

Si une catégorie ne doit plus être utilisée :
- Déplacer les questions vers une autre catégorie
- Supprimer la catégorie vide

---

## 💡 Alternative : "Pseudo-cacher" une catégorie

### Méthode 1 : Préfixe dans le nom

Renommer la catégorie avec un préfixe :
```
[ARCHIVE] Ancienne catégorie
[HIDDEN] Catégorie temporaire
```

### Méthode 2 : Déplacer dans une catégorie "Archive"

Créer une catégorie parente "📦 Archives" et y déplacer les catégories inutilisées :

```
📦 Archives
  └─ Ancienne catégorie 1
  └─ Ancienne catégorie 2
  └─ Ancienne catégorie 3
```

### Méthode 3 : Cacher toutes les questions de la catégorie

Si vous voulez "cacher" une catégorie, cachez toutes ses questions :

```sql
-- ⚠️ À exécuter avec PRÉCAUTION
UPDATE mdl_question_versions qv
INNER JOIN mdl_question_bank_entries qbe ON qbe.id = qv.questionbankentryid
SET qv.status = 'hidden'
WHERE qbe.questioncategoryid = [ID_CATEGORIE];
```

---

## 🔧 Outil de Diagnostic Créé

**Nouveau fichier** : `check_categories_structure.php`

**Fonction** :
- Affiche la structure complète de `question_categories`
- Vérifie si un champ `hidden` existe
- Compte les catégories cachées (si le champ existe)

**Accès** :
```
/local/question_diagnostic/check_categories_structure.php
```

---

## 📝 Conclusion

### Questions ✅
- Peuvent être cachées via `question_versions.status = 'hidden'`
- Notre plugin gère ça avec la page `unhide_questions.php`

### Catégories ❌
- **NE peuvent PAS être cachées** nativement dans Moodle 4.5
- Pas de champ de statut dans la table
- Visibilité contrôlée uniquement par permissions et contextes

---

## 🎯 Recommandations

Si vous souhaitez "cacher" des catégories :

1. ✅ **Utiliser les contextes** : Catégories dans contextes différents
2. ✅ **Préfixer les noms** : `[ARCHIVE]`, `[OLD]`, etc.
3. ✅ **Déplacer dans "Archives"** : Catégorie parente organisationnelle
4. ✅ **Cacher les questions** : Rendre invisibles toutes les questions de la catégorie
5. ❌ **Ne PAS modifier la structure BDD** : Risque de casser Moodle

---

**Conclusion** : Les catégories de questions **ne peuvent pas être cachées** dans Moodle 4.5. Seules les questions individuelles peuvent l'être. 📋

---

**Auteur** : AI Assistant  
**Documentation** : [Moodle Question Categories](https://docs.moodle.org/en/Question_bank#Question_categories)

