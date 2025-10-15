<?php
// ======================================================================
// Comparaison des Logiques : Vue Hiérarchique vs Déplacement vers Olution
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
$PAGE->set_url(new moodle_url('/local/question_diagnostic/compare_hierarchy_logic.php'));
$PAGE->set_title('Comparaison des Logiques Hiérarchiques');
$PAGE->set_heading('Comparaison des Logiques : Vue Hiérarchique vs Déplacement vers Olution');

// Activer le mode debug
$CFG->debug = (E_ALL | E_STRICT);
$CFG->debugdisplay = 1;

echo $OUTPUT->header();

echo html_writer::tag('h2', '🔍 Comparaison des Logiques Hiérarchiques');

// Test avec la catégorie Olution (ID: 78)
$course_category_id = 78;
$course_category_name = 'olution';

echo html_writer::start_div('alert alert-info');
echo '<strong>🔍 Test de la catégorie :</strong> ' . $course_category_name . ' (ID: ' . $course_category_id . ')';
echo html_writer::end_div();

echo html_writer::tag('h3', '1. Logique Vue Hiérarchique (categories.php)');

echo html_writer::start_div('alert alert-light');
echo '<strong>📋 Fonction utilisée :</strong> <code>local_question_diagnostic_get_question_categories_by_course_category()</code><br>';
echo '<strong>🎯 Objectif :</strong> Afficher les catégories de questions pour une catégorie de cours<br>';
echo '<strong>🔧 Approche :</strong> Récupère les cours dans la catégorie, puis les contextes, puis les catégories de questions';
echo html_writer::end_div();

try {
    $hierarchy_categories = local_question_diagnostic_get_question_categories_by_course_category($course_category_id);
    
    echo html_writer::start_div('alert alert-success');
    echo '✅ Vue hiérarchique trouve : ' . count($hierarchy_categories) . ' catégories';
    echo html_writer::end_div();
    
    // Afficher quelques exemples
    if (count($hierarchy_categories) > 0) {
        echo html_writer::tag('h5', 'Premières catégories trouvées :');
        echo html_writer::start_tag('ul');
        $count = 0;
        foreach ($hierarchy_categories as $item) {
            if ($count < 10) {
                $cat = $item; // Objet unifié
                echo html_writer::tag('li', 
                    format_string($cat->name) . ' (ID: ' . $cat->id . ', Questions: ' . ($cat->total_questions ?? 0) . ', Contexte: ' . ($cat->context_display_name ?? 'N/A') . ')'
                );
                $count++;
            }
        }
        echo html_writer::end_tag('ul');
    }
    
} catch (Exception $e) {
    echo html_writer::start_div('alert alert-danger');
    echo '❌ Erreur vue hiérarchique : ' . $e->getMessage();
    echo html_writer::end_div();
}

echo html_writer::tag('h3', '2. Logique Déplacement vers Olution (olution_manager.php)');

echo html_writer::start_div('alert alert-light');
echo '<strong>📋 Fonction utilisée :</strong> <code>local_question_diagnostic_find_olution_category()</code><br>';
echo '<strong>🎯 Objectif :</strong> Trouver la catégorie de QUESTIONS "Olution" (système)<br>';
echo '<strong>🔧 Approche :</strong> Recherche directe dans les catégories de questions système';
echo html_writer::end_div();

try {
    $olution_category = local_question_diagnostic_find_olution_category();
    
    if ($olution_category) {
        echo html_writer::start_div('alert alert-success');
        echo '✅ Catégorie Olution trouvée : ' . format_string($olution_category->name) . ' (ID: ' . $olution_category->id . ')';
        echo html_writer::end_div();
        
        // Récupérer les sous-catégories d'Olution
        $olution_subcategories = local_question_diagnostic_get_olution_subcategories();
        
        echo html_writer::start_div('alert alert-info');
        echo '📊 Sous-catégories d\'Olution trouvées : ' . count($olution_subcategories);
        echo html_writer::end_div();
        
        // Afficher quelques exemples
        if (count($olution_subcategories) > 0) {
            echo html_writer::tag('h5', 'Premières sous-catégories d\'Olution :');
            echo html_writer::start_tag('ul');
            $count = 0;
            foreach ($olution_subcategories as $subcat) {
                if ($count < 10) {
                    echo html_writer::tag('li', 
                        format_string($subcat->name) . ' (ID: ' . $subcat->id . ', Parent: ' . $subcat->parent . ')'
                    );
                    $count++;
                }
            }
            echo html_writer::end_tag('ul');
        }
        
    } else {
        echo html_writer::start_div('alert alert-warning');
        echo '⚠️ Catégorie Olution non trouvée';
        echo html_writer::end_div();
    }
    
} catch (Exception $e) {
    echo html_writer::start_div('alert alert-danger');
    echo '❌ Erreur recherche Olution : ' . $e->getMessage();
    echo html_writer::end_div();
}

echo html_writer::tag('h3', '3. Analyse des Différences');

echo html_writer::start_div('alert alert-warning');
echo '<strong>🔍 DIFFÉRENCE CLÉE IDENTIFIÉE :</strong><br><br>';
echo '<strong>Vue Hiérarchique :</strong><br>';
echo '• Cherche les catégories de questions dans les COURS de la catégorie "olution"<br>';
echo '• Utilise <code>local_question_diagnostic_get_courses_in_category_recursive()</code><br>';
echo '• Récupère les contextes de cours et modules<br>';
echo '• Affiche TOUTES les catégories de questions de ces contextes<br><br>';

echo '<strong>Déplacement vers Olution :</strong><br>';
echo '• Cherche la catégorie de QUESTIONS nommée "Olution" (système)<br>';
echo '• Utilise <code>local_question_diagnostic_find_olution_category()</code><br>';
echo '• Recherche directe dans <code>question_categories</code> avec <code>contextid = CONTEXT_SYSTEM</code><br>';
echo '• Trouve la catégorie racine "Olution" et ses sous-catégories<br><br>';

echo '<strong>🚨 PROBLÈME :</strong><br>';
echo 'Les deux logiques cherchent des choses DIFFÉRENTES !<br>';
echo '• Vue hiérarchique = catégories de questions dans les COURS de "olution"<br>';
echo '• Déplacement Olution = catégorie de QUESTIONS "Olution" (système)';
echo html_writer::end_div();

echo html_writer::tag('h3', '4. Vérification de la Correspondance');

echo html_writer::start_div('alert alert-info');
echo '<strong>🧪 Test de correspondance :</strong><br>';

// Vérifier si la catégorie de cours "olution" contient des cours qui ont des catégories de questions "Olution"
try {
    $courses = local_question_diagnostic_get_courses_in_category_recursive($course_category_id);
    echo '• Cours trouvés dans la catégorie "olution" : ' . count($courses) . '<br>';
    
    if (count($courses) > 0) {
        // Vérifier si ces cours ont des catégories de questions nommées "Olution"
        $course_ids = array_keys($courses);
        list($course_ids_sql, $course_params) = $DB->get_in_or_equal($course_ids, SQL_PARAMS_NAMED);
        
        $course_contexts = $DB->get_records_sql(
            "SELECT id FROM {context} WHERE contextlevel = " . CONTEXT_COURSE . " AND instanceid " . $course_ids_sql,
            $course_params
        );
        
        if (!empty($course_contexts)) {
            $context_ids = array_keys($course_contexts);
            list($context_ids_sql, $context_params) = $DB->get_in_or_equal($context_ids, SQL_PARAMS_NAMED);
            
            $olution_in_courses = $DB->get_records_sql(
                "SELECT * FROM {question_categories} WHERE contextid " . $context_ids_sql . " AND name = 'Olution'",
                $context_params
            );
            
            echo '• Catégories de questions "Olution" dans ces cours : ' . count($olution_in_courses) . '<br>';
            
            if (count($olution_in_courses) > 0) {
                echo '• <strong>✅ CORRESPONDANCE TROUVÉE !</strong> Les cours contiennent des catégories "Olution"<br>';
            } else {
                echo '• <strong>❌ AUCUNE CORRESPONDANCE</strong> - Les cours ne contiennent pas de catégories "Olution"<br>';
            }
        }
    }
    
} catch (Exception $e) {
    echo '• ❌ Erreur lors de la vérification : ' . $e->getMessage() . '<br>';
}

echo html_writer::end_div();

echo html_writer::tag('h3', '5. Conclusion et Recommandation');

echo html_writer::start_div('alert alert-success');
echo '<strong>💡 RECOMMANDATION :</strong><br><br>';
echo 'Pour que la vue hiérarchique affiche les catégories "Olution" comme attendu,<br>';
echo 'il faut modifier la logique pour chercher :<br><br>';
echo '1. <strong>La catégorie de QUESTIONS "Olution"</strong> (système)<br>';
echo '2. <strong>Ses sous-catégories</strong> (hiérarchie complète)<br>';
echo '3. <strong>PAS les catégories de questions dans les cours</strong> de la catégorie "olution"<br><br>';
echo 'La vue hiérarchique devrait utiliser la même logique que le déplacement vers Olution !';
echo html_writer::end_div();

echo $OUTPUT->footer();
