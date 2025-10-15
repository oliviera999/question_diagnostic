<?php
// ======================================================================
// Test Simple de Création de la Catégorie Olution (v1.11.14)
// ======================================================================

// Inclure la configuration de Moodle
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Vérifications de sécurité
require_login();
if (!is_siteadmin()) {
    print_error('accesinterdit', 'admin', '', 'Vous devez être administrateur du site pour accéder à cet outil.');
    exit;
}

// Définir le contexte et la page
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/question_diagnostic/test_simple_olution_creation.php'));
$PAGE->set_title('Test Simple Création Olution v1.11.14');
$PAGE->set_heading('Test Simple de Création de la Catégorie Olution');

echo $OUTPUT->header();

echo html_writer::tag('h2', '🆕 Test Simple de Création de la Catégorie Olution (v1.11.14)');

echo html_writer::start_div('alert alert-info');
echo '<strong>🎯 Objectif :</strong> Test rapide de la création automatique de la catégorie Olution.';
echo html_writer::end_div();

echo html_writer::tag('h3', '1. Test de la fonction find_olution_category()');

try {
    $olution_category = local_question_diagnostic_find_olution_category();
    
    if ($olution_category) {
        echo html_writer::start_div('alert alert-success');
        echo '✅ <strong>SUCCÈS :</strong> Catégorie Olution trouvée ou créée automatiquement !<br>';
        echo '• <strong>ID:</strong> ' . $olution_category->id . '<br>';
        echo '• <strong>Nom:</strong> ' . format_string($olution_category->name) . '<br>';
        echo '• <strong>Parent:</strong> ' . $olution_category->parent . '<br>';
        echo '• <strong>Info:</strong> ' . format_string($olution_category->info ?? 'Aucune');
        echo html_writer::end_div();
        
        // Test de la fonction is_in_olution
        require_once(__DIR__ . '/classes/olution_manager.php');
        $is_in_olution = local_question_diagnostic\olution_manager::is_in_olution($olution_category->id);
        
        echo html_writer::start_div('alert ' . ($is_in_olution ? 'alert-success' : 'alert-danger'));
        echo 'Test is_in_olution() avec la catégorie (ID: ' . $olution_category->id . ') : ' . ($is_in_olution ? '✅ True' : '❌ False');
        echo html_writer::end_div();
        
    } else {
        echo html_writer::start_div('alert alert-danger');
        echo '❌ <strong>ÉCHEC :</strong> Aucune catégorie Olution trouvée ou créée.';
        echo html_writer::end_div();
    }
    
} catch (Exception $e) {
    echo html_writer::start_div('alert alert-danger');
    echo '❌ <strong>ERREUR :</strong> ' . $e->getMessage();
    echo html_writer::end_div();
}

echo html_writer::tag('h3', '2. Instructions');

echo html_writer::start_div('alert alert-info');
echo '<strong>📋 Instructions :</strong><br>';
echo '1. <strong>Si le test est réussi :</strong> La catégorie Olution existe maintenant et le déplacement automatique devrait fonctionner<br>';
echo '2. <strong>Pour tester le déplacement :</strong> Allez sur la page des doublons Olution<br>';
echo '3. <strong>Pour vérifier dans Moodle :</strong> Consultez Administration > Questions > Catégories';
echo html_writer::end_div();

echo $OUTPUT->footer();
