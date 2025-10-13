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

// Afficher le badge de version
echo local_question_diagnostic_render_version_badge();

// Contenu principal
echo html_writer::tag('h2', '🔍 Diagnostic de la Base de Données');

echo html_writer::start_div('alert alert-info');
echo '<strong>Vérification de la structure Moodle 4.x</strong>';
echo html_writer::end_div();

// Récupérer les stats de base
$stats = new stdClass();
$stats->total_categories = $DB->count_records('question_categories');

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

// Tester plusieurs catégories aléatoires
echo html_writer::tag('h3', '3. Test sur 10 catégories aléatoires');

// Récupérer 10 catégories aléatoires (compatible MySQL et PostgreSQL)
$random_sql = "SELECT * FROM {question_categories} ORDER BY ";
if ($DB->get_dbfamily() == 'postgres') {
    $random_sql .= "RANDOM()";
} else {
    $random_sql .= "RAND()";
}
$random_sql .= " LIMIT 10";

$random_categories = $DB->get_records_sql($random_sql);

if ($random_categories) {
    echo '<table class="generaltable" style="width: 100%; margin-top: 10px;">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom de la catégorie</th>
                    <th>Contexte</th>
                    <th>Méthode ancienne</th>
                    <th>Méthode SANS correction</th>
                    <th>✅ Méthode AVEC correction</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($random_categories as $cat) {
        // Récupérer le nom du contexte
        try {
            $context = context::instance_by_id($cat->contextid, IGNORE_MISSING);
            $context_name = $context ? context_helper::get_level_name($context->contextlevel) : 'Inconnu';
        } catch (Exception $e) {
            $context_name = 'Erreur';
        }
        
        // Méthode ancienne (Moodle 3.x) - compter directement dans question
        $old_sql = "SELECT COUNT(*) FROM {question} WHERE category = :categoryid";
        try {
            $old_count = $DB->count_records_sql($old_sql, ['categoryid' => $cat->id]);
            $old_result = $old_count;
            $old_style = '';
        } catch (Exception $e) {
            $old_result = 'ERREUR';
            $old_style = 'color: red;';
        }
        
        // Méthode nouvelle SANS correction (avec JOIN simple)
        $sql_without_fix = "SELECT COUNT(DISTINCT q.id) 
                           FROM {question} q
                           JOIN {question_versions} qv ON qv.questionid = q.id
                           JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                           WHERE qbe.questioncategoryid = :categoryid";
        try {
            $count_without_fix = $DB->count_records_sql($sql_without_fix, ['categoryid' => $cat->id]);
            $without_result = $count_without_fix;
            $without_style = 'color: orange;';
        } catch (Exception $e) {
            $without_result = 'ERREUR';
            $without_style = 'color: red;';
        }
        
        // Méthode nouvelle AVEC correction (avec INNER JOIN + validation catégorie)
        $sql_with_fix = "SELECT COUNT(DISTINCT q.id) 
                        FROM {question} q
                        INNER JOIN {question_versions} qv ON qv.questionid = q.id
                        INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                        INNER JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                        WHERE qbe.questioncategoryid = :categoryid";
        try {
            $count_with_fix = $DB->count_records_sql($sql_with_fix, ['categoryid' => $cat->id]);
            $with_result = $count_with_fix;
            $with_style = $count_with_fix > 0 ? 'color: green; font-weight: bold;' : '';
        } catch (Exception $e) {
            $with_result = 'ERREUR';
            $with_style = 'color: red;';
        }
        
        // Générer l'URL vers la banque de questions pour cette catégorie
        try {
            $context = context::instance_by_id($cat->contextid, IGNORE_MISSING);
            $courseid = 0; // Par défaut, système
            
            // Si c'est un contexte de cours, récupérer l'ID du cours
            if ($context && $context->contextlevel == CONTEXT_COURSE) {
                $courseid = $context->instanceid;
            } else if ($context && $context->contextlevel == CONTEXT_MODULE) {
                // Si c'est un module, remonter au cours parent
                $coursecontext = $context->get_course_context(false);
                if ($coursecontext) {
                    $courseid = $coursecontext->instanceid;
                }
            }
            
            // Construire l'URL : /question/edit.php?courseid=X&cat=categoryid,contextid
            $question_bank_url = new moodle_url('/question/edit.php', [
                'courseid' => $courseid,
                'cat' => $cat->id . ',' . $cat->contextid
            ]);
            
            $category_link = html_writer::link(
                $question_bank_url,
                '<strong>' . s($cat->name) . '</strong>',
                ['title' => 'Ouvrir cette catégorie dans la banque de questions', 'target' => '_blank']
            );
        } catch (Exception $e) {
            // Si erreur, afficher juste le nom sans lien
            $category_link = '<strong>' . s($cat->name) . '</strong>';
        }
        
        echo '<tr>
                <td>' . $cat->id . '</td>
                <td>' . $category_link . ' 🔗</td>
                <td>' . $context_name . '</td>
                <td style="' . $old_style . '">' . $old_result . '</td>
                <td style="' . $without_style . '">' . $without_result . '</td>
                <td style="' . $with_style . '">' . $with_result . ' ✅</td>
              </tr>';
    }
    
    echo '</tbody></table>';
    
    echo html_writer::start_div('alert alert-info', ['style' => 'margin-top: 15px;']);
    echo '<strong>Légende :</strong><br>';
    echo '• <strong>Méthode ancienne</strong> : Colonne "category" dans table question (Moodle 3.x)<br>';
    echo '• <strong>Sans correction</strong> : JOIN simple (peut retourner 0 à cause des entries orphelines)<br>';
    echo '• <strong>✅ Avec correction</strong> : INNER JOIN avec validation (comptage correct)<br>';
    echo '• <strong>🔗 Noms de catégories</strong> : Cliquables pour ouvrir directement dans la banque de questions';
    echo html_writer::end_div();
} else {
    echo html_writer::div('Aucune catégorie trouvée pour le test.', 'alert alert-warning');
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
    
    if ($entries_without_category > 0) {
        echo html_writer::tag('p', 
            "⚠️ Entries sans catégorie : {$entries_without_category}", 
            ['style' => 'color: red; font-weight: bold;']
        );
        
        echo html_writer::start_div('alert alert-danger', ['style' => 'margin: 20px 0;']);
        echo html_writer::tag('h4', '🚨 PROBLÈME IDENTIFIÉ');
        echo html_writer::tag('p', 
            "Ces {$entries_without_category} entries orphelines empêchent le comptage correct des questions dans les catégories."
        );
        echo html_writer::tag('p', 
            "<strong>Conséquence :</strong> Toutes les catégories apparaissent vides alors qu'elles contiennent des questions.",
            ['style' => 'margin-top: 10px;']
        );
        echo html_writer::end_div();
        
        // Afficher les détails des entries orphelines avec plus d'informations
        echo html_writer::tag('h4', '📋 Détails des entries orphelines (10 premières)');
        $orphan_entries = $DB->get_records_sql("
            SELECT qbe.id, 
                   qbe.questioncategoryid, 
                   qbe.idnumber, 
                   qbe.ownerid,
                   COUNT(DISTINCT qv.id) as version_count,
                   COUNT(DISTINCT q.id) as question_count,
                   MIN(q.name) as first_question_name,
                   MIN(q.qtype) as question_type,
                   MIN(q.timecreated) as created_time
            FROM {question_bank_entries} qbe
            LEFT JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
            LEFT JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
            LEFT JOIN {question} q ON q.id = qv.questionid
            WHERE qc.id IS NULL
            GROUP BY qbe.id, qbe.questioncategoryid, qbe.idnumber, qbe.ownerid
            ORDER BY qbe.id DESC
            LIMIT 10
        ");
        
        if ($orphan_entries) {
            echo '<table class="generaltable" style="width: 100%; margin-top: 10px;">
                    <thead>
                        <tr>
                            <th>Entry ID</th>
                            <th>Catégorie ID<br>(inexistante)</th>
                            <th>Questions<br>liées</th>
                            <th>Versions</th>
                            <th>Exemple de question</th>
                            <th>Type</th>
                            <th>Propriétaire</th>
                            <th>Créée le</th>
                        </tr>
                    </thead>
                    <tbody>';
            foreach ($orphan_entries as $entry) {
                // Récupérer le nom du propriétaire
                $owner_name = '-';
                if ($entry->ownerid) {
                    try {
                        $owner = $DB->get_record('user', ['id' => $entry->ownerid], 'firstname, lastname');
                        if ($owner) {
                            $owner_name = $owner->firstname . ' ' . $owner->lastname;
                        }
                    } catch (Exception $e) {
                        $owner_name = 'ID: ' . $entry->ownerid;
                    }
                }
                
                // Formater la date
                $created_date = '-';
                if ($entry->created_time) {
                    $created_date = date('d/m/Y', $entry->created_time);
                }
                
                // Tronquer le nom de la question si trop long
                $question_name = $entry->first_question_name ? s($entry->first_question_name) : '-';
                if (strlen($question_name) > 50) {
                    $question_name = substr($question_name, 0, 50) . '...';
                }
                
                // Créer le lien vers la page de détails
                $detail_url = new moodle_url('/local/question_diagnostic/orphan_entries.php', ['id' => $entry->id]);
                $entry_id_link = html_writer::link($detail_url, '<strong>' . $entry->id . '</strong>', 
                    ['style' => 'color: #0066cc;', 'title' => 'Cliquez pour voir les détails et réassigner']);
                
                echo '<tr style="cursor: pointer;" onclick="window.location.href=\'' . $detail_url->out() . '\'">
                        <td>' . $entry_id_link . '</td>
                        <td style="color: red; font-weight: bold;">' . $entry->questioncategoryid . ' ❌</td>
                        <td style="text-align: center;"><strong>' . $entry->question_count . '</strong></td>
                        <td style="text-align: center;">' . $entry->version_count . '</td>
                        <td style="font-size: 0.9em;">' . $question_name . '</td>
                        <td>' . ($entry->question_type ?: '-') . '</td>
                        <td style="font-size: 0.9em;">' . $owner_name . '</td>
                        <td style="font-size: 0.9em;">' . $created_date . '</td>
                      </tr>';
            }
            echo '</tbody></table>';
            
            echo html_writer::start_div('alert alert-warning', ['style' => 'margin-top: 15px;']);
            echo '<strong>💡 Que faire avec ces entries orphelines ?</strong><br>';
            echo '• Ces ' . $entries_without_category . ' entries pointent vers des catégories qui ont été supprimées<br>';
            echo '• Les questions associées existent toujours mais sont "invisibles" dans l\'interface<br>';
            echo '• <strong>👉 Cliquez sur un Entry ID</strong> pour voir les détails et réassigner vers une catégorie "Récupération"';
            echo html_writer::end_div();
            
            // Bouton pour voir toutes les entries orphelines
            echo html_writer::start_div('', ['style' => 'margin-top: 20px; text-align: center;']);
            echo html_writer::link(
                new moodle_url('/local/question_diagnostic/orphan_entries.php'),
                '🔧 Gérer toutes les entries orphelines (' . $entries_without_category . ')',
                ['class' => 'btn btn-lg btn-primary']
            );
            echo html_writer::end_div();
        }
        
        echo html_writer::start_div('alert alert-info', ['style' => 'margin-top: 20px;']);
        echo html_writer::tag('h4', '✅ Solution appliquée');
        echo html_writer::tag('p', 
            "Le plugin a été modifié pour utiliser des INNER JOIN au lieu de JOIN/LEFT JOIN. " .
            "Cela permet d'exclure automatiquement les entries orphelines du comptage."
        );
        echo html_writer::tag('p', 
            "<strong>Résultat :</strong> Les catégories afficheront maintenant le nombre correct de questions.",
            ['style' => 'margin-top: 10px;']
        );
        echo html_writer::end_div();
        
    } else {
        echo html_writer::tag('p', "✅ Entries sans catégorie : 0", ['style' => 'color: green;']);
    }
    
} catch (Exception $e) {
    echo html_writer::tag('p', 'Erreur : ' . $e->getMessage(), ['style' => 'color: red;']);
}

// Tester le comptage après correction
echo html_writer::tag('h3', '6. Test du comptage après correction');
try {
    // Compter les questions avec la nouvelle méthode
    $sql = "SELECT COUNT(DISTINCT q.id)
            FROM {question} q
            INNER JOIN {question_versions} qv ON qv.questionid = q.id
            INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
            INNER JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid";
    $valid_questions = $DB->count_records_sql($sql);
    
    echo html_writer::tag('p', 
        "Questions valides (liées à des catégories existantes) : <strong>{$valid_questions}</strong>",
        ['style' => 'color: green; font-size: 1.1em;']
    );
    
    $total_questions = $DB->count_records('question');
    $orphan_questions = $total_questions - $valid_questions;
    
    if ($orphan_questions > 0) {
        echo html_writer::tag('p', 
            "Questions orphelines (entries cassées) : <strong>{$orphan_questions}</strong>",
            ['style' => 'color: orange;']
        );
    }
    
    // Résumé des catégories avec questions
    $categories_with_questions_sql = "
        SELECT COUNT(DISTINCT qbe.questioncategoryid) as cat_count
        FROM {question_bank_entries} qbe
        INNER JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
        INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
        INNER JOIN {question} q ON q.id = qv.questionid
    ";
    $cat_with_q = $DB->get_record_sql($categories_with_questions_sql);
    $cat_count = $cat_with_q ? $cat_with_q->cat_count : 0;
    
    echo html_writer::start_div('alert alert-success', ['style' => 'margin-top: 15px;']);
    echo '<h5>📊 Résumé global après correction :</h5>';
    echo '• <strong>' . $valid_questions . '</strong> questions valides comptabilisées<br>';
    echo '• <strong>' . $cat_count . '</strong> catégories contiennent au moins une question<br>';
    echo '• <strong>' . ($stats->total_categories - $cat_count) . '</strong> catégories sans questions directes<br>';
    echo '<small style="color: #666; margin-left: 15px;">(Inclut les catégories parentes/conteneurs avec sous-catégories)</small>';
    echo html_writer::end_div();
    
} catch (Exception $e) {
    echo html_writer::tag('p', 'Erreur : ' . $e->getMessage(), ['style' => 'color: red;']);
}

// ========================================
// 6. SCAN DES QUESTIONS ORPHELINES
// ========================================
echo html_writer::start_div('', ['style' => 'margin-top: 50px; padding: 20px; background-color: #f9f9f9; border: 2px solid #d9534f; border-radius: 8px;']);
echo html_writer::tag('h3', '6. 🔍 Scan des Questions Orphelines', ['style' => 'color: #d9534f; margin-top: 0;']);

try {
    // Compter les questions orphelines (via entries sans catégorie)
    $orphan_questions_count = $DB->count_records_sql("
        SELECT COUNT(DISTINCT q.id)
        FROM {question} q
        INNER JOIN {question_versions} qv ON qv.questionid = q.id
        INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
        LEFT JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
        WHERE qc.id IS NULL
    ");
    
    // Compter les entries orphelines (avec et sans questions)
    $orphan_entries_with_questions = $DB->count_records_sql("
        SELECT COUNT(DISTINCT qbe.id)
        FROM {question_bank_entries} qbe
        LEFT JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
        INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
        WHERE qc.id IS NULL
    ");
    
    $orphan_entries_empty = $DB->count_records_sql("
        SELECT COUNT(qbe.id)
        FROM {question_bank_entries} qbe
        LEFT JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
        LEFT JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
        WHERE qc.id IS NULL AND qv.id IS NULL
    ");
    
    $total_orphan_entries = $orphan_entries_with_questions + $orphan_entries_empty;
    
    // Affichage des résultats
    if ($orphan_questions_count > 0 || $total_orphan_entries > 0) {
        echo html_writer::start_div('alert alert-danger', ['style' => 'margin-bottom: 20px;']);
        echo html_writer::tag('h4', '🚨 QUESTIONS ORPHELINES DÉTECTÉES', ['style' => 'margin-top: 0;']);
        echo html_writer::tag('p', 
            "<strong>{$orphan_questions_count} question(s)</strong> sont orphelines (invisibles dans la banque de questions Moodle).",
            ['style' => 'font-size: 16px; margin: 10px 0;']
        );
        echo html_writer::end_div();
        
        // Tableau récapitulatif
        echo '<table class="generaltable" style="width: 100%; margin-top: 15px;">';
        echo '<thead>
                <tr style="background-color: #f2dede;">
                    <th>Type</th>
                    <th>Nombre</th>
                    <th>Description</th>
                    <th>Action</th>
                </tr>
              </thead>';
        echo '<tbody>';
        
        // Ligne 1 : Questions orphelines
        echo '<tr>';
        echo '<td><strong style="color: #d9534f;">Questions orphelines</strong></td>';
        echo '<td style="text-align: center; font-size: 18px;"><strong style="color: #d9534f;">' . $orphan_questions_count . '</strong></td>';
        echo '<td>Questions liées à des entries pointant vers des catégories inexistantes</td>';
        echo '<td>';
        if ($orphan_questions_count > 0) {
            echo html_writer::link(
                new moodle_url('/local/question_diagnostic/orphan_entries.php'),
                '🔧 Récupérer ces questions',
                ['class' => 'btn btn-danger', 'style' => 'font-weight: bold;']
            );
        } else {
            echo '<span style="color: #5cb85c;">✓ Aucune</span>';
        }
        echo '</td>';
        echo '</tr>';
        
        // Ligne 2 : Entries avec questions
        echo '<tr style="background-color: #fcf8e3;">';
        echo '<td><strong>Entries avec questions</strong></td>';
        echo '<td style="text-align: center; font-size: 16px;"><strong>' . $orphan_entries_with_questions . '</strong></td>';
        echo '<td>Entries orphelines contenant des questions à récupérer</td>';
        echo '<td>';
        if ($orphan_entries_with_questions > 0) {
            echo html_writer::link(
                new moodle_url('/local/question_diagnostic/orphan_entries.php'),
                '→ Gérer',
                ['class' => 'btn btn-warning btn-sm']
            );
        } else {
            echo '<span style="color: #5cb85c;">✓ Aucune</span>';
        }
        echo '</td>';
        echo '</tr>';
        
        // Ligne 3 : Entries vides
        echo '<tr style="opacity: 0.7;">';
        echo '<td><strong>Entries vides</strong></td>';
        echo '<td style="text-align: center;">' . $orphan_entries_empty . '</td>';
        echo '<td>Entries orphelines sans questions (peuvent être supprimées)</td>';
        echo '<td>';
        if ($orphan_entries_empty > 0) {
            echo html_writer::link(
                new moodle_url('/local/question_diagnostic/orphan_entries.php'),
                '🗑️ Supprimer',
                ['class' => 'btn btn-secondary btn-sm']
            );
        } else {
            echo '<span style="color: #5cb85c;">✓ Aucune</span>';
        }
        echo '</td>';
        echo '</tr>';
        
        echo '</tbody></table>';
        
        // Instructions
        echo html_writer::start_div('alert alert-info', ['style' => 'margin-top: 20px;']);
        echo '<h5 style="margin-top: 0;">💡 Comment résoudre ce problème ?</h5>';
        echo '<ol style="margin-bottom: 0;">';
        echo '<li><strong>Consultez DATABASE_IMPACT.md</strong> pour comprendre les impacts sur la base de données</li>';
        echo '<li><strong>Faites un backup</strong> de votre base de données avant toute action</li>';
        echo '<li><strong>Cliquez sur "🔧 Récupérer ces questions"</strong> pour accéder à l\'outil de gestion</li>';
        echo '<li><strong>Créez une catégorie "Récupération"</strong> dans votre Moodle si ce n\'est pas déjà fait</li>';
        echo '<li><strong>Réassignez les entries avec questions</strong> vers la catégorie "Récupération"</li>';
        echo '<li><strong>Supprimez les entries vides</strong> pour nettoyer la base de données</li>';
        echo '<li><strong>Vérifiez les questions récupérées</strong> dans la banque de questions Moodle</li>';
        echo '</ol>';
        echo html_writer::end_div();
        
        // Statistiques supplémentaires
        if ($orphan_questions_count > 0) {
            // Récupérer quelques exemples de questions orphelines
            $sample_questions = $DB->get_records_sql("
                SELECT q.id, q.name, q.qtype, qbe.questioncategoryid as orphan_category_id
                FROM {question} q
                INNER JOIN {question_versions} qv ON qv.questionid = q.id
                INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                LEFT JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                WHERE qc.id IS NULL
                LIMIT 5
            ");
            
            if ($sample_questions) {
                echo html_writer::start_div('', ['style' => 'margin-top: 20px; padding: 15px; background-color: #fff; border: 1px solid #ddd; border-radius: 4px;']);
                echo html_writer::tag('h5', '📋 Exemples de questions orphelines (5 premières)', ['style' => 'margin-top: 0;']);
                echo '<table class="generaltable" style="width: 100%; font-size: 0.9em;">';
                echo '<thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom de la question</th>
                            <th>Type</th>
                            <th>Catégorie orpheline (ID)</th>
                        </tr>
                      </thead>';
                echo '<tbody>';
                
                foreach ($sample_questions as $question) {
                    $question_name = s($question->name);
                    if (strlen($question_name) > 60) {
                        $question_name = substr($question_name, 0, 60) . '...';
                    }
                    
                    echo '<tr>';
                    echo '<td>' . $question->id . '</td>';
                    echo '<td>' . $question_name . '</td>';
                    echo '<td>' . $question->qtype . '</td>';
                    echo '<td style="color: red; font-weight: bold;">' . $question->orphan_category_id . ' ❌</td>';
                    echo '</tr>';
                }
                
                echo '</tbody></table>';
                echo html_writer::end_div();
            }
        }
        
    } else {
        // Aucune question orpheline
        echo html_writer::start_div('alert alert-success');
        echo html_writer::tag('h4', '✅ AUCUNE QUESTION ORPHELINE', ['style' => 'margin-top: 0;']);
        echo html_writer::tag('p', 
            'Toutes les questions sont correctement liées à des catégories existantes.',
            ['style' => 'margin-bottom: 0;']
        );
        echo html_writer::end_div();
        
        echo '<table class="generaltable" style="width: 100%; margin-top: 15px;">';
        echo '<tbody>';
        echo '<tr>';
        echo '<td><strong>Questions orphelines</strong></td>';
        echo '<td style="text-align: center; color: #5cb85c; font-weight: bold;">0 ✓</td>';
        echo '</tr>';
        echo '<tr>';
        echo '<td><strong>Entries avec questions</strong></td>';
        echo '<td style="text-align: center; color: #5cb85c; font-weight: bold;">0 ✓</td>';
        echo '</tr>';
        echo '<tr>';
        echo '<td><strong>Entries vides</strong></td>';
        echo '<td style="text-align: center; color: #5cb85c; font-weight: bold;">0 ✓</td>';
        echo '</tr>';
        echo '</tbody></table>';
    }
    
} catch (Exception $e) {
    echo html_writer::tag('p', 'Erreur lors du scan : ' . $e->getMessage(), ['style' => 'color: red;']);
}

echo html_writer::end_div();

// Fin de la page
echo $OUTPUT->footer();

