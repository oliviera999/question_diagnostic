# 🎯 Nouvelle Fonctionnalité : Sélection Intelligente des Doublons à Conserver

**Version** : v1.9.46  
**Date** : 13 octobre 2025  
**Type** : Feature Enhancement  
**Priorité** : Moyenne  
**Fichiers modifiés** : `actions/cleanup_duplicate_groups.php`

---

## 📋 Contexte

Lorsqu'un groupe de questions en doublon ne contient **aucune version utilisée** dans des quiz, le plugin devait choisir quelle version conserver pour éviter de tout supprimer.

### ⚠️ Ancien Comportement

Avant cette version, la logique était simple :
- Garder **la version la plus ANCIENNE** (premier timestamp)
- Supprimer toutes les autres versions inutilisées

**Problème** : Cette approche ne tenait pas compte de l'**accessibilité** de la question dans l'architecture Moodle. Une question créée dans un module d'activité spécifique (contexte restreint) pouvait être conservée au lieu d'une version identique créée au niveau du site (contexte global).

---

## 🎯 Nouveau Comportement

### Logique de Sélection Intelligente

La version conservée est désormais choisie selon **deux critères hiérarchiques** :

#### 1️⃣ **Priorité Principale** : Niveau d'Accessibilité (Contexte)

Score d'accessibilité basé sur le `contextlevel` Moodle :

| Contexte | Score | Description | Accessibilité |
|----------|-------|-------------|---------------|
| `CONTEXT_SYSTEM` | 50 | 🌐 Niveau site entier | ⭐⭐⭐⭐⭐ Maximum |
| `CONTEXT_COURSECAT` | 40 | 📂 Catégorie de cours | ⭐⭐⭐⭐ Élevée |
| `CONTEXT_COURSE` | 30 | 📚 Cours spécifique | ⭐⭐⭐ Moyenne |
| `CONTEXT_MODULE` | 20 | 📝 Module d'activité | ⭐⭐ Faible |
| Autre/Invalide | 10/5/1 | ❓ Cas d'erreur | ⭐ Très faible |

**Règle** : La question avec le **score le plus ÉLEVÉ** est conservée (contexte le plus large = plus accessible).

#### 2️⃣ **Critère de Départage** : Ancienneté

Si plusieurs versions ont le **même niveau de contexte** (ex: toutes dans CONTEXT_COURSE), le critère de départage est :
- **La plus ANCIENNE** est conservée (`timecreated` le plus petit)

---

## ✅ Avantages

### 1. **Réutilisabilité Maximale**
Les questions conservées sont celles du contexte le plus large, donc **accessibles au plus grand nombre** d'enseignants/cours.

### 2. **Patrimoine Commun Privilégié**
Une question au niveau site est considérée comme un **bien commun** à préserver, plutôt qu'une copie locale dans un module.

### 3. **Cohérence Architecturale**
Respecte la hiérarchie Moodle : Site > Catégorie > Cours > Module.

### 4. **Transparence**
Logs détaillés pour traçabilité :
```php
debugging('Groupe "Question X" : Version conservée ID 12345 
          (Score: 50, 🌐 Site entier, créée le 12/03/2024)', DEBUG_DEVELOPER);
```

---

## 🔧 Implémentation Technique

### Nouvelle Fonction : `local_question_diagnostic_get_accessibility_score()`

```php
/**
 * Calcule le score d'accessibilité d'une question basé sur son contexte
 * 
 * @param object $question Question Moodle
 * @return object {score, contextlevel, contextid, timecreated, info}
 */
function local_question_diagnostic_get_accessibility_score($question)
```

**Fonctionnement** :
1. Récupère la catégorie de la question via `question_bank_entries` (Moodle 4.x)
2. Récupère le contexte associé via `context` table
3. Attribue un score selon `contextlevel`
4. Retourne un objet avec toutes les métadonnées

### Algorithme de Sélection

```php
// Calculer les scores pour toutes les versions inutilisées
foreach ($to_delete as $q) {
    $score_info = local_question_diagnostic_get_accessibility_score($q);
    $questions_with_scores[] = (object)[
        'question' => $q,
        'score' => $score_info->score,
        'timecreated' => $q->timecreated,
        'info' => $score_info->info
    ];
}

// Trier : Score décroissant > Ancienneté croissante
usort($questions_with_scores, function($a, $b) {
    if ($a->score != $b->score) {
        return $b->score - $a->score; // Plus grand score d'abord
    }
    return $a->timecreated - $b->timecreated; // Plus ancien d'abord
});

// Garder la meilleure
$best = array_shift($questions_with_scores);
$to_keep[] = $best->question;
```

---

## 📊 Exemples Concrets

### Exemple 1 : Questions dans Différents Contextes

**Groupe de doublons** :
- Question A : ID 100, CONTEXT_MODULE, créée le 01/01/2024
- Question B : ID 101, CONTEXT_COURSE, créée le 15/03/2024
- Question C : ID 102, CONTEXT_SYSTEM, créée le 10/05/2024

**Résultat** : Question C conservée (score 50 > 30 > 20)

### Exemple 2 : Même Contexte, Anciennetés Différentes

**Groupe de doublons** :
- Question A : ID 200, CONTEXT_COURSE, créée le 01/01/2024
- Question B : ID 201, CONTEXT_COURSE, créée le 15/03/2024
- Question C : ID 202, CONTEXT_COURSE, créée le 10/05/2024

**Résultat** : Question A conservée (même score 30, mais plus ancienne)

### Exemple 3 : Questions Orphelines

**Groupe de doublons** :
- Question A : ID 300, contexte invalide (score 5), créée le 01/01/2024
- Question B : ID 301, CONTEXT_COURSE, créée le 15/03/2024

**Résultat** : Question B conservée (score 30 > 5)

---

## 🔒 Sécurité & Compatibilité

### Sécurité Maintenue
- ✅ Au moins 1 version **TOUJOURS conservée**
- ✅ Versions utilisées dans des quiz **JAMAIS touchées**
- ✅ Confirmation utilisateur **OBLIGATOIRE**
- ✅ Logs détaillés pour audit

### Compatibilité Moodle
- ✅ **Moodle 4.5** (cible principale)
- ✅ **Moodle 4.3-4.4** (compatible)
- ⚠️ Utilise `question_bank_entries` (Moodle 4.0+)

### Gestion d'Erreurs
- Si le contexte ne peut être récupéré → score minimal (1-5)
- Si erreur SQL → fallback avec debugging
- Aucune interruption du processus de nettoyage

---

## 📝 Message Utilisateur

Lors de la confirmation de nettoyage, l'utilisateur voit désormais :

> 🔒 **Règles de sécurité**
> - ✅ Les versions utilisées dans des quiz seront CONSERVÉES
> - ✅ Seules les versions inutilisées seront supprimées
> - ✅ Au moins 1 version sera toujours conservée (même si toutes inutilisées)
> - 🌐 **Logique de conservation intelligente :** Si aucune version n'est utilisée, la version conservée sera celle du contexte le plus large (site > catégorie > cours > module), puis la plus ancienne en cas d'égalité

---

## 🧪 Tests Recommandés

1. **Test Contextes Mixtes**
   - Créer 3 doublons dans SYSTEM, COURSE, MODULE
   - Vérifier que la version SYSTEM est conservée

2. **Test Même Contexte**
   - Créer 3 doublons dans le même cours
   - Vérifier que la plus ancienne est conservée

3. **Test Contexte Invalide**
   - Créer des doublons avec catégories orphelines
   - Vérifier le fallback sur score minimal

4. **Test Versions Mixtes (Utilisées + Inutilisées)**
   - Créer 2 doublons inutilisés (MODULE, SYSTEM)
   - Créer 1 doublon utilisé (COURSE)
   - Vérifier que les 2 versions sont conservées (utilisée + SYSTEM)

---

## 📚 Références

- **Moodle Context API** : https://moodledev.io/docs/apis/core/context
- **Question Bank Architecture (4.x)** : https://moodledev.io/docs/apis/subsystems/questionbank
- **Cursor Rules** : `MOODLE_4.5_DATABASE_REFERENCE.md`

---

## 🎓 Notes Développeur

### Pourquoi cette Approche ?

L'objectif est d'encourager la **mutualisation des ressources pédagogiques** :
- Questions au niveau site = patrimoine commun
- Évite la fragmentation des ressources dans des contextes locaux
- Facilite la maintenance (1 version centralisée vs N copies)

### Extension Future Possible

- Ajouter un **paramètre utilisateur** pour choisir la stratégie (accessibilité vs ancienneté)
- Intégrer un **tableau de bord** montrant les versions conservées et leurs contextes
- Ajouter une **prévisualisation** avant nettoyage avec justification du choix

---

**Auteur** : Équipe Question Diagnostic  
**Révision** : v1.0 (13/10/2025)

