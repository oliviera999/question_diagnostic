<?php
// ======================================================================
// Diagnostic du problème de filtrage par catégorie de cours
// ======================================================================

// Inclure la configuration de Moodle.
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Charger les bibliothèques Moodle nécessaires.
require_login();

// Vérification stricte : seuls les administrateurs du site peuvent accéder à cette page.
if (!is_siteadmin()) {
    throw new \moodle_exception('accesinterdit', 'admin', '', 'Vous devez être administrateur du site pour accéder à cet outil.');
    exit;
}

// Définir le contexte de la page (système).
$context = context_system::instance();

// Définir le titre et l'URL de la page.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/question_diagnostic/diagnose_course_category_issue.php'));
$pagetitle = 'Diagnostic - Problème de filtrage par catégorie de cours';
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);
$PAGE->set_pagelayout('report');

// ======================================================================
// Section d'en-tête Moodle standard.
echo $OUTPUT->header();

echo html_writer::tag('h1', '🔍 Diagnostic du problème de filtrage par catégorie de cours');

// ======================================================================
// ÉTAPE 1 : Trouver la catégorie de cours "Olution"
// ======================================================================

echo html_writer::tag('h2', '1. Recherche de la catégorie de cours "Olution"');

global $DB;

// Rechercher la catégorie de cours Olution
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

if ($olution_course_category) {
    echo html_writer::tag('p', '✅ Catégorie de cours trouvée : <strong>' . $olution_course_category->name . '</strong> (ID: ' . $olution_course_category->id . ')');
    
    // Compter les cours dans cette catégorie
    $courses_count = $DB->count_records('course', ['category' => $olution_course_category->id]);
    echo html_writer::tag('p', '📚 Nombre de cours dans cette catégorie : <strong>' . $courses_count . '</strong>');
    
    if ($courses_count > 0) {
        // Lister les cours
        $courses = $DB->get_records('course', ['category' => $olution_course_category->id], 'fullname ASC');
        echo html_writer::tag('h3', 'Cours dans la catégorie Olution :');
        echo html_writer::start_tag('ul');
        foreach ($courses as $course) {
            echo html_writer::tag('li', $course->fullname . ' (ID: ' . $course->id . ')');
        }
        echo html_writer::end_tag('ul');
    }
} else {
    echo html_writer::tag('p', '❌ Aucune catégorie de cours "Olution" trouvée.');
    echo html_writer::tag('p', 'Voici toutes les catégories de cours disponibles :');
    
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

// ======================================================================
// ÉTAPE 2 : Analyser les contextes des cours Olution
// ======================================================================

echo html_writer::tag('h2', '2. Analyse des contextes des cours Olution');

$courses = $DB->get_records('course', ['category' => $olution_course_category->id], 'fullname ASC');

foreach ($courses as $course) {
    echo html_writer::tag('h3', 'Cours : ' . $course->fullname . ' (ID: ' . $course->id . ')');
    
    // Récupérer le contexte du cours
    $course_context = $DB->get_record('context', [
        'contextlevel' => CONTEXT_COURSE,
        'instanceid' => $course->id
    ]);
    
    if ($course_context) {
        echo html_writer::tag('p', '✅ Contexte trouvé : ID ' . $course_context->id);
        
        // Récupérer les catégories de questions dans ce contexte
        $question_categories = $DB->get_records('question_categories', ['contextid' => $course_context->id], 'name ASC');
        
        echo html_writer::tag('p', '📂 Catégories de questions trouvées : <strong>' . count($question_categories) . '</strong>');
        
        if (!empty($question_categories)) {
            echo html_writer::start_tag('ul');
            foreach ($question_categories as $qcat) {
                // Compter les questions dans cette catégorie
                $questions_sql = "SELECT COUNT(DISTINCT q.id) as total_questions
                                  FROM {question_bank_entries} qbe
                                  INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                                  INNER JOIN {question} q ON q.id = qv.questionid
                                  WHERE qbe.questioncategoryid = :categoryid";
                
                $question_count = $DB->get_record_sql($questions_sql, ['categoryid' => $qcat->id]);
                $count = $question_count ? $question_count->total_questions : 0;
                
                echo html_writer::tag('li', $qcat->name . ' (ID: ' . $qcat->id . ') - ' . $count . ' questions');
            }
            echo html_writer::end_tag('ul');
        }
    } else {
        echo html_writer::tag('p', '❌ Aucun contexte trouvé pour ce cours');
    }
    
    echo html_writer::tag('hr', '');
}

// ======================================================================
// ÉTAPE 3 : Tester notre fonction actuelle
// ======================================================================

echo html_writer::tag('h2', '3. Test de notre fonction actuelle');

$question_categories = local_question_diagnostic_get_question_categories_by_course_category($olution_course_category->id);

echo html_writer::tag('p', '🔧 Résultat de notre fonction : <strong>' . count($question_categories) . '</strong> catégories trouvées');

if (!empty($question_categories)) {
    echo html_writer::start_tag('table', ['class' => 'table table-striped']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'ID');
    echo html_writer::tag('th', 'Nom');
    echo html_writer::tag('th', 'Cours');
    echo html_writer::tag('th', 'Questions');
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    
    echo html_writer::start_tag('tbody');
    foreach ($question_categories as $qcat) {
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', $qcat->id);
        echo html_writer::tag('td', $qcat->name);
        echo html_writer::tag('td', $qcat->course_name);
        echo html_writer::tag('td', $qcat->total_questions);
        echo html_writer::end_tag('tr');
    }
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
} else {
    echo html_writer::tag('p', '❌ Aucune catégorie trouvée par notre fonction');
}

// ======================================================================
// ÉTAPE 4 : Analyser la différence avec la banque de questions Moodle
// ======================================================================

echo html_writer::tag('h2', '4. Analyse de la différence avec la banque de questions Moodle');

echo html_writer::start_div('alert alert-info');
echo '<strong>💡 Explication du problème :</strong><br>';
echo 'La banque de questions Moodle peut afficher des catégories de différents contextes :<br>';
echo '<ul>';
echo '<li><strong>Contexte système</strong> : Catégories globales accessibles partout</li>';
echo '<li><strong>Contexte de cours</strong> : Catégories spécifiques à un cours</li>';
echo '<li><strong>Contexte de module</strong> : Catégories spécifiques à une activité</li>';
echo '</ul>';
echo 'Notre fonction actuelle ne récupère que les catégories des cours dans la catégorie de cours sélectionnée, ';
echo 'mais la banque de questions peut aussi afficher des catégories système ou d\'autres contextes selon les permissions.';
echo html_writer::end_div();

// ======================================================================
// ÉTAPE 5 : Proposer une solution
// ======================================================================

echo html_writer::tag('h2', '5. Solution proposée');

echo html_writer::start_div('alert alert-success');
echo '<strong>🔧 Solution :</strong><br>';
echo 'Nous devons modifier notre fonction pour inclure :<br>';
echo '<ul>';
echo '<li>Les catégories de questions des cours dans la catégorie de cours sélectionnée</li>';
echo '<li>Les catégories de questions système (si elles sont visibles dans la banque)</li>';
echo '<li>Les catégories de questions des modules des cours dans la catégorie</li>';
echo '</ul>';
echo 'Cela reproduira exactement ce que vous voyez dans la banque de questions Moodle.';
echo html_writer::end_div();

echo $OUTPUT->footer();
