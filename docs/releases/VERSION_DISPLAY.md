# 📌 Affichage de la version du plugin (v1.2.4)

## 🎯 Fonctionnalité

La version du plugin est maintenant affichée **automatiquement** entre parenthèses après le titre de chaque page.

### Exemple d'affichage

```
Gestion des Questions - Diagnostic (v1.2.4)
Gestion des Catégories (v1.2.4)
Statistiques des Questions (v1.2.4)
Vérification des Liens Cassés (v1.2.4)
```

---

## 📊 Version actuelle

**Version** : v1.2.4  
**Format numérique** : 2025100704  
**Date** : 7 octobre 2025

---

## 🔄 Quand la version est-elle incrémentée ?

La version est incrémentée à chaque **modification significative** du code :

### Format de versionnage

Le plugin utilise le **versionnage sémantique** adapté à Moodle :

1. **Format lisible** : `v1.2.4`
   - `1` = Version majeure (changements incompatibles)
   - `2` = Version mineure (nouvelles fonctionnalités)
   - `4` = Version de correctif (corrections de bugs)

2. **Format numérique** : `YYYYMMDDXX`
   - `YYYY` = Année (2025)
   - `MM` = Mois (10 = octobre)
   - `DD` = Jour (07)
   - `XX` = Incrément du jour (01, 02, 03...)

### Exemples historiques

| Version | Date | Changement |
|---------|------|------------|
| v1.2.1 | 2025-10-07 | Optimisation cache doublons |
| v1.2.2 | 2025-10-07 | Fix timeout 29K questions |
| v1.2.3 | 2025-10-07 | Fix catégories orphelines |
| v1.2.4 | 2025-10-07 | Affichage version sur pages |

---

## 🛠️ Implémentation technique

### Fonctions ajoutées dans `lib.php`

#### 1. `local_question_diagnostic_get_version()`

Récupère la version depuis `version.php` :

```php
function local_question_diagnostic_get_version() {
    global $CFG;
    
    // Get plugin info from version.php
    $plugin = new stdClass();
    require($CFG->dirroot . '/local/question_diagnostic/version.php');
    
    return $plugin->release ?? 'v1.0.0';
}
```

**Retourne** : `v1.2.4` (chaîne de caractères)

#### 2. `local_question_diagnostic_get_heading_with_version()`

Ajoute la version au titre de la page :

```php
function local_question_diagnostic_get_heading_with_version($heading) {
    $version = local_question_diagnostic_get_version();
    return $heading . ' (' . $version . ')';
}
```

**Paramètre** : `$heading` (titre de la page)  
**Retourne** : `Gestion des Questions (v1.2.4)`

---

## 📝 Modifications sur chaque page

### Avant (v1.2.3)

```php
$PAGE->set_title(get_string('pluginname', 'local_question_diagnostic'));
$PAGE->set_heading(get_string('pluginname', 'local_question_diagnostic'));
```

**Affichage** : `Gestion des Questions`

### Après (v1.2.4)

```php
require_once(__DIR__ . '/lib.php');  // Ajout de l'include

$pagetitle = get_string('pluginname', 'local_question_diagnostic');
$PAGE->set_title($pagetitle);
$PAGE->set_heading(local_question_diagnostic_get_heading_with_version($pagetitle));
```

**Affichage** : `Gestion des Questions (v1.2.4)`

---

## 📄 Pages modifiées

✅ **4 pages mises à jour** :

1. **`index.php`** - Page d'accueil du plugin
2. **`categories.php`** - Gestion des catégories
3. **`questions_cleanup.php`** - Statistiques des questions
4. **`broken_links.php`** - Vérification des liens cassés

---

## 🎨 Apparence

### Sur l'interface

Le titre de chaque page affiche maintenant :

```
┌──────────────────────────────────────────────┐
│  Gestion des Questions (v1.2.4)              │
│  ════════════════════════════════════════    │
│                                              │
│  [Contenu de la page...]                     │
└──────────────────────────────────────────────┘
```

La version apparaît en **gris clair** entre parenthèses, de manière discrète mais visible.

---

## ✅ Avantages

### Pour les administrateurs

1. **Visibilité immédiate** de la version installée
2. **Pas besoin** d'aller dans Administration du site → Plugins
3. **Debugging facilité** : savoir quelle version est en production
4. **Support simplifié** : l'utilisateur peut donner sa version facilement

### Pour les développeurs

1. **Source unique** : version définie dans `version.php` uniquement
2. **Maintenance facile** : une seule valeur à modifier
3. **Cohérence** : même version affichée partout
4. **Automatique** : pas besoin de mettre à jour chaque page manuellement

---

## 🔧 Comment mettre à jour la version

### 1. Modifier `version.php`

```php
$plugin->version = 2025100705;  // Incrémenter
$plugin->release = 'v1.2.5';     // Nouvelle version
```

### 2. Purger les caches

```bash
php admin/cli/purge_caches.php
```

### 3. La nouvelle version s'affiche automatiquement

Toutes les pages afficheront **immédiatement** la nouvelle version !

---

## 📊 Cas d'usage

### Scénario 1 : Support utilisateur

**Utilisateur** : "J'ai un problème avec le plugin"  
**Support** : "Quelle version utilisez-vous ?"  
**Utilisateur** : "Je vois (v1.2.4) en haut de la page"  
**Support** : "OK, je vois que vous avez la dernière version"

### Scénario 2 : Debugging

**Admin** : "Le plugin ne fonctionne pas comme prévu"  
**Dev** : "Vérifiez la version affichée sur la page"  
**Admin** : "C'est v1.2.2"  
**Dev** : "Ah, il faut mettre à jour vers v1.2.4 qui corrige ce bug"

### Scénario 3 : Installation

**Admin** : "J'ai mis à jour le plugin"  
**Vérification** : Regarder le titre de la page → (v1.2.4)  
**Confirmation** : Mise à jour réussie !

---

## 🔍 Dépannage

### La version ne s'affiche pas

**Cause 1** : Cache non purgé
```bash
php admin/cli/purge_caches.php
```

**Cause 2** : `lib.php` non chargé
- Vérifier que `require_once(__DIR__ . '/lib.php');` est présent

**Cause 3** : Erreur PHP
- Vérifier les logs : `tail -f /var/log/php-fpm/error.log`

### La version affichée est incorrecte

**Solution** :
1. Vérifier `version.php` : `$plugin->release = 'v1.2.4';`
2. Purger les caches
3. Rafraîchir la page (Ctrl+F5)

---

## 🎯 Bonnes pratiques

### Quand incrémenter la version

✅ **OUI** - Incrémenter pour :
- Corrections de bugs
- Nouvelles fonctionnalités
- Optimisations majeures
- Changements de comportement

❌ **NON** - Ne pas incrémenter pour :
- Modifications de documentation uniquement
- Corrections de typos dans les commentaires
- Changements de formatage du code

### Format du numéro de version

**Version mineure** (1.2.X → 1.2.Y)
- Corrections de bugs
- Petites améliorations
- Pas de breaking changes

**Version majeure** (1.X.Y → 2.X.Y)
- Refonte majeure
- Breaking changes
- Nouvelles architectures

---

## 📚 Fichiers concernés

| Fichier | Rôle |
|---------|------|
| `version.php` | Définit `$plugin->release` |
| `lib.php` | Fonctions de récupération version |
| `index.php` | Affiche version sur page d'accueil |
| `categories.php` | Affiche version sur page catégories |
| `questions_cleanup.php` | Affiche version sur page questions |
| `broken_links.php` | Affiche version sur page liens cassés |

---

## 🎉 Conclusion

Cette fonctionnalité simple mais efficace améliore :
- ✅ L'**expérience utilisateur** (visibilité immédiate)
- ✅ La **maintenabilité** (version centralisée)
- ✅ Le **support** (debugging facilité)
- ✅ La **cohérence** (même version partout)

**Installation** : Automatique dès mise à jour vers v1.2.4  
**Configuration** : Aucune  
**Maintenance** : Modifier `version.php` uniquement

---

**Version** : v1.2.4 (2025100704)  
**Date** : 7 octobre 2025  
**Status** : ✅ **Actif**

