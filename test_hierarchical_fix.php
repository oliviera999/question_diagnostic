<?php
// ======================================================================
// Test de la Correction de la Vue Hiérarchique (v1.11.12)
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
$PAGE->set_url(new moodle_url('/local/question_diagnostic/test_hierarchical_fix.php'));
$PAGE->set_title('Test Correction Vue Hiérarchique v1.11.12');
$PAGE->set_heading('Test Correction Vue Hiérarchique');

// Activer le mode debug
$CFG->debug = (E_ALL | E_STRICT);
$CFG->debugdisplay = 1;

echo $OUTPUT->header();

echo html_writer::tag('h2', '🔧 Test de la Correction de la Vue Hiérarchique (v1.11.12)');

// Test avec la catégorie Olution (ID: 78)
$course_category_id = 78;
$course_category_name = 'olution';

echo html_writer::start_div('alert alert-info');
echo '<strong>🔍 Test de la catégorie :</strong> ' . $course_category_name . ' (ID: ' . $course_category_id . ')';
echo html_writer::end_div();

echo html_writer::tag('h3', '1. Test de la fonction corrigée');

try {
    $hierarchy = local_question_diagnostic_get_question_categories_hierarchy($course_category_id);
    
    if (empty($hierarchy)) {
        echo html_writer::start_div('alert alert-warning');
        echo '⚠️ Aucune catégorie trouvée dans la hiérarchie.';
        echo html_writer::end_div();
    } else {
        echo html_writer::start_div('alert alert-success');
        echo '✅ Hiérarchie récupérée avec succès !';
        echo html_writer::end_div();
        
        // Afficher les statistiques
        function count_categories_recursive($hierarchy) {
            $count = 0;
            $questions = 0;
            foreach ($hierarchy as $category) {
                $count++;
                $questions += (int)($category->total_questions ?? 0);
                if (!empty($category->children)) {
                    $child_stats = count_categories_recursive($category->children);
                    $count += $child_stats['categories'];
                    $questions += $child_stats['questions'];
                }
            }
            return ['categories' => $count, 'questions' => $questions];
        }
        
        $stats = count_categories_recursive($hierarchy);
        
        echo html_writer::start_div('row');
        echo html_writer::start_div('col-md-6');
        echo html_writer::tag('div', '📊 <strong>Catégories trouvées :</strong> ' . $stats['categories'], ['class' => 'alert alert-primary']);
        echo html_writer::end_div();
        echo html_writer::start_div('col-md-6');
        echo html_writer::tag('div', '❓ <strong>Questions totales :</strong> ' . $stats['questions'], ['class' => 'alert alert-success']);
        echo html_writer::end_div();
        echo html_writer::end_div();
        
        // Afficher les premières catégories racines
        echo html_writer::tag('h4', 'Premières catégories racines :');
        echo html_writer::start_tag('ul');
        $count = 0;
        foreach ($hierarchy as $category) {
            if ($count < 10) {
                $children_count = !empty($category->children) ? count($category->children) : 0;
                echo html_writer::tag('li', 
                    format_string($category->name) . ' (' . ($category->total_questions ?? 0) . ' questions, ' . $children_count . ' enfants)'
                );
                $count++;
            }
        }
        echo html_writer::end_tag('ul');
    }
    
} catch (Exception $e) {
    echo html_writer::start_div('alert alert-danger');
    echo '❌ Erreur lors de la récupération de la hiérarchie : ' . $e->getMessage();
    echo html_writer::end_div();
}

echo html_writer::tag('h3', '2. Rendu hiérarchique');

if (!empty($hierarchy)) {
    echo html_writer::start_div('qd-hierarchy-container', [
        'style' => 'background: #ffffff; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin: 20px 0;'
    ]);
    
    echo local_question_diagnostic_render_category_hierarchy($hierarchy);
    
    echo html_writer::end_div();
} else {
    echo html_writer::start_div('alert alert-warning');
    echo 'Aucune hiérarchie à afficher.';
    echo html_writer::end_div();
}

echo html_writer::tag('h3', '3. Test de l\'interface utilisateur');

echo html_writer::start_div('alert alert-info');
echo '<strong>🧪 Instructions de test :</strong><br>';
echo '1. Allez sur la page <a href="categories.php">Gestion des catégories</a><br>';
echo '2. Sélectionnez la catégorie de cours "olution"<br>';
echo '3. Cliquez sur "Mode banque (liste)"<br>';
echo '4. Vérifiez que les catégories s\'affichent maintenant en arbre hiérarchique<br>';
echo '5. Testez les boutons "Purge this category"';
echo html_writer::end_div();

echo html_writer::tag('h3', '4. Résumé de la correction');

echo html_writer::start_div('alert alert-success');
echo '<strong>✅ CORRECTION APPLIQUÉE :</strong><br>';
echo '• La fonction <code>local_question_diagnostic_get_question_categories_hierarchy()</code> utilise maintenant<br>';
echo '• la fonction existante <code>local_question_diagnostic_get_question_categories_by_course_category()</code><br>';
echo '• qui fonctionne déjà correctement et récupère toutes les catégories<br>';
echo '• Évite les problèmes SQL complexes et les jointures fragiles<br>';
echo '• Garantit la compatibilité avec tous les SGBD';
echo html_writer::end_div();

echo $OUTPUT->footer();
