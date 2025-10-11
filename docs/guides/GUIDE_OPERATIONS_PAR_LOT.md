# 📚 Guide : Comment Utiliser les Opérations par Lot

## 🎯 Objectif

Ce guide explique comment utiliser les **opérations par lot** dans la page "Gestion des Catégories" pour gérer plusieurs catégories simultanément.

## ✅ Ce qui a été amélioré (aujourd'hui)

### 1. **Aide visuelle ajoutée**
Une bannière bleue explique maintenant comment utiliser les opérations par lot avant le tableau des catégories.

### 2. **Checkboxes plus visibles**
- Les cases à cocher sont maintenant **20% plus grandes** (scale 1.2)
- Les lignes sélectionnées ont un **fond bleu clair** (`#cfe2ff`)
- Une **bordure bleue gauche** (`4px solid #0d6efd`) indique la sélection

### 3. **Barre d'actions améliorée**
- La barre apparaît automatiquement dès qu'une catégorie est cochée
- Design moderne avec gradient violet
- 3 actions disponibles : Supprimer, Exporter, Annuler

## 📖 Comment Utiliser les Opérations par Lot

### Étape 1 : Accéder à la page
```
Navigation Moodle > Administration > Plugins locaux > Question Diagnostic > Gestion des Catégories
```

### Étape 2 : Sélectionner des catégories

**Option A : Sélection individuelle**
1. Cochez les cases à cocher (☐) dans la première colonne du tableau
2. Les lignes sélectionnées deviennent **bleues**

**Option B : Tout sélectionner**
1. Cliquez sur la case à cocher dans l'**en-tête du tableau** (première colonne)
2. Toutes les catégories visibles seront sélectionnées

### Étape 3 : La barre d'actions apparaît automatiquement

Dès que vous cochez une ou plusieurs catégories, une **barre violette** apparaît avec :

```
┌─────────────────────────────────────────────────────────────────┐
│ 📋 5 catégorie(s) sélectionnée(s)                               │
│                                                                  │
│ [🗑️ Supprimer la sélection] [📤 Exporter] [❌ Annuler]        │
└─────────────────────────────────────────────────────────────────┘
```

### Étape 4 : Choisir une action

#### 🗑️ Supprimer la sélection
- **Ce que ça fait** : Supprime toutes les catégories **vides** sélectionnées
- **Protection** : Seules les catégories vides peuvent être supprimées
- **Confirmation** : Une page de confirmation s'affiche avant la suppression
- **Rapport** : Un message indique le nombre de suppressions réussies

**Exemple d'utilisation :**
```
Vous avez : 5 catégories sélectionnées
- 3 catégories vides
- 2 catégories avec questions

Résultat : Seules les 3 catégories vides seront supprimées
```

#### 📤 Exporter la sélection
- **Ce que ça fait** : Télécharge un fichier CSV contenant UNIQUEMENT les catégories sélectionnées
- **Format** : UTF-8 avec BOM (compatible Excel)
- **Nom du fichier** : `categories_questions_selection_2025-10-08_14-30-00.csv`
- **Colonnes** : ID, Nom, Contexte, Parent, Questions visibles, Questions totales, Sous-catégories, Statut

**Cas d'usage :**
- Analyser un sous-ensemble spécifique de catégories
- Créer un rapport pour une réunion
- Comparer les catégories vides avant/après nettoyage

#### ❌ Annuler
- **Ce que ça fait** : Désélectionne toutes les catégories en un clic
- **Résultat** : La barre violette disparaît, les lignes redeviennent blanches

## 🎨 Indicateurs Visuels

### États de sélection
| État | Apparence |
|------|-----------|
| **Non sélectionnée** | Ligne blanche, checkbox vide ☐ |
| **Sélectionnée** | Ligne bleue clair, bordure gauche bleue, checkbox cochée ☑️ |
| **Survol** | Légère ombre portée |

### Barre d'actions
| État | Affichage |
|------|-----------|
| **Aucune sélection** | Cachée (invisible) |
| **1+ sélections** | Visible avec animation de glissement |
| **Couleur** | Gradient violet moderne |

## 🧪 Testez-le Maintenant !

### Test Rapide
1. Allez dans la page "Gestion des Catégories"
2. Cherchez une catégorie **vide** dans le tableau (badge "Vide")
3. Cochez la case à cocher de cette catégorie
4. ✅ **Vérifiez** : La barre violette devrait apparaître
5. Cliquez sur "Annuler" pour désélectionner

### Test Complet
1. Filtrez pour afficher uniquement les catégories vides :
   - Menu déroulant "Statut" → Sélectionnez "Vide"
2. Cliquez sur "Tout sélectionner" dans l'en-tête
3. Cliquez sur "📤 Exporter la sélection"
4. ✅ **Vérifiez** : Vous téléchargez un CSV avec toutes les catégories vides

## ❓ Dépannage

### Problème : Je ne vois pas les checkboxes
**Solution :**
- Vérifiez que JavaScript est activé dans votre navigateur
- Videz le cache Moodle : Administration > Purger tous les caches
- Rechargez la page avec `Ctrl+Shift+R` (forcer le rechargement CSS/JS)

### Problème : La barre violette n'apparaît jamais
**Solution :**
1. Ouvrez la console du navigateur (`F12`)
2. Vérifiez s'il y a des erreurs JavaScript
3. Assurez-vous que le fichier `/local/question_diagnostic/scripts/main.js` est bien chargé
4. Vérifiez que vous avez bien coché une case (pas juste cliqué sur la ligne)

### Problème : Le bouton "Supprimer" ne fonctionne pas
**Solution :**
- Vérifiez que vous avez sélectionné **uniquement des catégories vides**
- Les catégories avec questions ou sous-catégories ne peuvent pas être supprimées
- Vérifiez que vous êtes bien **administrateur du site**

### Problème : L'export CSV ne contient rien
**Solution :**
- Assurez-vous d'avoir bien sélectionné des catégories (coché les cases)
- Si vous utilisez "Export" sans sélection, toutes les catégories sont exportées
- Vérifiez les autorisations de téléchargement de votre navigateur

## 🔒 Sécurité

Toutes les actions par lot sont protégées :
- ✅ Vérification **administrateur du site** requise
- ✅ Protection **CSRF** avec `sesskey`
- ✅ **Confirmation utilisateur** avant toute suppression
- ✅ Validation stricte des IDs côté serveur
- ✅ Les catégories protégées ne peuvent jamais être supprimées

## 📊 Cas d'Usage Pratiques

### Cas 1 : Nettoyage de printemps
**Objectif** : Supprimer toutes les catégories vides créées l'année dernière

1. Filtrez : Statut → "Vide"
2. Cochez toutes les catégories vides que vous voulez supprimer
3. Cliquez sur "🗑️ Supprimer la sélection"
4. Confirmez la suppression
5. ✅ Les catégories sont supprimées en une seule fois

### Cas 2 : Rapport pour la direction
**Objectif** : Exporter uniquement les catégories du contexte "Cours"

1. Filtrez : Contexte → "Cours"
2. Cliquez sur "Tout sélectionner"
3. Cliquez sur "📤 Exporter la sélection"
4. ✅ Vous obtenez un CSV avec seulement les catégories de cours

### Cas 3 : Analyse ciblée
**Objectif** : Comparer 5 catégories spécifiques

1. Recherchez la première catégorie par nom
2. Cochez-la
3. Répétez pour les 4 autres catégories
4. Exportez la sélection
5. ✅ Vous obtenez un CSV avec exactement 5 lignes

## 📚 Documentation Technique

Pour les développeurs souhaitant comprendre l'implémentation :

### Fichiers modifiés
- `categories.php` : Ajout de l'aide visuelle et commentaires
- `styles/main.css` : Amélioration du style des checkboxes et lignes sélectionnées
- `scripts/main.js` : Gestion de la sélection et actions groupées
- `actions/delete.php` : Support de la suppression par lot
- `actions/export.php` : Support de l'export filtré

### Architecture
```
User clicks checkbox
    ↓
JavaScript: updateBulkActionsBar()
    ↓
Add class "visible" to #bulk-actions-bar
    ↓
CSS: display: block (animation slideDown)
    ↓
User clicks action button
    ↓
JavaScript: collect selected IDs
    ↓
Redirect to action.php?ids=1,2,3&sesskey=...
    ↓
PHP: validate, confirm, execute
```

### État global JavaScript
```javascript
const state = {
    selectedCategories: new Set(),  // IDs des catégories sélectionnées
    allCategories: [],              // Toutes les catégories du tableau
    filteredCategories: [],         // Catégories après filtrage
    currentSort: {...},             // Tri actuel
    currentPage: 1,                 // Pagination
    itemsPerPage: 50                // Éléments par page
};
```

## 🆕 Prochaines Améliorations Possibles

1. **Déplacement par lot** : Déplacer plusieurs catégories vers un parent commun
2. **Fusion multiple** : Fusionner N catégories vers une destination
3. **Tags/Favoris** : Marquer des catégories pour y accéder rapidement
4. **Actions conditionnelles** : Afficher/masquer certains boutons selon le type
5. **Prévisualisation** : Modal montrant un résumé avant confirmation
6. **Historique** : Log des actions groupées effectuées

---

**Développé pour Moodle Question Diagnostic Tool v1.2.3**  
*Compatible Moodle 4.5+*

**Date** : 8 octobre 2025  
**Auteur** : Équipe de développement local_question_diagnostic
