# Fonctionnalité : Détection et Réparation des Liens Cassés

## Vue d'ensemble

Cette fonctionnalité permet de détecter automatiquement les questions de la banque de questions Moodle qui contiennent des liens cassés vers des images ou des fichiers manquants dans moodledata.

## Fonctionnalités principales

### 1. Détection automatique des liens cassés

Le système analyse tous les types de questions et détecte :
- **Images manquantes** : Balises `<img>` pointant vers des fichiers inexistants
- **Fichiers pluginfile manquants** : Liens vers des fichiers stockés dans moodledata qui n'existent plus
- **Images de fond manquantes** : Pour les questions de type "drag and drop"

### 2. Support des types de questions

La détection fonctionne avec tous les types de questions standards et plugins tiers :
- Questions à choix multiples (multichoice)
- Vrai/Faux (truefalse)
- Réponse courte (shortanswer)
- **Drag and drop sur image** (ddimageortext) - Plugin tiers
- **Drag and drop markers** (ddmarker)
- **Drag and drop dans le texte** (ddwtos)
- Tous les autres types avec contenu HTML

### 3. Analyse complète

Le système vérifie les liens dans :
- Texte de la question (`questiontext`)
- Feedback général (`generalfeedback`)
- Réponses (`answers`)
- Feedback des réponses (`answer feedback`)
- Images de fond pour drag and drop (`bgimage`)
- Labels des éléments drag and drop

### 4. Interface utilisateur

#### Menu principal
- **Vue d'ensemble globale** : Statistiques rapides sur les catégories et les questions
- **Deux outils principaux** :
  1. Gestion des catégories (existant)
  2. Vérification des liens (nouveau)

#### Page de vérification des liens
- **Dashboard statistiques** :
  - Total de questions
  - Questions avec liens cassés
  - Total de liens cassés
  - Santé globale (pourcentage de questions OK)
  
- **Répartition par type** : Nombre de questions problématiques par type (multichoice, drag and drop, etc.)

- **Filtres** :
  - Recherche par nom de question, ID ou catégorie
  - Filtre par type de question

- **Tableau détaillé** pour chaque question problématique :
  - ID et nom de la question
  - Type de question
  - Catégorie (avec lien vers la banque de questions)
  - Nombre de liens cassés
  - Détails des liens cassés (champ, URL, raison)
  - Lien vers la question dans la banque de questions
  - Bouton de réparation

### 5. Options de réparation

Pour chaque lien cassé, plusieurs options sont disponibles :

#### Suppression de la référence
- Remplace le lien cassé par le texte `[Image supprimée]`
- Solution de dernier recours
- Confirmation requise

#### Recherche de fichiers similaires (future)
- Recherche dans moodledata des fichiers ayant le même nom
- Propose des suggestions de remplacement
- Permet de recréer le lien avec un fichier existant

## Architecture technique

### Fichiers créés

```
local/question_diagnostic/
├── classes/
│   ├── category_manager.php (existant)
│   └── question_link_checker.php (NOUVEAU)
├── index.php (modifié - menu principal)
├── categories.php (NOUVEAU - ancienne fonction de index.php)
├── broken_links.php (NOUVEAU)
├── lang/
│   ├── fr/
│   │   └── local_question_diagnostic.php (mis à jour)
│   └── en/
│       └── local_question_diagnostic.php (mis à jour)
└── FEATURE_BROKEN_LINKS.md (ce fichier)
```

### Classe question_link_checker

La classe `question_link_checker` fournit les méthodes suivantes :

#### Méthodes publiques

```php
// Récupère toutes les questions avec des liens cassés
public static function get_questions_with_broken_links()

// Vérifie les liens dans une question spécifique
public static function check_question_links($question)

// Obtient les statistiques globales sur les liens cassés
public static function get_global_stats()

// Génère l'URL vers la banque de questions pour une question
public static function get_question_bank_url($question, $category)

// Tente de réparer un lien cassé
public static function attempt_repair($questionid, $field, $broken_url)

// Supprime une référence cassée d'une question
public static function remove_broken_link($questionid, $field, $broken_url)
```

#### Méthodes privées

```php
// Extrait les liens d'images depuis un texte HTML
private static function extract_image_links($text)

// Extrait les liens pluginfile.php depuis un texte
private static function extract_pluginfile_links($text)

// Vérifie si un fichier existe
private static function verify_file_exists($url, $questionid)

// Vérifie si un pluginfile existe
private static function verify_pluginfile_exists($url, $questionid)

// Récupère tous les fichiers d'une question
private static function get_all_question_files($questionid)

// Récupère les fichiers d'une zone spécifique
private static function get_question_files($questionid, $filearea)

// Cherche des fichiers similaires dans moodledata
private static function find_similar_files($filename, $questionid)
```

## Processus de détection

1. **Récupération des questions** : Toutes les questions sont récupérées de la table `mdl_question`

2. **Analyse par question** :
   - Récupération des champs texte (questiontext, generalfeedback)
   - Récupération des réponses et feedbacks
   - Vérification du type de question pour les champs spécifiques

3. **Extraction des liens** :
   - Analyse des balises `<img>` pour les images
   - Recherche des liens `pluginfile.php`
   - Vérification des images de fond pour drag and drop

4. **Vérification de l'existence** :
   - Récupération du contexte de la question
   - Recherche dans la table `mdl_files`
   - Comparaison des noms de fichiers

5. **Rapport des résultats** :
   - Liste des liens cassés par question
   - Détails du champ concerné et raison du problème

## Limitations connues

1. **Liens externes** : Les liens vers des images hébergées en dehors de Moodle ne sont pas vérifiés (considérés comme valides)

2. **Performance** : Sur une base de données très volumineuse (>10 000 questions), le processus peut prendre plusieurs secondes

3. **Réparation automatique** : La réparation automatique est limitée à la suppression de la référence. Le remplacement automatique nécessiterait une intervention manuelle

4. **Types de questions personnalisés** : Les types de questions de plugins tiers non standards peuvent nécessiter une adaptation du code

## Recommandations d'utilisation

### Avant utilisation
1. **Sauvegarde** : Toujours faire une sauvegarde complète avant de supprimer des références
2. **Test** : Tester sur une copie de développement d'abord
3. **Documentation** : Noter les questions problématiques avant intervention

### Processus recommandé
1. **Identifier** : Utiliser l'outil pour identifier toutes les questions avec liens cassés
2. **Analyser** : Examiner les questions dans la banque de questions
3. **Réparer manuellement** si possible :
   - Réuploader les images manquantes
   - Corriger les liens dans l'éditeur de question
4. **Supprimer la référence** seulement si la réparation manuelle est impossible

### Maintenance régulière
- Vérifier les liens une fois par mois
- Après une migration ou restauration de cours
- Après une mise à jour majeure de Moodle

## Cas d'usage

### Cas 1 : Migration de serveur
Après une migration, certains fichiers peuvent ne pas avoir été transférés correctement. L'outil permet de détecter rapidement toutes les questions affectées.

### Cas 2 : Nettoyage de base de données
Lors du nettoyage d'anciens cours, certaines questions peuvent référencer des fichiers supprimés. L'outil aide à maintenir l'intégrité de la banque de questions.

### Cas 3 : Restauration partielle
Lors de la restauration d'une sauvegarde partielle, certains fichiers peuvent manquer. L'outil identifie les questions nécessitant une attention.

### Cas 4 : Questions partagées entre contextes
Des questions importées d'un contexte à un autre peuvent avoir des liens cassés si les fichiers n'ont pas été copiés.

## Développements futurs possibles

1. **Réparation automatique intelligente** :
   - Recherche avancée de fichiers similaires
   - Correspondance par hash de contenu
   - Proposition de remplacements automatiques

2. **Export/Import de corrections** :
   - Export de la liste des corrections à effectuer
   - Import de corrections en masse via CSV

3. **Historique des réparations** :
   - Journal des modifications effectuées
   - Possibilité d'annuler les modifications

4. **Notifications** :
   - Alertes automatiques lors de la détection de nouveaux liens cassés
   - Rapports réguliers par email

5. **API REST** :
   - Intégration avec d'autres outils
   - Vérification automatisée via scripts

## Support et maintenance

Pour toute question ou problème :
1. Vérifier les logs Moodle dans Administration > Rapports > Journaux
2. Activer le mode débogage pour plus de détails
3. Consulter la documentation Moodle sur la gestion des fichiers

## Licence

Ce plugin est distribué sous licence GNU GPL v3 ou ultérieure, comme Moodle.

