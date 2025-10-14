# Fonctionnalité : Déplacement automatique vers Olution (v1.10.4)

## 📋 Vue d'ensemble

Cette nouvelle fonctionnalité détecte automatiquement les questions en doublon entre les catégories de cours et la catégorie système "Olution", puis permet de les déplacer de manière sécurisée vers les catégories correspondantes dans Olution.

## 🎯 Objectif

Nettoyer les catégories de cours en déplaçant les questions qui sont des doublons de questions déjà présentes dans "Olution" (la banque de questions centrale au niveau système).

## ✨ Fonctionnalités

### 1. Détection intelligente des doublons

Le système détecte les doublons selon 3 critères stricts :
- **Nom** : Identique (case-sensitive)
- **Type** : Identique (qtype)
- **Contenu** : Similarité ≥ 90% du texte de la question

### 2. Correspondance automatique des catégories

- Trouve la catégorie "Olution" au niveau système
- Recherche les sous-catégories d'Olution par nom
- Matche automatiquement une catégorie de cours avec sa correspondante dans Olution
- Exemple : Catégorie cours "Mathématiques" → Catégorie Olution "Mathématiques"

### 3. Interface de gestion

Page accessible via : **Dashboard → Gérer les doublons Olution**

#### Statistiques affichées :
- Nombre total de doublons détectés
- Questions déplaçables (avec correspondance trouvée)
- Questions sans correspondance (ignorées)
- Nombre de sous-catégories Olution

#### Tableau détaillé :
- Nom et type de chaque question
- Catégorie source (cours)
- Catégorie cible (Olution)
- Pourcentage de similarité
- Actions individuelles

### 4. Actions de déplacement

#### Déplacement individuel
- Bouton "Déplacer" pour chaque question
- Page de confirmation avec détails
- Transaction SQL sécurisée avec rollback

#### Déplacement en masse
- Bouton "Déplacer toutes les questions (X)"
- Page de confirmation avec liste des catégories affectées
- Traitement par batch avec rapport de résultats

### 5. Sécurité

✅ **Vérifications implémentées :**
- Accès réservé aux administrateurs (`is_siteadmin()`)
- Protection CSRF (`require_sesskey()`)
- Page de confirmation AVANT toute modification
- Transactions SQL avec rollback automatique en cas d'erreur
- Validation que la catégorie cible est bien dans Olution (CONTEXT_SYSTEM)
- Logs d'audit pour traçabilité

## 📁 Fichiers créés

### Nouveaux fichiers

1. **`olution_duplicates.php`**
   - Page principale de gestion
   - Affichage des statistiques et liste des doublons
   - Interface de pagination

2. **`classes/olution_manager.php`**
   - Classe principale de logique métier
   - Méthodes de détection des doublons
   - Méthodes de déplacement (individuel et masse)
   - Calcul de similarité de texte

3. **`actions/move_to_olution.php`**
   - Action de déplacement avec confirmation
   - Gestion du déplacement individuel
   - Gestion du déplacement en masse

### Fichiers modifiés

1. **`lib.php`**
   - Ajout de 3 fonctions utilitaires :
     - `local_question_diagnostic_find_olution_category()`
     - `local_question_diagnostic_get_olution_subcategories()`
     - `local_question_diagnostic_find_olution_category_by_name()`

2. **`index.php`**
   - Ajout d'une carte "Doublons Cours → Olution" dans le dashboard
   - Affichage des statistiques Olution

3. **`lang/fr/local_question_diagnostic.php`**
   - 32 nouvelles chaînes de traduction en français

4. **`lang/en/local_question_diagnostic.php`**
   - 32 nouvelles chaînes de traduction en anglais

## 🚀 Utilisation

### Prérequis

1. **Créer la catégorie Olution** (si elle n'existe pas déjà)
   - Aller dans : Site administration → Question bank → Categories
   - Créer une catégorie nommée **"Olution"** au niveau **Système**
   - Créer des sous-catégories correspondant aux noms de vos catégories de cours

2. **Exemple de structure attendue :**
   ```
   Olution (Système)
   ├── Mathématiques
   ├── Histoire
   ├── Sciences
   └── Français
   ```

### Procédure

1. **Accéder à la page**
   - Dashboard → Cliquer sur "Gérer les doublons Olution →"

2. **Vérifier les statistiques**
   - Consulter le nombre de doublons détectés
   - Vérifier que les questions sont déplaçables

3. **Option A : Déplacement individuel**
   - Parcourir la liste des doublons
   - Cliquer sur "Déplacer" pour une question spécifique
   - Vérifier les détails sur la page de confirmation
   - Confirmer ou annuler

4. **Option B : Déplacement en masse**
   - Cliquer sur "Déplacer toutes les questions (X)"
   - Examiner la liste des catégories affectées
   - ⚠️ **ATTENTION** : Action groupée, vérifier avant de confirmer
   - Confirmer pour lancer le traitement batch

5. **Résultats**
   - Message de succès avec nombre de questions déplacées
   - Les questions sont maintenant dans leurs catégories Olution
   - Les doublons peuvent ensuite être supprimés manuellement si souhaité

## 🔧 Technique

### Architecture Moodle 4.5

Compatible avec la nouvelle architecture de Question Bank :
- Utilise `question_bank_entries.questioncategoryid` pour le déplacement
- Compatible avec `question_versions` (versioning des questions)
- Requêtes optimisées avec JOINs appropriés

### Algorithme de détection

```php
Pour chaque catégorie de cours :
  1. Trouver la catégorie Olution correspondante (par nom)
  2. Si pas de correspondance → Ignorer
  3. Si correspondance trouvée :
     - Récupérer toutes les questions de la catégorie cours
     - Pour chaque question :
       - Chercher dans Olution : même nom + même type
       - Calculer similarité du contenu
       - Si similarité ≥ 90% → Marquer comme doublon
```

### Opération de déplacement

```php
Transaction SQL {
  1. Mettre à jour question_bank_entries.questioncategoryid
  2. Logger l'action dans audit_logs
  3. Commit ou Rollback si erreur
}
Purger les caches Moodle
```

## 📊 Performances

- Détection par batch (optimisée avec SQL)
- Pagination des résultats (50 par page par défaut)
- Pas de timeout sur grandes bases (traitement progressif)
- Caches purgés uniquement après modifications réussies

## ⚠️ Limitations connues

1. **Correspondance par nom exact** : Les catégories doivent avoir exactement le même nom (sensible à la casse, mais trim + insensible pour recherche)

2. **Questions sans correspondance** : Les questions dont la catégorie n'existe pas dans Olution sont ignorées (signalées dans les statistiques)

3. **Pas de création automatique** : Le système ne crée PAS de catégories Olution manquantes (conformément à la règle de sécurité)

4. **Seuil de similarité fixe** : 90% actuellement (peut être ajusté dans le code si nécessaire)

## 🧪 Tests recommandés

Avant utilisation en production :

1. **Tester sur environnement de développement**
   - Créer des questions tests en doublon
   - Vérifier la détection
   - Tester le déplacement

2. **Backup de la base de données**
   - TOUJOURS faire un backup avant déplacement en masse

3. **Vérifier les résultats**
   - Après déplacement, vérifier dans la banque de questions Moodle
   - Confirmer que les questions sont bien dans Olution
   - Vérifier qu'elles sont toujours utilisables dans les quiz

## 📝 Logs et traçabilité

Chaque déplacement est enregistré dans les logs d'audit :
- Type d'action : `question_moved_to_olution`
- Détails : ID question, catégorie cible, nom catégorie
- Consultable via : Dashboard → Logs d'Audit

## 🔄 Compatibilité

- **Moodle** : 4.5+ (architecture Question Bank nouvelle génération)
- **PHP** : 7.4+
- **Base de données** : MySQL 8.0+, MariaDB 10.6+, PostgreSQL 13+

## 💡 Cas d'usage

### Scénario 1 : Nettoyage après import de cours
Un enseignant a importé un cours contenant des questions qui existent déjà dans Olution.
→ Utiliser cette fonctionnalité pour déplacer les doublons vers Olution et supprimer les versions du cours.

### Scénario 2 : Centralisation des questions
Plusieurs cours ont créé leurs propres versions de questions communes.
→ Déplacer toutes les versions vers Olution pour centraliser la gestion.

### Scénario 3 : Migration progressive
Migrer petit à petit les questions des cours vers une banque centrale.
→ Utiliser le déplacement individuel pour migrer question par question avec contrôle.

## 🆘 Support

En cas de problème :
1. Vérifier que la catégorie "Olution" existe au niveau Système
2. Vérifier les logs d'audit pour voir les erreurs
3. Purger les caches Moodle
4. Consulter les logs de debugging Moodle

## 📜 Version

- **Version** : v1.10.4
- **Date** : Octobre 2025
- **Auteur** : Plugin Question Diagnostic
- **License** : GPL v3+

