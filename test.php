<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Page de test pour le plugin Question Diagnostic
 *
 * @package    local_question_diagnostic
 * @copyright  2025 Question Diagnostic Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// Sécurité
require_login();
if (!is_siteadmin()) {
    print_error('accessdenied', 'admin');
}

// Configuration de la page
$PAGE->set_url(new moodle_url('/local/question_diagnostic/test.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('test_page_title', 'local_question_diagnostic'));
$PAGE->set_heading(get_string('test_page_heading', 'local_question_diagnostic'));

// Début de la page
echo $OUTPUT->header();

// Contenu principal
echo html_writer::tag('h2', '🔍 Diagnostic de la Base de Données');

echo html_writer::start_div('alert alert-info');
echo '<strong>Vérification de la structure Moodle 4.x</strong>';
echo html_writer::end_div();

// Vérifier les tables
echo html_writer::tag('h3', '1. Vérification des tables');

$tables_to_check = ['question', 'question_categories', 'question_bank_entries', 'question_versions'];
foreach ($tables_to_check as $table) {
    try {
        $count = $DB->count_records($table);
        echo html_writer::tag('p', "✅ Table <code>{$table}</code> : {$count} enregistrements", ['style' => 'color: green;']);
    } catch (Exception $e) {
        echo html_writer::tag('p', "❌ Table <code>{$table}</code> : ERREUR - " . $e->getMessage(), ['style' => 'color: red;']);
    }
}

// Vérifier les colonnes de question_bank_entries
echo html_writer::tag('h3', '2. Structure de question_bank_entries');
try {
    $columns = $DB->get_columns('question_bank_entries');
    echo '<ul>';
    foreach ($columns as $column) {
        echo html_writer::tag('li', "<code>{$column->name}</code> ({$column->type})");
    }
    echo '</ul>';
} catch (Exception $e) {
    echo html_writer::tag('p', '❌ Erreur : ' . $e->getMessage(), ['style' => 'color: red;']);
}

// Vérifier une catégorie spécifique
echo html_writer::tag('h3', '3. Test sur une catégorie');
$test_category = $DB->get_record('question_categories', [], '*', IGNORE_MULTIPLE);
if ($test_category) {
    echo html_writer::tag('p', "Catégorie test : <strong>{$test_category->name}</strong> (ID: {$test_category->id})");
    
    // Méthode ancienne (Moodle 3.x) - compter directement dans question
    $old_sql = "SELECT COUNT(*) FROM {question} WHERE category = :categoryid";
    try {
        $old_count = $DB->count_records_sql($old_sql, ['categoryid' => $test_category->id]);
        echo html_writer::tag('p', "Méthode ancienne (question.category) : {$old_count} questions", ['style' => 'color: blue;']);
    } catch (Exception $e) {
        echo html_writer::tag('p', "Méthode ancienne : ERREUR - " . $e->getMessage(), ['style' => 'color: red;']);
    }
    
    // Méthode nouvelle (Moodle 4.x) - via question_bank_entries
    $new_sql = "SELECT COUNT(DISTINCT q.id) 
                FROM {question} q
                JOIN {question_versions} qv ON qv.questionid = q.id
                JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                WHERE qbe.questioncategoryid = :categoryid";
    try {
        $new_count = $DB->count_records_sql($new_sql, ['categoryid' => $test_category->id]);
        echo html_writer::tag('p', "Méthode nouvelle (question_bank_entries) : {$new_count} questions", ['style' => 'color: blue;']);
    } catch (Exception $e) {
        echo html_writer::tag('p', "Méthode nouvelle : ERREUR - " . $e->getMessage(), ['style' => 'color: red;']);
    }
}

// Vérifier la structure de la table question
echo html_writer::tag('h3', '4. Colonnes de la table question');
try {
    $columns = $DB->get_columns('question');
    $has_category = isset($columns['category']);
    
    if ($has_category) {
        echo html_writer::tag('p', '✅ La colonne <code>category</code> existe dans la table question', ['style' => 'color: green;']);
        
        // Compter combien de questions ont une catégorie définie
        $with_category = $DB->count_records_select('question', 'category > 0');
        echo html_writer::tag('p', "Questions avec category définie : {$with_category}");
    } else {
        echo html_writer::tag('p', '❌ La colonne <code>category</code> n\'existe PAS dans la table question', ['style' => 'color: red;']);
        echo html_writer::tag('p', 'Cela confirme que vous utilisez Moodle 4.0+', ['style' => 'color: orange;']);
    }
} catch (Exception $e) {
    echo html_writer::tag('p', 'Erreur : ' . $e->getMessage(), ['style' => 'color: red;']);
}

// Vérifier les relations
echo html_writer::tag('h3', '5. Vérification des relations');
try {
    // Compter les questions sans version
    $questions_without_version = $DB->count_records_sql("
        SELECT COUNT(q.id)
        FROM {question} q
        LEFT JOIN {question_versions} qv ON qv.questionid = q.id
        WHERE qv.id IS NULL
    ");
    echo html_writer::tag('p', "Questions sans version : {$questions_without_version}");
    
    // Compter les versions sans entry
    $versions_without_entry = $DB->count_records_sql("
        SELECT COUNT(qv.id)
        FROM {question_versions} qv
        LEFT JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
        WHERE qbe.id IS NULL
    ");
    echo html_writer::tag('p', "Versions sans entry : {$versions_without_entry}");
    
    // Compter les entries sans catégorie
    $entries_without_category = $DB->count_records_sql("
        SELECT COUNT(qbe.id)
        FROM {question_bank_entries} qbe
        LEFT JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
        WHERE qc.id IS NULL
    ");
    echo html_writer::tag('p', "Entries sans catégorie : {$entries_without_category}");
    
} catch (Exception $e) {
    echo html_writer::tag('p', 'Erreur : ' . $e->getMessage(), ['style' => 'color: red;']);
}

// Fin de la page
echo $OUTPUT->footer();

