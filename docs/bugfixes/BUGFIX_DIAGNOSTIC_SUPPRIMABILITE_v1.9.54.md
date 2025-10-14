# 🔍 Diagnostic de Supprimabilité des Doublons - v1.9.54

**Date** : 14 octobre 2025  
**Version** : 1.9.54  
**Priorité** : Haute  
**Type** : Amélioration + Diagnostic

---

## 🎯 Problème Identifié

Sur la page `question_group_detail.php`, un utilisateur a observé que **36 questions identiques inutilisées** n'étaient pas toutes marquées comme supprimables, alors que logiquement elles devraient l'être.

### Exemple concret

**Groupe "Capteur photo"** :
- 36 instances dupliquées (IDs 56549-56584)
- **Toutes inutilisées** : 0 quiz, 0 utilisations
- **Toutes en doublon** : Même nom + même type
- **Résultat attendu** : 36/36 supprimables
- **Résultat observé** : Seulement quelques-unes avec checkbox visible

---

## 🔍 Analyse du Code de Supprimabilité

La fonction `question_analyzer::can_delete_questions_batch()` vérifie 3 conditions :

```php
// ❌ Bloquer si utilisée dans au moins 1 quiz
if ($quiz_count > 0) {
    return false; // "Question utilisée dans X quiz"
}

// ❌ Bloquer si cachée (Moodle 4.5: question_versions.status = 'hidden')
if (isset($hidden_map[$qid]) && $hidden_map[$qid] === true) {
    return false; // "Question cachée (protégée)"
}

// ❌ Bloquer si UNIQUE (pas de doublon)
if ($duplicate_count == 0) {
    return false; // "Question unique (pas de doublon)"
}

// ✅ Si on arrive ici → SUPPRIMABLE
return true;
```

### Hypothèses

1. **Hypothèse 1** : Questions marquées `hidden` dans `question_versions.status`
2. **Hypothèse 2** : Erreur dans la requête de vérification du statut caché
3. **Hypothèse 3** : Bug dans le comptage des doublons

---

## ✅ Solution Implémentée

### 1. Ajout d'une section de diagnostic dans l'en-tête

```php
// 🔍 Analyse de supprimabilité pour diagnostic
$deletability_map = question_analyzer::can_delete_questions_batch($group_question_ids);
$deletable_count = 0;
$protected_reasons = [];

foreach ($all_questions as $q) {
    if (isset($deletability_map[$q->id])) {
        $check = $deletability_map[$q->id];
        if ($check->can_delete) {
            $deletable_count++;
        } else {
            // Compter les raisons de protection
            if (!isset($protected_reasons[$check->reason])) {
                $protected_reasons[$check->reason] = 0;
            }
            $protected_reasons[$check->reason]++;
        }
    }
}
```

**Affichage dans le résumé :**
```
🔍 Instances supprimables : X / 36

⚠️ Raisons de protection :
  • Question cachée (protégée) : 30 question(s)
  • Question unique (pas de doublon) : 5 question(s)
```

### 2. Amélioration du tooltip sur l'icône 🔒

Avant :
```
🔒 PROTÉGÉE : Question cachée (protégée)
```

Après :
```
🔒 PROTÉGÉE : Question cachée (protégée)

Détails:
is_hidden: true
debug_name: 'Capteur photo'
debug_type: 'multichoice'
```

---

## 🧪 Tests à Effectuer

### Test 1 : Vérifier le statut caché

Connectez-vous à votre base Moodle et exécutez :

```sql
SELECT 
    q.id,
    q.name,
    q.qtype,
    qv.status,
    qv.version
FROM mdl_question q
JOIN mdl_question_versions qv ON qv.questionid = q.id
WHERE q.name = 'Capteur photo' 
  AND q.qtype = 'multichoice'
ORDER BY q.id;
```

**Résultat attendu :**
- Si `qv.status = 'hidden'` → Questions protégées (normal)
- Si `qv.status = 'ready'` → Questions devraient être supprimables

### Test 2 : Vérifier les doublons

```sql
SELECT 
    q.name,
    q.qtype,
    COUNT(*) as duplicate_count
FROM mdl_question q
WHERE q.name = 'Capteur photo' 
  AND q.qtype = 'multichoice'
GROUP BY q.name, q.qtype;
```

**Résultat attendu :** `duplicate_count = 36`

### Test 3 : Vérifier l'utilisation

```sql
SELECT 
    q.id,
    COUNT(qas.id) as usage_count
FROM mdl_question q
LEFT JOIN mdl_quiz_slots qs ON qs.questionid = q.id
LEFT JOIN mdl_question_attempts qa ON qa.questionid = q.id
LEFT JOIN mdl_question_attempt_steps qas ON qas.questionattemptid = qa.id
WHERE q.name = 'Capteur photo' 
  AND q.qtype = 'multichoice'
GROUP BY q.id;
```

**Résultat attendu :** Tous `usage_count = 0`

---

## 🎯 Actions Recommandées

### Si les questions sont `status = 'hidden'` :

**Option A - Rendre visibles puis supprimer :**
```sql
-- ⚠️ À exécuter avec PRÉCAUTION
UPDATE mdl_question_versions
SET status = 'ready'
WHERE questionid IN (56549, 56550, 56551, ..., 56584);
```

Puis recharger la page : les questions devraient devenir supprimables.

**Option B - Modifier la logique de protection :**

Si vous considérez que les questions cachées ET dupliquées DOIVENT être supprimables, modifier `classes/question_analyzer.php` ligne 1394 :

```php
// AVANT
if (isset($hidden_map[$qid]) && $hidden_map[$qid] === true) {
    $results[$qid]->reason = 'Question cachée (protégée)';
    continue;
}

// APRÈS (moins strict)
if (isset($hidden_map[$qid]) && $hidden_map[$qid] === true) {
    // ⚠️ NOUVELLE LOGIQUE : Les questions cachées MAIS dupliquées peuvent être supprimées
    // Vérifier d'abord si c'est un doublon
    $all_with_same_signature = $DB->get_records('question', [
        'name' => $q->name,
        'qtype' => $q->qtype
    ]);
    
    $duplicate_count = count($all_with_same_signature) - 1;
    
    if ($duplicate_count == 0) {
        // Question unique ET cachée → PROTÉGER
        $results[$qid]->reason = 'Question cachée unique (protégée)';
        continue;
    }
    // Sinon, continuer la vérification (doublon caché peut être supprimé)
}
```

---

## 📊 Résultat Attendu Après Correctif

Sur la page de détail du groupe "Capteur photo" :

```
📋 Résumé du groupe
Intitulé de la question : Capteur photo
Type : multichoice
Nombre d'instances dupliquées : 36 question(s)
Instances utilisées : 0
Instances inutilisées : 36

🔍 Instances supprimables : 36 / 36  ✅

💡 Recommandation : Ce groupe contient 36 instance(s) inutilisée(s) qui 
pourrai(en)t être supprimée(s) pour nettoyer la base.
```

Et dans le tableau, **toutes les lignes** devraient avoir la checkbox de sélection visible.

---

## 📝 Fichiers Modifiés

1. ✏️ **`question_group_detail.php`** :
   - Ajout section diagnostic de supprimabilité (lignes 126-145)
   - Affichage des raisons de protection (lignes 160-169)
   - Amélioration tooltip icône 🔒 (lignes 309-323)
   - Optimisation : calcul unique de `$deletability_map`

2. 📄 **`docs/bugfixes/BUGFIX_DIAGNOSTIC_SUPPRIMABILITE_v1.9.54.md`** (ce fichier)

---

## 🔗 Liens Utiles

- [Documentation Moodle 4.5 - Question Bank](https://docs.moodle.org/405/en/Question_bank)
- [Moodle 4.0+ Question Versioning](https://docs.moodle.org/dev/Question_versioning)
- [Hidden Questions in Moodle](https://docs.moodle.org/en/Question_bank#Hidden_questions)

---

## ✅ Checklist Validation

- [x] Section diagnostic ajoutée dans l'en-tête
- [x] Raisons de protection affichées
- [x] Tooltip détaillé sur icône 🔒
- [x] Optimisation performance (1 seul appel batch)
- [ ] Tests SQL exécutés
- [ ] Page rechargée et diagnostic vérifié
- [ ] Questions rendues supprimables si nécessaire

---

**Auteur** : AI Assistant  
**Reviewer** : À valider par l'administrateur Moodle

