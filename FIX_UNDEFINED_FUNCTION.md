# 🔧 FIX : Erreur "Call to undefined function local_question_diagnostic_get_parent_url()"

## 📋 Problème

Vous rencontrez l'erreur suivante lors de la suppression de questions :

```
Exception : Call to undefined function local_question_diagnostic_get_parent_url()
```

## ✅ Solution Rapide (3 étapes)

### **Étape 1 : Vérifier que les fichiers sont à jour sur le serveur**

⚠️ **IMPORTANT** : Ce dépôt Git (`C:\Users\olivi\OneDrive\Bureau\moodle_dev-questions\`) doit être synchronisé avec votre installation Moodle.

**Où se trouve votre installation Moodle ?**

- **Serveur local (XAMPP/WAMP)** : Probablement `C:\xampp\htdocs\moodle\local\question_diagnostic\`
- **Serveur distant** : `/var/www/moodle/local/question_diagnostic/` (ou similaire)

**Actions :**

1. **Si développement local :**
   ```powershell
   # Ouvrez PowerShell et copiez les fichiers
   # Remplacez CHEMIN_MOODLE par le vrai chemin
   Copy-Item -Path "C:\Users\olivi\OneDrive\Bureau\moodle_dev-questions\*" `
             -Destination "C:\xampp\htdocs\moodle\local\question_diagnostic\" `
             -Recurse -Force
   ```

2. **Si serveur distant :**
   - Uploadez tous les fichiers via FTP/SFTP
   - OU utilisez `git pull` si vous avez cloné le dépôt sur le serveur

### **Étape 2 : Purger les caches Moodle**

**Option A : Via le script automatique (RECOMMANDÉ)**

1. Accédez à : `http://votresite.moodle/local/question_diagnostic/purge_cache.php`
2. Cliquez sur **"Purger les Caches Maintenant"**
3. Attendez la confirmation de succès

**Option B : Via l'interface Moodle**

1. Allez dans : **Administration du site** → **Développement** → **Purger les caches**
2. Cliquez sur **"Purger les caches"**

**Option C : URL directe**

```
http://votresite.moodle/admin/purgecaches.php?confirm=1&sesskey=VOTRE_SESSKEY
```

### **Étape 3 : Tester**

1. **Test automatique** : Accédez à `http://votresite.moodle/local/question_diagnostic/test_function.php`
   - ✅ Tous les tests doivent être verts
   - Si un test échoue, suivez les instructions affichées

2. **Test manuel** : Essayez de supprimer une question
   - Allez dans **Gestion Questions** → **Questions**
   - Cliquez sur l'icône de suppression d'une question doublon inutilisée
   - L'erreur devrait avoir disparu

## 🔍 Diagnostic Avancé

Si le problème persiste après ces 3 étapes :

### Vérification 1 : Le fichier lib.php est-il à jour ?

1. Ouvrez le fichier `lib.php` sur votre serveur Moodle
2. Cherchez la ligne **665** (environ)
3. Vous devriez voir :
   ```php
   function local_question_diagnostic_get_parent_url($current_page) {
   ```
4. **Si cette fonction n'existe pas** :
   - Le fichier `lib.php` n'est PAS à jour sur le serveur
   - → Retournez à l'**Étape 1** ci-dessus

### Vérification 2 : Le chemin d'inclusion est-il correct ?

Dans le fichier `actions/delete_question.php` (ligne 24), vous devriez voir :
```php
require_once(__DIR__ . '/../lib.php');
```

Ce chemin devrait pointer vers `local/question_diagnostic/lib.php` depuis `local/question_diagnostic/actions/delete_question.php`.

### Vérification 3 : Erreurs PHP

Consultez les logs PHP de votre serveur :

**Windows (XAMPP) :**
```
C:\xampp\apache\logs\error.log
```

**Linux :**
```bash
tail -f /var/log/apache2/error.log
# ou
tail -f /var/log/nginx/error.log
```

Recherchez des erreurs liées à `lib.php` ou `local_question_diagnostic`.

## 🆘 Solutions de Secours

### Solution 1 : Réinstallation du plugin

```bash
# 1. Sauvegarde (si modifications locales importantes)
cd /chemin/vers/moodle/local/
cp -r question_diagnostic question_diagnostic.backup

# 2. Télécharger la dernière version
cd question_diagnostic
git pull origin master

# 3. Purger les caches via CLI (si accès SSH)
cd /chemin/vers/moodle/
php admin/cli/purge_caches.php
```

### Solution 2 : Vérification manuelle de tous les fichiers

Assurez-vous que TOUS ces fichiers existent et sont à jour sur le serveur :

- ✅ `local/question_diagnostic/lib.php` (doit contenir `local_question_diagnostic_get_parent_url()`)
- ✅ `local/question_diagnostic/actions/delete_question.php`
- ✅ `local/question_diagnostic/actions/delete_questions_bulk.php`
- ✅ `local/question_diagnostic/version.php`

### Solution 3 : Commit et Push des modifications

Si vous avez modifié `lib.php` localement mais pas commité :

```bash
cd "C:\Users\olivi\OneDrive\Bureau\moodle_dev-questions"

# Voir les fichiers modifiés
git status

# Ajouter les modifications
git add lib.php

# Commiter
git commit -m "Fix: Add local_question_diagnostic_get_parent_url() function"

# Pusher (si vous avez un dépôt distant)
git push origin master
```

Puis, sur le serveur Moodle, faites un `git pull`.

## 📝 Checklist Complète

- [ ] **Étape 1 :** Les fichiers du dépôt sont copiés vers l'installation Moodle
- [ ] **Étape 2 :** Les caches Moodle ont été purgés (via `purge_cache.php` ou interface admin)
- [ ] **Étape 3 :** Le test `test_function.php` affiche tous les tests en vert
- [ ] **Étape 4 :** Le cache du navigateur a été vidé (Ctrl+Shift+Delete)
- [ ] **Étape 5 :** La suppression de question fonctionne sans erreur

## 📊 Fichiers Créés pour vous Aider

1. **`purge_cache.php`** : Script automatique de purge des caches
   - Accès : `http://votresite.moodle/local/question_diagnostic/purge_cache.php`

2. **`test_function.php`** : Test de diagnostic
   - Accès : `http://votresite.moodle/local/question_diagnostic/test_function.php`

3. **`PURGE_CACHE_INSTRUCTIONS.md`** : Instructions détaillées

4. **`FIX_UNDEFINED_FUNCTION.md`** : Ce fichier

## 💡 Pourquoi cette Erreur ?

Cette erreur se produit lorsque :

1. ✅ **Le code appelle la fonction** : `delete_question.php` ligne 41
2. ❌ **La fonction n'est pas définie** dans la mémoire PHP

**Causes possibles :**

- **Cache Moodle** : PHP a mis en cache l'ancien `lib.php` sans la fonction
- **Fichiers non synchronisés** : Votre dépôt Git n'est pas synchronisé avec le serveur Moodle
- **Inclusion manquante** : `lib.php` n'est pas correctement inclus (mais ce n'est pas le cas ici, nous l'avons vérifié)

## 🎓 Prévenir ce Problème

Pour éviter ce problème à l'avenir :

1. **Toujours purger les caches** après modification de `lib.php`
2. **Synchroniser régulièrement** votre dépôt Git avec votre installation Moodle
3. **Tester immédiatement** après chaque modification
4. **Utiliser un workflow Git propre** : commit → push → pull sur serveur → purge cache

---

**Version** : v1.9.50  
**Date** : Octobre 2025  
**Auteur** : Plugin Question Diagnostic Team

Si le problème persiste après toutes ces étapes, partagez les résultats de `test_function.php` et les logs PHP.

