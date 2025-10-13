# 🚀 Guide de Déploiement - Version 1.10.0

## 📋 Informations de Version

- **Version** : v1.10.0
- **Date** : 14 octobre 2025
- **Fonctionnalité majeure** : Gestion des fichiers orphelins
- **Compatibilité** : Moodle 4.0 - 4.5+
- **Statut** : ✅ Production Ready

---

## 📦 Contenu de la Release

### Nouveaux fichiers (8)

```
local/question_diagnostic/
├── classes/
│   └── orphan_file_detector.php          [NOUVEAU - 550 lignes]
├── orphan_files.php                       [NOUVEAU - 450 lignes]
├── actions/
│   ├── orphan_delete.php                  [NOUVEAU - 210 lignes]
│   ├── orphan_archive.php                 [NOUVEAU - 230 lignes]
│   └── orphan_export.php                  [NOUVEAU - 75 lignes]
└── docs/
    └── features/
        └── FEATURE_ORPHAN_FILES.md        [NOUVEAU - 400 lignes]
```

### Fichiers modifiés (7)

```
local/question_diagnostic/
├── db/
│   └── caches.php                         [MODIFIÉ - Ajout cache orphanfiles]
├── classes/
│   └── cache_manager.php                  [MODIFIÉ - Support CACHE_ORPHANFILES]
├── index.php                              [MODIFIÉ - Nouvelle option menu]
├── version.php                            [MODIFIÉ - Version 1.10.0]
├── lang/
│   ├── fr/local_question_diagnostic.php   [MODIFIÉ - +52 chaînes]
│   └── en/local_question_diagnostic.php   [MODIFIÉ - +52 chaînes]
└── CHANGELOG.md                           [MODIFIÉ - Entrée v1.10.0]
```

---

## ⚙️ Instructions d'Installation

### Étape 1 : Sauvegarde (OBLIGATOIRE)

```bash
# 1. Sauvegarde de la base de données
mysqldump -u [user] -p [database] > backup_moodle_$(date +%Y%m%d_%H%M%S).sql

# 2. Sauvegarde des fichiers du plugin
cd /var/www/moodle/local/
tar -czf question_diagnostic_backup_$(date +%Y%m%d_%H%M%S).tar.gz question_diagnostic/

# 3. Sauvegarde de moodledata (optionnel mais recommandé)
tar -czf moodledata_backup_$(date +%Y%m%d_%H%M%S).tar.gz /var/moodledata/
```

### Étape 2 : Mise à jour des fichiers

#### Option A : Via Git (recommandé)

```bash
cd /var/www/moodle/local/question_diagnostic/

# Vérifier la branche actuelle
git status

# Récupérer la dernière version
git fetch origin
git pull origin main

# Vérifier la version
grep "release" version.php
# Doit afficher : $plugin->release = 'v1.10.0';
```

#### Option B : Installation manuelle

```bash
# 1. Télécharger les fichiers modifiés/nouveaux
# 2. Copier dans /var/www/moodle/local/question_diagnostic/

# 3. Vérifier les permissions
cd /var/www/moodle/local/question_diagnostic/
chown -R www-data:www-data .
chmod -R 755 .
chmod 644 *.php
chmod 644 classes/*.php
chmod 644 actions/*.php
```

### Étape 3 : Purger les caches Moodle

```bash
# Via CLI (recommandé)
cd /var/www/moodle
sudo -u www-data php admin/cli/purge_caches.php

# OU via interface web
# Administration du site → Développement → Purger tous les caches
```

### Étape 4 : Mise à jour de la base de données

```bash
# Moodle détectera automatiquement la nouvelle version
# Via CLI
sudo -u www-data php admin/cli/upgrade.php

# OU via interface web
# Accéder à : https://votre-moodle.com/admin/
# Suivre le processus de mise à jour
```

### Étape 5 : Vérification post-installation

#### 5.1 Vérifier la version

```bash
# Via CLI
cd /var/www/moodle/local/question_diagnostic/
grep "version\|release" version.php

# Doit afficher :
# $plugin->version = 2025101401;
# $plugin->release = 'v1.10.0';
```

#### 5.2 Vérifier le cache

```bash
# Via CLI
sudo -u www-data php admin/cli/cfg.php --component=local_question_diagnostic
```

#### 5.3 Tester l'interface

1. Se connecter en tant qu'administrateur
2. Accéder à : `https://votre-moodle.com/local/question_diagnostic/index.php`
3. Vérifier la présence de la nouvelle option : **"🗑️ Fichiers Orphelins"**
4. Cliquer sur l'option pour accéder à la page `orphan_files.php`
5. Vérifier que le dashboard s'affiche correctement

---

## ✅ Checklist de Validation

### Tests fonctionnels

- [ ] **Menu principal** : Option "Fichiers Orphelins" visible
- [ ] **Page orphan_files.php** : Dashboard s'affiche sans erreur
- [ ] **Statistiques** : Les cartes affichent des données cohérentes
- [ ] **Filtres** : Les filtres fonctionnent en temps réel
- [ ] **Sélection multiple** : Les checkboxes fonctionnent
- [ ] **Actions individuelles** : Boutons Supprimer/Archiver présents
- [ ] **Actions groupées** : Barre d'actions apparaît quand fichiers sélectionnés
- [ ] **Bouton Rafraîchir** : Purge le cache et recharge les données
- [ ] **Export CSV** : Génère un fichier CSV valide
- [ ] **Confirmation** : Page de confirmation s'affiche avant suppression
- [ ] **Mode Dry-Run** : Simulation fonctionne sans suppression réelle

### Tests de sécurité

- [ ] **Accès admin** : Seuls les admins peuvent accéder
- [ ] **Protection CSRF** : sesskey vérifié sur toutes les actions
- [ ] **Confirmation obligatoire** : Aucune suppression sans confirmation
- [ ] **Logs** : Toutes les actions sont loggées

### Tests de performance

- [ ] **Cache** : Les résultats sont mis en cache (1 heure)
- [ ] **Temps de chargement** : < 3 secondes avec 1000 fichiers
- [ ] **Filtres JS** : Instantanés (< 100ms)
- [ ] **Pagination** : Limite à 1000 fichiers respectée

### Tests techniques

- [ ] **Pas d'erreur PHP** : Vérifier les logs PHP
- [ ] **Pas d'erreur JS** : Console navigateur vide
- [ ] **Responsive** : Interface fonctionne sur mobile/tablette
- [ ] **Traductions** : Chaînes FR et EN correctes

---

## 🔧 Configuration Post-Déploiement

### 1. Vérifier les permissions du dossier d'archives

```bash
# Créer le dossier d'archives si nécessaire
sudo mkdir -p /var/moodledata/temp/orphan_archive
sudo chown www-data:www-data /var/moodledata/temp/orphan_archive
sudo chmod 755 /var/moodledata/temp/orphan_archive
```

### 2. Configurer le cache (optionnel)

```php
// Dans config.php, pour optimiser le cache
$CFG->cachejs = true;
$CFG->cachetemplates = true;
```

### 3. Planifier un nettoyage régulier (future)

```bash
# Ajouter à crontab (pour Phase 2)
# 0 2 * * 0 php /var/www/moodle/local/question_diagnostic/cli/cleanup_orphan_archives.php
```

---

## 📊 Monitoring Post-Déploiement

### Métriques à surveiller (Semaine 1)

| Métrique | Comment vérifier | Valeur attendue |
|----------|------------------|-----------------|
| Erreurs PHP | `tail -f /var/log/php-errors.log` | 0 erreurs |
| Temps de réponse | Chrome DevTools Network | < 3 secondes |
| Utilisation CPU | `top` pendant analyse | < 30% |
| Utilisation RAM | `free -h` | Pas d'augmentation |
| Logs erreurs Moodle | Admin → Rapports → Journaux | Aucune erreur liée au plugin |

### Indicateurs de succès

- ✅ Aucune erreur 500 sur `orphan_files.php`
- ✅ Les admins peuvent accéder et utiliser la fonctionnalité
- ✅ Les fichiers orphelins sont correctement détectés
- ✅ Les suppressions sont loggées
- ✅ Les archives sont créées correctement

---

## 🐛 Dépannage

### Problème 1 : "Page blanche" sur orphan_files.php

**Solution** :
```bash
# 1. Vérifier les erreurs PHP
tail -f /var/log/php-errors.log

# 2. Activer le debug Moodle
# Dans config.php :
$CFG->debug = (E_ALL | E_STRICT);
$CFG->debugdisplay = 1;

# 3. Purger les caches
sudo -u www-data php admin/cli/purge_caches.php
```

### Problème 2 : "Class not found: orphan_file_detector"

**Solution** :
```bash
# Vérifier que le fichier existe
ls -la /var/www/moodle/local/question_diagnostic/classes/orphan_file_detector.php

# Purger le cache des classes
sudo -u www-data php admin/cli/purge_caches.php

# Vérifier les permissions
chmod 644 /var/www/moodle/local/question_diagnostic/classes/orphan_file_detector.php
```

### Problème 3 : "Cache definition not found: orphanfiles"

**Solution** :
```bash
# Vérifier db/caches.php
grep "orphanfiles" /var/www/moodle/local/question_diagnostic/db/caches.php

# Si absent, réinstaller le plugin ou purger les caches
sudo -u www-data php admin/cli/purge_caches.php
sudo -u www-data php admin/cli/upgrade.php --non-interactive
```

### Problème 4 : Erreur de permissions sur archives

**Solution** :
```bash
# Créer et configurer le dossier
sudo mkdir -p /var/moodledata/temp/orphan_archive
sudo chown -R www-data:www-data /var/moodledata/temp/
sudo chmod -R 755 /var/moodledata/temp/
```

### Problème 5 : Performance lente

**Solution** :
```bash
# 1. Vérifier le cache
# Admin → Monitoring → Cache

# 2. Réduire la limite d'analyse
# Modifier dans orphan_file_detector.php ligne 45 :
# $limit = 1000; → $limit = 500;

# 3. Optimiser MySQL
# Vérifier les index sur mdl_files
mysql> SHOW INDEX FROM mdl_files;
```

---

## 🔄 Rollback (Si Nécessaire)

### Plan de rollback

Si des problèmes critiques surviennent :

```bash
# 1. Restaurer les fichiers
cd /var/www/moodle/local/
rm -rf question_diagnostic/
tar -xzf question_diagnostic_backup_[DATE].tar.gz

# 2. Restaurer la base de données (si nécessaire)
mysql -u [user] -p [database] < backup_moodle_[DATE].sql

# 3. Purger les caches
sudo -u www-data php admin/cli/purge_caches.php

# 4. Vérifier la version
grep "release" question_diagnostic/version.php
```

### Conditions de rollback

Effectuer un rollback si :
- ❌ Erreurs PHP critiques empêchant l'accès au site
- ❌ Perte de données détectée
- ❌ Performance dégradée (> 10 secondes chargement)
- ❌ Erreurs de suppression de fichiers valides

---

## 📞 Support et Escalade

### Niveau 1 : Auto-diagnostic

1. Consulter ce document
2. Vérifier les logs PHP et Moodle
3. Tester sur environnement de développement

### Niveau 2 : Documentation

1. Lire `docs/features/FEATURE_ORPHAN_FILES.md`
2. Consulter `CHANGELOG.md` pour les changements
3. Vérifier les issues GitHub

### Niveau 3 : Contact support

Si le problème persiste :
- Ouvrir une issue sur GitHub avec :
  - Version Moodle
  - Version PHP
  - Logs d'erreur complets
  - Étapes de reproduction

---

## 📚 Documentation de Référence

- **Guide complet** : `docs/features/FEATURE_ORPHAN_FILES.md`
- **Changelog** : `CHANGELOG.md` (section v1.10.0)
- **API Reference** : `classes/orphan_file_detector.php` (commentaires PHPDoc)
- **Guide utilisateur** : À créer pour les administrateurs

---

## 🎯 Prochaines Étapes (Post-Déploiement)

### Semaine 1
- [ ] Monitorer les logs quotidiennement
- [ ] Recueillir les retours des administrateurs
- [ ] Corriger les bugs mineurs si nécessaires

### Semaine 2-4
- [ ] Analyser les statistiques d'utilisation
- [ ] Identifier les améliorations UX
- [ ] Planifier Phase 2 (fichiers physiques orphelins)

### Mois 1-3
- [ ] Implémenter Phase 2 si demandée
- [ ] Ajouter nettoyage automatique (Scheduled task)
- [ ] Créer guide utilisateur complet

---

## ✅ Validation Finale

**Déployé par** : ___________________  
**Date de déploiement** : ___________________  
**Environnement** : ☐ Dev  ☐ Staging  ☐ Production  
**Validation réussie** : ☐ Oui  ☐ Non  

**Signature** : ___________________

---

## 📋 Annexes

### A. Commandes utiles

```bash
# Vérifier la version du plugin
grep "version\|release" local/question_diagnostic/version.php

# Lister les caches actifs
sudo -u www-data php admin/cli/cfg.php --component=core | grep cache

# Purger un cache spécifique
sudo -u www-data php -r "require('config.php'); \$cache = cache::make('local_question_diagnostic', 'orphanfiles'); \$cache->purge();"

# Voir les logs en temps réel
tail -f /var/log/apache2/error.log
tail -f /var/www/moodledata/error.log

# Tester l'accès à la page
curl -I https://votre-moodle.com/local/question_diagnostic/orphan_files.php
```

### B. Checklist administrateurs

Informer les administrateurs :
- [ ] Nouvelle fonctionnalité disponible : "Fichiers Orphelins"
- [ ] Toujours sauvegarder avant suppression massive
- [ ] Utiliser le mode Dry-Run pour tester
- [ ] Consulter les archives avant suppression définitive
- [ ] Vérifier les logs après chaque opération

---

**Version du document** : 1.0  
**Dernière mise à jour** : 14 octobre 2025  
**Statut** : ✅ Validé pour déploiement

