<?php
/**
 * Script de vérification des catégories par défaut de Moodle
 * À exécuter depuis /local/question_diagnostic/
 */

require_once(__DIR__ . '/../../config.php');

require_login();
if (!is_siteadmin()) {
    throw new \moodle_exception('accessdenied', 'admin');
}

$PAGE->set_url(new moodle_url('/local/question_diagnostic/check_default_categories.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Vérification Catégories Par Défaut');
$PAGE->set_heading('Vérification Catégories Par Défaut');

echo $OUTPUT->header();

// Afficher le badge de version
echo local_question_diagnostic_render_version_badge();

echo html_writer::tag('h2', '🔍 Analyse des Catégories Par Défaut de Moodle');

echo html_writer::start_div('alert alert-info');
echo '<strong>Objectif :</strong> Identifier les catégories qui ne doivent JAMAIS être supprimées car elles sont créées automatiquement par Moodle pour chaque cours/contexte.';
echo html_writer::end_div();

// ===================================================================
// 1. CATÉGORIES AVEC "Default for" DANS LE NOM
// ===================================================================

echo html_writer::tag('h3', '1. Catégories contenant "Default for" dans le nom');

$default_for_categories = $DB->get_records_sql("
    SELECT qc.*, ctx.contextlevel
    FROM {question_categories} qc
    LEFT JOIN {context} ctx ON ctx.id = qc.contextid
    WHERE " . $DB->sql_like('qc.name', ':pattern1', false) . "
    OR " . $DB->sql_like('qc.name', ':pattern2', false) . "
    ORDER BY qc.contextid, qc.id
", [
    'pattern1' => '%Default for%',
    'pattern2' => '%Par défaut pour%'
]);

if (!empty($default_for_categories)) {
    echo html_writer::start_div('alert alert-warning');
    echo '<strong>⚠️ ' . count($default_for_categories) . ' catégorie(s) par défaut trouvée(s)</strong><br>';
    echo 'Ces catégories sont créées automatiquement par Moodle et ne devraient JAMAIS être supprimées.';
    echo html_writer::end_div();
    
    echo '<table class="generaltable" style="width: 100%;">';
    echo '<thead><tr>
            <th>ID</th>
            <th>Nom</th>
            <th>Contexte</th>
            <th>Parent</th>
            <th>Questions</th>
            <th>Sous-cat</th>
            <th>Info (description)</th>
          </tr></thead>';
    echo '<tbody>';
    
    foreach ($default_for_categories as $cat) {
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
        
        $row_style = ($q_count == 0 && $subcat_count == 0) ? 'background-color: #fcf8e3;' : '';
        
        echo '<tr style="' . $row_style . '">';
        echo '<td><strong>' . $cat->id . '</strong></td>';
        echo '<td>' . s($cat->name) . '</td>';
        echo '<td>' . $context_name . '</td>';
        echo '<td>' . ($cat->parent ?: '-') . '</td>';
        echo '<td style="text-align: center;">' . $q_count . '</td>';
        echo '<td style="text-align: center;">' . $subcat_count . '</td>';
        echo '<td style="font-size: 0.85em; max-width: 300px;">' . s(substr($cat->info ?: '-', 0, 100)) . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
} else {
    echo html_writer::div('✅ Aucune catégorie "Default for" trouvée.', 'alert alert-success');
}

// ===================================================================
// 2. CATÉGORIES À LA RACINE (parent = 0) PAR CONTEXTE
// ===================================================================

echo html_writer::tag('h3', '2. Catégories à la racine (parent = 0) par contexte', ['style' => 'margin-top: 40px;']);

$root_categories = $DB->get_records_sql("
    SELECT qc.*, ctx.contextlevel
    FROM {question_categories} qc
    LEFT JOIN {context} ctx ON ctx.id = qc.contextid
    WHERE qc.parent = 0
    ORDER BY qc.contextid, qc.sortorder, qc.id
");

echo html_writer::start_div('alert alert-info');
echo '<strong>ℹ️ ' . count($root_categories) . ' catégorie(s) à la racine (parent = 0)</strong><br>';
echo 'Ces catégories sont souvent les catégories par défaut de chaque contexte (cours, système). Moodle crée automatiquement une catégorie racine pour chaque cours.';
echo html_writer::end_div();

// Grouper par contexte
$by_context = [];
foreach ($root_categories as $cat) {
    $by_context[$cat->contextid][] = $cat;
}

echo html_writer::tag('h4', 'Répartition par contexte (' . count($by_context) . ' contextes distincts)');

echo '<table class="generaltable" style="width: 100%;">';
echo '<thead><tr>
        <th>Contexte ID</th>
        <th>Type</th>
        <th>Nb catégories racine</th>
        <th>Détails</th>
      </tr></thead>';
echo '<tbody>';

foreach ($by_context as $contextid => $cats) {
    // Déterminer le type de contexte
    try {
        $context = context::instance_by_id($contextid, IGNORE_MISSING);
        $context_type = $context ? context_helper::get_level_name($context->contextlevel) : 'Inconnu';
    } catch (Exception $e) {
        $context_type = 'Erreur/Orphelin';
    }
    
    echo '<tr>';
    echo '<td><strong>' . $contextid . '</strong></td>';
    echo '<td>' . $context_type . '</td>';
    echo '<td style="text-align: center;"><strong>' . count($cats) . '</strong></td>';
    echo '<td>';
    foreach ($cats as $cat) {
        $q_count = $DB->count_records_sql("
            SELECT COUNT(DISTINCT q.id)
            FROM {question} q
            INNER JOIN {question_versions} qv ON qv.questionid = q.id
            INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
            WHERE qbe.questioncategoryid = :categoryid
        ", ['categoryid' => $cat->id]);
        
        $subcat_count = $DB->count_records('question_categories', ['parent' => $cat->id]);
        
        $badge_class = ($q_count == 0 && $subcat_count == 0) ? 'badge-warning' : 'badge-success';
        
        echo '<div style="margin: 5px 0; padding: 5px; background: #f9f9f9; border-left: 3px solid #ccc;">';
        echo '<strong>' . s($cat->name) . '</strong> (ID: ' . $cat->id . ') ';
        echo '<span class="badge ' . $badge_class . '">' . $q_count . ' Q, ' . $subcat_count . ' Sous-cat</span>';
        echo '</div>';
    }
    echo '</td>';
    echo '</tr>';
}

echo '</tbody></table>';

// ===================================================================
// 3. ANALYSE DES CATÉGORIES AVEC CHAMP INFO NON VIDE
// ===================================================================

echo html_writer::tag('h3', '3. Catégories avec description (champ info)', ['style' => 'margin-top: 40px;']);

$with_info = $DB->get_records_sql("
    SELECT * FROM {question_categories}
    WHERE info IS NOT NULL AND info != ''
    ORDER BY id DESC
    LIMIT 50
");

if (!empty($with_info)) {
    echo html_writer::div(count($with_info) . ' catégorie(s) ont une description (affichage limité à 50)', 'alert alert-info');
    
    echo '<table class="generaltable" style="width: 100%;">';
    echo '<thead><tr>
            <th>ID</th>
            <th>Nom</th>
            <th>Parent</th>
            <th>Questions</th>
            <th>Description (info)</th>
          </tr></thead>';
    echo '<tbody>';
    
    foreach ($with_info as $cat) {
        $q_count = $DB->count_records_sql("
            SELECT COUNT(DISTINCT q.id)
            FROM {question} q
            INNER JOIN {question_versions} qv ON qv.questionid = q.id
            INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
            WHERE qbe.questioncategoryid = :categoryid
        ", ['categoryid' => $cat->id]);
        
        echo '<tr>';
        echo '<td>' . $cat->id . '</td>';
        echo '<td><strong>' . s($cat->name) . '</strong></td>';
        echo '<td>' . ($cat->parent ?: 'Racine (0)') . '</td>';
        echo '<td style="text-align: center;">' . $q_count . '</td>';
        echo '<td style="font-size: 0.85em; max-width: 400px;">' . htmlspecialchars(substr($cat->info, 0, 150)) . (strlen($cat->info) > 150 ? '...' : '') . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
} else {
    echo html_writer::div('Aucune catégorie avec description trouvée.', 'alert alert-info');
}

// ===================================================================
// 4. RECOMMANDATIONS
// ===================================================================

echo html_writer::start_div('alert alert-success', ['style' => 'margin-top: 40px; border-left: 4px solid #5cb85c;']);
echo html_writer::tag('h3', '💡 Recommandations de Protection');

echo '<strong>Catégories à NE JAMAIS supprimer :</strong>';
echo '<ol>';
echo '<li><strong>Catégories "Default for..."</strong> : Créées automatiquement par Moodle pour chaque cours</li>';
echo '<li><strong>Catégories racine (parent=0) avec des sous-catégories</strong> : Structure organisationnelle</li>';
echo '<li><strong>Catégories avec une description (info)</strong> : Indique une utilisation intentionnelle</li>';
echo '<li><strong>Catégories dans des contextes de cours actifs</strong> : Même si vides, elles peuvent être utilisées ultérieurement</li>';
echo '</ol>';

echo '<strong>Catégories SÛRES à supprimer :</strong>';
echo '<ul>';
echo '<li>Catégories vides (0 questions, 0 sous-catégories)</li>';
echo '<li>SANS description (info vide)</li>';
echo '<li>PAS "Default for..."</li>';
echo '<li>PAS à la racine SI aucune sous-catégorie</li>';
echo '<li>Dans des contextes orphelins (cours supprimés)</li>';
echo '</ul>';

echo html_writer::end_div();

// ===================================================================
// 5. COMPTAGE FINAL AVEC PROTECTIONS
// ===================================================================

echo html_writer::tag('h3', '5. Comptage avec protections recommandées', ['style' => 'margin-top: 40px;']);

// Catégories vraiment supprimables (avec protections)
$safe_to_delete = $DB->count_records_sql("
    SELECT COUNT(qc.id)
    FROM {question_categories} qc
    WHERE qc.id NOT IN (
        SELECT DISTINCT qbe.questioncategoryid
        FROM {question_bank_entries} qbe
        INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
    )
    AND qc.id NOT IN (
        SELECT DISTINCT parent
        FROM {question_categories}
        WHERE parent IS NOT NULL AND parent > 0
    )
    AND qc.parent != 0
    AND (qc.info IS NULL OR qc.info = '')
    AND " . $DB->sql_like('qc.name', ':pattern', true, true, true) . "
", ['pattern' => '%Default for%']);

$protected_default = $DB->count_records_sql("
    SELECT COUNT(*)
    FROM {question_categories}
    WHERE " . $DB->sql_like('name', ':pattern', false) . "
", ['pattern' => '%Default for%']);

$protected_root_with_children = $DB->count_records_sql("
    SELECT COUNT(DISTINCT qc.id)
    FROM {question_categories} qc
    WHERE qc.parent = 0
    AND qc.id IN (
        SELECT DISTINCT parent
        FROM {question_categories}
        WHERE parent IS NOT NULL AND parent > 0
    )
");

$protected_with_info = $DB->count_records_sql("
    SELECT COUNT(*)
    FROM {question_categories}
    WHERE info IS NOT NULL AND info != ''
");

echo '<table class="generaltable" style="width: 100%;">';
echo '<thead><tr>
        <th>Type</th>
        <th>Nombre</th>
        <th>Action</th>
      </tr></thead>';
echo '<tbody>';

echo '<tr style="background-color: #fcf8e3;">';
echo '<td><strong>Catégories "Default for..."</strong></td>';
echo '<td style="text-align: center; font-size: 18px;"><strong>' . $protected_default . '</strong></td>';
echo '<td>🛡️ <strong>PROTÉGÉES</strong> - Ne jamais supprimer</td>';
echo '</tr>';

echo '<tr style="background-color: #fcf8e3;">';
echo '<td><strong>Catégories racine avec enfants</strong></td>';
echo '<td style="text-align: center; font-size: 18px;"><strong>' . $protected_root_with_children . '</strong></td>';
echo '<td>🛡️ <strong>PROTÉGÉES</strong> - Organisation</td>';
echo '</tr>';

echo '<tr style="background-color: #fcf8e3;">';
echo '<td><strong>Catégories avec description</strong></td>';
echo '<td style="text-align: center; font-size: 18px;"><strong>' . $protected_with_info . '</strong></td>';
echo '<td>⚠️ <strong>À VÉRIFIER</strong> - Usage intentionnel probable</td>';
echo '</tr>';

echo '<tr style="background-color: #dff0d8;">';
echo '<td><strong>Catégories SÛRES à supprimer</strong></td>';
echo '<td style="text-align: center; font-size: 18px; color: green;"><strong>' . $safe_to_delete . '</strong></td>';
echo '<td>✅ <strong>SUPPRIMABLES</strong> - Pas de protection</td>';
echo '</tr>';

echo '</tbody></table>';

// ===================================================================
// RECOMMANDATION FINALE
// ===================================================================

echo html_writer::start_div('alert alert-danger', ['style' => 'margin-top: 30px; border-left: 4px solid #d9534f;']);
echo html_writer::tag('h4', '🚨 RECOMMANDATION CRITIQUE');
echo '<p><strong>Le plugin doit être modifié pour PROTÉGER automatiquement :</strong></p>';
echo '<ol>';
echo '<li>Les catégories contenant "Default for" dans le nom</li>';
echo '<li>Les catégories racine (parent=0) qui ont des enfants</li>';
echo '<li>Les catégories avec une description (champ info non vide)</li>';
echo '</ol>';
echo '<p><strong>Risque actuel :</strong> Le plugin pourrait permettre la suppression de catégories critiques pour Moodle.</p>';
echo html_writer::end_div();

echo $OUTPUT->footer();

