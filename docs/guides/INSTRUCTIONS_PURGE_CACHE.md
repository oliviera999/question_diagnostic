# 🔧 Instructions de Purge du Cache Moodle

## ⚠️ CRITIQUE : Le Cache DOIT être purgé

Les modifications de code dans `questions_cleanup.php` ne seront PAS actives tant que le cache Moodle n'est pas purgé.

**Symptôme si le cache n'est pas purgé** :
- Vous voyez toujours "Groupe de Doublons Utilisés Trouvé" avec "0 utilisations"
- L'ancien code avec le bug `!empty()` s'exécute encore

---

## 🚀 Méthode 1 : Interface Moodle (RECOMMANDÉE)

### Étapes

1. **Se connecter en tant qu'administrateur** sur votre site Moodle

2. **Naviguer vers** :
   ```
   Administration du site → Développement → Purger tous les caches
   ```
   
   **OU directement via l'URL** :
   ```
   https://votre-moodle.com/admin/purgecaches.php
   ```

3. **Cliquer sur le bouton** :
   ```
   "Purger tous les caches"
   ```

4. **Attendre** que Moodle confirme :
   ```
   ✅ "Les caches ont été purgés"
   ```

5. **Rafraîchir la page** du navigateur (F5 ou Ctrl+R)

---

## 🖥️ Méthode 2 : Ligne de Commande (Pour serveur)

### Via CLI Moodle

```bash
# Se placer dans le répertoire Moodle
cd /chemin/vers/moodle

# Purger tous les caches
php admin/cli/purge_caches.php
```

**Résultat attendu** :
```
Purging cache stores:
  Default application store
  Default session store
  Default request store
  Language string cache
  ...
Caches purged successfully
```

### Via sudo (si nécessaire)

```bash
sudo -u www-data php admin/cli/purge_caches.php
```

---

## 🔄 Méthode 3 : Vider le répertoire cache (Méthode manuelle)

⚠️ **ATTENTION** : Utiliser uniquement en dernier recours

```bash
# Se placer dans moodledata
cd /chemin/vers/moodledata

# Supprimer le contenu du répertoire cache
rm -rf cache/*
rm -rf localcache/*
```

**IMPORTANT** : Assurez-vous que les permissions sont correctes après :
```bash
chown -R www-data:www-data cache/
chown -R www-data:www-data localcache/
chmod -R 755 cache/
chmod -R 755 localcache/
```

---

## ✅ Vérification que le Cache est Purgé

### Étape 1 : Vérifier la version du plugin

1. **Accéder à** :
   ```
   Administration du site → Plugins → Vue d'ensemble des plugins
   ```

2. **Rechercher** : `local_question_diagnostic`

3. **Vérifier** que la version affichée est :
   ```
   v1.9.9 (2025101011)  ← Version avec la correction
   ```

### Étape 2 : Tester la fonctionnalité

1. **Accéder à** :
   ```
   Administration du site → Plugins locaux → Question Diagnostic
   ```
   
2. **Cliquer sur** : `Analyser les questions`

3. **Cliquer sur** : `🎲 Test Doublons Utilisés`

4. **Résultat attendu APRÈS correction** :

   **✅ CAS A : Groupe utilisé trouvé**
   ```
   🎯 Groupe de Doublons Utilisés Trouvé !
   
   Versions utilisées : 5 (ou plus)  ← DOIT être > 0
   Versions inutilisées : 129
   Total quiz : 5 quiz  ← DOIT être > 0
   ```
   
   **✅ CAS B : Aucun groupe utilisé**
   ```
   ⚠️ Aucun groupe de doublons utilisés trouvé
   
   Après 5 tentatives, aucun groupe de doublons avec au moins
   1 version utilisée n'a été trouvé.
   ```

   **❌ BUG PERSISTANT (cache non purgé)** :
   ```
   🎯 Groupe de Doublons Utilisés Trouvé !  ← Dit "utilisés"
   
   Versions utilisées : 0  ← INCOHÉRENCE !
   Versions inutilisées : 134
   ```

---

## 🐛 Mode Debug (Si le problème persiste)

Si après purge du cache, le problème persiste, activer le mode debug :

### Activer le Debug Moodle

1. **Éditer** `config.php` :
   ```php
   $CFG->debug = (E_ALL | E_STRICT);
   $CFG->debugdisplay = 1;
   ```

2. **Purger le cache**

3. **Retester** le bouton "Test Doublons Utilisés"

4. **Consulter les logs** dans :
   ```
   Administration du site → Rapports → Logs
   ```

5. **Chercher** les messages de debug commençant par :
   ```
   GROUPE MARQUÉ COMME UTILISÉ - Détails : {...}
   ```

6. **Envoyer** ces logs pour analyse si le problème persiste

---

## 📞 Support

Si après avoir :
- ✅ Purgé le cache
- ✅ Vérifié la version (v1.9.9)
- ✅ Activé le mode debug

Le problème **persiste toujours**, cela indique un problème plus profond avec la fonction `get_questions_usage_by_ids()`.

Dans ce cas, fournir :
1. Les logs de debug
2. La version exacte de Moodle (`Administration → Notifications`)
3. Le message d'erreur complet (si affiché)

---

## 🎯 Récapitulatif

```
AVANT purge cache : Bug visible (0 utilisations affichées)
        ↓
    PURGER LE CACHE
        ↓
APRÈS purge cache : Bug corrigé (cohérence restaurée)
```

**IMPORTANT** : **TOUJOURS purger le cache après modification de code** dans Moodle !

---

**Document créé le** : 10 octobre 2025  
**Version** : 1.0

