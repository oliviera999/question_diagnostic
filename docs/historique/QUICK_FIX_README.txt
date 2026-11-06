╔═══════════════════════════════════════════════════════════════════════════════╗
║                                                                               ║
║                    🔧 FIX RAPIDE : Erreur Fonction Manquante                  ║
║                                                                               ║
╚═══════════════════════════════════════════════════════════════════════════════╝

Erreur rencontrée :
  "Call to undefined function local_question_diagnostic_get_parent_url()"

═══════════════════════════════════════════════════════════════════════════════

✅ SOLUTION EN 3 ÉTAPES SIMPLES :

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

  ÉTAPE 1 : Synchroniser les Fichiers
  ───────────────────────────────────

  Votre dépôt Git doit être copié vers votre installation Moodle.

  Exemple (Windows/XAMPP) :
  
    Copy-Item -Path "C:\Users\olivi\OneDrive\Bureau\moodle_dev-questions\*" `
              -Destination "C:\xampp\htdocs\moodle\local\question_diagnostic\" `
              -Recurse -Force

  Pour serveur distant : Uploadez via FTP/SFTP

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

  ÉTAPE 2 : Purger les Caches
  ────────────────────────────

  Option A (Automatique - RECOMMANDÉ) :
    → http://votresite.moodle/local/question_diagnostic/purge_cache.php
    
  Option B (Interface Moodle) :
    → Administration du site → Développement → Purger les caches

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

  ÉTAPE 3 : Tester
  ────────────────

  Test automatique :
    → http://votresite.moodle/local/question_diagnostic/test_function.php
    
  Test manuel :
    → Essayez de supprimer une question doublon

═══════════════════════════════════════════════════════════════════════════════

📚 DOCUMENTATION COMPLÈTE :

  • FIX_UNDEFINED_FUNCTION.md  → Guide détaillé avec diagnostic avancé
  • PURGE_CACHE_INSTRUCTIONS.md → Instructions de purge du cache
  • purge_cache.php             → Script automatique de purge
  • test_function.php           → Test de diagnostic

═══════════════════════════════════════════════════════════════════════════════

💡 POURQUOI CETTE ERREUR ?

  La fonction local_question_diagnostic_get_parent_url() existe dans lib.php
  (ligne 665), mais PHP ne la voit pas car :
  
  ❌ Le cache Moodle contient l'ancienne version de lib.php
  ❌ Les fichiers ne sont pas synchronisés entre votre dépôt et Moodle

═══════════════════════════════════════════════════════════════════════════════

🎯 SUCCÈS = Tous ces points sont verts :

  ✅ Fichiers copiés vers l'installation Moodle
  ✅ Caches Moodle purgés
  ✅ test_function.php affiche tous les tests en vert
  ✅ Cache navigateur vidé (Ctrl+Shift+Delete)
  ✅ Suppression de question fonctionne sans erreur

═══════════════════════════════════════════════════════════════════════════════

Version : v1.9.50 | Octobre 2025

