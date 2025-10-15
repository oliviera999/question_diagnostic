# 🚀 Référence Rapide : Protection des Catégories "Default for"

## 📊 Tableau de Décision Rapide

| Type de Catégorie | Contexte | Parent | Vide | Description | 🛡️ Protégée | ✅ Supprimable |
|-------------------|----------|--------|------|-------------|--------------|----------------|
| **"Default for Cours A"** | ✅ Valide | 15 | ✅ Oui | Non | ✅ **OUI** | ❌ NON |
| **"Default for [Supprimé]"** | ❌ Orphelin | 15 | ✅ Oui | Non | ❌ NON | ✅ **OUI** |
| **"Default for Quiz X"** | ✅ Valide | 0 | ✅ Oui | Non | ✅ **OUI** (racine) | ❌ NON |
| **"Default for Context 999"** | ❌ Invalide | 15 | ✅ Oui | Non | ❌ NON | ✅ **OUI** |
| **"Default for Cours B"** | ✅ Valide | 15 | ❌ Non | Non | ✅ **OUI** | ❌ NON |
| **"Default for [Supprimé]"** | ❌ Orphelin | 15 | ❌ Non | Non | ❌ NON | ❌ NON (questions) |

---

## 🎯 Règle Simple

```
PROTÉGÉE = "Default for" + Contexte VALIDE
                OU
           Parent = 0 + Contexte VALIDE
                OU
           Description NON VIDE
```

---

## 🔍 Comment Identifier les Catégories Supprimables ?

### Dans l'interface `categories.php`

#### ✅ SUPPRIMABLE si :
```
Statut : Vide + Orpheline
Supprimable : ✅ OUI
Bouton : 🗑️ Supprimer (actif)
```

#### ❌ NON SUPPRIMABLE si :
```
Statut : 🛡️ PROTÉGÉE
Supprimable : ❌ NON
Bouton : 🛡️ Protégée (désactivé)
```

---

## 📋 Exemples Concrets

### Exemple 1 : Cours Supprimé

**Avant v1.10.3** :
```
Nom : "Default for Ancien Cours 2023"
Contexte : ID 456 (n'existe plus dans la table context)
Questions : 0
Sous-catégories : 0

Statut : 🛡️ PROTÉGÉE
Raison : Catégorie par défaut Moodle
Supprimable : ❌ NON
```

**Après v1.10.3** :
```
Nom : "Default for Ancien Cours 2023"
Contexte : Contexte supprimé (ID: 456)
Questions : 0
Sous-catégories : 0

Statut : Vide + Orpheline
Supprimable : ✅ OUI
Action : 🗑️ Supprimer (cliquable)
```

### Exemple 2 : Quiz Actif

**Avant et Après v1.10.3** (aucun changement) :
```
Nom : "Default for Quiz Final Math"
Contexte : Quiz Module (ID: 123, valide)
Questions : 0
Sous-catégories : 0

Statut : 🛡️ PROTÉGÉE
Raison : Catégorie par défaut Moodle (contexte actif)
Supprimable : ❌ NON
```

---

## 🧹 Workflow de Nettoyage Recommandé

### Étape 1 : Identifier
```
1. Aller sur categories.php
2. Utiliser le filtre : "Statut" → "Orphelines"
3. Chercher les catégories contenant "Default for" dans la colonne Nom
```

### Étape 2 : Vérifier
```
Pour chaque catégorie "Default for" orpheline :
- Vérifier que "Questions" = 0
- Vérifier que "Sous-cat." = 0
- Vérifier que "Supprimable" = ✅ OUI
```

### Étape 3 : Nettoyer
```
Option A - Suppression individuelle :
- Cliquer sur 🗑️ Supprimer
- Confirmer sur la page de confirmation

Option B - Suppression en masse :
- Cocher les catégories voulues
- Cliquer sur "🗑️ Supprimer la sélection"
- Confirmer

Option C - Nettoyage automatique :
- Utiliser cleanup_all_categories.php (v1.10.2+)
- Mode "preview" pour voir avant suppression
```

---

## 🛡️ Catégories TOUJOURS Protégées

Même avec v1.10.3, certaines catégories restent **TOUJOURS** protégées :

### 1. Catégories Racine (parent=0) avec contexte valide
```
✅ PROTÉGÉE même si vide
Raison : Structure critique de Moodle
```

### 2. Catégories avec Description
```
✅ PROTÉGÉE même si vide
Raison : Usage documenté/intentionnel
```

### 3. Catégories "Default for" avec contexte valide
```
✅ PROTÉGÉE même si vide
Raison : Liée à un cours/quiz actif
```

---

## ❓ FAQ Rapide

**Q : Puis-je supprimer une catégorie "Default for Cours XYZ" si le cours existe encore ?**  
R : ❌ NON. Elle est protégée car liée au contexte actif du cours.

**Q : J'ai supprimé un cours, puis-je supprimer sa catégorie "Default for" ?**  
R : ✅ OUI, si elle est vide (0 questions, 0 sous-catégories).

**Q : Comment savoir si le cours existe encore ?**  
R : Regardez la colonne "Contexte". Si c'est "Contexte supprimé (ID: xxx)", le cours n'existe plus.

**Q : Que se passe-t-il si j'essaie de supprimer une catégorie protégée ?**  
R : Le plugin affiche un message d'erreur explicite et refuse la suppression.

**Q : Les catégories "Default for" racine (parent=0) sont-elles supprimables ?**  
R : ❌ NON, jamais. Protection racine prioritaire sur protection "Default for".

---

## 🔗 Liens Utiles

- Documentation complète : `FEATURE_DEFAULT_CATEGORIES_PROTECTION.md`
- Changelog : `CHANGELOG.md` (v1.10.3)
- Code source : `classes/category_manager.php`

---

**Version** : v1.10.3  
**Mise à jour** : 14 octobre 2025

