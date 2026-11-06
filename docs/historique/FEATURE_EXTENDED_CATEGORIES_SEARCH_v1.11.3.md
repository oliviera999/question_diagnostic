# 🆕 Fonctionnalité v1.11.3 - Recherche étendue toutes catégories du site

**Date :** 15 octobre 2025  
**Version :** v1.11.3  
**Type :** ✨ **NOUVELLE FONCTIONNALITÉ**

## 🎯 Demande utilisateur

> "Est-il possible de rechercher parmi absolument toutes les catégories du site dans 'gestion des catégories à supprimer' ?"

## ✅ Solution implémentée

### 🔍 **Recherche étendue multi-types**

La page "Gestion des catégories" peut maintenant afficher **toutes les catégories du site** :

- **📚 Catégories de cours** (`course_categories`)
- **❓ Catégories de questions** (`question_categories`)

### 🎛️ **Interface utilisateur améliorée**

#### **Nouveau filtre "Type de catégories"**
- **"Questions uniquement (actuel)"** : Vue traditionnelle (catégories de questions)
- **"Toutes les catégories (questions + cours)"** : Vue étendue (toutes les catégories)

#### **Tableau enrichi avec nouvelles colonnes**
- **Type** : Icône 📚 (cours) ou ❓ (questions)
- **Cours** : Nombre de cours (pour les catégories de cours)
- **Contexte adaptatif** : Affichage selon le type

### 🏗️ **Architecture technique**

#### **Nouvelle méthode : `get_all_site_categories_with_stats()`**
```php
// PARTIE 1 : Catégories de QUESTIONS (logique existante)
$question_categories = self::get_all_categories_with_stats();

// PARTIE 2 : Catégories de COURS (nouvelle logique)
$course_categories = $DB->get_records('course_categories', null, 'parent, name ASC');

// PARTIE 3 : Tri unifié (questions puis cours)
usort($all_categories, function($a, $b) {
    // Tri par type d'abord (questions puis cours)
    // Puis tri par nom
});
```

#### **Objet unifié pour les deux types**
```php
$unified_category = (object)[
    'category_type' => 'course' | 'question',
    'category_type_label' => 'Catégorie de cours' | 'Catégorie de questions',
    'total_questions' => 0, // Pour catégories de cours
    'course_count' => 5,    // Pour catégories de cours
    'subcategories' => 2,
    'can_delete' => true,   // Logique adaptée au type
    // ... autres propriétés
];
```

## 🎯 **Fonctionnalités détaillées**

### ✅ **Catégories de cours**
- **Détection** : Toutes les catégories de cours du site
- **Statistiques** : Nombre de cours, sous-catégories
- **Statuts** : Vide, Orpheline, OK
- **Protection** : Catégories système importantes (ID 1, "miscellaneous")
- **Suppression** : Possible si vide et non protégée

### ✅ **Catégories de questions**
- **Fonctionnalités existantes** : Inchangées
- **Statistiques** : Questions, sous-catégories, doublons
- **Contexte** : Système, cours, module
- **Protection** : "Default for...", catégories système

### ✅ **Interface unifiée**
- **Tri** : Par type puis par nom
- **Filtrage** : Tous les filtres existants fonctionnent
- **Recherche** : Nom, statut, contexte
- **Actions** : Sélection multiple, export CSV
- **Navigation** : Basculement entre vues

## 🔧 **Utilisation**

### **Accès à la fonctionnalité**
```
/local/question_diagnostic/categories.php
```

### **Activation de la vue étendue**
1. **Sélectionner** "Toutes les catégories (questions + cours)" dans le filtre
2. **Page se recharge** automatiquement avec toutes les catégories
3. **Bouton de retour** vers la vue questions uniquement

### **URLs supportées**
```
/categories.php                    # Vue questions (par défaut)
/categories.php?type=questions     # Vue questions explicite
/categories.php?type=all          # Vue étendue (toutes catégories)
```

## 📊 **Impact et bénéfices**

### ✅ **Positif**
- **Visibilité complète** : Voir toutes les catégories du site
- **Gestion unifiée** : Une seule interface pour tout
- **Détection étendue** : Trouver les catégories de cours vides/orphelines
- **Flexibilité** : Basculement facile entre les vues
- **Performance** : Chargement optimisé avec requêtes séparées

### 🎯 **Cas d'usage**
1. **Audit complet** : Voir toutes les catégories du site
2. **Nettoyage étendu** : Supprimer les catégories de cours vides
3. **Organisation** : Comprendre la structure complète
4. **Maintenance** : Identifier les catégories problématiques

## 🔄 **Compatibilité**

- **Moodle** : 4.0, 4.1 LTS, 4.3, 4.4, 4.5+ (inchangée)
- **PHP** : 7.4+ (inchangée)
- **Base de données** : MySQL 8.0+, MariaDB 10.6+, PostgreSQL 13+ (inchangée)
- **Interface** : Responsive, tous les navigateurs modernes

## 🧪 **Tests recommandés**

### ✅ **Scénarios à tester**
1. **Vue questions** : Fonctionnement inchangé
2. **Vue étendue** : Affichage de toutes les catégories
3. **Basculement** : Changement de vue fluide
4. **Filtrage** : Tous les filtres fonctionnent
5. **Tri** : Par type puis par nom
6. **Actions** : Sélection, export CSV

### 🔍 **Vérifications**
- Les catégories de cours apparaissent avec l'icône 📚
- Les catégories de questions apparaissent avec l'icône ❓
- La colonne "Cours" affiche le bon nombre
- Les contextes sont correctement affichés
- Les actions de suppression fonctionnent selon le type

## 📝 **Fichiers modifiés**

- ✅ `classes/category_manager.php` : Nouvelle méthode `get_all_site_categories_with_stats()`
- ✅ `categories.php` : Interface étendue avec filtre et colonnes supplémentaires
- ✅ `version.php` : Version incrémentée vers v1.11.3

## 🏷️ **Tags**

`nouvelle-fonctionnalité` `recherche-étendue` `catégories-cours` `catégories-questions` `interface-unifiée` `v1.11.3`

---

**Conclusion :** Cette fonctionnalité répond parfaitement à la demande utilisateur en permettant de rechercher parmi **absolument toutes les catégories du site**. L'interface reste intuitive avec un basculement facile entre les vues, et toutes les fonctionnalités existantes sont préservées.
