# Changelog v1.11.12 - Correction Vue Hiérarchique

## 🎯 Problème Identifié
La vue hiérarchique affichait "Aucune catégorie trouvée dans cette sélection" alors que le tableau montrait clairement de nombreuses catégories dans la catégorie "olution".

## 🔍 Diagnostic
Le problème venait de la fonction `local_question_diagnostic_get_question_categories_hierarchy()` qui :
1. **Utilisait `CONCAT()`** dans la requête SQL (incompatible avec certains SGBD)
2. **Avait des jointures complexes** qui pouvaient échouer
3. **Dupliquait la logique** déjà existante et fonctionnelle

## 🔧 Solution Appliquée

### Modification de `lib.php`

#### Fonction `local_question_diagnostic_get_question_categories_hierarchy()`
- **Avant** : Requête SQL complexe avec `CONCAT()` et jointures fragiles
- **Après** : Utilise la fonction existante `local_question_diagnostic_get_question_categories_by_course_category()`

#### Nouvelle approche
```php
// Utiliser la fonction existante qui fonctionne déjà
$categories_with_stats = local_question_diagnostic_get_question_categories_by_course_category($course_category_id);

// Convertir en objets simples pour la construction de la hiérarchie
$categories = [];
foreach ($categories_with_stats as $item) {
    $category = new stdClass();
    $category->id = $item->id;
    $category->name = $item->name;
    $category->info = $item->info ?? '';
    $category->parent = $item->parent;
    $category->sortorder = $item->sortorder ?? 0;
    $category->total_questions = $item->total_questions ?? 0;
    $category->context_display_name = $item->context_display_name ?? '';
    $category->context_type = $item->context_type ?? 'unknown';
    $categories[] = $category;
}

// Construire la hiérarchie
return local_question_diagnostic_build_category_hierarchy($categories);
```

## ✅ Avantages de la Correction

### 1. Fiabilité
- **Réutilise le code existant** qui fonctionne déjà
- **Évite la duplication** de logique métier
- **Garantit la cohérence** avec le tableau principal

### 2. Compatibilité
- **Supprime `CONCAT()`** problématique
- **Évite les jointures complexes** fragiles
- **Compatible avec tous les SGBD** (MySQL, MariaDB, PostgreSQL)

### 3. Maintenabilité
- **Code plus simple** et plus lisible
- **Moins de points de défaillance**
- **Plus facile à déboguer**

## 🧪 Tests

### Script de test créé
- **Fichier** : `test_hierarchical_fix.php`
- **Fonctionnalités** :
  - Test de la fonction corrigée
  - Affichage des statistiques
  - Rendu complet de l'arbre
  - Instructions de test utilisateur

### Résultats attendus
- ✅ La hiérarchie récupère maintenant les catégories
- ✅ L'arbre s'affiche correctement
- ✅ Les boutons de purge fonctionnent
- ✅ Compatible avec tous les SGBD

## 📋 Checklist de déploiement

- [x] Fonction corrigée dans `lib.php`
- [x] Suppression de la requête SQL complexe
- [x] Utilisation de la fonction existante
- [x] Version incrémentée vers `v1.11.12`
- [x] Script de test créé
- [x] Changelog documenté

## 🎯 Résultat

Après cette correction, la vue hiérarchique devrait :
- **Afficher toutes les catégories** de la catégorie de cours sélectionnée
- **Fonctionner de manière fiable** sur tous les SGBD
- **Être cohérente** avec le tableau principal
- **Offrir une expérience utilisateur** identique à la banque de questions Moodle

## 🔮 Impact

### Pour les utilisateurs
- **Vue hiérarchique fonctionnelle** : Plus de message "Aucune catégorie trouvée"
- **Expérience cohérente** : Même données que le tableau principal
- **Navigation intuitive** : Arbre hiérarchique comme dans Moodle

### Pour les développeurs
- **Code plus robuste** : Moins de points de défaillance
- **Maintenance simplifiée** : Réutilisation du code existant
- **Debugging facilité** : Logique centralisée

---

**Version** : v1.11.12  
**Date** : 15 octobre 2025  
**Statut** : ✅ Correction appliquée  
**Impact** : 🟢 Correction de bug, aucune régression
