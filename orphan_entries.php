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
 * Page de gestion des entries orphelines
 *
 * @package    local_question_diagnostic
 * @copyright  2025 Question Diagnostic Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Sécurité
require_login();
if (!is_siteadmin()) {
    print_error('accessdenied', 'admin');
}

// Paramètres
$entryid = optional_param('id', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$targetcategoryid = optional_param('targetcategory', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);

// Configuration de la page
$PAGE->set_url(new moodle_url('/local/question_diagnostic/orphan_entries.php', ['id' => $entryid]));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('pluginname', 'local_question_diagnostic'));
$PAGE->set_heading(local_question_diagnostic_get_heading_with_version('Gestion des Entries Orphelines'));
$PAGE->set_pagelayout('report');
$PAGE->requires->css('/local/question_diagnostic/styles/main.css');

// Traiter les actions
if ($action === 'reassign' && $entryid && $targetcategoryid && $confirm) {
    require_sesskey();
    
    try {
        // Vérifier que la catégorie cible existe
        $target_category = $DB->get_record('question_categories', ['id' => $targetcategoryid], '*', MUST_EXIST);
        
        // Vérifier que l'entry existe et est bien orpheline
        $entry = $DB->get_record('question_bank_entries', ['id' => $entryid], '*', MUST_EXIST);
        $old_category_exists = $DB->record_exists('question_categories', ['id' => $entry->questioncategoryid]);
        
        if (!$old_category_exists) {
            // Réassigner l'entry vers la nouvelle catégorie
            $entry->questioncategoryid = $targetcategoryid;
            $DB->update_record('question_bank_entries', $entry);
            
            // Compter les questions concernées
            $question_count = $DB->count_records_sql("
                SELECT COUNT(DISTINCT q.id)
                FROM {question} q
                INNER JOIN {question_versions} qv ON qv.questionid = q.id
                WHERE qv.questionbankentryid = :entryid
            ", ['entryid' => $entryid]);
            
            redirect(
                new moodle_url('/local/question_diagnostic/orphan_entries.php'),
                "Entry #{$entryid} réassignée avec succès vers '{$target_category->name}' ({$question_count} question(s) récupérée(s))",
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        } else {
            redirect(
                new moodle_url('/local/question_diagnostic/orphan_entries.php', ['id' => $entryid]),
                "Cette entry n'est plus orpheline, sa catégorie existe maintenant.",
                null,
                \core\output\notification::NOTIFY_INFO
            );
        }
    } catch (Exception $e) {
        redirect(
            new moodle_url('/local/question_diagnostic/orphan_entries.php', ['id' => $entryid]),
            "Erreur lors de la réassignation : " . $e->getMessage(),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

// Début de la page
echo $OUTPUT->header();

// Breadcrumb et liens utiles
echo html_writer::start_div('breadcrumb-nav', ['style' => 'margin-bottom: 20px;']);
echo html_writer::link(
    new moodle_url('/local/question_diagnostic/index.php'),
    '← Retour au menu principal'
);
echo ' | ';
echo html_writer::link(
    new moodle_url('/local/question_diagnostic/test.php'),
    'Page de diagnostic'
);
echo ' | ';
echo html_writer::link(
    new moodle_url('/local/question_diagnostic/DATABASE_IMPACT.md'),
    '🛡️ Impact Base de Données',
    ['target' => '_blank', 'style' => 'font-weight: bold; color: #d9534f;']
);
echo html_writer::end_div();

// Alerte de sécurité en haut de page
if ($entryid == 0) {
    echo html_writer::start_div('alert alert-warning', ['style' => 'margin-bottom: 20px; border-left: 4px solid #d9534f;']);
    echo '<strong>🛡️ SÉCURITÉ DES DONNÉES</strong><br>';
    echo 'Cette page permet de <strong>modifier la base de données</strong>. ';
    echo 'Avant toute action, consultez la ';
    echo html_writer::link(
        new moodle_url('/local/question_diagnostic/DATABASE_IMPACT.md'),
        'documentation des impacts sur la BDD',
        ['target' => '_blank', 'style' => 'font-weight: bold; text-decoration: underline;']
    );
    echo ' pour connaître :<br>';
    echo '• Les tables modifiées<br>';
    echo '• Les commandes de backup recommandées<br>';
    echo '• Les procédures de restauration';
    echo html_writer::end_div();
}

// Si une entry spécifique est demandée
if ($entryid > 0) {
    
    // Récupérer l'entry
    $entry = $DB->get_record('question_bank_entries', ['id' => $entryid]);
    
    if (!$entry) {
        echo html_writer::div('Entry introuvable.', 'alert alert-danger');
        echo $OUTPUT->footer();
        exit;
    }
    
    // Vérifier si elle est encore orpheline
    $category_exists = $DB->record_exists('question_categories', ['id' => $entry->questioncategoryid]);
    
    if ($category_exists) {
        echo html_writer::div(
            '✅ Cette entry n\'est plus orpheline. Sa catégorie existe maintenant dans le système.',
            'alert alert-success'
        );
        
        $category = $DB->get_record('question_categories', ['id' => $entry->questioncategoryid]);
        echo html_writer::tag('p', "Catégorie actuelle : <strong>" . s($category->name) . "</strong> (ID: {$category->id})");
        
        echo html_writer::link(
            new moodle_url('/local/question_diagnostic/orphan_entries.php'),
            '← Retour à la liste des entries orphelines',
            ['class' => 'btn btn-secondary']
        );
        
        echo $OUTPUT->footer();
        exit;
    }
    
    echo html_writer::tag('h2', '🔍 Détails de l\'Entry Orpheline #' . $entryid);
    
    // Compter les questions AVANT d'afficher l'alerte
    $questions = $DB->get_records_sql("
        SELECT q.*, qv.version
        FROM {question} q
        INNER JOIN {question_versions} qv ON qv.questionid = q.id
        WHERE qv.questionbankentryid = :entryid
        ORDER BY qv.version DESC
    ", ['entryid' => $entryid]);
    
    $question_count = count($questions);
    
    if ($question_count == 0) {
        echo html_writer::start_div('alert alert-info');
        echo '<strong>ℹ️ Entry orpheline VIDE</strong><br>';
        echo 'Cette entry pointe vers la catégorie ID <strong>' . $entry->questioncategoryid . '</strong> qui n\'existe plus dans la base de données.<br>';
        echo '<strong>Important :</strong> Cette entry ne contient <strong>aucune question</strong>. Elle peut être ignorée ou supprimée.';
        echo html_writer::end_div();
    } else {
        echo html_writer::start_div('alert alert-warning');
        echo '<strong>⚠️ Entry orpheline détectée</strong><br>';
        echo 'Cette entry pointe vers la catégorie ID <strong>' . $entry->questioncategoryid . '</strong> qui n\'existe plus dans la base de données.<br>';
        echo '<strong>Impact :</strong> ' . $question_count . ' question(s) sont actuellement invisibles dans Moodle.';
        echo html_writer::end_div();
    }
    
    // Informations sur l'entry
    echo html_writer::tag('h3', '📋 Informations générales');
    echo '<table class="generaltable">';
    echo '<tr><th style="width: 200px;">Entry ID</th><td>' . $entry->id . '</td></tr>';
    echo '<tr><th>Catégorie ID (inexistante)</th><td style="color: red; font-weight: bold;">' . $entry->questioncategoryid . ' ❌</td></tr>';
    echo '<tr><th>ID Number</th><td>' . ($entry->idnumber ?: '-') . '</td></tr>';
    
    // Propriétaire
    if ($entry->ownerid) {
        $owner = $DB->get_record('user', ['id' => $entry->ownerid], 'firstname, lastname, email');
        if ($owner) {
            echo '<tr><th>Propriétaire</th><td>' . fullname($owner) . ' (' . $owner->email . ')</td></tr>';
        } else {
            echo '<tr><th>Propriétaire</th><td>ID: ' . $entry->ownerid . ' (utilisateur introuvable)</td></tr>';
        }
    }
    
    echo '</table>';
    
    // Questions liées à cette entry
    echo html_writer::tag('h3', '📝 Questions liées à cette entry', ['style' => 'margin-top: 30px;']);
    
    if ($questions && $question_count > 0) {
        echo '<table class="generaltable" style="width: 100%;">';
        echo '<thead><tr>
                <th>ID</th>
                <th>Nom de la question</th>
                <th>Type</th>
                <th>Version</th>
                <th>Cachée</th>
                <th>Créée le</th>
                <th>Modifiée le</th>
              </tr></thead>';
        echo '<tbody>';
        
        foreach ($questions as $question) {
            $hidden_badge = $question->hidden ? '<span class="badge badge-warning">Oui</span>' : '<span class="badge badge-success">Non</span>';
            
            echo '<tr>';
            echo '<td>' . $question->id . '</td>';
            echo '<td><strong>' . s($question->name) . '</strong></td>';
            echo '<td>' . $question->qtype . '</td>';
            echo '<td>' . $question->version . '</td>';
            echo '<td>' . $hidden_badge . '</td>';
            echo '<td>' . userdate($question->timecreated, '%d/%m/%Y') . '</td>';
            echo '<td>' . userdate($question->timemodified, '%d/%m/%Y') . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        
        echo html_writer::div(
            '<strong>' . count($questions) . '</strong> question(s) liée(s) à cette entry',
            'alert alert-info',
            ['style' => 'margin-top: 10px;']
        );
    } else {
        echo html_writer::div('Aucune question liée à cette entry.', 'alert alert-warning');
        
        // Si l'entry est vide, afficher un message explicatif
        echo html_writer::start_div('alert alert-info', ['style' => 'margin-top: 20px;']);
        echo '<h4>💡 Entry vide - Aucune action nécessaire</h4>';
        echo '<p>Cette entry ne contient aucune question. Cela signifie que :</p>';
        echo '<ul>';
        echo '<li>Les questions ont été supprimées manuellement</li>';
        echo '<li>Ou l\'entry a été créée mais jamais utilisée</li>';
        echo '</ul>';
        echo '<p><strong>Recommandation :</strong> Vous pouvez ignorer cette entry. Elle n\'affecte aucune question.</p>';
        echo html_writer::end_div();
        
        // Lien retour pour les entries vides
        echo html_writer::start_div('', ['style' => 'margin-top: 30px;']);
        echo html_writer::link(
            new moodle_url('/local/question_diagnostic/orphan_entries.php'),
            '← Retour à la liste des entries orphelines',
            ['class' => 'btn btn-secondary']
        );
        echo html_writer::end_div();
        
        echo $OUTPUT->footer();
        exit;
    }
    
    // Section réassignation (uniquement si l'entry contient des questions)
    echo html_writer::tag('h3', '🔧 Réassigner cette entry', ['style' => 'margin-top: 40px;']);
    
    // Si confirmation demandée
    if ($action === 'reassign' && $targetcategoryid && !$confirm) {
        require_sesskey();
        
        $target_category = $DB->get_record('question_categories', ['id' => $targetcategoryid]);
        
        if ($target_category) {
            echo html_writer::start_div('alert alert-warning', ['style' => 'margin: 20px 0;']);
            echo html_writer::tag('h4', '⚠️ Confirmation requise');
            echo html_writer::tag('p', 
                "Êtes-vous sûr de vouloir réassigner l'entry #{$entryid} vers la catégorie <strong>" . s($target_category->name) . "</strong> ?"
            );
             echo html_writer::tag('p', 
                'Cette action modifiera la catégorie de <strong>' . $question_count . ' question(s)</strong>.',
                ['style' => 'margin-top: 10px;']
             );
             echo html_writer::tag('p',
                'Les questions redeviendront immédiatement visibles dans la banque de questions.',
                ['style' => 'margin-top: 5px; color: green;']
             );
            echo html_writer::end_div();
            
            // Boutons
            echo html_writer::start_div('', ['style' => 'margin-top: 20px;']);
            echo html_writer::link(
                new moodle_url('/local/question_diagnostic/orphan_entries.php', [
                    'id' => $entryid,
                    'action' => 'reassign',
                    'targetcategory' => $targetcategoryid,
                    'confirm' => 1,
                    'sesskey' => sesskey()
                ]),
                '✅ Confirmer la réassignation',
                ['class' => 'btn btn-danger', 'style' => 'margin-right: 10px;']
            );
            echo html_writer::link(
                new moodle_url('/local/question_diagnostic/orphan_entries.php', ['id' => $entryid]),
                '❌ Annuler',
                ['class' => 'btn btn-secondary']
            );
            echo html_writer::end_div();
        }
    } else {
        // Formulaire de sélection de catégorie
        echo html_writer::start_div('alert alert-info');
        echo '<p><strong>💡 Où réassigner cette entry ?</strong></p>';
        echo '<p>Vous pouvez réassigner cette entry vers n\'importe quelle catégorie existante. ';
        echo 'Une catégorie nommée <strong>"Récupération"</strong> sera automatiquement suggérée si elle existe.</p>';
        echo html_writer::end_div();
        
        // Chercher la catégorie "Récupération" automatiquement
        $recovery_categories = $DB->get_records_sql("
            SELECT * FROM {question_categories}
            WHERE " . $DB->sql_like('name', ':pattern', false) . "
            ORDER BY id DESC
        ", ['pattern' => '%récupération%']);
        
        if ($recovery_categories) {
            echo html_writer::tag('h4', '✨ Catégories "Récupération" détectées');
            echo '<table class="generaltable" style="width: 100%; margin-top: 10px;">';
            echo '<thead><tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Contexte</th>
                    <th>Parent</th>
                    <th>Action</th>
                  </tr></thead>';
            echo '<tbody>';
            
            foreach ($recovery_categories as $cat) {
                try {
                    $context = context::instance_by_id($cat->contextid, IGNORE_MISSING);
                    $context_name = $context ? context_helper::get_level_name($context->contextlevel) : 'Inconnu';
                } catch (Exception $e) {
                    $context_name = 'Erreur';
                }
                
                $parent_name = '-';
                if ($cat->parent) {
                    $parent = $DB->get_record('question_categories', ['id' => $cat->parent], 'name');
                    $parent_name = $parent ? s($parent->name) : 'ID: ' . $cat->parent;
                }
                
                echo '<tr>';
                echo '<td>' . $cat->id . '</td>';
                echo '<td><strong>' . s($cat->name) . '</strong></td>';
                echo '<td>' . $context_name . '</td>';
                echo '<td>' . $parent_name . '</td>';
                echo '<td>';
                echo html_writer::link(
                    new moodle_url('/local/question_diagnostic/orphan_entries.php', [
                        'id' => $entryid,
                        'action' => 'reassign',
                        'targetcategory' => $cat->id,
                        'sesskey' => sesskey()
                    ]),
                    '→ Utiliser cette catégorie',
                    ['class' => 'btn btn-sm btn-primary']
                );
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        } else {
            echo html_writer::start_div('alert alert-warning', ['style' => 'margin-top: 20px;']);
            echo '<strong>⚠️ Aucune catégorie "Récupération" trouvée</strong><br>';
            echo 'Créez d\'abord une catégorie nommée "Récupération" dans votre Moodle, puis revenez ici.';
            echo html_writer::end_div();
        }
        
        // Lister toutes les catégories disponibles
        echo html_writer::tag('h4', '📂 Toutes les catégories disponibles', ['style' => 'margin-top: 30px;']);
        echo html_writer::tag('p', '<em>Vous pouvez aussi choisir n\'importe quelle autre catégorie :</em>');
        
        $all_categories = $DB->get_records('question_categories', null, 'name ASC', '*', 0, 50);
        
        if ($all_categories) {
            echo '<table class="generaltable" style="width: 100%; margin-top: 10px;">';
            echo '<thead><tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Contexte</th>
                    <th>Action</th>
                  </tr></thead>';
            echo '<tbody>';
            
            foreach ($all_categories as $cat) {
                try {
                    $context = context::instance_by_id($cat->contextid, IGNORE_MISSING);
                    $context_name = $context ? context_helper::get_level_name($context->contextlevel) : 'Inconnu';
                } catch (Exception $e) {
                    $context_name = 'Erreur';
                }
                
                echo '<tr>';
                echo '<td>' . $cat->id . '</td>';
                echo '<td><strong>' . s($cat->name) . '</strong></td>';
                echo '<td>' . $context_name . '</td>';
                echo '<td>';
                echo html_writer::link(
                    new moodle_url('/local/question_diagnostic/orphan_entries.php', [
                        'id' => $entryid,
                        'action' => 'reassign',
                        'targetcategory' => $cat->id,
                        'sesskey' => sesskey()
                    ]),
                    '→ Utiliser',
                    ['class' => 'btn btn-sm btn-secondary']
                );
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
            echo html_writer::tag('p', '<em>Affichage limité aux 50 premières catégories</em>', ['style' => 'margin-top: 10px; color: #666;']);
        }
    }
    
} else {
    // Afficher la liste de toutes les entries orphelines
    echo html_writer::tag('h2', '🗂️ Liste des Entries Orphelines');
    
    echo html_writer::start_div('alert alert-info');
    echo '<p><strong>💡 Qu\'est-ce qu\'une entry orpheline ?</strong></p>';
    echo '<p>Une entry orpheline est une entrée dans la table <code>question_bank_entries</code> qui pointe vers une catégorie qui n\'existe plus. ';
    echo 'Les questions liées à ces entries sont "invisibles" dans l\'interface standard de Moodle.</p>';
    echo html_writer::end_div();
    
    // Compter les entries orphelines
    $orphan_count = $DB->count_records_sql("
        SELECT COUNT(qbe.id)
        FROM {question_bank_entries} qbe
        LEFT JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
        WHERE qc.id IS NULL
    ");
    
    // Séparer les entries avec questions des entries vides
    $orphan_entries_all = $DB->get_records_sql("
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
            ORDER BY question_count DESC, qbe.id DESC
        ");
        
        // Filtrer les entries avec questions et sans questions
        $orphan_entries_with_questions = array_filter($orphan_entries_all, function($entry) {
            return $entry->question_count > 0;
        });
        
        $orphan_entries_empty = array_filter($orphan_entries_all, function($entry) {
            return $entry->question_count == 0;
        });
        
        $count_with_questions = count($orphan_entries_with_questions);
        $count_empty = count($orphan_entries_empty);
        
        echo html_writer::tag('h3', "📊 {$orphan_count} entry(ies) orpheline(s) détectée(s)");
        
        // Résumé
        echo html_writer::start_div('alert alert-info', ['style' => 'margin-bottom: 20px;']);
        echo '<strong>Résumé :</strong><br>';
        echo '• <strong>' . $count_with_questions . '</strong> entries contiennent des questions (à récupérer)<br>';
        echo '• <strong>' . $count_empty . '</strong> entries sont vides (peuvent être ignorées)';
        echo html_writer::end_div();
        
        // Afficher d'abord les entries AVEC questions
        if ($count_with_questions > 0) {
            echo html_writer::tag('h3', '🔴 Entries avec questions à récupérer (' . $count_with_questions . ')', ['style' => 'color: #d9534f;']);
            echo '<table class="generaltable" style="width: 100%; margin-top: 10px; border: 2px solid #d9534f;">';
            echo '<thead>
                    <tr style="background-color: #f2dede;">
                        <th>Entry ID</th>
                        <th>Catégorie ID<br>(inexistante)</th>
                        <th>Questions</th>
                        <th>Versions</th>
                        <th>Exemple de question</th>
                        <th>Type</th>
                        <th>Créée le</th>
                        <th>Actions</th>
                    </tr>
                  </thead>';
            echo '<tbody>';
            
            foreach ($orphan_entries_with_questions as $entry) {
                $created_date = $entry->created_time ? userdate($entry->created_time, '%d/%m/%Y') : '-';
                $question_name = $entry->first_question_name ? s($entry->first_question_name) : '-';
                if (strlen($question_name) > 50) {
                    $question_name = substr($question_name, 0, 50) . '...';
                }
                
                echo '<tr style="background-color: #fcf8e3;">';
                echo '<td><strong>' . $entry->id . '</strong></td>';
                echo '<td style="color: red; font-weight: bold;">' . $entry->questioncategoryid . ' ❌</td>';
                echo '<td style="text-align: center;"><strong style="color: #d9534f;">' . $entry->question_count . '</strong></td>';
                echo '<td style="text-align: center;">' . $entry->version_count . '</td>';
                echo '<td style="font-size: 0.9em;">' . $question_name . '</td>';
                echo '<td>' . ($entry->question_type ?: '-') . '</td>';
                echo '<td style="font-size: 0.9em;">' . $created_date . '</td>';
                echo '<td>';
                echo html_writer::link(
                    new moodle_url('/local/question_diagnostic/orphan_entries.php', ['id' => $entry->id]),
                    '🔧 Récupérer →',
                    ['class' => 'btn btn-sm btn-danger', 'style' => 'font-weight: bold;']
                );
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        } else {
            echo html_writer::div('✅ Aucune entry avec questions à récupérer !', 'alert alert-success');
        }
        
        // Afficher ensuite les entries VIDES (moins importantes)
        if ($count_empty > 0) {
            echo html_writer::tag('h3', 'ℹ️ Entries vides (' . $count_empty . ') - Peuvent être ignorées', ['style' => 'color: #5bc0de; margin-top: 40px;']);
            echo html_writer::start_div('alert alert-info');
            echo 'Ces entries ne contiennent aucune question. Elles peuvent être ignorées sans risque.';
            echo html_writer::end_div();
            
            echo '<table class="generaltable" style="width: 100%; margin-top: 10px; opacity: 0.7;">';
            echo '<thead>
                    <tr>
                        <th>Entry ID</th>
                        <th>Catégorie ID<br>(inexistante)</th>
                        <th>Questions</th>
                        <th>Créée le</th>
                        <th>Actions</th>
                    </tr>
                  </thead>';
            echo '<tbody>';
            
            foreach ($orphan_entries_empty as $entry) {
                $created_date = $entry->created_time ? userdate($entry->created_time, '%d/%m/%Y') : '-';
                
                echo '<tr>';
                echo '<td>' . $entry->id . '</td>';
                echo '<td style="color: #999;">' . $entry->questioncategoryid . '</td>';
                echo '<td style="text-align: center; color: #999;"><em>0</em></td>';
                echo '<td style="font-size: 0.9em;">' . $created_date . '</td>';
                echo '<td>';
                echo html_writer::link(
                    new moodle_url('/local/question_diagnostic/orphan_entries.php', ['id' => $entry->id]),
                    'Voir détails',
                    ['class' => 'btn btn-sm btn-secondary', 'style' => 'font-size: 0.85em;']
                );
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        }
        
        // Instructions
        echo html_writer::start_div('alert alert-success', ['style' => 'margin-top: 30px;']);
        echo '<h4>✨ Comment récupérer ces questions ?</h4>';
        echo '<ol>';
        echo '<li><strong>📖 Consultez d\'abord</strong> la ';
        echo html_writer::link(
            new moodle_url('/local/question_diagnostic/DATABASE_IMPACT.md'),
            'documentation DATABASE_IMPACT.md',
            ['target' => '_blank', 'style' => 'font-weight: bold;']
        );
        echo ' pour comprendre les impacts</li>';
        echo '<li><strong>💾 Faites un backup</strong> de votre base de données (recommandé)</li>';
        echo '<li><strong>Créez une catégorie "Récupération"</strong> dans votre Moodle si elle n\'existe pas encore</li>';
        echo '<li><strong>Cliquez sur "🔧 Récupérer"</strong> pour chaque entry avec questions</li>';
        echo '<li><strong>Réassignez l\'entry</strong> vers la catégorie "Récupération" (détection automatique)</li>';
        echo '<li>Les questions redeviendront <strong>visibles et utilisables</strong> dans Moodle ✅</li>';
        echo '</ol>';
        echo html_writer::end_div();
        
    } else {
        echo html_writer::div('✅ Aucune entry orpheline détectée !', 'alert alert-success');
    }
}

// Fin de la page
echo $OUTPUT->footer();
