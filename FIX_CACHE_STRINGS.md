# 🔧 Correction : Chaînes de traduction non chargées

## ❌ Problème

Les chaînes de traduction s'affichent brutes au lieu du texte :
```
[[olution_duplicates_title]]
[[olution_not_found]]
```

## ✅ Cause

Les **caches Moodle ne sont pas purgés** après l'ajout des nouvelles chaînes de traduction.

## 🔧 Solution RAPIDE (30 secondes)

### Option 1 : Via l'interface Web (RECOMMANDÉ)

1. **Allez sur votre site Moodle**

2. **Accédez à** :
   ```
   Administration du site → Développement → Purger tous les caches
   ```
   
   **OU** directement via URL :
   ```
   https://votre-site-moodle.com/admin/purgecaches.php
   ```

3. **Cliquez sur** : "Purger tous les caches"

4. **Attendez 5-10 secondes**

5. **✅ FAIT !** Rechargez la page `olution_duplicates.php`

### Option 2 : Via le script intégré au plugin

**URL directe** :
```
http://votre-site-moodle/local/question_diagnostic/purge_cache.php
```

1. Accédez à l'URL ci-dessus (connecté comme admin)
2. Cliquez sur "Purger tous les caches"
3. Rechargez la page

### Option 3 : Via CLI (si accès serveur)

```bash
cd /path/to/moodle
php admin/cli/purge_caches.php
```

## 📋 Vérification

Après purge, vous devriez voir :

**AVANT** (incorrect) :
```
[[olution_duplicates_title]]
[[olution_not_found]]
```

**APRÈS** (correct) :
```
Déplacement automatique vers Olution
La catégorie "Olution" n'a pas été trouvée
```

## 🔍 Si le problème persiste

### Vérifier que les fichiers de langue existent

```bash
ls -la lang/fr/local_question_diagnostic.php
ls -la lang/en/local_question_diagnostic.php
```

Tous deux doivent exister et contenir les nouvelles chaînes (lignes 476-507).

### Vérifier les permissions

```bash
chmod 644 lang/fr/local_question_diagnostic.php
chmod 644 lang/en/local_question_diagnostic.php
```

### Forcer la mise à jour du plugin

1. **Administration du site → Notifications**
2. Cliquer sur "Mettre à jour la base de données"
3. Purger les caches à nouveau

### Vérifier la version du plugin

Dans `version.php`, la version doit être :
```php
$plugin->version = 2025101404;  // v1.10.4
$plugin->release = 'v1.10.4';
```

## ⚡ Quick Fix : URL directe de purge

**Copiez-collez cette URL dans votre navigateur** (en remplaçant par votre domaine) :

```
https://VOTRE-SITE-MOODLE.com/admin/purgecaches.php?confirm=1&sesskey=VOTRE-SESSKEY
```

Pour obtenir votre `sesskey` :
1. Connectez-vous à Moodle
2. Inspectez n'importe quel formulaire
3. Cherchez `<input name="sesskey" value="...">` 
4. Copiez la valeur

## 🎯 Résultat attendu

Après la purge des caches, la page devrait s'afficher correctement avec :

✅ Titre : "Déplacement automatique vers Olution"
✅ Message : "La catégorie 'Olution' n'a pas été trouvée" (si Olution n'existe pas)
✅ Aide : "Pour utiliser cette fonctionnalité, vous devez d'abord créer..."

---

**Temps estimé** : ⏱️ 30 secondes

**Difficulté** : ⭐ Facile

