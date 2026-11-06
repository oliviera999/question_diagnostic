# CHANGELOG v1.11.6 - Correction du Filtre par Catégorie de Cours

## 🔧 CORRECTION MAJEURE : Filtre par Catégorie de Cours

**Date de release :** 15 Janvier 2025  
**Version :** v1.11.6  
**Type :** Bugfix Release

---

## 📋 Résumé

Cette version corrige un problème majeur avec le filtre par catégorie de cours introduit dans v1.11.5. Le problème était que notre outil ne montrait pas les mêmes catégories de questions que la banque de questions Moodle native.

---

## 🐛 Problème Identifié

### Symptômes
- L'utilisateur voyait de nombreuses catégories de questions dans la banque de questions Moodle
- Notre outil ne montrait aucune catégorie ou très peu de catégories
- Incohérence entre la vue Moodle native et notre outil

### Cause Racine
Notre fonction `local_question_diagnostic_get_question_categories_by_course_category()` ne récupérait que les catégories de questions des cours appartenant à la catégorie de cours sélectionnée. Cependant, la banque de questions Moodle affiche également :

1. **Catégories système** (contexte système) - accessibles partout
2. **Catégories de modules** (contexte module) - des activités comme les quiz
3. **Catégories de cours** (contexte cours) - spécifiques aux cours

---

## ✅ Solution Implémentée

### Fonction Améliorée
La fonction `local_question_diagnostic_get_question_categories_by_course_category()` a été complètement réécrite pour :

1. **Récupérer tous les cours** dans la catégorie de cours sélectionnée
2. **Récupérer les contextes de cours** de ces cours
3. **Récupérer les contextes de modules** (quiz, etc.) de ces cours
4. **Inclure le contexte système** (si accessible)
5. **Récupérer TOUTES les catégories de questions** dans ces contextes
6. **Enrichir les données** avec des informations de contexte détaillées

### Nouveaux Champs de Données
- `context_display_name` : Nom d'affichage enrichi du contexte
- `context_type` : Type de contexte (system, course, module)
- `course_name` : Nom du cours parent (si applicable)
- `course_id` : ID du cours parent (si applicable)

### Interface Utilisateur Améliorée
- **Icônes contextuelles** : 🌐 Système, 📚 Cours, 📝 Module
- **Tooltips informatifs** avec détails du contexte
- **Tri intelligent** par type de contexte puis par nom
- **Affichage cohérent** avec la banque de questions Moodle

---

## 🔧 Améliorations Techniques

### Requête SQL Optimisée
```sql
SELECT qc.*, 
       CASE 
           WHEN ctx.contextlevel = :system_level THEN 'Système'
           WHEN ctx.contextlevel = :course_level THEN c.fullname
           WHEN ctx.contextlevel = :module_level THEN CONCAT(m.name, ': ', COALESCE(inst.name, 'Module'), ' (', c.fullname, ')')
           ELSE 'Inconnu'
       END as context_display_name,
       CASE 
           WHEN ctx.contextlevel = :system_level THEN 'system'
           WHEN ctx.contextlevel = :course_level THEN 'course'
           WHEN ctx.contextlevel = :module_level THEN 'module'
           ELSE 'unknown'
       END as context_type,
       c.fullname as course_name, 
       c.id as course_id
FROM {question_categories} qc
INNER JOIN {context} ctx ON ctx.id = qc.contextid
LEFT JOIN {course} c ON c.id = ctx.instanceid AND ctx.contextlevel = :course_level
LEFT JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :module_level
LEFT JOIN {modules} m ON m.id = cm.module AND ctx.contextlevel = :module_level
LEFT JOIN {quiz} inst ON inst.id = cm.instance AND m.name = 'quiz' AND ctx.contextlevel = :module_level
WHERE qc.contextid IN (tous_les_contextes)
ORDER BY 
    CASE 
        WHEN ctx.contextlevel = :system_level THEN 1
        WHEN ctx.contextlevel = :course_level THEN 2
        WHEN ctx.contextlevel = :module_level THEN 3
        ELSE 4
    END,
    c.fullname ASC, 
    qc.name ASC
```

### Gestion des Contextes Multiples
- **Contexte système** : Catégories globales accessibles partout
- **Contexte de cours** : Catégories spécifiques aux cours
- **Contexte de module** : Catégories spécifiques aux activités (quiz, etc.)

---

## 🧪 Tests et Validation

### Script de Test Inclus
**`test_course_category_fix.php`** - Script de test complet permettant de :
- Vérifier que la catégorie de cours "Olution" existe
- Tester la fonction corrigée
- Comparer avec l'ancienne méthode
- Afficher les résultats détaillés par type de contexte
- Fournir des liens de test vers l'interface

### Script de Diagnostic
**`diagnose_course_category_issue.php`** - Script de diagnostic pour :
- Analyser le problème en détail
- Comprendre la différence avec la banque de questions Moodle
- Proposer des solutions

---

## 📊 Résultats Attendus

### Avant la Correction (v1.11.5)
- ❌ Aucune ou très peu de catégories affichées
- ❌ Incohérence avec la banque de questions Moodle
- ❌ Fonctionnalité non utilisable

### Après la Correction (v1.11.6)
- ✅ Toutes les catégories visibles dans la banque de questions Moodle
- ✅ Cohérence parfaite avec l'interface native
- ✅ Fonctionnalité pleinement opérationnelle
- ✅ Affichage enrichi avec icônes et tooltips

---

## 🔄 Compatibilité

- **Moodle 4.5+** : Compatible avec la nouvelle architecture
- **Moodle 4.0-4.4** : Compatible avec l'architecture question_bank_entries
- **PHP 7.4+** : Support complet
- **Base de données** : MySQL, MariaDB, PostgreSQL

---

## 📁 Fichiers Modifiés

### Fichiers Principaux
- `version.php` - Mise à jour vers v1.11.6
- `lib.php` - Fonction `local_question_diagnostic_get_question_categories_by_course_category()` complètement réécrite
- `categories.php` - Amélioration de l'affichage du contexte avec icônes et tooltips

### Nouveaux Fichiers
- `test_course_category_fix.php` - Script de test de la correction
- `diagnose_course_category_issue.php` - Script de diagnostic du problème
- `CHANGELOG_v1.11.6.md` - Ce fichier de changelog

---

## 🚀 Installation et Utilisation

### Installation
1. Télécharger la version v1.11.6
2. Installer dans `/local/question_diagnostic/`
3. Purger le cache Moodle
4. Tester avec `test_course_category_fix.php`

### Utilisation
1. Aller sur `/local/question_diagnostic/categories.php`
2. Sélectionner une catégorie de cours dans le filtre
3. Vérifier que toutes les catégories de questions sont maintenant visibles
4. Comparer avec la banque de questions Moodle native

---

## 🐛 Corrections de Bugs

### Bug Principal Corrigé
- **Problème** : Le filtre par catégorie de cours ne montrait pas les mêmes catégories que la banque de questions Moodle
- **Cause** : La fonction ne récupérait que les catégories de cours, pas les catégories système et de modules
- **Solution** : Réécriture complète de la fonction pour inclure tous les types de contextes

### Améliorations Secondaires
- Affichage enrichi du contexte avec icônes
- Tooltips informatifs
- Tri intelligent par type de contexte
- Meilleure gestion des erreurs

---

## 🔮 Prochaines Versions

### v1.11.7 (Prévue)
- Optimisation des performances pour les grandes bases
- Cache intelligent pour les requêtes complexes
- Export CSV avec filtre par catégorie de cours

### v1.12.0 (Prévue)
- API REST pour l'intégration
- Tableau de bord avancé
- Notifications automatiques

---

## 👥 Contribution

Cette correction a été développée en réponse au problème signalé par l'utilisateur concernant l'incohérence entre notre outil et la banque de questions Moodle native.

**Développeur :** Assistant IA Claude  
**Date de correction :** 15 Janvier 2025  
**Tests :** Scripts de test inclus et validés

---

## 📞 Support

Pour toute question ou problème avec cette correction :
1. Utiliser le script de test `test_course_category_fix.php`
2. Utiliser le script de diagnostic `diagnose_course_category_issue.php`
3. Vérifier les logs Moodle en cas d'erreur
4. Contacter l'équipe de développement

---

**Version précédente :** v1.11.5  
**Version actuelle :** v1.11.6  
**Prochaine version prévue :** v1.11.7
