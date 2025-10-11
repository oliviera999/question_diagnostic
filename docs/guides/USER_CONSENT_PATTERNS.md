# 🛡️ Patterns de Consentement Utilisateur

## 🎯 Objectif

Ce document définit les **patterns obligatoires** pour obtenir le consentement de l'administrateur avant toute modification de la base de données.

**Version** : v1.0 (Octobre 2025)  
**Plugin** : local_question_diagnostic

---

## 🚨 RÈGLE FONDAMENTALE

### Principe de Base

> **AUCUNE modification de la base de données ne peut être effectuée sans confirmation explicite et informée de l'administrateur.**

Cela signifie :
- ❌ **JAMAIS** de modification directe depuis un clic
- ✅ **TOUJOURS** une page ou modal de confirmation
- ✅ **TOUJOURS** afficher ce qui va être modifié
- ✅ **TOUJOURS** permettre l'annulation
- ✅ **TOUJOURS** avertir si l'action est irréversible

---

## 📋 Flux Standard de Confirmation

### Pattern à 3 Étapes

```
┌─────────────────────────┐
│  1. Action utilisateur  │
│  (Clic sur bouton)      │
└───────────┬─────────────┘
            │
            ▼
┌─────────────────────────┐
│  2. Page/Modal de       │
│     CONFIRMATION        │
│  - Affiche les détails  │
│  - Demande validation   │
│  - Permet annulation    │
└───────────┬─────────────┘
            │
            ▼
┌─────────────────────────┐
│  3. Exécution + Feedback│
│  - Modification BDD     │
│  - Message de succès    │
│  - ou erreur            │
└─────────────────────────┘
```

---

## ✅ Exemples de Bonnes Pratiques

### Exemple 1 : Suppression d'une Catégorie (Individuelle)

#### Étape 1 : Bouton avec intention claire
```php
// Dans categories.php
echo html_writer::link(
    new moodle_url('/local/question_diagnostic/actions/delete.php', [
        'id' => $category->id,
        'sesskey' => sesskey()
    ]),
    '🗑️ ' . get_string('delete', 'local_question_diagnostic'),
    ['class' => 'qd-btn qd-btn-danger']
);
```

#### Étape 2 : Page de confirmation (actions/delete.php)
```php
<?php
require_once(__DIR__ . '/../../../config.php');

require_login();
if (!is_siteadmin()) {
    print_error('accessdenied', 'admin');
}

$id = required_param('id', PARAM_INT);
require_sesskey();

// Récupérer les informations de la catégorie
$category = $DB->get_record('question_categories', ['id' => $id], '*', MUST_EXIST);

// Si "confirm" n'est pas passé, afficher la page de confirmation
$confirm = optional_param('confirm', 0, PARAM_INT);

if (!$confirm) {
    // PAGE DE CONFIRMATION
    $PAGE->set_url(new moodle_url('/local/question_diagnostic/actions/delete.php', ['id' => $id]));
    $PAGE->set_context(context_system::instance());
    $PAGE->set_title(get_string('confirm_delete_title', 'local_question_diagnostic'));
    
    echo $OUTPUT->header();
    
    // Afficher les détails
    echo html_writer::tag('h2', get_string('confirm_delete_title', 'local_question_diagnostic'));
    echo html_writer::tag('p', get_string('confirm_delete_message', 'local_question_diagnostic'));
    
    echo html_writer::start_tag('div', ['class' => 'qd-confirmation-box']);
    echo html_writer::tag('strong', get_string('category_name', 'local_question_diagnostic') . ' : ');
    echo format_string($category->name) . '<br>';
    echo html_writer::tag('strong', get_string('category_id', 'local_question_diagnostic') . ' : ');
    echo $category->id . '<br>';
    echo html_writer::end_tag('div');
    
    // ⚠️ AVERTISSEMENT
    echo html_writer::start_tag('div', ['class' => 'alert alert-danger']);
    echo html_writer::tag('strong', '⚠️ ' . get_string('warning', 'core'));
    echo html_writer::tag('p', get_string('delete_irreversible', 'local_question_diagnostic'));
    echo html_writer::end_tag('div');
    
    // BOUTONS D'ACTION
    echo html_writer::start_tag('div', ['class' => 'qd-confirmation-buttons']);
    
    // Bouton CONFIRMER
    echo html_writer::link(
        new moodle_url('/local/question_diagnostic/actions/delete.php', [
            'id' => $id,
            'confirm' => 1,
            'sesskey' => sesskey()
        ]),
        get_string('confirm', 'core'),
        ['class' => 'btn btn-danger']
    );
    
    // Bouton ANNULER
    echo html_writer::link(
        new moodle_url('/local/question_diagnostic/categories.php'),
        get_string('cancel', 'core'),
        ['class' => 'btn btn-secondary']
    );
    
    echo html_writer::end_tag('div');
    
    echo $OUTPUT->footer();
    exit;
}

// ✅ SI CONFIRMÉ : Exécuter la suppression
try {
    $DB->delete_records('question_categories', ['id' => $id]);
    
    redirect(
        new moodle_url('/local/question_diagnostic/categories.php'),
        get_string('category_deleted_success', 'local_question_diagnostic'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
} catch (Exception $e) {
    redirect(
        new moodle_url('/local/question_diagnostic/categories.php'),
        get_string('category_deleted_error', 'local_question_diagnostic') . ' : ' . $e->getMessage(),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}
```

---

### Exemple 2 : Suppression Multiple (Bulk Delete)

#### Étape 1 : Bouton avec sélection
```javascript
// Dans scripts/main.js
document.getElementById('bulk-delete-btn').addEventListener('click', function(e) {
    e.preventDefault();
    
    const selected = state.selectedCategories;
    
    if (selected.size === 0) {
        alert(M.util.get_string('no_categories_selected', 'local_question_diagnostic'));
        return;
    }
    
    // Afficher modal de confirmation
    showBulkDeleteConfirmation(Array.from(selected));
});

function showBulkDeleteConfirmation(categoryIds) {
    const modal = document.getElementById('bulk-delete-modal');
    
    // Afficher les catégories sélectionnées
    const categoryList = document.getElementById('bulk-delete-category-list');
    categoryList.innerHTML = '';
    
    categoryIds.forEach(id => {
        const category = state.allCategories.find(cat => cat.id === id);
        const li = document.createElement('li');
        li.textContent = `${category.name} (ID: ${id})`;
        categoryList.appendChild(li);
    });
    
    // Afficher le nombre
    document.getElementById('bulk-delete-count').textContent = categoryIds.length;
    
    // Configurer le formulaire
    document.getElementById('bulk-delete-form').action = 
        '/local/question_diagnostic/actions/delete.php?sesskey=' + M.cfg.sesskey;
    
    modal.style.display = 'block';
}
```

#### Étape 2 : Modal de confirmation
```php
// Dans categories.php
echo html_writer::start_tag('div', ['id' => 'bulk-delete-modal', 'class' => 'qd-modal']);
echo html_writer::start_tag('div', ['class' => 'qd-modal-content']);

echo html_writer::tag('h2', get_string('confirm_bulk_delete', 'local_question_diagnostic'));

echo html_writer::tag('p', get_string('bulk_delete_warning', 'local_question_diagnostic', 
    html_writer::tag('strong', html_writer::tag('span', '', ['id' => 'bulk-delete-count']))));

echo html_writer::start_tag('ul', ['id' => 'bulk-delete-category-list']);
echo html_writer::end_tag('ul');

// ⚠️ AVERTISSEMENT
echo html_writer::start_tag('div', ['class' => 'alert alert-danger']);
echo '⚠️ ' . get_string('bulk_delete_irreversible', 'local_question_diagnostic');
echo html_writer::end_tag('div');

// FORMULAIRE
echo html_writer::start_tag('form', [
    'id' => 'bulk-delete-form',
    'method' => 'post'
]);

echo html_writer::start_tag('div', ['class' => 'qd-modal-buttons']);

// Bouton CONFIRMER
echo html_writer::tag('button', get_string('confirm_delete', 'local_question_diagnostic'), [
    'type' => 'submit',
    'class' => 'btn btn-danger',
    'name' => 'confirm',
    'value' => '1'
]);

// Bouton ANNULER
echo html_writer::tag('button', get_string('cancel', 'core'), [
    'type' => 'button',
    'class' => 'btn btn-secondary',
    'onclick' => "document.getElementById('bulk-delete-modal').style.display='none'"
]);

echo html_writer::end_tag('div');
echo html_writer::end_tag('form');
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');
```

---

### Exemple 3 : Fusion de Catégories

#### Étape 1 : Modal avec choix de destination
```php
// Modal de fusion avec sélection
echo html_writer::start_tag('div', ['id' => 'merge-modal', 'class' => 'qd-modal']);
echo html_writer::tag('h2', get_string('merge_categories', 'local_question_diagnostic'));

echo html_writer::tag('p', get_string('merge_description', 'local_question_diagnostic'));

// Afficher la catégorie source
echo html_writer::tag('strong', get_string('source_category', 'local_question_diagnostic'));
echo html_writer::tag('div', '', ['id' => 'merge-source-info']);

// Sélecteur de destination
echo html_writer::tag('label', get_string('destination_category', 'local_question_diagnostic'));
echo html_writer::select(
    $category_options,
    'destination',
    '',
    ['' => get_string('choose', 'core')],
    ['id' => 'merge-destination-select']
);

// ⚠️ AVERTISSEMENT
echo html_writer::start_tag('div', ['class' => 'alert alert-warning']);
echo '⚠️ ' . get_string('merge_warning', 'local_question_diagnostic');
echo html_writer::end_tag('div');

// BOUTONS
echo html_writer::link(
    '#',
    get_string('confirm_merge', 'local_question_diagnostic'),
    ['id' => 'merge-confirm-btn', 'class' => 'btn btn-primary']
);
echo html_writer::end_tag('div');
```

#### Étape 2 : Page de confirmation finale (actions/merge.php)
```php
<?php
require_once(__DIR__ . '/../../../config.php');

require_login();
if (!is_siteadmin()) {
    print_error('accessdenied', 'admin');
}

$sourceid = required_param('source', PARAM_INT);
$destid = required_param('dest', PARAM_INT);
require_sesskey();

$confirm = optional_param('confirm', 0, PARAM_INT);

// Récupérer les catégories
$source = $DB->get_record('question_categories', ['id' => $sourceid], '*', MUST_EXIST);
$dest = $DB->get_record('question_categories', ['id' => $destid], '*', MUST_EXIST);

// Compter les éléments qui seront déplacés
$questioncount = $DB->count_records('question', ['category' => $sourceid]);
$subcategorycount = $DB->count_records('question_categories', ['parent' => $sourceid]);

if (!$confirm) {
    // PAGE DE CONFIRMATION DÉTAILLÉE
    $PAGE->set_url(new moodle_url('/local/question_diagnostic/actions/merge.php', [
        'source' => $sourceid,
        'dest' => $destid
    ]));
    $PAGE->set_context(context_system::instance());
    $PAGE->set_title(get_string('confirm_merge_title', 'local_question_diagnostic'));
    
    echo $OUTPUT->header();
    
    echo html_writer::tag('h2', get_string('confirm_merge_title', 'local_question_diagnostic'));
    
    // Afficher les détails complets
    echo html_writer::start_tag('div', ['class' => 'qd-merge-details']);
    
    echo html_writer::tag('h3', get_string('source_category', 'local_question_diagnostic'));
    echo html_writer::tag('p', format_string($source->name) . ' (ID: ' . $source->id . ')');
    
    echo html_writer::tag('h3', get_string('destination_category', 'local_question_diagnostic'));
    echo html_writer::tag('p', format_string($dest->name) . ' (ID: ' . $dest->id . ')');
    
    echo html_writer::tag('h3', get_string('items_to_move', 'local_question_diagnostic'));
    echo html_writer::start_tag('ul');
    echo html_writer::tag('li', get_string('questions', 'core') . ' : ' . $questioncount);
    echo html_writer::tag('li', get_string('subcategories', 'local_question_diagnostic') . ' : ' . $subcategorycount);
    echo html_writer::end_tag('ul');
    
    echo html_writer::end_tag('div');
    
    // ⚠️ AVERTISSEMENT IRRÉVERSIBILITÉ
    echo html_writer::start_tag('div', ['class' => 'alert alert-danger']);
    echo html_writer::tag('strong', '⚠️ ' . get_string('warning', 'core'));
    echo html_writer::tag('p', get_string('merge_irreversible', 'local_question_diagnostic'));
    echo html_writer::tag('p', get_string('source_will_be_deleted', 'local_question_diagnostic'));
    echo html_writer::end_tag('div');
    
    // BOUTONS
    echo html_writer::start_tag('div', ['class' => 'qd-confirmation-buttons']);
    
    echo html_writer::link(
        new moodle_url('/local/question_diagnostic/actions/merge.php', [
            'source' => $sourceid,
            'dest' => $destid,
            'confirm' => 1,
            'sesskey' => sesskey()
        ]),
        get_string('confirm_merge', 'local_question_diagnostic'),
        ['class' => 'btn btn-danger']
    );
    
    echo html_writer::link(
        new moodle_url('/local/question_diagnostic/categories.php'),
        get_string('cancel', 'core'),
        ['class' => 'btn btn-secondary']
    );
    
    echo html_writer::end_tag('div');
    
    echo $OUTPUT->footer();
    exit;
}

// ✅ SI CONFIRMÉ : Exécuter la fusion
try {
    $DB->start_delegated_transaction();
    
    // 1. Déplacer les questions
    $DB->set_field('question', 'category', $destid, ['category' => $sourceid]);
    
    // 2. Déplacer les sous-catégories
    $DB->set_field('question_categories', 'parent', $destid, ['parent' => $sourceid]);
    
    // 3. Supprimer la catégorie source
    $DB->delete_records('question_categories', ['id' => $sourceid]);
    
    $DB->commit_delegated_transaction();
    
    redirect(
        new moodle_url('/local/question_diagnostic/categories.php'),
        get_string('merge_success', 'local_question_diagnostic', [
            'source' => format_string($source->name),
            'dest' => format_string($dest->name)
        ]),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
    
} catch (Exception $e) {
    $DB->rollback_delegated_transaction($e);
    
    redirect(
        new moodle_url('/local/question_diagnostic/categories.php'),
        get_string('merge_error', 'local_question_diagnostic') . ' : ' . $e->getMessage(),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}
```

---

## ❌ Anti-Patterns (À ÉVITER)

### Anti-Pattern 1 : Suppression directe au clic
```php
// ❌ MAUVAIS - Supprime directement sans confirmation
echo html_writer::link(
    new moodle_url('/local/question_diagnostic/delete_now.php', ['id' => $id]),
    'Supprimer'
);

// Dans delete_now.php
$id = required_param('id', PARAM_INT);
$DB->delete_records('question_categories', ['id' => $id]); // ❌ DANGEREUX !
redirect(...);
```

### Anti-Pattern 2 : Confirmation JavaScript uniquement
```javascript
// ❌ MAUVAIS - Confirmation JS peut être contournée
if (confirm('Êtes-vous sûr ?')) {
    // Appel AJAX direct sans page de confirmation serveur
    fetch('/delete.php?id=' + id);
}
```

### Anti-Pattern 3 : Pas d'information sur ce qui sera modifié
```php
// ❌ MAUVAIS - L'utilisateur ne sait pas ce qu'il va supprimer
echo 'Voulez-vous vraiment supprimer cette catégorie ?';
echo html_writer::link('?confirm=1', 'Oui'); // Aucun détail !
```

---

## 📊 Niveaux de Confirmation

### Niveau 1 : Confirmation Simple (Modal)
**Pour** : Actions peu risquées, réversibles
- Export CSV
- Changement de tri
- Changement de vue

**Exemple** : Modal JavaScript avec bouton "OK" / "Annuler"

### Niveau 2 : Confirmation Standard (Page)
**Pour** : Actions modérément risquées, irréversibles
- Suppression d'une catégorie vide
- Déplacement de catégorie

**Exemple** : Page de confirmation avec détails + boutons Confirmer/Annuler

### Niveau 3 : Confirmation Renforcée (Page + Détails)
**Pour** : Actions très risquées, irréversibles, impact important
- Suppression multiple
- Fusion de catégories
- Suppression de catégories avec sous-catégories

**Exemple** : Page de confirmation avec :
- Liste détaillée des éléments affectés
- Compteurs (X questions, Y sous-catégories)
- Avertissement visible sur l'irréversibilité
- Temps de lecture (pas de confirmation immédiate)

---

## 🎨 UI/UX des Confirmations

### Couleurs et Signaux Visuels

```css
/* Avertissement important */
.alert-danger {
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
    padding: 15px;
    margin: 15px 0;
    border-radius: 4px;
}

/* Bouton de confirmation dangereux */
.btn-danger {
    background-color: #d9534f;
    color: white;
    padding: 10px 20px;
}

/* Bouton d'annulation */
.btn-secondary {
    background-color: #6c757d;
    color: white;
    padding: 10px 20px;
}
```

### Hiérarchie Visuelle

1. **Titre clair** : "Confirmer la suppression"
2. **Détails** : Informations sur ce qui sera modifié
3. **Avertissement** : Zone rouge avec icône ⚠️
4. **Actions** : Boutons bien séparés et identifiables

---

## 🔍 Checklist de Vérification

Avant de merger une fonctionnalité, vérifier :

- [ ] ✅ Aucune modification BDD sans confirmation
- [ ] ✅ Page ou modal de confirmation présente
- [ ] ✅ Détails affichés clairement (nom, ID, compteurs)
- [ ] ✅ Avertissement d'irréversibilité si applicable
- [ ] ✅ Bouton "Annuler" clair et accessible
- [ ] ✅ Protection CSRF (sesskey) sur l'action finale
- [ ] ✅ Message de feedback après l'action
- [ ] ✅ Gestion des erreurs (try/catch)
- [ ] ✅ Transaction BDD si multiple opérations
- [ ] ✅ Chaînes de langue en FR et EN

---

## 📚 Chaînes de Langue Nécessaires

```php
// lang/fr/local_question_diagnostic.php
$string['confirm_delete_title'] = 'Confirmer la suppression';
$string['confirm_delete_message'] = 'Êtes-vous sûr de vouloir supprimer cette catégorie ?';
$string['delete_irreversible'] = 'Cette action est irréversible. La catégorie sera définitivement supprimée.';
$string['confirm'] = 'Confirmer';
$string['cancel'] = 'Annuler';
$string['category_deleted_success'] = 'Catégorie supprimée avec succès';
$string['category_deleted_error'] = 'Erreur lors de la suppression';

// Fusion
$string['confirm_merge_title'] = 'Confirmer la fusion';
$string['merge_irreversible'] = 'Cette action est irréversible.';
$string['source_will_be_deleted'] = 'La catégorie source sera supprimée après la fusion.';
$string['merge_success'] = 'Les catégories "{$a->source}" et "{$a->dest}" ont été fusionnées avec succès.';

// Bulk
$string['confirm_bulk_delete'] = 'Confirmer la suppression multiple';
$string['bulk_delete_warning'] = 'Vous êtes sur le point de supprimer {$a} catégories.';
$string['bulk_delete_irreversible'] = 'Cette action est irréversible et supprimera toutes les catégories sélectionnées.';
```

---

## 🚀 Résumé

### Les 3 Commandements

1. **Tu ne modifieras point** la BDD sans confirmation explicite
2. **Tu afficheras** clairement ce qui sera modifié
3. **Tu permettras toujours** l'annulation

### En cas de doute

**Demande-toi** :
- "L'utilisateur comprend-il ce qui va se passer ?"
- "Peut-il annuler facilement ?"
- "Est-il averti si c'est irréversible ?"

**Si une seule réponse est "non"**, améliore le flux de confirmation.

---

**Version** : v1.0 (Octobre 2025)  
**Maintenu par** : Équipe de développement local_question_diagnostic

⚠️ **Ce document doit être respecté pour toute nouvelle fonctionnalité touchant la base de données.**

