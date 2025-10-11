# 📋 TODOs Restants après Audit v1.9.27

Ce document liste toutes les améliorations, optimisations et fonctionnalités identifiées lors de l'audit complet qui n'ont **pas encore été implémentées**.

**Date** : 10 Octobre 2025  
**Après version** : v1.9.27  

---

## 🔥 URGENT (À faire dans les 2 prochaines semaines)

### 1. ⚠️ Unifier la définition de "doublon"

**Problème** :  
Actuellement, 3 définitions différentes existent :
- `find_question_duplicates()` : Similarité 85% (nom + texte)
- `find_exact_duplicates()` : Nom + type + texte exact
- `can_delete_questions_batch()` : Nom + type SEULEMENT

**Impact** :  
Résultats incohérents selon la page utilisée. L'utilisateur est confus.

**Solution recommandée** :
1. Choisir UNE définition (recommandation : nom + type + hash du texte)
2. Créer une méthode `is_duplicate($q1, $q2)` centralisée
3. Utiliser partout cette méthode
4. Documenter clairement ce qu'est un "doublon"

**Fichiers à modifier** :
- `classes/question_analyzer.php` (3 méthodes)
- Documentation utilisateur

**Estimation** : 2-3 heures

---

### 2. ⚠️ Corriger le lien vers DATABASE_IMPACT.md

**Problème** :  
```php
// index.php ligne 49
echo html_writer::link(
    new moodle_url('/local/question_diagnostic/DATABASE_IMPACT.md'),
    'documentation DATABASE_IMPACT.md',
    ['target' => '_blank']
);
```

Les fichiers .md ne sont pas servis par le serveur web → lien mort.

**Impact** :  
L'utilisateur clique sur le lien mais obtient une erreur 404.

**Solution recommandée** :
1. **Option A (rapide)** : Créer `database_impact.php` qui affiche le contenu du .md en HTML
2. **Option B (meilleure)** : Créer une vraie page d'aide intégrée avec navigation
3. **Option C (temporaire)** : Supprimer le lien et mettre le texte directement

**Fichiers à modifier** :
- `index.php`
- `categories.php`
- Créer `help_database_impact.php` (option A)

**Estimation** : 1-2 heures

---

### 3. ⚠️ Ajouter limite sur export CSV

**Problème** :  
```php
// actions/export.php
// Aucune limite sur le nombre de lignes à exporter
$categories = category_manager::get_all_categories_with_stats();  // Peut être 10 000+ !
```

**Impact** :  
- Timeout PHP sur grandes bases
- Out of memory
- Téléchargement de fichiers énormes (>50 MB)

**Solution recommandée** :
```php
define('MAX_EXPORT_ROWS', 5000);

if (count($categories) > MAX_EXPORT_ROWS) {
    // Proposer export par batch ou filtre
}
```

**Fichiers à modifier** :
- `actions/export.php`

**Estimation** : 1 heure

---

### 4. ⚠️ Utiliser la nouvelle fonction `local_question_diagnostic_get_used_question_ids()`

**Problème** :  
La fonction a été créée mais les 6 occurrences existantes n'utilisent pas encore cette fonction.

**Impact** :  
Le code dupliqué existe toujours, la fonction n'est pas utilisée.

**Solution** :  
Remplacer les 6 occurrences par des appels à la nouvelle fonction.

**Fichiers à modifier** :
- `questions_cleanup.php` (lignes 242-299)
- `classes/question_analyzer.php` (5 méthodes)

**Estimation** : 2 heures (avec tests)

---

## ⚡ HAUTE PRIORITÉ (À faire dans le mois)

### 5. ⚠️ Implémenter pagination côté serveur

**Problème** :  
Actuellement, pagination uniquement côté client (JavaScript).  
Sur 30 000 questions, toutes sont chargées en PHP puis filtrées en JS.

**Impact** :  
- Timeout sur chargement initial
- Mémoire élevée
- Lenteur navigateur

**Solution recommandée** :
```php
// Ajouter paramètres d'URL
$page = optional_param('page', 1, PARAM_INT);
$per_page = optional_param('per_page', 50, PARAM_INT);
$offset = ($page - 1) * $per_page;

$questions = $DB->get_records('question', null, 'id DESC', '*', $offset, $per_page);
```

**Fichiers à modifier** :
- `questions_cleanup.php`
- `categories.php`
- `scripts/main.js` (supprimer pagination client)

**Estimation** : 4-6 heures

---

### 6. ⚠️ Ajouter transactions SQL pour les fusions

**Problème** :  
```php
// category_manager.php ligne 490
public static function merge_categories($sourceid, $destid) {
    // Déplacer les questions
    $DB->execute($sql, ...);
    
    // Déplacer les sous-catégories (peut échouer)
    foreach ($subcats as $subcat) {
        $DB->update_record(...);  // Si erreur ici, questions déjà déplacées !
    }
    
    // Supprimer la source (peut échouer)
    $DB->delete_records(...);
}
```

**Impact** :  
Si une étape échoue, la base est dans un état incohérent.

**Solution recommandée** :
```php
public static function merge_categories($sourceid, $destid) {
    global $DB;
    
    $transaction = $DB->start_delegated_transaction();
    
    try {
        // Toutes les opérations
        $DB->execute(...);
        $DB->update_record(...);
        $DB->delete_records(...);
        
        $transaction->allow_commit();
        return true;
    } catch (Exception $e) {
        $transaction->rollback($e);
        return "Erreur : " . $e->getMessage();
    }
}
```

**Fichiers à modifier** :
- `classes/category_manager.php` (méthode `merge_categories`)

**Estimation** : 1-2 heures

---

### 7. ⚠️ Implémenter système de logs d'audit

**Problème** :  
Aucune trace des modifications effectuées.  
Impossible de savoir qui a supprimé quoi et quand.

**Impact** :  
- Pas de traçabilité
- Impossible de déboguer les problèmes
- Non conforme pour certains audits de sécurité

**Solution recommandée** :
```php
// Créer nouvelle table mdl_local_qd_audit_log
// Structure : id, userid, action, target_type, target_id, details, timecreated

class audit_logger {
    public static function log($action, $target_type, $target_id, $details = []) {
        global $DB, $USER;
        
        $record = (object)[
            'userid' => $USER->id,
            'action' => $action,  // 'delete', 'merge', 'move', 'export'
            'target_type' => $target_type,  // 'category', 'question'
            'target_id' => $target_id,
            'details' => json_encode($details),
            'timecreated' => time()
        ];
        
        $DB->insert_record('local_qd_audit_log', $record);
    }
}
```

**Fichiers à créer** :
- `classes/audit_logger.php`
- `db/install.xml` (définition table)
- `audit_log.php` (interface de consultation)

**Estimation** : 6-8 heures

---

## 📋 MOYENNE PRIORITÉ (À faire dans les 3 mois)

### 8. ⚠️ Organiser les 63 fichiers .md

**Problème** :  
Tous les .md sont à la racine → Navigation difficile.

**Solution** :
```
/docs
  /features       -> FEATURE_*.md
  /bugfixes       -> BUGFIX_*.md, BUGS_*.md
  /guides         -> QUICKSTART*.md, GUIDE_*.md
  /releases       -> VERSION_*.md, RELEASE_*.md
  /archive        -> Anciens RESUME_*.md
README.md         -> À la racine
CHANGELOG.md      -> À la racine
INSTALLATION.md   -> À la racine
```

**Estimation** : 2 heures (automatisable avec script)

---

### 9. ⚠️ Ajouter tests unitaires (PHPUnit)

**Problème** :  
Aucun test automatisé → Risque de régression.

**Solution recommandée** :
```php
// tests/category_manager_test.php
class category_manager_test extends advanced_testcase {
    
    public function test_delete_category_with_questions() {
        // Arrange
        $category = $this->create_test_category();
        $question = $this->create_test_question($category);
        
        // Act
        $result = category_manager::delete_category($category->id);
        
        // Assert
        $this->assertNotTrue($result);
        $this->assertContains('question', $result);
    }
}
```

**Tests à créer** :
- `category_manager_test.php` (15+ tests)
- `question_analyzer_test.php` (20+ tests)
- `question_link_checker_test.php` (10+ tests)
- `cache_manager_test.php` (8+ tests)

**Estimation** : 16-20 heures

---

### 10. ⚠️ Implémenter tâche planifiée (scheduled task)

**Problème** :  
Certaines opérations sont trop lourdes pour le web (scan complet des liens).

**Solution recommandée** :
```php
// classes/task/scan_broken_links.php
class scan_broken_links extends \core\task\scheduled_task {
    
    public function get_name() {
        return get_string('task_scan_broken_links', 'local_question_diagnostic');
    }
    
    public function execute() {
        // Scanner toutes les questions (sans limite)
        $broken = question_link_checker::get_questions_with_broken_links(false, 0);
        
        // Envoyer un rapport par email à l'admin
        $this->send_report_email($broken);
    }
}
```

**Tâches planifiées à créer** :
1. **scan_broken_links** : Scanner tous les liens (quotidien)
2. **cleanup_empty_categories** : Nettoyer catégories vides (hebdomadaire)
3. **generate_stats_report** : Rapport statistiques (mensuel)

**Fichiers à créer** :
- `classes/task/scan_broken_links.php`
- `classes/task/cleanup_empty_categories.php`
- `classes/task/generate_stats_report.php`
- `db/tasks.php`
- Chaînes de langue associées

**Estimation** : 8-12 heures

---

### 11. ⚠️ Clarifier politique de compatibilité Moodle

**Problème** :  
Contradiction entre les sources :
- `README.md` : "Moodle 3.9+"
- `.cursorrules` : "Moodle 4.5 CIBLE"
- Code : Fallbacks pour 3.x, 4.0, 4.1-4.4, 4.5+

**Impact** :  
Code legacy maintenu sans raison claire.

**Actions** :
1. **Décider** : Supporter vraiment 3.9+ ou 4.5+ uniquement ?
2. **Si 4.5+ uniquement** : Supprimer tous les fallbacks
3. **Si 3.9+** : Documenter clairement chaque fallback
4. **Mettre à jour** README, version.php, documentation

**Estimation** : 3-4 heures (+ tests sur anciennes versions)

---

### 12. ⚠️ Supprimer les méthodes fallback inutiles

**Problème** :  
Si on décide de supporter uniquement Moodle 4.5+ :

**Code à supprimer** :
```php
// Dans question_analyzer.php
if (isset($columns['questionbankentryid'])) {
    // Moodle 4.1+ : utilise questionbankentryid
    // ... CODE À SUPPRIMER si 4.5+ uniquement
} else if (isset($columns['questionid'])) {
    // Moodle 3.x/4.0 : utilise questionid directement
    // ... CODE À SUPPRIMER si 4.5+ uniquement
} else {
    // Moodle 4.5+ : Nouvelle architecture
    // ... GARDER UNIQUEMENT CETTE BRANCHE
}
```

**Gain** :  
~200 lignes de code en moins, maintenance simplifiée.

**Estimation** : 4-6 heures (identifier toutes les occurrences + tests)

---

## 🎨 BASSE PRIORITÉ (Améliorations UX)

### 13. ⚠️ Implémenter vraie pagination côté client

**Description** :  
Variables `currentPage` et `itemsPerPage` ont été supprimées mais pagination jamais implémentée.

**Solution** :
```javascript
function paginate(items, page, perPage) {
    const start = (page - 1) * perPage;
    const end = start + perPage;
    return items.slice(start, end);
}

function renderPagination(totalItems, currentPage, perPage) {
    // Créer boutons < 1 2 3 4 5 >
}
```

**Estimation** : 3-4 heures

---

### 14. ⚠️ Ajouter barres de progression

**Description** :  
Pour toutes les opérations > 5 secondes :
- Chargement des questions
- Suppression en masse
- Export CSV
- Scan des liens cassés

**Solution** :
```javascript
// Utiliser l'API Fetch avec suivi de progression
async function bulkDelete(ids) {
    const total = ids.length;
    
    for (let i = 0; i < total; i++) {
        await deleteOne(ids[i]);
        updateProgress((i + 1) / total * 100);
    }
}
```

**Estimation** : 6-8 heures (avec backend AJAX)

---

### 15. ⚠️ Créer page d'aide HTML

**Description** :  
Au lieu de liens vers .md, créer une vraie page web.

**Structure** :
```
/help.php?topic=database_impact
/help.php?topic=category_protection
/help.php?topic=question_deletion
```

**Estimation** : 4-6 heures

---

### 16. ⚠️ Ajouter action "move" dans l'interface

**Problème** :  
Le fichier `actions/move.php` existe mais aucun bouton ne l'appelle !

**Solution** :
```php
// Dans categories.php, ajouter après le bouton Fusionner
echo html_writer::tag('a', '📤 Déplacer', [
    'href' => '#',
    'class' => 'qd-btn qd-btn-move move-btn',
    'data-id' => $cat->id,
    'data-name' => format_string($cat->name)
]);
```

**Estimation** : 2-3 heures (interface + tests)

---

### 17. ⚠️ Compléter réparation automatique des liens cassés

**Description** :  
Actuellement, seule la suppression est disponible.

**Fonctionnalités manquantes** :
1. Recherche intelligente de fichiers similaires (par contenthash, taille)
2. Interface de remplacement avec drag & drop
3. Prévisualisation du fichier avant/après
4. Logs de toutes les réparations

**Solution recommandée** :
```php
// Nouvelle méthode
public static function replace_broken_file($questionid, $field, $old_url, $new_file) {
    // 1. Uploader le nouveau fichier
    // 2. Remplacer l'URL dans le texte
    // 3. Logger l'action
    // 4. Purger le cache
}
```

**Fichiers à modifier** :
- `classes/question_link_checker.php`
- `broken_links.php` (modal amélioré)
- Nouvelle page `replace_file.php`

**Estimation** : 12-16 heures

---

## 🔬 AMÉLIORATIONS AVANCÉES (Long terme)

### 18. ⚠️ Créer API REST

**Description** :  
Exposer les fonctionnalités via API pour intégration externe.

**Endpoints** :
```
GET  /webservice/rest/server.php?wsfunction=local_qd_get_stats
GET  /webservice/rest/server.php?wsfunction=local_qd_get_categories
POST /webservice/rest/server.php?wsfunction=local_qd_delete_category
```

**Fichiers à créer** :
- `db/services.php`
- `classes/external/*.php` (classes de service)

**Estimation** : 16-20 heures

---

### 19. ⚠️ Implémenter système de permissions granulaires

**Description** :  
Remplacer `is_siteadmin()` par capabilities Moodle.

**Capabilities à créer** :
```php
$capabilities = [
    'local/question_diagnostic:view' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => ['manager' => CAP_ALLOW]
    ],
    'local/question_diagnostic:delete' => [
        'riskbitmask' => RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => ['manager' => CAP_PREVENT]
    ]
];
```

**Fichiers à créer** :
- `db/access.php`
- Mettre à jour tous les fichiers pour utiliser `has_capability()`

**Estimation** : 8-12 heures

---

### 20. ⚠️ Créer interface de monitoring

**Description** :  
Page dédiée pour voir :
- Opérations en cours
- Historique des actions
- Statistiques d'utilisation du plugin
- Santé des caches

**Fichier à créer** :
- `monitoring.php`

**Estimation** : 8-10 heures

---

## 🧪 TESTS ET QUALITÉ

### 21. ⚠️ Audit de sécurité complet

**Actions** :
1. Scanner avec SonarQube
2. Vérifier toutes les entrées utilisateur
3. Tester les injections SQL (même via API $DB)
4. Vérifier les XSS
5. Tester les CSRF

**Estimation** : 4-6 heures

---

### 22. ⚠️ Tests de performance sur vraie base

**Actions** :
1. Tester sur base avec 50 000+ questions
2. Mesurer tous les temps de chargement
3. Identifier les goulots d'étranglement
4. Optimiser les requêtes lentes

**Estimation** : 6-8 heures

---

### 23. ⚠️ Tests de compatibilité multi-versions

**Actions** :
1. Installer Moodle 3.9, 4.0, 4.1, 4.3, 4.4, 4.5
2. Tester le plugin sur chaque version
3. Documenter les différences de comportement
4. Corriger les bugs spécifiques

**Estimation** : 12-16 heures

---

## 📊 Estimation Globale

| Priorité | Nombre de TODOs | Temps Estimé |
|----------|-----------------|--------------|
| URGENT | 4 | 8-12 heures |
| HAUTE | 3 | 16-24 heures |
| MOYENNE | 5 | 31-42 heures |
| BASSE | 5 | 31-41 heures |
| AVANCÉ | 6 | 54-72 heures |
| **TOTAL** | **23** | **140-191 heures** |

---

## 🎯 Roadmap Recommandée

### Phase 1 : Stabilisation (2-3 semaines)
- TODOs URGENT (1-4)
- Tests de base
- Documentation mise à jour

### Phase 2 : Performance (1 mois)
- TODOs HAUTE PRIORITÉ (5-7)
- Optimisations identifiées
- Tests de charge

### Phase 3 : Qualité (2 mois)
- TODOs MOYENNE PRIORITÉ (8-12)
- Tests unitaires complets
- Refactoring architecture

### Phase 4 : Évolution (3-6 mois)
- TODOs BASSE et AVANCÉ (13-23)
- Nouvelles fonctionnalités
- API et intégrations

---

## 📝 Notes

- Toutes les estimations sont pour un développeur expérimenté en Moodle
- Les temps incluent développement + tests + documentation
- Certains TODOs peuvent être faits en parallèle
- Prioriser selon les besoins réels de votre installation

---

**Document créé le** : 10 Octobre 2025  
**À mettre à jour** : Après chaque version avec TODOs complétés

