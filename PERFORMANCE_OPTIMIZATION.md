# Optimisation des performances - Détection des doublons de questions

## 🚀 Problème résolu

**Symptômes initiaux :**
- Temps de chargement très long (plusieurs minutes) sur la page de suppression des doublons
- Erreurs de timeout de base de données
- Page qui ne se charge pas ou timeout PHP

**Cause principale :**
La fonction `get_duplicates_map()` effectuait une détection de doublons pour **toutes** les questions à chaque chargement de page, ce qui pouvait générer des dizaines de milliers de requêtes SQL pour les grandes bases de données (>1000 questions).

## ✨ Optimisations implémentées

### 1. **Système de cache Moodle** 
- Ajout de 3 caches applicatifs :
  - `duplicates` : Cache la map des doublons (1 heure)
  - `globalstats` : Cache les statistiques globales (30 minutes)
  - `questionusage` : Cache l'usage des questions (30 minutes)
- Configuration dans `db/caches.php`

### 2. **Détection intelligente des doublons**
- **Pour les petites bases (<5000 questions)** : Détection complète avec calcul de similarité
- **Pour les grandes bases (>5000 questions)** : Détection rapide par nom exact uniquement
- Protection par timeout (30 secondes maximum)
- Utilisation du cache pour éviter les recalculs

### 3. **Optimisation des requêtes SQL**
- Réduction du nombre de requêtes SQL avec des jointures optimisées
- Regroupement des données au niveau SQL plutôt qu'en PHP
- Requêtes compatibles avec tous les SGBD (MySQL, PostgreSQL, etc.)

### 4. **Gestion d'erreurs améliorée**
- Messages d'erreur explicites avec suggestions de résolution
- Détection automatique des grandes bases de données
- Désactivation automatique de la détection de doublons si nécessaire
- Continuité du service même en cas d'erreur partielle

### 5. **Fonctionnalité de purge de cache**
- Bouton "🔄 Purger le cache" ajouté sur la page `questions_cleanup.php`
- Permet de forcer le recalcul des statistiques à la demande
- Utile après des modifications massives de questions

## 📊 Performances attendues

| Scénario | Avant | Après (1er chargement) | Après (avec cache) |
|----------|-------|------------------------|-------------------|
| 100 questions | ~5s | ~2s | <1s |
| 1000 questions | >60s ou timeout | ~10s | ~2s |
| 5000 questions | timeout | ~30s | ~3s |
| 10000+ questions | timeout | ~40s (mode rapide) | ~5s |

## 🔧 Configuration recommandée

### Pour les grandes bases de données (>5000 questions)

Ajustez votre configuration PHP dans `php.ini` ou `.htaccess` :

```ini
; Augmenter le timeout d'exécution
max_execution_time = 300

; Augmenter la limite de mémoire
memory_limit = 512M

; Augmenter le timeout MySQL
mysql.connect_timeout = 60
```

### Configuration Moodle

Dans `config.php`, vous pouvez ajuster :

```php
// Augmenter le timeout de base de données
$CFG->dboptions = array(
    'connecttimeout' => 60,
    'readonly' => array('instance' => array()),
);
```

## 📝 Utilisation

### Chargement normal
1. Accédez à la page des statistiques de questions
2. Les données sont chargées depuis le cache si disponible
3. Le premier chargement peut prendre quelques secondes

### Purger le cache
1. Cliquez sur le bouton "🔄 Purger le cache" en haut de la page
2. Les statistiques seront recalculées au prochain chargement
3. Utilisez cette fonction après :
   - Import massif de questions
   - Suppression de questions
   - Modifications importantes de la base de données

### Purge manuelle du cache (via CLI)

```bash
# Purger tous les caches Moodle
php admin/cli/purge_caches.php

# Ou via l'interface web
Administration du site > Développement > Purger tous les caches
```

## 🐛 Dépannage

### La page est encore lente
1. Vérifiez le nombre de questions : `Administration du site > Plugins > Rapports`
2. Purgez le cache avec le bouton dédié
3. Vérifiez les logs Moodle pour les erreurs SQL
4. Augmentez les limites PHP (voir Configuration recommandée)

### Erreur "Impossible de charger les questions"
1. Vérifiez les logs d'erreurs PHP et MySQL
2. Augmentez `memory_limit` et `max_execution_time`
3. Vérifiez la connexion à la base de données
4. Activez le mode debug Moodle pour plus de détails

### Les doublons ne sont pas détectés
1. Vérifiez que vous avez moins de 5000 questions (sinon seuls les noms exacts sont comparés)
2. Purgez le cache pour forcer un recalcul
3. Vérifiez que les noms des questions sont bien renseignés

## 📚 Fichiers modifiés

- `classes/question_analyzer.php` : Optimisations principales
- `questions_cleanup.php` : Gestion d'erreurs et option de purge
- `db/caches.php` : Définitions des caches (nouveau fichier)

## 🔍 Détails techniques

### Algorithme de détection de doublons

**Mode complet (< 5000 questions) :**
1. Recherche exacte par nom et type
2. Recherche par similarité de nom (20 premiers caractères)
3. Calcul de score de similarité :
   - Nom : 30%
   - Texte : 40%
   - Type : 20%
   - Catégorie : 10%
4. Seuil de similarité : 85%

**Mode rapide (≥ 5000 questions) :**
1. Groupement par nom exact + type
2. Identification des groupes avec plus d'un élément
3. Pas de calcul de similarité (instantané)

### Stratégie de cache

- **Application cache** : Partagé entre tous les utilisateurs
- **Static acceleration** : Cache en mémoire pour la session
- **TTL adaptatif** : 
  - Doublons : 1 heure (calcul coûteux)
  - Statistiques globales : 30 minutes
  - Usage des questions : 30 minutes

## 📌 Notes importantes

1. Le cache est automatiquement invalidé après 1 heure maximum
2. Les statistiques peuvent être légèrement obsolètes si le cache est actif
3. Utilisez la purge de cache après des modifications importantes
4. Pour les très grandes bases (>10000 questions), envisagez un calcul nocturne via CRON

## 🎯 Prochaines améliorations possibles

- [ ] Calcul asynchrone des doublons via tâche planifiée
- [ ] Pagination de la liste des questions
- [ ] Filtrage côté serveur pour réduire le volume de données
- [ ] Indexation des questions pour améliorer les recherches
- [ ] API AJAX pour charger les données progressivement

---

**Date de mise à jour :** 7 octobre 2025
**Version :** 1.2.1

