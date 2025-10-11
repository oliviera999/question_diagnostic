# ⚡ Guide de Démarrage Rapide

Un guide en 5 minutes pour commencer à utiliser l'outil de gestion des catégories de questions.

## 📥 Installation Express (3 minutes)

### 1️⃣ Télécharger et Copier
```bash
# Via terminal SSH
cd /chemin/vers/moodle/local/
git clone <votre-repo> question_diagnostic
# OU simplement copier le dossier via FTP
```

### 2️⃣ Installer le Plugin
1. Connexion en tant qu'**admin Moodle**
2. Aller sur : **Administration du site > Notifications**
3. Cliquer sur **"Mettre à jour la base de données"**
4. ✅ C'est installé !

### 3️⃣ Accéder à l'Outil
```
https://votre-moodle.com/local/question_diagnostic/index.php
```

---

## 🎯 Premiers Pas (2 minutes)

### Comprendre le Dashboard

Dès l'ouverture, vous voyez **5 cartes** :

| Carte | Signification | Action |
|-------|---------------|--------|
| 🔵 **Total Catégories** | Nombre total | Info générale |
| 🟡 **Catégories Vides** | Sans questions | À supprimer |
| 🔴 **Catégories Orphelines** | Contexte invalide | À vérifier |
| 🔴 **Doublons** | Même nom | À fusionner |
| 🟢 **Total Questions** | Dans toutes les cat. | Info générale |

---

## 🔥 Actions Rapides

### 👁️ Voir une Catégorie dans la Banque de Questions

**Méthode 1 : Cliquer sur le nom**
1. Dans le tableau, **cliquer sur le nom** de la catégorie (lien bleu avec 🔗)
2. La banque de questions s'ouvre dans un **nouvel onglet**
3. Vous voyez directement cette catégorie avec toutes ses questions

**Méthode 2 : Bouton "Voir"**
1. Cliquer sur le bouton **👁️ Voir** dans la colonne Actions
2. Même résultat : ouverture dans un nouvel onglet

💡 **Astuce** : Gardez l'outil ouvert dans un onglet et la banque dans un autre pour un workflow efficace !

---

### 🗑️ Supprimer des Catégories Vides

**Méthode 1 : Suppression simple**
1. Filtrer par statut : **"Vides"**
2. Cliquer sur **🗑️ Supprimer** à droite de la catégorie
3. Confirmer

**Méthode 2 : Suppression en masse**
1. Cocher les cases ✅ des catégories à supprimer
2. Cliquer sur **🗑️ Supprimer la sélection** (en haut)
3. Confirmer

⚠️ **Important** : Seules les catégories **vides** peuvent être supprimées.

---

### 🔀 Fusionner des Doublons

1. Repérer les doublons dans le tableau (badge 🔴)
2. Cliquer sur **🔀 Fusionner** pour la catégorie source
3. Dans le modal, sélectionner la **catégorie destination**
4. Cliquer sur **"Fusionner"**
5. ✅ Les questions sont automatiquement déplacées

---

### 🔍 Rechercher et Filtrer

**Recherche par nom**
```
Taper dans la barre : "Mathématiques"
→ Affiche toutes les catégories contenant "Mathématiques"
```

**Filtrer par statut**
- **Toutes** : Affiche tout
- **Vides** : Seulement les catégories sans questions
- **Orphelines** : Contexte invalide
- **OK** : Catégories saines

**Filtrer par contexte**
- Sélectionner un contexte spécifique
- Voir uniquement les catégories de ce contexte

💡 **Astuce** : Combinez les filtres ! (Ex : Vides + Contexte Cours)

---

### 📊 Trier le Tableau

Cliquer sur n'importe quel **en-tête de colonne** :
- 1er clic : Tri ascendant ▲
- 2ème clic : Tri descendant ▼

Exemples utiles :
- Trier par **"Questions"** → Voir les catégories les plus remplies
- Trier par **"Nom"** → Ordre alphabétique
- Trier par **"ID"** → Ordre chronologique de création

---

### 📥 Exporter les Données

1. Cliquer sur **📥 Exporter en CSV** (en haut)
2. Le fichier se télécharge automatiquement
3. Ouvrir avec Excel, LibreOffice, Google Sheets

**Contenu du CSV** :
- ID, Nom, Contexte, Parent
- Questions visibles, Questions totales
- Sous-catégories, Statut

💡 **Astuce** : Exportez avant toute opération en masse (backup)

---

## 🎓 Scénarios d'Usage

### Scénario 1 : Nettoyage Rapide

**Objectif** : Supprimer toutes les catégories vides

```
1. Cliquer sur filtre "Statut" → Vides
2. Cocher "Tout sélectionner" en haut du tableau
3. Cliquer "🗑️ Supprimer la sélection"
4. Confirmer
5. ✅ Terminé !
```

---

### Scénario 2 : Consolidation après Migration

**Objectif** : Fusionner les doublons créés lors d'un import

```
1. Regarder la carte "Doublons Détectés"
2. Si > 0, rechercher dans le tableau
3. Pour chaque doublon :
   - Cliquer "🔀 Fusionner" sur la catégorie à supprimer
   - Choisir la catégorie à conserver
   - Confirmer
4. Les questions sont automatiquement déplacées
5. La catégorie source est supprimée
```

---

### Scénario 3 : Audit de la Base

**Objectif** : Documenter l'état actuel de la banque de questions

```
1. Exporter en CSV
2. Ouvrir dans Excel
3. Créer des tableaux croisés dynamiques
4. Analyser :
   - Distribution par contexte
   - Catégories vides vs remplies
   - Questions par catégorie
```

---

### Scénario 4 : Recherche Ciblée

**Objectif** : Trouver une catégorie spécifique rapidement

```
# Par nom
Taper dans la recherche : "Algèbre"

# Par ID
Taper : "42"

# Par contexte + statut
Filtre Contexte : "Cours"
Filtre Statut : "Vides"
```

---

## 💡 Conseils d'Expert

### ✅ À Faire

- ✅ **Exporter régulièrement** pour avoir des backups
- ✅ **Filtrer avant sélectionner** (éviter les erreurs)
- ✅ **Vérifier le nombre sélectionné** avant suppression
- ✅ **Tester sur une catégorie** avant suppression en masse
- ✅ **Utiliser les badges** pour identifier rapidement

### ❌ À Éviter

- ❌ Supprimer sans vérifier le contenu
- ❌ Fusionner des catégories de contextes différents
- ❌ Oublier de confirmer les actions importantes
- ❌ Ne jamais exporter (pas de backup)

---

## 🔐 Sécurité

**Qui peut utiliser l'outil ?**
- ✅ Administrateurs du site uniquement
- ❌ Pas les enseignants
- ❌ Pas les étudiants
- ❌ Pas les administrateurs de cours

**Protection des données**
- ✅ Confirmations avant toute suppression
- ✅ Vérifications automatiques (catégories non vides protégées)
- ✅ Tokens de sécurité (CSRF protection)
- ✅ Logs Moodle de toutes les actions

---

## 🆘 Aide Rapide

### L'outil ne s'affiche pas ?
→ Vérifier : Êtes-vous **admin du site** ? (pas juste admin de cours)

### Erreur 404 ?
→ Vérifier le chemin : `moodle/local/question_diagnostic/index.php`

### CSS cassé ?
→ Purger les caches : **Admin > Développement > Purger les caches**

### Impossible de supprimer ?
→ Normal si la catégorie contient des questions ou sous-catégories

### Le tableau est vide ?
→ Vérifier qu'il existe des catégories dans votre Moodle

---

## 📚 Ressources

- 📖 **Documentation complète** : [README.md](README.md)
- 🔧 **Guide d'installation** : [INSTALLATION.md](INSTALLATION.md)
- 📋 **Historique des versions** : [CHANGELOG.md](CHANGELOG.md)

---

## 🎯 Checklist du Premier Usage

- [ ] Plugin installé via Administration > Notifications
- [ ] Accès à l'outil en tant qu'admin
- [ ] Dashboard visible avec statistiques
- [ ] Test de recherche fonctionne
- [ ] Test de filtrage fonctionne
- [ ] Export CSV téléchargé et ouvert
- [ ] Test de suppression d'1 catégorie vide OK
- [ ] Compréhension des badges de statut

**✅ Tout coché ? Vous êtes prêt ! 🚀**

---

## ⏱️ Temps de Maîtrise

| Niveau | Temps | Compétences |
|--------|-------|-------------|
| 🟢 **Débutant** | 5 min | Navigation, filtres |
| 🟡 **Intermédiaire** | 15 min | Suppression, export |
| 🔴 **Expert** | 30 min | Fusion, actions en masse |

---

**Besoin d'aide ? Ouvrez une issue sur le dépôt ! 🤝**

