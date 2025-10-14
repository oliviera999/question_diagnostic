# Tri par Statut dans les Tableaux

## 📋 Vue d'ensemble

Cette fonctionnalité permet de trier les tableaux du plugin par statut en cliquant sur l'en-tête de colonne correspondant.

## ✨ Fonctionnalités ajoutées

### 1. Tableau des Catégories (`categories.php`)

**Modifications :**
- Ajout du tri par statut sur la colonne "Statut"
- Système de priorité pour le tri :
  - **5** : Protégée 🛡️ (plus haute priorité)
  - **4** : Orpheline ⚠️
  - **3** : Doublon 🔀
  - **2** : Vide 📭
  - **1** : OK ✅ (plus basse priorité)

**Utilisation :**
Cliquez sur l'en-tête "Statut" pour trier les catégories par ordre de criticité (décroissant ou croissant).

### 2. Tableau des Liens Cassés (`broken_links.php`)

**Modifications :**
- Ajout du tri sur plusieurs colonnes :
  - **ID** : Tri numérique
  - **Nom** : Tri alphabétique
  - **Type** : Tri alphabétique
  - **Catégorie** : Tri alphabétique
  - **Nombre de liens cassés** : Tri numérique (permet d'identifier rapidement les questions les plus problématiques)

**Utilisation :**
Cliquez sur n'importe quelle en-tête de colonne triable pour trier le tableau.

### 3. Tableau des Questions (`questions_cleanup.php`)

**Statut :**
Le tri par statut était déjà implémenté dans ce tableau.
- **1** : Question utilisée (dans quiz ou tentatives)
- **0** : Question inutilisée

## 🎯 Comment utiliser le tri

1. **Cliquer sur l'en-tête de colonne** : Le tableau se trie en ordre croissant
2. **Recliquer sur la même colonne** : Le tri s'inverse (décroissant)
3. **Indicateur visuel** : La colonne triée affiche un symbole (▲ ou ▼)

## 🔧 Détails techniques

### Architecture

Le tri est géré par le JavaScript existant dans `scripts/main.js` :
- Fonction `initializeSorting()` : Initialise les écouteurs d'événements sur les colonnes triables
- Fonction `sortTable()` : Effectue le tri en mémoire côté client (rapide, pas de rechargement)

### Attributs HTML

Les colonnes triables nécessitent :
```html
<th class="sortable" data-column="status">Statut</th>
```

Les lignes du tableau nécessitent :
```html
<tr data-status="5" data-id="123" data-name="Ma catégorie">
```

### Logique de tri

1. **Détection numérique** : Si les valeurs sont numériques, tri numérique
2. **Sinon** : Tri alphabétique (insensible à la casse)
3. **Ordre** : Croissant par défaut, alternance croissant/décroissant au clic

## 📊 Exemples d'utilisation

### Cas d'usage 1 : Identifier les catégories critiques
1. Aller sur la page `categories.php`
2. Cliquer sur "Statut"
3. Les catégories protégées et orphelines apparaissent en haut (tri décroissant)

### Cas d'usage 2 : Trouver les questions avec le plus de liens cassés
1. Aller sur la page `broken_links.php`
2. Cliquer sur "Nombre de liens cassés"
3. Les questions avec le plus de problèmes apparaissent en haut

### Cas d'usage 3 : Trier par nom de catégorie
1. Sur n'importe quel tableau
2. Cliquer sur "Nom"
3. Tri alphabétique A-Z (recliquer pour Z-A)

## ✅ Tests recommandés

### Test 1 : Tri simple
- [ ] Cliquer sur "Statut" dans categories.php
- [ ] Vérifier que les catégories protégées apparaissent en premier
- [ ] Recliquer et vérifier l'inversion du tri

### Test 2 : Tri après filtrage
- [ ] Appliquer un filtre (ex: "Vide")
- [ ] Cliquer sur "Statut"
- [ ] Vérifier que seules les lignes visibles sont triées

### Test 3 : Tri multiples colonnes
- [ ] Trier par "Questions"
- [ ] Puis trier par "Statut"
- [ ] Vérifier que le nouveau tri remplace l'ancien

### Test 4 : Compatibilité pagination
- [ ] Sur un tableau paginé
- [ ] Trier par une colonne
- [ ] Vérifier que toutes les pages sont triées (si applicable)

## 🐛 Dépannage

### Le tri ne fonctionne pas
1. Vérifier que `scripts/main.js` est bien chargé
2. Vérifier la console JavaScript pour des erreurs
3. Vérifier que les attributs `data-*` sont présents sur les lignes `<tr>`

### Le tri est incorrect
1. Vérifier que les valeurs dans les attributs `data-*` sont cohérentes
2. Pour un tri numérique, s'assurer que les valeurs sont des nombres (pas de texte)
3. Vérifier la priorité des statuts dans le code PHP

### Conflit avec les filtres
Le tri et les filtres fonctionnent ensemble :
- Le tri s'applique uniquement aux lignes visibles (après filtrage)
- Changer un filtre ne réinitialise pas le tri

## 📝 Notes de version

**Version** : 1.2.1+  
**Date** : Octobre 2025  
**Compatibilité** : Moodle 4.5+

**Fichiers modifiés :**
- `categories.php` : Lignes 342, 354-365, 380
- `broken_links.php` : Lignes 221-225, 239-246
- `scripts/main.js` : Aucune modification (utilise le code existant)

**Rétrocompatibilité :**
Cette fonctionnalité est entièrement rétrocompatible. Le tri est optionnel et ne change pas le comportement par défaut des tableaux.

## 🎨 Améliorations futures possibles

1. **Tri multi-colonnes** : Permettre de trier par plusieurs colonnes simultanément (ex: statut puis nom)
2. **Sauvegarde préférence** : Mémoriser le tri préféré de l'utilisateur (localStorage)
3. **Indicateurs visuels améliorés** : Ajouter des icônes ▲▼ animées
4. **Tri côté serveur** : Pour les très grandes tables, effectuer le tri en PHP
5. **Export CSV trié** : Exporter le tableau dans l'ordre actuellement affiché

## 📚 Références

- [MDN - dataset API](https://developer.mozilla.org/fr/docs/Web/API/HTMLElement/dataset)
- [Moodle Coding Guidelines](https://moodledev.io/general/development/policies/codingstyle)
- Code source : `scripts/main.js`, fonction `sortTable()`

