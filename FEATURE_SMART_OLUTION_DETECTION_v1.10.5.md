# 🧠 Recherche intelligente de la catégorie Olution (v1.10.5)

## 🎯 Amélioration

Le système détecte maintenant **automatiquement** la catégorie de questions partagées, même si elle ne s'appelle pas exactement "Olution".

## 🔍 Stratégies de recherche

Le système utilise **4 stratégies progressives** pour trouver la catégorie :

### Stratégie 1 : Nom exact (case-sensitive)
```
Cherche : "Olution"
Exemple : ✅ "Olution"
```

### Stratégie 2 : Nom contenant "olution" (case-insensitive)
```
Cherche : %olution%
Exemples : 
  ✅ "olution"
  ✅ "Olution"
  ✅ "OLUTION"
  ✅ "Questions Olution"
  ✅ "Banque Olution"
  ✅ "Olution - Banque centrale"
```

### Stratégie 3 : Description contenant "olution"
```
Cherche : %olution% dans le nom OU la description
Exemples :
  ✅ Nom: "Banque centrale" + Description: "Catégorie olution pour questions partagées"
  ✅ Nom: "Questions système" + Description: "Solution de partage"
```

### Stratégie 4 : Mots-clés dans la description
```
Cherche :
  • "banque centrale"
  • "questions partagées"
  • "partagé"
  
Exemples :
  ✅ Nom: "Questions système" + Description: "Banque centrale de questions"
  ✅ Nom: "QCM" + Description: "Questions partagées entre tous les cours"
```

## ✨ Avantages

### 1. Plus de flexibilité
- ✅ Pas besoin de nommer exactement "Olution"
- ✅ Accepte différentes casses (olution, Olution, OLUTION)
- ✅ Accepte des préfixes/suffixes ("Questions Olution", "Olution v2")

### 2. Détection automatique
- ✅ Le système trouve lui-même la catégorie appropriée
- ✅ Pas besoin de configuration manuelle
- ✅ Fonctionne avec vos conventions de nommage

### 3. Multi-critères
- ✅ Cherche dans le nom ET la description
- ✅ Utilise des mots-clés intelligents
- ✅ Priorise les résultats (exact → flexible → mots-clés)

## 📋 Exemples de catégories détectées

### ✅ Noms acceptés

| Nom de la catégorie | Détecté ? | Stratégie |
|---------------------|-----------|-----------|
| Olution | ✅ Oui | 1 (exact) |
| olution | ✅ Oui | 2 (flexible) |
| OLUTION | ✅ Oui | 2 (flexible) |
| Questions Olution | ✅ Oui | 2 (flexible) |
| Banque Olution | ✅ Oui | 2 (flexible) |
| Olution - 2024 | ✅ Oui | 2 (flexible) |
| QCM Olution | ✅ Oui | 2 (flexible) |

### ✅ Descriptions acceptées

| Nom | Description | Détecté ? | Stratégie |
|-----|-------------|-----------|-----------|
| Questions système | Catégorie olution pour le partage | ✅ Oui | 3 (description) |
| Banque centrale | Questions partagées entre cours | ✅ Oui | 4 (mots-clés) |
| QCM | Banque centrale de questions | ✅ Oui | 4 (mots-clés) |
| Questions | Repository partagé | ✅ Oui | 4 (mots-clés) |

### ❌ Cas NON détectés

| Nom | Description | Détecté ? | Raison |
|-----|-------------|-----------|--------|
| Questions | (vide) | ❌ Non | Trop générique |
| Système | QCM de base | ❌ Non | Aucun mot-clé |
| Test | Catégorie test | ❌ Non | Aucun mot-clé |

## 🔧 Configuration recommandée

### Option 1 : Nom simple (RECOMMANDÉ)
```
Nom : Olution
Parent : (aucun - racine)
Contexte : Système
Description : (optionnel)
```

### Option 2 : Nom descriptif
```
Nom : Questions Olution
Parent : (aucun - racine)
Contexte : Système
Description : Banque centrale de questions partagées
```

### Option 3 : Nom personnalisé avec description
```
Nom : Banque Centrale QCM
Parent : (aucun - racine)
Contexte : Système
Description : Catégorie olution pour questions partagées entre tous les cours
```

## 🎨 Interface utilisateur

### Indication visuelle

Quand une catégorie est trouvée, l'interface affiche :

```
✅ Catégorie système détectée : Olution (ID: 123)
```

ou

```
✅ Catégorie système détectée : Questions Olution (ID: 456)
```

### Message d'aide amélioré

Si aucune catégorie n'est trouvée, le message explique :

```
Aucune catégorie système de questions partagées n'a été trouvée

Pour utiliser cette fonctionnalité, créez une catégorie de questions 
au niveau système (contexte : Système) avec :
• Un nom contenant "Olution" (ex: "Olution", "Questions Olution", "Banque Olution")
• OU une description contenant "olution", "banque centrale" ou "questions partagées"

Le système détectera automatiquement cette catégorie comme catégorie 
principale de questions partagées.
```

## 🔍 Mode debug

Pour voir quelle stratégie a été utilisée, activer le mode debug Moodle :

```php
// Dans config.php
$CFG->debug = (E_ALL | E_STRICT);
$CFG->debugdisplay = 1;
```

Messages de debug affichés :
```
Olution category found with flexible search: Questions Olution
Olution category found via description: Banque centrale
Olution category found via keywords: Questions système
```

## 📊 Priorité des stratégies

Les stratégies sont appliquées dans l'ordre :

1. ⭐⭐⭐ **Nom exact** → Plus spécifique, plus fiable
2. ⭐⭐ **Nom flexible** → Très probable
3. ⭐ **Description** → Moins précis, mais utile
4. ⭐ **Mots-clés** → Dernier recours, peut donner de faux positifs

## 🚀 Migration depuis v1.10.4

### Si vous aviez déjà "Olution"
✅ **Aucune action requise** - fonctionne exactement pareil

### Si vous aviez un autre nom
✅ **Aucune action requise** - le système le trouvera automatiquement

### Si vous n'aviez rien
⚠️ Créez une catégorie système avec un nom ou description contenant "olution" ou les mots-clés

## 🧪 Tests

### Test 1 : Nom exact
```
1. Créer catégorie "Olution" au niveau système
2. Accéder à olution_duplicates.php
3. ✅ Vérifier : "Catégorie système détectée : Olution"
```

### Test 2 : Nom flexible
```
1. Renommer en "Questions Olution"
2. Purger les caches
3. Accéder à olution_duplicates.php
4. ✅ Vérifier : "Catégorie système détectée : Questions Olution"
```

### Test 3 : Description
```
1. Créer catégorie "Banque QCM"
2. Description : "Catégorie olution pour le partage"
3. Purger les caches
4. Accéder à olution_duplicates.php
5. ✅ Vérifier : "Catégorie système détectée : Banque QCM"
```

### Test 4 : Mots-clés
```
1. Créer catégorie "Questions système"
2. Description : "Banque centrale de questions partagées"
3. Purger les caches
4. Accéder à olution_duplicates.php
5. ✅ Vérifier : "Catégorie système détectée : Questions système"
```

## 💡 Bonnes pratiques

### ✅ Recommandé
- Utiliser "Olution" dans le nom (simple et clair)
- Ajouter une description explicite
- Une seule catégorie système racine avec ces critères

### ⚠️ À éviter
- Plusieurs catégories système avec "olution" dans le nom
- Descriptions trop génériques sans mot-clé
- Catégories avec parent (doivent être racine, parent=0)

## 🔒 Sécurité

- ✅ Ne cherche QUE dans les catégories **système** (CONTEXT_SYSTEM)
- ✅ Ne cherche QUE les catégories **racine** (parent = 0)
- ✅ Retourne la **première** correspondance trouvée
- ✅ Ne modifie RIEN automatiquement

## 📝 Code technique

### Fonction principale

```php
function local_question_diagnostic_find_olution_category()
```

Située dans : `lib.php` (lignes 795-881)

### Utilisation

```php
$olution = local_question_diagnostic_find_olution_category();

if ($olution) {
    echo "Trouvée : " . $olution->name;
} else {
    echo "Non trouvée";
}
```

## 📖 Fichiers modifiés

- **`lib.php`** : Fonction de recherche intelligente
- **`olution_duplicates.php`** : Affichage du nom détecté
- **`lang/fr/local_question_diagnostic.php`** : Messages FR mis à jour
- **`lang/en/local_question_diagnostic.php`** : Messages EN mis à jour
- **`version.php`** : Version incrémentée à v1.10.5

## 🎊 Résultat

✅ Le système trouve maintenant automatiquement votre catégorie de questions partagées !
✅ Plus besoin de s'inquiéter du nom exact
✅ Flexibilité maximale pour vos conventions de nommage

---

**Version** : v1.10.5
**Date** : 14 octobre 2025
**Type** : Amélioration (enhancement)

