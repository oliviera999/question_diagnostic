# 🔧 Fonctionnalité : Réparation Automatique des Fichiers Orphelins

## 🎯 Objectif

Ajouter des capacités de **réparation intelligente et sécurisée** des fichiers orphelins, au-delà de la simple suppression/archivage.

**Principe** : Tenter de restaurer automatiquement les liens entre fichiers orphelins et leur contenu parent, quand c'est possible et sûr.

---

## 🔍 Types de Réparations Possibles

### 1. 🔗 Réassociation par Contenthash (Haute Fiabilité)

**Problème détecté** : Fichier orphelin existe en BDD mais le parent est manquant

**Solution** : Chercher un autre enregistrement avec le même `contenthash`

```php
// Exemple : Fichier orphelin avec contenthash abc123...
// Recherche d'autres fichiers avec le même contenthash
SELECT * FROM mdl_files 
WHERE contenthash = 'abc123...' 
  AND id != [orphan_id]
  AND component IS NOT NULL
  AND itemid IN (SELECT id FROM mdl_question)
LIMIT 1;

// Si trouvé → Proposer de réassocier le fichier orphelin au même parent
```

**Fiabilité** : 95% (même contenu = même fichier)

**Cas d'usage** :
- Restauration partielle qui a dupliqué les fichiers
- Import/export qui a créé des doublons
- Migration avec copies multiples

### 2. 📝 Réattribution par Nom de Fichier (Fiabilité Moyenne)

**Problème** : Fichier orphelin avec un nom unique

**Solution** : Chercher dans les questions/ressources qui référencent ce nom dans leur HTML

```php
// Recherche de questions contenant le nom du fichier
SELECT q.id, q.name, q.questiontext 
FROM mdl_question q
WHERE q.questiontext LIKE '%[filename]%'
  OR q.generalfeedback LIKE '%[filename]%';

// Si 1 seul résultat → Haute confiance
// Si plusieurs → Proposer liste de candidats
```

**Fiabilité** : 70% (dépend de l'unicité du nom)

**Cas d'usage** :
- Liens cassés après suppression accidentelle
- Fichiers mal migrés avec références HTML intactes

### 3. 🔄 Réassociation par Contexte (Fiabilité Moyenne-Élevée)

**Problème** : Fichier avec `contextid` valide mais `itemid` invalide

**Solution** : Trouver des éléments candidats dans le même contexte

```php
// Exemple pour questions
SELECT q.id, q.name, 
       LEVENSHTEIN(q.name, '[filename]') as distance
FROM mdl_question q
INNER JOIN mdl_question_categories qc ON qc.id = q.category
WHERE qc.contextid = [file_contextid]
  AND q.id NOT IN (
      SELECT itemid FROM mdl_files 
      WHERE component = 'question' 
        AND filearea = 'questiontext'
  )
ORDER BY distance ASC
LIMIT 5;

// Proposer les 5 meilleurs candidats
```

**Fiabilité** : 60-80% (dépend du contexte)

**Cas d'usage** :
- Suppression accidentelle suivie de recréation
- Questions réimportées dans le même contexte

### 4. 🗂️ Création de Question de Récupération (Faible Risque)

**Problème** : Fichier orphelin sans parent évident

**Solution** : Créer une question "stub" pour héberger le fichier

```php
// Créer une catégorie "Fichiers Récupérés"
$category = create_recovery_category('Recovered Files - [Date]');

// Créer une question de type "description" (pas de scoring)
$question = new stdClass();
$question->name = 'Recovered: ' . $orphan_file->filename;
$question->questiontext = '<p>Fichier récupéré automatiquement</p>';
$question->qtype = 'description';
$question->category = $category->id;

// Sauvegarder et réassocier le fichier
$question_id = save_question($question);
reassociate_file($orphan_file->id, $question_id);
```

**Fiabilité** : 100% (pas de perte, fichier préservé)

**Cas d'usage** :
- Dernier recours avant suppression
- Préservation du contenu pour audit ultérieur
- Fichiers à valeur patrimoniale

### 5. 🔀 Fusion de Doublons par Contenthash

**Problème** : Plusieurs enregistrements pour le même fichier physique

**Solution** : Garder 1 seul enregistrement, mettre à jour les références

```php
// Identifier les doublons
SELECT contenthash, COUNT(*) as count
FROM mdl_files
WHERE filename != '.'
GROUP BY contenthash
HAVING count > 1;

// Pour chaque groupe de doublons
// - Garder l'enregistrement le plus récent avec parent valide
// - Supprimer les autres enregistrements BDD
// - Mettre à jour les références HTML si nécessaire
```

**Fiabilité** : 90% (même contenthash = même fichier)

**Cas d'usage** :
- Nettoyage après migrations multiples
- Optimisation espace disque

---

## 🎨 Interface de Réparation

### Colonne "Réparation Possible" dans le Tableau

Ajouter une colonne avec icône indicatrice :

| Icône | Signification | Action |
|-------|---------------|--------|
| 🟢 ✅ | Réparation haute fiabilité (>90%) | Bouton "Réparer" |
| 🟡 🔧 | Réparation possible (60-90%) | Bouton "Proposer" |
| 🔴 ❌ | Pas de réparation évidente | Bouton "Archiver/Supprimer" |

### Modal de Réparation Détaillé

Quand on clique sur "Réparer" ou "Proposer" :

```
┌─────────────────────────────────────────────────┐
│ 🔧 Options de Réparation                        │
├─────────────────────────────────────────────────┤
│                                                  │
│ Fichier : image.jpg (250 KB)                   │
│ ID : 12345                                       │
│ Raison : Question parent supprimée (ID: 999)    │
│                                                  │
│ ━━━ Réparations Possibles ━━━                   │
│                                                  │
│ 🟢 Option 1 : Réassociation par contenthash     │
│    Confiance : 95%                               │
│    ┌─────────────────────────────────────────┐  │
│    │ Un fichier identique (même contenthash) │  │
│    │ existe déjà pour la question #1042      │  │
│    │                                          │  │
│    │ Question : "Introduction à Python"      │  │
│    │ Catégorie : Programmation > Débutant    │  │
│    │ Dernière modif : 2025-10-10             │  │
│    └─────────────────────────────────────────┘  │
│    [✓] Réassocier à la question #1042           │
│                                                  │
│ 🟡 Option 2 : Réattribution par nom             │
│    Confiance : 70%                               │
│    ┌─────────────────────────────────────────┐  │
│    │ 3 questions contiennent "image.jpg"     │  │
│    │ dans leur HTML :                         │  │
│    │                                          │  │
│    │ • Question #1050 (87% match)            │  │
│    │ • Question #1023 (65% match)            │  │
│    │ • Question #987 (43% match)             │  │
│    └─────────────────────────────────────────┘  │
│    [○] Sélectionner un candidat...              │
│                                                  │
│ 🟢 Option 3 : Création question récupération    │
│    Confiance : 100% (sûr, pas de perte)         │
│    ┌─────────────────────────────────────────┐  │
│    │ Créer une question "Recovered: ..."     │  │
│    │ dans la catégorie "Fichiers Récupérés"  │  │
│    │ pour préserver ce fichier               │  │
│    └─────────────────────────────────────────┘  │
│    [○] Créer question stub                      │
│                                                  │
│ ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━  │
│                                                  │
│ ⚠️ Note : Vous pouvez tester avec Dry-Run       │
│                                                  │
│ [Dry-Run (Simuler)] [Réparer Maintenant] [Annuler] │
└─────────────────────────────────────────────────┘
```

### Mode "Réparation Automatique en Masse"

Pour traiter plusieurs fichiers d'un coup :

```
┌─────────────────────────────────────────────────┐
│ 🔧 Réparation Automatique en Masse              │
├─────────────────────────────────────────────────┤
│                                                  │
│ 42 fichiers sélectionnés                        │
│                                                  │
│ Analyse des réparations possibles :             │
│                                                  │
│ 🟢 18 fichiers : Haute fiabilité (>90%)         │
│    → Réassociation par contenthash              │
│                                                  │
│ 🟡 12 fichiers : Fiabilité moyenne (60-90%)     │
│    → Réattribution par nom (1 candidat)         │
│                                                  │
│ 🟡 7 fichiers : Fiabilité moyenne               │
│    → Réattribution par nom (plusieurs candidats)│
│    ⚠️ Nécessite sélection manuelle              │
│                                                  │
│ 🔴 5 fichiers : Aucune réparation automatique   │
│    → Proposition création questions stub        │
│                                                  │
│ ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━  │
│                                                  │
│ Actions :                                        │
│ [✓] Réparer automatiquement les cas haute fiabilité (18) │
│ [✓] Réparer les cas fiabilité moyenne avec 1 candidat (12) │
│ [○] Créer questions stub pour les cas restants (5) │
│ [○] Ignorer les cas ambigus (7)                 │
│                                                  │
│ [Prévisualiser] [Lancer la Réparation] [Annuler] │
└─────────────────────────────────────────────────┘
```

---

## 🔒 Sécurité et Validation

### Règles de Sécurité

1. **Confirmation obligatoire** même pour réparations automatiques
2. **Mode Dry-Run** TOUJOURS disponible
3. **Backup automatique** : Sauvegarder état avant dans table temporaire
4. **Rollback possible** : Possibilité d'annuler dans les 24h
5. **Logs ultra-détaillés** : Chaque réparation loggée avec justification

### Vérifications Avant Réparation

```php
function can_safely_repair($orphan_file, $target_parent) {
    // 1. Vérifier que le parent cible existe et est valide
    if (!parent_exists_and_valid($target_parent)) {
        return false;
    }
    
    // 2. Vérifier que le fichier n'est pas déjà associé
    if (file_already_associated($orphan_file, $target_parent)) {
        return false;
    }
    
    // 3. Vérifier compatibilité de contexte
    if (!contexts_compatible($orphan_file->contextid, $target_parent->contextid)) {
        return false;
    }
    
    // 4. Vérifier que le parent n'a pas déjà ce fichier
    if (parent_has_file_with_same_name($target_parent, $orphan_file->filename)) {
        return false; // Éviter les doublons
    }
    
    // 5. Vérifier droits d'accès
    if (!user_can_modify_parent($target_parent)) {
        return false;
    }
    
    return true;
}
```

---

## 💾 Implémentation Technique

### Nouvelle Classe : `orphan_file_repairer.php`

```php
<?php
namespace local_question_diagnostic;

class orphan_file_repairer {
    
    /**
     * Analyse les options de réparation pour un fichier
     *
     * @param object $orphan_file Fichier orphelin
     * @return array Options de réparation triées par confiance
     */
    public static function analyze_repair_options($orphan_file) {
        $options = [];
        
        // Option 1 : Réassociation par contenthash
        $contenthash_match = self::find_by_contenthash($orphan_file);
        if ($contenthash_match) {
            $options[] = [
                'type' => 'contenthash',
                'confidence' => 95,
                'target' => $contenthash_match,
                'description' => 'Fichier identique trouvé',
                'action' => 'reassociate'
            ];
        }
        
        // Option 2 : Réattribution par nom
        $name_matches = self::find_by_filename($orphan_file);
        if (!empty($name_matches)) {
            $confidence = count($name_matches) == 1 ? 80 : 60;
            $options[] = [
                'type' => 'filename',
                'confidence' => $confidence,
                'targets' => $name_matches,
                'description' => count($name_matches) . ' candidat(s) trouvé(s)',
                'action' => 'reassign'
            ];
        }
        
        // Option 3 : Réassociation par contexte
        $context_matches = self::find_by_context($orphan_file);
        if (!empty($context_matches)) {
            $options[] = [
                'type' => 'context',
                'confidence' => 70,
                'targets' => $context_matches,
                'description' => 'Parents potentiels dans le même contexte',
                'action' => 'reassign'
            ];
        }
        
        // Option 4 : Création question stub (toujours possible)
        $options[] = [
            'type' => 'recovery_stub',
            'confidence' => 100,
            'description' => 'Création question de récupération',
            'action' => 'create_stub'
        ];
        
        // Trier par confiance décroissante
        usort($options, function($a, $b) {
            return $b['confidence'] - $a['confidence'];
        });
        
        return $options;
    }
    
    /**
     * Cherche par contenthash (haute fiabilité)
     */
    private static function find_by_contenthash($orphan_file) {
        global $DB;
        
        $sql = "SELECT f.*, 
                       CASE 
                           WHEN f.component = 'question' THEN q.name
                           WHEN f.component = 'mod_label' THEN l.name
                           -- etc.
                       END as parent_name
                FROM {files} f
                LEFT JOIN {question} q ON f.component = 'question' AND f.itemid = q.id
                LEFT JOIN {label} l ON f.component = 'mod_label' AND f.itemid = l.id
                WHERE f.contenthash = :hash
                  AND f.id != :fileid
                  AND f.filename != '.'
                  AND f.component IS NOT NULL
                  AND (
                      (f.component = 'question' AND q.id IS NOT NULL)
                      OR (f.component = 'mod_label' AND l.id IS NOT NULL)
                      -- Vérifier que le parent existe
                  )
                LIMIT 1";
        
        return $DB->get_record_sql($sql, [
            'hash' => $orphan_file->contenthash,
            'fileid' => $orphan_file->id
        ]);
    }
    
    /**
     * Cherche par nom de fichier dans HTML
     */
    private static function find_by_filename($orphan_file) {
        global $DB;
        
        $filename = $orphan_file->filename;
        
        // Recherche dans questions
        $sql = "SELECT id, name, questiontext,
                       CASE 
                           WHEN questiontext LIKE :exact THEN 100
                           WHEN questiontext LIKE :partial THEN 70
                           ELSE 50
                       END as match_score
                FROM {question}
                WHERE (questiontext LIKE :search1 
                       OR generalfeedback LIKE :search2)
                ORDER BY match_score DESC
                LIMIT 5";
        
        $results = $DB->get_records_sql($sql, [
            'exact' => '%src="' . $filename . '"%',
            'partial' => '%' . $filename . '%',
            'search1' => '%' . $filename . '%',
            'search2' => '%' . $filename . '%'
        ]);
        
        return $results;
    }
    
    /**
     * Cherche par contexte (même contexte, parent manquant)
     */
    private static function find_by_context($orphan_file) {
        global $DB;
        
        // Exemple pour questions
        if ($orphan_file->component === 'question') {
            $sql = "SELECT q.id, q.name, qc.name as category_name,
                           LEVENSHTEIN(q.name, :filename) as distance
                    FROM {question} q
                    INNER JOIN {question_categories} qc ON qc.id = q.category
                    WHERE qc.contextid = :contextid
                      AND q.id NOT IN (
                          SELECT DISTINCT itemid FROM {files}
                          WHERE component = 'question' 
                            AND filearea = 'questiontext'
                            AND filename != '.'
                      )
                    ORDER BY distance ASC
                    LIMIT 5";
            
            return $DB->get_records_sql($sql, [
                'contextid' => $orphan_file->contextid,
                'filename' => $orphan_file->filename
            ]);
        }
        
        return [];
    }
    
    /**
     * Exécute la réparation
     */
    public static function execute_repair($orphan_file, $option, $dry_run = false) {
        global $DB;
        
        // Vérifications de sécurité
        if (!self::can_safely_repair($orphan_file, $option)) {
            return [
                'success' => false,
                'message' => 'Réparation non sûre - vérifications échouées'
            ];
        }
        
        if ($dry_run) {
            return [
                'success' => true,
                'message' => '[DRY-RUN] Réparation SERAIT effectuée',
                'details' => $option
            ];
        }
        
        // Sauvegarde avant modification
        self::backup_file_record($orphan_file);
        
        try {
            switch ($option['action']) {
                case 'reassociate':
                    return self::reassociate_file($orphan_file, $option['target']);
                    
                case 'reassign':
                    return self::reassign_file($orphan_file, $option['target']);
                    
                case 'create_stub':
                    return self::create_recovery_question($orphan_file);
                    
                default:
                    return ['success' => false, 'message' => 'Action inconnue'];
            }
        } catch (Exception $e) {
            // Rollback en cas d'erreur
            self::restore_file_record($orphan_file->id);
            return [
                'success' => false,
                'message' => 'Erreur : ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Réassocie un fichier à un parent existant
     */
    private static function reassociate_file($orphan_file, $target) {
        global $DB;
        
        // Mettre à jour l'enregistrement
        $update = new stdClass();
        $update->id = $orphan_file->id;
        $update->component = $target->component;
        $update->filearea = $target->filearea;
        $update->itemid = $target->itemid;
        $update->contextid = $target->contextid;
        
        $DB->update_record('files', $update);
        
        // Logger
        self::log_repair('reassociate', $orphan_file, $target);
        
        return [
            'success' => true,
            'message' => 'Fichier réassocié avec succès',
            'details' => $target
        ];
    }
    
    /**
     * Crée une question de récupération
     */
    private static function create_recovery_question($orphan_file) {
        global $DB, $USER;
        
        // Créer ou récupérer la catégorie "Fichiers Récupérés"
        $category = self::get_or_create_recovery_category();
        
        // Créer la question
        $question = new stdClass();
        $question->category = $category->id;
        $question->name = 'Recovered: ' . $orphan_file->filename;
        $question->questiontext = '<p>Fichier récupéré automatiquement le ' . date('Y-m-d H:i:s') . '</p>';
        $question->questiontextformat = FORMAT_HTML;
        $question->generalfeedback = '';
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->qtype = 'description'; // Pas de scoring
        $question->defaultmark = 0;
        $question->penalty = 0;
        $question->length = 0;
        $question->stamp = make_unique_id_code();
        $question->timecreated = time();
        $question->timemodified = time();
        $question->createdby = $USER->id;
        $question->modifiedby = $USER->id;
        
        // Sauvegarder via API Question Bank
        $question_id = question_save($question);
        
        // Réassocier le fichier
        $update = new stdClass();
        $update->id = $orphan_file->id;
        $update->component = 'question';
        $update->filearea = 'questiontext';
        $update->itemid = $question_id;
        
        $DB->update_record('files', $update);
        
        // Logger
        self::log_repair('create_stub', $orphan_file, ['question_id' => $question_id]);
        
        return [
            'success' => true,
            'message' => 'Question de récupération créée avec succès',
            'question_id' => $question_id,
            'category' => $category->name
        ];
    }
}
```

---

## 📊 Statistiques de Réparation

### Dashboard avec Indicateurs

Ajouter au dashboard existant :

```
┌─────────────────────────────────────────────────┐
│ 🔧 Analyse de Réparabilité                      │
├─────────────────────────────────────────────────┤
│                                                  │
│ 🟢 Haute fiabilité (>90%)     : 18 fichiers     │
│    → Réparation automatique recommandée          │
│                                                  │
│ 🟡 Fiabilité moyenne (60-90%) : 24 fichiers     │
│    → Réparation avec validation recommandée      │
│                                                  │
│ 🔴 Pas de réparation évidente  : 8 fichiers     │
│    → Archivage ou suppression                    │
│                                                  │
│ [Lancer l'Analyse Détaillée]                    │
└─────────────────────────────────────────────────┘
```

---

## 🎯 Roadmap d'Implémentation

### Phase 1 (Immédiate) : Analyse

- [ ] Créer `classes/orphan_file_repairer.php`
- [ ] Implémenter `analyze_repair_options()`
- [ ] Implémenter `find_by_contenthash()`
- [ ] Ajouter colonne "Réparation Possible" au tableau

### Phase 2 : Réparations Simples

- [ ] Implémenter `find_by_filename()`
- [ ] Implémenter `reassociate_file()`
- [ ] Créer modal de réparation
- [ ] Tests unitaires

### Phase 3 : Réparations Avancées

- [ ] Implémenter `find_by_context()`
- [ ] Implémenter `create_recovery_question()`
- [ ] Mode réparation en masse
- [ ] Système de rollback

### Phase 4 : Intelligence Artificielle (Futur)

- [ ] Machine learning pour améliorer suggestions
- [ ] Analyse sémantique du contenu
- [ ] Recommandations proactives

---

## ⚠️ Avertissements

1. **Ne jamais forcer une réparation** si confiance < 60%
2. **Toujours proposer Dry-Run** avant réparation
3. **Logs ultra-détaillés** pour traçabilité
4. **Rollback possible** dans les 24h
5. **Validation admin** pour toute réparation en masse

---

**Version** : 1.0 - Extension v1.10.0  
**Date** : 14 octobre 2025  
**Statut** : 📋 Planifié pour v1.11.0

