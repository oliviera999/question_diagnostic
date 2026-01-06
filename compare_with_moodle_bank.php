<?php
// ======================================================================
// Comparaison avec la banque de questions Moodle native
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
$PAGE->set_url(new moodle_url('/local/question_diagnostic/compare_with_moodle_bank.php'));
$pagetitle = 'Comparaison avec la banque de questions Moodle';
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);
$PAGE->set_pagelayout('report');

// ======================================================================
// Section d'en-tête Moodle standard.
echo $OUTPUT->header();

echo html_writer::tag('h1', '🔍 Comparaison avec la banque de questions Moodle native');

global $DB;

// ======================================================================
// ÉTAPE 1 : Trouver la catégorie Olution
// ======================================================================

echo html_writer::tag('h2', '1. Recherche de la catégorie Olution');

$olution_course_category = $DB->get_record('course_categories', ['name' => 'Olution']);

if (!$olution_course_category) {
    $olution_course_category = $DB->get_record_sql("
        SELECT * FROM {course_categories} 
        WHERE " . $DB->sql_like('name', ':pattern', false, false) . "
        ORDER BY " . $DB->sql_position("'Olution'", 'name') . " ASC
        LIMIT 1
    ", ['pattern' => '%Olution%']);
}

if (!$olution_course_category) {
    echo html_writer::tag('p', '❌ Aucune catégorie de cours "Olution" trouvée.');
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::tag('p', '✅ Catégorie Olution trouvée : <strong>' . $olution_course_category->name . '</strong> (ID: ' . $olution_course_category->id . ')');

// ======================================================================
// ÉTAPE 2 : Simuler ce que fait la banque de questions Moodle
// ======================================================================

echo html_writer::tag('h2', '2. Simulation de la banque de questions Moodle');

// Récupérer tous les cours dans la catégorie Olution
$courses = $DB->get_records('course', ['category' => $olution_course_category->id], 'fullname ASC');

echo html_writer::tag('p', '📚 Cours dans Olution : <strong>' . count($courses) . '</strong>');

if (empty($courses)) {
    echo html_writer::start_div('alert alert-warning');
    echo '<strong>⚠️ PROBLÈME :</strong> Aucun cours dans la catégorie Olution !<br><br>';
    echo 'La banque de questions Moodle ne peut pas afficher de catégories de questions sans cours.';
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

// Récupérer les contextes de cours
$course_ids = array_keys($courses);
list($course_ids_sql, $course_params) = $DB->get_in_or_equal($course_ids);

$contexts = $DB->get_records_sql("
    SELECT id, instanceid
    FROM {context}
    WHERE contextlevel = :contextlevel
    AND instanceid " . $course_ids_sql, 
    array_merge(['contextlevel' => CONTEXT_COURSE], $course_params)
);

echo html_writer::tag('p', '🔗 Contextes de cours : <strong>' . count($contexts) . '</strong>');

// Récupérer les contextes de modules
$module_contexts = $DB->get_records_sql("
    SELECT ctx.id, ctx.instanceid, cm.course
    FROM {context} ctx
    INNER JOIN {course_modules} cm ON cm.id = ctx.instanceid
    WHERE ctx.contextlevel = :contextlevel
    AND cm.course " . $course_ids_sql,
    array_merge(['contextlevel' => CONTEXT_MODULE], $course_params)
);

echo html_writer::tag('p', '📝 Contextes de modules : <strong>' . count($module_contexts) . '</strong>');

// Récupérer le contexte système
$system_context = context_system::instance();
echo html_writer::tag('p', '🌐 Contexte système : <strong>ID ' . $system_context->id . '</strong>');

// Construire la liste de tous les contextes
$all_context_ids = array_merge(array_keys($contexts), array_keys($module_contexts));
$all_context_ids[] = $system_context->id;
$all_context_ids = array_unique($all_context_ids);

echo html_writer::tag('p', '📋 Total contextes à rechercher : <strong>' . count($all_context_ids) . '</strong>');

// Récupérer TOUTES les catégories de questions dans ces contextes
list($all_context_ids_sql, $all_context_params) = $DB->get_in_or_equal($all_context_ids);

$all_question_categories = $DB->get_records_sql("
    SELECT qc.*, ctx.contextlevel, ctx.instanceid
    FROM {question_categories} qc
    INNER JOIN {context} ctx ON ctx.id = qc.contextid
    WHERE qc.contextid " . $all_context_ids_sql,
    $all_context_params
);

echo html_writer::tag('p', '📂 Catégories de questions trouvées : <strong>' . count($all_question_categories) . '</strong>');

// ======================================================================
// ÉTAPE 3 : Analyser les résultats par type de contexte
// ======================================================================

echo html_writer::tag('h2', '3. Analyse par type de contexte');

$by_context_type = [
    'system' => [],
    'course' => [],
    'module' => []
];

foreach ($all_question_categories as $cat) {
    switch ($cat->contextlevel) {
        case CONTEXT_SYSTEM:
            $by_context_type['system'][] = $cat;
            break;
        case CONTEXT_COURSE:
            $by_context_type['course'][] = $cat;
            break;
        case CONTEXT_MODULE:
            $by_context_type['module'][] = $cat;
            break;
    }
}

// Afficher les catégories système
echo html_writer::tag('h3', '🌐 Catégories Système (' . count($by_context_type['system']) . ')');
if (!empty($by_context_type['system'])) {
    echo html_writer::start_tag('ul');
    foreach ($by_context_type['system'] as $cat) {
        echo html_writer::tag('li', $cat->name . ' (ID: ' . $cat->id . ')');
    }
    echo html_writer::end_tag('ul');
} else {
    echo html_writer::tag('p', 'Aucune catégorie système trouvée.');
}

// Afficher les catégories de cours
echo html_writer::tag('h3', '📚 Catégories de Cours (' . count($by_context_type['course']) . ')');
if (!empty($by_context_type['course'])) {
    echo html_writer::start_tag('ul');
    foreach ($by_context_type['course'] as $cat) {
        $course = $DB->get_record('course', ['id' => $cat->instanceid]);
        $course_name = $course ? $course->fullname : 'Cours inconnu';
        echo html_writer::tag('li', $cat->name . ' (ID: ' . $cat->id . ') - Cours: ' . $course_name);
    }
    echo html_writer::end_tag('ul');
} else {
    echo html_writer::tag('p', 'Aucune catégorie de cours trouvée.');
}

// Afficher les catégories de modules
echo html_writer::tag('h3', '📝 Catégories de Modules (' . count($by_context_type['module']) . ')');
if (!empty($by_context_type['module'])) {
    echo html_writer::start_tag('ul');
    foreach ($by_context_type['module'] as $cat) {
        $module_info = $DB->get_record_sql("
            SELECT cm.course, m.name as module_name
            FROM {course_modules} cm
            INNER JOIN {modules} m ON m.id = cm.module
            WHERE cm.id = :module_id
        ", ['module_id' => $cat->instanceid]);
        
        $course = $module_info ? $DB->get_record('course', ['id' => $module_info->course]) : null;
        $course_name = $course ? $course->fullname : 'Cours inconnu';
        $module_name = $module_info ? $module_info->module_name : 'Module inconnu';
        
        echo html_writer::tag('li', $cat->name . ' (ID: ' . $cat->id . ') - ' . $module_name . ' dans ' . $course_name);
    }
    echo html_writer::end_tag('ul');
} else {
    echo html_writer::tag('p', 'Aucune catégorie de module trouvée.');
}

// ======================================================================
// ÉTAPE 4 : Comparer avec notre fonction
// ======================================================================

echo html_writer::tag('h2', '4. Comparaison avec notre fonction');

$our_result = local_question_diagnostic_get_question_categories_by_course_category($olution_course_category->id);

echo html_writer::tag('p', '🔧 Notre fonction trouve : <strong>' . count($our_result) . '</strong> catégories');
echo html_writer::tag('p', '📊 Banque Moodle trouve : <strong>' . count($all_question_categories) . '</strong> catégories');

$difference = count($all_question_categories) - count($our_result);

if ($difference == 0) {
    echo html_writer::start_div('alert alert-success');
    echo '<strong>✅ PARFAIT :</strong> Notre fonction trouve exactement les mêmes catégories que la banque Moodle !';
    echo html_writer::end_div();
} else if ($difference > 0) {
    echo html_writer::start_div('alert alert-warning');
    echo '<strong>⚠️ DIFFÉRENCE :</strong> La banque Moodle trouve ' . $difference . ' catégories de plus que notre fonction.';
    echo html_writer::end_div();
} else {
    echo html_writer::start_div('alert alert-info');
    echo '<strong>ℹ️ DIFFÉRENCE :</strong> Notre fonction trouve ' . abs($difference) . ' catégories de plus que la banque Moodle.';
    echo html_writer::end_div();
}

// ======================================================================
// ÉTAPE 5 : Recommandations
// ======================================================================

echo html_writer::tag('h2', '5. Recommandations');

if (count($all_question_categories) == 0) {
    echo html_writer::start_div('alert alert-warning');
    echo '<strong>💡 PROBLÈME IDENTIFIÉ :</strong><br><br>';
    echo 'La catégorie Olution ne contient aucune catégorie de questions, même selon la banque Moodle native.<br><br>';
    echo '<strong>Solutions possibles :</strong><br>';
    echo '1. Créer des questions dans les cours Olution<br>';
    echo '2. Importer des questions dans ces cours<br>';
    echo '3. Utiliser une autre catégorie de cours qui contient des questions<br>';
    echo '4. Vérifier s\'il y a des sous-catégories avec des cours et des questions';
    echo html_writer::end_div();
} else {
    echo html_writer::start_div('alert alert-success');
    echo '<strong>✅ RÉSULTAT :</strong><br><br>';
    echo 'La catégorie Olution contient ' . count($all_question_categories) . ' catégories de questions selon la banque Moodle native.<br><br>';
    echo 'Notre fonction devrait maintenant fonctionner correctement.';
    echo html_writer::end_div();
}

echo $OUTPUT->footer();
