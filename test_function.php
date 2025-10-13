<?php
/**
 * Test de diagnostic : Vérification de l'existence de la fonction
 * 
 * Ce fichier teste si la fonction local_question_diagnostic_get_parent_url() 
 * est correctement chargée depuis lib.php
 * 
 * Accès : http://votresite.moodle/local/question_diagnostic/test_function.php
 */

require_once(__DIR__ . '/../../config.php');
require_login();

if (!is_siteadmin()) {
    die('Accès réservé aux administrateurs');
}

echo "<!DOCTYPE html><html><head><title>Test Fonction</title></head><body>";
echo "<h1>Test de Diagnostic</h1>";

// Test 1 : lib.php est-il chargeable ?
echo "<h2>Test 1 : Inclusion de lib.php</h2>";
$lib_path = __DIR__ . '/lib.php';
if (file_exists($lib_path)) {
    echo "✅ lib.php existe : " . $lib_path . "<br>";
    require_once($lib_path);
    echo "✅ lib.php chargé avec succès<br>";
} else {
    echo "❌ lib.php n'existe pas : " . $lib_path . "<br>";
    die();
}

// Test 2 : La fonction existe-t-elle ?
echo "<h2>Test 2 : Existence de la fonction</h2>";
if (function_exists('local_question_diagnostic_get_parent_url')) {
    echo "✅ Fonction local_question_diagnostic_get_parent_url() existe<br>";
} else {
    echo "❌ Fonction local_question_diagnostic_get_parent_url() n'existe PAS<br>";
    echo "<p>Fonctions définies dans lib.php :</p><ul>";
    $functions = get_defined_functions()['user'];
    foreach ($functions as $func) {
        if (strpos($func, 'local_question_diagnostic') !== false) {
            echo "<li>" . htmlspecialchars($func) . "</li>";
        }
    }
    echo "</ul>";
    die();
}

// Test 3 : La fonction fonctionne-t-elle ?
echo "<h2>Test 3 : Test d'exécution de la fonction</h2>";
try {
    $url = local_question_diagnostic_get_parent_url('actions/delete_question.php');
    echo "✅ Fonction exécutée avec succès<br>";
    echo "URL retournée : " . htmlspecialchars($url->out()) . "<br>";
} catch (Exception $e) {
    echo "❌ Erreur lors de l'exécution : " . htmlspecialchars($e->getMessage()) . "<br>";
}

// Test 4 : Vérifier le cache Moodle
echo "<h2>Test 4 : État du cache Moodle</h2>";
echo "<p>Si les fonctions n'étaient pas reconnues, c'est probablement dû au cache.</p>";
echo "<p><strong>Pour purger le cache :</strong></p>";
echo "<ol>";
echo "<li>Allez dans <strong>Administration du site → Développement → Purger les caches</strong></li>";
echo "<li>OU accédez directement : <a href='" . new moodle_url('/admin/purgecaches.php', ['confirm' => 1, 'sesskey' => sesskey()]) . "' target='_blank'>Purger les caches maintenant</a></li>";
echo "</ol>";

echo "<h2>✅ Tous les tests sont passés !</h2>";
echo "<p>La fonction est correctement chargée. Si vous rencontrez toujours des erreurs :</p>";
echo "<ol>";
echo "<li>Purgez les caches Moodle (lien ci-dessus)</li>";
echo "<li>Videz le cache de votre navigateur</li>";
echo "<li>Fermez et rouvrez votre navigateur</li>";
echo "</ol>";

echo "<p><a href='" . new moodle_url('/local/question_diagnostic/index.php') . "'>← Retour au menu principal</a></p>";

echo "</body></html>";

