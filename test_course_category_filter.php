<?php
// ======================================================================
// Test du filtre par catégorie de cours
// ======================================================================

// Inclure la configuration de Moodle.
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Charger les bibliothèques Moodle nécessaires.
require_login();

// Vérification stricte : seuls les administrateurs du site peuvent accéder à cette page.
if (!is_siteadmin()) {
    print_error('accesinterdit', 'admin', '', 'Vous devez être administrateur du site pour accéder à cet outil.');
    exit;
}

// Définir le contexte de la page (système).
$context = context_system::instance();

// Définir le titre et l'URL de la page.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/question_diagnostic/test_course_category_filter.php'));
$pagetitle = 'Test du filtre par catégorie de cours';
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);
$PAGE->set_pagelayout('report');

// ======================================================================
// Section d'en-tête Moodle standard.
echo $OUTPUT->header();

echo html_writer::tag('h1', 'Test du filtre par catégorie de cours');

// ======================================================================
// TEST 1 : Récupérer toutes les catégories de cours
// ======================================================================

echo html_writer::tag('h2', '1. Liste des catégories de cours disponibles');

$course_categories = local_question_diagnostic_get_course_categories();

if (empty($course_categories)) {
    echo html_writer::tag('p', 'Aucune catégorie de cours trouvée.');
} else {
    echo html_writer::start_tag('ul');
    foreach ($course_categories as $cat) {
        $label = $cat->formatted_name;
        if ($cat->course_count > 0) {
            $label .= ' (' . $cat->course_count . ' cours)';
        }
        echo html_writer::tag('li', $label . ' (ID: ' . $cat->id . ')');
    }
    echo html_writer::end_tag('ul');
}

// ======================================================================
// TEST 2 : Tester le filtre pour chaque catégorie de cours
// ======================================================================

echo html_writer::tag('h2', '2. Test du filtre par catégorie de cours');

foreach ($course_categories as $course_cat) {
    if ($course_cat->course_count == 0) {
        continue; // Ignorer les catégories sans cours
    }
    
    echo html_writer::tag('h3', 'Catégorie : ' . $course_cat->formatted_name . ' (ID: ' . $course_cat->id . ')');
    
    $question_categories = local_question_diagnostic_get_question_categories_by_course_category($course_cat->id);
    
    if (empty($question_categories)) {
        echo html_writer::tag('p', 'Aucune catégorie de questions trouvée pour cette catégorie de cours.');
    } else {
        echo html_writer::tag('p', 'Nombre de catégories de questions trouvées : ' . count($question_categories));
        
        echo html_writer::start_tag('table', ['class' => 'table table-striped']);
        echo html_writer::start_tag('thead');
        echo html_writer::start_tag('tr');
        echo html_writer::tag('th', 'ID');
        echo html_writer::tag('th', 'Nom');
        echo html_writer::tag('th', 'Cours');
        echo html_writer::tag('th', 'Questions');
        echo html_writer::tag('th', 'Sous-catégories');
        echo html_writer::tag('th', 'Statut');
        echo html_writer::end_tag('tr');
        echo html_writer::end_tag('thead');
        
        echo html_writer::start_tag('tbody');
        foreach ($question_categories as $qcat) {
            echo html_writer::start_tag('tr');
            echo html_writer::tag('td', $qcat->id);
            echo html_writer::tag('td', $qcat->name);
            echo html_writer::tag('td', $qcat->course_name);
            echo html_writer::tag('td', $qcat->total_questions);
            echo html_writer::tag('td', $qcat->subcategory_count);
            echo html_writer::tag('td', $qcat->status);
            echo html_writer::end_tag('tr');
        }
        echo html_writer::end_tag('tbody');
        echo html_writer::end_tag('table');
    }
    
    echo html_writer::tag('hr', '');
}

// ======================================================================
// TEST 3 : Lien vers la page categories.php avec filtre
// ======================================================================

echo html_writer::tag('h2', '3. Liens vers la page categories.php avec filtre');

echo html_writer::tag('p', 'Vous pouvez tester le filtre directement sur la page categories.php :');

echo html_writer::start_tag('ul');
foreach ($course_categories as $course_cat) {
    if ($course_cat->course_count == 0) {
        continue;
    }
    
    $url = new moodle_url('/local/question_diagnostic/categories.php', [
        'course_category' => $course_cat->id
    ]);
    
    echo html_writer::tag('li', 
        html_writer::link($url, 'Voir les catégories de questions pour : ' . $course_cat->formatted_name)
    );
}
echo html_writer::end_tag('ul');

// Lien vers toutes les catégories
$all_url = new moodle_url('/local/question_diagnostic/categories.php');
echo html_writer::tag('p', 
    html_writer::link($all_url, 'Voir toutes les catégories de questions')
);

echo $OUTPUT->footer();
