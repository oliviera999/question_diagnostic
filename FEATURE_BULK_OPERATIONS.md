# 🚀 Fonctionnalité : Opérations par Lot sur les Catégories

## 📋 Résumé

Les opérations par lot permettent de gérer plusieurs catégories simultanément via une interface intuitive et moderne.

## ✨ Fonctionnalités Implémentées

### 1. **Sélection Multiple**
- ✅ Cases à cocher sur chaque ligne du tableau
- ✅ Case "Tout sélectionner" dans l'en-tête
- ✅ Mise en surbrillance visuelle des lignes sélectionnées
- ✅ Compteur en temps réel des catégories sélectionnées

### 2. **Barre d'Actions Groupées**

La barre d'actions s'affiche automatiquement dès qu'une ou plusieurs catégories sont sélectionnées.

**Design:**
- 🎨 Fond dégradé violet moderne (gradient #667eea → #764ba2)
- ⚡ Animation de glissement fluide lors de l'apparition
- 📱 Entièrement responsive (adapté mobile/tablette)
- 💫 Effets de survol avec élévation des boutons

**Actions disponibles:**

#### 🗑️ Suppression par Lot
- Supprime toutes les catégories sélectionnées
- Validation automatique : seules les catégories vides peuvent être supprimées
- Page de confirmation avant suppression
- Rapport détaillé : nombre de suppressions réussies + liste des erreurs

#### 📤 Export par Lot
- Exporte uniquement les catégories sélectionnées au format CSV
- Nom de fichier avec horodatage : `categories_questions_selection_YYYY-MM-DD_HH-mm-ss.csv`
- Format UTF-8 avec BOM (compatible Excel)
- Colonnes : ID, Nom, Contexte, Parent, Questions visibles, Questions totales, Sous-catégories, Statut

#### ❌ Annuler la Sélection
- Désélectionne toutes les catégories en un clic
- Masque automatiquement la barre d'actions
- Réinitialise l'état de sélection

### 3. **Expérience Utilisateur**

**Feedback Visuel:**
- Lignes sélectionnées en bleu clair (`#cfe2ff`)
- Compteur de sélection en gras et blanc avec ombre portée
- Icônes emoji pour une meilleure lisibilité
- Tooltips explicatifs sur les boutons

**Responsive Design:**
- Sur mobile : boutons empilés verticalement pleine largeur
- Sur tablette : disposition flex adaptative
- Sur desktop : disposition horizontale optimisée

**Animations:**
- Apparition fluide de la barre (slideDown 0.3s)
- Survol des boutons : élévation avec ombre portée
- Transitions douces sur tous les éléments interactifs

## 🔧 Fichiers Modifiés

### 1. `categories.php`
**Changements:**
- ✅ Correction du bug d'affichage de la barre d'actions (ligne 176)
- ✅ Ajout de l'icône 📋 pour identifier la sélection
- ✅ Ajout des boutons Export et Annuler
- ✅ Amélioration de la structure HTML avec conteneur séparé pour les boutons

### 2. `styles/main.css`
**Changements:**
- ✅ Refonte complète du style `.qd-bulk-actions`
- ✅ Gradient moderne violet/violet foncé
- ✅ Animation @keyframes slideDown
- ✅ Style `.qd-bulk-actions-buttons` pour la disposition des boutons
- ✅ Effets de survol avec transform et box-shadow
- ✅ Media queries pour le responsive design

### 3. `scripts/main.js`
**Changements:**
- ✅ Gestionnaire d'événements pour le bouton Export
- ✅ Gestionnaire d'événements pour le bouton Annuler
- ✅ Fonction de désélection complète avec réinitialisation de l'état
- ✅ Construction d'URL avec paramètres pour l'export filtré

### 4. `actions/export.php`
**Changements:**
- ✅ Support du paramètre `ids` pour filtrer les catégories à exporter
- ✅ Parsing et validation des IDs fournis
- ✅ Filtrage des catégories selon la sélection
- ✅ Nom de fichier dynamique selon le contexte (avec ou sans sélection)

## 📸 Captures d'Écran Attendues

### État Initial (aucune sélection)
```
[Filtres de recherche]
[Tableau des catégories avec checkboxes]
```

### Avec Sélection Active
```
┌────────────────────────────────────────────────────────────────┐
│ 📋 5 catégorie(s) sélectionnée(s)                              │
│                                                                 │
│ [🗑️ Supprimer la sélection] [📤 Exporter] [❌ Annuler]       │
└────────────────────────────────────────────────────────────────┘

[Tableau avec 5 lignes surlignées en bleu]
```

## 🧪 Tests Recommandés

### Test 1 : Sélection Simple
1. Cocher 2-3 catégories
2. ✅ Vérifier que la barre apparaît avec animation
3. ✅ Vérifier que le compteur affiche le bon nombre

### Test 2 : Suppression par Lot
1. Sélectionner uniquement des catégories vides
2. Cliquer sur "Supprimer la sélection"
3. ✅ Vérifier la page de confirmation
4. ✅ Confirmer et vérifier le message de succès
5. ✅ Vérifier que les catégories ont été supprimées

### Test 3 : Export par Lot
1. Sélectionner 5 catégories variées
2. Cliquer sur "Exporter la sélection"
3. ✅ Vérifier le téléchargement du fichier CSV
4. ✅ Ouvrir le CSV et vérifier que seules les 5 catégories sont présentes

### Test 4 : Annulation
1. Sélectionner plusieurs catégories
2. Cliquer sur "Annuler"
3. ✅ Vérifier que toutes les cases sont décochées
4. ✅ Vérifier que la barre disparaît
5. ✅ Vérifier que les lignes ne sont plus surlignées

### Test 5 : Tout Sélectionner
1. Cliquer sur la case "Tout sélectionner" dans l'en-tête
2. ✅ Vérifier que toutes les catégories visibles sont cochées
3. ✅ Vérifier le compteur (doit afficher le total)
4. Cliquer à nouveau pour désélectionner
5. ✅ Vérifier que la barre disparaît

### Test 6 : Responsive
1. Ouvrir en mode mobile (< 768px)
2. Sélectionner des catégories
3. ✅ Vérifier que les boutons sont empilés verticalement
4. ✅ Vérifier que les boutons occupent toute la largeur

### Test 7 : Gestion d'Erreurs
1. Sélectionner un mélange de catégories vides et non-vides
2. Tenter la suppression
3. ✅ Vérifier le message d'erreur détaillé
4. ✅ Vérifier que seules les catégories vides ont été supprimées

## 🔒 Sécurité

- ✅ Toutes les actions nécessitent `sesskey` (protection CSRF)
- ✅ Vérification `is_siteadmin()` sur toutes les actions
- ✅ Validation stricte des IDs (filtrage + cast en entier)
- ✅ Vérification côté serveur avant suppression (catégorie vide)

## 🎯 Prochaines Améliorations Possibles

1. **Déplacement par Lot** : Déplacer plusieurs catégories vers un nouveau parent
2. **Fusion Multiple** : Fusionner plusieurs catégories vers une destination
3. **Tags/Favoris** : Marquer des catégories pour y accéder rapidement
4. **Actions Conditionnelles** : Afficher/masquer certains boutons selon le type de catégories sélectionnées
5. **Prévisualisation** : Afficher un résumé avant confirmation de suppression
6. **Undo/Redo** : Possibilité d'annuler les dernières actions

## 📚 Utilisation

### Pour l'utilisateur final

1. Accédez à la page "Gestion des Catégories"
2. Cochez une ou plusieurs catégories via les cases à cocher
3. La barre d'actions apparaît automatiquement
4. Choisissez l'action souhaitée :
   - **Supprimer** : Supprime les catégories vides sélectionnées
   - **Exporter** : Télécharge un CSV des catégories sélectionnées
   - **Annuler** : Désélectionne tout

### Pour le développeur

**Ajouter une nouvelle action par lot :**

1. Ajouter un bouton dans `categories.php` :
```php
echo html_writer::tag('button', '🔧 Nouvelle Action', [
    'id' => 'bulk-newaction-btn',
    'class' => 'btn btn-warning',
    'title' => 'Description de l\'action'
]);
```

2. Ajouter le gestionnaire d'événements dans `scripts/main.js` :
```javascript
const newActionBtn = document.getElementById('bulk-newaction-btn');
if (newActionBtn) {
    newActionBtn.addEventListener('click', function() {
        if (state.selectedCategories.size === 0) {
            alert('Veuillez sélectionner au moins une catégorie.');
            return;
        }
        const ids = Array.from(state.selectedCategories).join(',');
        const url = M.cfg.wwwroot + '/local/question_diagnostic/actions/newaction.php?ids=' + ids + '&sesskey=' + M.cfg.sesskey;
        window.location.href = url;
    });
}
```

3. Créer le fichier d'action `actions/newaction.php` :
```php
<?php
require_once(__DIR__ . '/../../../config.php');
require_login();
require_sesskey();

if (!is_siteadmin()) {
    print_error('accessdenied', 'admin');
}

$ids = optional_param('ids', '', PARAM_TEXT);
$selectedIds = array_filter(array_map('intval', explode(',', $ids)));

// Traiter l'action ici

$returnurl = new moodle_url('/local/question_diagnostic/categories.php');
redirect($returnurl, 'Action effectuée avec succès.', null, \core\output\notification::NOTIFY_SUCCESS);
```

## 🐛 Bugs Corrigés

### Bug #1 : Barre d'actions invisible
**Problème :** La barre d'actions ne s'affichait jamais, même avec des catégories sélectionnées.

**Cause :** Ligne 176 de `categories.php`, l'ID `selected-count` était passé dans un tableau séparé au lieu d'être fusionné avec les autres attributs :
```php
// ❌ Avant (incorrect)
echo html_writer::tag('span', '', ['class' => 'qd-selected-count'], ['id' => 'selected-count']);
```

**Solution :** Fusion des attributs dans un seul tableau :
```php
// ✅ Après (correct)
echo html_writer::tag('span', '', ['class' => 'qd-selected-count', 'id' => 'selected-count']);
```

**Impact :** Le JavaScript ne trouvait pas l'élément par `getElementById('selected-count')`, empêchant la mise à jour du compteur et l'affichage de la barre.

## 📝 Notes de Version

**Version 1.2.0** - Opérations par Lot sur Catégories
- ✅ Correction du bug d'affichage de la barre d'actions
- ✅ Refonte complète du design de la barre (gradient moderne)
- ✅ Ajout de l'export par lot
- ✅ Ajout du bouton d'annulation
- ✅ Amélioration du responsive design
- ✅ Animations fluides
- ✅ Support du filtrage dans l'export

---

**Développé pour Moodle Question Diagnostic Tool**  
*Testé sur Moodle 4.5+*

