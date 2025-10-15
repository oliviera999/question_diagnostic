# 🎨 Logique Visuelle : Protection des Catégories "Default for"

## 📊 Arbre de Décision (v1.10.3)

```
                    Catégorie "Default for..."
                              |
                              |
            ┌─────────────────┴─────────────────┐
            |                                   |
       Parent = 0 ?                        Parent > 0
            |                                   |
            ✅                                  |
      TOUJOURS PROTÉGÉE              Contexte Valide ?
    (Catégorie racine)                        |
                                    ┌─────────┴─────────┐
                                    |                   |
                               ✅ OUI               ❌ NON
                                    |                   |
                              PROTÉGÉE            Description ?
                        (Contexte actif)                |
                                              ┌─────────┴─────────┐
                                              |                   |
                                         ✅ OUI               ❌ NON
                                              |                   |
                                        PROTÉGÉE               Vide ?
                                     (Description)                |
                                                        ┌─────────┴─────────┐
                                                        |                   |
                                                   ✅ OUI               ❌ NON
                                                        |                   |
                                                  SUPPRIMABLE        NON SUPPRIMABLE
                                              (Orpheline vide)    (Contient données)
```

---

## 🔄 Comparaison Avant/Après

### 🔴 AVANT v1.10.3 : Protection Systématique

```
┌─────────────────────────────────────────┐
│  TOUTES les "Default for" PROTÉGÉES    │
├─────────────────────────────────────────┤
│                                         │
│  ✅ Default for Cours Actif            │
│     → PROTÉGÉE ✓                       │
│                                         │
│  ✅ Default for [Cours Supprimé]       │
│     → PROTÉGÉE ✗ (PROBLÈME)            │
│                                         │
│  ✅ Default for Context 999 (orphelin) │
│     → PROTÉGÉE ✗ (PROBLÈME)            │
│                                         │
└─────────────────────────────────────────┘

Résultat : Accumulation de catégories orphelines
           non supprimables 📈
```

### 🟢 APRÈS v1.10.3 : Protection Conditionnelle

```
┌─────────────────────────────────────────┐
│  Protection basée sur le CONTEXTE       │
├─────────────────────────────────────────┤
│                                         │
│  ✅ Default for Cours Actif            │
│     Contexte : ✅ Valide               │
│     → PROTÉGÉE ✓                       │
│                                         │
│  🗑️ Default for [Cours Supprimé]       │
│     Contexte : ❌ Orphelin             │
│     → SUPPRIMABLE ✓                    │
│                                         │
│  🗑️ Default for Context 999             │
│     Contexte : ❌ Invalide             │
│     → SUPPRIMABLE ✓                    │
│                                         │
└─────────────────────────────────────────┘

Résultat : Nettoyage intelligent possible 🧹
```

---

## 🎯 Matrice de Protection

```
┌───────────────┬───────────┬──────────┬──────────────┬──────────────┐
│   Catégorie   │  Contexte │ Parent   │  Description │  Protection  │
├───────────────┼───────────┼──────────┼──────────────┼──────────────┤
│ "Default for" │  ✅ Valide │    0     │      -       │  🛡️ RACINE   │
│ "Default for" │  ✅ Valide │   > 0    │      -       │  🛡️ CONTEXTE │
│ "Default for" │  ✅ Valide │   > 0    │    ✅ Oui    │  🛡️ DOUBLE   │
│ "Default for" │  ❌ Orphelin│   0     │      -       │  🛡️ RACINE   │
│ "Default for" │  ❌ Orphelin│  > 0    │      -       │  ✅ AUCUNE   │
│ "Default for" │  ❌ Orphelin│  > 0    │    ✅ Oui    │  🛡️ DESC     │
└───────────────┴───────────┴──────────┴──────────────┴──────────────┘

Légende :
🛡️ RACINE   = Protection racine (parent=0)
🛡️ CONTEXTE = Protection "Default for" (contexte actif)
🛡️ DESC     = Protection description
🛡️ DOUBLE   = Double protection (contexte + description)
✅ AUCUNE   = Aucune protection (supprimable si vide)
```

---

## 🔍 Cas d'Usage Visuels

### Cas 1 : Cours Actif

```
┌──────────────────────────────────────────────┐
│  Catégorie : "Default for Math 101"         │
│  ════════════════════════════════════        │
│                                              │
│  📚 Cours : Math 101 (ID: 5)                │
│     Status : ✅ ACTIF                       │
│                                              │
│  🏢 Contexte : Course (ID: 42)              │
│     Status : ✅ VALIDE (dans table context) │
│                                              │
│  📊 Données :                               │
│     Questions : 0                            │
│     Sous-cat. : 0                            │
│     Description : (vide)                     │
│     Parent : 15                              │
│                                              │
│  🛡️  PROTÉGÉE                               │
│  ❌  NON SUPPRIMABLE                        │
│                                              │
│  Raison : Catégorie par défaut Moodle       │
│           (contexte actif)                   │
└──────────────────────────────────────────────┘
```

### Cas 2 : Cours Supprimé

```
┌──────────────────────────────────────────────┐
│  Catégorie : "Default for Old Course 2022"  │
│  ════════════════════════════════════        │
│                                              │
│  📚 Cours : Old Course 2022                 │
│     Status : ❌ SUPPRIMÉ (n'existe plus)    │
│                                              │
│  🏢 Contexte : Course (ID: 999)             │
│     Status : ❌ ORPHELIN (pas dans context) │
│                                              │
│  📊 Données :                               │
│     Questions : 0                            │
│     Sous-cat. : 0                            │
│     Description : (vide)                     │
│     Parent : 15                              │
│                                              │
│  ✅  NON PROTÉGÉE                           │
│  ✅  SUPPRIMABLE                            │
│                                              │
│  Statut : Vide + Orpheline                  │
│  Action : 🗑️ Supprimer (disponible)        │
└──────────────────────────────────────────────┘
```

### Cas 3 : Catégorie Racine

```
┌──────────────────────────────────────────────┐
│  Catégorie : "Default for System"           │
│  ════════════════════════════════════        │
│                                              │
│  🏢 Contexte : System (ID: 1)               │
│     Status : ✅ VALIDE                      │
│                                              │
│  📊 Données :                               │
│     Questions : 0                            │
│     Sous-cat. : 0                            │
│     Description : (vide)                     │
│     Parent : 0  ← RACINE !                  │
│                                              │
│  🛡️  TOUJOURS PROTÉGÉE                     │
│  ❌  JAMAIS SUPPRIMABLE                     │
│                                              │
│  Raison : Catégorie racine (top-level)      │
│  Note : Même si contexte orphelin,          │
│         la protection RACINE est prioritaire│
└──────────────────────────────────────────────┘
```

---

## 📈 Impact sur le Dashboard

### Carte "Catégories Protégées"

```
AVANT v1.10.3                    APRÈS v1.10.3
──────────────                   ──────────────

┌──────────────────┐             ┌──────────────────┐
│  PROTÉGÉES       │             │  PROTÉGÉES       │
│                  │             │                  │
│      125         │             │      98          │
│                  │             │                  │
│  Tous types      │             │  Contexte valide │
└──────────────────┘             └──────────────────┘

Différence : -27 catégories
             (orphelines "Default for" exclues)
```

### Filtre "Sans questions ni sous-catégories (supprimables)"

```
AVANT v1.10.3                    APRÈS v1.10.3
──────────────                   ──────────────

┌──────────────────┐             ┌──────────────────┐
│  SUPPRIMABLES    │             │  SUPPRIMABLES    │
│                  │             │                  │
│      45          │             │      72          │
│                  │             │                  │
│  Hors "Default"  │             │  + "Default"     │
│                  │             │    orphelines    │
└──────────────────┘             └──────────────────┘

Différence : +27 catégories
             (orphelines "Default for" incluses)
```

---

## 🚦 Indicateurs Visuels dans l'Interface

### Colonne "Statut"

```
Catégorie Protégée (contexte valide)
──────────────────────────────────────
┌────────────────────────────────────┐
│  🛡️ PROTÉGÉE                       │
│  Catégorie par défaut Moodle       │
│  (contexte actif)                  │
└────────────────────────────────────┘


Catégorie Orpheline (contexte invalide)
──────────────────────────────────────
┌────────────────────────────────────┐
│  Vide    Orpheline                 │
└────────────────────────────────────┘
```

### Colonne "🗑️ Supprimable"

```
Catégorie Protégée
──────────────────
┌────────────────────────────────────┐
│  ❌ NON                            │
│  🛡️ Catégorie par défaut Moodle   │
│     (contexte actif)               │
└────────────────────────────────────┘


Catégorie Supprimable
─────────────────────
┌────────────────────────────────────┐
│  ✅ OUI                            │
└────────────────────────────────────┘
```

### Bouton d'Action

```
Protégée                    Supprimable
────────                    ───────────

┌──────────────┐           ┌──────────────┐
│ 🛡️ Protégée  │           │ 🗑️ Supprimer │
│  (désactivé) │           │   (cliquable)│
└──────────────┘           └──────────────┘
```

---

## 🔄 Workflow de Vérification

```
   DÉBUT
     |
     v
┌─────────────────┐
│ Catégorie       │
│ "Default for"   │
└────────┬────────┘
         |
         v
    ┌────────┐   NON
    │Parent=0?├────────┐
    └────┬───┘         |
         |OUI          v
         v         ┌────────────┐    NON
   ┌──────────┐   │ Contexte   ├──────────┐
   │PROTÉGÉE  │   │  Valide?   │          |
   │ (RACINE) │   └─────┬──────┘          v
   └──────────┘         |OUI         ┌──────────┐   NON
                        v            │Description├────────┐
                  ┌──────────┐      │  ?       │        |
                  │PROTÉGÉE  │      └────┬─────┘        v
                  │(CONTEXTE)│           |OUI      ┌──────────┐   NON
                  └──────────┘           v         │  Vide?   ├────────┐
                                   ┌──────────┐    └────┬─────┘        |
                                   │PROTÉGÉE  │         |OUI           v
                                   │  (DESC)  │         v          ┌──────────┐
                                   └──────────┘   ┌──────────┐    │   NON    │
                                                  │SUPPRIMABLE│   │SUPPRIMABLE│
                                                  │ (Orphelin)│   │(Questions)│
                                                  └──────────┘    └──────────┘
```

---

## 🎓 Mnémotechnique

```
D  efault
E  st
P  rotégée
S  i
C  ontexte
   actif

DEPSC = Default Est Protégée Si Contexte actif
```

---

**Version** : v1.10.3  
**Date** : 14 octobre 2025  
**Auteur** : Équipe local_question_diagnostic

