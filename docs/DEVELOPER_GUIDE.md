# 🛠️ Guide du Développeur - Plugin Question Diagnostic

**Version** : v1.9.33  
**Public cible** : Développeurs Moodle souhaitant contribuer ou étendre le plugin

---

## 📋 Table des Matières

1. [Architecture du Plugin](#architecture)
2. [Standards de Développement](#standards)
3. [Structure des Fichiers](#structure)
4. [Composants Principaux](#composants)
5. [Créer une Nouvelle Action](#nouvelle-action)
6. [Ajouter une Fonctionnalité](#nouvelle-fonctionnalite)
7. [Tests et Validation](#tests)
8. [Workflow de Contribution](#contribution)

---

## 🏗️ Architecture du Plugin {#architecture}

### Vue d'Ensemble

Le plugin suit une **architecture MVC modifiée** adaptée à Moodle :

```
┌─────────────────────────────────────────────────────────┐
│                     INTERFACE WEB                        │
│  index.php, categories.php, questions_cleanup.php        │
└─────────────────┬───────────────────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────────────────┐
│                   BUSINESS LOGIC                         │
│  classes/category_manager.php                            │
│  classes/question_analyzer.php                           │
│  classes/question_link_checker.php                       │
│  classes/cache_manager.php                               │
└─────────────────┬───────────────────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────────────────┐
│                    DATA LAYER                            │
│  Moodle $DB API → MySQL/PostgreSQL                       │
│  Moodle Cache API → Application/Session cache            │
└─────────────────────────────────────────────────────────┘
```

### Principes Architecturaux

1. **Séparation des responsabilités** : 
   - Pages = Affichage + Contrôle
   - Classes = Logique métier
   - Actions = Traitement des formulaires

2. **Stateless** : Pas de session PHP, utilisation des caches Moodle

3. **API-first** : Toute logique dans les classes, réutilisable

4. **Non-intrusif** : Aucune modification de tables Moodle existantes

---

## 📏 Standards de Développement {#standards}

### Règles Obligatoires

#### 1. Respect des Standards Moodle

```php
// ✅ BON - Style Moodle
if ($condition) {
    $result = $DB->get_records('table', ['field' => $value]);
}

// ❌ MAUVAIS - Non conforme
if($condition){
    $result=mysqli_query($conn,"SELECT * FROM mdl_table");
}
```

**Documentation** : [Moodle Coding Style](https://moodledev.io/general/development/policies/codingstyle)

#### 2. Sécurité Stricte

**Toujours inclure** :

```php
// En haut de chaque page
require_login();
require_sesskey();  // Pour toutes les actions

if (!is_siteadmin()) {
    print_error('accessdenied', 'admin');
}

// Pour les paramètres
$id = required_param('id', PARAM_INT);  // Obligatoire
$name = optional_param('name', '', PARAM_TEXT);  // Optionnel
```

**JAMAIS faire** :
- ❌ SQL brut (`SELECT`, `INSERT`, `UPDATE`)
- ❌ `$_GET`, `$_POST` directement
- ❌ Modification BDD sans confirmation
- ❌ echo de variables non échappées

#### 3. Internationalisation

```php
// ✅ BON - Chaînes traduisibles
echo get_string('my_key', 'local_question_diagnostic');

// ❌ MAUVAIS - Texte hardcodé
echo "Catégorie supprimée";
```

**Fichiers** :
- `lang/fr/local_question_diagnostic.php` : Français
- `lang/en/local_question_diagnostic.php` : Anglais

#### 4. Cache Moodle

```php
// Utiliser le CacheManager (v1.9.27+)
use local_question_diagnostic\cache_manager;

// Récupérer depuis cache
$data = cache_manager::get_cache('globalstats', 'stats');

// Stocker dans cache
cache_manager::set_cache('globalstats', 'stats', $data);

// Purger un cache spécifique
cache_manager::purge_cache('globalstats');

// Purger TOUS les caches du plugin
cache_manager::purge_all_caches();
```

#### 5. Transactions SQL (v1.9.30+)

Pour **toute opération multi-étapes** sur la BDD :

```php
$transaction = $DB->start_delegated_transaction();

try {
    // Étape 1
    $DB->execute(...);
    
    // Étape 2
    $DB->update_record(...);
    
    // ✅ Tout OK : COMMIT
    $transaction->allow_commit();
    
} catch (\Exception $e) {
    // 🔄 ROLLBACK AUTOMATIQUE
    debugging('Erreur : ' . $e->getMessage(), DEBUG_DEVELOPER);
    throw $e;
}
```

---

## 📁 Structure des Fichiers {#structure}

```
local/question_diagnostic/
├── index.php                     # Dashboard principal
├── categories.php                # Gestion catégories
├── questions_cleanup.php         # Analyse questions
├── broken_links.php              # Vérification liens
├── version.php                   # Métadonnées plugin
├── lib.php                       # Fonctions utilitaires globales
├── classes/
│   ├── base_action.php           # 🆕 v1.9.33 Classe abstraite actions
│   ├── category_manager.php      # Logique catégories
│   ├── question_analyzer.php     # Logique questions
│   ├── question_link_checker.php # Logique liens cassés
│   ├── cache_manager.php         # 🆕 v1.9.27 Gestion caches
│   └── actions/
│       └── delete_category_action.php # 🆕 v1.9.33 Exemple action OO
├── actions/
│   ├── delete.php                # Suppression catégories
│   ├── delete_question.php       # Suppression questions
│   ├── merge.php                 # Fusion catégories
│   ├── export.php                # Export CSV
│   ├── move.php                  # Déplacement catégories
│   └── delete_refactored.php     # 🆕 v1.9.33 Exemple refactorisé
├── styles/
│   └── main.css                  # Styles personnalisés (préfixe qd-)
├── scripts/
│   ├── main.js                   # JavaScript catégories
│   └── questions.js              # JavaScript questions
├── lang/
│   ├── fr/local_question_diagnostic.php
│   └── en/local_question_diagnostic.php
├── tests/                        # 🆕 v1.9.30 Tests PHPUnit
│   ├── category_manager_test.php
│   ├── question_analyzer_test.php
│   ├── lib_test.php
│   └── README.md
└── docs/                         # 🆕 v1.9.31 Documentation organisée
    ├── README.md                 # Index documentation
    ├── audits/
    ├── bugfixes/
    ├── features/
    ├── guides/
    ├── installation/
    ├── technical/
    ├── performance/
    └── releases/
```

---

## 🔧 Composants Principaux {#composants}

### 1. category_manager.php

**Responsabilité** : Gestion des catégories de questions

**Méthodes principales** :
```php
// Récupérer toutes les catégories avec stats
category_manager::get_all_categories_with_stats()

// Récupérer stats d'une catégorie
category_manager::get_category_stats($category)

// Supprimer une catégorie
category_manager::delete_category($id)

// Supprimer en masse
category_manager::delete_categories_bulk($ids)

// Fusionner deux catégories (avec transaction v1.9.30)
category_manager::merge_categories($sourceid, $destid)

// Déplacer une catégorie (avec transaction v1.9.30)
category_manager::move_category($categoryid, $newparentid)
```

**Protections implémentées** :
- ✅ Catégories "Default for..." (système Moodle)
- ✅ Catégories avec description (usage intentionnel)
- ✅ Catégories racine parent=0 (v1.9.29)

### 2. question_analyzer.php

**Responsabilité** : Analyse et gestion des questions

**Méthodes principales** :
```php
// Récupérer questions avec stats (avec pagination v1.9.30)
question_analyzer::get_all_questions_with_stats($include_duplicates, $limit, $offset)

// Stats d'une question
question_analyzer::get_question_stats($question, $usage_map, $duplicates_map)

// Détecter doublons (v1.9.28 : définition unique nom+type)
question_analyzer::find_exact_duplicates()

// Vérifier si deux questions sont doublons
question_analyzer::are_duplicates($q1, $q2)

// Récupérer doublons utilisés (avec pagination v1.9.30)
question_analyzer::get_used_duplicates_questions($limit, $offset)

// Vérifier si question supprimable
question_analyzer::can_delete_questions_batch($questionids)
```

**Règles de suppression** :
- ❌ **INTERDITE** : Question utilisée dans quiz
- ❌ **INTERDITE** : Question avec tentatives
- ❌ **INTERDITE** : Question unique (pas de doublon)
- ✅ **AUTORISÉE** : Question en doublon ET inutilisée

### 3. cache_manager.php (v1.9.27)

**Responsabilité** : Gestion centralisée des caches

**Méthodes** :
```php
// Récupérer depuis cache
cache_manager::get_cache($cache_name, $key)

// Stocker dans cache
cache_manager::set_cache($cache_name, $key, $data)

// Supprimer un cache
cache_manager::delete_cache($cache_name, $key)

// Purger un cache complet
cache_manager::purge_cache($cache_name)

// Purger TOUS les caches du plugin
cache_manager::purge_all_caches()
```

**Caches disponibles** :
- `duplicates` : Map des doublons
- `globalstats` : Statistiques globales questions
- `questionusage` : Usage des questions dans quiz
- `brokenlinks` : Questions avec liens cassés

### 4. base_action.php (v1.9.33)

**Responsabilité** : Classe abstraite pour factoriser les actions

**Template Method Pattern** :
```php
// Méthode template (non modifiable)
public function execute() {
    $this->validate_parameters();
    
    if (!$this->confirmed) {
        $this->show_confirmation_page();
        return;
    }
    
    $this->perform_action(); // ← Implémenté par sous-classes
}
```

**Méthodes utilitaires** :
```php
$this->redirect_success($message);
$this->redirect_error($message);
$this->redirect_warning($message);
```

---

## ➕ Créer une Nouvelle Action {#nouvelle-action}

### Méthode Moderne (v1.9.33+) - Recommandée

**1. Créer la classe d'action** :

```php
// classes/actions/my_action.php
<?php
namespace local_question_diagnostic\actions;

use local_question_diagnostic\base_action;
use local_question_diagnostic\category_manager;

class my_action extends base_action {

    // Logique métier
    protected function perform_action() {
        if ($this->is_bulk) {
            // Traiter plusieurs items
            foreach ($this->item_ids as $id) {
                // Faire quelque chose avec $id
            }
            $this->redirect_success(count($this->item_ids) . ' éléments traités.');
        } else {
            // Traiter un seul item
            $id = $this->item_id;
            // Faire quelque chose avec $id
            $this->redirect_success('Élément traité avec succès.');
        }
    }

    // Configuration de l'action
    protected function get_action_url() {
        return new \moodle_url('/local/question_diagnostic/actions/my_action.php');
    }

    protected function get_confirmation_title() {
        return 'Confirmer l\'action';
    }

    protected function get_confirmation_heading() {
        return '⚠️ Confirmation requise';
    }

    protected function get_confirmation_message() {
        if ($this->is_bulk) {
            return "Traiter " . count($this->item_ids) . " éléments ?";
        }
        return "Traiter cet élément ?";
    }

    protected function get_confirm_button_text() {
        return 'Oui, traiter';
    }

    // Optionnel : Personnalisation
    protected function is_action_dangerous() {
        return false; // Bouton bleu au lieu de rouge
    }

    protected function has_bulk_limit() {
        return true;
    }

    protected function get_bulk_limit() {
        return 50; // Max 50 éléments à la fois
    }
}
```

**2. Créer le point d'entrée** :

```php
// actions/my_action.php
<?php
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../classes/actions/my_action.php');

use local_question_diagnostic\actions\my_action;

$action = new my_action();
$action->execute();
```

**C'est tout !** Sécurité, confirmation et redirections sont automatiques.

**Total** : ~80 lignes au lieu de 140+ (gain de -43%)

---

### Méthode Classique (Legacy)

Si vous ne voulez pas utiliser `base_action` :

```php
// actions/my_action.php
<?php
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../classes/category_manager.php');

require_login();
require_sesskey();

if (!is_siteadmin()) {
    print_error('accessdenied', 'admin');
}

$id = required_param('id', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);
$returnurl = new moodle_url('/local/question_diagnostic/index.php');

if (!$confirm) {
    // Afficher page de confirmation
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url(new moodle_url('/local/question_diagnostic/actions/my_action.php'));
    $PAGE->set_title('Confirmation');
    
    echo $OUTPUT->header();
    echo $OUTPUT->heading('⚠️ Confirmation');
    echo html_writer::tag('p', "Message de confirmation");
    
    // Formulaire POST
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => new moodle_url('/local/question_diagnostic/actions/my_action.php')
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $id]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => '1']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => 'Confirmer', 'class' => 'btn btn-danger']);
    echo html_writer::end_tag('form');
    
    echo $OUTPUT->footer();
    exit;
}

// Exécuter l'action
try {
    // Faire quelque chose
    redirect($returnurl, 'Succès !', null, \core\output\notification::NOTIFY_SUCCESS);
} catch (\Exception $e) {
    redirect($returnurl, 'Erreur : ' . $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
}
```

**Total** : ~140 lignes

---

## 🆕 Ajouter une Fonctionnalité {#nouvelle-fonctionnalite}

### Exemple : Ajouter un Nouveau Type de Statistique

**1. Modifier la classe de logique** :

```php
// classes/category_manager.php

/**
 * Compte les catégories avec un nom spécifique
 * 
 * @param string $pattern Pattern de recherche
 * @return int Nombre de catégories correspondantes
 */
public static function count_categories_by_pattern($pattern) {
    global $DB;
    
    $sql = "SELECT COUNT(*) 
            FROM {question_categories}
            WHERE " . $DB->sql_like('name', ':pattern', false);
    
    return $DB->count_records_sql($sql, ['pattern' => '%' . $DB->sql_like_escape($pattern) . '%']);
}
```

**2. Ajouter aux stats globales** :

```php
// Dans get_global_stats()
$stats->custom_pattern_count = self::count_categories_by_pattern('Test');
```

**3. Ajouter la chaîne de langue** :

```php
// lang/fr/local_question_diagnostic.php
$string['custom_pattern_count'] = 'Catégories de test';

// lang/en/local_question_diagnostic.php
$string['custom_pattern_count'] = 'Test categories';
```

**4. Afficher dans l'interface** :

```php
// categories.php ou index.php
echo html_writer::start_div('qd-card');
echo html_writer::tag('h3', get_string('custom_pattern_count', 'local_question_diagnostic'));
echo html_writer::tag('div', $stats->custom_pattern_count, ['class' => 'qd-card-number']);
echo html_writer::end_div();
```

---

## 🧪 Tests et Validation {#tests}

### Tests Unitaires PHPUnit

**Créer un test** :

```php
// tests/my_feature_test.php
<?php
namespace local_question_diagnostic;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/question_diagnostic/classes/category_manager.php');

use advanced_testcase;
use category_manager;

class my_feature_test extends advanced_testcase {

    public function test_my_feature() {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Test logic
        $result = category_manager::my_method();
        
        $this->assertIsArray($result);
        $this->assertGreaterThan(0, count($result));
    }
}
```

**Exécuter les tests** :

```bash
# Tous les tests du plugin
vendor/bin/phpunit --testdox local/question_diagnostic/tests/

# Un fichier spécifique
vendor/bin/phpunit local/question_diagnostic/tests/my_feature_test.php

# Un test spécifique
vendor/bin/phpunit --filter test_my_feature local/question_diagnostic/tests/
```

### Tests Manuels

**Checklist de base** :

- [ ] Tester en tant qu'admin site
- [ ] Tester les erreurs (ID invalide, sesskey manquant)
- [ ] Tester sur petite base (<100 catégories/questions)
- [ ] Tester sur grosse base (>10k questions)
- [ ] Tester les opérations en masse
- [ ] Purger les caches et re-tester
- [ ] Vérifier les logs de debug

---

## 🔄 Workflow de Contribution {#contribution}

### 1. Préparer l'Environnement

```bash
# Fork le projet sur GitHub
# Cloner votre fork
git clone https://github.com/VOTRE-USERNAME/question_diagnostic.git

# Créer une branche
cd question_diagnostic
git checkout -b feature/ma-nouvelle-fonctionnalite
```

### 2. Développer

```bash
# Faire vos modifications
# Respecter les standards ci-dessus

# Tester localement
vendor/bin/phpunit local/question_diagnostic/tests/

# Vérifier les lints
php admin/cli/check_syntax.php local/question_diagnostic/
```

### 3. Documenter

- **Code** : Commentaires PHPDoc sur toutes les fonctions publiques
- **CHANGELOG** : Ajouter une entrée avec votre modification
- **Strings** : Ajouter les chaînes en FR et EN

### 4. Committer

```bash
# Ajouter les fichiers
git add classes/my_new_file.php
git add tests/my_new_test.php
git add lang/fr/local_question_diagnostic.php
git add lang/en/local_question_diagnostic.php
git add CHANGELOG.md

# Commit avec message descriptif
git commit -m "Ajout fonctionnalité X

- Description du problème résolu
- Solution implémentée
- Tests ajoutés
- Documentation mise à jour"
```

### 5. Pull Request

```bash
# Pousser vers votre fork
git push origin feature/ma-nouvelle-fonctionnalite

# Créer une PR sur GitHub
# Décrire :
# - Problème résolu
# - Solution technique
# - Tests effectués
# - Impact sur performance
```

---

## 🎨 Conventions CSS

### Préfixes

**Toutes les classes CSS doivent commencer par `qd-`** :

```css
/* ✅ BON */
.qd-card { ... }
.qd-table { ... }
.qd-badge-warning { ... }

/* ❌ MAUVAIS */
.card { ... }
.my-table { ... }
```

### Variables CSS

Utiliser les variables définies dans `styles/main.css` :

```css
:root {
    --qd-primary: #0f6cbf;
    --qd-success: #5cb85c;
    --qd-warning: #f0ad4e;
    --qd-danger: #d9534f;
    --qd-neutral: #6c757d;
}

/* Utilisation */
.qd-my-element {
    color: var(--qd-primary);
}
```

---

## 📚 Ressources Utiles

### Documentation Moodle

- [Moodle Developer Docs](https://moodledev.io/)
- [DB API](https://moodledev.io/docs/apis/core/dml)
- [Cache API](https://moodledev.io/docs/apis/subsystems/cache)
- [Testing Guide](https://moodledev.io/general/development/process/testing)

### Documentation du Plugin

- **[README.md](../README.md)** : Vue d'ensemble
- **[CHANGELOG.md](../CHANGELOG.md)** : Historique
- **[docs/README.md](README.md)** : Index complet documentation
- **[docs/technical/](technical/)** : Documentation technique

### Outils de Développement

- **PHPUnit** : Tests automatisés
- **Moodle Code Checker** : Vérification coding style
- **PHPStan** : Analyse statique du code
- **XDebug** : Debugging

---

## ✅ Checklist Avant Contribution

Avant de soumettre une PR :

- [ ] Code respecte le Moodle Coding Style
- [ ] Sécurité : `require_sesskey()`, `is_siteadmin()`, validation params
- [ ] Chaînes traduites (FR + EN)
- [ ] Pas de SQL brut (utiliser API $DB)
- [ ] Préfixes CSS `qd-` respectés
- [ ] Commentaires PHPDoc sur fonctions publiques
- [ ] Tests unitaires créés/mis à jour
- [ ] Tests PHPUnit passent tous (21/21)
- [ ] CHANGELOG.md mis à jour
- [ ] Version incrémentée dans version.php (si nécessaire)
- [ ] Cache purgé et re-testé
- [ ] Documentation mise à jour

---

## 🆘 Support Développeur

### Questions Fréquentes

**Q: Comment débugger le plugin ?**

```php
// Activer debug dans config.php
$CFG->debug = (E_ALL | E_STRICT);
$CFG->debugdisplay = 1;

// Ajouter des messages
debugging('Mon message de debug', DEBUG_DEVELOPER);

// Dump de variables
var_dump($ma_variable);
print_r($mon_objet);
```

**Q: Comment purger les caches ?**

```bash
# Via CLI
php admin/cli/purge_caches.php

# Via code
cache_manager::purge_all_caches();
```

**Q: Comment tester sur Moodle 4.5 ?**

1. Installer Moodle 4.5 local (Docker recommandé)
2. Installer le plugin
3. Exécuter les tests
4. Vérifier les logs

**Q: Où trouver les exemples de code ?**

- `classes/actions/delete_category_action.php` : Action OO moderne
- `classes/category_manager.php` : Gestion de données
- `tests/category_manager_test.php` : Tests PHPUnit

---

## 📞 Contact

- **GitHub** : https://github.com/oliviera999/question_diagnostic
- **Issues** : https://github.com/oliviera999/question_diagnostic/issues
- **Discussions** : https://github.com/oliviera999/question_diagnostic/discussions

---

**Version du guide** : v1.0  
**Dernière mise à jour** : 11 Octobre 2025  
**Auteur** : Équipe local_question_diagnostic  

