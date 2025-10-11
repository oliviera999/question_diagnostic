# 🛡️ Protection des Catégories Critiques

**Date**: 8 octobre 2025  
**Version**: v1.4.1+  
**Priorité**: CRITIQUE

---

## ⚠️ Problème Identifié

Le plugin permettait potentiellement la suppression de **catégories critiques** nécessaires au bon fonctionnement de Moodle, notamment :

1. Catégories "**Default for [Cours]**" créées automatiquement par Moodle
2. Catégories **racine** (parent=0) dans les contextes de cours
3. Catégories avec une **description** (usage intentionnel documenté)

---

## 🎯 Catégories Moodle Spéciales

### 1. Catégories "Default for..." 

**Qu'est-ce que c'est ?**
- Moodle crée **automatiquement** une catégorie par défaut pour chaque cours
- Nom type : "Default for [Nom du Cours]"
- Permet de stocker les questions créées dans ce cours

**Pourquoi protéger ?**
- Suppression = **perte de la structure par défaut** du cours
- Moodle peut tenter de recréer la catégorie → incohérences
- Les enseignants s'attendent à trouver cette catégorie

**Exemple** :
```
📂 Default for Mathematics 101 (ID: 456)
   ├─ Context: Course (ID: 12)
   ├─ Parent: 0 (racine)
   ├─ Questions: 0 (peut être vide temporairement)
   └─ Status: 🛡️ PROTÉGÉE
```

---

### 2. Catégories Racine de Cours (parent=0)

**Qu'est-ce que c'est ?**
- Catégories de niveau supérieur dans chaque cours
- Parent = 0 (pas de parent)
- Souvent utilisées comme conteneurs organisationnels

**Pourquoi protéger ?**
- Structure hiérarchique de la banque de questions
- Peut avoir des sous-catégories même si vide
- Point d'entrée de l'arborescence

**Exemple** :
```
📂 Questions du cours (ID: 123) [parent=0]
   ├─ 📂 Chapitre 1 (15 questions)
   ├─ 📂 Chapitre 2 (22 questions)
   └─ 📂 Examens finaux (8 questions)
```
Même si "Questions du cours" est vide, elle organise les sous-catégories.

---

### 3. Catégories avec Description (champ `info`)

**Qu'est-ce que c'est ?**
- Catégories où l'administrateur/enseignant a ajouté une description
- Champ `info` non vide dans la table `question_categories`

**Pourquoi protéger ?**
- Indique un **usage intentionnel** et documenté
- L'administrateur a pris le temps de décrire son utilité
- Peut être vide temporairement en attendant des questions

**Exemple** :
```
📂 Questions pour TP Python (ID: 789)
   ├─ Info: "Catégorie réservée pour les exercices pratiques Python niveau avancé"
   ├─ Questions: 0 (en préparation)
   └─ Status: 🛡️ PROTÉGÉE
```

---

## 🔒 Protections Implémentées

### Dans `category_manager.php::delete_category()`

```php
// 🛡️ PROTECTION 1 : Catégories "Default for..."
if (stripos($category->name, 'Default for') !== false) {
    return "❌ PROTÉGÉE : Cette catégorie est créée automatiquement par Moodle...";
}

// 🛡️ PROTECTION 2 : Catégories avec description
if (!empty($category->info)) {
    return "❌ PROTÉGÉE : Cette catégorie a une description...";
}

// 🛡️ PROTECTION 3 : Catégories racine de cours
if ($category->parent == 0 && $context->contextlevel == CONTEXT_COURSE) {
    return "❌ PROTÉGÉE : Cette catégorie est à la racine d'un cours...";
}
```

### Dans `category_manager.php::get_global_stats()`

Le comptage des "catégories vides" **exclut maintenant** les catégories protégées :

```sql
WHERE qc.id NOT IN (SELECT ... questions ...)
  AND qc.id NOT IN (SELECT ... enfants ...)
  AND qc.parent != 0                          -- ✅ Exclut racines
  AND (qc.info IS NULL OR qc.info = '')       -- ✅ Exclut avec description
  AND name NOT LIKE '%Default for%'            -- ✅ Exclut "Default for..."
```

### Dans l'Interface `categories.php`

- 🎨 Badge **"🛡️ PROTÉGÉE"** affiché visuellement
- 🚫 Bouton "Supprimer" désactivé/masqué
- ℹ️ Raison de protection affichée au survol
- 📊 Carte dédiée "Catégories Protégées" dans le dashboard

---

## 📊 Impact sur les Statistiques

### Avant Protection (v1.4.0)

```
Catégories vides supprimables : 3465
(Incluait potentiellement des catégories "Default for...")
```

### Après Protection (v1.4.1+)

```
Catégories vraiment supprimables : ~3200-3400 (dépend de votre config)
Catégories protégées affichées séparément : ~50-200
```

Le chiffre est maintenant **plus précis** et **plus sûr**.

---

## 🧪 Script de Vérification

Un nouveau script `check_default_categories.php` permet de :

1. ✅ Lister toutes les catégories "Default for..."
2. ✅ Lister toutes les catégories racine par contexte
3. ✅ Lister toutes les catégories avec description
4. ✅ Afficher un comptage avec/sans protections

**Utilisation** :
```
https://votre-moodle.com/local/question_diagnostic/check_default_categories.php
```

---

## 🎯 Recommandations

### Catégories à NE JAMAIS Supprimer

❌ **Catégories "Default for..."**
- Créées automatiquement par Moodle
- Une par cours
- Essentielles au fonctionnement

❌ **Catégories racine (parent=0) de cours**
- Point d'entrée de l'arborescence
- Même si vides, elles organisent la structure

❌ **Catégories avec description**
- Usage documenté et intentionnel
- Suppression = perte d'information

### Catégories Sûres à Supprimer

✅ **Catégories vides (0 questions, 0 sous-catégories)**
- ET parent != 0 (pas à la racine)
- ET info vide (pas de description)
- ET nom != "Default for..."

✅ **Catégories orphelines** (après vérification manuelle)
- Contexte supprimé
- À traiter via `orphan_entries.php`

---

## 📋 Checklist avant Suppression

Avant de supprimer une catégorie, vérifier :

- [ ] ❌ Le nom ne contient PAS "Default for"
- [ ] ❌ Ce n'est PAS une catégorie racine (parent != 0) 
- [ ] ❌ Le champ `info` est vide
- [ ] ✅ 0 questions
- [ ] ✅ 0 sous-catégories
- [ ] ✅ Contexte valide OU orpheline confirmée

Si tous les critères sont remplis → **Suppression sûre ✅**

---

## 🔧 Bypass des Protections (Avancé)

**En cas de besoin absolu** (très rare), un administrateur peut :

1. Supprimer la description (`UPDATE question_categories SET info = NULL WHERE id = X`)
2. Vérifier manuellement que la catégorie n'est pas utilisée
3. Supprimer via requête SQL directe (NON recommandé)

⚠️ **DANGER** : Bypasser les protections peut casser votre Moodle !

---

## 📖 Ressources

- Documentation Moodle : [Question Bank](https://docs.moodle.org/en/Question_bank)
- Code source : `question/category_class.php` (fonction `can_be_deleted()`)
- Table structure : `MOODLE_4.5_DATABASE_REFERENCE.md`

---

## 🐛 Tests Réalisés

✅ Test avec catégories "Default for..." (protection active)  
✅ Test avec catégories racine de cours (protection active)  
✅ Test avec catégories avec description (protection active)  
✅ Test suppression catégories vraiment vides (OK)  
✅ Test messages d'erreur explicites  

---

**Version** : v1.4.1+  
**Auteur** : Équipe Question Diagnostic  
**License** : GPL v3

