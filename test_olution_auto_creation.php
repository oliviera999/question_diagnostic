<?php
// ======================================================================
// Test de Création Automatique de la Catégorie Olution (v1.11.14)
// ======================================================================

// Inclure la configuration de Moodle
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Vérifications de sécurité
require_login();
if (!is_siteadmin()) {
    throw new \moodle_exception('accessdenied', 'admin', '', 'Vous devez être administrateur du site pour accéder à cet outil.');
}

// Définir le contexte et la page
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/question_diagnostic/test_olution_auto_creation.php'));
$PAGE->set_title('Test Création Automatique Olution v1.11.14');
$PAGE->set_heading('Test Création Automatique de la Catégorie Olution');

// Activer le mode debug
$CFG->debug = (E_ALL | E_STRICT);
$CFG->debugdisplay = 1;

echo $OUTPUT->header();

echo html_writer::tag('h2', '🆕 Test de Création Automatique de la Catégorie Olution (v1.11.14)');

echo html_writer::start_div('alert alert-info');
echo '<strong>🎯 Objectif :</strong> Tester la création automatique de la catégorie Olution au niveau système si elle n\'existe pas.';
echo html_writer::end_div();

echo html_writer::tag('h3', '1. Vérification de l\'état actuel');

// Vérifier si la catégorie Olution existe déjà
$olution_category = local_question_diagnostic_find_olution_category();

if ($olution_category) {
    echo html_writer::start_div('alert alert-success');
    echo '✅ Catégorie Olution existe déjà :<br>';
    echo '• <strong>ID:</strong> ' . $olution_category->id . '<br>';
    echo '• <strong>Nom:</strong> ' . format_string($olution_category->name) . '<br>';
    echo '• <strong>Parent:</strong> ' . $olution_category->parent . '<br>';
    echo '• <strong>Info:</strong> ' . format_string($olution_category->info ?? 'Aucune') . '<br>';
    echo '• <strong>Contexte:</strong> ' . $olution_category->contextid;
    echo html_writer::end_div();
} else {
    echo html_writer::start_div('alert alert-warning');
    echo '⚠️ Aucune catégorie Olution trouvée. Le système va tenter de la créer automatiquement.';
    echo html_writer::end_div();
}

echo html_writer::tag('h3', '2. Test de création automatique');

try {
    // Tenter de créer la catégorie Olution
    $created_category = local_question_diagnostic_create_olution_category();
    
    if ($created_category) {
        echo html_writer::start_div('alert alert-success');
        echo '✅ Catégorie Olution créée avec succès :<br>';
        echo '• <strong>ID:</strong> ' . $created_category->id . '<br>';
        echo '• <strong>Nom:</strong> ' . format_string($created_category->name) . '<br>';
        echo '• <strong>Parent:</strong> ' . $created_category->parent . '<br>';
        echo '• <strong>Info:</strong> ' . format_string($created_category->info ?? 'Aucune') . '<br>';
        echo '• <strong>Contexte:</strong> ' . $created_category->contextid . '<br>';
        echo '• <strong>Sort Order:</strong> ' . ($created_category->sortorder ?? 'Non défini');
        echo html_writer::end_div();
        
        echo html_writer::start_div('alert alert-info');
        echo '<strong>💡 Information :</strong> La catégorie a été créée automatiquement avec :<br>';
        echo '• Nom : "Olution"<br>';
        echo '• Description : "Catégorie système pour les questions partagées Olution"<br>';
        echo '• Contexte : Système<br>';
        echo '• Parent : Racine (0)<br>';
        echo '• Sort Order : 999 (à la fin)';
        echo html_writer::end_div();
        
    } else {
        echo html_writer::start_div('alert alert-danger');
        echo '❌ Échec de la création de la catégorie Olution. Vérifiez les logs de debug ci-dessus.';
        echo html_writer::end_div();
    }
    
} catch (Exception $e) {
    echo html_writer::start_div('alert alert-danger');
    echo '❌ Erreur lors de la création de la catégorie Olution : ' . $e->getMessage();
    echo html_writer::end_div();
}

echo html_writer::tag('h3', '3. Vérification post-création');

// Vérifier à nouveau si la catégorie existe
$olution_category_after = local_question_diagnostic_find_olution_category();

if ($olution_category_after) {
    echo html_writer::start_div('alert alert-success');
    echo '✅ Catégorie Olution trouvée après création :<br>';
    echo '• <strong>ID:</strong> ' . $olution_category_after->id . '<br>';
    echo '• <strong>Nom:</strong> ' . format_string($olution_category_after->name) . '<br>';
    echo '• <strong>Parent:</strong> ' . $olution_category_after->parent . '<br>';
    echo '• <strong>Info:</strong> ' . format_string($olution_category_after->info ?? 'Aucune');
    echo html_writer::end_div();
    
    // Test de la fonction is_in_olution
    require_once(__DIR__ . '/classes/olution_manager.php');
    $is_in_olution = local_question_diagnostic\olution_manager::is_in_olution($olution_category_after->id);
    
    echo html_writer::start_div('alert ' . ($is_in_olution ? 'alert-success' : 'alert-danger'));
    echo 'Test is_in_olution() avec la catégorie créée (ID: ' . $olution_category_after->id . ') : ' . ($is_in_olution ? '✅ True' : '❌ False');
    echo html_writer::end_div();
    
} else {
    echo html_writer::start_div('alert alert-danger');
    echo '❌ La catégorie Olution n\'est toujours pas trouvée après la tentative de création.';
    echo html_writer::end_div();
}

echo html_writer::tag('h3', '4. Test du déplacement automatique');

if ($olution_category_after) {
    echo html_writer::start_div('alert alert-info');
    echo '<strong>🧪 Test du déplacement automatique :</strong><br>';
    echo 'Maintenant que la catégorie Olution existe, vous pouvez tester le déplacement automatique des questions.';
    echo html_writer::end_div();
    
    try {
        // Test avec 1 question seulement pour éviter les problèmes
        $test_result = local_question_diagnostic\olution_manager::test_automatic_movement_to_olution(1);
        
        if ($test_result['success']) {
            echo html_writer::start_div('alert alert-success');
            echo '✅ Test de déplacement automatique réussi :<br>';
            echo '• Questions testées : ' . $test_result['tested_questions'] . '<br>';
            echo '• Questions déplacées : ' . $test_result['moved_questions'] . '<br>';
            echo '• Questions échouées : ' . $test_result['failed_questions'] . '<br>';
            echo '• Message : ' . $test_result['message'];
            echo html_writer::end_div();
        } else {
            echo html_writer::start_div('alert alert-warning');
            echo '⚠️ Test de déplacement automatique partiellement réussi :<br>';
            echo '• Questions testées : ' . $test_result['tested_questions'] . '<br>';
            echo '• Questions déplacées : ' . $test_result['moved_questions'] . '<br>';
            echo '• Questions échouées : ' . $test_result['failed_questions'] . '<br>';
            echo '• Message : ' . $test_result['message'];
            echo html_writer::end_div();
        }
        
    } catch (Exception $e) {
        echo html_writer::start_div('alert alert-danger');
        echo '❌ Erreur lors du test de déplacement automatique : ' . $e->getMessage();
        echo html_writer::end_div();
    }
}

echo html_writer::tag('h3', '5. Instructions pour l\'utilisateur');

echo html_writer::start_div('alert alert-info');
echo '<strong>📋 Instructions :</strong><br>';
echo '1. <strong>Si la catégorie a été créée :</strong> Le système de déplacement automatique vers Olution devrait maintenant fonctionner<br>';
echo '2. <strong>Pour tester :</strong> Allez sur la page des doublons Olution et testez le déplacement automatique<br>';
echo '3. <strong>Pour vérifier :</strong> Consultez la banque de questions Moodle pour voir la nouvelle catégorie "Olution"<br>';
echo '4. <strong>Pour créer des sous-catégories :</strong> Vous pouvez maintenant créer des sous-catégories dans "Olution" pour organiser vos questions';
echo html_writer::end_div();

echo html_writer::tag('h3', '6. Messages de debug');

echo html_writer::start_div('alert alert-light');
echo '<strong>📝 Messages de debug :</strong><br>';
echo 'Les messages de debug s\'affichent ci-dessus si le mode debug est activé.';
echo html_writer::end_div();

echo $OUTPUT->footer();
