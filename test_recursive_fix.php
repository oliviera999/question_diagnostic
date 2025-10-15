<?php
// ======================================================================
// Test de la correction récursive pour les sous-catégories (v1.11.8)
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
$PAGE->set_url(new moodle_url('/local/question_diagnostic/test_recursive_fix.php'));
$pagetitle = 'Test de la correction récursive (v1.11.8)';
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);
$PAGE->set_pagelayout('report');

// ======================================================================
// Section d'en-tête Moodle standard.
echo $OUTPUT->header();

echo html_writer::tag('h1', '🔧 Test de la correction récursive (v1.11.8)');

global $DB;

// ======================================================================
// ÉTAPE 1 : Tester la fonction récursive
// ======================================================================

echo html_writer::tag('h2', '1. Test de la fonction récursive');

$olution_course_category = $DB->get_record('course_categories', ['name' => 'olution']);

if (!$olution_course_category) {
    echo html_writer::tag('p', '❌ Aucune catégorie de cours "olution" trouvée.');
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::tag('p', '✅ Catégorie Olution trouvée : <strong>' . $olution_course_category->name . '</strong> (ID: ' . $olution_course_category->id . ')');

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

// Tester la fonction récursive
$courses = local_question_diagnostic_get_courses_in_category_recursive($olution_course_category->id);

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

echo html_writer::tag('p', '🔧 Fonction récursive trouve : <strong>' . count($courses) . '</strong> cours');

if (!empty($courses)) {
    echo html_writer::start_div('alert alert-success');
    echo '<strong>✅ SUCCÈS :</strong> La fonction récursive trouve des cours dans Olution et ses sous-catégories !';
    echo html_writer::end_div();
    
    // Afficher la liste des cours trouvés
    echo html_writer::tag('h3', 'Cours trouvés :');
    echo html_writer::start_tag('table', ['class' => 'table table-striped table-sm']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'ID');
    echo html_writer::tag('th', 'Nom du cours');
    echo html_writer::tag('th', 'Catégorie directe');
    echo html_writer::tag('th', 'Statut');
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    
    echo html_writer::start_tag('tbody');
    foreach ($courses as $course) {
        $direct_category = $DB->get_record('course_categories', ['id' => $course->category]);
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', $course->id);
        echo html_writer::tag('td', $course->fullname);
        echo html_writer::tag('td', $direct_category ? $direct_category->name : 'Inconnue');
        echo html_writer::tag('td', $course->visible ? 'Visible' : 'Caché');
        echo html_writer::end_tag('tr');
    }
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
} else {
    echo html_writer::start_div('alert alert-danger');
    echo '<strong>❌ ÉCHEC :</strong> La fonction récursive ne trouve aucun cours.';
    echo html_writer::end_div();
}

// ======================================================================
// ÉTAPE 2 : Tester la fonction complète
// ======================================================================

echo html_writer::tag('h2', '2. Test de la fonction complète');

// Activer le mode debug
$CFG->debug = DEBUG_DEVELOPER;
$CFG->debugdisplay = 1;

ob_start();

$question_categories = local_question_diagnostic_get_question_categories_by_course_category($olution_course_category->id);

$debug_output2 = ob_get_clean();

// Restaurer les paramètres de debug
$CFG->debug = $old_debug;
$CFG->debugdisplay = $old_debugdisplay;

// Afficher les messages de debug
if (!empty($debug_output2)) {
    echo html_writer::tag('h3', 'Messages de debug de la fonction complète :');
    echo html_writer::start_div('alert alert-secondary');
    echo '<pre>' . htmlspecialchars($debug_output2) . '</pre>';
    echo html_writer::end_div();
}

echo html_writer::tag('p', '🔧 Fonction complète trouve : <strong>' . count($question_categories) . '</strong> catégories de questions');

if (!empty($question_categories)) {
    echo html_writer::start_div('alert alert-success');
    echo '<strong>✅ SUCCÈS :</strong> La fonction complète trouve des catégories de questions !';
    echo html_writer::end_div();
    
    // Grouper par type de contexte
    $grouped_categories = [];
    foreach ($question_categories as $cat) {
        $context_type = $cat->context_type ?? 'unknown';
        if (!isset($grouped_categories[$context_type])) {
            $grouped_categories[$context_type] = [];
        }
        $grouped_categories[$context_type][] = $cat;
    }
    
    // Afficher par groupe
    foreach ($grouped_categories as $context_type => $categories) {
        $type_label = '';
        $type_icon = '';
        switch ($context_type) {
            case 'system':
                $type_label = 'Catégories Système';
                $type_icon = '🌐';
                break;
            case 'course':
                $type_label = 'Catégories de Cours';
                $type_icon = '📚';
                break;
            case 'module':
                $type_label = 'Catégories de Modules';
                $type_icon = '📝';
                break;
            default:
                $type_label = 'Autres Catégories';
                $type_icon = '❓';
        }
        
        echo html_writer::tag('h4', $type_icon . ' ' . $type_label . ' (' . count($categories) . ')');
        
        echo html_writer::start_tag('table', ['class' => 'table table-striped table-sm']);
        echo html_writer::start_tag('thead');
        echo html_writer::start_tag('tr');
        echo html_writer::tag('th', 'ID');
        echo html_writer::tag('th', 'Nom');
        echo html_writer::tag('th', 'Contexte');
        echo html_writer::tag('th', 'Questions');
        echo html_writer::end_tag('tr');
        echo html_writer::end_tag('thead');
        
        echo html_writer::start_tag('tbody');
        foreach ($categories as $cat) {
            echo html_writer::start_tag('tr');
            echo html_writer::tag('td', $cat->id);
            echo html_writer::tag('td', $cat->name);
            echo html_writer::tag('td', $cat->context_display_name ?? 'Inconnu');
            echo html_writer::tag('td', $cat->total_questions);
            echo html_writer::end_tag('tr');
        }
        echo html_writer::end_tag('tbody');
        echo html_writer::end_tag('table');
        
        echo html_writer::tag('br', '');
    }
    
    // Statistiques globales
    $total_questions = 0;
    $total_subcategories = 0;
    $empty_categories = 0;
    
    foreach ($question_categories as $cat) {
        $total_questions += $cat->total_questions;
        $total_subcategories += $cat->subcategory_count;
        if ($cat->status === 'empty') {
            $empty_categories++;
        }
    }
    
    echo html_writer::start_div('alert alert-info');
    echo '<strong>📊 Statistiques globales :</strong><br>';
    echo '• Total questions : <strong>' . $total_questions . '</strong><br>';
    echo '• Total sous-catégories : <strong>' . $total_subcategories . '</strong><br>';
    echo '• Catégories vides : <strong>' . $empty_categories . '</strong><br>';
    echo '• Catégories avec contenu : <strong>' . (count($question_categories) - $empty_categories) . '</strong>';
    echo html_writer::end_div();
    
} else {
    echo html_writer::start_div('alert alert-warning');
    echo '<strong>⚠️ ATTENTION :</strong> Aucune catégorie de questions trouvée.<br><br>';
    echo 'Cela peut être normal si les cours dans les sous-catégories n\'ont pas encore de questions créées.';
    echo html_writer::end_div();
}

// ======================================================================
// ÉTAPE 3 : Test de l'interface utilisateur
// ======================================================================

echo html_writer::tag('h2', '3. Test de l\'interface utilisateur');

echo html_writer::tag('p', '🧪 Vous pouvez maintenant tester l\'interface utilisateur :');

$test_url = new moodle_url('/local/question_diagnostic/categories.php', [
    'course_category' => $olution_course_category->id
]);

echo html_writer::tag('p', 
    html_writer::link($test_url, '🔗 Tester le filtre par catégorie de cours dans l\'interface', [
        'class' => 'btn btn-primary',
        'target' => '_blank'
    ])
);

echo html_writer::tag('p', 
    html_writer::link(
        new moodle_url('/local/question_diagnostic/categories.php'),
        '🔗 Voir toutes les catégories (sans filtre)',
        ['class' => 'btn btn-secondary', 'target' => '_blank']
    )
);

// ======================================================================
// ÉTAPE 4 : Résumé de la correction
// ======================================================================

echo html_writer::tag('h2', '4. Résumé de la correction');

echo html_writer::start_div('alert alert-success');
echo '<strong>🎯 CORRECTION APPLIQUÉE :</strong><br><br>';
echo 'La version v1.11.8 inclut maintenant :<br>';
echo '• <strong>Recherche récursive</strong> dans les sous-catégories de cours<br>';
echo '• <strong>Fonction</strong> <code>local_question_diagnostic_get_courses_in_category_recursive()</code><br>';
echo '• <strong>Support complet</strong> des catégories parent sans cours directs<br>';
echo '• <strong>Logs de debug</strong> détaillés pour le diagnostic<br><br>';
echo '<strong>Résultat :</strong> La catégorie Olution devrait maintenant afficher toutes les catégories de questions de ses sous-catégories !';
echo html_writer::end_div();

echo $OUTPUT->footer();
