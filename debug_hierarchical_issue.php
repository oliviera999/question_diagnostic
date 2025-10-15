<?php
// ======================================================================
// Diagnostic de la Vue Hiérarchique - Problème "Aucune catégorie trouvée"
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
$PAGE->set_url(new moodle_url('/local/question_diagnostic/debug_hierarchical_issue.php'));
$PAGE->set_title('Diagnostic Vue Hiérarchique');
$PAGE->set_heading('Diagnostic Vue Hiérarchique - Problème "Aucune catégorie trouvée"');

// Activer le mode debug
$CFG->debug = (E_ALL | E_STRICT);
$CFG->debugdisplay = 1;

echo $OUTPUT->header();

echo html_writer::tag('h2', '🔍 Diagnostic de la Vue Hiérarchique');

// Test avec la catégorie Olution (ID: 78)
$course_category_id = 78;
$course_category_name = 'olution';

echo html_writer::start_div('alert alert-info');
echo '<strong>🔍 Test de la catégorie :</strong> ' . $course_category_name . ' (ID: ' . $course_category_id . ')';
echo html_writer::end_div();

echo html_writer::tag('h3', '1. Comparaison des deux fonctions');

// Test de l'ancienne fonction (qui fonctionne)
echo html_writer::tag('h4', 'Ancienne fonction (qui fonctionne)');
$old_categories = local_question_diagnostic_get_question_categories_by_course_category($course_category_id);
echo html_writer::start_div('alert alert-success');
echo '✅ Ancienne fonction trouve : ' . count($old_categories) . ' catégories';
echo html_writer::end_div();

// Test de la nouvelle fonction (qui ne fonctionne pas)
echo html_writer::tag('h4', 'Nouvelle fonction (qui ne fonctionne pas)');
$new_hierarchy = local_question_diagnostic_get_question_categories_hierarchy($course_category_id);
echo html_writer::start_div('alert alert-warning');
echo '⚠️ Nouvelle fonction trouve : ' . count($new_hierarchy) . ' catégories racines';
echo html_writer::end_div();

echo html_writer::tag('h3', '2. Analyse des cours récupérés');

// Vérifier les cours récupérés
$courses = local_question_diagnostic_get_courses_in_category_recursive($course_category_id);
echo html_writer::start_div('alert alert-info');
echo '📚 Cours trouvés : ' . count($courses) . ' cours';
echo html_writer::end_div();

if (count($courses) > 0) {
    echo html_writer::start_div('row');
    echo html_writer::start_div('col-md-6');
    echo html_writer::tag('h5', 'Premiers cours trouvés :');
    echo html_writer::start_tag('ul');
    $count = 0;
    foreach ($courses as $course) {
        if ($count < 5) {
            echo html_writer::tag('li', $course->fullname . ' (ID: ' . $course->id . ')');
            $count++;
        }
    }
    echo html_writer::end_tag('ul');
    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo html_writer::tag('h3', '3. Test de la requête SQL');

// Test direct de la requête SQL
global $DB;

try {
    $course_ids = array_keys($courses);
    if (!empty($course_ids)) {
        list($course_ids_sql, $course_params) = $DB->get_in_or_equal($course_ids, SQL_PARAMS_NAMED);
        
        // Test des contextes de cours
        $course_contexts = $DB->get_records_sql(
            "SELECT id, contextlevel, instanceid FROM {context} 
             WHERE contextlevel = " . CONTEXT_COURSE . " 
             AND instanceid " . $course_ids_sql,
            $course_params
        );
        
        echo html_writer::start_div('alert alert-info');
        echo '📚 Contextes de cours trouvés : ' . count($course_contexts);
        echo html_writer::end_div();
        
        // Test des contextes de modules
        $module_contexts = $DB->get_records_sql(
            "SELECT c.id, c.contextlevel, c.instanceid, m.name as modulename, co.fullname as coursename
             FROM {context} c
             INNER JOIN {course_modules} cm ON cm.id = c.instanceid
             INNER JOIN {modules} m ON m.id = cm.module
             INNER JOIN {course} co ON co.id = cm.course
             WHERE c.contextlevel = " . CONTEXT_MODULE . " 
             AND cm.course " . $course_ids_sql,
            $course_params
        );
        
        echo html_writer::start_div('alert alert-info');
        echo '📝 Contextes de modules trouvés : ' . count($module_contexts);
        echo html_writer::end_div();
        
        // Test du contexte système
        $system_context = $DB->get_record('context', ['contextlevel' => CONTEXT_SYSTEM]);
        echo html_writer::start_div('alert alert-info');
        echo '🌐 Contexte système trouvé : ' . ($system_context ? 'Oui (ID: ' . $system_context->id . ')' : 'Non');
        echo html_writer::end_div();
        
        // Test de la requête principale
        $all_context_ids = array_merge(
            array_keys($course_contexts),
            array_keys($module_contexts),
            $system_context ? [$system_context->id] : []
        );
        
        if (!empty($all_context_ids)) {
            list($all_context_ids_sql, $all_context_params) = $DB->get_in_or_equal($all_context_ids, SQL_PARAMS_NAMED);
            
            $categories = $DB->get_records_sql(
                "SELECT qc.*, ctx.contextlevel, ctx.instanceid
                 FROM {question_categories} qc
                 INNER JOIN {context} ctx ON ctx.id = qc.contextid
                 WHERE qc.contextid " . $all_context_ids_sql . "
                 ORDER BY qc.parent, qc.sortorder, qc.name",
                $all_context_params
            );
            
            echo html_writer::start_div('alert alert-success');
            echo '✅ Catégories de questions trouvées : ' . count($categories);
            echo html_writer::end_div();
            
            // Test de la construction de la hiérarchie
            $hierarchy = local_question_diagnostic_build_category_hierarchy($categories);
            echo html_writer::start_div('alert alert-info');
            echo '🌳 Catégories racines dans la hiérarchie : ' . count($hierarchy);
            echo html_writer::end_div();
            
        } else {
            echo html_writer::start_div('alert alert-danger');
            echo '❌ Aucun contexte trouvé !';
            echo html_writer::end_div();
        }
        
    } else {
        echo html_writer::start_div('alert alert-danger');
        echo '❌ Aucun cours trouvé !';
        echo html_writer::end_div();
    }
    
} catch (Exception $e) {
    echo html_writer::start_div('alert alert-danger');
    echo '❌ Erreur SQL : ' . $e->getMessage();
    echo html_writer::end_div();
}

echo html_writer::tag('h3', '4. Messages de debug');

echo html_writer::start_div('alert alert-light');
echo '<strong>📝 Messages de debug :</strong><br>';
echo 'Les messages de debug s\'affichent ci-dessus si le mode debug est activé.';
echo html_writer::end_div();

echo $OUTPUT->footer();
