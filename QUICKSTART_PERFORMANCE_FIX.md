# Guide rapide - Résolution du problème de performance

## ⚡ Solution immédiate

### Étape 1 : Installer les fichiers
Les fichiers suivants ont été modifiés/créés :
- ✅ `classes/question_analyzer.php` - Optimisations principales
- ✅ `questions_cleanup.php` - Gestion d'erreurs améliorée
- ✅ `db/caches.php` - **NOUVEAU** - Définitions de cache

### Étape 2 : Purger les caches Moodle
```bash
# Via CLI (recommandé)
cd /chemin/vers/moodle
php admin/cli/purge_caches.php
```

OU via l'interface web :
1. Administration du site
2. Développement
3. Purger tous les caches

### Étape 3 : Tester
1. Accédez à la page : `/local/question_diagnostic/questions_cleanup.php`
2. La page devrait se charger beaucoup plus rapidement
3. Si vous avez >5000 questions, un message vous informera que la détection est en mode rapide

## 🔧 Si ça ne fonctionne toujours pas

### Configuration PHP recommandée

Ajoutez dans votre `php.ini` ou `.htaccess` :

```ini
max_execution_time = 300
memory_limit = 512M
```

### Purger le cache du plugin

Sur la page `questions_cleanup.php`, cliquez sur :
```
🔄 Purger le cache
```

## 📊 Vérifier le nombre de questions

```sql
-- Exécuter dans phpMyAdmin ou CLI MySQL
SELECT COUNT(*) FROM mdl_question;
```

- **< 1000 questions** : Tout devrait être rapide
- **1000-5000 questions** : Détection complète, peut prendre 10-30s au premier chargement
- **> 5000 questions** : Mode rapide automatique (nom exact uniquement)

## ✅ Checklist de vérification

- [ ] Fichier `db/caches.php` existe et contient les 3 définitions de cache
- [ ] Cache Moodle purgé
- [ ] Pas d'erreur PHP visible (vérifier les logs)
- [ ] Page se charge en moins de 30 secondes
- [ ] Bouton "Purger le cache" visible en haut de la page

## 🆘 Besoin d'aide ?

Si le problème persiste :

1. **Vérifier les logs d'erreurs PHP**
   ```bash
   tail -f /var/log/apache2/error.log
   # ou
   tail -f /var/log/php-fpm/error.log
   ```

2. **Activer le mode debug Moodle**
   - Administration du site > Développement > Mode de débogage
   - Choisir "DEVELOPER: messages d'erreur supplémentaires pour les développeurs"

3. **Vérifier la base de données**
   - Les requêtes SQL longues peuvent indiquer un problème d'index
   - Vérifier les tables `mdl_question`, `mdl_quiz_slots`, `mdl_question_attempts`

## 💡 Astuce

Pour les très grandes bases (>10000 questions), envisagez de :
- Désactiver temporairement la détection de doublons
- Augmenter les timeouts PHP/MySQL
- Utiliser une approche par lots (traiter les questions par paquets)

---

**Tout devrait fonctionner maintenant !** 🎉

Si vous rencontrez encore des problèmes, consultez le fichier `PERFORMANCE_OPTIMIZATION.md` pour plus de détails.

