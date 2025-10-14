# 🛡️ Protections contre la Suppression de Questions - v1.9.61

**Date** : 14 octobre 2025  
**Version** : 1.9.61  
**Fonction** : `question_analyzer::can_delete_questions_batch()`

---

## 🎯 Objectif

Déterminer si une question peut être supprimée en toute sécurité.

---

## ✅ Protections ACTIVES

### Protection 1 : Question utilisée dans des quiz

**Condition** :
```php
if ($quiz_count > 0) {
    return false; // "Question utilisée dans X quiz"
}
```

**Raison** :
- **CRITIQUE** : Supprimer une question utilisée casserait les quiz et les tentatives
- **Impact** : Perte de données historiques, notes incorrectes
- **Statut** : ✅ **MAINTENUE** (essentielle)

**Exemple** :
```
Question ID 12345 utilisée dans 3 quiz
→ ❌ SUPPRESSION INTERDITE
```

---

### Protection 2 : Question unique (pas de doublon)

**Condition** :
```php
$all_with_same_signature = $DB->get_records('question', [
    'name' => $q->name,
    'qtype' => $q->qtype
]);

$duplicate_count = count($all_with_same_signature) - 1;

if ($duplicate_count == 0) {
    return false; // "Question unique (pas de doublon)"
}
```

**Raison** :
- **IMPORTANT** : Une question unique ne peut pas être supprimée
- **Impact** : Perte définitive de la question (pas de copie de secours)
- **Statut** : ✅ **MAINTENUE** (importante)

**Exemple** :
```
Question "Calcul intégral" - Aucun doublon trouvé
→ ❌ SUPPRESSION INTERDITE
```

---

## ❌ Protection RETIRÉE (v1.9.61)

### ~~Protection 3 : Question cachée~~ 🗑️

**Ancienne condition** :
```php
// 🗑️ SUPPRIMÉ v1.9.61
// if (isset($hidden_map[$qid]) && $hidden_map[$qid] === true) {
//     return false; // "Question cachée (protégée)"
// }
```

**Raison du retrait** :
- **Demande utilisateur** : "Je souhaite que le verrou qui permet de protéger les questions cachées de la suppression saute"
- **Cas d'usage** : Questions cachées ET en doublon ET inutilisées → Peuvent être supprimées
- **Impact** : Les questions cachées peuvent maintenant être nettoyées si elles sont des doublons

**Nouveau comportement** :
```
Question cachée (hidden) + doublon + non utilisée
→ ✅ SUPPRESSION AUTORISÉE
```

---

## 🔄 Nouvelles Règles de Suppression

### Cas 1 : Question visible, utilisée

```
👁️ Visible | 📊 Utilisée dans 2 quiz | 🔀 3 doublons
→ ❌ INTERDITE (Protection 1: utilisée)
```

### Cas 2 : Question cachée, utilisée

```
🔒 Cachée | 📊 Utilisée dans 1 quiz | 🔀 5 doublons
→ ❌ INTERDITE (Protection 1: utilisée)
```

### Cas 3 : Question visible, inutilisée, unique

```
👁️ Visible | ⚠️ Inutilisée | 📌 Unique (0 doublon)
→ ❌ INTERDITE (Protection 2: unique)
```

### Cas 4 : Question cachée, inutilisée, unique

```
🔒 Cachée | ⚠️ Inutilisée | 📌 Unique (0 doublon)
→ ❌ INTERDITE (Protection 2: unique)
```

### Cas 5 : Question visible, inutilisée, doublon ✅

```
👁️ Visible | ⚠️ Inutilisée | 🔀 10 doublons
→ ✅ SUPPRESSION AUTORISÉE
```

### Cas 6 : Question cachée, inutilisée, doublon ✅ **NOUVEAU**

```
🔒 Cachée | ⚠️ Inutilisée | 🔀 10 doublons
→ ✅ SUPPRESSION AUTORISÉE (depuis v1.9.61)
```

---

## 📊 Matrice de Décision

| Utilisée | Unique | Cachée | Supprimable | Protection active |
|----------|--------|--------|-------------|-------------------|
| ✅ Oui | - | - | ❌ NON | Protection 1 |
| ❌ Non | ✅ Oui | - | ❌ NON | Protection 2 |
| ❌ Non | ❌ Non | ✅ Oui | ✅ **OUI** | Aucune (v1.9.61+) |
| ❌ Non | ❌ Non | ❌ Non | ✅ OUI | Aucune |

---

## 🚀 Optimisation Performance

### Avant v1.9.61

```
3 requêtes SQL par batch :
1. Charger les questions
2. Charger les usages
3. Charger les statuts cachés ← SUPPRIMÉ
```

### Après v1.9.61

```
2 requêtes SQL par batch :
1. Charger les questions
2. Charger les usages
```

**Gain** : -33% de requêtes SQL = Meilleure performance ! ⚡

---

## 🎯 Impact Utilisateur

### Avant v1.9.61

Sur un groupe de 36 doublons cachés inutilisés :
```
🔍 Instances supprimables : 0 / 36
⚠️ Raisons de protection :
  • Question cachée (protégée) : 36 question(s)
```

### Après v1.9.61

Sur le même groupe :
```
🔍 Instances supprimables : 36 / 36 ✅
💡 Recommandation : Ce groupe contient 36 instance(s) inutilisée(s) 
qui pourrai(en)t être supprimée(s) pour nettoyer la base.
```

---

## ⚠️ Avertissement de Sécurité

### Ce qui est toujours protégé ✅

1. ✅ **Questions utilisées dans des quiz**
   - Protection absolue
   - Préserve l'intégrité des tentatives
   - Message clair : "Question utilisée dans X quiz"

2. ✅ **Questions uniques**
   - Protection contre perte de données
   - Pas de copie de secours
   - Message clair : "Question unique (pas de doublon)"

### Ce qui n'est plus protégé ⚠️

1. ⚠️ **Questions cachées**
   - PEUVENT être supprimées si doublons inutilisés
   - L'utilisateur doit faire attention
   - Recommandation : Utiliser `unhide_questions.php` pour rendre visibles d'abord

---

## 📝 Recommandation Workflow

### Workflow sécurisé pour nettoyer des questions cachées

#### Étape 1 : Rendre visibles
```
/local/question_diagnostic/unhide_questions.php
→ Rendre toutes les questions cachées visibles
```

#### Étape 2 : Vérifier les doublons
```
/local/question_diagnostic/questions_cleanup.php?loadstats=1
→ Voir quelles questions sont maintenant détectées comme doublons
```

#### Étape 3 : Supprimer les doublons
```
/local/question_diagnostic/question_group_detail.php?name=...
→ Supprimer les doublons inutilisés
```

### Workflow rapide (nouveau avec v1.9.61)

#### Option directe : Supprimer directement les doublons cachés
```
/local/question_diagnostic/question_group_detail.php?name=...
→ Les doublons cachés sont maintenant supprimables
→ Checkbox visible sur toutes les questions cachées
```

---

## 🔧 Code Modifié

### Fichier : `classes/question_analyzer.php`

**Lignes supprimées** : 1352-1370 (ancien code de protection)

**Avant** :
```php
// ÉTAPE 2.5 : Vérifier le statut caché
$hidden_map = [];
// ... requête SQL ...

// Vérification 2 : Question cachée ?
if (isset($hidden_map[$qid]) && $hidden_map[$qid] === true) {
    $results[$qid]->reason = 'Question cachée (protégée)';
    continue;
}
```

**Après** :
```php
// 🗑️ REMOVED v1.9.61 : Protection "Question cachée" RETIRÉE
// L'utilisateur peut maintenant supprimer les questions cachées si doublons inutilisés
```

---

## ✅ Tests à Effectuer

### Test 1 : Question cachée, inutilisée, doublon

**Setup** :
- Créer 2 questions identiques (même nom + type)
- Cacher la première (status='hidden' dans question_versions)

**Résultat attendu** :
- ✅ Checkbox de suppression visible
- ✅ Raison : "Doublon inutilisé"
- ✅ Suppression possible

### Test 2 : Question cachée, utilisée, doublon

**Setup** :
- Question cachée
- Utilisée dans 1 quiz
- A des doublons

**Résultat attendu** :
- ❌ Checkbox de suppression invisible
- ❌ Raison : "Question utilisée dans 1 quiz"
- ❌ Suppression interdite (Protection 1 active)

### Test 3 : Question cachée, inutilisée, unique

**Setup** :
- Question cachée
- Non utilisée
- Unique (pas de doublon)

**Résultat attendu** :
- ❌ Checkbox de suppression invisible
- ❌ Raison : "Question unique (pas de doublon)"
- ❌ Suppression interdite (Protection 2 active)

---

## 📊 Résumé

### Protections maintenues ✅
1. ✅ Question utilisée → TOUJOURS protégée
2. ✅ Question unique → TOUJOURS protégée

### Protection retirée ❌
3. ❌ Question cachée → PLUS protégée (depuis v1.9.61)

### Optimisation bonus ⚡
- 1 requête SQL en moins par batch
- Meilleure performance

---

**Auteur** : AI Assistant  
**Version** : 1.9.61  
**Demande** : Retrait protection questions cachées pour permettre suppression

