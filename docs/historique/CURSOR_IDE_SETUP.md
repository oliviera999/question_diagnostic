# 🚀 Configuration Cursor IDE pour Plugin Moodle

## 📋 Prérequis

### 1. Installation PHP (Windows)

#### Option A : XAMPP (Recommandé)
```bash
# Télécharger XAMPP depuis https://www.apachefriends.org/
# Installer avec PHP 8.0+ inclus
# Ajouter au PATH : C:\xampp\php
```

#### Option B : PHP Standalone
```bash
# Télécharger PHP depuis https://windows.php.net/download/
# Extraire dans C:\php
# Ajouter C:\php au PATH Windows
```

### 2. Installation Composer
```bash
# Télécharger depuis https://getcomposer.org/download/
# Installer globalement sur Windows
# Vérifier : composer --version
```

### 3. Vérification Installation
```bash
# Ouvrir PowerShell et vérifier :
php --version    # Doit afficher PHP 7.4+ ou 8.0+
composer --version # Doit afficher Composer 2.0+
```

## 🔧 Configuration Cursor IDE

### 1. Extensions Requises
Installer les extensions recommandées via `.vscode/extensions.json` :
- **PHP Intelephense** : Autocomplétion PHP avancée
- **PHP Debug** : Débogage PHP
- **Prettier** : Formatage automatique
- **GitLens** : Intégration Git avancée

### 2. Configuration PHP
Le fichier `.vscode/settings.json` est déjà configuré avec :
- ✅ Chemin PHP automatique
- ✅ Autocomplétion Moodle
- ✅ Formatage automatique
- ✅ Exclusions de fichiers

### 3. Tâches Disponibles
Utiliser `Ctrl+Shift+P` → "Tasks: Run Task" :
- **PHP: Lint Current File** : Vérifier la syntaxe PHP
- **PHP: Run PHPUnit Tests** : Exécuter les tests
- **Moodle: Purge Cache** : Vider le cache Moodle
- **Composer: Install Dependencies** : Installer les dépendances

## 🧪 Configuration Tests

### 1. Installation PHPUnit
```bash
composer install
```

### 2. Configuration Base de Données Test
Modifier `phpunit.xml` si nécessaire :
```xml
<env name="MOODLE_TEST_DB" value="moodle_test"/>
<env name="MOODLE_TEST_DBHOST" value="localhost"/>
<env name="MOODLE_TEST_DBUSER" value="root"/>
<env name="MOODLE_TEST_DBPASS" value=""/>
```

### 3. Exécution Tests
```bash
# Via Cursor IDE
Ctrl+Shift+P → "Tasks: Run Task" → "PHP: Run PHPUnit Tests"

# Via terminal
composer test
```

## 🔍 Qualité de Code

### 1. Standards de Code
```bash
# PHP CodeSniffer
composer phpcs

# PHP Mess Detector
composer phpmd

# PHPStan (analyse statique)
composer phpstan
```

### 2. Correction Automatique
```bash
# Correction automatique
composer cs-fix
```

## 🚀 Optimisations Cursor IDE

### 1. Performance
Les paramètres suivants sont déjà configurés dans `settings.json` :
- ✅ `cursor.maxConcurrentThreads: 2`
- ✅ Indexation optimisée
- ✅ Exclusions de fichiers lourds

### 2. Autocomplétion Moodle
- ✅ Stubs PHP inclus
- ✅ Fonctions Moodle reconnues
- ✅ Classes Moodle disponibles

### 3. Debugging
Configuration dans `.vscode/launch.json` :
- ✅ PHP Debug
- ✅ PHPUnit Tests
- ✅ Moodle CLI

## 🔧 Résolution de Problèmes

### Erreur "PHP not found"
1. Vérifier le PATH Windows
2. Redémarrer Cursor IDE
3. Vérifier le chemin dans `settings.json`

### Erreurs de linting Moodle
1. Installer les extensions PHP
2. Vérifier la configuration `intelephense`
3. Purger le cache Cursor IDE

### Tests qui échouent
1. Vérifier la configuration base de données
2. Créer la base de test : `moodle_test`
3. Vérifier les permissions

## 📚 Ressources

- [Documentation Cursor IDE](https://docs.cursor.com/)
- [PHP Intelephense](https://marketplace.visualstudio.com/items?itemName=bmewburn.vscode-intelephense-client)
- [Moodle Developer Docs](https://moodledev.io/)
- [PHPUnit Documentation](https://phpunit.readthedocs.io/)

## ✅ Checklist Final

- [ ] PHP 7.4+ installé et dans le PATH
- [ ] Composer installé et fonctionnel
- [ ] Extensions Cursor IDE installées
- [ ] Configuration `.vscode/` en place
- [ ] Tests PHPUnit fonctionnels
- [ ] Autocomplétion Moodle active
- [ ] Formatage automatique activé
- [ ] Debugging configuré

---

**🎉 Votre environnement Cursor IDE est maintenant optimisé pour le développement Moodle !**
