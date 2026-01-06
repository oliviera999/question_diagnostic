<?php
// ======================================================================
// Diagnostic approfondi du problème "Olution vide"
// ======================================================================

// Inclure la configuration de Moodle.
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Charger les bibliothèques Moodle nécessaires.
require_login();

// Vérification stricte : seuls les administrateurs du site peuvent accéder à cette page.
if (!is_siteadmin()) {
    throw new \moodle_exception('accessdenied', 'admin', '', 'Vous devez être administrateur du site pour accéder à cet outil.');
}

// Définir le contexte de la page (système).
$context = context_system::instance();

// Définir le titre et l'URL de la page.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/question_diagnostic/debug_olution_empty.php'));
$pagetitle = 'Diagnostic Olution Vide';
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);
$PAGE->set_pagelayout('report');

// ======================================================================
// Section d'en-tête Moodle standard.
echo $OUTPUT->header();

echo html_writer::tag('h1', '🔍 Diagnostic approfondi - Catégorie Olution vide');

global $DB;

// ======================================================================
// ÉTAPE 1 : Vérifier l'existence de la catégorie Olution
// ======================================================================

echo html_writer::tag('h2', '1. Vérification de la catégorie Olution');

$olution_course_category = $DB->get_record('course_categories', ['name' => 'Olution']);

if (!$olution_course_category) {
    // Essayer avec des variantes
    $olution_course_category = $DB->get_record_sql("
        SELECT * FROM {course_categories} 
        WHERE " . $DB->sql_like('name', ':pattern', false, false) . "
        ORDER BY " . $DB->sql_position("'Olution'", 'name') . " ASC
        LIMIT 1
    ", ['pattern' => '%Olution%']);
}

if (!$olution_course_category) {
    echo html_writer::start_div('alert alert-danger');
    echo '<strong>❌ ERREUR :</strong> Aucune catégorie de cours "Olution" trouvée !<br><br>';
    echo 'Voici toutes les catégories de cours disponibles :';
    echo html_writer::end_div();
    
    $all_course_categories = $DB->get_records('course_categories', null, 'name ASC');
    echo html_writer::start_tag('ul');
    foreach ($all_course_categories as $cat) {
        $courses_count = $DB->count_records('course', ['category' => $cat->id]);
        echo html_writer::tag('li', $cat->name . ' (ID: ' . $cat->id . ') - ' . $courses_count . ' cours');
    }
    echo html_writer::end_tag('ul');
    
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::start_div('alert alert-success');
echo '<strong>✅ Catégorie Olution trouvée :</strong><br>';
echo '• ID : ' . $olution_course_category->id . '<br>';
echo '• Nom : ' . $olution_course_category->name . '<br>';
echo '• Description : ' . ($olution_course_category->description ?: 'Aucune') . '<br>';
echo '• Parent : ' . ($olution_course_category->parent ?: 'Aucun (racine)');
echo html_writer::end_div();

// ======================================================================
// ÉTAPE 2 : Vérifier les cours dans Olution
// ======================================================================

echo html_writer::tag('h2', '2. Vérification des cours dans Olution');

$courses = $DB->get_records('course', ['category' => $olution_course_category->id], 'fullname ASC');

echo html_writer::tag('p', '📚 Nombre de cours trouvés : <strong>' . count($courses) . '</strong>');

if (empty($courses)) {
    echo html_writer::start_div('alert alert-warning');
    echo '<strong>⚠️ PROBLÈME IDENTIFIÉ :</strong> Aucun cours dans la catégorie Olution !<br><br>';
    echo 'C\'est pourquoi notre filtre ne trouve aucune catégorie de questions.';
    echo html_writer::end_div();
    
    // Vérifier s'il y a des sous-catégories
    $subcategories = $DB->get_records('course_categories', ['parent' => $olution_course_category->id], 'name ASC');
    if (!empty($subcategories)) {
        echo html_writer::tag('h3', 'Sous-catégories trouvées :');
        echo html_writer::start_tag('ul');
        foreach ($subcategories as $subcat) {
            $subcat_courses = $DB->count_records('course', ['category' => $subcat->id]);
            echo html_writer::tag('li', $subcat->name . ' (ID: ' . $subcat->id . ') - ' . $subcat_courses . ' cours');
        }
        echo html_writer::end_tag('ul');
    }
    
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::start_tag('table', ['class' => 'table table-striped']);
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
echo html_writer::tag('th', 'ID');
echo html_writer::tag('th', 'Nom du cours');
echo html_writer::tag('th', 'Statut');
echo html_writer::tag('th', 'Catégories de questions');
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');

echo html_writer::start_tag('tbody');
foreach ($courses as $course) {
    // Récupérer le contexte du cours
    $course_context = $DB->get_record('context', [
        'contextlevel' => CONTEXT_COURSE,
        'instanceid' => $course->id
    ]);
    
    $question_categories_count = 0;
    if ($course_context) {
        $question_categories_count = $DB->count_records('question_categories', ['contextid' => $course_context->id]);
    }
    
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', $course->id);
    echo html_writer::tag('td', $course->fullname);
    echo html_writer::tag('td', $course->visible ? 'Visible' : 'Caché');
    echo html_writer::tag('td', $question_categories_count);
    echo html_writer::end_tag('tr');
}
echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');

// ======================================================================
// ÉTAPE 3 : Vérifier les contextes
// ======================================================================

echo html_writer::tag('h2', '3. Vérification des contextes');

$course_ids = array_keys($courses);
list($course_ids_sql, $course_params) = $DB->get_in_or_equal($course_ids);

$contexts = $DB->get_records_sql("
    SELECT id, instanceid
    FROM {context}
    WHERE contextlevel = :contextlevel
    AND instanceid " . $course_ids_sql, 
    array_merge(['contextlevel' => CONTEXT_COURSE], $course_params)
);

echo html_writer::tag('p', '🔗 Contextes de cours trouvés : <strong>' . count($contexts) . '</strong>');

if (empty($contexts)) {
    echo html_writer::start_div('alert alert-danger');
    echo '<strong>❌ PROBLÈME CRITIQUE :</strong> Aucun contexte de cours trouvé !<br><br>';
    echo 'Cela peut indiquer un problème avec la structure de la base de données Moodle.';
    echo html_writer::end_div();
} else {
    echo html_writer::start_tag('ul');
    foreach ($contexts as $ctx) {
        $course = $DB->get_record('course', ['id' => $ctx->instanceid]);
        $question_cats = $DB->count_records('question_categories', ['contextid' => $ctx->id]);
        echo html_writer::tag('li', 
            'Contexte ID ' . $ctx->id . ' → ' . ($course ? $course->fullname : 'Cours inconnu') . 
            ' (' . $question_cats . ' catégories de questions)'
        );
    }
    echo html_writer::end_tag('ul');
}

// ======================================================================
// ÉTAPE 4 : Vérifier les catégories de questions
// ======================================================================

echo html_writer::tag('h2', '4. Vérification des catégories de questions');

if (!empty($contexts)) {
    $context_ids = array_keys($contexts);
    list($context_ids_sql, $context_params) = $DB->get_in_or_equal($context_ids);
    
    $question_categories = $DB->get_records_sql("
        SELECT qc.*, ctx.instanceid as course_id
        FROM {question_categories} qc
        INNER JOIN {context} ctx ON ctx.id = qc.contextid
        WHERE qc.contextid " . $context_ids_sql,
        $context_params
    );
    
    echo html_writer::tag('p', '📂 Catégories de questions trouvées : <strong>' . count($question_categories) . '</strong>');
    
    if (empty($question_categories)) {
        echo html_writer::start_div('alert alert-warning');
        echo '<strong>⚠️ PROBLÈME :</strong> Aucune catégorie de questions dans les cours Olution !<br><br>';
        echo 'Cela peut être normal si les cours n\'ont pas encore de questions créées.';
        echo html_writer::end_div();
    } else {
        echo html_writer::start_tag('table', ['class' => 'table table-striped']);
        echo html_writer::start_tag('thead');
        echo html_writer::start_tag('tr');
        echo html_writer::tag('th', 'ID');
        echo html_writer::tag('th', 'Nom');
        echo html_writer::tag('th', 'Cours');
        echo html_writer::tag('th', 'Parent');
        echo html_writer::end_tag('tr');
        echo html_writer::end_tag('thead');
        
        echo html_writer::start_tag('tbody');
        foreach ($question_categories as $cat) {
            $course = $DB->get_record('course', ['id' => $cat->course_id]);
            echo html_writer::start_tag('tr');
            echo html_writer::tag('td', $cat->id);
            echo html_writer::tag('td', $cat->name);
            echo html_writer::tag('td', $course ? $course->fullname : 'Inconnu');
            echo html_writer::tag('td', $cat->parent ?: 'Racine');
            echo html_writer::end_tag('tr');
        }
        echo html_writer::end_tag('tbody');
        echo html_writer::end_tag('table');
    }
}

// ======================================================================
// ÉTAPE 5 : Tester notre fonction
// ======================================================================

echo html_writer::tag('h2', '5. Test de notre fonction');

// Activer le mode debug
$old_debug = $CFG->debug;
$old_debugdisplay = $CFG->debugdisplay;
$CFG->debug = DEBUG_DEVELOPER;
$CFG->debugdisplay = 1;

echo html_writer::start_div('alert alert-info');
echo '<strong>🔍 Mode debug activé</strong> - Les messages de debug seront affichés ci-dessous.';
echo html_writer::end_div();

// Capturer les messages de debug
ob_start();

$result = local_question_diagnostic_get_question_categories_by_course_category($olution_course_category->id);

$debug_output = ob_get_clean();

// Restaurer les paramètres de debug
$CFG->debug = $old_debug;
$CFG->debugdisplay = $old_debugdisplay;

// Afficher les messages de debug
if (!empty($debug_output)) {
    echo html_writer::tag('h3', 'Messages de debug :');
    echo html_writer::start_div('alert alert-secondary');
    echo '<pre>' . htmlspecialchars($debug_output) . '</pre>';
    echo html_writer::end_div();
}

echo html_writer::tag('p', '🔧 Résultat de notre fonction : <strong>' . count($result) . '</strong> catégories');

if (!empty($result)) {
    echo html_writer::start_div('alert alert-success');
    echo '<strong>✅ SUCCÈS :</strong> Notre fonction trouve des catégories !';
    echo html_writer::end_div();
} else {
    echo html_writer::start_div('alert alert-danger');
    echo '<strong>❌ ÉCHEC :</strong> Notre fonction ne trouve aucune catégorie.';
    echo html_writer::end_div();
}

// ======================================================================
// ÉTAPE 6 : Recommandations
// ======================================================================

echo html_writer::tag('h2', '6. Recommandations');

if (empty($courses)) {
    echo html_writer::start_div('alert alert-warning');
    echo '<strong>💡 SOLUTION :</strong><br><br>';
    echo '1. <strong>Créer des cours</strong> dans la catégorie Olution<br>';
    echo '2. <strong>Ou utiliser une autre catégorie</strong> qui contient des cours<br>';
    echo '3. <strong>Ou vérifier</strong> s\'il y a des sous-catégories avec des cours';
    echo html_writer::end_div();
} else if (empty($question_categories)) {
    echo html_writer::start_div('alert alert-info');
    echo '<strong>💡 SOLUTION :</strong><br><br>';
    echo '1. <strong>Créer des questions</strong> dans les cours Olution<br>';
    echo '2. <strong>Ou importer des questions</strong> dans ces cours<br>';
    echo '3. <strong>Ou vérifier</strong> si les questions sont dans d\'autres contextes';
    echo html_writer::end_div();
} else {
    echo html_writer::start_div('alert alert-success');
    echo '<strong>✅ TOUT EST OK :</strong><br><br>';
    echo 'La catégorie Olution contient des cours et des catégories de questions.<br>';
    echo 'Notre fonction devrait fonctionner correctement.';
    echo html_writer::end_div();
}

echo $OUTPUT->footer();
