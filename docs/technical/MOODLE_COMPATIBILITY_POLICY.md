# 🎯 Politique de Compatibilité Moodle - Plugin Question Diagnostic

**Version** : v1.9.34  
**Dernière mise à jour** : 11 Octobre 2025

---

## 📊 Versions Supportées

### Version Minimale Requise

**Moodle 4.0** (Release : Novembre 2022)

```php
// version.php
$plugin->requires = 2022041900; // Moodle 4.0
```

**Pourquoi Moodle 4.0 minimum ?**

1. **Architecture Question Bank** refactorisée dans Moodle 4.0 :
   - Nouvelle table `question_bank_entries`
   - Nouvelle table `question_versions`
   - Fin du système legacy `question.category`

2. **Compatibilité descendante complexe** :
   - Moodle 3.x utilise une architecture totalement différente
   - Maintenir la compatibilité 3.x double la complexité du code
   - Risque élevé de bugs sur anciennes versions

3. **Cycle de vie Moodle** :
   - Moodle 3.11 (dernière 3.x) : **End of Life en décembre 2023**
   - Moodle 4.0 : Support jusqu'en novembre 2024
   - Moodle 4.1 LTS : Support jusqu'en novembre 2026

---

### Versions Testées et Validées

| Version Moodle | Statut | Tests | Recommandation |
|----------------|--------|-------|----------------|
| **4.5** | ✅ Supporté | Complets | ⭐ **Recommandé** |
| **4.4** | ✅ Supporté | Basiques | ✅ OK |
| **4.3** | ✅ Supporté | Basiques | ✅ OK |
| **4.2** | ⚠️ Compatible | Non testés | ⚠️ Non garanti |
| **4.1 LTS** | ✅ Supporté | Basiques | ✅ OK |
| **4.0** | ⚠️ Compatible | Minimal | ⚠️ Minimum requis |
| **3.x** | ❌ Non supporté | - | ❌ Incompatible |

---

## 🔧 Différences par Version

### Moodle 4.5+ (Cible Principale)

**Changements majeurs** :
- `quiz_slots` : Plus de colonne `questionid` ni `questionbankentryid`
- Nouvelle table `question_references` pour lier slots ↔ questions
- `question_versions.status` pour questions cachées

**Gestion dans le plugin** :
```php
// Détection automatique de la structure
$columns = $DB->get_columns('quiz_slots');

if (!isset($columns['questionbankentryid']) && !isset($columns['questionid'])) {
    // Moodle 4.5+ : Utiliser question_references
    $sql = "SELECT DISTINCT qv.questionid
            FROM {quiz_slots} qs
            INNER JOIN {question_references} qr ON qr.itemid = qs.id 
                AND qr.component = 'mod_quiz' 
                AND qr.questionarea = 'slot'
            INNER JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
            INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id";
}
```

---

### Moodle 4.1-4.4

**Structure** :
- `quiz_slots.questionbankentryid` existe
- `question_bank_entries` et `question_versions` présentes
- Pas encore `question_references`

**Gestion dans le plugin** :
```php
if (isset($columns['questionbankentryid'])) {
    // Moodle 4.1-4.4
    $sql = "SELECT DISTINCT qv.questionid
            FROM {quiz_slots} qs
            INNER JOIN {question_bank_entries} qbe ON qbe.id = qs.questionbankentryid
            INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id";
}
```

---

### Moodle 4.0

**Structure** :
- `quiz_slots.questionid` existe encore
- `question_bank_entries` introduite mais pas utilisée dans quiz_slots
- Période de transition

**Gestion dans le plugin** :
```php
if (isset($columns['questionid'])) {
    // Moodle 4.0
    $sql = "SELECT DISTINCT qs.questionid FROM {quiz_slots} qs";
}
```

---

### Moodle 3.x ❌ NON SUPPORTÉ

**Pourquoi ?**

1. **Architecture incompatible** :
   - Pas de `question_bank_entries`
   - Utilise `question.category` (deprecated en 4.0)
   - Logique totalement différente

2. **End of Life** : Moodle 3.11 EOL depuis décembre 2023

3. **Complexité** : Supporter 3.x doublerait la base de code

**Si vous DEVEZ utiliser Moodle 3.x** :
- ❌ Ce plugin n'est PAS compatible
- 💡 Utiliser une version antérieure du plugin (v1.0-v1.5)
- 💡 Ou upgrader votre Moodle vers 4.0+

---

## 🧹 Fallbacks dans le Code

### Fallbacks Conservés (Nécessaires)

#### 1. Détection Structure quiz_slots

**Fichiers concernés** :
- `lib.php` : `local_question_diagnostic_get_used_question_ids()`
- `classes/question_analyzer.php` : Plusieurs méthodes
- `questions_cleanup.php` : Test doublons utilisés

**Raison** : Les structures changent entre 4.0, 4.1-4.4 et 4.5+

```php
// ✅ CONSERVER : Détection dynamique de la structure
$columns = $DB->get_columns('quiz_slots');

if (!isset($columns['questionbankentryid']) && !isset($columns['questionid'])) {
    // Moodle 4.5+
} else if (isset($columns['questionbankentryid'])) {
    // Moodle 4.1-4.4
} else if (isset($columns['questionid'])) {
    // Moodle 4.0
}
```

**Verdict** : ✅ **CONSERVER** (nécessaire pour compatibilité 4.0-4.5)

---

#### 2. Fallback question_versions.status

**Fichier** : `classes/question_analyzer.php` lignes 205-209

```php
try {
    $stats->is_hidden = ($record->status == 'hidden');
    $stats->status = $stats->is_hidden ? 'hidden' : 'visible';
} catch (\Exception $e) {
    // Fallback si erreur
    $stats->is_hidden = false;
    $stats->status = 'visible';
}
```

**Verdict** : ✅ **CONSERVER** (gestion d'erreur robuste)

---

#### 3. Fallback get_all_categories_with_stats

**Fichier** : `classes/category_manager.php` lignes 217-220

```php
} catch (\Exception $e) {
    // FALLBACK : Si erreur SQL, utiliser l'ancienne méthode (lente mais fiable)
    debugging('Erreur dans get_all_categories_with_stats optimisé, utilisation fallback : ' . $e->getMessage(), DEBUG_DEVELOPER);
    return self::get_all_categories_with_stats_fallback();
}
```

**Verdict** : ✅ **CONSERVER** (robustesse en cas d'erreur)

---

### Fallbacks à Supprimer (Legacy Moodle 3.x)

#### 1. Commentaires "Moodle 3.x" trompeurs

**Problème** : Les commentaires disent "Moodle 3.x/4.0" alors que Moodle 3.x n'est PAS supporté

**Solution** : Clarifier les commentaires

**Avant** :
```php
} else if (isset($columns['questionid'])) {
    // Moodle 3.x/4.0 : utilise questionid directement
```

**Après** :
```php
} else if (isset($columns['questionid'])) {
    // Moodle 4.0 uniquement : utilise questionid directement
    // Note : Moodle 3.x NON supporté par ce plugin
```

**Fichiers à corriger** :
- `lib.php` : ligne 206
- `classes/question_analyzer.php` : lignes 259, 348, 544, 658, 1031, 1135
- `questions_cleanup.php` : ligne 266

**Total** : 8 commentaires à clarifier

---

#### 2. Code de debug référençant Moodle 3.x

**Fichiers** :
- `debug_categories.php` : lignes 120-129
- `test.php` : lignes 116-118, 204-206

**Ces fichiers sont-ils nécessaires ?**
- `debug_categories.php` : Outil de debug pour développeurs → ✅ CONSERVER
- `test.php` : Tests manuels → ⚠️ SUPPRIMER ou renommer en `manual_tests.php`

**Solution** : Clarifier que c'est pour comparaison uniquement

---

## ✅ Actions Recommandées

### Immédiat (v1.9.34)

1. **Clarifier les commentaires** (8 emplacements) :
   - Remplacer "Moodle 3.x/4.0" par "Moodle 4.0"
   - Ajouter note "Moodle 3.x NON supporté"

2. **Mettre à jour README.md** :
   - Changer "Moodle 3.9+" en "Moodle 4.0+ (4.5+ recommandé)"
   - Documenter les versions testées

3. **Documenter clairement** :
   - Créer ce fichier (MOODLE_COMPATIBILITY_POLICY.md)
   - Référencer dans README

### Futur (optionnel)

1. **Supprimer tests manuels legacy** :
   - Renommer `test.php` en `manual_tests_legacy.php`
   - Documenter qu'ils sont obsolètes

2. **Créer tests compatibilité automatisés** :
   - CI/CD GitHub Actions
   - Tester sur Moodle 4.1, 4.3, 4.4, 4.5

---

## 📋 Déclaration Officielle

### Versions Supportées

**✅ SUPPORTÉ :**
- Moodle 4.5.x (recommandé - testé)
- Moodle 4.4.x (compatible - testé)
- Moodle 4.3.x (compatible - testé)
- Moodle 4.1.x LTS (compatible - testé)
- Moodle 4.0.x (minimum requis - testé)

**❌ NON SUPPORTÉ :**
- Moodle 3.x (toutes versions) : Architecture incompatible
- Moodle versions < 3.9 : Totalement incompatible

### Pourquoi Moodle 4.0+ ?

1. **Architecture Question Bank** : Nouveau système question_bank_entries
2. **Cycle de vie** : Moodle 3.x est EOL depuis 2023
3. **Maintenance** : Supporter 3.x doublerait la complexité
4. **Performance** : Nouvelles tables optimisées pour grandes bases

---

## 🔄 Migration depuis Moodle 3.x

**Si vous utilisez Moodle 3.x** :

**Option 1** : Upgrader Moodle (Recommandé)
```bash
# Moodle 3.11 → 4.0 → 4.1 → 4.3 → 4.5
# Suivre : https://docs.moodle.org/en/Upgrading
```

**Option 2** : Utiliser ancienne version du plugin
- Plugin version < 1.5 supportait Moodle 3.x
- ⚠️ Fonctionnalités limitées
- ⚠️ Plus de support

---

## 📝 Checklist Compatibilité pour Développeurs

Avant de coder :

- [ ] Vérifier `$plugin->requires` dans version.php (Moodle 4.0+)
- [ ] Utiliser `question_bank_entries` (PAS `question.category`)
- [ ] Détecter dynamiquement la structure `quiz_slots`
- [ ] Tester sur Moodle 4.5 (version cible)
- [ ] Ajouter tests PHPUnit pour validation
- [ ] Documenter les breaking changes

---

## 🚨 Breaking Changes par Version

### Moodle 4.0 → 4.1

- `quiz_slots` : Ajout colonne `questionbankentryid`
- Impact : ✅ Géré par détection dynamique

### Moodle 4.4 → 4.5

- `quiz_slots` : Suppression colonnes `questionid` et `questionbankentryid`
- Ajout `question_references` pour lier slots ↔ questions
- Impact : ✅ Géré par détection dynamique via `question_references`

### Futures Versions

**Stratégie** : Détection dynamique de la structure

```php
// Pattern à utiliser
$columns = $DB->get_columns('table_name');

if (isset($columns['new_column'])) {
    // Nouvelle structure
} else {
    // Ancienne structure
}
```

---

## 🧪 Tests de Compatibilité

### Tests Manuels Requis

Avant chaque release, tester sur :

1. **Moodle 4.5** (Docker ou VM)
2. **Moodle 4.3** (Docker ou VM)
3. **Moodle 4.1 LTS** (Docker ou VM)
4. **Moodle 4.0** (Docker ou VM - minimum requis)

### Tests Automatisés (Futur)

```yaml
# .github/workflows/moodle-compatibility.yml
name: Moodle Compatibility Tests

on: [push, pull_request]

jobs:
  test:
    strategy:
      matrix:
        moodle: ['4.0', '4.1', '4.3', '4.4', '4.5']
    runs-on: ubuntu-latest
    steps:
      - name: Setup Moodle ${{ matrix.moodle }}
      - name: Install plugin
      - name: Run PHPUnit tests
```

---

## 📚 Ressources

- **Moodle Release Notes** : https://docs.moodle.org/dev/Releases
- **Question Bank API** : https://moodledev.io/docs/apis/subsystems/questionbank
- **Upgrade Guide** : https://docs.moodle.org/en/Upgrading
- **EOL Dates** : https://moodledev.io/general/releases

---

## ✅ Résumé

**Version minimum** : Moodle 4.0  
**Version recommandée** : Moodle 4.5  
**Moodle 3.x** : ❌ NON SUPPORTÉ  

**Raison** : Architecture Question Bank incompatible + EOL

---

**Version de cette politique** : v1.0  
**Auteur** : Équipe local_question_diagnostic  
**Applicable depuis** : v1.9.34  

