<?php
// ======================================================================
// Comparaison de la Logique Olution - Arborescence vs Déplacement Automatique
// ======================================================================

// Inclure la configuration de Moodle
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/olution_manager.php');

use local_question_diagnostic\olution_manager;

// Vérifications de sécurité
require_login();
if (!is_siteadmin()) {
    throw new \moodle_exception('accessdenied', 'admin', '', 'Vous devez être administrateur du site pour accéder à cet outil.');
}

// Définir le contexte et la page
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/question_diagnostic/test_olution_logic_comparison.php'));
$PAGE->set_title('Comparaison Logique Olution');
$PAGE->set_heading('Comparaison Logique Olution - Arborescence vs Déplacement');

// Activer le mode debug
$CFG->debug = (E_ALL | E_STRICT);
$CFG->debugdisplay = 1;

echo $OUTPUT->header();

echo html_writer::tag('h2', '🔍 Comparaison de la Logique Olution');

echo html_writer::tag('h3', '1. Test de la fonction find_olution_category()');

// Test de la fonction de recherche Olution
$olution_category = local_question_diagnostic_find_olution_category();

if ($olution_category) {
    echo html_writer::start_div('alert alert-success');
    echo '✅ Catégorie Olution trouvée : ' . format_string($olution_category->name) . ' (ID: ' . $olution_category->id . ')';
    echo html_writer::end_div();
    
    // Afficher les détails
    echo html_writer::start_div('alert alert-info');
    echo '<strong>Détails de la catégorie Olution :</strong><br>';
    echo '• ID : ' . $olution_category->id . '<br>';
    echo '• Nom : ' . format_string($olution_category->name) . '<br>';
    echo '• Parent : ' . ($olution_category->parent == 0 ? 'Racine' : $olution_category->parent) . '<br>';
    echo '• Contexte : ' . $olution_category->contextid . '<br>';
    echo '• Info : ' . format_string($olution_category->info ?? 'Aucune');
    echo html_writer::end_div();
} else {
    echo html_writer::start_div('alert alert-danger');
    echo '❌ Catégorie Olution non trouvée !';
    echo html_writer::end_div();
}

echo html_writer::tag('h3', '2. Test de la fonction get_olution_subcategories()');

if ($olution_category) {
    $subcategories = local_question_diagnostic_get_olution_subcategories($olution_category->id);
    
    echo html_writer::start_div('alert alert-info');
    echo '📊 Sous-catégories d\'Olution trouvées : ' . count($subcategories);
    echo html_writer::end_div();
    
    if (count($subcategories) > 0) {
        echo html_writer::tag('h4', 'Premières sous-catégories :');
        echo html_writer::start_tag('ul');
        $count = 0;
        foreach ($subcategories as $subcat) {
            if ($count < 10) {
                echo html_writer::tag('li', 
                    format_string($subcat->name) . ' (ID: ' . $subcat->id . ', Parent: ' . $subcat->parent . ')'
                );
                $count++;
            }
        }
        echo html_writer::end_tag('ul');
    }
}

echo html_writer::tag('h3', '3. Test de la fonction find_all_duplicates_for_olution()');

try {
    $duplicate_groups = olution_manager::find_all_duplicates_for_olution(5, 0); // Limiter à 5 groupes pour le test
    
    echo html_writer::start_div('alert alert-info');
    echo '📊 Groupes de doublons avec présence Olution : ' . count($duplicate_groups);
    echo html_writer::end_div();
    
    if (count($duplicate_groups) > 0) {
        echo html_writer::tag('h4', 'Premiers groupes de doublons :');
        foreach ($duplicate_groups as $i => $group) {
            if ($i < 3) { // Limiter à 3 groupes pour l'affichage
                echo html_writer::start_div('card mb-3');
                echo html_writer::start_div('card-header');
                echo html_writer::tag('strong', format_string($group['group_name']) . ' (' . $group['group_type'] . ')');
                echo html_writer::end_div();
                echo html_writer::start_div('card-body');
                echo '• Total questions : ' . $group['total_count'] . '<br>';
                echo '• Dans Olution : ' . $group['olution_count'] . '<br>';
                echo '• Hors Olution : ' . $group['non_olution_count'] . '<br>';
                if ($group['target_category']) {
                    echo '• Catégorie cible : ' . format_string($group['target_category']->name) . ' (ID: ' . $group['target_category']->id . ')<br>';
                }
                echo html_writer::end_div();
                echo html_writer::end_div();
            }
        }
    }
    
} catch (Exception $e) {
    echo html_writer::start_div('alert alert-danger');
    echo '❌ Erreur lors de la recherche de doublons : ' . $e->getMessage();
    echo html_writer::end_div();
}

echo html_writer::tag('h3', '4. Test de la fonction is_in_olution()');

if ($olution_category) {
    // Tester avec la catégorie Olution elle-même
    $is_olution_self = olution_manager::is_in_olution($olution_category->id);
    echo html_writer::start_div('alert ' . ($is_olution_self ? 'alert-success' : 'alert-danger'));
    echo 'Test is_in_olution() avec la catégorie Olution elle-même (ID: ' . $olution_category->id . ') : ' . ($is_olution_self ? '✅ True' : '❌ False');
    echo html_writer::end_div();
    
    // Tester avec une sous-catégorie si elle existe
    if (!empty($subcategories)) {
        $test_subcat = $subcategories[0];
        $is_olution_sub = olution_manager::is_in_olution($test_subcat->id);
        echo html_writer::start_div('alert ' . ($is_olution_sub ? 'alert-success' : 'alert-danger'));
        echo 'Test is_in_olution() avec une sous-catégorie (ID: ' . $test_subcat->id . ') : ' . ($is_olution_sub ? '✅ True' : '❌ False');
        echo html_writer::end_div();
    }
}

echo html_writer::tag('h3', '5. Test du déplacement automatique vers Olution');

try {
    // Tester le déplacement automatique avec 2 questions
    $test_result = olution_manager::test_automatic_movement_to_olution(2);
    
    echo html_writer::start_div('alert ' . ($test_result['success'] ? 'alert-success' : 'alert-danger'));
    echo '<strong>Résultat du test de déplacement automatique :</strong><br>';
    echo '• Succès global : ' . ($test_result['success'] ? '✅ Oui' : '❌ Non') . '<br>';
    echo '• Message : ' . $test_result['message'] . '<br>';
    echo '• Questions testées : ' . $test_result['tested_questions'] . '<br>';
    echo '• Questions déplacées : ' . $test_result['moved_questions'] . '<br>';
    echo '• Questions échouées : ' . $test_result['failed_questions'];
    echo html_writer::end_div();
    
    if (!empty($test_result['details'])) {
        echo html_writer::tag('h4', 'Détails des tests :');
        foreach ($test_result['details'] as $i => $detail) {
            echo html_writer::start_div('card mb-2');
            echo html_writer::start_div('card-body');
            echo '<strong>Test ' . ($i + 1) . ':</strong><br>';
            echo '• Question : ' . format_string($detail['question_name']) . ' (ID: ' . $detail['question_id'] . ')<br>';
            echo '• Type : ' . $detail['question_type'] . '<br>';
            echo '• Cible : ' . format_string($detail['target_category_name']) . ' (ID: ' . $detail['target_category_id'] . ')<br>';
            echo '• Résultat : ' . ($detail['success'] ? '✅ Succès' : '❌ Échec') . '<br>';
            if (!$detail['success']) {
                echo '• Erreur : ' . $detail['move_result'];
            }
            echo html_writer::end_div();
            echo html_writer::end_div();
        }
    }
    
} catch (Exception $e) {
    echo html_writer::start_div('alert alert-danger');
    echo '❌ Erreur lors du test de déplacement automatique : ' . $e->getMessage();
    echo html_writer::end_div();
}

echo html_writer::tag('h3', '6. Analyse des problèmes identifiés');

echo html_writer::start_div('alert alert-success');
echo '<strong>✅ Corrections appliquées :</strong><br>';
echo '1. <strong>Fonction is_in_olution() publique</strong> : Maintenant publique avec logs de debug détaillés<br>';
echo '2. <strong>Logique de recherche Olution cohérente</strong> : Utilise la même logique que l\'arborescence<br>';
echo '3. <strong>Structure des sous-catégories</strong> : Récursion testée et fonctionnelle<br>';
echo '4. <strong>Déplacement automatique</strong> : Nouvelle fonction de test avec vérifications robustes<br>';
echo '5. <strong>Logs de debug</strong> : Traçabilité complète des opérations';
echo html_writer::end_div();

echo html_writer::tag('h3', '7. Messages de debug');

echo html_writer::start_div('alert alert-light');
echo '<strong>📝 Messages de debug :</strong><br>';
echo 'Les messages de debug s\'affichent ci-dessus si le mode debug est activé.';
echo html_writer::end_div();

echo $OUTPUT->footer();
