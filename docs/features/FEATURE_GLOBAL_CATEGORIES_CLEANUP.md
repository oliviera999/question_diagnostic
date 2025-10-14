# 🧹 Fonctionnalité : Nettoyage global des catégories

**Version** : v1.10.2  
**Date de création** : Octobre 2025  
**Statut** : ✅ Implémenté

---

## 📝 Description

Cette fonctionnalité permet de **supprimer automatiquement toutes les catégories supprimables** du site en une seule opération. Elle identifie et supprime en masse toutes les catégories vides et non protégées, avec traitement par lots pour éviter les timeouts sur de grandes bases de données.

## 🎯 Objectifs

1. **Automatiser** le nettoyage complet des catégories inutilisées
2. **Identifier** toutes les catégories supprimables en une seule analyse
3. **Traiter par lots** pour supporter de grandes bases de données
4. **Protéger** automatiquement les catégories importantes
5. **Auditer** toutes les suppressions avec traçabilité complète

## 📂 Fichiers créés/modifiés

### Nouveaux fichiers
- `actions/cleanup_all_categories.php` - Action de nettoyage global

### Fichiers modifiés
- `categories.php` - Ajout bouton "🧹 Nettoyage Global"
- `lang/fr/local_question_diagnostic.php` - Chaînes de langue FR
- `lang/en/local_question_diagnostic.php` - Chaînes de langue EN

## 🏗️ Architecture technique

### Flux de l'outil

```
1. PREVIEW (Prévisualisation)
   ↓
   → Analyse toutes les catégories
   → Calcule statistiques (à supprimer / à conserver)
   → Affiche dashboard + confirmation
   ↓
2. CONFIRM (Confirmation)
   ↓
   → Utilisateur confirme ou télécharge CSV
   ↓
3. EXECUTE (Exécution par lots)
   ↓
   → Traitement par lots de 20 catégories
   → Suppression + Audit logging
   → Auto-redirection vers lot suivant
   ↓
4. COMPLETE (Résumé final)
   ↓
   → Affiche statistiques finales
   → Lien retour vers categories.php
```

### Modes de fonctionnement

#### Mode 1 : PREVIEW (Prévisualisation)
- URL : `?preview=1&sesskey=XXX`
- Analyse toutes les catégories du site
- Calcule les statistiques de nettoyage
- Affiche :
  - Dashboard avec 4 cartes (Total, À supprimer, À conserver, Temps estimé)
  - Détails par type (vides, orphelines)
  - Règles de protection appliquées
  - Boutons : Télécharger CSV / Confirmer / Annuler

#### Mode 2 : DOWNLOAD_CSV (Export)
- URL : `?download_csv=1&sesskey=XXX`
- Génère un fichier CSV avec la liste complète
- Colonnes : ID, Nom, Context ID, Contexte, Parent ID, Vide, Orpheline, Action
- Encodage UTF-8 avec BOM pour Excel

#### Mode 3 : EXECUTE (Exécution par lots)
- URL : `?execute=1&batch=N&sesskey=XXX`
- Traite 20 catégories par lot
- Affiche :
  - Barre de progression
  - Liste des catégories supprimées/erreurs
  - Statistiques cumulées
  - Auto-redirection vers lot suivant
- Stockage en session : `$_SESSION['cleanup_categories_deleted']`

#### Mode 4 : COMPLETE (Résumé final)
- URL : `?complete=1&deleted=X&errors=Y&sesskey=XXX`
- Affiche le résumé final
- Nettoie les variables de session
- Lien retour vers categories.php

## 🛡️ Règles de protection

### Catégories PROTÉGÉES (non supprimables)

1. ✅ **Catégories "Default for..."** : Catégories par défaut Moodle
2. ✅ **Catégories avec description** : Ont une description (champ `info`)
3. ✅ **Catégories racine** : Parent = 0 (top-level)
4. ✅ **Catégories avec questions** : Contiennent au moins 1 question
5. ✅ **Catégories avec sous-catégories** : Ont des enfants

### Catégories SUPPRIMABLES

- ✅ Vides (0 questions ET 0 sous-catégories)
- ✅ Non protégées
- ✅ OU orphelines (contexte invalide)

## 📊 Statistiques calculées

```php
$stats = new stdClass();
$stats->total_categories = X;           // Total dans la base
$stats->total_to_delete = Y;            // Supprimables
$stats->total_to_keep = Z;              // À conserver
$stats->empty_to_delete = N;            // Vides supprimables
$stats->empty_to_keep = M;              // Vides protégées
$stats->orphan_to_delete = P;           // Orphelines supprimables
$stats->orphan_to_keep = Q;             // Orphelines protégées
$stats->estimated_time_seconds = T;     // ~0.3s par catégorie
$stats->estimated_batches = B;          // Nombre de lots
$stats->categories_list = [];           // Liste pour CSV
```

## 💾 Performances

### Paramètres de traitement

- **Taille de lot** : 20 catégories par batch (`BATCH_SIZE`)
- **Temps par catégorie** : ~0.3 secondes
- **Auto-redirection** : 2 secondes entre les lots
- **Session storage** : Compteurs cumulés

### Exemples de temps

| Catégories à supprimer | Lots | Temps total estimé |
|------------------------|------|--------------------|
| 20                     | 1    | ~6 secondes        |
| 100                    | 5    | ~30 secondes       |
| 500                    | 25   | ~2.5 minutes       |
| 1000                   | 50   | ~5 minutes         |

### Optimisations

1. **Traitement par lots** : Évite les timeouts PHP
2. **Auto-redirection** : Continuité automatique entre lots
3. **Session storage** : Conserve les compteurs entre requêtes
4. **Requêtes SQL optimisées** : Utilise `get_all_categories_with_stats()` pré-optimisée

## 🔒 Sécurité

### Validations

1. ✅ **Admin uniquement** : `is_siteadmin()` requis
2. ✅ **Session key** : `require_sesskey()` sur toutes les actions
3. ✅ **Confirmation utilisateur** : Page de prévisualisation obligatoire
4. ✅ **Double vérification** : `can_delete_category()` avant suppression
5. ✅ **Protection automatique** : Règles strictes appliquées

### Audit logging

Chaque suppression est tracée avec :
- **Action** : `ACTION_DELETE_CATEGORY`
- **Type** : `category`
- **Item ID** : ID de la catégorie
- **Item name** : Nom de la catégorie
- **User ID** : Admin qui a lancé l'action
- **Metadata** : 
  - `context` : "Nettoyage global automatique"
  - `contextid` : ID du contexte
  - `was_empty` : 1 si vide
  - `was_orphan` : 1 si orpheline

## 🎨 Interface utilisateur

### Page de prévisualisation

#### Dashboard (4 cartes)
- **Total catégories** : Nombre total dans la base
- **À supprimer** : Nombre + pourcentage (rouge)
- **À conserver** : Nombre + pourcentage (vert)
- **Temps estimé** : Minutes + nombre de lots

#### Tableau détaillé
| Type | À supprimer | À conserver |
|------|-------------|-------------|
| 🗑️ Catégories vides | X | Y |
| 👻 Catégories orphelines | X | Y |

#### Avertissement
- Message d'alerte rouge
- Compte des catégories à supprimer
- Liste des règles de protection

#### Boutons d'action
- **📥 Télécharger CSV** : Export de la liste
- **🚀 Confirmer et lancer** : Démarrage du nettoyage
- **← Annuler** : Retour à categories.php

### Page d'exécution

- **Titre** : "🚀 Nettoyage en cours..."
- **Info** : "Traitement du lot X sur Y"
- **Barre de progression** : Pourcentage visuel animé
- **Liste** : ✅/❌ pour chaque catégorie traitée
- **Statistiques** : Supprimées / Conservées / Erreurs
- **Auto-redirection** : Vers lot suivant après 2s

### Page de résumé

- **Titre** : "✅ Nettoyage global terminé"
- **Message** : "🎉 Nettoyage terminé avec succès !"
- **Statistiques** : X catégorie(s) supprimée(s)
- **Erreurs** : Si > 0, affichage en orange
- **Bouton** : "← Retour aux catégories"

## 📋 Export CSV

### Structure du fichier

```csv
ID;Nom;Context ID;Contexte;Parent ID;Vide;Orpheline;Action
123;Catégorie Test;45;Course: Test;0;Oui;Non;Supprimer
```

### Caractéristiques
- **Séparateur** : Point-virgule (`;`) pour Excel français
- **Encodage** : UTF-8 avec BOM
- **Nom du fichier** : `cleanup_categories_YYYY-MM-DD_HHmmss.csv`

## ✅ Tests recommandés

### Tests fonctionnels

1. ✅ **Prévisualisation**
   - Vérifier l'affichage des statistiques
   - Vérifier le dashboard (4 cartes)
   - Vérifier le tableau de détails
   - Vérifier les règles de protection

2. ✅ **Export CSV**
   - Télécharger le CSV
   - Vérifier l'encodage UTF-8
   - Vérifier les données

3. ✅ **Exécution simple** (< 20 catégories)
   - Lancer le nettoyage
   - Vérifier la suppression
   - Vérifier les logs d'audit
   - Vérifier le résumé

4. ✅ **Exécution par lots** (> 20 catégories)
   - Lancer le nettoyage
   - Vérifier les auto-redirections
   - Vérifier les compteurs cumulés
   - Vérifier le résumé final

5. ✅ **Protection**
   - Créer catégorie "Default for Test"
   - Vérifier qu'elle n'est PAS dans la liste
   - Créer catégorie avec description
   - Vérifier qu'elle n'est PAS dans la liste

6. ✅ **Annulation**
   - Cliquer sur "Annuler"
   - Vérifier retour à categories.php
   - Vérifier qu'aucune suppression n'a eu lieu

### Tests de performance

1. ✅ **Petite base** (< 50 catégories à supprimer)
   - Temps < 30 secondes

2. ✅ **Grande base** (> 500 catégories à supprimer)
   - Vérifier l'exécution par lots
   - Vérifier l'absence de timeout
   - Vérifier l'intégrité des compteurs

### Tests de sécurité

1. ✅ **Accès non autorisé**
   - Tester l'accès sans être admin (doit échouer)

2. ✅ **CSRF Protection**
   - Tester sans `sesskey` (doit échouer)

3. ✅ **Protection des données**
   - Vérifier qu'aucune catégorie protégée n'est supprimée

## 🚀 Utilisation

### Workflow complet

1. **Accéder** : Page categories.php → Cliquer sur "🧹 Nettoyage Global"
2. **Analyser** : Examiner les statistiques de prévisualisation
3. **Télécharger** (optionnel) : CSV pour archivage/documentation
4. **Confirmer** : Cliquer sur "🚀 Confirmer et lancer"
5. **Patienter** : Laisser le traitement par lots s'exécuter
6. **Vérifier** : Consulter le résumé final
7. **Retour** : Cliquer sur "← Retour aux catégories"

### Bonnes pratiques

1. ✅ **Backup** : Faire une sauvegarde complète AVANT
2. ✅ **Test** : Tester sur environnement de développement d'abord
3. ✅ **CSV** : Télécharger le CSV pour traçabilité
4. ✅ **Timing** : Lancer hors heures de production
5. ✅ **Vérification** : Vérifier les logs d'audit après

## 🔗 Navigation

- **categories.php** → Bouton "🧹 Nettoyage Global"
- **Preview** → Boutons : CSV / Confirmer / Annuler
- **Execute** → Auto-redirection entre lots
- **Complete** → Bouton "← Retour aux catégories"

## 📚 Documentation connexe

- `docs/guides/USER_GUIDE.md` - Guide utilisateur
- `docs/features/FEATURE_BULK_OPERATIONS.md` - Opérations en masse
- `docs/technical/AUDIT_LOGGING.md` - Système d'audit
- `USER_CONSENT_PATTERNS.md` - Patterns de confirmation

## 📈 Métriques de succès

- **Adoption** : Nombre d'utilisations par mois
- **Efficacité** : Nombre moyen de catégories supprimées
- **Fiabilité** : Taux d'erreur < 1%
- **Performance** : Temps moyen < 5 minutes pour 1000 catégories

## 🚀 Évolutions futures possibles

1. **Planification** : Nettoyage automatique programmé (cron)
2. **Notifications** : Email à l'admin avec le résumé
3. **Dry-run avancé** : Mode simulation sans suppression réelle
4. **Filtres** : Sélectionner les types de catégories à supprimer
5. **Restauration** : Trash temporaire avant suppression définitive
6. **Statistiques** : Dashboard historique des nettoyages

## 👥 Contributeurs

- **Développeur initial** : Équipe local_question_diagnostic
- **Version** : v1.10.2
- **Date** : Octobre 2025

## 🔍 Différences avec cleanup_all_duplicates

| Fonctionnalité | Questions (duplicates) | Catégories |
|----------------|----------------------|------------|
| **Cible** | Questions en doublon | Catégories vides/orphelines |
| **Critère** | Même nom + type | Vide OU orpheline |
| **Protection** | Questions utilisées | Catégories protégées |
| **Taille lot** | 10 groupes | 20 catégories |
| **Temps/item** | 0.5s | 0.3s |
| **Audit** | Oui | Oui |

---

**Note** : Cette fonctionnalité respecte toutes les règles de sécurité et les standards Moodle définis dans le projet. Consentement utilisateur obligatoire avant toute modification.

