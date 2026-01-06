<?php
// ======================================================================
// Test de la correction SQL du filtre par catégorie de cours (v1.11.7)
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
$PAGE->set_url(new moodle_url('/local/question_diagnostic/test_sql_fix.php'));
$pagetitle = 'Test de la correction SQL (v1.11.7)';
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);
$PAGE->set_pagelayout('report');

// ======================================================================
// Section d'en-tête Moodle standard.
echo $OUTPUT->header();

echo html_writer::tag('h1', '🔧 Test de la correction SQL (v1.11.7)');

// ======================================================================
// ÉTAPE 1 : Informations sur la base de données
// ======================================================================

echo html_writer::tag('h2', '1. Informations sur la base de données');

global $DB;

$db_info = [
    'Type' => $DB->get_dbfamily(),
    'Version' => $DB->get_server_info(),
    'Driver' => get_class($DB)
];

echo html_writer::start_tag('table', ['class' => 'table table-striped']);
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
echo html_writer::tag('th', 'Propriété');
echo html_writer::tag('th', 'Valeur');
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');

echo html_writer::start_tag('tbody');
foreach ($db_info as $key => $value) {
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', $key);
    echo html_writer::tag('td', $value);
    echo html_writer::end_tag('tr');
}
echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');

// ======================================================================
// ÉTAPE 2 : Test de la fonction corrigée
// ======================================================================

echo html_writer::tag('h2', '2. Test de la fonction corrigée');

// Trouver une catégorie de cours pour tester
$course_categories = $DB->get_records('course_categories', null, 'name ASC', 'id, name', 0, 5);

if (empty($course_categories)) {
    echo html_writer::tag('p', '❌ Aucune catégorie de cours trouvée.');
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::tag('p', '📚 Catégories de cours disponibles pour test :');
echo html_writer::start_tag('ul');
foreach ($course_categories as $cat) {
    $courses_count = $DB->count_records('course', ['category' => $cat->id]);
    echo html_writer::tag('li', $cat->name . ' (ID: ' . $cat->id . ') - ' . $courses_count . ' cours');
}
echo html_writer::end_tag('ul');

// Tester avec la première catégorie qui a des cours
$test_category = null;
foreach ($course_categories as $cat) {
    $courses_count = $DB->count_records('course', ['category' => $cat->id]);
    if ($courses_count > 0) {
        $test_category = $cat;
        break;
    }
}

if (!$test_category) {
    echo html_writer::tag('p', '❌ Aucune catégorie de cours avec des cours trouvée.');
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::tag('h3', 'Test avec la catégorie : ' . $test_category->name . ' (ID: ' . $test_category->id . ')');

// Activer le mode debug temporairement
$old_debug = $CFG->debug;
$old_debugdisplay = $CFG->debugdisplay;
$CFG->debug = DEBUG_DEVELOPER;
$CFG->debugdisplay = 1;

echo html_writer::start_div('alert alert-info');
echo '<strong>🔍 Mode debug activé</strong> - Les messages de debug seront affichés ci-dessous.';
echo html_writer::end_div();

// Capturer les messages de debug
ob_start();

// Tester la fonction
$question_categories = local_question_diagnostic_get_question_categories_by_course_category($test_category->id);

// Récupérer les messages de debug
$debug_output = ob_get_clean();

// Restaurer les paramètres de debug
$CFG->debug = $old_debug;
$CFG->debugdisplay = $old_debugdisplay;

// Afficher les messages de debug
if (!empty($debug_output)) {
    echo html_writer::tag('h4', 'Messages de debug :');
    echo html_writer::start_div('alert alert-secondary');
    echo '<pre>' . htmlspecialchars($debug_output) . '</pre>';
    echo html_writer::end_div();
}

// Afficher les résultats
echo html_writer::tag('p', '🔧 Résultat de la fonction : <strong>' . count($question_categories) . '</strong> catégories trouvées');

if (!empty($question_categories)) {
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
        echo html_writer::tag('th', 'Sous-catégories');
        echo html_writer::end_tag('tr');
        echo html_writer::end_tag('thead');
        
        echo html_writer::start_tag('tbody');
        foreach ($categories as $cat) {
            echo html_writer::start_tag('tr');
            echo html_writer::tag('td', $cat->id);
            echo html_writer::tag('td', $cat->name);
            echo html_writer::tag('td', $cat->context_display_name ?? 'Inconnu');
            echo html_writer::tag('td', $cat->total_questions);
            echo html_writer::tag('td', $cat->subcategory_count);
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
    
    echo html_writer::start_div('alert alert-success');
    echo '<strong>📊 Statistiques globales :</strong><br>';
    echo '• Total questions : <strong>' . $total_questions . '</strong><br>';
    echo '• Total sous-catégories : <strong>' . $total_subcategories . '</strong><br>';
    echo '• Catégories vides : <strong>' . $empty_categories . '</strong><br>';
    echo '• Catégories avec contenu : <strong>' . (count($question_categories) - $empty_categories) . '</strong>';
    echo html_writer::end_div();
    
} else {
    echo html_writer::start_div('alert alert-warning');
    echo '<strong>⚠️ Aucune catégorie trouvée</strong><br>';
    echo 'Cela peut être normal si la catégorie de cours ne contient pas de catégories de questions.';
    echo html_writer::end_div();
}

// ======================================================================
// ÉTAPE 3 : Test de l'interface utilisateur
// ======================================================================

echo html_writer::tag('h2', '3. Test de l\'interface utilisateur');

echo html_writer::tag('p', '🧪 Vous pouvez maintenant tester l\'interface utilisateur :');

$test_url = new moodle_url('/local/question_diagnostic/categories.php', [
    'course_category' => $test_category->id
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
// ÉTAPE 4 : Instructions pour l'utilisateur
// ======================================================================

echo html_writer::tag('h2', '4. Instructions pour l\'utilisateur');

echo html_writer::start_div('alert alert-info');
echo '<strong>💡 Comment utiliser la fonctionnalité corrigée :</strong><br><br>';
echo '1. <strong>Accédez à la page des catégories</strong> : <code>/local/question_diagnostic/categories.php</code><br>';
echo '2. <strong>Sélectionnez une catégorie de cours</strong> dans le filtre "Catégorie de cours"<br>';
echo '3. <strong>La page se recharge automatiquement</strong> avec toutes les catégories de questions visibles<br>';
echo '4. <strong>Vous verrez maintenant :</strong><br>';
echo '&nbsp;&nbsp;&nbsp;• 🌐 Catégories système (accessibles partout)<br>';
echo '&nbsp;&nbsp;&nbsp;• 📚 Catégories des cours dans la catégorie sélectionnée<br>';
echo '&nbsp;&nbsp;&nbsp;• 📝 Catégories des modules (quiz, etc.) des cours<br>';
echo '5. <strong>Utilisez le lien "Voir toutes les catégories de cours"</strong> pour revenir à la vue complète<br><br>';
echo '<strong>🎯 Résultat :</strong> La correction SQL devrait maintenant fonctionner avec tous les SGBD !';
echo html_writer::end_div();

echo $OUTPUT->footer();
