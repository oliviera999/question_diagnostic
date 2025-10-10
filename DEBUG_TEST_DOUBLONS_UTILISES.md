# 🔍 DEBUG : Test Doublons Utilisés Affiche des Groupes Inutilisés

## Problème Reporté

L'utilisateur a cliqué sur "Test Doublons Utilisés" et a obtenu :

```
🎯 Groupe de Doublons Utilisés Trouvé !

Versions utilisées : 0
Total quiz : 0
Total utilisations : 0
```

**C'est IMPOSSIBLE** - Le bouton doit trouver UNIQUEMENT des groupes avec AU MOINS 1 version utilisée.

---

## Analyse du Code

### Logique Attendue (lignes 268-349)

```php
// Pour chaque groupe de 5 sélectionnés aléatoirement
foreach ($duplicate_groups as $group) {
    // Vérifier si AU MOINS 1 version est utilisée
    $has_used = false;
    foreach ($group_ids as $qid) {
        if (is_used($qid)) {
            $has_used = true;
            break;
        }
    }
    
    if ($has_used) {
        $random_question = $sample;  // Garder ce groupe
        $found = true;
        break;
    }
}

// Si aucun groupe utilisé trouvé
if (!$found || !$random_question) {
    echo "Aucun groupe trouvé";
    exit;
}

// Sinon afficher le groupe trouvé
echo "Groupe Utilisé Trouvé !";
```

### Hypothèses

**Hypothèse 1** : `$has_used` est mis à TRUE alors qu'il ne devrait pas
- Cause possible : `$usage_map[$qid]['is_used']` retourne TRUE pour une question inutilisée
- Debug nécessaire : Vérifier le retour de `get_questions_usage_by_ids()`

**Hypothèse 2** : `$found` ou `$random_question` ne sont pas correctement définis
- Cause possible : Logique conditionnelle incorrecte
- Debug nécessaire : Ajouter des logs avant ligne 334

**Hypothèse 3** : Le code n'entre jamais dans le `if (!$found)` ligne 334
- Cause possible : Une des variables a une valeur inattendue
- Debug nécessaire : Log des valeurs de $found et $random_question

---

## Solution Proposée

### 1. Ajouter un Log de Debug Détaillé

```php
// Après ligne 332, AVANT le if (!$found)
debugging('TEST DOUBLONS - found=' . ($found ? 'true' : 'false') . 
          ', random_question=' . ($random_question ? $random_question->id : 'null'), 
          DEBUG_DEVELOPER);
```

### 2. Forcer la Vérification Plus Stricte

```php
// Ligne 334 - Rendre la condition plus explicite
if ($found === false || $random_question === null) {
    echo "Aucun groupe trouvé";
    exit;
}
```

### 3. Vérifier Tous les Groupes (Pas Seulement 5)

Le code actuel ne teste que 5 groupes aléatoires. Si on a beaucoup de doublons inutilisés, on peut facilement tomber sur 5 groupes inutilisés.

**Solution** : Augmenter le nombre de tentatives ou tester TOUS les groupes.

```php
// Au lieu de prendre seulement 5
$duplicate_groups = array_slice($all_duplicate_groups, 0, 20); // Tester 20 groupes
```

---

## Action Immédiate

Je vais implémenter une correction qui :
1. ✅ Augmente le nombre de groupes testés (5 → 20)
2. ✅ Ajoute des logs de debug détaillés
3. ✅ Rend la vérification plus stricte
4. ✅ Ajoute un compteur de groupes testés dans le message

---

## Test Après Correction

**Résultats attendus** :

**Cas A - Groupes utilisés trouvés** :
```
🎯 Groupe de Doublons Utilisés Trouvé !
(Testé 3 groupes avant de trouver celui-ci)

Versions utilisées : 2 ou plus
Total quiz : > 0
```

**Cas B - Aucun groupe utilisé** :
```
⚠️ Aucun groupe de doublons utilisés trouvé

Après avoir testé 20 groupes de doublons, aucun ne contient 
de version utilisée. Tous vos doublons semblent inutilisés.
```

---

**Document créé le** : 10 octobre 2025  
**Version à corriger** : v1.9.14 → v1.9.15

