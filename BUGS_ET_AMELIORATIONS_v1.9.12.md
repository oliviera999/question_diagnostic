# 🐛 BUGS IDENTIFIÉS ET AMÉLIORATIONS PROPOSÉES - v1.9.12

**Date d'audit** : 10 octobre 2025  
**Fichier** : `questions_cleanup.php`  
**Version actuelle** : v1.9.12

---

## 🔴 BUGS CRITIQUES

### BUG #1 : SQL Non-Portable - RAND() ⚠️ CRITIQUE

**Fichier** : `questions_cleanup.php`  
**Lignes** : 98, 237  
**Sévérité** : 🔴 CRITIQUE (Cassé sur PostgreSQL/MSSQL)

#### Problème

```php
// Ligne 98
$random_question = $DB->get_record_sql("SELECT * FROM {question} ORDER BY RAND() LIMIT 1");

// Ligne 237
ORDER BY RAND()
LIMIT 5
```

**Pourquoi c'est un bug** :
- `RAND()` est spécifique à MySQL/MariaDB
- PostgreSQL utilise `RANDOM()`
- MSSQL utilise `NEWID()`
- **L'application plante sur tout SGBD non-MySQL**

#### Impact

- ❌ Fonctionnalité "Test Aléatoire Doublons" **inutilisable** sur PostgreSQL
- ❌ Fonctionnalité "Test Doublons Utilisés" **inutilisable** sur PostgreSQL
- ❌ Incompatibilité avec Moodle sur PostgreSQL (25% des installations)

#### Solution Proposée

**Option 1 : Utiliser l'API Moodle (RECOMMANDÉ)**

```php
// ✅ AVANT - Non portable
$random_question = $DB->get_record_sql("SELECT * FROM {question} ORDER BY RAND() LIMIT 1");

// ✅ APRÈS - Portable
$total_count = $DB->count_records('question');
if ($total_count > 0) {
    $random_offset = rand(0, $total_count - 1);
    $questions = $DB->get_records('question', null, 'id ASC', '*', $random_offset, 1);
    $random_question = reset($questions);
}
```

**Option 2 : SQL Multi-SGBD**

```php
// Utiliser $DB->sql_random() de Moodle
$sql = "SELECT * FROM {question} ORDER BY " . $DB->sql_random() . " LIMIT 1";
$random_question = $DB->get_record_sql($sql);
```

**Note** : `$DB->sql_random()` retourne la fonction appropriée selon le SGBD :
- MySQL: `RAND()`
- PostgreSQL: `RANDOM()`
- MSSQL: `NEWID()`

---

### BUG #2 : SQL Non-Portable - CONCAT() ⚠️ MOYEN

**Fichier** : `questions_cleanup.php`  
**Ligne** : 231  
**Sévérité** : 🟡 MOYEN (Problème sur MSSQL)

#### Problème

```php
$sql = "SELECT CONCAT(q.name, '|', q.qtype) as signature, ...
```

**Pourquoi c'est un problème** :
- `CONCAT()` avec séparateurs fonctionne différemment sur MSSQL
- MSSQL utilise `+` pour concaténer : `q.name + '|' + q.qtype`
- PostgreSQL accepte CONCAT mais aussi `||`

#### Solution Proposée

```php
// ✅ Utiliser $DB->sql_concat()
$signature_field = $DB->sql_concat('q.name', "'|'", 'q.qtype');
$sql = "SELECT {$signature_field} as signature,
               MIN(q.id) as sample_id,
               COUNT(DISTINCT q.id) as question_count
        FROM {question} q
        GROUP BY q.name, q.qtype
        HAVING COUNT(DISTINCT q.id) > 1
        ORDER BY " . $DB->sql_random() . "
        LIMIT 5";
```

---

## 🟡 PROBLÈMES DE PERFORMANCE

### PERF #1 : Boucle N+1 Potentielle ⚠️ MOYEN

**Fichier** : `questions_cleanup.php`  
**Lignes** : 914-916  
**Sévérité** : 🟡 MOYEN (Impact sur grandes bases)

#### Problème

```php
// Mode doublons utilisés
$questions_with_stats = [];
foreach ($questions as $q) {
    $stats = question_analyzer::get_question_stats($q);  // ⚠️ Requête par question !
    $questions_with_stats[] = $stats;
}
```

**Impact** :
- Si `get_question_stats()` fait 5 requêtes par question
- Et qu'on charge 100 questions
- = **500 requêtes SQL** !

#### Analyse Requise

Vérifier si `question_analyzer::get_question_stats()` :
1. Utilise déjà du batching interne ✅
2. Fait des requêtes individuelles ❌

#### Solution Proposée (si besoin)

```php
// ✅ Charger les stats en batch
$question_ids = array_map(function($q) { return $q->id; }, $questions);
$stats_batch = question_analyzer::get_multiple_question_stats($question_ids);

$questions_with_stats = [];
foreach ($questions as $q) {
    $questions_with_stats[] = $stats_batch[$q->id];
}
```

---

### PERF #2 : Limite de 5000 Sans Pagination

**Fichier** : `questions_cleanup.php`  
**Ligne** : 866  
**Sévérité** : 🟡 MOYEN (UX sur très grandes bases)

#### Problème

```php
$max_questions_display = min($max_questions_display, 5000); // Limite absolue : 5000
```

**Problème** :
- Sur une base de 30 000 questions, impossible de voir les questions 10 000+
- Pas de pagination, seulement "afficher les N premières"

#### Solution Proposée

**Option 1 : Ajouter pagination**

```php
$page = optional_param('page', 1, PARAM_INT);
$per_page = optional_param('show', 100, PARAM_INT);
$offset = ($page - 1) * $per_page;

$questions_with_stats = question_analyzer::get_all_questions_with_stats(
    $include_duplicates, 
    $per_page,
    $offset  // Nouveau paramètre
);
```

**Option 2 : Augmenter la limite avec avertissement**

```php
$max_questions_display = optional_param('show', 10, PARAM_INT);
if ($max_questions_display > 1000) {
    echo html_writer::start_tag('div', ['class' => 'alert alert-warning']);
    echo '⚠️ Vous avez demandé ' . $max_questions_display . ' questions. ';
    echo 'Le chargement peut prendre plus de 30 secondes.';
    echo html_writer::end_tag('div');
}
$max_questions_display = min($max_questions_display, 10000); // Augmenter à 10000
```

---

## 🔵 AMÉLIORATIONS UX

### UX #1 : Valeur par Défaut Trop Basse

**Fichier** : `questions_cleanup.php`  
**Ligne** : 865  
**Sévérité** : 🔵 MINEUR (UX)

#### Problème

```php
$max_questions_display = optional_param('show', 10, PARAM_INT); // Par défaut : 10 questions
```

**Problème** :
- 10 questions par défaut = très peu
- L'utilisateur clique sur "Charger les statistiques" et voit... 10 questions
- Frustration si il en a 29 000

#### Solution Proposée

```php
// Adapter la valeur par défaut selon la taille de la base
$total_questions = $globalstats->total_questions;

if ($total_questions < 100) {
    $default_show = $total_questions; // Tout afficher
} else if ($total_questions < 1000) {
    $default_show = 100;
} else if ($total_questions < 5000) {
    $default_show = 500;
} else {
    $default_show = 100; // Grande base : rester raisonnable
}

$max_questions_display = optional_param('show', $default_show, PARAM_INT);
```

---

### UX #2 : Pas de Message Explicite pour "show=10"

**Problème** :
L'utilisateur ne comprend pas pourquoi seulement 10 questions sont affichées alors qu'il en a 29 500.

#### Solution Proposée

```php
echo html_writer::start_tag('div', ['class' => 'alert alert-info']);
echo '📊 Affichage : <strong>' . min($max_questions_display, $total_questions) . ' question(s)</strong> ';
echo 'sur un total de <strong>' . number_format($total_questions, 0, ',', ' ') . '</strong>.';
echo '<br>';
echo '💡 <strong>Astuce</strong> : Utilisez les boutons ci-dessous pour afficher plus de questions, ';
echo 'ou utilisez les <strong>filtres</strong> pour affiner votre recherche.';
echo html_writer::end_tag('div');
```

---

### UX #3 : Bouton "Tout Afficher" Manquant

**Problème** :
Pas de moyen facile d'afficher TOUTES les questions sur une petite base (< 1000).

#### Solution Proposée

```php
// Ajouter un bouton "Tout" si < 2000 questions
if ($total_questions < 2000) {
    $url_all = new moodle_url('/local/question_diagnostic/questions_cleanup.php', 
                              array_merge($base_params, ['show' => $total_questions]));
    echo html_writer::link($url_all, 'Tout (' . $total_questions . ')', 
                          ['class' => $max_questions_display >= $total_questions ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-secondary']);
}
```

---

## 🟢 SIMPLIFICATIONS POSSIBLES

### SIMP #1 : Code Dupliqué pour les URLs

**Lignes** : 887-891

```php
// ❌ Répétitif
$url_10 = new moodle_url('/local/question_diagnostic/questions_cleanup.php', array_merge($base_params, ['show' => 10]));
$url_50 = new moodle_url('/local/question_diagnostic/questions_cleanup.php', array_merge($base_params, ['show' => 50]));
$url_100 = new moodle_url('/local/question_diagnostic/questions_cleanup.php', array_merge($base_params, ['show' => 100]));
...
```

#### Solution

```php
// ✅ Boucle
$show_options = [10, 50, 100, 500, 1000];
foreach ($show_options as $show_value) {
    $url = new moodle_url('/local/question_diagnostic/questions_cleanup.php', 
                          array_merge($base_params, ['show' => $show_value]));
    $is_active = ($max_questions_display == $show_value);
    echo html_writer::link($url, $show_value, 
                          ['class' => $is_active ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-secondary']);
    echo ' ';
}
```

---

### SIMP #2 : Extraction de Fonctions

**Problème** : Le fichier fait ~1600 lignes, c'est trop long.

#### Sections à Extraire

1. **Mode Test Aléatoire** (lignes 92-220)
   → Extraire vers `handle_random_test()`

2. **Mode Test Doublons Utilisés** (lignes 222-513)
   → Extraire vers `handle_used_duplicates_test()`

3. **Affichage du Tableau** (lignes 967-1200)
   → Extraire vers `render_questions_table($questions_with_stats)`

---

## 📊 RÉSUMÉ

### Bugs à Corriger en Priorité

1. 🔴 **CRITIQUE** : RAND() non-portable (lignes 98, 237)
2. 🟡 **MOYEN** : CONCAT() non-portable (ligne 231)
3. 🟡 **MOYEN** : Boucle N+1 potentielle (lignes 914-916)

### Améliorations UX Recommandées

1. 🔵 Valeur par défaut adaptative selon taille BDD
2. 🔵 Message explicite sur le nombre affiché
3. 🔵 Bouton "Tout afficher" si < 2000 questions
4. 🔵 Pagination pour grandes bases

### Simplifications Code

1. 🟢 Réduire duplication des URLs
2. 🟢 Extraire fonctions (fichier trop long)
3. 🟢 Améliorer lisibilité

---

## 🎯 PLAN D'ACTION PROPOSÉ

### Phase 1 : Bugs Critiques (URGENT)

- [ ] Remplacer `RAND()` par `$DB->sql_random()`
- [ ] Remplacer `CONCAT()` par `$DB->sql_concat()`
- [ ] Tester sur PostgreSQL

### Phase 2 : Performance (IMPORTANT)

- [ ] Analyser `get_question_stats()` pour boucle N+1
- [ ] Implémenter batching si nécessaire
- [ ] Ajouter pagination (optionnel)

### Phase 3 : UX (SOUHAITABLE)

- [ ] Valeur par défaut adaptative
- [ ] Message explicite sur affichage
- [ ] Bouton "Tout afficher"

### Phase 4 : Refactoring (À TERME)

- [ ] Extraire fonctions
- [ ] Réduire duplication
- [ ] Améliorer structure du code

---

**Document créé le** : 10 octobre 2025  
**Prochaine révision** : Après implémentation Phase 1

