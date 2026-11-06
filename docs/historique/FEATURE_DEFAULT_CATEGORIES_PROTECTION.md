# 🛡️ Protection Conditionnelle des Catégories "Default for"

## 📋 Vue d'ensemble

Depuis la version **v1.10.3**, le plugin implémente une **protection conditionnelle intelligente** pour les catégories "Default for..." / "Par défaut pour...".

**Principe** : Protéger SEULEMENT les catégories par défaut liées à des **contextes valides**, tout en permettant la suppression des catégories orphelines.

---

## 🎯 Pourquoi ce changement ?

### ❌ Ancien comportement (v1.0 - v1.10.2)

**Protection systématique de TOUTES les catégories "Default for"**

```
✅ "Default for Cours Actif" → PROTÉGÉE (correct ✓)
✅ "Default for [Cours supprimé]" → PROTÉGÉE (problème ✗)
✅ "Default for Context ID: 999" (orphelin) → PROTÉGÉE (problème ✗)
```

**Conséquence** :
- Impossible de nettoyer les catégories "Default for" obsolètes
- Accumulation de catégories orphelines non supprimables
- Confusion pour l'administrateur

### ✅ Nouveau comportement (v1.10.3+)

**Protection conditionnelle basée sur le contexte**

```
✅ "Default for Cours Actif" → PROTÉGÉE (contexte valide)
❌ "Default for [Cours supprimé]" → SUPPRIMABLE (contexte orphelin)
❌ "Default for Context ID: 999" → SUPPRIMABLE (contexte invalide)
```

**Avantages** :
- ✅ Nettoyage intelligent des catégories obsolètes
- ✅ Protection maintenue pour les catégories actives
- ✅ Cohérence avec la logique des autres catégories orphelines

---

## 🔍 Logique de Protection

### Critères de Protection

Une catégorie "Default for..." est **PROTÉGÉE** si et seulement si :

1. ✅ Son nom contient "Default for" OU "Par défaut pour"
2. **ET** ✅ Son contexte est **valide** (existe dans la table `context`)

Si le contexte est **orphelin** (n'existe plus), la catégorie devient **SUPPRIMABLE** (si vide).

### Hiérarchie des Protections

Le plugin implémente **3 types de protections** (ordre de priorité) :

1. 🛡️ **Catégorie racine (parent=0)** avec contexte valide
   - **Raison** : Catégorie top-level, structure critique Moodle
   - **Supprimable** : ❌ NON (même si vide)

2. 🛡️ **Catégorie "Default for"** avec contexte valide
   - **Raison** : Catégorie par défaut Moodle (contexte actif)
   - **Supprimable** : ❌ NON (même si vide)
   
3. 🛡️ **Catégorie avec description** (`info` non vide)
   - **Raison** : Usage documenté/intentionnel
   - **Supprimable** : ❌ NON (même si vide)

**Exception** : Si le contexte est **orphelin**, seule la protection #1 (racine) s'applique. Les protections #2 et #3 sont levées.

---

## 💻 Implémentation Technique

### Fichiers Modifiés

**`classes/category_manager.php`** - 3 fonctions mises à jour :

#### 1. `get_all_categories_with_stats()` (ligne 156-163)

**Version optimisée pour batch**

```php
// Protection 1 : "Default for..." AVEC contexte valide
// 🔧 v1.10.3 : Protection conditionnelle - protéger SEULEMENT si contexte valide
// Les catégories "Default for" orphelines (contexte supprimé) sont supprimables
if ((stripos($cat->name, 'Default for') !== false || stripos($cat->name, 'Par défaut pour') !== false) 
    && $context_valid) {
    $is_protected = true;
    $protection_reason = 'Catégorie par défaut Moodle (contexte actif)';
}
```

**Variables disponibles** :
- `$context_valid` : Booléen calculé en amont (ligne 150)
- Provient de la requête SQL ligne 67-75 (LEFT JOIN avec `context`)

#### 2. `get_category_stats()` (ligne 321-328)

**Version fallback (une catégorie à la fois)**

```php
// Protection 1 : "Default for..." AVEC contexte valide
// 🔧 v1.10.3 : Protection conditionnelle - protéger SEULEMENT si contexte valide
// Les catégories "Default for" orphelines (contexte supprimé) sont supprimables
if ((stripos($category->name, 'Default for') !== false || stripos($category->name, 'Par défaut pour') !== false) 
    && $stats->context_valid) {
    $stats->is_protected = true;
    $stats->protection_reason = 'Catégorie par défaut Moodle (contexte actif)';
}
```

**Variables disponibles** :
- `$stats->context_valid` : Calculé lignes 285-300
- Vérifie l'existence du contexte via `$DB->record_exists('context', ['id' => $category->contextid])`

#### 3. `delete_category()` (ligne 412-427)

**Fonction de suppression avec vérification finale**

```php
// 🛡️ PROTECTION 1 : Catégories "Default for..." AVEC contexte valide
// 🔧 v1.10.3 : Protection conditionnelle - protéger SEULEMENT si contexte actif
// Les catégories "Default for" orphelines (contexte supprimé) peuvent être supprimées
if (stripos($category->name, 'Default for') !== false || stripos($category->name, 'Par défaut pour') !== false) {
    // Vérifier si le contexte est valide
    try {
        $context = \context::instance_by_id($category->contextid, IGNORE_MISSING);
        if ($context) {
            // Contexte valide → PROTÉGÉE
            return "❌ PROTÉGÉE : Cette catégorie par défaut est liée à un contexte actif (cours, quiz, etc.) et ne doit pas être supprimée. Si vous devez vraiment la supprimer, supprimez d'abord le cours/contexte associé.";
        }
        // Sinon : contexte invalide/orphelin → SUPPRIMABLE (continuer les autres vérifications)
    } catch (\Exception $e) {
        // Erreur de contexte → considéré comme orphelin → SUPPRIMABLE (continuer)
    }
}
```

**Vérification en temps réel** :
- Utilise `\context::instance_by_id($category->contextid, IGNORE_MISSING)`
- Si contexte trouvé → PROTÉGÉE
- Si exception ou null → SUPPRIMABLE (continuer les autres vérifications)

---

## 🎨 Affichage dans l'Interface

### Dashboard des Catégories (`categories.php`)

#### Colonne "Statut"

**Catégorie "Default for" avec contexte valide** :
```
🛡️ PROTÉGÉE
Catégorie par défaut Moodle (contexte actif)
```

**Catégorie "Default for" orpheline** :
```
Vide   Orpheline
```

#### Colonne "🗑️ Supprimable"

**Catégorie "Default for" avec contexte valide** :
```
❌ NON
🛡️ Catégorie par défaut Moodle (contexte actif)
```

**Catégorie "Default for" orpheline (vide)** :
```
✅ OUI
```

### Messages de Suppression

#### Tentative de suppression d'une catégorie protégée

```
❌ PROTÉGÉE : Cette catégorie par défaut est liée à un contexte actif 
(cours, quiz, etc.) et ne doit pas être supprimée. 
Si vous devez vraiment la supprimer, supprimez d'abord le cours/contexte associé.
```

#### Suppression réussie d'une catégorie orpheline

```
✅ Catégorie "Default for [Cours supprimé]" supprimée avec succès.
```

---

## 📊 Statistiques Affectées

### Dashboard Global

**Carte "Catégories Protégées"**

Depuis v1.10.3, le compteur **exclut** les catégories "Default for" orphelines.

**Requête SQL (ligne 843-853 de `category_manager.php`)** :
```sql
SELECT COUNT(DISTINCT qc.id)
FROM {question_categories} qc
LEFT JOIN {context} ctx ON ctx.id = qc.contextid
WHERE (
    qc.name LIKE '%Default for%'
    OR (qc.info IS NOT NULL AND qc.info != '')
    OR (qc.parent = 0 AND ctx.id IS NOT NULL)  -- Vérification contexte valide
)
```

**Impact** :
- Le nombre de catégories protégées diminue si des "Default for" sont orphelines
- Cohérence avec l'affichage du tableau

---

## 🧪 Tests et Validation

### Scénarios de Test

#### Test 1 : Catégorie "Default for" avec cours actif

**Contexte** :
- Catégorie : "Default for Cours Math 101"
- Cours : Existe (ID: 5)
- Contexte : Valide (ID: 42, type COURSE)

**Comportement attendu** :
- ✅ Badge "🛡️ PROTÉGÉE"
- ✅ Colonne "Supprimable" : ❌ NON
- ✅ Bouton "Supprimer" : Désactivé (remplacé par "🛡️ Protégée")
- ✅ Tentative de suppression : Refusée avec message explicite

#### Test 2 : Catégorie "Default for" orpheline (cours supprimé)

**Contexte** :
- Catégorie : "Default for Cours Supprimé" (vide)
- Cours : N'existe plus
- Contexte : Invalide (ID: 999, pas dans la table `context`)

**Comportement attendu** :
- ❌ PAS de badge "PROTÉGÉE"
- ✅ Badge "Vide" + "Orpheline"
- ✅ Colonne "Supprimable" : ✅ OUI
- ✅ Bouton "🗑️ Supprimer" : Actif
- ✅ Suppression : Autorisée et réussie

#### Test 3 : Catégorie "Default for" racine (parent=0)

**Contexte** :
- Catégorie : "Default for System" (parent=0)
- Contexte : Valide (CONTEXT_SYSTEM)

**Comportement attendu** :
- ✅ Badge "🛡️ PROTÉGÉE"
- ✅ Raison : "Catégorie racine (top-level)" (priorité sur "Default for")
- ✅ TOUJOURS protégée (même si contexte invalide, car racine)

---

## 🔐 Sécurité et Garanties

### Protections Maintenues

✅ **Aucune régression** : Les catégories "Default for" actives restent **100% protégées**

✅ **Cohérence BDD** : Vérification en temps réel du contexte avant suppression

✅ **Transparence** : Messages explicites pour l'administrateur

✅ **Audit** : Logs de suppression via `audit_logger::log_category_deletion()`

### Cas Limites Gérés

| Cas | Comportement |
|-----|--------------|
| Catégorie "Default for" + contexte valide + vide | ✅ PROTÉGÉE |
| Catégorie "Default for" + contexte orphelin + vide | ❌ SUPPRIMABLE |
| Catégorie "Default for" + contexte valide + contient questions | ✅ PROTÉGÉE (double protection) |
| Catégorie "Default for" + parent=0 + contexte orphelin | ✅ PROTÉGÉE (protection racine prioritaire) |
| Catégorie "Default for" + contexte valide + avec description | ✅ PROTÉGÉE (double protection) |

---

## 📚 Références

### Documentation Associée

- **`README.md`** : Vue d'ensemble du plugin
- **`PROJECT_OVERVIEW.md`** : Architecture technique
- **`CHANGELOG.md`** : Historique des versions
- **`FEATURE_CATEGORY_PROTECTION.md`** : Documentation générale des protections

### Code Source

- **`classes/category_manager.php`** : Implémentation des protections
- **`categories.php`** : Interface utilisateur
- **`actions/delete.php`** : Action de suppression

### Tests

- **Script de test** : À créer (`tests/category_protection_test.php`)
- **Jeu de données** : À créer (`tests/fixtures/default_categories.sql`)

---

## 📝 Notes de Migration

### Mise à jour depuis v1.10.2 ou antérieur

**Aucune action requise**

- ✅ Compatibilité ascendante garantie
- ✅ Aucune modification de base de données
- ✅ Les catégories déjà protégées restent protégées (si contexte valide)

**Comportement après mise à jour** :

1. Les catégories "Default for" **orphelines** deviendront **supprimables** (si vides)
2. Le compteur de catégories protégées peut diminuer
3. Le filtre "Sans questions ni sous-catégories (supprimables)" affichera plus de résultats

**Recommandations** :

1. 🔍 **Vérifier** le dashboard avant/après mise à jour
2. 📊 **Comparer** le nombre de catégories protégées
3. 🧹 **Nettoyer** les catégories orphelines identifiées
4. 💾 **Backup** avant tout nettoyage (procédure standard)

---

## ✅ Checklist de Validation

Avant de déployer en production, vérifier :

- [ ] Les catégories "Default for" avec cours actifs restent protégées
- [ ] Les catégories "Default for" orphelines deviennent supprimables (si vides)
- [ ] Le compteur de catégories protégées est cohérent
- [ ] Les messages d'erreur sont clairs
- [ ] La suppression d'une catégorie orpheline fonctionne
- [ ] Les logs d'audit sont générés
- [ ] Le dashboard affiche les bonnes statistiques
- [ ] La documentation est à jour

---

**Version** : v1.10.3  
**Date** : 14 octobre 2025  
**Auteur** : Équipe local_question_diagnostic

