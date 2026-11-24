# CLAUDE.md — question_diagnostic

Guide pour Claude Code sur **local_question_diagnostic** : plugin Moodle (5.1 à 5.3, PHP 8.2+) de
diagnostic et de nettoyage de la banque de questions, utilisé sur olution.info.
Répondre, commenter et documenter en **français**. Référence détaillée : `README.md`, `docs/`.

## Où ce dépôt s'inscrit

- **Projet Claude Code** : « olution — plateforme et enseignement ». Il regroupe les dépôts
  `mood_prod`, `pronoteio-sidecar`, `question_diagnostic`, `audit_bdd` et `index_olution` ;
  ses instructions s'ajoutent à ce fichier.
- **Projet de chat claude.ai** : « Projet olution.info ». Les contenus de cours, la banque de
  questions et les décisions pédagogiques s'y travaillent ; le code se fait ici.
- **Connecteur MCP `olution`** : il agit sur la **prod** de olution.info (plans d'écriture,
  déploiement, Pronote). À utiliser pour vérifier l'effet d'un changement, pas pour le contourner.

## Règles

- Respecter les **garde-fous** décrits dans le `README.md` (doublons par empreinte complète,
  usage au sens large, catégories visées par des tirages aléatoires intouchables, suppression par
  `local\question_deleter` avec export XML préalable, fusion limitée à `question_references`).
- Toute opération qui écrit : confirmation en POST avec `sesskey`, journal Moodle
  (`operation_performed`), tâche de fond pour les opérations sur tout le site.
- Monter `version.php` et compléter `CHANGELOG.md` à chaque livraison ; la CI
  (moodle-plugin-ci) doit être verte.
- Le plugin est épinglé par commit dans `mood_prod/manifest.json` : une nouvelle version n'arrive
  sur olution.info qu'en mettant à jour ce SHA dans `mood_prod`.
