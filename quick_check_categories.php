<?php
/**
 * Vérification rapide des catégories et de leur nommage
 */

require_once(__DIR__ . '/../../config.php');

require_login();
if (!is_siteadmin()) {
    print_error('accessdenied', 'admin');
}

$PAGE->set_url(new moodle_url('/local/question_diagnostic/quick_check_categories.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Quick Check Catégories');
$PAGE->set_heading('Vérification Rapide des Catégories');

echo $OUTPUT->header();

// Afficher le badge de version
echo local_question_diagnostic_render_version_badge();

echo html_writer::tag('h2', '🔍 Analyse Rapide des Noms de Catégories');

// ===================================================================
// 1. TOUTES LES CATÉGORIES RACINE (parent = 0)
// ===================================================================

echo html_writer::tag('h3', '1. Toutes les catégories à la racine (parent = 0)');

$root_categories = $DB->get_records_sql("
    SELECT qc.*, ctx.contextlevel
    FROM {question_categories} qc
    LEFT JOIN {context} ctx ON ctx.id = qc.contextid
    WHERE qc.parent = 0
    ORDER BY qc.name
");

echo html_writer::div('<strong>' . count($root_categories) . '</strong> catégories racine trouvées', 'alert alert-info');

if (!empty($root_categories)) {
    echo '<table class="generaltable" style="width: 100%;">';
    echo '<thead><tr>
            <th>ID</th>
            <th>Nom EXACT</th>
            <th>Contexte</th>
            <th>Questions</th>
            <th>Sous-cat</th>
            <th>Info/Description</th>
            <th>Contient "Default"?</th>
            <th>Contient "défaut"?</th>
            <th>Contient "Top"?</th>
          </tr></thead>';
    echo '<tbody>';
    
    $patterns_found = [
        'default_for' => 0,
        'default' => 0,
        'defaut' => 0,
        'top' => 0,
        'other' => 0
    ];
    
    foreach ($root_categories as $cat) {
        // Compter questions
        $q_count = $DB->count_records_sql("
            SELECT COUNT(DISTINCT q.id)
            FROM {question} q
            INNER JOIN {question_versions} qv ON qv.questionid = q.id
            INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
            WHERE qbe.questioncategoryid = :categoryid
        ", ['categoryid' => $cat->id]);
        
        // Compter sous-catégories
        $subcat_count = $DB->count_records('question_categories', ['parent' => $cat->id]);
        
        // Nom du contexte
        try {
            if ($cat->contextlevel) {
                $context_name = context_helper::get_level_name($cat->contextlevel);
            } else {
                $context_name = 'Inconnu';
            }
        } catch (Exception $e) {
            $context_name = 'Erreur';
        }
        
        // Vérifier les patterns dans le nom
        $name_lower = strtolower($cat->name);
        $has_default_for = (stripos($cat->name, 'Default for') !== false) ? '✅ OUI' : '❌ Non';
        $has_defaut = (stripos($cat->name, 'défaut') !== false || stripos($cat->name, 'defaut') !== false) ? '✅ OUI' : '❌ Non';
        $has_top = (stripos($cat->name, 'top') !== false) ? '✅ OUI' : '❌ Non';
        
        // Compter les patterns
        if (stripos($cat->name, 'Default for') !== false) {
            $patterns_found['default_for']++;
            $row_style = 'background-color: #d4edda; font-weight: bold;';
        } else if (stripos($name_lower, 'default') !== false) {
            $patterns_found['default']++;
            $row_style = 'background-color: #fff3cd;';
        } else if (stripos($name_lower, 'défaut') !== false) {
            $patterns_found['defaut']++;
            $row_style = 'background-color: #d1ecf1;';
        } else if (stripos($name_lower, 'top') !== false) {
            $patterns_found['top']++;
            $row_style = 'background-color: #cce5ff;';
        } else {
            $patterns_found['other']++;
            $row_style = '';
        }
        
        echo '<tr style="' . $row_style . '">';
        echo '<td><strong>' . $cat->id . '</strong></td>';
        echo '<td><code>' . s($cat->name) . '</code></td>';
        echo '<td>' . $context_name . '</td>';
        echo '<td style="text-align: center;">' . $q_count . '</td>';
        echo '<td style="text-align: center;">' . $subcat_count . '</td>';
        echo '<td style="font-size: 0.8em; max-width: 200px;">' . s(substr($cat->info ?: '-', 0, 50)) . '</td>';
        echo '<td style="text-align: center;">' . $has_default_for . '</td>';
        echo '<td style="text-align: center;">' . $has_defaut . '</td>';
        echo '<td style="text-align: center;">' . $has_top . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
    
    // Résumé des patterns
    echo html_writer::start_div('alert alert-success', ['style' => 'margin-top: 20px;']);
    echo '<h4>📊 Résumé des patterns trouvés dans les noms :</h4>';
    echo '<ul>';
    echo '<li><strong>"Default for"</strong> : ' . $patterns_found['default_for'] . ' catégorie(s)</li>';
    echo '<li><strong>"default"</strong> (autre) : ' . $patterns_found['default'] . ' catégorie(s)</li>';
    echo '<li><strong>"défaut"</strong> (français) : ' . $patterns_found['defaut'] . ' catégorie(s)</li>';
    echo '<li><strong>"top"</strong> : ' . $patterns_found['top'] . ' catégorie(s)</li>';
    echo '<li><strong>Autres noms</strong> : ' . $patterns_found['other'] . ' catégorie(s)</li>';
    echo '</ul>';
    echo html_writer::end_div();
}

// ===================================================================
// 2. ANALYSE DU PATTERN DE PROTECTION ACTUEL
// ===================================================================

echo html_writer::tag('h3', '2. Requête de protection actuelle', ['style' => 'margin-top: 40px;']);

echo html_writer::start_div('alert alert-warning');
echo '<strong>⚠️ Pattern de recherche actuel :</strong><br>';
echo '<code>WHERE name LIKE \'%Default for%\'</code><br>';
echo '<em>Cherche uniquement "Default for" (anglais, casse insensible)</em>';
echo html_writer::end_div();

// Tester différents patterns
$test_patterns = [
    'Default for' => '%Default for%',
    'défaut pour' => '%défaut pour%',
    'Par défaut' => '%Par défaut%',
    'default' => '%default%',
    'Top' => 'Top%'
];

echo '<h4>Test de différents patterns :</h4>';
echo '<table class="generaltable">';
echo '<thead><tr><th>Pattern</th><th>Catégories Trouvées</th></tr></thead><tbody>';

foreach ($test_patterns as $label => $pattern) {
    $count = $DB->count_records_sql("
        SELECT COUNT(*)
        FROM {question_categories}
        WHERE " . $DB->sql_like('name', ':pattern', false),
        ['pattern' => $pattern]
    );
    
    $color = $count > 0 ? 'green' : 'red';
    echo '<tr>';
    echo '<td><code>' . htmlspecialchars($pattern) . '</code></td>';
    echo '<td style="color: ' . $color . '; font-weight: bold; text-align: center;">' . $count . '</td>';
    echo '</tr>';
}

echo '</tbody></table>';

// ===================================================================
// 3. RECOMMANDATION
// ===================================================================

echo html_writer::start_div('alert alert-info', ['style' => 'margin-top: 30px; border-left: 4px solid #0066cc;']);
echo '<h4>💡 Interprétation :</h4>';

$total_default_for = $DB->count_records_sql("
    SELECT COUNT(*)
    FROM {question_categories}
    WHERE " . $DB->sql_like('name', ':pattern', false),
    ['pattern' => '%Default for%']
);

if ($total_default_for == 0) {
    echo '<p><strong style="color: #d9534f;">⚠️ Aucune catégorie "Default for" trouvée !</strong></p>';
    echo '<p>Cela peut signifier :</p>';
    echo '<ol>';
    echo '<li>Votre Moodle est en <strong>français</strong> et utilise un autre pattern (ex: "Top", "Par défaut")</li>';
    echo '<li>Les catégories par défaut ont été <strong>renommées</strong> manuellement</li>';
    echo '<li>Votre installation Moodle n\'utilise <strong>pas ce pattern</strong></li>';
    echo '</ol>';
    
    echo '<p><strong>🔧 Solution :</strong></p>';
    echo '<ul>';
    echo '<li>Regardez le tableau ci-dessus pour identifier le <strong>vrai pattern</strong> utilisé</li>';
    echo '<li>Si beaucoup de catégories racine avec "Top" → utilisez ce pattern</li>';
    echo '<li>Si beaucoup avec "défaut" → utilisez ce pattern en français</li>';
    echo '</ul>';
} else if ($total_default_for == 1) {
    echo '<p><strong style="color: #f0ad4e;">⚠️ Une seule catégorie "Default for" trouvée</strong></p>';
    echo '<p>C\'est <strong>inhabituel</strong>. Généralement, il devrait y en avoir une par cours.</p>';
    echo '<p><strong>Possibilités :</strong></p>';
    echo '<ol>';
    echo '<li>Vous n\'avez qu\'<strong>un seul cours</strong> dans votre Moodle</li>';
    echo '<li>Les autres catégories par défaut ont été <strong>renommées</strong></li>';
    echo '<li>Votre Moodle utilise un <strong>mix de patterns</strong> (anglais + français)</li>';
    echo '</ol>';
} else {
    echo '<p><strong style="color: #5cb85c;">✅ ' . $total_default_for . ' catégories "Default for" trouvées</strong></p>';
    echo '<p>C\'est normal. Moodle crée une catégorie par défaut pour chaque cours.</p>';
}

echo html_writer::end_div();

// ===================================================================
// 4. CATÉGORIES RACINE DANS CONTEXTES DE COURS
// ===================================================================

echo html_writer::tag('h3', '4. Catégories racine dans des contextes de cours', ['style' => 'margin-top: 40px;']);

$root_in_courses = $DB->get_records_sql("
    SELECT qc.*, ctx.contextlevel
    FROM {question_categories} qc
    INNER JOIN {context} ctx ON ctx.id = qc.contextid
    WHERE qc.parent = 0
    AND ctx.contextlevel = " . CONTEXT_COURSE . "
    ORDER BY qc.id DESC
    LIMIT 50
");

echo html_writer::div('<strong>' . count($root_in_courses) . '</strong> catégorie(s) racine dans des contextes de COURS (affichage limité à 50)', 'alert alert-info');

if (!empty($root_in_courses)) {
    echo '<p><strong>🛡️ TOUTES ces catégories devraient être protégées</strong> car elles sont à la racine d\'un cours.</p>';
    
    echo '<table class="generaltable">';
    echo '<thead><tr><th>ID</th><th>Nom EXACT</th><th>Questions</th><th>Sous-cat</th></tr></thead><tbody>';
    
    foreach ($root_in_courses as $cat) {
        $q_count = $DB->count_records_sql("
            SELECT COUNT(DISTINCT q.id)
            FROM {question} q
            INNER JOIN {question_versions} qv ON qv.questionid = q.id
            INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
            WHERE qbe.questioncategoryid = :categoryid
        ", ['categoryid' => $cat->id]);
        
        $subcat_count = $DB->count_records('question_categories', ['parent' => $cat->id]);
        
        echo '<tr>';
        echo '<td>' . $cat->id . '</td>';
        echo '<td><strong>' . s($cat->name) . '</strong></td>';
        echo '<td style="text-align: center;">' . $q_count . '</td>';
        echo '<td style="text-align: center;">' . $subcat_count . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
}

// ===================================================================
// 5. RECOMMANDATION DE CORRECTION
// ===================================================================

echo html_writer::start_div('alert alert-danger', ['style' => 'margin-top: 40px; border-left: 4px solid #d9534f;']);
echo '<h4>🚨 ACTION REQUISE</h4>';

$total_root_courses = count($root_in_courses);

if ($total_default_for < $total_root_courses) {
    echo '<p><strong>Problème détecté :</strong></p>';
    echo '<p>Vous avez <strong>' . $total_root_courses . ' catégories racine de cours</strong> mais seulement <strong>' . $total_default_for . ' catégorie(s)</strong> détectée(s) avec le pattern "Default for".</p>';
    
    echo '<p><strong>Cela signifie que ' . ($total_root_courses - $total_default_for) . ' catégorie(s) racine ne sont PAS protégées !</strong></p>';
    
    echo '<p><strong>Solution :</strong></p>';
    echo '<p>Le code de protection doit être modifié pour protéger <strong>TOUTES les catégories racine (parent=0) dans des contextes COURSE</strong>, indépendamment de leur nom.</p>';
    
    echo '<p><strong>Code actuel :</strong></p>';
    echo '<pre style="background: #f9f9f9; padding: 10px;">
// ❌ Protection basée sur le NOM
if (stripos($category->name, \'Default for\') !== false) {
    return "PROTÉGÉE";
}
</pre>';
    
    echo '<p><strong>Code corrigé nécessaire :</strong></p>';
    echo '<pre style="background: #d4edda; padding: 10px;">
// ✅ Protection basée sur parent=0 + contexte COURSE
if ($category->parent == 0) {
    $context = context::instance_by_id($category->contextid);
    if ($context && $context->contextlevel == CONTEXT_COURSE) {
        return "PROTÉGÉE : Catégorie racine de cours";
    }
}
</pre>';
    
} else {
    echo '<p><strong style="color: #5cb85c;">✅ Toutes les catégories racine de cours semblent protégées</strong></p>';
    echo '<p>Le pattern "Default for" couvre toutes les catégories racine.</p>';
}

echo html_writer::end_div();

echo $OUTPUT->footer();
