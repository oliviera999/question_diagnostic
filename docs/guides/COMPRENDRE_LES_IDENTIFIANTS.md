# 🆔 Comprendre les Identifiants dans le Plugin Question Diagnostic

## 📋 Vue d'ensemble

Le plugin affiche plusieurs types d'identifiants (ID) dans ses tableaux. Ce guide explique ce que représente chacun.

---

## 🔑 Types d'identifiants affichés

### 1️⃣ ID de Question
**Colonne : ID (première colonne)**

- **Ce que c'est** : L'identifiant unique de la question dans la table `question`
- **Exemple** : `12345`
- **Usage** : Permet d'identifier et de retrouver une question spécifique

### 2️⃣ ID de Catégorie de Questions
**Colonne : Catégorie**

- **Ce que c'est** : L'identifiant de la catégorie dans laquelle la question est organisée
- **Exemple** : `Mathématiques - Niveau 1 (ID: 42)`
- **Table Moodle** : `question_categories`
- **Usage** : Les catégories de questions permettent d'organiser les questions dans la banque de questions

**⚠️ Important** : Ne pas confondre avec les catégories de cours !

### 3️⃣ ID de Contexte
**Colonne : Contexte**

- **Ce que c'est** : L'identifiant du contexte Moodle où se trouve la question
- **Exemple** : `Cours : PHP101 (ID: 89)`
- **Table Moodle** : `context`
- **Usage** : Définit la portée et les permissions d'accès à la question

---

## 🌳 Comprendre les Contextes Moodle

Le **contexte** définit **où** une question est stockée dans la hiérarchie Moodle. Il existe plusieurs types :

### Types de contextes

| Type | Icône | Description | Exemple d'affichage |
|------|-------|-------------|---------------------|
| **Système** | 🌐 | Questions globales accessibles partout | `🌐 Système (ID: 1)` |
| **Catégorie** | 📁 | Questions dans une catégorie de cours | `Catégorie (ID: 15)` |
| **Cours** | 📚 | Questions spécifiques à un cours | `📚 Cours : PHP101 (ID: 89)` |
| **Module** | 📝 | Questions dans une activité (quiz, etc.) | `📝 Quiz : Test Final (ID: 234)` |

### ⚠️ Confusion fréquente : Catégorie vs Catégorie de Questions

**❌ Ce ne sont PAS la même chose !**

#### Catégorie de cours (Contexte)
- 📁 **Catégorie dans le contexte** = Une catégorie de cours Moodle
- **Hiérarchie** : Système > Catégorie de cours > Cours > Module
- **Exemple** : "Sciences", "Informatique", "Langues"
- **Affichage** : Dans la colonne **Contexte**

#### Catégorie de questions
- 📂 **Catégorie de questions** = Organisation des questions dans la banque
- **But** : Ranger et trier les questions par thème/sujet
- **Exemple** : "Algèbre", "Grammaire", "Histoire médiévale"
- **Affichage** : Dans la colonne **Catégorie**

### 📊 Exemple concret

Voici comment une question peut être organisée :

```
Contexte : 📚 Cours : Mathématiques 101 (ID: 42)
           ↑ Définit OÙ est la question

Catégorie de questions : Algèbre - Équations du second degré (ID: 156)
                         ↑ Définit COMMENT elle est rangée
```

**Explication** :
- La question est **stockée dans le cours "Mathématiques 101"** (contexte)
- Elle est **rangée dans la catégorie "Algèbre - Équations du second degré"** (organisation)

---

## 🔍 Pourquoi afficher les IDs ?

### Avantages des identifiants

1. **Traçabilité** : Permet de retrouver rapidement un élément dans la base de données
2. **Debugging** : Utile pour le support technique et le dépannage
3. **Intégration** : Facilite les requêtes SQL manuelles si nécessaire
4. **Audit** : Permet de suivre les relations entre objets

### Cas d'usage pratique

**Scénario** : Vous devez déplacer toutes les questions d'un contexte vers un autre

1. Notez l'ID du contexte source dans la colonne "Contexte"
2. Utilisez cet ID pour une requête SQL ou un script de migration
3. Vérifiez les changements en consultant les nouveaux IDs

---

## 📚 Ressources

- [Documentation Moodle sur les contextes](https://docs.moodle.org/en/Context)
- [Structure de la base de données Moodle](https://docs.moodle.org/dev/Database_Schema)
- [Guide développeur Question Bank](https://moodledev.io/docs/apis/subsystems/questionbank)

---

## 💡 Astuce

Si vous voyez "Catégorie (ID: XX)" dans la colonne **Contexte**, cela signifie que les questions sont stockées au niveau d'une **catégorie de cours Moodle**, et non dans un cours ou un module spécifique.

C'est généralement le cas pour des questions "partagées" entre plusieurs cours d'une même catégorie.

---

**Version** : v1.2.2  
**Date** : Octobre 2025  
**Auteur** : Plugin Question Diagnostic Team

