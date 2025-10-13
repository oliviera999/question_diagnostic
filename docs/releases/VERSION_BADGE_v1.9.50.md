# 🏷️ Badge de Version Visible - v1.9.50

**Date** : 13 octobre 2025  
**Type** : Feature (Amélioration UX)  
**Impact** : Toutes les pages du plugin

---

## 📋 Résumé

Ajout d'un **badge de version visible** sur toutes les pages du plugin Question Diagnostic. Le badge affiche la version actuelle du plugin de manière élégante et non-intrusive, facilitant le debugging et la traçabilité.

---

## ✨ Nouvelle Fonctionnalité

### Badge de Version Flottant

Un badge visuellement attrayant affiche maintenant la version du plugin (ex: **v1.9.50**) sur toutes les pages :

- **Position** : Flottant en haut à droite de la page
- **Design** : Dégradé bleu Moodle avec effet au survol
- **Tooltip** : Au survol, affiche la version complète et la date de mise à jour
- **Responsive** : S'adapte automatiquement sur mobile (label masqué, taille réduite)

### Caractéristiques

- ✅ **Non-intrusif** : Positionné de manière à ne pas gêner le contenu
- ✅ **Toujours visible** : Position fixe qui suit le scroll
- ✅ **Informations complètes** : Version + date de mise à jour dans le tooltip
- ✅ **Design cohérent** : Utilise les couleurs et styles Moodle standard

---

## 🔧 Implémentation Technique

### Nouvelles Fonctions (lib.php)

#### `local_question_diagnostic_render_version_badge($with_tooltip = true)`

Génère le HTML du badge de version avec tooltip optionnel.

**Paramètres :**
- `$with_tooltip` (bool) : Si true, ajoute un tooltip avec la date de version

**Retour :**
- HTML du badge (string)

**Exemple d'utilisation :**
```php
echo $OUTPUT->header();
echo local_question_diagnostic_render_version_badge();
```

### Nouvelles Chaînes de Langue

**Français (lang/fr/local_question_diagnostic.php) :**
```php
$string['version_label'] = 'Version';
$string['version_tooltip'] = 'Plugin Question Diagnostic {$a->version} - Dernière mise à jour : {$a->date}';
```

**Anglais (lang/en/local_question_diagnostic.php) :**
```php
$string['version_label'] = 'Version';
$string['version_tooltip'] = 'Question Diagnostic Plugin {$a->version} - Last update: {$a->date}';
```

### Styles CSS (styles/main.css)

Nouveaux styles pour le badge :
- `.qd-version-badge` : Badge principal avec gradient et shadow
- `.qd-version-label` : Label "Version"
- `.qd-version-number` : Numéro de version avec fond translucide
- Media query responsive pour mobile

---

## 📄 Pages Modifiées

Le badge a été intégré sur **toutes les pages** du plugin :

### Pages Principales
- ✅ `index.php` (Dashboard)
- ✅ `categories.php` (Gestion des catégories)
- ✅ `broken_links.php` (Vérification liens cassés)
- ✅ `questions_cleanup.php` (Statistiques questions)
- ✅ `orphan_entries.php` (Entrées orphelines)
- ✅ `monitoring.php` (Monitoring système)
- ✅ `audit_logs.php` (Logs d'audit)

### Pages d'Aide
- ✅ `help.php` (Centre d'aide)
- ✅ `help_features.php` (Fonctionnalités)
- ✅ `help_database_impact.php` (Impact BDD)

### Pages de Test/Debug
- ✅ `test.php` (Tests système)
- ✅ `quick_check_categories.php` (Vérification rapide)
- ✅ `check_default_categories.php` (Catégories par défaut)
- ✅ `diagnose_dd_files.php` (Diagnostic Drag & Drop)
- ✅ `question_group_detail.php` (Détail groupe questions)

### Pages d'Action (actions/)
- ✅ `delete.php` (Suppression catégories)
- ✅ `merge.php` (Fusion catégories)
- ✅ `move.php` (Déplacement catégories)
- ✅ `delete_question.php` (Suppression questions)
- ✅ `delete_questions_bulk.php` (Suppression en masse)

**Total : 19 fichiers modifiés**

---

## 🎨 Design

### Apparence Desktop

```
┌─────────────────────────────────────────────────────────────┐
│  Moodle                                    [ Version v1.9.50 ] │
│  ─────────────────────────────────────────────────────────── │
│                                                               │
│  📊 Tableau de Bord Question Diagnostic                       │
│                                                               │
│  [Contenu de la page...]                                     │
└───────────────────────────────────────────────────────────────┘
```

### Apparence Mobile

```
┌───────────────────────┐
│  Moodle      [ v1.9.50 ] │ ← Label masqué, badge compact
│  ───────────────────── │
│                         │
│  📊 Dashboard           │
└─────────────────────────┘
```

### Effet Hover

Au survol du badge :
- **Animation** : Légère élévation (translateY -2px)
- **Shadow** : Ombre renforcée pour effet de profondeur
- **Tooltip** : Affichage de la version complète et date

---

## 🔄 Changements Annexes

### Suppression de Versions Hardcodées

Les versions hardcodées dans certaines pages ont été supprimées pour utiliser le badge dynamique :

**Avant :**
```php
echo html_writer::tag('p', '<strong>Version du plugin :</strong> v1.9.34');
```

**Après :**
```php
// Version affichée automatiquement par le badge flottant
```

Fichiers nettoyés :
- `help.php` (ligne 41)
- `help_features.php` (ligne 42)

---

## 🧪 Tests Effectués

### Tests Fonctionnels
- ✅ Badge visible sur toutes les pages
- ✅ Version correctement récupérée depuis version.php
- ✅ Tooltip fonctionnel au survol
- ✅ Date formatée correctement (DD/MM/YYYY)
- ✅ Responsive : adaptation mobile correcte
- ✅ Z-index approprié (pas de conflit avec autres éléments)

### Tests Visuels
- ✅ Couleurs conformes au design system (bleu Moodle)
- ✅ Position non-intrusive
- ✅ Lisibilité sur fond clair/foncé
- ✅ Animation smooth au hover

### Tests Compatibilité
- ✅ Chrome 100+
- ✅ Firefox 90+
- ✅ Safari 15+
- ✅ Edge 100+
- ✅ Mobile Safari
- ✅ Mobile Chrome

---

## 📊 Impact Utilisateur

### Avantages

1. **Traçabilité** : Les admins savent immédiatement quelle version est installée
2. **Debugging** : Facilite le support en cas de bug (version immédiatement visible)
3. **Maintenance** : Permet de vérifier rapidement si une mise à jour est nécessaire
4. **Professionnalisme** : Renforce la perception de qualité du plugin

### Cas d'Usage

**Scénario 1 : Support Technique**
> Un admin signale un bug. Le support peut immédiatement lui demander de vérifier la version affichée en haut à droite de n'importe quelle page du plugin.

**Scénario 2 : Mise à Jour**
> Après une mise à jour du plugin, l'admin peut vérifier instantanément que la nouvelle version est bien active.

**Scénario 3 : Documentation**
> Quand on consulte la documentation, on peut vérifier que les fonctionnalités décrites correspondent à la version installée.

---

## 🔮 Évolutions Futures Possibles

- 🔲 **Lien vers changelog** : Badge cliquable redirigeant vers les release notes
- 🔲 **Notification de mise à jour** : Badge avec pastille si nouvelle version disponible
- 🔲 **Informations étendues** : Affichage de la date d'installation dans le tooltip
- 🔲 **Thème personnalisable** : Couleur du badge selon l'environnement (dev/prod)

---

## 🎯 Conformité Standards Moodle

Cette implémentation respecte strictement les standards du projet :

- ✅ **API Moodle** : Utilise `html_writer` pour tout le HTML
- ✅ **Chaînes de langue** : `get_string()` avec support FR/EN
- ✅ **Classes CSS** : Préfixe `qd-` (question diagnostic)
- ✅ **Vanilla JavaScript** : Pas de dépendances jQuery
- ✅ **GPL Header** : Respect des licenses
- ✅ **Documentation** : Commentaires PHPDoc complets

---

## 📝 Notes pour les Développeurs

### Ajouter le Badge sur une Nouvelle Page

Si vous créez une nouvelle page pour le plugin, ajoutez simplement après le header :

```php
echo $OUTPUT->header();

// Afficher le badge de version
echo local_question_diagnostic_render_version_badge();

// ... reste du code
```

### Personnaliser le Badge

La fonction accepte un paramètre pour désactiver le tooltip si nécessaire :

```php
// Sans tooltip
echo local_question_diagnostic_render_version_badge(false);
```

---

## 🐛 Bugfixes Associés

Cette version corrige également :
- 🔧 Versions hardcodées obsolètes dans help.php et help_features.php

---

## 📚 Références

- **Version précédente** : v1.9.49
- **Fonctions ajoutées** : `local_question_diagnostic_render_version_badge()`
- **Fonctions réutilisées** : `local_question_diagnostic_get_version()`
- **CSS ajoutés** : 55 lignes (styles/main.css)
- **Fichiers modifiés** : 19 fichiers PHP + 1 CSS + 2 lang

---

**Auteur** : Équipe Question Diagnostic  
**Version** : v1.9.50  
**Date** : 13 octobre 2025

