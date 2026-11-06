# 🚀 Guide de Déploiement - Fix v1.9.51

## 📦 Contenu de cette Version

Version **v1.9.51** - Outils de Diagnostic et Résolution du Cache

**5 nouveaux fichiers** :
1. `purge_cache.php` - Interface de purge des caches
2. `test_function.php` - Diagnostic automatique
3. `FIX_UNDEFINED_FUNCTION.md` - Guide complet
4. `PURGE_CACHE_INSTRUCTIONS.md` - Instructions détaillées
5. `QUICK_FIX_README.txt` - Résumé rapide

**1 fichier modifié** :
- `CHANGELOG.md` - Documentation du correctif v1.9.51

## 🎯 Objectif

Résoudre l'erreur `Call to undefined function local_question_diagnostic_get_parent_url()` qui se produit lors de la suppression de questions.

## 📋 Étapes de Déploiement

### Étape 1 : Synchroniser les Fichiers

**Option A : Développement Local (XAMPP/WAMP)**

```powershell
# Ouvrez PowerShell en tant qu'administrateur
cd "C:\Users\olivi\OneDrive\Bureau\moodle_dev-questions"

# Remplacez CHEMIN_MOODLE par votre installation réelle
# Exemple : C:\xampp\htdocs\moodle\local\question_diagnostic
$destination = "CHEMIN_MOODLE\local\question_diagnostic"

# Copier tous les fichiers
Copy-Item -Path ".\*" -Destination $destination -Recurse -Force

# Vérifier que les nouveaux fichiers sont bien copiés
Get-ChildItem $destination\*.php | Where-Object { $_.Name -like "purge_cache.php" -or $_.Name -like "test_function.php" }
```

**Option B : Serveur Distant (FTP/SFTP)**

1. Connectez-vous à votre serveur via FTP/SFTP
2. Uploadez ces 5 nouveaux fichiers vers `local/question_diagnostic/` :
   - `purge_cache.php`
   - `test_function.php`
   - `FIX_UNDEFINED_FUNCTION.md`
   - `PURGE_CACHE_INSTRUCTIONS.md`
   - `QUICK_FIX_README.txt`
3. Remplacez le fichier `CHANGELOG.md` existant

**Option C : Git Pull (Si le dépôt est sur le serveur)**

```bash
# SSH vers le serveur
ssh user@votre-serveur.com

# Naviguer vers le plugin
cd /var/www/moodle/local/question_diagnostic/

# Sauvegarder les modifications locales (si nécessaire)
git stash

# Tirer les dernières modifications
git pull origin master

# Vérifier que les nouveaux fichiers sont présents
ls -la *.php | grep -E "purge_cache|test_function"
```

### Étape 2 : Purger les Caches Moodle

**Option A : Via le Script Automatique (RECOMMANDÉ)**

1. Accédez à : `http://votresite.moodle/local/question_diagnostic/purge_cache.php`
2. Connectez-vous en tant qu'administrateur
3. Cliquez sur **"Purger les Caches Maintenant"**
4. Attendez la confirmation de succès (✅)

**Option B : Via l'Interface Moodle**

1. Connectez-vous en tant qu'administrateur
2. Allez dans : **Administration du site** → **Développement** → **Purger les caches**
3. Cliquez sur **"Purger les caches"**

**Option C : Via CLI (Serveur Linux)**

```bash
cd /var/www/moodle/
php admin/cli/purge_caches.php
```

### Étape 3 : Tester le Déploiement

**Test Automatique (RECOMMANDÉ)**

1. Accédez à : `http://votresite.moodle/local/question_diagnostic/test_function.php`
2. Vérifiez que tous les tests sont verts (✅) :
   - ✅ Test 1 : lib.php existe
   - ✅ Test 2 : Fonction local_question_diagnostic_get_parent_url() existe
   - ✅ Test 3 : La fonction s'exécute correctement

**Test Manuel**

1. Allez dans : Moodle → **Gestion Questions** (via le plugin)
2. Cliquez sur **"Questions"** dans le menu
3. Trouvez une question doublon inutilisée
4. Cliquez sur l'icône de suppression (🗑️)
5. L'erreur `Call to undefined function` ne devrait **plus apparaître**
6. La page de confirmation devrait s'afficher correctement

### Étape 4 : Vider le Cache du Navigateur

**Tous les Navigateurs**

- **Windows** : `Ctrl + Shift + Delete`
- **Mac** : `Cmd + Shift + Delete`

Sélectionnez :
- ✅ Cookies et données de site
- ✅ Images et fichiers en cache
- ✅ Données hébergées d'applications (si disponible)

Puis cliquez sur **"Effacer les données"**

**OU** ouvrez une fenêtre de navigation privée/incognito pour tester.

## ✅ Checklist de Validation

- [ ] Les 5 nouveaux fichiers sont présents sur le serveur Moodle
- [ ] `CHANGELOG.md` a été mis à jour avec la version v1.9.51
- [ ] Les caches Moodle ont été purgés
- [ ] `test_function.php` affiche tous les tests en vert ✅
- [ ] Le cache du navigateur a été vidé
- [ ] La suppression de question fonctionne sans erreur
- [ ] L'erreur `Call to undefined function` a disparu

## 🐛 Si le Problème Persiste

### Diagnostic Étape par Étape

1. **Vérifier que `purge_cache.php` est accessible**
   ```
   http://votresite.moodle/local/question_diagnostic/purge_cache.php
   ```
   - Si erreur 404 → Les fichiers ne sont pas sur le serveur
   - Si erreur 500 → Consulter les logs PHP

2. **Exécuter `test_function.php`**
   ```
   http://votresite.moodle/local/question_diagnostic/test_function.php
   ```
   - Si Test 1 échoue → `lib.php` n'est pas à jour
   - Si Test 2 échoue → La fonction n'existe pas dans `lib.php`
   - Si Test 3 échoue → Problème d'exécution (erreur PHP)

3. **Vérifier manuellement `lib.php`**
   
   Ouvrez `local/question_diagnostic/lib.php` sur le serveur et cherchez la ligne **665** :
   ```php
   function local_question_diagnostic_get_parent_url($current_page) {
   ```
   
   - Si cette ligne n'existe pas → Le fichier `lib.php` n'est PAS à jour
   - → Retournez à l'**Étape 1** du déploiement

4. **Consulter les Logs**

   **Windows (XAMPP)** :
   ```
   C:\xampp\apache\logs\error.log
   ```
   
   **Linux** :
   ```bash
   tail -f /var/log/apache2/error.log
   # ou
   tail -f /var/log/nginx/error.log
   ```
   
   Recherchez des erreurs contenant `local_question_diagnostic` ou `lib.php`

### Solutions de Secours

**Solution 1 : Réinstallation Complète du Plugin**

```bash
# Sauvegarde
cp -r local/question_diagnostic local/question_diagnostic.backup

# Suppression
rm -rf local/question_diagnostic

# Réinstallation depuis Git
git clone https://votre-repo.git local/question_diagnostic

# Purge
php admin/cli/purge_caches.php
```

**Solution 2 : Vérification des Permissions**

```bash
# Linux : S'assurer que les fichiers sont lisibles par le serveur web
chmod 644 local/question_diagnostic/*.php
chmod 755 local/question_diagnostic/
```

**Solution 3 : Désactivation du Cache PHP (Temporaire)**

Si vous utilisez OPcache ou un autre cache PHP, désactivez-le temporairement pour tester :

```php
// Dans config.php (temporairement)
$CFG->opcache = false;
```

Puis retestez. Si cela fonctionne, le problème vient du cache PHP.

## 📞 Support

Si après toutes ces étapes le problème persiste :

1. **Partagez les résultats** de `test_function.php` (capture d'écran)
2. **Partagez les logs PHP** contenant l'erreur
3. **Vérifiez votre version de Moodle** : Administration du site → Notifications
4. **Vérifiez votre version de PHP** : `php -v` (doit être ≥ 7.4)

## 📚 Documentation Complémentaire

- **`QUICK_FIX_README.txt`** : Résumé rapide (consultable en texte brut)
- **`FIX_UNDEFINED_FUNCTION.md`** : Guide complet avec diagnostic avancé
- **`PURGE_CACHE_INSTRUCTIONS.md`** : Instructions détaillées de purge
- **`CHANGELOG.md`** : Historique complet des versions

## 🎉 Après le Déploiement

Une fois le déploiement réussi :

1. ✅ Testez toutes les fonctionnalités du plugin
2. ✅ Supprimez quelques questions doublons pour valider
3. ✅ Surveillez les logs pour détecter d'éventuelles nouvelles erreurs
4. ✅ Documentez votre procédure de déploiement pour la prochaine fois

---

**Version** : v1.9.51  
**Date de Déploiement** : 13 Octobre 2025  
**Auteur** : Plugin Question Diagnostic Team  
**Statut** : ✅ Prêt pour Production

