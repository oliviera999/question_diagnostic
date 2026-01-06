<?php
// ======================================================================
// Test de la correction du filtre par catégorie de cours (v1.11.6)
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
$PAGE->set_url(new moodle_url('/local/question_diagnostic/test_course_category_fix.php'));
$pagetitle = 'Test de la correction du filtre par catégorie de cours (v1.11.6)';
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);
$PAGE->set_pagelayout('report');

// ======================================================================
// Section d'en-tête Moodle standard.
echo $OUTPUT->header();

echo html_writer::tag('h1', '🔧 Test de la correction du filtre par catégorie de cours (v1.11.6)');

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

if (!$olution_course_category) {
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

echo html_writer::tag('p', '✅ Catégorie de cours trouvée : <strong>' . $olution_course_category->name . '</strong> (ID: ' . $olution_course_category->id . ')');

// ======================================================================
// ÉTAPE 2 : Tester notre fonction corrigée
// ======================================================================

echo html_writer::tag('h2', '2. Test de la fonction corrigée');

$question_categories = local_question_diagnostic_get_question_categories_by_course_category($olution_course_category->id);

echo html_writer::tag('p', '🔧 Résultat de notre fonction corrigée : <strong>' . count($question_categories) . '</strong> catégories trouvées');

if (!empty($question_categories)) {
    // Grouper par type de contexte pour un affichage plus clair
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
        
        echo html_writer::tag('h3', $type_icon . ' ' . $type_label . ' (' . count($categories) . ')');
        
        echo html_writer::start_tag('table', ['class' => 'table table-striped table-sm']);
        echo html_writer::start_tag('thead');
        echo html_writer::start_tag('tr');
        echo html_writer::tag('th', 'ID');
        echo html_writer::tag('th', 'Nom');
        echo html_writer::tag('th', 'Contexte');
        echo html_writer::tag('th', 'Questions');
        echo html_writer::tag('th', 'Sous-catégories');
        echo html_writer::tag('th', 'Statut');
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
            echo html_writer::tag('td', $cat->status);
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
    echo html_writer::tag('p', '❌ Aucune catégorie trouvée par notre fonction corrigée');
}

// ======================================================================
// ÉTAPE 3 : Comparaison avec l'ancienne méthode
// ======================================================================

echo html_writer::tag('h2', '3. Comparaison avec l\'ancienne méthode');

// Simuler l'ancienne méthode (seulement les contextes de cours)
$courses = $DB->get_records('course', ['category' => $olution_course_category->id], 'fullname ASC');
$course_ids = array_keys($courses);
list($course_ids_sql, $course_params) = $DB->get_in_or_equal($course_ids);

$old_method_sql = "SELECT qc.*, c.fullname as course_name, c.id as course_id
                   FROM {question_categories} qc
                   INNER JOIN {context} ctx ON ctx.id = qc.contextid
                   INNER JOIN {course} c ON c.id = ctx.instanceid
                   WHERE ctx.contextlevel = :contextlevel
                   AND c.id " . $course_ids_sql;

$old_method_categories = $DB->get_records_sql($old_method_sql, array_merge(
    ['contextlevel' => CONTEXT_COURSE],
    $course_params
));

echo html_writer::tag('p', '🔧 Ancienne méthode : <strong>' . count($old_method_categories) . '</strong> catégories trouvées');
echo html_writer::tag('p', '🆕 Nouvelle méthode : <strong>' . count($question_categories) . '</strong> catégories trouvées');

$difference = count($question_categories) - count($old_method_categories);
if ($difference > 0) {
    echo html_writer::start_div('alert alert-success');
    echo '<strong>✅ Amélioration :</strong> La nouvelle méthode trouve <strong>' . $difference . '</strong> catégories supplémentaires !';
    echo html_writer::end_div();
} else if ($difference < 0) {
    echo html_writer::start_div('alert alert-warning');
    echo '<strong>⚠️ Attention :</strong> La nouvelle méthode trouve <strong>' . abs($difference) . '</strong> catégories en moins.';
    echo html_writer::end_div();
} else {
    echo html_writer::start_div('alert alert-info');
    echo '<strong>ℹ️ Identique :</strong> Les deux méthodes trouvent le même nombre de catégories.';
    echo html_writer::end_div();
}

// ======================================================================
// ÉTAPE 4 : Test de l'interface utilisateur
// ======================================================================

echo html_writer::tag('h2', '4. Test de l\'interface utilisateur');

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
// ÉTAPE 5 : Instructions pour l'utilisateur
// ======================================================================

echo html_writer::tag('h2', '5. Instructions pour l\'utilisateur');

echo html_writer::start_div('alert alert-info');
echo '<strong>💡 Comment utiliser la fonctionnalité corrigée :</strong><br><br>';
echo '1. <strong>Accédez à la page des catégories</strong> : <code>/local/question_diagnostic/categories.php</code><br>';
echo '2. <strong>Sélectionnez la catégorie de cours</strong> dans le filtre "Catégorie de cours"<br>';
echo '3. <strong>La page se recharge automatiquement</strong> avec toutes les catégories de questions visibles<br>';
echo '4. <strong>Vous verrez maintenant :</strong><br>';
echo '&nbsp;&nbsp;&nbsp;• 🌐 Catégories système (accessibles partout)<br>';
echo '&nbsp;&nbsp;&nbsp;• 📚 Catégories des cours dans la catégorie sélectionnée<br>';
echo '&nbsp;&nbsp;&nbsp;• 📝 Catégories des modules (quiz, etc.) des cours<br>';
echo '5. <strong>Utilisez le lien "Voir toutes les catégories de cours"</strong> pour revenir à la vue complète<br><br>';
echo '<strong>🎯 Résultat :</strong> Vous devriez maintenant voir exactement les mêmes catégories que dans la banque de questions Moodle !';
echo html_writer::end_div();

echo $OUTPUT->footer();
