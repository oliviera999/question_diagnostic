# 🔧 FIX v1.9.26 : Charger Doublons Utilisés - Application de la Logique Robuste

**Date** : 10 octobre 2025  
**Version** : v1.9.26  
**Fichier modifié** : `classes/question_analyzer.php`  
**Fonction** : `get_used_duplicates_questions()`

---

## 📋 Contexte

Le plugin possède deux fonctionnalités pour travailler avec les doublons utilisés :

1. **🎲 Test Doublons Utilisés** (bouton) - Affiche un groupe aléatoire de doublons utilisés
2. **📋 Charger Doublons Utilisés** (bouton) - Charge tous les groupes de doublons utilisés dans le tableau

La fonctionnalité "Test Doublons Utilisés" a été **corrigée dans les versions précédentes** (v1.9.16+) pour utiliser une logique robuste qui :
- Détecte d'abord les questions **réellement utilisées dans les quiz** (via `quiz_slots`)
- Puis cherche les doublons de ces questions
- **Ne se base plus sur `!empty()` qui donnait des faux positifs**

Cependant, la fonctionnalité "Charger Doublons Utilisés" **utilisait encore l'ancienne logique problématique**.

---

## ⚠️ Problème Identifié

### Ancienne logique (v1.9.4 - v1.9.25)

```php
// ❌ PROBLÉMATIQUE : Vérification avec !empty() qui donne des faux positifs
$usage_map = self::get_questions_usage_by_ids($group_ids);

$has_used = false;
foreach ($group_ids as $qid) {
    if (isset($usage_map[$qid]) && !empty($usage_map[$qid])) {  // ⚠️ !empty() retourne TRUE même pour objets vides
        $has_used = true;
        break;
    }
}
```

**Symptômes** :
- Le bouton "📋 Charger Doublons Utilisés" pouvait afficher des groupes où **toutes les versions sont inutilisées**
- Incohérence avec "🎲 Test Doublons Utilisés" qui lui fonctionnait correctement après la correction v1.9.16

---

## ✅ Solution Appliquée

### Nouvelle logique (v1.9.26)

Appliquer **exactement la même logique** que "Test Doublons Utilisés" :

```php
// ✅ CORRECTE : Détection directe depuis quiz_slots
// Étape 1 : Récupérer TOUTES les questions utilisées
$columns = $DB->get_columns('quiz_slots');

if (isset($columns['questionbankentryid'])) {
    // Moodle 4.1+
    $sql_used = "SELECT DISTINCT qv.questionid
                 FROM {quiz_slots} qs
                 INNER JOIN {question_bank_entries} qbe ON qbe.id = qs.questionbankentryid
                 INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id";
} else if (isset($columns['questionid'])) {
    // Moodle 3.x/4.0
    $sql_used = "SELECT DISTINCT qs.questionid FROM {quiz_slots} qs";
} else {
    // Moodle 4.5+ - Nouvelle architecture avec question_references
    $sql_used = "SELECT DISTINCT qv.questionid
                 FROM {quiz_slots} qs
                 INNER JOIN {question_references} qr ON qr.itemid = qs.id 
                     AND qr.component = 'mod_quiz' 
                     AND qr.questionarea = 'slot'
                 INNER JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                 INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id";
}

$used_question_ids = $DB->get_fieldset_sql($sql_used);

// Étape 2 : Pour chaque question utilisée, chercher SES doublons
foreach ($used_question_ids as $qid) {
    $question = $DB->get_record('question', ['id' => $qid]);
    
    // Chercher les doublons (même nom + même type)
    $all_versions = $DB->get_records('question', [
        'name' => $question->name,
        'qtype' => $question->qtype
    ]);
    
    // Si au moins 2 versions → Groupe de doublons utilisés !
    if (count($all_versions) > 1) {
        // Ajouter TOUTES les versions du groupe
        foreach ($all_versions as $q) {
            $result_questions[] = $q;
        }
    }
}
```

---

## 🔧 Modifications Techniques

### Fichier : `classes/question_analyzer.php`

**Fonction** : `get_used_duplicates_questions($limit = 100)`

**Changements** :

1. ✅ **Détection directe des questions utilisées** via `quiz_slots` (au lieu de `get_questions_usage_by_ids()`)
2. ✅ **Support multi-versions Moodle** (3.x, 4.0, 4.1+, 4.5+) avec détection automatique de la structure BDD
3. ✅ **Suppression de la vérification `!empty()`** qui causait des faux positifs
4. ✅ **Ajout de logs de debug** détaillés pour diagnostic
5. ✅ **Évite les doublons** dans le résultat avec `$processed_signatures`
6. ✅ **Respecte la limite** demandée par l'utilisateur

**Nouveaux logs de debug** :
```php
debugging('CHARGER DOUBLONS UTILISÉS v1.9.26 - Questions utilisées détectées: ' . count($used_question_ids), DEBUG_DEVELOPER);
debugging('CHARGER DOUBLONS UTILISÉS v1.9.26 - Résultat: ' . count($result_questions) . ' questions dans ' . $groups_found . ' groupes', DEBUG_DEVELOPER);
```

---

## 📊 Impact Utilisateur

### Avant (v1.9.25)

```
📋 Charger Doublons Utilisés
→ Affiche 50 questions
   ❌ Problème : Certains groupes affichés ont 0 versions utilisées
   ❌ Incohérence avec "Test Doublons Utilisés"
```

### Après (v1.9.26)

```
📋 Charger Doublons Utilisés
→ Affiche 50 questions
   ✅ Garantie : TOUS les groupes affichés ont au moins 1 version utilisée dans un quiz
   ✅ Cohérence parfaite avec "Test Doublons Utilisés"
```

---

## 🧪 Comment Tester

### Test 1 : Vérifier que seuls les doublons utilisés sont chargés

1. Aller sur **Question Diagnostic → Analyser Questions**
2. Cliquer sur **"📋 Charger Doublons Utilisés"**
3. **Résultat attendu** :
   - Message : "X questions en doublon avec au moins 1 version utilisée ont été chargées"
   - Dans le tableau, vérifier les colonnes "📊 Dans Quiz" et "🔢 Utilisations"
   - **AU MOINS 1 version dans chaque groupe DOIT avoir "Dans Quiz" > 0**

### Test 2 : Cohérence avec Test Doublons Utilisés

1. Cliquer sur **"🎲 Test Doublons Utilisés"** plusieurs fois
2. Noter les groupes trouvés
3. Cliquer sur **"📋 Charger Doublons Utilisés"**
4. **Résultat attendu** : Les groupes trouvés par le test aléatoire DOIVENT tous être dans le tableau chargé

### Test 3 : Cas limite - Aucune question utilisée

1. Sur une instance Moodle **sans quiz** ou **sans questions dans les quiz**
2. Cliquer sur **"📋 Charger Doublons Utilisés"**
3. **Résultat attendu** : Message vide ou aucune question affichée (pas de faux positifs)

---

## 🔍 Logs de Debug

Si vous activez le mode debug (`$CFG->debug = DEBUG_DEVELOPER`), vous verrez :

```
CHARGER DOUBLONS UTILISÉS v1.9.26 - Questions utilisées détectées: 1250
CHARGER DOUBLONS UTILISÉS v1.9.26 - Résultat: 85 questions dans 12 groupes de doublons
```

Cela vous permet de vérifier :
- Combien de questions sont utilisées dans votre base
- Combien de groupes de doublons utilisés ont été trouvés
- Combien de questions au total ont été chargées

---

## ✅ Checklist de Déploiement

- [x] Fonction `get_used_duplicates_questions()` mise à jour
- [x] Logs de debug ajoutés
- [x] Support multi-versions Moodle (3.x → 4.5+)
- [x] Version incrémentée (v1.9.26)
- [x] Documentation créée (ce fichier)
- [ ] Cache Moodle purgé après déploiement (à faire par l'admin)
- [ ] Tests effectués sur environnement réel

---

## 🎯 Résumé

| Aspect | Avant v1.9.26 | Après v1.9.26 |
|--------|---------------|---------------|
| **Détection usage** | `!empty($usage_map[$qid])` ❌ | Requête directe `quiz_slots` ✅ |
| **Faux positifs** | Possibles | Impossibles ✅ |
| **Cohérence avec Test** | ❌ Logiques différentes | ✅ Logique identique |
| **Support Moodle** | 4.0+ | 3.x → 4.5+ ✅ |
| **Logs debug** | Aucun | Détaillés ✅ |

---

**Référence** : Cette correction applique la même logique que celle introduite dans `questions_cleanup.php` lignes 242-362 pour "Test Doublons Utilisés" (v1.9.16+).

**Document lié** : `DEBUG_TEST_DOUBLONS_UTILISES.md` (analyse du problème initial)


