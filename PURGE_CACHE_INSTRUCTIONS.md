# 🔧 Résolution du problème "Call to undefined function"

## Problème Identifié

L'erreur `Call to undefined function local_question_diagnostic_get_parent_url()` survient parce que :

1. **Le cache Moodle** n'a pas été purgé après les modifications de `lib.php`
2. **Les fichiers modifiés** ne sont peut-être pas encore sur le serveur web

## ✅ Solution en 3 étapes

### Étape 1 : Vérifier que les fichiers sont à jour

**Option A : Si vous développez en local (même machine que le serveur)**

Les fichiers de ce répertoire sont-ils dans votre installation Moodle ?
- **Chemin de développement** : `C:\Users\olivi\OneDrive\Bureau\moodle_dev-questions\`
- **Chemin Moodle attendu** : `C:\xampp\htdocs\moodle\local\question_diagnostic\` (ou similaire)

Si ces chemins sont différents, vous devez **copier les fichiers modifiés** :

```powershell
# Remplacer CHEMIN_MOODLE par le vrai chemin de votre installation
Copy-Item -Path "C:\Users\olivi\OneDrive\Bureau\moodle_dev-questions\*" -Destination "CHEMIN_MOODLE\local\question_diagnostic\" -Recurse -Force
```

**Option B : Si vous développez sur un serveur distant**

Vous devez **uploader les fichiers modifiés** via FTP/SFTP vers :
- `votre-serveur/local/question_diagnostic/`

### Étape 2 : Purger les caches Moodle

**Méthode 1 : Interface Web (RECOMMANDÉE)**

1. Connectez-vous à Moodle en tant qu'administrateur
2. Allez dans : **Administration du site → Développement → Purger les caches**
3. Cliquez sur **"Purger les caches"**
4. Attendez le message de confirmation

**Méthode 2 : URL directe**

Accédez à cette URL (remplacez par votre domaine) :
```
http://votresite.moodle/admin/purgecaches.php?confirm=1&sesskey=VOTRE_SESSKEY
```

**Méthode 3 : Ligne de commande (si accès SSH)**

```bash
php admin/cli/purge_caches.php
```

### Étape 3 : Tester la fonction

1. **Accédez au fichier de test** que nous venons de créer :
   ```
   http://votresite.moodle/local/question_diagnostic/test_function.php
   ```

2. **Vérifiez les résultats** :
   - ✅ Tous les tests doivent être verts
   - Si un test est rouge, suivez les instructions affichées

3. **Essayez de supprimer une question** :
   - Allez dans **Gestion Questions** → **Questions**
   - Essayez de supprimer une question doublon inutilisée
   - L'erreur devrait avoir disparu

## 🚨 Si le problème persiste

### Solution de secours : Vérification manuelle

1. **Ouvrez votre fichier** : `local/question_diagnostic/lib.php` (sur le serveur web)
2. **Cherchez la ligne 665** (environ) : `function local_question_diagnostic_get_parent_url($current_page) {`
3. **Si cette fonction n'existe pas** : Le fichier `lib.php` n'est pas à jour sur votre serveur
   - → Copiez le nouveau `lib.php` depuis votre dépôt local
   - → Purgez à nouveau les caches

### Solution de secours 2 : Réinstallation du plugin

```bash
# 1. Sauvegarde (si vous avez des modifications locales)
cp -r local/question_diagnostic local/question_diagnostic.backup

# 2. Téléchargez la dernière version depuis Git
cd local/question_diagnostic
git pull origin master

# 3. Purgez les caches
php admin/cli/purge_caches.php
```

## 📋 Checklist de vérification

- [ ] Les fichiers modifiés sont sur le serveur web
- [ ] Le fichier `lib.php` contient la fonction `local_question_diagnostic_get_parent_url()` (ligne ~665)
- [ ] Les caches Moodle ont été purgés
- [ ] Le cache du navigateur a été vidé (Ctrl+Shift+Delete)
- [ ] Le test `test_function.php` affiche tous les tests en vert
- [ ] La suppression de question fonctionne sans erreur

## 🆘 Besoin d'aide ?

Si aucune des solutions ci-dessus ne fonctionne :

1. **Partagez la sortie** du fichier `test_function.php`
2. **Vérifiez les logs PHP** de votre serveur web
3. **Vérifiez les logs Moodle** : Administration du site → Rapports → Logs

---

**Version** : v1.9.50  
**Date** : Octobre 2025

