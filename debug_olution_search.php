<?php
// ======================================================================
// Diagnostic de la Recherche de Catégorie Olution (v1.11.13)
// ======================================================================

// Inclure la configuration de Moodle
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Vérifications de sécurité
require_login();
if (!is_siteadmin()) {
    throw new \moodle_exception('accessdenied', 'admin', '', 'Vous devez être administrateur du site pour accéder à cet outil.');
}

// Définir le contexte et la page
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/question_diagnostic/debug_olution_search.php'));
$PAGE->set_title('Diagnostic Recherche Olution v1.11.13');
$PAGE->set_heading('Diagnostic Recherche de Catégorie Olution');

// Activer le mode debug
$CFG->debug = (E_ALL | E_STRICT);
$CFG->debugdisplay = 1;

echo $OUTPUT->header();

echo html_writer::tag('h2', '🔍 Diagnostic de la Recherche de Catégorie Olution (v1.11.13)');

echo html_writer::start_div('alert alert-info');
echo '<strong>🎯 Objectif :</strong> Diagnostiquer pourquoi la recherche de catégorie Olution échoue et identifier les catégories système existantes.';
echo html_writer::end_div();

echo html_writer::tag('h3', '1. Vérification du contexte système');

global $DB;
$systemcontext = context_system::instance();

echo html_writer::start_div('alert alert-success');
echo '✅ Contexte système trouvé : ID ' . $systemcontext->id;
echo html_writer::end_div();

echo html_writer::tag('h3', '2. Toutes les catégories de questions système');

try {
    // Récupérer TOUTES les catégories de questions système
    $all_system_categories = $DB->get_records_sql(
        "SELECT id, name, info, parent, sortorder
         FROM {question_categories}
         WHERE contextid = :contextid
         ORDER BY parent, sortorder, name",
        ['contextid' => $systemcontext->id]
    );
    
    if (empty($all_system_categories)) {
        echo html_writer::start_div('alert alert-danger');
        echo '❌ Aucune catégorie de questions système trouvée !';
        echo html_writer::end_div();
    } else {
        echo html_writer::start_div('alert alert-success');
        echo '✅ ' . count($all_system_categories) . ' catégories de questions système trouvées';
        echo html_writer::end_div();
        
        // Afficher toutes les catégories
        echo html_writer::start_div('row');
        echo html_writer::start_div('col-md-6');
        echo html_writer::tag('h4', 'Catégories racines (parent = 0)');
        echo html_writer::start_tag('ul');
        
        foreach ($all_system_categories as $cat) {
            if ($cat->parent == 0) {
                $highlight = '';
                if (stripos($cat->name, 'olution') !== false || stripos($cat->info ?? '', 'olution') !== false) {
                    $highlight = ' style="background-color: #d4edda; padding: 2px 4px; border-radius: 3px;"';
                }
                
                echo html_writer::tag('li', 
                    '<span' . $highlight . '>' . 
                    '<strong>ID:</strong> ' . $cat->id . ' | ' .
                    '<strong>Nom:</strong> ' . format_string($cat->name) . ' | ' .
                    '<strong>Parent:</strong> ' . $cat->parent . ' | ' .
                    '<strong>Info:</strong> ' . format_string($cat->info ?? 'Aucune') .
                    '</span>'
                );
            }
        }
        echo html_writer::end_tag('ul');
        echo html_writer::end_div();
        
        echo html_writer::start_div('col-md-6');
        echo html_writer::tag('h4', 'Sous-catégories (parent > 0)');
        echo html_writer::start_tag('ul');
        
        foreach ($all_system_categories as $cat) {
            if ($cat->parent > 0) {
                $highlight = '';
                if (stripos($cat->name, 'olution') !== false || stripos($cat->info ?? '', 'olution') !== false) {
                    $highlight = ' style="background-color: #d4edda; padding: 2px 4px; border-radius: 3px;"';
                }
                
                echo html_writer::tag('li', 
                    '<span' . $highlight . '>' . 
                    '<strong>ID:</strong> ' . $cat->id . ' | ' .
                    '<strong>Nom:</strong> ' . format_string($cat->name) . ' | ' .
                    '<strong>Parent:</strong> ' . $cat->parent .
                    '</span>'
                );
            }
        }
        echo html_writer::end_tag('ul');
        echo html_writer::end_div();
        echo html_writer::end_div();
    }
    
} catch (Exception $e) {
    echo html_writer::start_div('alert alert-danger');
    echo '❌ Erreur lors de la récupération des catégories système : ' . $e->getMessage();
    echo html_writer::end_div();
}

echo html_writer::tag('h3', '3. Test de la fonction find_olution_category()');

try {
    $olution_category = local_question_diagnostic_find_olution_category();
    
    if ($olution_category) {
        echo html_writer::start_div('alert alert-success');
        echo '✅ Catégorie Olution trouvée par la fonction :<br>';
        echo '• <strong>ID:</strong> ' . $olution_category->id . '<br>';
        echo '• <strong>Nom:</strong> ' . format_string($olution_category->name) . '<br>';
        echo '• <strong>Parent:</strong> ' . $olution_category->parent . '<br>';
        echo '• <strong>Info:</strong> ' . format_string($olution_category->info ?? 'Aucune') . '<br>';
        echo '• <strong>Contexte:</strong> ' . $olution_category->contextid;
        echo html_writer::end_div();
    } else {
        echo html_writer::start_div('alert alert-danger');
        echo '❌ Aucune catégorie Olution trouvée par la fonction find_olution_category()';
        echo html_writer::end_div();
    }
    
} catch (Exception $e) {
    echo html_writer::start_div('alert alert-danger');
    echo '❌ Erreur lors de l\'appel à find_olution_category() : ' . $e->getMessage();
    echo html_writer::end_div();
}

echo html_writer::tag('h3', '4. Recherche manuelle de catégories avec "olution"');

try {
    // Recherche manuelle avec différents patterns
    $patterns = [
        '%Olution%',
        '%olution%',
        '%OLUTION%',
        'Olution%',
        '%Olution',
        '% olution %',
        '% olution',
        'olution %'
    ];
    
    foreach ($patterns as $pattern) {
        $categories = $DB->get_records_sql(
            "SELECT id, name, info, parent
             FROM {question_categories}
             WHERE contextid = :contextid
             AND " . $DB->sql_like('name', ':pattern', false, false) . "
             ORDER BY name",
            [
                'contextid' => $systemcontext->id,
                'pattern' => $pattern
            ]
        );
        
        if (!empty($categories)) {
            echo html_writer::start_div('alert alert-info');
            echo '<strong>Pattern "' . $pattern . '" :</strong> ' . count($categories) . ' catégorie(s) trouvée(s)';
            echo html_writer::start_tag('ul');
            foreach ($categories as $cat) {
                echo html_writer::tag('li', 
                    'ID: ' . $cat->id . ' | Nom: ' . format_string($cat->name) . 
                    ($cat->parent == 0 ? ' (RACINE)' : ' (Parent: ' . $cat->parent . ')')
                );
            }
            echo html_writer::end_tag('ul');
            echo html_writer::end_div();
        }
    }
    
} catch (Exception $e) {
    echo html_writer::start_div('alert alert-danger');
    echo '❌ Erreur lors de la recherche manuelle : ' . $e->getMessage();
    echo html_writer::end_div();
}

echo html_writer::tag('h3', '5. Recherche dans les descriptions');

try {
    $categories_with_info = $DB->get_records_sql(
        "SELECT id, name, info, parent
         FROM {question_categories}
         WHERE contextid = :contextid
         AND " . $DB->sql_like('info', ':pattern', false, false) . "
         ORDER BY name",
        [
            'contextid' => $systemcontext->id,
            'pattern' => '%olution%'
        ]
    );
    
    if (!empty($categories_with_info)) {
        echo html_writer::start_div('alert alert-info');
        echo '<strong>Catégories avec "olution" dans la description :</strong> ' . count($categories_with_info) . ' trouvée(s)';
        echo html_writer::start_tag('ul');
        foreach ($categories_with_info as $cat) {
            echo html_writer::tag('li', 
                'ID: ' . $cat->id . ' | Nom: ' . format_string($cat->name) . 
                ' | Description: ' . format_string($cat->info) .
                ($cat->parent == 0 ? ' (RACINE)' : ' (Parent: ' . $cat->parent . ')')
            );
        }
        echo html_writer::end_tag('ul');
        echo html_writer::end_div();
    } else {
        echo html_writer::start_div('alert alert-warning');
        echo '⚠️ Aucune catégorie avec "olution" dans la description trouvée';
        echo html_writer::end_div();
    }
    
} catch (Exception $e) {
    echo html_writer::start_div('alert alert-danger');
    echo '❌ Erreur lors de la recherche dans les descriptions : ' . $e->getMessage();
    echo html_writer::end_div();
}

echo html_writer::tag('h3', '6. Recommandations');

echo html_writer::start_div('alert alert-warning');
echo '<strong>💡 Recommandations :</strong><br>';
echo '1. <strong>Si aucune catégorie Olution n\'existe :</strong> Créer une catégorie système avec le nom "Olution"<br>';
echo '2. <strong>Si une catégorie existe mais n\'est pas trouvée :</strong> Vérifier le nom exact et les espaces<br>';
echo '3. <strong>Si la catégorie est une sous-catégorie :</strong> Déplacer vers la racine (parent = 0)<br>';
echo '4. <strong>Pour créer la catégorie :</strong> Aller dans Administration > Questions > Catégories > Ajouter';
echo html_writer::end_div();

echo html_writer::tag('h3', '7. Messages de debug');

echo html_writer::start_div('alert alert-light');
echo '<strong>📝 Messages de debug :</strong><br>';
echo 'Les messages de debug s\'affichent ci-dessus si le mode debug est activé.';
echo html_writer::end_div();

echo $OUTPUT->footer();
