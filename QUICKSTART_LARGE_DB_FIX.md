# ⚡ Guide de déploiement rapide - Fix grandes bases (v1.2.2)

## 🎯 Ce que cette mise à jour corrige

✅ Page de statistiques qui ne charge jamais avec 29 512 questions  
✅ Timeout du serveur  
✅ Ralentissement général de Moodle  

## 📦 Installation (5 minutes)

### Étape 1 : Sauvegarder

```bash
# Sauvegarder le plugin actuel
cp -r /var/www/moodle/local/question_diagnostic /tmp/question_diagnostic_backup

# Sauvegarder la base de données (optionnel mais recommandé)
mysqldump -u moodle_user -p moodle_db > /tmp/moodle_backup.sql
```

### Étape 2 : Copier les fichiers modifiés

Remplacez ces 3 fichiers :

1. **`questions_cleanup.php`** (ligne 297-325 modifiées)
2. **`classes/question_analyzer.php`** (lignes 31-429 modifiées)
3. **`version.php`** (version 1.2.2)

```bash
# Si vous avez les fichiers sur votre machine
cd /chemin/vers/votre/moodle
cp /chemin/vers/nouveaux/fichiers/questions_cleanup.php local/question_diagnostic/
cp /chemin/vers/nouveaux/fichiers/question_analyzer.php local/question_diagnostic/classes/
cp /chemin/vers/nouveaux/fichiers/version.php local/question_diagnostic/
```

### Étape 3 : Purger les caches

**Option A : Via interface web** (recommandé)
1. Connectez-vous en admin
2. Administration du site → Développement → Purger tous les caches
3. Cliquez sur "Purger tous les caches"

**Option B : Via CLI**
```bash
cd /var/www/moodle
php admin/cli/purge_caches.php
```

### Étape 4 : Tester

1. Accédez à `/local/question_diagnostic/questions_cleanup.php`
2. La page devrait se charger en **moins de 10 secondes**
3. Vous devriez voir :
   - ✅ Statistiques globales (29 512 questions)
   - ✅ Message d'avertissement : "seules les 1 000 premières questions sont affichées"
   - ✅ Tableau avec max 1000 questions

## ✨ Résultat attendu

### Avant (v1.2.1)
- ⏱️ Chargement : Timeout (jamais fini)
- 💥 Serveur ralenti
- ❌ Page inutilisable

### Après (v1.2.2)
- ⚡ Chargement : ~5 secondes
- ✅ Serveur réactif
- ✅ Page fonctionnelle

## 📊 Ce qui change

### ✅ Ce qui fonctionne toujours
- Dashboard avec statistiques de TOUTES les 29 512 questions
- Détection de doublons par nom exact (toute la base)
- Export CSV
- Filtres et recherche
- Bouton de purge de cache

### ℹ️ Ce qui est limité
- Tableau détaillé : affiche les **1000 questions les plus récentes** (au lieu de toutes)
- Détection avancée de doublons (par similarité) : limitée aux 1000 affichées

### 💡 Pourquoi cette limitation ?
Pour garantir un temps de chargement raisonnable sur toutes les installations, même avec des dizaines de milliers de questions.

## ⚙️ Configuration optionnelle

### Ajuster la limite

Si vous voulez afficher plus ou moins de questions :

Éditez `questions_cleanup.php` ligne 297 :

```php
$max_questions_display = 1000; // Changez cette valeur
```

**Recommandations :**
- **500** : Serveurs moins puissants
- **1000** : ✅ Valeur par défaut (recommandé)
- **2000** : Serveurs puissants (16+ GB RAM)
- **5000+** : ⚠️ Risque de timeout

### Augmenter les limites PHP

Si vous montez à 2000+, ajustez votre `php.ini` :

```ini
max_execution_time = 300
memory_limit = 512M
```

Puis redémarrez PHP-FPM/Apache :

```bash
sudo systemctl restart php-fpm
# ou
sudo systemctl restart apache2
```

## 🔍 Dépannage

### La page est toujours lente

1. **Vérifiez que les caches sont purgés**
   ```bash
   php admin/cli/purge_caches.php
   ```

2. **Réduisez la limite à 500**
   ```php
   $max_questions_display = 500;
   ```

3. **Vérifiez les logs PHP**
   ```bash
   tail -f /var/log/php-fpm/error.log
   tail -f /var/log/apache2/error.log
   ```

### Message d'erreur

Activez le mode debug Moodle :
- Administration du site → Développement → Mode de débogage
- Sélectionnez "DEVELOPER"
- Cochez "Afficher les messages"

### Les statistiques sont incorrectes

Cliquez sur le bouton **"🔄 Purger le cache"** en haut de la page pour forcer un recalcul.

## 📝 Fichiers modifiés dans cette version

| Fichier | Modifications | Impact |
|---------|--------------|--------|
| `questions_cleanup.php` | Ajout limite 1000 + messages | Performance |
| `classes/question_analyzer.php` | 2 nouvelles fonctions optimisées | Performance |
| `version.php` | Version 1.2.2 | Metadata |

## ✅ Checklist de déploiement

Avant de déployer en production :

- [ ] Sauvegarder le plugin actuel
- [ ] Sauvegarder la base de données
- [ ] Copier les 3 fichiers modifiés
- [ ] Purger les caches Moodle
- [ ] Tester en tant qu'admin
- [ ] Vérifier le temps de chargement (<10s)
- [ ] Vérifier le message d'avertissement
- [ ] Tester les filtres
- [ ] Vérifier les logs d'erreurs

## 📖 Documentation complète

Pour plus de détails :
- **`LARGE_DATABASE_FIX.md`** : Documentation technique complète
- **`CHANGELOG.md`** : Historique des versions
- **`PERFORMANCE_OPTIMIZATION.md`** : Guide d'optimisation

## 🎉 Conclusion

Cette mise à jour résout **définitivement** le problème de timeout sur les grandes bases de données. La page est maintenant **utilisable et rapide** même avec 29 512 questions.

**Temps d'installation** : 5 minutes  
**Gain de performance** : Page utilisable (au lieu de timeout)  
**Compatibilité** : Moodle 4.3+

---

**Version** : 1.2.2  
**Date** : 7 octobre 2025  
**Status** : ✅ Production ready

