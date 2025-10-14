# 🗑️ Fonctionnalité : Questions inutilisées

**Version** : v1.10.1  
**Date de création** : Octobre 2025  
**Statut** : ✅ Implémenté

---

## 📝 Description

Cette fonctionnalité permet de visualiser et de gérer toutes les questions qui ne sont pas utilisées dans des quiz sur votre site Moodle. Elle offre une interface complète avec tableau détaillé, filtres avancés, tri par colonnes, et suppression en masse.

## 🎯 Objectifs

1. **Identifier** les questions obsolètes qui encombrent la base de données
2. **Visualiser** toutes les informations importantes sur chaque question inutilisée
3. **Filtrer et trier** pour faciliter la gestion
4. **Supprimer** individuellement ou en masse les questions inutilisées
5. **Exporter** la liste pour archivage ou documentation

## 📂 Fichiers créés/modifiés

### Nouveaux fichiers
- `unused_questions.php` - Page principale de gestion des questions inutilisées

### Fichiers modifiés
- `classes/question_analyzer.php` - Ajout méthode `get_unused_questions()`
- `index.php` - Ajout lien dans le menu principal
- `lang/fr/local_question_diagnostic.php` - Chaînes de langue FR
- `lang/en/local_question_diagnostic.php` - Chaînes de langue EN

## 🏗️ Architecture technique

### Nouvelle méthode dans `question_analyzer`

```php
/**
 * Récupère les questions inutilisées (avec limite et pagination)
 *
 * @param int $limit Limite du nombre de questions (défaut: 50)
 * @param int $offset Offset pour la pagination (défaut: 0)
 * @return array Tableau des questions inutilisées
 */
public static function get_unused_questions($limit = 50, $offset = 0)
```

**Logique de détection des questions inutilisées :**

1. Détecte l'architecture Moodle (4.5+ avec `question_references`, 4.1-4.4 avec `questionbankentryid`, 4.0 avec `questionid`)
2. Récupère les IDs des questions utilisées dans des quiz (via `quiz_slots`)
3. Récupère les IDs des questions ayant des tentatives (via `question_attempts`)
4. Retourne les questions qui NE sont PAS dans ces deux listes
5. Support de la pagination pour performances optimales

### Compatibilité multi-version Moodle

La méthode s'adapte automatiquement à l'architecture de votre version Moodle :

- **Moodle 4.5+** : Utilise `question_references` (nouvelle architecture)
- **Moodle 4.1-4.4** : Utilise `questionbankentryid`
- **Moodle 4.0** : Utilise `questionid` direct

## 🎨 Interface utilisateur

### Dashboard de statistiques

Affiche 3 cartes principales :
- **Total questions** dans la base
- **Questions utilisées** (dans quiz ou avec tentatives)
- **Questions inutilisées** (focus principal)

### Tableau détaillé

Colonnes disponibles (toggables) :
- ✅ ID (affiché par défaut)
- ✅ Nom (affiché par défaut)
- ✅ Type (affiché par défaut)
- ✅ Catégorie (affiché par défaut, cliquable)
- ✅ Cours (affiché par défaut)
- ⚪ Contexte (masqué par défaut)
- ⚪ Créateur (masqué par défaut)
- ✅ Date de création (affiché par défaut)
- ⚪ Date de modification (masqué par défaut)
- ⚪ Visible/Cachée (masqué par défaut)
- ⚪ Extrait du texte (masqué par défaut)
- ✅ Actions (affiché par défaut)

### Fonctionnalités du tableau

#### 🔍 Filtres avancés
- **Recherche textuelle** : Nom, ID, cours, texte de la question (avec debounce 300ms)
- **Filtre par type** : Tous les types de questions disponibles
- **Filtre par visibilité** : Toutes / Visibles / Cachées

#### ⬆️⬇️ Tri par colonnes
- Cliquer sur n'importe quelle colonne pour trier
- Tri ascendant/descendant (toggle)
- Indicateurs visuels (▲▼)
- Tri intelligent (numérique vs alphabétique)

#### ☑️ Sélection en masse
- Checkbox "Tout sélectionner/désélectionner" en en-tête
- Checkboxes individuelles (uniquement pour questions supprimables)
- Compteur de sélection dynamique
- Bouton de suppression en masse (apparaît si sélection > 0)

#### 🗑️ Suppression
- **Individuelle** : Bouton 🗑️ sur chaque ligne (avec confirmation)
- **En masse** : Sélectionner plusieurs questions et cliquer sur "🗑️ Supprimer la sélection"
- **Protection automatique** : Les questions protégées affichent 🔒 avec tooltip explicatif

### Actions disponibles

- **👁️ Voir** : Ouvrir la question dans la banque de questions (nouvel onglet)
- **🗑️ Supprimer** : Supprimer la question (avec confirmation)
- **🔒 Protégée** : Affichage pour les questions non supprimables avec raison

### Pagination

- **Par défaut** : 50 questions affichées
- **Bouton "Charger plus"** : +50 questions à chaque clic
- **Limite maximale** : 500 questions par page (pour performances)
- Compteur : "Affichage de X sur Y au total"

## 🔒 Sécurité

### Règles de protection

**Une question inutilisée peut être supprimée SI :**
- ✅ Elle n'est pas utilisée dans un quiz
- ✅ Elle n'a aucune tentative associée
- ✅ Elle n'est pas cachée
- ✅ La vérification de suppression passe (`can_delete_questions_batch()`)

**Sinon, elle est PROTÉGÉE et affiche 🔒**

### Validations de sécurité

1. ✅ **Admin uniquement** : `is_siteadmin()` requis
2. ✅ **Session key** : `sesskey()` validé sur toutes les actions
3. ✅ **Confirmation utilisateur** : Double confirmation avant suppression
4. ✅ **Protection automatique** : Vérifie la supprimabilité via `can_delete_questions_batch()`
5. ✅ **Validation des paramètres** : `optional_param()` avec types (`PARAM_INT`)

## 📊 Export CSV

Fonctionnalité d'export disponible via le bouton "📥 Exporter les questions inutilisées en CSV".

**Colonnes exportées :**
- ID
- Nom
- Type
- Catégorie
- Cours
- Créateur
- Date de création
- Date de modification
- Statut (utilisée/inutilisée)

## 💾 Performances

### Optimisations implémentées

1. **Pagination serveur** : Limite à 50-100 questions chargées à la fois
2. **Requêtes optimisées** : Utilisation de subqueries pour filtrer en SQL (pas en PHP)
3. **Batch loading** : `can_delete_questions_batch()` pour vérifier la supprimabilité en une seule requête
4. **Cache localStorage** : Préférences de colonnes sauvegardées localement
5. **Debounce search** : 300ms pour éviter trop de rerender pendant la frappe

### Temps de chargement estimés

- **Petite base** (<1000 questions) : <2 secondes
- **Moyenne base** (1000-5000 questions) : 2-5 secondes
- **Grande base** (>5000 questions) : 5-10 secondes (avec pagination)

## 🌐 Internationalisation

Chaînes de langue ajoutées en **français** et **anglais** :

### Nouvelles clés
```php
$string['unused_questions'] = 'Questions inutilisées';
$string['unused_questions_title'] = 'Questions inutilisées';
$string['unused_questions_heading'] = 'Gestion des questions inutilisées';
$string['unused_questions_info'] = 'Cette page affiche...';
$string['unused_questions_list'] = 'Liste des questions inutilisées';
$string['no_unused_questions'] = 'Aucune question inutilisée trouvée';
$string['no_unused_questions_desc'] = 'Toutes vos questions sont utilisées...';
$string['export_unused_csv'] = 'Exporter les questions inutilisées en CSV';
$string['load_more_questions'] = 'Charger 50 questions supplémentaires';
$string['statistics'] = 'Statistiques';
$string['tool_unused_questions_title'] = 'Questions inutilisées';
$string['tool_unused_questions_desc'] = 'Visualisez et gérez...';
```

## 🔗 Navigation

### Depuis le menu principal

Nouvelle carte ajoutée dans `index.php` (Option 3b) :

- **Icône** : 🗑️
- **Titre** : Questions inutilisées
- **Description** : Visualisez et gérez toutes les questions qui ne sont pas utilisées...
- **Statistiques affichées** :
  - 📊 X questions (total)
  - 💤 Y inutilisées
  - 📈 Z% du total

### Liens de navigation

- **Menu principal → Questions inutilisées** : `index.php` → `unused_questions.php`
- **Lien retour** : Badge hiérarchique en haut de page (via `local_question_diagnostic_render_back_link()`)
- **Bouton Aide** : Lien vers `help.php`
- **Bouton Purger cache** : Force le recalcul des statistiques

## ✅ Tests recommandés

### Tests fonctionnels

1. ✅ **Affichage de base**
   - Accéder à la page depuis le menu principal
   - Vérifier l'affichage des statistiques
   - Vérifier l'affichage du tableau

2. ✅ **Filtres**
   - Tester la recherche textuelle
   - Tester les filtres par type
   - Tester le filtre par visibilité
   - Vérifier le compteur de résultats

3. ✅ **Tri**
   - Trier par chaque colonne
   - Vérifier le sens ascendant/descendant
   - Vérifier les indicateurs visuels

4. ✅ **Sélection**
   - Sélectionner des questions individuellement
   - Utiliser "Tout sélectionner"
   - Vérifier le compteur de sélection

5. ✅ **Suppression**
   - Supprimer une question individuellement
   - Supprimer plusieurs questions en masse
   - Vérifier la confirmation
   - Vérifier que les questions protégées ne sont pas supprimables

6. ✅ **Pagination**
   - Charger plus de questions
   - Vérifier le compteur
   - Vérifier les filtres après chargement

7. ✅ **Export CSV**
   - Exporter la liste
   - Vérifier le contenu du CSV

### Tests de performance

1. ✅ **Petite base** (<100 questions inutilisées)
   - Temps de chargement < 2s

2. ✅ **Grande base** (>1000 questions inutilisées)
   - Temps de chargement initial < 5s
   - Chargement progressif fonctionnel

### Tests de sécurité

1. ✅ **Accès non autorisé**
   - Tester l'accès sans être admin (doit échouer)

2. ✅ **CSRF Protection**
   - Tester les actions sans `sesskey` (doivent échouer)

3. ✅ **Validation des paramètres**
   - Tester avec des valeurs invalides

## 📈 Métriques de succès

- **Adoption** : Nombre d'utilisateurs utilisant la page (tracking via logs)
- **Nettoyage** : Nombre de questions supprimées via cette fonctionnalité
- **Performance** : Temps de chargement moyen < 5s
- **Satisfaction** : Feedback positif des administrateurs

## 🚀 Évolutions futures possibles

1. **Export avancé** : Export en PDF, Excel
2. **Archivage** : Au lieu de supprimer, archiver dans une catégorie dédiée
3. **Restauration** : Possibilité de restaurer des questions supprimées (trash)
4. **Notifications** : Alerter les créateurs de questions avant suppression
5. **Scheduling** : Suppression automatique programmée des questions inutilisées depuis X mois
6. **Statistiques avancées** : Graphiques d'évolution dans le temps
7. **Comparaison** : Comparer les questions inutilisées entre cours

## 📚 Documentation connexe

- `docs/guides/USER_GUIDE.md` - Guide utilisateur
- `docs/features/FEATURE_SAFE_QUESTION_DELETION.md` - Suppression sécurisée
- `docs/technical/MOODLE_4.5_DATABASE_REFERENCE.md` - Structure BDD Moodle 4.5

## 👥 Contributeurs

- **Développeur initial** : Équipe local_question_diagnostic
- **Version** : v1.10.1
- **Date** : Octobre 2025

---

**Note** : Cette fonctionnalité respecte toutes les règles de sécurité et les standards Moodle définis dans le projet.

