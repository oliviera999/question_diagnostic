# 🔧 BUGFIX v1.9.43 : Correction Affichage Test Doublons Utilisés

**Date** : 13 octobre 2025  
**Version** : 1.9.43  
**Sévérité** : 🔴 CRITIQUE  
**Impact** : Affichage trompeur + logique de verrouillage complètement cassée

---

## 🐛 Problèmes Identifiés

### 1. **Affichage incorrect du nombre de versions utilisées** (`questions_cleanup.php`, ligne 413)

**Symptôme** :
```
Nombre de versions totales : 33 (1 utilisée dans quiz + 32 doublon(s))
```

Mais en réalité, il y avait **11 versions utilisées** et **22 inutilisées** !

**Cause** : Affichage statique hardcodé qui supposait qu'une seule version était utilisée.

---

### 2. 🚨 **CRITIQUE : Logique de verrouillage cassée** (`classes/question_analyzer.php`, lignes 1344-1358)

**Symptôme** :
- Questions inutilisées affichées comme 🔒 PROTÉGÉES
- Impossibilité de supprimer des doublons pourtant inutilisés
- Checkboxes désactivées à tort

**Cause** : Le code itérait sur un **array associatif** comme si c'était une liste séquentielle :

```php
// ❌ CODE INCORRECT
if (isset($usage_map[$qid])) {
    $usage = $usage_map[$qid]; // ['quiz_count' => 0, 'quiz_list' => [], ...]
    if (!empty($usage)) {
        $quiz_count = 0;
        foreach ($usage as $u) {  // ← BUG : itère sur les CLÉS !
            $quiz_count++;         // Compte quiz_count, quiz_list, attempt_count, is_used
        }
        // Résultat : quiz_count = 4 même si aucun quiz !
        
        if ($quiz_count > 0) {
            // Question VERROUILLÉE à tort !
        }
    }
}
```

**Impact** :
- `$quiz_count` valait toujours **4** (nombre de clés de l'array)
- Toutes les questions avec doublons étaient verrouillées
- Les vraies questions inutilisées ne pouvaient pas être supprimées

---

### 3. **Incohérence entre en-tête et résumé**

L'en-tête disait "1 utilisée" mais le résumé détaillé disait "11 utilisées".

---

## ✅ Corrections Appliquées

### 1. Correction de `can_delete_questions_batch()` (Lignes 1344-1355)

**Fichier** : `classes/question_analyzer.php`

```php
// ✅ CODE CORRIGÉ
// 🔧 v1.9.43 FIX CRITIQUE : Utiliser la clé 'quiz_count' directement
if (isset($usage_map[$qid]) && is_array($usage_map[$qid])) {
    $quiz_count = isset($usage_map[$qid]['quiz_count']) ? 
                  $usage_map[$qid]['quiz_count'] : 0;
    
    if ($quiz_count > 0) {
        $results[$qid]->reason = 'Question utilisée dans ' . $quiz_count . ' quiz';
        $results[$qid]->details['quiz_count'] = $quiz_count;
        continue;
    }
}
```

**Résultat** :
- `$quiz_count` contient maintenant le **vrai** nombre de quiz (0, 1, 2, etc.)
- Les questions inutilisées ne sont plus verrouillées à tort
- Les checkboxes s'affichent correctement

---

### 2. Correction de l'affichage de l'en-tête (Lignes 405-430)

**Fichier** : `questions_cleanup.php`

**Ajout d'un calcul dynamique AVANT l'affichage** :

```php
// 🔧 v1.9.43 FIX : Calculer le VRAI nombre de versions utilisées
$group_question_ids_preview = array_map(function($q) { return $q->id; }, $all_questions);
$group_usage_map_preview = question_analyzer::get_questions_usage_by_ids($group_question_ids_preview);

$used_count_preview = 0;
foreach ($all_questions as $q) {
    $quiz_count = 0;
    if (isset($group_usage_map_preview[$q->id]) && is_array($group_usage_map_preview[$q->id])) {
        $quiz_count = isset($group_usage_map_preview[$q->id]['quiz_count']) ? 
                      $group_usage_map_preview[$q->id]['quiz_count'] : 0;
    }
    if ($quiz_count > 0) {
        $used_count_preview++;
    }
}

$unused_count_preview = count($all_questions) - $used_count_preview;
```

**Nouvel affichage** :
```php
echo '... ' . count($all_questions) . ' (' . $used_count_preview . 
     ' utilisée(s) dans quiz + ' . $unused_count_preview . 
     ' doublon(s) inutilisé(s))';
```

---

### 3. Optimisation : Réutilisation des données

**Fichier** : `questions_cleanup.php` (Lignes 472-479)

Au lieu de charger deux fois les mêmes données :

```php
// 🔧 v1.9.43 OPTIMISATION : Réutiliser les données déjà chargées
$group_question_ids = $group_question_ids_preview;
$group_usage_map = $group_usage_map_preview;
```

**Gain** : Une seule requête SQL au lieu de deux pour charger l'usage des questions.

---

## 📊 Résultat Final

### Avant (v1.9.29)
```
🎯 Groupe de Doublons Utilisés Trouvé !

Nombre de versions totales : 33 (1 utilisée dans quiz + 32 doublon(s))

Tableau :
- 11 questions avec ✅ Utilisée mais affichées comme 🔒 PROTÉGÉES
- 22 questions avec ⚠️ Inutilisée mais AUSSI 🔒 PROTÉGÉES

Résumé :
Versions utilisées : 11
Versions inutilisées : 22

❌ INCOHÉRENT (1 vs 11)
❌ Aucune question supprimable (toutes verrouillées)
```

### Après (v1.9.43)
```
🎯 Groupe de Doublons Utilisés Trouvé !

Nombre de versions totales : 33 (11 utilisée(s) dans quiz + 22 doublon(s) inutilisé(s))

Tableau :
- 11 questions avec ✅ Utilisée et 🔒 PROTÉGÉES (correct)
- 22 questions avec ⚠️ Inutilisée et ☑️ CHECKBOX (correct)

Résumé :
Versions utilisées : 11
Versions inutilisées (supprimables) : 22

✅ COHÉRENT (11 = 11)
✅ 22 questions supprimables en masse
```

---

## 🧪 Tests Effectués

### Scénario 1 : Groupe avec plusieurs versions utilisées
- ✅ L'en-tête affiche le bon nombre (11)
- ✅ Le résumé affiche le bon nombre (11)
- ✅ Les 11 versions utilisées ont l'icône 🔒
- ✅ Les 22 versions inutilisées ont une checkbox ☑️

### Scénario 2 : Suppression en masse
- ✅ Les checkboxes apparaissent uniquement pour les questions inutilisées
- ✅ Le bouton "Supprimer la sélection" fonctionne
- ✅ Les questions utilisées restent protégées

---

## 📝 Fichiers Modifiés

1. **`classes/question_analyzer.php`**
   - Ligne 1344-1355 : Correction de la logique de `can_delete_questions_batch()`
   - Commentaires ajoutés pour expliquer le bug

2. **`questions_cleanup.php`**
   - Lignes 405-430 : Calcul dynamique du nombre de versions utilisées
   - Lignes 472-479 : Optimisation (réutilisation des données)

3. **`docs/bugfixes/BUGFIX_DUPLICATE_TEST_DISPLAY_v1.9.43.md`** (ce fichier)
   - Documentation complète du bugfix

---

## 🔍 Leçon Apprise

### ⚠️ Danger des foreach sur arrays associatifs

```php
// ❌ DANGEREUX
$array = ['key1' => 'value1', 'key2' => 'value2'];
$count = 0;
foreach ($array as $item) {
    $count++; // Compte les valeurs (correct)
}

// Mais si on oublie que c'est associatif...
$usage = ['quiz_count' => 0, 'quiz_list' => [], ...]; // 4 clés
if (!empty($usage)) {  // TRUE car array non vide
    foreach ($usage as $u) {
        $count++; // Compte les CLÉS, pas les quiz !
    }
    // $count = 4 alors qu'il n'y a AUCUN quiz !
}
```

### ✅ Solution : Accès direct aux clés

```php
$quiz_count = isset($usage['quiz_count']) ? $usage['quiz_count'] : 0;
// Simple, clair, correct
```

---

## 🎯 Impact

- **Utilisabilité** : Les utilisateurs peuvent maintenant supprimer les doublons inutilisés
- **Confiance** : L'affichage est cohérent et transparent
- **Performance** : Optimisation (une seule requête au lieu de deux)
- **Sécurité** : Les questions utilisées restent protégées (pas de régression)

---

**Statut** : ✅ CORRIGÉ ET TESTÉ  
**Version cible** : Moodle 4.5+  
**Compatibilité** : Rétrocompatible avec v1.9.x

