# ✅ Checklist de Test - Opérations par Lot

## 🎯 Objectif
Vérifier que toutes les fonctionnalités d'opérations par lot fonctionnent correctement.

---

## 📋 Tests à Effectuer

### Test 1️⃣ : Apparition de la Barre d'Actions
**Objectif :** Vérifier que la barre apparaît correctement

| Étape | Action | Résultat Attendu | ✅/❌ |
|-------|--------|------------------|-------|
| 1.1 | Accéder à `/local/question_diagnostic/categories.php` | Page se charge sans erreur | ☐ |
| 1.2 | Observer l'état initial | Aucune barre violette visible | ☐ |
| 1.3 | Cocher 1 catégorie | Barre violette apparaît avec animation | ☐ |
| 1.4 | Vérifier le compteur | Affiche "1 catégorie(s) sélectionnée(s)" | ☐ |
| 1.5 | Vérifier les boutons | 3 boutons visibles : Supprimer, Exporter, Annuler | ☐ |
| 1.6 | Vérifier la couleur | Fond dégradé violet (#667eea → #764ba2) | ☐ |

**Critères de Succès :** Toutes les cases cochées ✅

---

### Test 2️⃣ : Sélection Multiple
**Objectif :** Vérifier la sélection de plusieurs catégories

| Étape | Action | Résultat Attendu | ✅/❌ |
|-------|--------|------------------|-------|
| 2.1 | Cocher 3 catégories manuellement | Compteur affiche "3" | ☐ |
| 2.2 | Vérifier les lignes | 3 lignes surlignées en bleu | ☐ |
| 2.3 | Décocher 1 catégorie | Compteur affiche "2" | ☐ |
| 2.4 | Cliquer sur "Tout sélectionner" | Toutes les catégories cochées | ☐ |
| 2.5 | Vérifier le compteur | Affiche le nombre total de catégories | ☐ |
| 2.6 | Cliquer à nouveau sur "Tout sélectionner" | Toutes décochées, barre disparaît | ☐ |

**Critères de Succès :** Le compteur est toujours correct ✅

---

### Test 3️⃣ : Bouton Annuler
**Objectif :** Vérifier le bouton d'annulation

| Étape | Action | Résultat Attendu | ✅/❌ |
|-------|--------|------------------|-------|
| 3.1 | Sélectionner 5 catégories | Barre visible avec "5" | ☐ |
| 3.2 | Cliquer sur "❌ Annuler" | Toutes les cases décochées | ☐ |
| 3.3 | Vérifier les lignes | Aucune ligne surlignée | ☐ |
| 3.4 | Vérifier la barre | Barre disparaît | ☐ |
| 3.5 | Vérifier "Tout sélectionner" | Case décochée | ☐ |

**Critères de Succès :** Réinitialisation complète ✅

---

### Test 4️⃣ : Export par Lot
**Objectif :** Vérifier l'export de catégories sélectionnées

| Étape | Action | Résultat Attendu | ✅/❌ |
|-------|--------|------------------|-------|
| 4.1 | Sélectionner exactement 3 catégories | Barre affiche "3" | ☐ |
| 4.2 | Cliquer sur "📤 Exporter la sélection" | Téléchargement déclenché | ☐ |
| 4.3 | Vérifier le nom du fichier | Format : `categories_questions_selection_YYYY-MM-DD_HH-mm-ss.csv` | ☐ |
| 4.4 | Ouvrir le CSV | S'ouvre dans Excel/LibreOffice | ☐ |
| 4.5 | Compter les lignes | Exactement 4 lignes (1 en-tête + 3 catégories) | ☐ |
| 4.6 | Vérifier les colonnes | ID, Nom, Contexte, Parent, Questions visibles, Questions totales, Sous-cat., Statut | ☐ |
| 4.7 | Vérifier les données | Les 3 catégories sélectionnées sont présentes | ☐ |

**Critères de Succès :** CSV correct avec seulement les catégories sélectionnées ✅

---

### Test 5️⃣ : Suppression par Lot (Succès)
**Objectif :** Supprimer des catégories vides

| Étape | Action | Résultat Attendu | ✅/❌ |
|-------|--------|------------------|-------|
| 5.1 | Filtrer par "Vides" | Seules les catégories vides affichées | ☐ |
| 5.2 | Sélectionner 2 catégories vides | Barre affiche "2" | ☐ |
| 5.3 | Cliquer sur "🗑️ Supprimer la sélection" | Redirection vers page de confirmation | ☐ |
| 5.4 | Vérifier le message | "Vous êtes sur le point de supprimer 2 catégorie(s)" | ☐ |
| 5.5 | Cliquer sur "Oui, supprimer" | Redirection vers la page catégories | ☐ |
| 5.6 | Vérifier le message de succès | "✅ 2 catégorie(s) supprimée(s) avec succès." | ☐ |
| 5.7 | Vérifier le tableau | Les 2 catégories ont disparu | ☐ |

**Critères de Succès :** Suppression réussie sans erreur ✅

---

### Test 6️⃣ : Suppression par Lot (Erreur)
**Objectif :** Vérifier la gestion des erreurs

| Étape | Action | Résultat Attendu | ✅/❌ |
|-------|--------|------------------|-------|
| 6.1 | Sélectionner 1 catégorie vide + 1 catégorie avec questions | Barre affiche "2" | ☐ |
| 6.2 | Tenter la suppression | Page de confirmation s'affiche | ☐ |
| 6.3 | Confirmer | Redirection avec message d'erreur | ☐ |
| 6.4 | Vérifier le message | Détaille quelle catégorie n'a pas pu être supprimée | ☐ |
| 6.5 | Vérifier le tableau | Catégorie vide supprimée, l'autre toujours présente | ☐ |

**Critères de Succès :** Erreur claire + suppression partielle ✅

---

### Test 7️⃣ : Interaction avec les Filtres
**Objectif :** Vérifier l'intégration avec les filtres

| Étape | Action | Résultat Attendu | ✅/❌ |
|-------|--------|------------------|-------|
| 7.1 | Appliquer filtre "Vides" | Affichage réduit aux catégories vides | ☐ |
| 7.2 | Cliquer "Tout sélectionner" | Seulement les catégories visibles cochées | ☐ |
| 7.3 | Vérifier le compteur | Nombre = nombre de catégories vides | ☐ |
| 7.4 | Changer le filtre à "Orphelines" | Sélection maintenue sur les catégories cochées | ☐ |
| 7.5 | Annuler la sélection | Barre disparaît | ☐ |

**Critères de Succès :** Filtres et sélection fonctionnent ensemble ✅

---

### Test 8️⃣ : Responsive Design (Mobile)
**Objectif :** Vérifier l'affichage sur mobile

| Étape | Action | Résultat Attendu | ✅/❌ |
|-------|--------|------------------|-------|
| 8.1 | Ouvrir DevTools (F12) | Console ouverte | ☐ |
| 8.2 | Activer mode mobile (< 768px) | Vue mobile activée | ☐ |
| 8.3 | Sélectionner 2 catégories | Barre apparaît | ☐ |
| 8.4 | Vérifier la disposition | Boutons empilés verticalement | ☐ |
| 8.5 | Vérifier la largeur des boutons | Boutons en pleine largeur | ☐ |
| 8.6 | Tester chaque bouton | Tous cliquables et fonctionnels | ☐ |

**Critères de Succès :** Interface utilisable sur mobile ✅

---

### Test 9️⃣ : Performance
**Objectif :** Vérifier les performances

| Étape | Action | Résultat Attendu | ✅/❌ |
|-------|--------|------------------|-------|
| 9.1 | Sélectionner 50 catégories | Barre apparaît rapidement (< 100ms) | ☐ |
| 9.2 | Cliquer sur Annuler | Désélection rapide (< 200ms) | ☐ |
| 9.3 | Tout sélectionner (100+ catégories) | Aucun lag perceptible | ☐ |
| 9.4 | Exporter 100 catégories | Téléchargement immédiat | ☐ |

**Critères de Succès :** Aucun ralentissement ✅

---

### Test 🔟 : Sécurité
**Objectif :** Vérifier les protections

| Étape | Action | Résultat Attendu | ✅/❌ |
|-------|--------|------------------|-------|
| 10.1 | Déconnecter l'utilisateur | Session fermée | ☐ |
| 10.2 | Tenter d'accéder à `actions/delete.php?ids=1,2` | Erreur "require_login" | ☐ |
| 10.3 | Se connecter comme enseignant (non-admin) | Session ouverte | ☐ |
| 10.4 | Accéder à la page catégories | Erreur "accès interdit" | ☐ |
| 10.5 | Se connecter comme admin | Accès autorisé | ☐ |
| 10.6 | Modifier l'URL sans sesskey | Erreur "sesskey invalide" | ☐ |

**Critères de Succès :** Sécurité étanche ✅

---

## 📊 Résumé des Tests

### Statut Global
```
Total tests : 59
Tests réussis : ____ / 59
Tests échoués : ____ / 59
Taux de réussite : _____%
```

### Tests Critiques (Obligatoires)
- [ ] Test 1 : Apparition de la barre
- [ ] Test 3 : Bouton Annuler
- [ ] Test 4 : Export par lot
- [ ] Test 5 : Suppression par lot (succès)
- [ ] Test 6 : Suppression par lot (erreur)

### Tests Importants
- [ ] Test 2 : Sélection multiple
- [ ] Test 7 : Interaction avec filtres
- [ ] Test 10 : Sécurité

### Tests Optionnels
- [ ] Test 8 : Responsive design
- [ ] Test 9 : Performance

---

## 🐛 Bugs Détectés

### Bug #1
**Description :** _____________________  
**Gravité :** ☐ Critique  ☐ Majeure  ☐ Mineure  
**Étapes de reproduction :**
1. _____________________
2. _____________________
3. _____________________

**Résultat attendu :** _____________________  
**Résultat obtenu :** _____________________

---

## ✅ Validation Finale

**Date du test :** _______________  
**Testeur :** _______________  
**Version Moodle :** _______________  
**Navigateur :** _______________

**Conclusion :**
- [ ] ✅ Tous les tests critiques passent → **PRÊT POUR LA PRODUCTION**
- [ ] ⚠️ Quelques tests échouent → **CORRECTIONS NÉCESSAIRES**
- [ ] ❌ Tests critiques échouent → **BLOCAGE MAJEUR**

**Commentaires :**
_____________________________________
_____________________________________
_____________________________________

---

## 🚀 Prochaines Étapes

Si tous les tests passent :
1. ✅ Fusionner dans la branche master
2. ✅ Mettre à jour le CHANGELOG
3. ✅ Créer un tag de version (v1.2.0)
4. ✅ Déployer en production

Si des corrections sont nécessaires :
1. 🐛 Documenter les bugs
2. 🔧 Corriger les problèmes
3. 🔄 Relancer les tests
4. ✅ Valider à nouveau

---

**Checklist créée le :** 2025-01-XX  
**Dernière mise à jour :** 2025-01-XX


