# 🧪 Guide de Test - Statistiques des Questions

## Installation et premier test

### 1. Installation du plugin

```bash
# Se placer dans le dossier Moodle
cd /path/to/moodle

# Copier les fichiers du plugin
cp -r moodle_dev-questions local/question_diagnostic

# Vérifier les permissions
chmod -R 755 local/question_diagnostic
chmod -R 644 local/question_diagnostic/*.php
```

### 2. Activation dans Moodle

1. Connectez-vous en tant qu'administrateur
2. Allez dans **Administration du site > Notifications**
3. Le plugin devrait apparaître pour installation
4. Cliquez sur **Mettre à jour la base de données Moodle**
5. Confirmez l'installation

### 3. Premier accès

**Méthode 1 - Via le menu :**
1. Administration du site
2. Local plugins
3. Question Diagnostic
4. Cliquer sur "Analyser les questions →"

**Méthode 2 - URL directe :**
```
https://votre-moodle.com/local/question_diagnostic/questions_cleanup.php
```

---

## ✅ Checklist de test

### Tests basiques (5 min)

- [ ] **Accès à la page**
  - La page se charge sans erreur
  - Le dashboard s'affiche avec 6 cartes
  - Les statistiques sont cohérentes

- [ ] **Dashboard**
  - Carte "Total Questions" affiche un nombre > 0
  - Carte "Questions Utilisées" ≤ Total
  - Carte "Questions Inutilisées" = Total - Utilisées
  - Carte "Questions en Doublon" ≥ 0
  - Carte "Questions Cachées" ≥ 0
  - Carte "Liens Cassés" ≥ 0

- [ ] **Répartition par type**
  - Tous les types présents sont affichés
  - Les pourcentages totalisent ~100%
  - Clic sur un type fonctionne (si implémenté)

### Tests des filtres (10 min)

#### Test 1 : Recherche
```
Action : Taper "test" dans la barre de recherche
Résultat attendu : 
- Seules les questions contenant "test" dans leur nom, ID ou texte s'affichent
- Le compteur se met à jour
- Le filtrage se fait en temps réel (< 1s)
```

#### Test 2 : Filtre par type
```
Action : Sélectionner "multichoice" dans le filtre Type
Résultat attendu :
- Seules les questions de type multichoice s'affichent
- Le compteur affiche le bon nombre
- Les autres lignes sont masquées (display: none)
```

#### Test 3 : Filtre par usage
```
Action : Sélectionner "Inutilisées"
Résultat attendu :
- Seules les questions avec Quiz=0 ET Tentatives=0 s'affichent
- Le nombre correspond à la carte "Questions Inutilisées"
```

#### Test 4 : Filtre par doublons
```
Action : Sélectionner "Avec doublons"
Résultat attendu :
- Seules les questions avec Doublons > 0 s'affichent
- Les badges "Doublons: X" sont visibles et cliquables
```

#### Test 5 : Filtres combinés
```
Action : 
1. Recherche: "question"
2. Type: "multichoice"
3. Usage: "Utilisées"

Résultat attendu :
- Les 3 filtres s'appliquent simultanément (AND)
- Le compteur reflète le résultat final
```

### Tests du tableau (10 min)

#### Test 6 : Tri des colonnes
```
Action : Cliquer sur l'en-tête "ID"
Résultat attendu :
- Première fois : tri croissant (1, 2, 3...)
- Indicateur ▲ apparaît
- Deuxième fois : tri décroissant (3, 2, 1...)
- Indicateur ▼ apparaît
```

Répéter pour :
- [ ] Colonne "Nom" (alphabétique)
- [ ] Colonne "Type" (alphabétique)
- [ ] Colonne "Quiz" (numérique)
- [ ] Colonne "Doublons" (numérique)

#### Test 7 : Gestion des colonnes
```
Action : Cliquer sur le bouton "⚙️ Colonnes"
Résultat attendu :
- Un panneau s'ouvre avec toutes les colonnes
- Les colonnes actuellement affichées sont cochées
- Décocher "ID" → la colonne disparaît du tableau
- Recharger la page → la préférence est conservée (localStorage)
```

#### Test 8 : Liens vers la banque
```
Action : Cliquer sur "👁️ Voir" pour une question
Résultat attendu :
- Nouvel onglet s'ouvre
- URL : /question/edit.php?courseid=X&cat=Y,Z&qid=Q
- La question est présélectionnée dans la banque
```

### Tests des doublons (15 min)

#### Test 9 : Modal des doublons
```
Action : Cliquer sur un badge "Doublons: 2"
Résultat attendu :
- Un modal s'ouvre
- Titre : "Questions en doublon"
- Affiche le nom de la question source
- Liste les 2 doublons avec :
  - ID
  - Nom
  - Type
  - Catégorie
  - Bouton "Voir"
```

#### Test 10 : Vérification de la similarité

**Créer 2 questions similaires :**
1. Créer une question "Test de mathématiques"
2. Dupliquer et renommer en "Test de mathématique" (sans s)
3. Recharger la page de statistiques

```
Résultat attendu :
- Les 2 questions apparaissent avec "Doublons: 1"
- Le score de similarité devrait être > 85%
- Le modal affiche bien les 2 questions liées
```

### Tests de l'export (5 min)

#### Test 11 : Export CSV
```
Action : Cliquer sur "📥 Exporter en CSV"
Résultat attendu :
- Un fichier CSV est téléchargé
- Nom : questions_statistics_YYYY-MM-DD_HH-mm-ss.csv
- Ouvrir dans Excel :
  - 13 colonnes
  - Encodage UTF-8 correct (accents affichés)
  - Toutes les lignes présentes
  - Virgules comme séparateurs
```

### Tests de performance (5 min)

#### Test 12 : Temps de chargement

**Si < 100 questions :**
```
Résultat attendu : < 2 secondes
```

**Si 100-500 questions :**
```
Résultat attendu : < 5 secondes
```

**Si > 1000 questions :**
```
Résultat attendu : < 30 secondes
Action si dépassé :
- Vérifier la configuration PHP (memory_limit, max_execution_time)
- Consulter les logs Moodle
```

#### Test 13 : Réactivité des filtres
```
Action : Taper rapidement plusieurs lettres dans la recherche
Résultat attendu :
- Pas de lag
- Debounce de 300ms fonctionne
- Résultats se mettent à jour après la pause
```

### Tests de sécurité (5 min)

#### Test 14 : Contrôle d'accès
```
Action : Se connecter en tant qu'enseignant (non-admin)
Tenter d'accéder à questions_cleanup.php

Résultat attendu :
- Message d'erreur : "Accès refusé"
- Pas d'affichage de données sensibles
```

#### Test 15 : Protection CSRF
```
Action : 
1. Ouvrir l'inspecteur du navigateur
2. Réseau > Filtrer par XHR
3. Cliquer sur "Exporter en CSV"
4. Observer la requête

Résultat attendu :
- Paramètre "sesskey" présent dans l'URL
- Valeur = token de session actuel
```

---

## 🐛 Résolution de problèmes

### Problème 1 : Page blanche

**Symptôme :** Page blanche, rien ne s'affiche

**Diagnostic :**
```bash
# Vérifier les logs PHP
tail -f /var/log/apache2/error.log

# Ou dans Moodle
# Administration du site > Rapports > Journaux
```

**Solutions possibles :**
1. Erreur de syntaxe PHP → Vérifier les fichiers
2. Mémoire insuffisante → Augmenter `memory_limit` à 512M
3. Timeout → Augmenter `max_execution_time` à 120s

### Problème 2 : Statistiques incohérentes

**Symptôme :** Nombres aberrants dans le dashboard

**Diagnostic :**
```sql
-- Vérifier le nombre de questions
SELECT COUNT(*) FROM mdl_question;

-- Vérifier les quiz
SELECT COUNT(DISTINCT questionid) FROM mdl_quiz_slots;

-- Vérifier les tentatives
SELECT COUNT(DISTINCT questionid) FROM mdl_question_attempts;
```

**Solutions possibles :**
1. Cache Moodle → Purger les caches
2. Base corrompue → Vérifier l'intégrité des tables
3. Plugin non à jour → Réinstaller

### Problème 3 : Doublons non détectés

**Symptôme :** Questions similaires non détectées comme doublons

**Diagnostic :**
```php
// Dans classes/question_analyzer.php, ligne ~158
// Temporairement réduire le seuil
$duplicates = self::find_question_duplicates($question, 0.75); // au lieu de 0.85
```

**Solutions possibles :**
1. Seuil trop élevé → Réduire à 0.75 ou 0.70
2. Textes trop différents → Vérifier l'algorithme de similarité
3. Questions dans catégories différentes → Augmenter le poids du texte

### Problème 4 : Export CSV vide

**Symptôme :** Fichier CSV téléchargé mais vide

**Diagnostic :**
```php
// Dans actions/export.php
// Ajouter avant l'export :
error_log("Nombre de questions à exporter : " . count($questions));
```

**Solutions possibles :**
1. Timeout → Augmenter `max_execution_time`
2. Mémoire → Augmenter `memory_limit`
3. Caractères spéciaux → Vérifier l'encodage UTF-8

### Problème 5 : Colonnes ne se masquent pas

**Symptôme :** Cocher/décocher les colonnes ne fait rien

**Diagnostic :**
```javascript
// Ouvrir la console du navigateur (F12)
// Vérifier les erreurs JavaScript
console.log(localStorage.getItem('qd_column_prefs'));
```

**Solutions possibles :**
1. JavaScript désactivé → Activer JavaScript
2. localStorage bloqué → Vérifier les cookies/stockage
3. Erreur JS → Consulter la console

---

## 📊 Scénarios de test complets

### Scénario 1 : Nettoyage de printemps

**Contexte :** Fin d'année scolaire, besoin de nettoyer la base

**Étapes :**
1. ✅ Accéder à la page de statistiques
2. ✅ Noter le nombre total de questions
3. ✅ Filtrer par "Inutilisées"
4. ✅ Trier par "Date création" (plus anciennes en premier)
5. ✅ Pour chaque question > 1 an et inutilisée :
   - Cliquer sur "Voir"
   - Vérifier le contenu
   - Décider : garder ou supprimer
6. ✅ Exporter la liste des questions supprimées (CSV)
7. ✅ Supprimer manuellement via la banque
8. ✅ Revenir sur la page → Vérifier nouveau total

**Temps estimé :** 30-60 minutes

### Scénario 2 : Chasse aux doublons

**Contexte :** Plusieurs enseignants créent des questions similaires

**Étapes :**
1. ✅ Filtrer par "Avec doublons"
2. ✅ Trier par "Doublons" (décroissant)
3. ✅ Pour chaque groupe de doublons :
   - Cliquer sur le badge
   - Comparer les questions dans le modal
   - Noter la plus utilisée (colonne Quiz)
   - Ouvrir toutes les versions dans des onglets
   - Choisir la meilleure version
   - Supprimer les autres
4. ✅ Recharger → Vérifier la réduction des doublons

**Temps estimé :** 1-2 heures

### Scénario 3 : Audit pour migration

**Contexte :** Migration vers un nouveau Moodle

**Étapes :**
1. ✅ Afficher toutes les colonnes (surtout Contexte, Créateur, Dates)
2. ✅ Exporter en CSV
3. ✅ Ouvrir dans Excel
4. ✅ Créer un tableau croisé dynamique :
   - Lignes : Type de question
   - Valeurs : Nombre de questions
5. ✅ Identifier les types peu utilisés
6. ✅ Revenir sur Moodle
7. ✅ Filtrer par ces types
8. ✅ Décider : migrer ou archiver

**Temps estimé :** 2-3 heures

---

## 🎯 Tests de validation finale

### Checklist de recette

Avant de valider l'implémentation, vérifier :

#### Fonctionnel
- [ ] Dashboard affiche 6 cartes avec valeurs correctes
- [ ] Répartition par type affiche tous les types
- [ ] 4 filtres fonctionnent (recherche, type, usage, doublons)
- [ ] Filtres combinables (AND)
- [ ] Tri des colonnes (croissant/décroissant)
- [ ] 14 colonnes configurables
- [ ] Préférences colonnes sauvegardées
- [ ] Modal des doublons s'ouvre et affiche les données
- [ ] Export CSV téléchargeable et lisible
- [ ] Liens vers banque de questions fonctionnent

#### Performance
- [ ] Chargement < 30s (pour bases < 5000 questions)
- [ ] Filtrage instantané (< 500ms)
- [ ] Tri instantané (< 500ms)
- [ ] Export CSV < 10s

#### Sécurité
- [ ] Accès réservé aux admins
- [ ] Protection CSRF sur l'export
- [ ] Aucune injection SQL possible
- [ ] Aucun XSS possible

#### UX/UI
- [ ] Design cohérent avec le reste du plugin
- [ ] Responsive (mobile, tablette, desktop)
- [ ] Pas d'erreur dans la console
- [ ] Messages d'information clairs
- [ ] Pas de texte en dur (tout traduit)

#### Documentation
- [ ] FEATURE_QUESTIONS_STATS.md complet
- [ ] FEATURE_IMPLEMENTATION_SUMMARY.md complet
- [ ] TESTING_GUIDE.md (ce fichier) complet
- [ ] Code commenté en français

---

## 📝 Rapport de test (template)

```markdown
# Rapport de test - Statistiques des Questions
Date : [DATE]
Testeur : [NOM]
Environnement : [Moodle X.X, PHP X.X, MySQL X.X]

## Résumé
- Tests réussis : X/40
- Tests échoués : X/40
- Bloquants : X

## Détails

### Dashboard
- ✅ Affichage correct
- ❌ Erreur sur carte X : [description]

### Filtres
- ✅ Recherche
- ✅ Type
- ⚠️ Usage : lenteur constatée
- ✅ Doublons

### Tableau
- ✅ Tri
- ✅ Colonnes
- ✅ Liens

### Export
- ✅ CSV téléchargé
- ❌ Encodage incorrect

### Performance
- Temps de chargement : Xs
- Nombre de questions : X
- ✅ / ❌ Acceptable

## Bugs identifiés
1. [Bug 1] : [Description]
2. [Bug 2] : [Description]

## Recommandations
- [Recommandation 1]
- [Recommandation 2]

## Validation finale
- ✅ / ❌ Prêt pour la production
```

---

## 🆘 Support

En cas de problème lors des tests :

1. **Consulter les logs Moodle**
   ```
   Administration du site > Rapports > Journaux
   ```

2. **Activer le mode debug**
   ```php
   // Dans config.php
   $CFG->debug = 32767;
   $CFG->debugdisplay = 1;
   ```

3. **Vérifier la configuration PHP**
   ```bash
   php -i | grep -E "memory_limit|max_execution_time"
   ```

4. **Purger tous les caches**
   ```
   Administration du site > Développement > Purger tous les caches
   ```

---

**Bon courage pour les tests ! 🚀**

*Guide créé : Octobre 2025*
*Version : 1.0*

