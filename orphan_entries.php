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
$action = optional_param('action', '', PARAM_ALPHANUMEXT); // ALPHANUMEXT permet les underscores
$targetcategoryid = optional_param('targetcategory', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);
$selectedentries = optional_param_array('entries', [], PARAM_INT); // Pour sélection multiple

// Debug temporaire (à retirer après) - Affichage visible à l'écran
$debug_info = [];
$debug_info[] = "Action reçue: '$action'";
$debug_info[] = "Entries count: " . count($selectedentries);
$debug_info[] = "Confirm: $confirm";
$debug_info[] = "Target category: $targetcategoryid";
if (!empty($selectedentries)) {
    $debug_info[] = "Entries IDs: " . implode(', ', $selectedentries);
}

// Configuration de la page
$PAGE->set_url(new moodle_url('/local/question_diagnostic/orphan_entries.php', ['id' => $entryid]));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('pluginname', 'local_question_diagnostic'));
$PAGE->set_heading(local_question_diagnostic_get_heading_with_version('Gestion des Entries Orphelines'));
$PAGE->set_pagelayout('report');
$PAGE->requires->css('/local/question_diagnostic/styles/main.css');

// Traiter les actions
// Action groupée : réassigner plusieurs entries
if ($action === 'bulk_reassign' && !empty($selectedentries) && $targetcategoryid && $confirm) {
    require_sesskey();
    
    try {
        // Vérifier que la catégorie cible existe
        $target_category = $DB->get_record('question_categories', ['id' => $targetcategoryid], '*', MUST_EXIST);
        
        $success_count = 0;
        $total_questions = 0;
        $errors = [];
        
        foreach ($selectedentries as $entry_id) {
            try {
                $entry = $DB->get_record('question_bank_entries', ['id' => $entry_id]);
                if (!$entry) {
                    $errors[] = "Entry #{$entry_id} : introuvable";
                    continue;
                }
                
                // Vérifier que l'entry est toujours orpheline
                $old_category_exists = $DB->record_exists('question_categories', ['id' => $entry->questioncategoryid]);
                
                if (!$old_category_exists) {
                    // Compter les questions avant réassignation
                    $question_count = $DB->count_records_sql("
                        SELECT COUNT(DISTINCT q.id)
                        FROM {question} q
                        INNER JOIN {question_versions} qv ON qv.questionid = q.id
                        WHERE qv.questionbankentryid = :entryid
                    ", ['entryid' => $entry_id]);
                    
                    // Réassigner l'entry vers la nouvelle catégorie
                    $entry->questioncategoryid = $targetcategoryid;
                    $DB->update_record('question_bank_entries', $entry);
                    
                    $success_count++;
                    $total_questions += $question_count;
                } else {
                    $errors[] = "Entry #{$entry_id} : n'est plus orpheline";
                }
            } catch (Exception $e) {
                $errors[] = "Entry #{$entry_id} : " . $e->getMessage();
            }
        }
        
        // Message de résultat
        $message = "{$success_count} entry(ies) réassignée(s) avec succès vers '{$target_category->name}'. ";
        $message .= "{$total_questions} question(s) récupérée(s).";
        
        if (!empty($errors)) {
            $message .= " Erreurs : " . implode(', ', $errors);
        }
        
        redirect(
            new moodle_url('/local/question_diagnostic/orphan_entries.php'),
            $message,
            null,
            empty($errors) ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_WARNING
        );
        
    } catch (Exception $e) {
        redirect(
            new moodle_url('/local/question_diagnostic/orphan_entries.php'),
            "Erreur lors de la réassignation groupée : " . $e->getMessage(),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

// Action groupée : supprimer les entries vides (0 questions)
if ($action === 'bulk_delete_empty' && !empty($selectedentries) && $confirm) {
    require_sesskey();
    
    try {
        $success_count = 0;
        $errors = [];
        
        foreach ($selectedentries as $entry_id) {
            try {
                $entry = $DB->get_record('question_bank_entries', ['id' => $entry_id]);
                if (!$entry) {
                    $errors[] = "Entry #{$entry_id} : introuvable";
                    continue;
                }
                
                // Vérifier que l'entry est bien orpheline
                $old_category_exists = $DB->record_exists('question_categories', ['id' => $entry->questioncategoryid]);
                
                if (!$old_category_exists) {
                    // SÉCURITÉ : Vérifier que l'entry est bien VIDE (0 questions)
                    $question_count = $DB->count_records_sql("
                        SELECT COUNT(DISTINCT q.id)
                        FROM {question} q
                        INNER JOIN {question_versions} qv ON qv.questionid = q.id
                        WHERE qv.questionbankentryid = :entryid
                    ", ['entryid' => $entry_id]);
                    
                    if ($question_count == 0) {
                        // Supprimer les versions liées (si existantes, normalement aucune)
                        $DB->delete_records('question_versions', ['questionbankentryid' => $entry_id]);
                        
                        // Supprimer l'entry
                        $DB->delete_records('question_bank_entries', ['id' => $entry_id]);
                        
                        $success_count++;
                    } else {
                        $errors[] = "Entry #{$entry_id} : contient {$question_count} question(s), NON SUPPRIMÉE par sécurité";
                    }
                } else {
                    $errors[] = "Entry #{$entry_id} : n'est plus orpheline";
                }
            } catch (Exception $e) {
                $errors[] = "Entry #{$entry_id} : " . $e->getMessage();
            }
        }
        
        // Message de résultat
        $message = "🗑️ {$success_count} entry(ies) vide(s) supprimée(s) avec succès.";
        
        if (!empty($errors)) {
            $message .= " Erreurs : " . implode(', ', $errors);
        }
        
        redirect(
            new moodle_url('/local/question_diagnostic/orphan_entries.php'),
            $message,
            null,
            empty($errors) ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_WARNING
        );
        
    } catch (Exception $e) {
        redirect(
            new moodle_url('/local/question_diagnostic/orphan_entries.php'),
            "Erreur lors de la suppression groupée : " . $e->getMessage(),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

// Action individuelle : réassigner une entry
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

// DEBUG TEMPORAIRE - Afficher les paramètres reçus
if (!empty($debug_info)) {
    echo html_writer::start_div('alert alert-info', ['style' => 'background-color: #fff3cd; border: 2px solid #ff6b6b; margin-bottom: 20px;']);
    echo '<h4 style="color: #d9534f;">🔍 DEBUG - Paramètres reçus</h4>';
    echo '<ul style="margin: 0; padding-left: 20px;">';
    foreach ($debug_info as $info) {
        echo '<li>' . $info . '</li>';
    }
    echo '</ul>';
    echo html_writer::end_div();
}

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
        
        // Vérifier s'il y a des entries orphelines
        if ($orphan_count > 0) {
            echo html_writer::tag('h3', "📊 {$orphan_count} entry(ies) orpheline(s) détectée(s)");
        
        // Résumé
        echo html_writer::start_div('alert alert-info', ['style' => 'margin-bottom: 20px;']);
        echo '<strong>Résumé :</strong><br>';
        echo '• <strong>' . $count_with_questions . '</strong> entries contiennent des questions (à récupérer)<br>';
        echo '• <strong>' . $count_empty . '</strong> entries sont vides (peuvent être ignorées)';
        echo html_writer::end_div();
        
        // Page de confirmation pour actions groupées
        if ($action === 'bulk_reassign' && !empty($selectedentries) && $targetcategoryid && !$confirm) {
            require_sesskey();
            
            $target_category = $DB->get_record('question_categories', ['id' => $targetcategoryid]);
            
            if ($target_category) {
                echo html_writer::tag('h3', '⚠️ Confirmation de réassignation groupée');
                
                echo html_writer::start_div('alert alert-warning', ['style' => 'margin: 20px 0;']);
                echo html_writer::tag('h4', '⚠️ Confirmation requise');
                echo html_writer::tag('p', 
                    "Vous êtes sur le point de réassigner <strong>" . count($selectedentries) . " entry(ies)</strong> vers la catégorie <strong>" . s($target_category->name) . "</strong>."
                );
                echo html_writer::end_div();
                
                // Tableau des entries sélectionnées avec comptage des questions
                echo html_writer::tag('h4', '📋 Entries sélectionnées');
                echo '<table class="generaltable" style="width: 100%; margin-top: 10px;">';
                echo '<thead><tr>
                        <th>Entry ID</th>
                        <th>Catégorie ID (inexistante)</th>
                        <th>Questions</th>
                        <th>Exemple de question</th>
                      </tr></thead>';
                echo '<tbody>';
                
                $total_questions_to_recover = 0;
                
                foreach ($selectedentries as $entry_id) {
                    $entry = $DB->get_record('question_bank_entries', ['id' => $entry_id]);
                    if ($entry) {
                        // Compter les questions
                        $question_count = $DB->count_records_sql("
                            SELECT COUNT(DISTINCT q.id)
                            FROM {question} q
                            INNER JOIN {question_versions} qv ON qv.questionid = q.id
                            WHERE qv.questionbankentryid = :entryid
                        ", ['entryid' => $entry_id]);
                        
                        $total_questions_to_recover += $question_count;
                        
                        // Récupérer exemple de question
                        $sample_question = $DB->get_record_sql("
                            SELECT q.name
                            FROM {question} q
                            INNER JOIN {question_versions} qv ON qv.questionid = q.id
                            WHERE qv.questionbankentryid = :entryid
                            LIMIT 1
                        ", ['entryid' => $entry_id]);
                        
                        $question_name = $sample_question ? s($sample_question->name) : '-';
                        if (strlen($question_name) > 50) {
                            $question_name = substr($question_name, 0, 50) . '...';
                        }
                        
                        echo '<tr>';
                        echo '<td><strong>' . $entry_id . '</strong></td>';
                        echo '<td style="color: red;">' . $entry->questioncategoryid . ' ❌</td>';
                        echo '<td style="text-align: center;"><strong>' . $question_count . '</strong></td>';
                        echo '<td style="font-size: 0.9em;">' . $question_name . '</td>';
                        echo '</tr>';
                    }
                }
                
                echo '</tbody></table>';
                
                echo html_writer::start_div('alert alert-info', ['style' => 'margin-top: 15px;']);
                echo '<strong>📊 Récapitulatif :</strong><br>';
                echo '• <strong>' . count($selectedentries) . '</strong> entry(ies) seront réassignées<br>';
                echo '• <strong>' . $total_questions_to_recover . '</strong> question(s) seront récupérées<br>';
                echo '• Catégorie cible : <strong>' . s($target_category->name) . '</strong>';
                echo html_writer::end_div();
                
                // Boutons de confirmation
                echo html_writer::start_div('', ['style' => 'margin-top: 20px;']);
                
                // Formulaire de confirmation avec les IDs sélectionnés
                echo '<form method="post" action="' . new moodle_url('/local/question_diagnostic/orphan_entries.php') . '">';
                echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
                echo '<input type="hidden" name="action" value="bulk_reassign">';
                echo '<input type="hidden" name="targetcategory" value="' . $targetcategoryid . '">';
                echo '<input type="hidden" name="confirm" value="1">';
                foreach ($selectedentries as $entry_id) {
                    echo '<input type="hidden" name="entries[]" value="' . $entry_id . '">';
                }
                echo '<button type="submit" class="btn btn-danger" style="margin-right: 10px;">✅ Confirmer la réassignation groupée</button>';
                echo '</form>';
                
                echo html_writer::link(
                    new moodle_url('/local/question_diagnostic/orphan_entries.php'),
                    '❌ Annuler',
                    ['class' => 'btn btn-secondary', 'style' => 'margin-left: 10px;']
                );
                echo html_writer::end_div();
                
                echo $OUTPUT->footer();
                exit;
            }
        }
        
        // Page de confirmation pour suppression groupée des entries vides
        if ($action === 'bulk_delete_empty' && !empty($selectedentries) && !$confirm) {
            // Note: On ne vérifie pas sesskey ici car on affiche juste la confirmation
            // Le sesskey sera vérifié lors de la suppression effective
            
            echo html_writer::tag('h3', '⚠️ Confirmation de suppression groupée');
            
            echo html_writer::start_div('alert alert-danger', ['style' => 'margin: 20px 0;']);
            echo html_writer::tag('h4', '🗑️ Suppression définitive');
            echo html_writer::tag('p', 
                "Vous êtes sur le point de supprimer <strong>" . count($selectedentries) . " entry(ies) VIDE(S)</strong> de la base de données."
            );
            echo html_writer::tag('p', '<strong>⚠️ ATTENTION :</strong> Cette action est IRRÉVERSIBLE.', ['style' => 'color: #d9534f; font-weight: bold;']);
            echo html_writer::end_div();
            
            // Tableau des entries sélectionnées
            echo html_writer::tag('h4', '📋 Entries à supprimer');
            echo '<table class="generaltable" style="width: 100%; margin-top: 10px;">';
            echo '<thead><tr>
                    <th>Entry ID</th>
                    <th>Catégorie ID (inexistante)</th>
                    <th>Questions</th>
                    <th>Statut</th>
                  </tr></thead>';
            echo '<tbody>';
            
            $verified_empty_count = 0;
            $has_questions_warning = [];
            
            foreach ($selectedentries as $entry_id) {
                $entry = $DB->get_record('question_bank_entries', ['id' => $entry_id]);
                if ($entry) {
                    // Double vérification de sécurité
                    $question_count = $DB->count_records_sql("
                        SELECT COUNT(DISTINCT q.id)
                        FROM {question} q
                        INNER JOIN {question_versions} qv ON qv.questionid = q.id
                        WHERE qv.questionbankentryid = :entryid
                    ", ['entryid' => $entry_id]);
                    
                    if ($question_count == 0) {
                        $verified_empty_count++;
                        $status = '<span class="badge badge-success">✓ Vide (sûr)</span>';
                        $status_style = '';
                    } else {
                        $status = '<span class="badge badge-danger">⚠️ Contient ' . $question_count . ' question(s)</span>';
                        $status_style = 'background-color: #f2dede;';
                        $has_questions_warning[] = $entry_id;
                    }
                    
                    echo '<tr style="' . $status_style . '">';
                    echo '<td><strong>' . $entry_id . '</strong></td>';
                    echo '<td style="color: red;">' . $entry->questioncategoryid . ' ❌</td>';
                    echo '<td style="text-align: center;">' . $question_count . '</td>';
                    echo '<td>' . $status . '</td>';
                    echo '</tr>';
                }
            }
            
            echo '</tbody></table>';
            
            // Avertissement si des entries contiennent des questions
            if (!empty($has_questions_warning)) {
                echo html_writer::start_div('alert alert-danger', ['style' => 'margin-top: 15px;']);
                echo '<strong>⚠️ AVERTISSEMENT :</strong> ' . count($has_questions_warning) . ' entry(ies) contiennent des questions et NE SERONT PAS SUPPRIMÉES par sécurité.';
                echo html_writer::end_div();
            }
            
            // Récapitulatif
            echo html_writer::start_div('alert alert-info', ['style' => 'margin-top: 15px;']);
            echo '<strong>📊 Récapitulatif :</strong><br>';
            echo '• <strong>' . $verified_empty_count . '</strong> entry(ies) vides seront supprimées<br>';
            if (!empty($has_questions_warning)) {
                echo '• <strong>' . count($has_questions_warning) . '</strong> entry(ies) avec questions seront IGNORÉES<br>';
            }
            echo '• Tables modifiées : <code>question_bank_entries</code>, <code>question_versions</code>';
            echo html_writer::end_div();
            
            echo html_writer::start_div('alert alert-warning', ['style' => 'margin-top: 15px;']);
            echo '<strong>💡 Impact :</strong> Suppression d\'entries orphelines vides n\'affecte aucune question. ';
            echo 'C\'est une opération de nettoyage sûre pour libérer de l\'espace dans la base de données.';
            echo html_writer::end_div();
            
            // Boutons de confirmation
            echo html_writer::start_div('', ['style' => 'margin-top: 20px;']);
            
            // Formulaire de confirmation avec les IDs sélectionnés
            echo '<form method="post" action="' . new moodle_url('/local/question_diagnostic/orphan_entries.php') . '">';
            echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
            echo '<input type="hidden" name="action" value="bulk_delete_empty">';
            echo '<input type="hidden" name="confirm" value="1">';
            foreach ($selectedentries as $entry_id) {
                echo '<input type="hidden" name="entries[]" value="' . $entry_id . '">';
            }
            echo '<button type="submit" class="btn btn-danger" style="margin-right: 10px;">🗑️ Confirmer la suppression groupée</button>';
            echo '</form>';
            
            echo html_writer::link(
                new moodle_url('/local/question_diagnostic/orphan_entries.php'),
                '❌ Annuler',
                ['class' => 'btn btn-secondary', 'style' => 'margin-left: 10px;']
            );
            echo html_writer::end_div();
            
            echo $OUTPUT->footer();
            exit;
        }
        
        // Afficher d'abord les entries AVEC questions
        if ($count_with_questions > 0) {
            echo html_writer::tag('h3', '🔴 Entries avec questions à récupérer (' . $count_with_questions . ')', ['style' => 'color: #d9534f;']);
            
            // Formulaire pour actions groupées
            echo '<form method="post" action="' . new moodle_url('/local/question_diagnostic/orphan_entries.php') . '" id="bulk-form">';
            echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
            echo '<input type="hidden" name="action" value="bulk_reassign">';
            echo '<input type="hidden" name="targetcategory" value="" id="bulk-target-category">';
            
            echo '<table class="generaltable" style="width: 100%; margin-top: 10px; border: 2px solid #d9534f;">';
            echo '<thead>
                    <tr style="background-color: #f2dede;">
                        <th style="width: 30px;">
                            <input type="checkbox" id="select-all" title="Tout sélectionner">
                        </th>
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
                echo '<td style="text-align: center;">
                        <input type="checkbox" name="entries[]" value="' . $entry->id . '" class="entry-checkbox">
                      </td>';
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
                    'Voir détails →',
                    ['class' => 'btn btn-sm btn-secondary', 'style' => 'font-size: 0.85em;']
                );
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
            echo '</form>';
            
            // Panneau d'actions groupées
            echo html_writer::start_div('alert alert-info', ['style' => 'margin-top: 20px; background-color: #e8f4f8; border-left: 4px solid #31708f;']);
            echo '<h4 style="margin-top: 0;">⚡ Actions groupées</h4>';
            echo '<p><strong><span id="selected-count">0</span></strong> entry(ies) sélectionnée(s)</p>';
            
            // Chercher les catégories "Récupération"
            $recovery_categories = $DB->get_records_sql("
                SELECT * FROM {question_categories}
                WHERE " . $DB->sql_like('name', ':pattern', false) . "
                ORDER BY id DESC
                LIMIT 5
            ", ['pattern' => '%récupération%']);
            
            if ($recovery_categories) {
                echo '<p><strong>Réassigner vers :</strong></p>';
                echo '<div style="margin-bottom: 10px;">';
                foreach ($recovery_categories as $cat) {
                    echo '<button type="button" class="btn btn-primary btn-sm bulk-assign-btn" data-categoryid="' . $cat->id . '" style="margin-right: 10px; margin-bottom: 5px;">';
                    echo '→ ' . s($cat->name) . ' (ID: ' . $cat->id . ')';
                    echo '</button>';
                }
                echo '</div>';
            } else {
                echo '<p style="color: #856404; background-color: #fff3cd; padding: 10px; border-radius: 4px;">';
                echo '⚠️ <strong>Aucune catégorie "Récupération" trouvée.</strong> Créez-en une d\'abord dans votre Moodle.';
                echo '</p>';
            }
            
            echo html_writer::end_div();
            
            // JavaScript pour gérer la sélection multiple
            echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                const selectAll = document.getElementById("select-all");
                const checkboxes = document.querySelectorAll(".entry-checkbox");
                const selectedCount = document.getElementById("selected-count");
                const bulkForm = document.getElementById("bulk-form");
                const bulkTargetCategory = document.getElementById("bulk-target-category");
                const bulkAssignBtns = document.querySelectorAll(".bulk-assign-btn");
                
                // Tout sélectionner
                selectAll.addEventListener("change", function() {
                    checkboxes.forEach(cb => cb.checked = this.checked);
                    updateSelectedCount();
                });
                
                // Mettre à jour le compteur
                checkboxes.forEach(cb => {
                    cb.addEventListener("change", updateSelectedCount);
                });
                
                function updateSelectedCount() {
                    const count = document.querySelectorAll(".entry-checkbox:checked").length;
                    selectedCount.textContent = count;
                    
                    // Désactiver/activer les boutons d\'action
                    bulkAssignBtns.forEach(btn => {
                        btn.disabled = count === 0;
                        btn.style.opacity = count === 0 ? "0.5" : "1";
                    });
                }
                
                // Boutons de réassignation groupée
                bulkAssignBtns.forEach(btn => {
                    btn.addEventListener("click", function() {
                        const count = document.querySelectorAll(".entry-checkbox:checked").length;
                        if (count === 0) {
                            alert("Veuillez sélectionner au moins une entry.");
                            return;
                        }
                        
                        const categoryId = this.dataset.categoryid;
                        bulkTargetCategory.value = categoryId;
                        bulkForm.submit();
                    });
                });
                
                // Initialiser le compteur
                updateSelectedCount();
            });
            </script>';
        } else {
            echo html_writer::div('✅ Aucune entry avec questions à récupérer !', 'alert alert-success');
        }
        
        // Afficher ensuite les entries VIDES (moins importantes)
        if ($count_empty > 0) {
            echo html_writer::tag('h3', 'ℹ️ Entries vides (' . $count_empty . ') - Peuvent être supprimées', ['style' => 'color: #5bc0de; margin-top: 40px;']);
            echo html_writer::start_div('alert alert-info');
            echo 'Ces entries ne contiennent aucune question. Elles peuvent être supprimées pour nettoyer la base de données.';
            echo html_writer::end_div();
            
            // Formulaire pour suppression groupée
            echo '<form method="post" action="' . new moodle_url('/local/question_diagnostic/orphan_entries.php') . '" id="bulk-form-empty">';
            echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
            echo '<input type="hidden" name="action" value="bulk_delete_empty">';
            
            echo '<table class="generaltable" style="width: 100%; margin-top: 10px; opacity: 0.7;">';
            echo '<thead>
                    <tr>
                        <th style="width: 30px;">
                            <input type="checkbox" id="select-all-empty" title="Tout sélectionner">
                        </th>
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
                echo '<td style="text-align: center;">
                        <input type="checkbox" name="entries[]" value="' . $entry->id . '" class="entry-checkbox-empty">
                      </td>';
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
            echo '</form>';
            
            // Panneau d'actions groupées pour suppression
            echo html_writer::start_div('alert alert-warning', ['style' => 'margin-top: 20px; background-color: #fcf8e3; border-left: 4px solid #f0ad4e;']);
            echo '<h4 style="margin-top: 0;">🗑️ Suppression groupée</h4>';
            echo '<p><strong><span id="selected-count-empty">0</span></strong> entry(ies) vide(s) sélectionnée(s)</p>';
            echo '<p>Les entries vides n\'ont pas de questions liées. Leur suppression est <strong>sûre</strong> et permet de nettoyer la base de données.</p>';
            echo '<button type="button" class="btn btn-danger btn-sm" id="bulk-delete-btn">🗑️ Supprimer les entries sélectionnées</button>';
            echo html_writer::end_div();
            
            // JavaScript pour gérer la sélection des entries vides
            echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                const selectAllEmpty = document.getElementById("select-all-empty");
                const checkboxesEmpty = document.querySelectorAll(".entry-checkbox-empty");
                const selectedCountEmpty = document.getElementById("selected-count-empty");
                const bulkFormEmpty = document.getElementById("bulk-form-empty");
                const bulkDeleteBtn = document.getElementById("bulk-delete-btn");
                
                // Tout sélectionner
                if (selectAllEmpty) {
                    selectAllEmpty.addEventListener("change", function() {
                        checkboxesEmpty.forEach(cb => cb.checked = this.checked);
                        updateSelectedCountEmpty();
                    });
                }
                
                // Mettre à jour le compteur
                checkboxesEmpty.forEach(cb => {
                    cb.addEventListener("change", updateSelectedCountEmpty);
                });
                
                function updateSelectedCountEmpty() {
                    const count = document.querySelectorAll(".entry-checkbox-empty:checked").length;
                    selectedCountEmpty.textContent = count;
                    
                    // Désactiver/activer le bouton de suppression
                    if (bulkDeleteBtn) {
                        bulkDeleteBtn.disabled = count === 0;
                        bulkDeleteBtn.style.opacity = count === 0 ? "0.5" : "1";
                    }
                }
                
                // Bouton de suppression groupée
                if (bulkDeleteBtn) {
                    bulkDeleteBtn.addEventListener("click", function() {
                        const count = document.querySelectorAll(".entry-checkbox-empty:checked").length;
                        if (count === 0) {
                            alert("Veuillez sélectionner au moins une entry vide.");
                            return;
                        }
                        
                        // Debug : Afficher les IDs sélectionnés
                        const selectedIds = [];
                        document.querySelectorAll(".entry-checkbox-empty:checked").forEach(cb => {
                            selectedIds.push(cb.value);
                        });
                        console.log("DEBUG: Submitting bulk delete with IDs:", selectedIds);
                        console.log("DEBUG: Form action:", bulkFormEmpty.action);
                        console.log("DEBUG: Form method:", bulkFormEmpty.method);
                        
                        bulkFormEmpty.submit();
                    });
                }
                
                // Initialiser le compteur
                updateSelectedCountEmpty();
            });
            </script>';
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
