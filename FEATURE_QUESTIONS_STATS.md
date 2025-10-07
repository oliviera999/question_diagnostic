# 📊 Statistiques et Nettoyage des Questions

## Vue d'ensemble

Cette nouvelle fonctionnalité offre une analyse complète et détaillée de toutes les questions de votre banque de questions Moodle. Elle permet d'identifier les questions inutilisées, détecter les doublons avec un algorithme de similarité, et effectuer un nettoyage intelligent de votre base.

## Accès

**Navigation :** `Administration du site` → `Local plugins` → `Question Diagnostic` → `Analyser les questions`

**URL directe :** `/local/question_diagnostic/questions_cleanup.php`

**Permissions requises :** Administrateur du site uniquement

---

## 🎯 Fonctionnalités principales

### 1. Dashboard de statistiques globales

Le dashboard affiche 6 cartes statistiques essentielles :

#### 📊 Total Questions
- Nombre total de questions dans la base de données
- Toutes catégories et contextes confondus

#### ✅ Questions Utilisées
- Questions présentes dans au moins un quiz actif **OU**
- Questions ayant déjà été utilisées dans des tentatives
- Affichage du nombre d'utilisations

#### ⚠️ Questions Inutilisées
- Questions jamais utilisées dans un quiz
- Questions sans aucune tentative enregistrée
- Candidats potentiels pour le nettoyage

#### 🔀 Questions en Doublon
- Détection automatique basée sur :
  - Similarité du nom (30%)
  - Similarité du texte (40%)
  - Même type de question (20%)
  - Même catégorie (10%)
- Seuil de similarité : 85% par défaut
- Affichage du nombre total de doublons

#### 👁️ Questions Cachées
- Questions marquées comme "cachées"
- Non visibles par les enseignants

#### 🔗 Liens Cassés
- Questions avec des liens vers des fichiers manquants
- Intégration avec l'outil de vérification des liens

### 2. Répartition par type de question

Graphique détaillé montrant :
- Nombre de questions par type (multichoice, truefalse, essay, etc.)
- Pourcentage de chaque type
- Identification des types les plus utilisés

---

## 🔍 Filtres avancés

### Barre de recherche
- **Recherche par :** Nom, ID, texte de la question, catégorie
- **Mode :** Recherche en temps réel avec debounce (300ms)
- **Sensibilité :** Non sensible à la casse

### Filtre par type
- Tous les types de questions disponibles
- Affichage du nombre de questions par type
- Liste dynamique basée sur les types présents

### Filtre par usage
- **Toutes** : Affiche toutes les questions
- **Utilisées** : Seulement les questions dans des quiz ou avec tentatives
- **Inutilisées** : Seulement les questions jamais utilisées

### Filtre par doublons
- **Toutes** : Affiche toutes les questions
- **Avec doublons** : Seulement les questions ayant des doublons détectés
- **Sans doublons** : Seulement les questions uniques

---

## 📋 Tableau détaillé des questions

### Colonnes disponibles

Le tableau propose **14 colonnes** dont la visibilité est configurable :

| Colonne | Visible par défaut | Description |
|---------|-------------------|-------------|
| **ID** | ✅ Oui | Identifiant unique de la question |
| **Nom** | ✅ Oui | Nom de la question (en gras) |
| **Type** | ✅ Oui | Type de question (badge coloré) |
| **Catégorie** | ✅ Oui | Catégorie avec lien vers la banque |
| **Contexte** | ❌ Non | Contexte (Système, Cours, Module) |
| **Créateur** | ❌ Non | Nom complet du créateur |
| **Créée le** | ❌ Non | Date et heure de création |
| **Modifiée le** | ❌ Non | Date et heure de dernière modification |
| **Visible** | ❌ Non | Statut de visibilité (✅/❌) |
| **Quiz** | ✅ Oui | Nombre de quiz utilisant la question |
| **Tentatives** | ❌ Non | Nombre de tentatives enregistrées |
| **Doublons** | ✅ Oui | Nombre de doublons détectés (cliquable) |
| **Extrait** | ❌ Non | Extrait du texte de la question (100 car.) |
| **Actions** | ✅ Oui | Boutons d'action (Voir, etc.) |

### Gestion des colonnes

**Bouton ⚙️ Colonnes** :
- Ouvre un panneau de configuration
- Cochez/décochez les colonnes à afficher
- Les préférences sont sauvegardées localement (localStorage)
- S'appliquent immédiatement sans rechargement

### Tri des colonnes

- **Clic sur l'en-tête** : Trie par ordre croissant
- **Second clic** : Inverse l'ordre (décroissant)
- **Indicateur visuel** : ▲ (croissant) ou ▼ (décroissant)
- **Types supportés** :
  - Numérique : ID, Quiz, Tentatives, Doublons
  - Alphabétique : Nom, Type, Catégorie, Créateur
  - Date : Créée le, Modifiée le

---

## 🔀 Détection des doublons

### Algorithme de similarité

L'algorithme calcule un score de similarité entre deux questions en combinant plusieurs critères :

```
Score = (Nom × 0.3) + (Texte × 0.4) + (Type × 0.2) + (Catégorie × 0.1)
```

#### Calcul de similarité du nom (30%)
- Comparaison insensible à la casse
- Score de 100% si noms identiques
- Sinon, utilisation de `similar_text()` PHP

#### Calcul de similarité du texte (40%)
- Extraction du texte brut (sans HTML)
- Comparaison insensible à la casse
- Algorithme de Levenshtein simplifié

#### Bonus : Même type (20%)
- +20% si les questions sont du même type
- Exemples : multichoice, truefalse, essay

#### Bonus : Même catégorie (10%)
- +10% si les questions sont dans la même catégorie

### Seuil de détection

**Seuil par défaut : 85%**

Une question est considérée comme doublon si son score de similarité est ≥ 85%.

### Modal des doublons

**Clic sur le badge "Doublons"** ouvre un modal affichant :
- Liste complète des questions similaires
- ID, nom, type et catégorie de chaque doublon
- Bouton "Voir" pour accéder à chaque question
- Score de similarité (visible dans les attributs data)

---

## 📥 Export CSV

### Bouton "📥 Exporter en CSV"

Génère un fichier CSV contenant :

**Colonnes exportées :**
1. ID
2. Nom
3. Type
4. Catégorie
5. Contexte
6. Créateur
7. Date création
8. Date modification
9. Visible (Oui/Non)
10. Utilisée (Oui/Non)
11. Quiz (nombre)
12. Tentatives (nombre)
13. Doublons (nombre)

**Format :**
- Encodage : UTF-8 avec BOM (compatible Excel)
- Séparateur : Virgule
- Nom du fichier : `questions_statistics_YYYY-MM-DD_HH-mm-ss.csv`

---

## 🔗 Liens directs

### Vers la banque de questions

Chaque question dispose d'un bouton **"👁️ Voir"** qui :
- Ouvre la banque de questions dans un nouvel onglet
- Pré-filtre sur la catégorie de la question
- Sélectionne automatiquement la question (paramètre `qid`)

**Format de l'URL :**
```
/question/edit.php?courseid={COURSE_ID}&cat={CAT_ID},{CONTEXT_ID}&qid={QUESTION_ID}
```

### Vers les catégories

Le nom de la catégorie est cliquable et redirige vers :
```
/question/edit.php?courseid={COURSE_ID}&cat={CAT_ID},{CONTEXT_ID}
```

---

## ⚡ Performances et optimisation

### Optimisations implémentées

1. **Pré-calcul des usages**
   - Une seule requête SQL pour tous les quiz
   - Cache des tentatives par question
   - Évite les requêtes N+1

2. **Calcul intelligent des doublons**
   - Recherche d'abord par nom exact
   - Puis par nom similaire (limite 50 résultats)
   - Cache des doublons pour éviter les recalculs

3. **Filtrage côté client**
   - JavaScript pour filtres et tri
   - Pas de rechargement de page
   - Debounce sur la recherche (300ms)

4. **Affichage progressif**
   - Indicateur de chargement
   - Masquage automatique après calcul
   - Messages informatifs

### Temps de calcul estimés

| Nombre de questions | Temps de chargement |
|--------------------|---------------------|
| < 100 | < 1 seconde |
| 100 - 500 | 1-3 secondes |
| 500 - 1000 | 3-5 secondes |
| 1000 - 5000 | 5-15 secondes |
| > 5000 | 15-30 secondes |

**Note :** Le calcul des doublons est le plus coûteux. Pour > 1000 questions, envisager d'optimiser le seuil ou de limiter la recherche.

---

## 🎨 Interface utilisateur

### Badges de statut

| Badge | Couleur | Signification |
|-------|---------|---------------|
| **Type** | Bleu | Type de question (multichoice, etc.) |
| **Quiz: 0** | Gris | Question non utilisée dans un quiz |
| **Quiz: X** | Vert | Question utilisée dans X quiz |
| **Doublons: 0** | Vert | Pas de doublon détecté |
| **Doublons: X** | Rouge | X doublons détectés (cliquable) |

### Indicateurs visuels

- **✅ Oui / ❌ Non** : Visibilité des questions
- **ℹ️** : Info-bulle au survol (liste des quiz)
- **▲ / ▼** : Indicateur de tri actif
- **📊 📈 🔍 🧹** : Icônes dans les cartes statistiques

---

## 🛠️ Cas d'usage

### Cas 1 : Nettoyage de questions inutilisées

**Objectif :** Supprimer les questions jamais utilisées

**Étapes :**
1. Filtre **Usage** → **Inutilisées**
2. Vérifier les colonnes **Quiz** et **Tentatives** (doivent être à 0)
3. Examiner manuellement chaque question
4. Supprimer via la banque de questions (bouton Voir)

**Attention :** Vérifier que ce ne sont pas des questions récemment créées !

### Cas 2 : Identification des doublons

**Objectif :** Fusionner ou supprimer les questions redondantes

**Étapes :**
1. Filtre **Doublons** → **Avec doublons**
2. Trier par colonne **Doublons** (décroissant)
3. Cliquer sur les badges **Doublons: X**
4. Comparer les questions dans le modal
5. Garder la meilleure version, supprimer les autres

**Astuce :** Privilégier la question la plus utilisée (colonne Quiz)

### Cas 3 : Audit des questions

**Objectif :** Exporter toutes les statistiques pour analyse

**Étapes :**
1. Cliquer sur **📥 Exporter en CSV**
2. Ouvrir dans Excel ou LibreOffice
3. Créer des tableaux croisés dynamiques
4. Analyser les tendances (types populaires, créateurs actifs, etc.)

### Cas 4 : Migration de contenu

**Objectif :** Identifier les questions à migrer vers un nouveau cours

**Étapes :**
1. Afficher colonne **Contexte**
2. Filtrer par type de question
3. Vérifier colonne **Catégorie**
4. Exporter la sélection
5. Utiliser l'import/export Moodle standard

---

## 🔐 Sécurité

### Contrôles d'accès

- ✅ Authentification requise (`require_login()`)
- ✅ Vérification admin site (`is_siteadmin()`)
- ✅ Token de session (`sesskey`) pour exports
- ✅ Échappement HTML pour tous les affichages
- ✅ Requêtes SQL préparées (API `$DB`)

### Protection des données

- ❌ Aucune modification de questions depuis cette page
- ❌ Aucune suppression directe
- ✅ Lecture seule sur la base de données
- ✅ Export CSV sécurisé (UTF-8 BOM)

---

## 🐛 Dépannage

### Problème : Temps de chargement très long

**Causes possibles :**
- Trop de questions (> 5000)
- Calcul des doublons intensif
- Serveur surchargé

**Solutions :**
1. Augmenter `max_execution_time` PHP
2. Optimiser la base de données (index)
3. Limiter le calcul des doublons (modifier le code)

### Problème : Doublons non détectés

**Causes possibles :**
- Seuil trop élevé (85%)
- Questions trop différentes
- Noms très courts

**Solutions :**
1. Réduire le seuil dans `question_analyzer.php` (ligne ~300)
2. Vérifier manuellement avec la recherche

### Problème : Export CSV vide

**Causes possibles :**
- Timeout PHP
- Mémoire insuffisante

**Solutions :**
1. Augmenter `memory_limit` (512M recommandé)
2. Exporter par tranches (modifier le code)

---

## 🚀 Améliorations futures

### Version 1.1.0

- [ ] Graphiques interactifs (Chart.js)
- [ ] Export Excel natif (PHPSpreadsheet)
- [ ] Filtres par date de création
- [ ] Filtres par créateur

### Version 1.2.0

- [ ] Actions groupées (suppression, fusion)
- [ ] Planification de nettoyage automatique
- [ ] Notifications par email
- [ ] Historique des modifications

### Version 2.0.0

- [ ] Intelligence artificielle pour suggestions
- [ ] Détection de similarité sémantique (NLP)
- [ ] Tableaux de bord personnalisables
- [ ] API REST pour intégrations tierces

---

## 📚 Références techniques

### Fichiers du projet

```
local/question_diagnostic/
├── questions_cleanup.php              # Page principale
├── classes/
│   └── question_analyzer.php          # Logique métier
├── actions/
│   └── export.php                     # Export CSV (modifié)
└── lang/
    ├── en/local_question_diagnostic.php   # Traductions EN
    └── fr/local_question_diagnostic.php   # Traductions FR
```

### Classes et méthodes

#### `question_analyzer`

**Méthodes publiques :**
- `get_all_questions_with_stats()` : Récupère toutes les questions avec stats
- `get_question_stats($question, $cache...)` : Stats d'une question
- `get_question_usage($questionid)` : Usage d'une question
- `find_question_duplicates($question, $threshold)` : Trouve les doublons
- `get_global_stats()` : Statistiques globales
- `get_question_bank_url($question, $category)` : URL vers banque
- `export_to_csv($questions)` : Export CSV

**Méthodes privées :**
- `get_all_questions_usage()` : Pré-calcul des usages
- `get_duplicates_map()` : Pré-calcul des doublons
- `calculate_question_similarity($q1, $q2)` : Calcul de similarité
- `get_text_excerpt($html, $length)` : Extrait de texte

---

## 📞 Support

Pour toute question ou problème :

1. Vérifier cette documentation
2. Consulter les logs Moodle (`Site administration > Reports > Logs`)
3. Ouvrir une issue sur le dépôt Git
4. Contacter l'équipe de développement

---

**Développé avec ❤️ pour la communauté Moodle**

*Dernière mise à jour : Octobre 2025*
*Version : 1.1.0*

