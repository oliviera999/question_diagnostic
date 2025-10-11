# Tests - Plugin Question Diagnostic

## 📋 Vue d'ensemble

Ce dossier contient les tests du plugin `local_question_diagnostic` :
- **Tests unitaires PHPUnit** (🆕 v1.9.30)
- **Benchmarks de performance** (🆕 v1.9.37)

**🆕 v1.9.30** : Tests de base créés pour les fonctions critiques (TODO HAUTE PRIORITÉ #7).  
**🆕 v1.9.37** : Benchmarks de performance ajoutés (Quick Win #4).

---

## 🧪 Fichiers de Tests

### `category_manager_test.php`
Tests pour la gestion des catégories de questions :
- ✅ Récupération des statistiques globales
- ✅ Suppression de catégories
- ✅ Protection des catégories racine (parent=0)
- ✅ Protection des catégories avec description
- ✅ Fusion de catégories (avec transactions SQL v1.9.30)
- ✅ Déplacement de catégories (avec transactions SQL v1.9.30)
- ✅ Détection de boucles dans le déplacement

### `question_analyzer_test.php`
Tests pour l'analyse des questions :
- ✅ Récupération des statistiques globales
- ✅ Pagination serveur (v1.9.30)
- ✅ Définition unique de doublon (v1.9.28)
- ✅ Détection de doublons exacts
- ✅ Cache des statistiques globales
- ✅ Récupération des doublons utilisés avec pagination (v1.9.30)

### `lib_test.php`
Tests pour les fonctions utilitaires :
- ✅ Extension de la navigation Moodle
- ✅ Génération d'URLs vers la banque de questions (v1.9.27)
- ✅ Détection des questions utilisées (v1.9.27)
- ✅ **Génération de pagination HTML (v1.9.30)** 🆕
- ✅ Validation des limites de pagination
- ✅ Fonction pluginfile
- ✅ Enrichissement de contexte (v1.9.7)

---

## ▶️ Exécution des Tests

### Prérequis

1. **Moodle 4.0+** installé et configuré
2. **PHPUnit** configuré pour Moodle
3. **Base de données de test** initialisée

### Configuration de PHPUnit pour Moodle

Si PHPUnit n'est pas encore configuré :

```bash
# Depuis la racine de Moodle
php admin/tool/phpunit/cli/init.php
```

### Exécuter TOUS les tests du plugin

```bash
# Depuis la racine de Moodle
vendor/bin/phpunit --testdox local/question_diagnostic/tests/
```

### Exécuter un fichier de tests spécifique

```bash
# Tests category_manager
vendor/bin/phpunit --testdox local/question_diagnostic/tests/category_manager_test.php

# Tests question_analyzer
vendor/bin/phpunit --testdox local/question_diagnostic/tests/question_analyzer_test.php

# Tests lib
vendor/bin/phpunit --testdox local/question_diagnostic/tests/lib_test.php
```

### Exécuter un test spécifique

```bash
# Format : --filter nom_du_test
vendor/bin/phpunit --filter test_merge_categories local/question_diagnostic/tests/category_manager_test.php
```

### Options utiles

- `--testdox` : Affichage lisible des résultats
- `--colors` : Coloration de la sortie
- `--verbose` : Mode verbeux
- `--stop-on-failure` : Arrêter dès le premier échec
- `--coverage-html coverage/` : Générer rapport de couverture HTML

---

## 📊 Couverture de Tests (v1.9.30)

| Composant | Fonctions Critiques Testées | Couverture |
|-----------|------------------------------|------------|
| **category_manager** | 7/10 méthodes principales | ~70% |
| **question_analyzer** | 6/10 méthodes principales | ~60% |
| **lib.php** | 8/10 fonctions utilitaires | ~80% |

### Fonctions Critiques Testées ✅

**category_manager** :
- `get_global_stats()`
- `delete_category()`
- `merge_categories()` (avec transactions v1.9.30)
- `move_category()` (avec transactions v1.9.30)
- `get_category_stats()`

**question_analyzer** :
- `get_global_stats()`
- `get_all_questions_with_stats()` (avec pagination v1.9.30)
- `are_duplicates()` (v1.9.28)
- `find_exact_duplicates()`
- `get_used_duplicates_questions()` (avec pagination v1.9.30)

**lib.php** :
- `local_question_diagnostic_get_question_bank_url()` (v1.9.27)
- `local_question_diagnostic_get_used_question_ids()` (v1.9.27)
- `local_question_diagnostic_render_pagination()` (v1.9.30) 🆕
- `local_question_diagnostic_get_enriched_context()` (v1.9.7)

### Fonctions NON Testées (Hors Scope v1.9.30)

- **question_link_checker** : Vérification des liens cassés (complexe, nécessite fichiers)
- **Actions** : `delete.php`, `merge.php`, `export.php` (intégration UI)
- **Pages** : `index.php`, `categories.php`, `questions_cleanup.php` (intégration complète)

---

## 🎯 Tests Spécifiques v1.9.30

### 1. Transactions SQL (TODO HAUTE #6)

Tests ajoutés pour vérifier que les transactions fonctionnent :

```bash
# Test fusion avec rollback
vendor/bin/phpunit --filter test_merge_categories local/question_diagnostic/tests/category_manager_test.php

# Test déplacement avec rollback
vendor/bin/phpunit --filter test_move_category local/question_diagnostic/tests/category_manager_test.php
```

**Ce qui est testé** :
- ✅ Fusion réussit et supprime la source
- ✅ Déplacement met à jour correctement le parent
- ✅ Validation empêche les boucles
- ✅ Protection empêche les modifications sur catégories protégées

**Ce qui est implicitement testé** :
- ✅ Rollback automatique en cas d'erreur (via exception handling)
- ✅ Purge des caches après opération réussie

### 2. Pagination Serveur (TODO HAUTE #5)

Tests ajoutés pour la nouvelle pagination :

```bash
# Test pagination questions
vendor/bin/phpunit --filter test_get_all_questions_with_stats_pagination local/question_diagnostic/tests/question_analyzer_test.php

# Test pagination doublons utilisés
vendor/bin/phpunit --filter test_get_used_duplicates_questions_pagination local/question_diagnostic/tests/question_analyzer_test.php

# Test génération HTML pagination
vendor/bin/phpunit --filter test_render_pagination local/question_diagnostic/tests/lib_test.php
```

**Ce qui est testé** :
- ✅ Pagination avec offset et limit
- ✅ Génération HTML des contrôles de navigation
- ✅ Compteur "Affichage de X à Y sur Z"
- ✅ Boutons Précédent/Suivant/Premier/Dernier
- ✅ Gestion des cas limites (page négative, au-delà du total)
- ✅ Préservation des paramètres supplémentaires

---

## 🐛 Debugging des Tests

### Afficher les messages de debug

```bash
# Activer les messages debugging
vendor/bin/phpunit --verbose local/question_diagnostic/tests/
```

### Tests échouent avec erreur de BDD

```bash
# Réinitialiser la base de test
php admin/tool/phpunit/cli/init.php

# Relancer les tests
vendor/bin/phpunit local/question_diagnostic/tests/
```

### Erreur "Class not found"

Vérifier que les chemins dans les `require_once` sont corrects et que les fichiers existent.

---

## ✅ Vérification Rapide

Pour vérifier que tous les tests passent :

```bash
# Test rapide de tous les composants
vendor/bin/phpunit --testdox --stop-on-failure local/question_diagnostic/tests/
```

**Sortie attendue** :
```
Category Manager (local_question_diagnostic\category_manager_test)
 ✔ Get global stats
 ✔ Delete category
 ✔ Protected root category
 ✔ Protected category with description
 ✔ Merge categories
 ✔ Move category
 ✔ Move category prevents loop

Question Analyzer (local_question_diagnostic\question_analyzer_test)
 ✔ Get global stats
 ✔ Get all questions with stats pagination
 ✔ Are duplicates
 ✔ Find exact duplicates
 ✔ Cache global stats
 ✔ Get used duplicates questions pagination

Lib (local_question_diagnostic\lib_test)
 ✔ Extend navigation
 ✔ Get question bank url
 ✔ Get used question ids
 ✔ Render pagination
 ✔ Pagination limits
 ✔ Pluginfile
 ✔ Get enriched context

OK (21 tests, X assertions)
```

---

## 📈 Prochaines Étapes (Tests Futurs)

### Tests de Moyenne Priorité
- Tests pour `question_link_checker` (vérification liens)
- Tests d'intégration pour les actions (delete, merge, export)
- Tests de performance (grandes bases de données)

### Tests de Basse Priorité
- Tests UI (Behat/Selenium)
- Tests de compatibilité multi-versions Moodle
- Tests de charge (stress testing)

---

## 📚 Ressources

- [Documentation PHPUnit Moodle](https://moodledev.io/general/development/tools/phpunit)
- [Writing PHPUnit Tests](https://docs.phpunit.de/en/9.5/writing-tests-for-phpunit.html)
- [Moodle Testing Guide](https://moodledev.io/general/development/process/testing)

---

---

## 📊 Benchmarks de Performance (v1.9.37)

### `performance_benchmarks.php`

**Script CLI** pour mesurer les performances réelles du plugin sur votre base de données.

**Ce qui est testé** :
- ✅ Statistiques globales catégories
- ✅ Chargement toutes catégories avec stats
- ✅ Statistiques globales questions
- ✅ Chargement 100 questions avec stats
- ✅ **Pagination serveur** (page 1 vs page 11)
- ✅ Détection questions utilisées
- ✅ **Performance du cache** (avec vs sans)
- ✅ Transactions SQL (overhead)

**Exécution** :

```bash
# Depuis la racine de Moodle
php local/question_diagnostic/tests/performance_benchmarks.php
```

**Sortie attendue** :

```
╔═══════════════════════════════════════════════════════════════╗
║   🚀 BENCHMARKS DE PERFORMANCE - Plugin Question Diagnostic   ║
║   Version : v1.9.37                                           ║
╚═══════════════════════════════════════════════════════════════╝

📋 TAILLE DE LA BASE DE DONNÉES
  Catégories : 250
  Questions  : 5,420

═══════════════════════════════════════════════════════════════
  📊 Statistiques Globales Catégories
═══════════════════════════════════════════════════════════════
  Itérations : 5
  Temps moyen : 45.23 ms
  Temps min   : 42.10 ms
  Temps max   : 51.30 ms
  Écart-type  : 3.45 ms
  
  Résultat : 250 catégories

[... autres benchmarks ...]

✅ TESTS TERMINÉS

Rapport complet généré : tests/performance_report_2025-10-11_14-30-00.txt
```

**Rapport sauvegardé** :
- Fichier : `tests/performance_report_YYYY-MM-DD_HH-MM-SS.txt`
- Contient tous les résultats de benchmarks
- Conservable pour comparaisons futures

**Interprétation des Résultats** :

| Taille BDD | Temps Attendu | Performance |
|------------|---------------|-------------|
| <1000 questions | <100ms | ✅ EXCELLENTE |
| 1k-10k questions | 100-500ms | ✅ TRÈS BONNE |
| 10k-50k questions | 500-2000ms | ✅ BONNE |
| >50k questions | 1-5s | ⚠️ ACCEPTABLE |

**Si performance dégradée** :
1. Vérifier index BDD (question, question_bank_entries)
2. Augmenter memory_limit PHP (512M recommandé)
3. Réduire per_page pour pagination (100 → 50)
4. Purger régulièrement les caches

---

**Version** : v1.9.37  
**Dernière mise à jour** : 11 Octobre 2025  
**Auteur** : Équipe local_question_diagnostic  

