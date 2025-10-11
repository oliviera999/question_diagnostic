# 🚀 Guide de Déploiement - Plugin Question Diagnostic v1.9.33

**Version** : v1.9.33  
**Date de release** : 11 Octobre 2025  
**Statut** : ✅ PRODUCTION-READY POUR GROS SITES

---

## ✅ Checklist Pré-Déploiement

### Prérequis Système

- ✅ **Moodle** : Version 4.0+ (4.5+ recommandé)
- ✅ **PHP** : 7.4+ (8.0+ recommandé)
- ✅ **Base de données** : MySQL 8.0+, MariaDB 10.6+, ou PostgreSQL 13+
- ✅ **Espace disque** : Au moins 10 MB pour le plugin
- ✅ **Mémoire PHP** : 256 MB minimum (512 MB recommandé pour gros sites)

### Vérifications

```bash
# Vérifier version PHP
php -v

# Vérifier version Moodle (depuis admin/environment.php)
# Ou dans config.php : echo $CFG->version;

# Vérifier espace disque
df -h

# Vérifier droits d'écriture
ls -la moodle/local/
```

---

## 📥 Installation

### Option 1 : Depuis GitHub (Recommandé)

```bash
# 1. Aller dans le dossier local/ de Moodle
cd /path/to/moodle/local/

# 2. Cloner le dépôt
git clone https://github.com/oliviera999/question_diagnostic.git question_diagnostic

# 3. Vérifier la version
cd question_diagnostic
git checkout master
git log --oneline -5

# 4. Vérifier que c'est bien v1.9.33
cat version.php | grep "release"
# Doit afficher : $plugin->release = 'v1.9.33';
```

### Option 2 : Upload Manuel

```bash
# 1. Télécharger le ZIP depuis GitHub
# https://github.com/oliviera999/question_diagnostic/archive/refs/heads/master.zip

# 2. Extraire dans moodle/local/
unzip master.zip
mv question_diagnostic-master moodle/local/question_diagnostic

# 3. Vérifier les permissions
chmod -R 755 moodle/local/question_diagnostic
```

---

## ⚙️ Configuration Moodle

### 1. Mise à jour de la base de données

```bash
# Via l'interface web
# Aller sur : https://votre-moodle.com/admin/index.php
# Moodle détectera automatiquement le nouveau plugin
# Cliquer sur "Mise à jour de la base de données"
```

**Ou via CLI** :
```bash
php admin/cli/upgrade.php
```

### 2. Purger les caches

**IMPORTANT** : Toujours purger après installation !

```bash
# Via CLI (recommandé)
php admin/cli/purge_caches.php

# Ou via interface web
# Administration → Développement → Purger tous les caches
```

### 3. Vérifier l'installation

```bash
# Via interface web
# Aller sur : https://votre-moodle.com/local/question_diagnostic/

# Vous devriez voir le dashboard avec :
# - Statistiques globales
# - Lien vers gestion catégories
# - Lien vers vérification liens
# - Version affichée : v1.9.33
```

---

## 🧪 Tests Post-Déploiement

### Test 1 : Dashboard

```
1. Ouvrir https://votre-moodle.com/local/question_diagnostic/
2. ✅ Vérifier que les stats s'affichent
3. ✅ Vérifier version affichée : v1.9.33
4. ✅ Cliquer sur chaque lien (catégories, questions, liens)
```

### Test 2 : Pagination Serveur (Nouveau v1.9.30)

```
1. Aller sur Questions → Charger les statistiques
2. ✅ Vérifier "Page 1 sur X" affiché
3. ✅ Vérifier boutons Précédent/Suivant présents
4. ✅ Cliquer sur "Page 2"
5. ✅ Vérifier chargement rapide (même sur 20k+ questions)
```

### Test 3 : Protections Catégories (Nouveau v1.9.29)

```
1. Aller sur Catégories
2. ✅ Vérifier badge "PROTÉGÉE" sur catégories racine
3. ✅ Tenter de supprimer une catégorie protégée
4. ✅ Vérifier message d'erreur clair
```

### Test 4 : Transactions SQL (Nouveau v1.9.30)

```
1. Créer 2 catégories de test (A et B)
2. Fusionner A → B
3. ✅ Vérifier que A est supprimée
4. ✅ Vérifier que les questions de A sont dans B
5. ✅ Vérifier aucune perte de données
```

### Test 5 : Tests Unitaires (Nouveau v1.9.30)

```bash
# Depuis la racine de Moodle
vendor/bin/phpunit --testdox local/question_diagnostic/tests/

# ✅ Doit afficher : OK (21 tests, X assertions)
```

---

## 🔧 Configuration Recommandée

### Pour Petit Site (<5000 questions)

```php
// Aucune configuration nécessaire
// Tout fonctionne out-of-the-box !
```

### Pour Gros Site (>20000 questions)

**1. Ajuster la pagination** (si besoin) :
```
URL : /local/question_diagnostic/questions_cleanup.php?per_page=200

Recommandation : 100-200 questions par page
```

**2. Activer les logs de debug** (temporairement) :
```php
// Dans config.php
$CFG->debug = (E_ALL | E_STRICT);
$CFG->debugdisplay = 1;

// Vérifier dans les logs les messages "v1.9.30" pour validation
```

**3. Augmenter limites PHP** (si nécessaire) :
```php
// Dans php.ini ou config.php
memory_limit = 512M
max_execution_time = 300
```

### Pour Site Mission-Critique

**1. Tester sur environnement de staging d'abord**

**2. Créer un backup de la BDD** :
```bash
mysqldump -u root -p moodle > backup_before_question_diagnostic_v1.9.33.sql
```

**3. Monitorer les performances** :
```bash
# Activer slow query log MySQL
set global slow_query_log = 'ON';
set global long_query_time = 2;

# Vérifier après déploiement
tail -f /var/log/mysql/mysql-slow.log
```

**4. Exécuter les tests** :
```bash
vendor/bin/phpunit --testdox local/question_diagnostic/tests/
```

---

## 🔄 Mise à Jour depuis Version Antérieure

### Depuis v1.9.26-29

```bash
# 1. Backup BDD
mysqldump -u root -p moodle > backup_before_upgrade.sql

# 2. Pull les changements
cd /path/to/moodle/local/question_diagnostic
git pull origin master

# 3. Vérifier la version
cat version.php | grep release
# Doit afficher : $plugin->release = 'v1.9.33';

# 4. Mise à jour Moodle
php admin/cli/upgrade.php

# 5. Purger TOUS les caches
php admin/cli/purge_caches.php

# 6. Tester
vendor/bin/phpunit --testdox local/question_diagnostic/tests/
```

### Depuis v1.0.x - v1.8.x

**Important** : Beaucoup de changements entre ces versions.

```bash
# 1. BACKUP COMPLET
mysqldump -u root -p moodle > backup_full.sql
tar -czf moodle_backup.tar.gz /path/to/moodle/

# 2. Lire le CHANGELOG
# docs/CHANGELOG.md (ou CHANGELOG.md à la racine)

# 3. Vérifier breaking changes
# Lire sections v1.9.30 (pagination), v1.9.27 (optimisations)

# 4. Upgrade
cd /path/to/moodle/local/question_diagnostic
git pull origin master
php admin/cli/upgrade.php
php admin/cli/purge_caches.php

# 5. Tests complets
vendor/bin/phpunit local/question_diagnostic/tests/
```

---

## 📊 Vérification Post-Déploiement

### Checklist Fonctionnelle

- [ ] Dashboard s'affiche correctement
- [ ] Statistiques catégories correctes
- [ ] Pagination fonctionne (page 1, 2, 3...)
- [ ] Filtres JavaScript fonctionnent
- [ ] Actions de suppression demandent confirmation
- [ ] Catégories protégées non supprimables
- [ ] Export CSV fonctionne
- [ ] Fusion de catégories fonctionne
- [ ] Vérification liens cassés fonctionne
- [ ] Tous les tests PHPUnit passent

### Checklist Performance

**Sur gros site (>20k questions)** :

- [ ] Chargement dashboard < 3 secondes
- [ ] Navigation pagination < 2 secondes par page
- [ ] Filtres JavaScript réactifs (< 500ms)
- [ ] Export CSV < 30 secondes (5000 lignes)
- [ ] Suppression en masse < 10 secondes (100 items)

### Checklist Sécurité

- [ ] Seuls les admins site peuvent accéder
- [ ] Sesskey vérifié sur toutes les actions
- [ ] Pages de confirmation affichées
- [ ] Messages d'erreur ne révèlent pas d'info sensible
- [ ] Logs ne contiennent pas de données sensibles

---

## 🐛 Dépannage

### Erreur "Plugin incompatible"

**Cause** : Version Moodle < 4.0

**Solution** :
```bash
# Vérifier version Moodle
php admin/cli/version.php

# Si < 4.0, mettre à jour Moodle d'abord
# Ou modifier version.php : $plugin->requires
```

### Erreur "Table question_bank_entries not found"

**Cause** : Base de données Moodle < 4.0

**Solution** : Moodle 4.0+ requis pour ce plugin

### Page blanche / Erreur 500

**Solution** :
```bash
# 1. Activer debug
# config.php : $CFG->debug = (E_ALL | E_STRICT);

# 2. Vérifier logs
tail -f /var/log/apache2/error.log

# 3. Purger caches
php admin/cli/purge_caches.php

# 4. Vérifier permissions
chmod -R 755 local/question_diagnostic
```

### Tests PHPUnit échouent

**Solution** :
```bash
# 1. Réinitialiser BDD de test
php admin/tool/phpunit/cli/init.php

# 2. Relancer tests
vendor/bin/phpunit --testdox local/question_diagnostic/tests/

# 3. Si échec persistant, vérifier config PHPUnit
cat config.php | grep phpunit
```

### Performance dégradée

**Solution** :
```bash
# 1. Purger TOUS les caches
php admin/cli/purge_caches.php

# 2. Ajuster pagination (si >20k questions)
# URL : ?per_page=100 (au lieu de 200/500)

# 3. Augmenter mémoire PHP
# php.ini : memory_limit = 512M

# 4. Vérifier index BDD
# Les tables question, question_bank_entries doivent avoir des index
```

---

## 📞 Support

### Documentation

- **README** : [README.md](../README.md)
- **CHANGELOG** : [CHANGELOG.md](../CHANGELOG.md)
- **Documentation complète** : [docs/README.md](../docs/README.md)

### Problèmes Connus

- **Aucun bug critique** dans v1.9.33 ✅
- **Compatibilité** : Moodle 4.0+ uniquement
- **Performance** : Optimale sur gros sites (pagination serveur)

### Rapporter un Bug

1. Vérifier [docs/bugfixes/](../docs/bugfixes/) si déjà corrigé
2. Consulter [CHANGELOG.md](../CHANGELOG.md)
3. Créer une issue sur GitHub avec :
   - Version Moodle
   - Version plugin
   - Logs d'erreur
   - Étapes pour reproduire

---

## ✅ Déploiement Réussi !

Si tous les tests passent :

```
╔═══════════════════════════════════════════════════════════╗
║                                                           ║
║   ✅ DÉPLOIEMENT v1.9.33 RÉUSSI !                         ║
║                                                           ║
║   Le plugin Question Diagnostic est maintenant actif     ║
║   sur votre site Moodle et prêt à être utilisé.          ║
║                                                           ║
║   🎉 Profitez des nouvelles fonctionnalités ! 🎉          ║
║                                                           ║
╚═══════════════════════════════════════════════════════════╝
```

**Prochaines étapes** :
1. Former les administrateurs au nouvel outil
2. Consulter les guides : [docs/guides/](../docs/guides/)
3. Explorer les fonctionnalités progressivement

---

**Version du guide** : v1.0  
**Dernière mise à jour** : 11 Octobre 2025  
**Auteur** : Équipe local_question_diagnostic  

