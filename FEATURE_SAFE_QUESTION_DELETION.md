# Fonctionnalité : Suppression Sécurisée de Questions

**Version** : v1.9.0  
**Date** : Octobre 2025  
**Statut** : ✅ Implémenté

## 📋 Vue d'ensemble

Cette fonctionnalité implémente un système de **suppression sécurisée** pour les questions individuelles, avec des **règles de protection strictes** pour éviter la perte de contenu pédagogique important.

## 🛡️ Règles de Protection

Le plugin applique **3 règles de protection strictes** :

### 1. ✅ Questions Utilisées = PROTÉGÉES

Une question est **protégée** si elle est :
- Utilisée dans au moins **un quiz actif**
- A au moins **une tentative enregistrée**

**Raison** : Ces questions sont actuellement utilisées dans votre enseignement et ne doivent JAMAIS être supprimées.

**Exemple** :
```
Question ID: 12345
- Utilisée dans : 3 quiz
- Tentatives : 45
→ PROTECTION ACTIVE ❌ Suppression impossible
```

### 2. ✅ Questions Uniques = PROTÉGÉES

Une question est **protégée** si elle n'a **aucun doublon** dans la base de données (nom, type et texte uniques).

**Raison** : La suppression d'une question unique entraînerait une perte définitive de contenu pédagogique.

**Exemple** :
```
Question ID: 67890
- Nom : "Question unique sur les intégrales"
- Doublons détectés : 0
→ PROTECTION ACTIVE ❌ Suppression impossible
```

### 3. ⚠️ Questions en Doublon ET Inutilisées = SUPPRIMABLES

Une question peut être supprimée **UNIQUEMENT** si :
- ✅ Elle a **au moins un doublon** (même nom, type et texte)
- ✅ Elle est **inutilisée** (pas dans un quiz, pas de tentatives)

**Exemple** :
```
Question ID: 11111
- Nom : "Question dupliquée"
- Doublons détectés : 3
- Utilisée dans quiz : 0
- Tentatives : 0
→ ✅ Suppression autorisée
```

## 🎯 Cas d'Usage

### Scénario 1 : Questions dupliquées dans des contextes inutiles

**Problème** : Vous avez des questions qui ont été dupliquées automatiquement dans des contextes où elles ne sont pas utilisées (ex : importation multiple, duplication accidentelle).

**Solution** : Le plugin détecte ces doublons inutilisés et permet de les supprimer en toute sécurité, tout en conservant les versions utilisées.

**Exemple** :
```
Question "Calcul d'intégrale" :
- Version A (ID: 100) → Utilisée dans Quiz "Maths 101" ✅ PROTÉGÉE
- Version B (ID: 101) → Contexte système, inutilisée ✅ SUPPRIMABLE
- Version C (ID: 102) → Contexte cours, inutilisée ✅ SUPPRIMABLE
```

### Scénario 2 : Nettoyage de base de données volumineuse

**Problème** : Votre base contient 30 000+ questions avec de nombreux doublons qui ralentissent les performances.

**Solution** : Utilisez le mode "Doublons Utilisés" pour identifier rapidement les groupes de questions où seules certaines versions sont utilisées, puis supprimez les versions inutilisées.

## 🔍 Interface Utilisateur

### 1. Liste des Questions

Dans `questions_cleanup.php`, chaque question affiche un bouton :
- **🗑️ Supprimer** (rouge) : Si la question peut être supprimée
- **🔒 Protégée** (gris) : Si la question est protégée, avec tooltip expliquant la raison

### 2. Page de Vérification

Si vous tentez de supprimer une question protégée :
```
🛑 SUPPRESSION INTERDITE

❌ Cette question ne peut pas être supprimée

Raison : Question utilisée

Détails de l'utilisation :
- Quiz utilisant cette question : 2
  • Quiz "Maths 101"
  • Quiz "Examen Final"
- Tentatives enregistrées : 145

🛡️ Règles de Protection
1. ✅ Les questions utilisées sont PROTÉGÉES
2. ✅ Les questions uniques sont PROTÉGÉES
3. ⚠️ Seules les questions en doublon ET inutilisées peuvent être supprimées
```

### 3. Page de Confirmation

Si la suppression est autorisée, une page de confirmation affiche :
- Détails de la question à supprimer
- Nombre de doublons conservés
- Avertissement sur l'irréversibilité
- Boutons "Confirmer" et "Annuler"

## 💻 Architecture Technique

### Méthode : `can_delete_question()`

**Fichier** : `classes/question_analyzer.php`

**Signature** :
```php
public static function can_delete_question($questionid) : object
```

**Retour** :
```php
{
    can_delete: bool,        // true si suppression autorisée
    reason: string,          // Raison (ex: "Question utilisée")
    details: array           // Détails complémentaires
}
```

**Algorithme** :
```php
1. Récupérer la question
2. Vérifier usage (quiz + tentatives)
   → Si utilisée : REFUSER (raison: "Question utilisée")
3. Vérifier doublons (find_exact_duplicates)
   → Si aucun doublon : REFUSER (raison: "Question unique")
4. Si on arrive ici : AUTORISER (raison: "Doublon inutilisé")
```

### Méthode : `delete_question_safe()`

**Fichier** : `classes/question_analyzer.php`

**Signature** :
```php
public static function delete_question_safe($questionid) : bool|string
```

**Retour** :
- `true` si succès
- `string` (message d'erreur) si échec

**Algorithme** :
```php
1. Vérifier avec can_delete_question()
   → Si non autorisé : ARRÊTER
2. Récupérer la question et sa catégorie
3. Appeler question_delete_question() (API Moodle)
   → Supprime proprement :
     - Entrée dans question_bank_entries
     - Versions dans question_versions
     - Fichiers associés
     - Données spécifiques au type
4. Retourner true
```

### Action : `delete_question.php`

**Fichier** : `actions/delete_question.php`

**Flux** :
```
1. Vérifications sécurité (sesskey, is_siteadmin)
2. can_delete_question()
   → Si non autorisé : Afficher page d'interdiction
3. Si confirm=0 : Afficher page de confirmation
4. Si confirm=1 : Exécuter delete_question_safe()
5. Purger caches + Rediriger
```

## 🔒 Sécurité

### Vérifications Moodle Standard

1. **`require_login()`** : Utilisateur authentifié
2. **`require_sesskey()`** : Protection CSRF
3. **`is_siteadmin()`** : Admin uniquement
4. **Confirmation utilisateur** : Page de confirmation obligatoire

### Vérifications Spécifiques au Plugin

1. **Usage check** : Via `quiz_slots` et `question_attempts`
2. **Duplicate check** : Via `find_exact_duplicates()` (nom + type + texte)
3. **API Moodle** : Utilise `question_delete_question()` pour suppression propre

## 📊 Performance

### Complexité

- `can_delete_question()` : **O(n)** où n = nombre de questions avec même nom
  - Vérification usage : 2 requêtes SQL
  - Vérification doublons : 1 requête SQL
  
- `delete_question_safe()` : **O(1)**
  - 2 requêtes pour vérification + 1 appel API

### Optimisations

- **Cache** : Les résultats de `get_question_usage()` sont mis en cache
- **Pagination** : L'interface charge seulement N questions à la fois
- **Filtre ciblé** : Mode "Doublons Utilisés" pour réduire la charge

## 🧪 Tests Recommandés

### Test 1 : Protection Question Utilisée

1. Créer une question dans un quiz actif
2. Tenter de la supprimer
3. ✅ **Attendu** : Message "Question utilisée" + détails des quiz

### Test 2 : Protection Question Unique

1. Créer une question unique (sans doublon)
2. Tenter de la supprimer
3. ✅ **Attendu** : Message "Question unique" + explication

### Test 3 : Suppression Autorisée

1. Créer 2 questions identiques (même nom, type, texte)
2. Ne les utiliser dans aucun quiz
3. Tenter de supprimer l'une d'elles
4. ✅ **Attendu** : Page de confirmation → Suppression réussie

### Test 4 : Doublon Partiellement Utilisé

1. Créer 3 questions identiques
2. Ajouter la première dans un quiz
3. Laisser les 2 autres inutilisées
4. ✅ **Attendu** :
   - Version 1 : Protégée (utilisée)
   - Version 2 & 3 : Supprimables

## 📚 Documentation Utilisateur

### Guide Rapide

1. **Accéder à** : `Administration du site > Plugins > Plugins locaux > Question Diagnostic > Statistiques des questions`
2. **Charger les questions** : Cliquer sur "Charger Toutes les Questions" ou "Charger Doublons Utilisés"
3. **Identifier les questions supprimables** :
   - Bouton **🗑️ Supprimer** (rouge) = Supprimable
   - Bouton **🔒 Protégée** (gris) = Protégée
4. **Supprimer** : Cliquer sur "Supprimer" → Confirmer
5. **Vérifier** : La question disparaît de la liste

### FAQ

**Q : Puis-je supprimer une question utilisée dans un quiz archivé ?**  
R : Non. Même les quiz archivés contiennent des données d'historique importantes (tentatives, notes). La question reste protégée.

**Q : Que se passe-t-il si je supprime une question par erreur ?**  
R : La suppression est irréversible. Cependant, si la question a des doublons, les autres versions sont conservées. Vous pouvez recréer la question à partir d'un doublon.

**Q : Comment identifier rapidement les questions à supprimer dans une grande base ?**  
R : Utilisez le mode "Doublons Utilisés" qui affiche uniquement les groupes de questions où au moins une version est utilisée. Filtrez ensuite par "Usage = Inutilisées".

**Q : La suppression est-elle vraiment sécurisée ?**  
R : Oui. Le plugin utilise l'API Moodle officielle (`question_delete_question()`) qui gère proprement :
- Suppression des entrées dans `question_bank_entries`
- Suppression des versions dans `question_versions`
- Suppression des fichiers associés
- Suppression des données spécifiques au type de question

## 🔗 Fichiers Modifiés/Créés

### Nouveaux Fichiers

- `actions/delete_question.php` : Action de suppression avec confirmation
- `FEATURE_SAFE_QUESTION_DELETION.md` : Cette documentation

### Fichiers Modifiés

- `classes/question_analyzer.php` :
  - Méthode `can_delete_question()`
  - Méthode `delete_question_safe()`
- `questions_cleanup.php` :
  - Ajout boutons "Supprimer" / "Protégée"
- `lang/fr/local_question_diagnostic.php` :
  - Chaînes de langue FR
- `lang/en/local_question_diagnostic.php` :
  - Chaînes de langue EN

## 📝 Changelog

### v1.9.0 - Suppression Sécurisée de Questions

**Ajouté** :
- Système de protection avec 3 règles strictes
- Boutons de suppression dans l'interface (uniquement si autorisé)
- Page d'interdiction avec détails de la protection
- Page de confirmation avec informations complètes
- Méthodes `can_delete_question()` et `delete_question_safe()`

**Sécurité** :
- Vérification usage (quiz + tentatives)
- Vérification unicité (protection doublons)
- Confirmation utilisateur obligatoire
- Utilisation API Moodle officielle

## 🎯 Compatibilité

- **Moodle** : 4.5+ (LTS)
- **PHP** : 7.4+
- **Base de données** : MySQL 8.0+, MariaDB 10.6+, PostgreSQL 13+

## 👥 Contributeurs

- Équipe de développement local_question_diagnostic
- Octobre 2025


