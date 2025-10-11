# 🔍 Diagnostic : Aucune Question Affichée

## Problème Reporté

**Symptôme** : La page "Outil d'Analyse - Statistiques Complètes des Questions (v1.9.12)" n'affiche aucune question dans la liste.

---

## ✅ Solution Rapide (95% des cas)

### **ÉTAPE 1 : Purger le Cache Moodle** ⚠️ CRITIQUE

```
Administration du site → Développement → Purger tous les caches
```

**OU via CLI** :
```bash
php admin/cli/purge_caches.php
```

### **ÉTAPE 2 : Cliquer sur "Charger les Statistiques"**

Sur la page "Analyser les questions", vous devriez voir :

```
📊 Statistiques des Questions

Votre base contient X questions.

[Bouton: 📊 Charger les Statistiques Complètes]
```

**👉 Cliquez sur ce bouton** pour charger les questions.

---

## 🔍 Diagnostic Avancé

Si après avoir purgé le cache et cliqué sur "Charger les statistiques", vous ne voyez toujours aucune question :

### **1. Activer le Mode Debug** 

Éditer `config.php` :

```php
$CFG->debug = (E_ALL | E_STRICT);
$CFG->debugdisplay = 1;
```

### **2. Purger le Cache à Nouveau**

```bash
php admin/cli/purge_caches.php
```

### **3. Recharger la Page**

Accéder à :
```
Administration → Plugins locaux → Question Diagnostic → Analyser les questions
```

Cliquer sur "📊 Charger les Statistiques Complètes"

### **4. Vérifier les Messages de Debug**

Chercher dans les logs ou sur la page le message :

```
Questions chargées : X sur Y demandées (Total BDD : Z)
```

**Interprétation** :

| Message | Signification | Solution |
|---------|---------------|----------|
| `Questions chargées : 0 sur 10 demandées (Total BDD : 0)` | ❌ Aucune question dans la BDD | Normal si BDD vide |
| `Questions chargées : 0 sur 10 demandées (Total BDD : 1000)` | ❌ Erreur de chargement | Voir diagnostic ci-dessous |
| `Questions chargées : 10 sur 10 demandées (Total BDD : 1000)` | ✅ Questions chargées | Vérifier les filtres |

---

## 🐛 Causes Possibles et Solutions

### **Cas 1 : "Questions chargées : 0" mais "Total BDD" > 0**

**Cause** : Erreur lors de la récupération des questions

**Solutions** :

1. **Vérifier les logs Moodle** :
   ```
   Administration → Rapports → Logs
   ```
   Chercher les erreurs PHP ou SQL

2. **Augmenter les limites PHP** :
   
   Dans `php.ini` :
   ```ini
   max_execution_time = 300
   memory_limit = 512M
   ```

3. **Vérifier les permissions BDD** :
   
   L'utilisateur Moodle a-t-il accès aux tables `question`, `question_categories`, `quiz_slots` ?

### **Cas 2 : Message "⚠️ Aucune question trouvée" dans le tableau**

**v1.9.12** affiche maintenant ce message explicite.

**Causes possibles** :

1. **BDD vide** : Aucune question dans votre Moodle
2. **Filtres trop restrictifs** : Les filtres JavaScript cachent toutes les questions
3. **Erreur silencieuse** : Une exception a été catchée

**Solution** :

1. Vérifier le nombre total de questions :
   ```sql
   SELECT COUNT(*) FROM mdl_question;
   ```

2. Désactiver tous les filtres (recharger la page)

3. Consulter les logs PHP pour voir les erreurs catchées

### **Cas 3 : Tableau vide sans en-têtes**

**Cause** : Erreur critique avant même l'affichage du tableau

**Solution** :

1. Vérifier les logs d'erreur PHP :
   ```bash
   tail -f /var/log/php-fpm/error.log
   ```

2. Chercher des erreurs SQL :
   - Colonne manquante dans une table
   - Table inexistante (vérifier structure Moodle 4.5)

3. Vérifier la compatibilité Moodle :
   ```
   Administration → Notifications
   ```
   Chercher les mises à jour BDD non appliquées

### **Cas 4 : Timeout**

**Symptôme** : Page blanche ou "Erreur 504 Gateway Timeout"

**Cause** : Base de données trop volumineuse (> 10 000 questions)

**Solution** :

1. **Réduire le nombre de questions affichées** :
   
   Modifier l'URL manuellement :
   ```
   /local/question_diagnostic/questions_cleanup.php?loadstats=1&show=10
   ```

2. **Augmenter le timeout** :
   
   Dans `php.ini` :
   ```ini
   max_execution_time = 600
   ```

3. **Utiliser le mode pagination** (prévu v1.10.0)

---

## 📊 Informations de Debug Utiles

### **v1.9.12 : Nouveaux Messages**

**Message si aucune question** :
```
⚠️ Aucune question trouvée

Aucune question ne correspond aux critères actuels.

Causes possibles :
• Votre base de données ne contient aucune question
• Les filtres actifs excluent toutes les questions
• Une erreur de chargement est survenue (vérifier les logs)
```

**Log de debug** (visible en mode DEBUG_DEVELOPER) :
```
Questions chargées : 10 sur 10 demandées (Total BDD : 29500)
```

---

## 🛠️ Commandes Utiles

### Compter les questions dans la BDD

```bash
# Via CLI Moodle
php admin/cli/execute_sql.php --sql="SELECT COUNT(*) as total FROM {question}"

# Via MySQL directement
mysql -u moodle -p moodle_db -e "SELECT COUNT(*) as total FROM mdl_question;"
```

### Vérifier la structure des tables

```bash
php admin/cli/execute_sql.php --sql="SHOW COLUMNS FROM {question}"
```

### Tester le chargement des questions

```bash
# Charger 5 questions avec debug
php -r "
define('CLI_SCRIPT', true);
require_once('/path/to/moodle/config.php');
require_once(\$CFG->dirroot . '/local/question_diagnostic/classes/question_analyzer.php');
\$questions = \local_question_diagnostic\question_analyzer::get_all_questions_with_stats(false, 5);
echo 'Questions chargées : ' . count(\$questions) . PHP_EOL;
"
```

---

## 📝 Checklist de Diagnostic

Cochez au fur et à mesure :

- [ ] Cache Moodle purgé
- [ ] Cliqué sur "Charger les Statistiques"
- [ ] Mode debug activé
- [ ] Vérifier version plugin = v1.9.12
- [ ] Vérifier le message de debug "Questions chargées"
- [ ] Consulter les logs Moodle
- [ ] Vérifier le nombre de questions en BDD (SQL)
- [ ] Désactiver les filtres JavaScript
- [ ] Augmenter timeout/mémoire PHP
- [ ] Tester avec show=10 dans l'URL

---

## 🆘 Contacter le Support

Si le problème persiste après toutes ces étapes, fournir :

1. **Version Moodle exacte** : Administration → Notifications
2. **Version du plugin** : v1.9.12
3. **Nombre de questions en BDD** : Résultat de `SELECT COUNT(*) FROM mdl_question`
4. **Message de debug** : "Questions chargées : ..."
5. **Logs d'erreur** : Les 50 dernières lignes de `/var/log/php-fpm/error.log`
6. **Capture d'écran** : La page complète incluant les messages d'erreur

---

**Document créé le** : 10 octobre 2025  
**Version** : 1.0  
**Plugin version** : v1.9.12

