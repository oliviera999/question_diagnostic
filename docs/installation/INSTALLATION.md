# 📦 Guide d'Installation

## Prérequis

- **Moodle 4.3+** (testé sur Moodle 4.5)
- **Accès administrateur** du site Moodle
- **Accès FTP/SSH** au serveur ou accès au gestionnaire de fichiers

## 🚀 Installation Complète

### Étape 1 : Préparation des fichiers

1. **Télécharger ou cloner** ce dépôt
2. **Renommer le dossier** en `question_diagnostic`
3. Vérifier la structure :

```
question_diagnostic/
├── index.php
├── version.php
├── lib.php
├── README.md
├── INSTALLATION.md
├── classes/
│   └── category_manager.php
├── actions/
│   ├── delete.php
│   ├── merge.php
│   ├── move.php
│   └── export.php
├── styles/
│   └── main.css
├── scripts/
│   └── main.js
└── lang/
    ├── en/
    │   └── local_question_diagnostic.php
    └── fr/
        └── local_question_diagnostic.php
```

### Étape 2 : Copier dans Moodle

**Méthode A : Via FTP/SFTP**

1. Se connecter au serveur via FTP/SFTP
2. Naviguer vers `moodle/local/`
3. Créer le dossier `question_diagnostic`
4. Copier **tous les fichiers** dans ce dossier

**Méthode B : Via ligne de commande (SSH)**

```bash
cd /chemin/vers/moodle/local/
mkdir question_diagnostic
cd question_diagnostic
# Copier les fichiers ici
```

**Méthode C : Via gestionnaire de fichiers (cPanel, etc.)**

1. Accéder au gestionnaire de fichiers
2. Naviguer vers `public_html/moodle/local/`
3. Créer le dossier et uploader les fichiers

### Étape 3 : Installation du plugin

1. **Se connecter** en tant qu'administrateur Moodle
2. Aller dans : **Administration du site > Notifications**
3. Moodle détectera automatiquement le nouveau plugin
4. Cliquer sur **"Mettre à jour la base de données de Moodle maintenant"**
5. Attendre la fin de l'installation

### Étape 4 : Vérification

1. L'installation affiche : ✅ **Installation réussie**
2. Aller sur : `https://votre-moodle.com/local/question_diagnostic/index.php`
3. Vous devriez voir le dashboard avec les statistiques

## ⚠️ Problèmes Courants

### Erreur : "Access denied"

**Cause** : Vous n'êtes pas administrateur du site

**Solution** :
1. Vérifier que vous êtes connecté en tant qu'admin
2. Aller dans : **Administration du site > Utilisateurs > Permissions > Définir les rôles**
3. Vérifier que votre rôle a la capacité `moodle/site:config`

### Erreur : "Plugin not found"

**Cause** : Les fichiers ne sont pas au bon endroit

**Solution** :
1. Vérifier que le dossier est bien `moodle/local/question_diagnostic/`
2. Vérifier que `version.php` existe à la racine
3. Vérifier les permissions des fichiers (644 pour les fichiers, 755 pour les dossiers)

### Les CSS/JS ne se chargent pas

**Cause** : Cache Moodle

**Solution** :
1. Aller dans : **Administration du site > Développement > Purger les caches**
2. Cliquer sur **"Purger tous les caches"**
3. Rafraîchir la page (Ctrl+F5)

### Erreur 404 sur les actions

**Cause** : Chemin incorrect

**Solution** :
1. Vérifier que tous les fichiers dans `actions/` sont présents
2. Vérifier les permissions (644)
3. Vérifier que le fichier `.htaccess` n'interfère pas

## 🔧 Configuration

### Permissions Fichiers (Linux/Unix)

```bash
cd /chemin/vers/moodle/local/question_diagnostic/
chmod 755 .
chmod 644 *.php
chmod 755 classes actions styles scripts lang
chmod 644 classes/*.php actions/*.php
chmod 644 styles/*.css scripts/*.js
chmod 644 lang/en/*.php lang/fr/*.php
```

### Configuration Apache (optionnel)

Si vous voulez créer un alias :

```apache
<Directory "/chemin/vers/moodle/local/question_diagnostic">
    Options FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

## 🧪 Test de l'Installation

Créer un fichier `test.php` à la racine du plugin :

```php
<?php
require_once(__DIR__ . '/../../config.php');
require_login();

if (!is_siteadmin()) {
    die('Accès refusé');
}

echo "✅ Configuration Moodle OK<br>";
echo "✅ Utilisateur admin OK<br>";
echo "✅ Base de données : " . $DB->count_records('question_categories') . " catégories<br>";
echo "✅ Plugin correctement installé !";
```

Accéder à : `https://votre-moodle.com/local/question_diagnostic/test.php`

⚠️ **Supprimer `test.php` après le test !**

## 🔄 Mise à Jour

### Depuis une version antérieure

1. **Sauvegarder** les fichiers existants
2. **Remplacer** tous les fichiers par les nouveaux
3. Aller dans : **Administration du site > Notifications**
4. Suivre le processus de mise à jour

### Mise à jour manuelle de la version

Dans `version.php`, augmenter le numéro :

```php
$plugin->version = 2025010701; // Incrémenter
```

## 🗑️ Désinstallation

1. Aller dans : **Administration du site > Plugins > Vue d'ensemble des plugins**
2. Trouver **"Question Category Management"**
3. Cliquer sur **"Désinstaller"**
4. Confirmer la désinstallation
5. Supprimer manuellement le dossier `moodle/local/question_diagnostic/`

⚠️ **Note** : Aucune donnée n'est perdue (le plugin ne modifie pas les tables de questions)

## 📊 Post-Installation

### Première utilisation recommandée

1. ✅ Consulter le **dashboard** pour avoir une vue d'ensemble
2. ✅ **Exporter** les données en CSV (sauvegarde)
3. ✅ Tester les **filtres** pour vous familiariser
4. ✅ Identifier les **catégories vides**
5. ✅ Faire un **test de suppression** sur une catégorie de test

### Bonnes pratiques

- 💾 **Toujours sauvegarder** avant des opérations en masse
- 🔍 **Filtrer** avant de sélectionner
- ✅ **Vérifier** les catégories avant suppression
- 📊 **Exporter** régulièrement pour audit

## 🆘 Support

En cas de problème :

1. Vérifier les **logs PHP** : `moodle/config.php` → activer le débogage
2. Vérifier les **logs Moodle** : Administration du site > Rapports > Journaux
3. Consulter la **documentation** : README.md
4. Ouvrir une **issue** sur le dépôt

## 📝 Checklist d'Installation

- [ ] Fichiers copiés dans `moodle/local/question_diagnostic/`
- [ ] Permissions correctes (644/755)
- [ ] Installation via Administration > Notifications
- [ ] Accès à l'outil en tant qu'admin
- [ ] Dashboard s'affiche correctement
- [ ] CSS/JS chargés (pas d'erreurs console)
- [ ] Filtres fonctionnent
- [ ] Export CSV fonctionne
- [ ] Test de suppression d'une catégorie vide OK
- [ ] Cache Moodle purgé

---

**Félicitations ! Votre outil de gestion est prêt ! 🎉**

