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
 * Page pour rendre les questions cachées visibles
 *
 * @package    local_question_diagnostic
 * @copyright  2025 Question Diagnostic Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/question_analyzer.php');
require_once(__DIR__ . '/classes/debug_manager.php');

use local_question_diagnostic\question_analyzer;
use local_question_diagnostic\debug_manager;

// Charger les bibliothèques Moodle nécessaires.
require_login();

// Vérification stricte : seuls les administrateurs du site peuvent accéder à cette page.
if (!is_siteadmin()) {
    throw new \moodle_exception('accessdenied', 'admin', '', 'Vous devez être administrateur du site pour accéder à cet outil.');
    exit;
}

// Définir le contexte de la page (système).
$context = context_system::instance();

// Définir le titre et l'URL de la page.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/question_diagnostic/unhide_questions.php'));
$pagetitle = get_string('unhide_questions_title', 'local_question_diagnostic');
$PAGE->set_title($pagetitle);
$PAGE->set_heading(local_question_diagnostic_get_heading_with_version($pagetitle));
$PAGE->set_pagelayout('report');

// Ajouter les CSS et JavaScript personnalisés
$PAGE->requires->css('/local/question_diagnostic/styles/main.css');
$PAGE->requires->js('/local/question_diagnostic/scripts/main.js', true);

// Initialiser le système de debugging
debug_manager::init();
debug_manager::set_context('unhide_questions');

// Traitement de l'action
$action = optional_param('action', '', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_INT);
$question_ids = optional_param('question_ids', '', PARAM_TEXT);

// Log des paramètres reçus
debug_manager::verbose("Action: {$action}, Confirm: {$confirm}, Question IDs: {$question_ids}");

if ($action === 'unhide_all' && $confirm) {
    require_sesskey();
    
    debug_manager::progress('Starting unhide process...');
    
    // Exécuter l'action : Rendre visibles TOUTES les questions cachées (y compris celles utilisées dans des quiz).
    // ⚠️ Cela peut modifier la visibilité de questions "soft delete".
    $all_hidden_questions = question_analyzer::get_hidden_questions(false, 0);
    $question_ids = array_map(function($q) { return $q->id; }, $all_hidden_questions);
    
    debug_manager::info('Found ' . count($question_ids) . ' hidden questions to unhide');
    
    if (empty($question_ids)) {
        debug_manager::info('No hidden questions found, redirecting...');
        redirect(
            new moodle_url('/local/question_diagnostic/unhide_questions.php'),
            '✅ Aucune question cachée trouvée.',
            null,
            \core\output\notification::NOTIFY_INFO
        );
    }
    
    // Exécuter en masse
    $result = question_analyzer::unhide_questions_batch($question_ids);
    
    // Purger le cache
    question_analyzer::purge_all_caches();
    
    $message = '✅ Opération terminée : ' . $result['success'] . ' question(s) rendues visibles.';
    if ($result['failed'] > 0) {
        $message .= ' ' . $result['failed'] . ' échec(s). Détails : ' . implode('; ', array_slice($result['errors'], 0, 5));
    }
    
    redirect(
        new moodle_url('/local/question_diagnostic/unhide_questions.php'),
        $message,
        null,
        $result['failed'] > 0 ? \core\output\notification::NOTIFY_WARNING : \core\output\notification::NOTIFY_SUCCESS
    );
}

// 🆕 Nouvelle action : Rendre visibles les questions sélectionnées
if ($action === 'unhide_selected' && $confirm && !empty($question_ids)) {
    require_sesskey();
    
    // Parser les IDs des questions sélectionnées
    $selected_ids = array_filter(array_map('intval', explode(',', $question_ids)));
    
    if (empty($selected_ids)) {
        redirect(
            new moodle_url('/local/question_diagnostic/unhide_questions.php'),
            '❌ Aucune question sélectionnée.',
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    
    // Exécuter en masse sur les questions sélectionnées
    $result = question_analyzer::unhide_questions_batch($selected_ids);
    
    // Purger le cache
    question_analyzer::purge_all_caches();
    
    $message = '✅ Opération terminée : ' . $result['success'] . ' question(s) rendues visibles.';
    if ($result['failed'] > 0) {
        $message .= ' ' . $result['failed'] . ' échec(s). Détails : ' . implode('; ', array_slice($result['errors'], 0, 5));
    }
    
    redirect(
        new moodle_url('/local/question_diagnostic/unhide_questions.php'),
        $message,
        null,
        $result['failed'] > 0 ? \core\output\notification::NOTIFY_WARNING : \core\output\notification::NOTIFY_SUCCESS
    );
}

// 🆕 Nouvelle action : Rendre visible une seule question
if ($action === 'unhide_single' && $confirm && !empty($question_ids)) {
    require_sesskey();
    
    $question_id = intval($question_ids);
    
    if ($question_id <= 0) {
        redirect(
            new moodle_url('/local/question_diagnostic/unhide_questions.php'),
            '❌ ID de question invalide.',
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    
    // Exécuter sur une seule question
    $result = question_analyzer::unhide_question($question_id);
    
    // Purger le cache
    question_analyzer::purge_all_caches();
    
    if ($result === true) {
        $message = '✅ Question ID ' . $question_id . ' rendue visible avec succès.';
        $notification_type = \core\output\notification::NOTIFY_SUCCESS;
    } else {
        $message = '❌ Échec pour la question ID ' . $question_id . ': ' . $result;
        $notification_type = \core\output\notification::NOTIFY_ERROR;
    }
    
    redirect(
        new moodle_url('/local/question_diagnostic/unhide_questions.php'),
        $message,
        null,
        $notification_type
    );
}

// ======================================================================
// Section d'en-tête Moodle standard.
echo $OUTPUT->header();

// Afficher le badge de version
echo local_question_diagnostic_render_version_badge();

// Lien retour
echo html_writer::start_tag('div', ['style' => 'margin-bottom: 20px;']);
echo local_question_diagnostic_render_back_link('unhide_questions.php');
echo html_writer::end_tag('div');

// Titre de la page
echo html_writer::tag('h2', '👁️ ' . get_string('unhide_questions_title', 'local_question_diagnostic'));

// Introduction
echo html_writer::start_tag('div', ['class' => 'alert alert-info', 'style' => 'margin: 20px 0;']);
echo html_writer::tag('p', '🔍 Cette page affiche <strong>TOUTES</strong> les questions avec <code>status="hidden"</code> dans <code>question_versions</code> (cachées manuellement ou soft delete).');
echo html_writer::tag('p', '⚠️ Le bouton ci-dessous rendra visibles <strong>toutes</strong> ces questions, y compris celles utilisées dans des quiz (soft delete).');
echo html_writer::end_tag('div');

// Charger TOUTES les questions cachées (y compris soft delete)
// 🔧 v1.9.60 : false = inclure TOUTES, 0 = pas de limite
$all_hidden_questions = question_analyzer::get_hidden_questions(false, 0);
$total_hidden = count($all_hidden_questions);

// 🔧 DEBUG : Afficher un message si aucune question trouvée
if ($total_hidden == 0) {
    echo html_writer::start_tag('div', ['class' => 'alert alert-warning', 'style' => 'margin: 20px 0; padding: 20px;']);
    echo html_writer::tag('strong', '🔍 Debug : ');
    echo 'Aucune question avec status="hidden" trouvée dans la table question_versions. ';
    echo 'Cela peut signifier que toutes vos questions sont visibles (status="ready"), ou qu\'il y a un problème avec la requête SQL.';
    echo html_writer::end_tag('div');
}

// Calculer combien sont utilisées vs non utilisées
$hidden_question_ids = array_map(function($q) { return $q->id; }, $all_hidden_questions);
$usage_map = [];
$manually_hidden = 0; // Cachées manuellement (non utilisées)
$soft_deleted = 0;    // Supprimées (soft delete, utilisées)

if (!empty($hidden_question_ids)) {
    $usage_map = question_analyzer::get_questions_usage_by_ids($hidden_question_ids);
    
    foreach ($all_hidden_questions as $q) {
        $is_used = false;
        if (isset($usage_map[$q->id]) && is_array($usage_map[$q->id])) {
            $quiz_count = isset($usage_map[$q->id]['quiz_count']) ? $usage_map[$q->id]['quiz_count'] : 0;
            $is_used = ($quiz_count > 0);
        }
        
        if ($is_used) {
            $soft_deleted++;
        } else {
            $manually_hidden++;
        }
    }
}

// Statistiques
echo html_writer::start_tag('div', ['class' => 'qd-dashboard', 'style' => 'margin: 30px 0;']);

echo html_writer::start_tag('div', ['class' => 'qd-card']);
echo html_writer::tag('div', get_string('total_hidden_questions', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', $total_hidden, ['class' => 'qd-card-value']);
echo html_writer::tag('div', 'Toutes les questions cachées (quel qu\'en soit la raison)', ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'qd-card success']);
echo html_writer::tag('div', 'Cachées Manuellement', ['class' => 'qd-card-title']);
echo html_writer::tag('div', $manually_hidden, ['class' => 'qd-card-value']);
echo html_writer::tag('div', 'Peuvent être rendues visibles', ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'qd-card danger']);
echo html_writer::tag('div', 'Soft Delete', ['class' => 'qd-card-title']);
echo html_writer::tag('div', $soft_deleted, ['class' => 'qd-card-value']);
echo html_writer::tag('div', 'Utilisées dans des quiz - SERONT AUSSI RENDUES VISIBLES ⚠️', ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

echo html_writer::end_tag('div');

// 🆕 Boutons d'action pour la sélection et l'action en masse
if ($total_hidden > 0) {
    echo html_writer::start_tag('div', ['style' => 'margin: 30px 0; text-align: center;']);
    
    // Boutons d'action pour la sélection
    echo html_writer::start_tag('div', ['class' => 'qd-action-buttons', 'style' => 'margin-bottom: 20px;']);
    
    // Bouton "Sélectionner tout"
    echo html_writer::tag('button', '☑️ Sélectionner tout', [
        'type' => 'button',
        'class' => 'btn btn-secondary btn-sm',
        'onclick' => 'selectAllQuestions()',
        'style' => 'margin-right: 10px;'
    ]);
    
    // Bouton "Désélectionner tout"
    echo html_writer::tag('button', '☐ Désélectionner tout', [
        'type' => 'button',
        'class' => 'btn btn-secondary btn-sm',
        'onclick' => 'deselectAllQuestions()',
        'style' => 'margin-right: 10px;'
    ]);
    
    // Bouton "Rendre visibles les sélectionnées"
    echo html_writer::tag('button', '👁️ Rendre visibles les sélectionnées', [
        'type' => 'button',
        'class' => 'btn btn-primary btn-sm',
        'onclick' => 'unhideSelectedQuestions()',
        'style' => 'margin-right: 10px;'
    ]);
    
    echo html_writer::end_tag('div');
    
    // Compteur de sélection
    echo html_writer::start_tag('div', ['id' => 'selection-counter', 'class' => 'alert alert-info', 'style' => 'margin-bottom: 20px;']);
    echo html_writer::tag('span', '0 question(s) sélectionnée(s)', ['id' => 'selection-count']);
    echo html_writer::end_tag('div');
    
    // Bouton pour rendre TOUTES visibles (avec avertissement sur soft delete)
    echo html_writer::start_tag('div', ['style' => 'border-top: 2px solid #dee2e6; padding-top: 20px; margin-top: 20px;']);
    
    // 🔧 Utiliser un FORMULAIRE POST pour que ça fonctionne
    $confirm_message = "⚠️ ATTENTION CRITIQUE\n\n";
    $confirm_message .= "Vous allez rendre visible " . $total_hidden . " question(s) cachée(s).\n\n";
    $confirm_message .= "Cela inclut :\n";
    $confirm_message .= "- " . $manually_hidden . " question(s) cachées manuellement\n";
    $confirm_message .= "- " . $soft_deleted . " question(s) soft delete (utilisées dans des quiz)\n\n";
    $confirm_message .= "Cette opération peut impacter la banque de questions et certains workflows.\n\n";
    $confirm_message .= "Êtes-vous ABSOLUMENT sûr ?";
    
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => new moodle_url('/local/question_diagnostic/unhide_questions.php'),
        'style' => 'display: inline-block;',
        'onsubmit' => 'return confirm(' . json_encode($confirm_message) . ')'
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'unhide_all']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => '1']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'value' => '👁️ Rendre TOUTES les questions visibles (' . $total_hidden . ')',
        'class' => 'btn btn-danger btn-lg'
    ]);
    echo html_writer::end_tag('form');
    
    echo html_writer::start_tag('div', ['class' => 'alert alert-danger', 'style' => 'margin-top: 20px; max-width: 800px; margin-left: auto; margin-right: auto;']);
    echo html_writer::tag('strong', '⚠️ ATTENTION IMPORTANTE : ');
    echo 'Cette action rendra visibles <strong>ABSOLUMENT TOUTES</strong> les ' . $total_hidden . ' question(s) cachées :<br><br>';
    echo '✅ <strong>' . $manually_hidden . '</strong> question(s) cachées manuellement<br>';
    echo '⚠️ <strong>' . $soft_deleted . '</strong> question(s) soft delete (utilisées dans des quiz)';
    echo html_writer::end_tag('div');
    
    echo html_writer::end_tag('div');
    echo html_writer::end_tag('div');
}

if ($total_hidden > 0) {
    // Tableau de TOUTES les questions cachées
    echo html_writer::tag('h3', '📋 Toutes les questions cachées');
    
    echo html_writer::start_tag('table', ['class' => 'qd-table', 'style' => 'width: 100%;']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', '☑️', ['style' => 'width: 40px; text-align: center;']);
    echo html_writer::tag('th', 'ID');
    echo html_writer::tag('th', 'Nom');
    echo html_writer::tag('th', 'Type');
    echo html_writer::tag('th', 'Statut');
    echo html_writer::tag('th', 'Quiz', ['title' => 'Nombre de quiz utilisant cette question']);
    echo html_writer::tag('th', 'Catégorie');
    echo html_writer::tag('th', 'Créée le');
    echo html_writer::tag('th', 'Actions', ['style' => 'width: 120px; text-align: center;']);
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    
    echo html_writer::start_tag('tbody');
    
    foreach ($all_hidden_questions as $question) {
        $stats = question_analyzer::get_question_stats($question);
        
        // Déterminer le statut
        $is_used = false;
        $quiz_count = 0;
        if (isset($usage_map[$question->id]) && is_array($usage_map[$question->id])) {
            $quiz_count = isset($usage_map[$question->id]['quiz_count']) ? $usage_map[$question->id]['quiz_count'] : 0;
            $is_used = ($quiz_count > 0);
        }
        
        $row_style = $is_used ? 'background: #f8d7da;' : 'background: #fff3cd;';
        
        echo html_writer::start_tag('tr', ['style' => $row_style, 'data-question-id' => $question->id]);
        
        // 🆕 Colonne checkbox de sélection
        echo html_writer::start_tag('td', ['style' => 'text-align: center;']);
        echo html_writer::empty_tag('input', [
            'type' => 'checkbox',
            'class' => 'question-select-checkbox',
            'value' => $question->id,
            'data-question-id' => $question->id,
            'onchange' => 'updateSelectionCounter()',
            'title' => $is_used ? 'Utilisée dans un quiz (soft delete) - sera rendue visible si sélectionnée' : 'Sélectionner'
        ]);
        echo html_writer::end_tag('td');
        
        echo html_writer::tag('td', $question->id);
        echo html_writer::tag('td', format_string($question->name));
        echo html_writer::tag('td', $question->qtype);
        
        // Colonne statut
        if ($is_used) {
            echo html_writer::tag('td', '🗑️ Supprimée (soft delete)', [
                'style' => 'color: #d9534f; font-weight: bold;',
                'title' => 'Cette question a été supprimée mais est conservée car utilisée dans ' . $quiz_count . ' quiz'
            ]);
        } else {
            echo html_writer::tag('td', '🔒 Cachée manuellement', [
                'style' => 'color: #f0ad4e; font-weight: bold;',
                'title' => 'Cette question peut être rendue visible sans risque'
            ]);
        }
        
        // Colonne quiz count
        echo html_writer::tag('td', $quiz_count, [
            'style' => $quiz_count > 0 ? 'font-weight: bold; color: #d9534f;' : 'color: #999;'
        ]);
        
        echo html_writer::tag('td', isset($stats->category_name) ? format_string($stats->category_name) : '-');
        echo html_writer::tag('td', userdate($question->timecreated, '%d/%m/%Y'));
        
        // 🆕 Colonne actions individuelles
        echo html_writer::start_tag('td', ['style' => 'text-align: center;']);
        
        // Bouton pour rendre visible cette question individuellement
        $confirm_single = "Êtes-vous sûr de vouloir rendre visible la question ID " . $question->id . " ?";
        echo html_writer::start_tag('form', [
            'method' => 'post',
            'action' => new moodle_url('/local/question_diagnostic/unhide_questions.php'),
            'style' => 'display: inline-block;',
            'onsubmit' => 'return confirm(' . json_encode($confirm_single) . ')'
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'unhide_single']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'question_ids', 'value' => $question->id]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => '1']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', [
            'type' => 'submit',
            'value' => '👁️',
            'class' => 'btn btn-sm btn-success',
            'title' => $is_used ? 'Rendre visible (utilisée dans un quiz / soft delete)' : 'Rendre visible cette question'
        ]);
        echo html_writer::end_tag('form');
        
        echo html_writer::end_tag('td');
        echo html_writer::end_tag('tr');
    }
    
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    
    if (count($all_hidden_questions) >= 1000) {
        echo html_writer::start_tag('div', ['class' => 'alert alert-info', 'style' => 'margin-top: 20px;']);
        echo 'ℹ️ Affichage limité aux 1000 premières questions pour des raisons de performance.';
        echo html_writer::end_tag('div');
    }
} else {
    echo html_writer::start_tag('div', ['class' => 'alert alert-success', 'style' => 'margin: 30px 0; text-align: center;']);
    echo html_writer::tag('h3', '✅ Aucune question cachée trouvée', ['style' => 'margin-top: 0;']);
    echo html_writer::tag('p', 'Toutes vos questions sont visibles. Aucune question avec status="hidden" détectée.');
    echo html_writer::end_tag('div');
}

// Message info si seulement des soft delete
if ($total_hidden > 0 && $manually_hidden == 0 && $soft_deleted > 0) {
    echo html_writer::start_tag('div', ['class' => 'alert alert-warning', 'style' => 'margin-top: 20px;']);
    echo html_writer::tag('strong', 'ℹ️ Information : ');
    echo 'Les ' . $soft_deleted . ' question(s) affichée(s) sont des questions supprimées (soft delete) mais conservées car utilisées dans des quiz. ';
    echo 'Elles <strong>NE PEUVENT PAS</strong> être rendues visibles automatiquement car cela pourrait affecter les tentatives de quiz existantes.';
    echo html_writer::end_tag('div');
}

// Pied de page Moodle standard
echo $OUTPUT->footer();

// 🆕 JavaScript pour la gestion de la sélection et des actions en masse
if ($total_hidden > 0) {
    echo html_writer::start_tag('script', ['type' => 'text/javascript']);
    echo "
    // Fonction pour sélectionner toutes les questions
    function selectAllQuestions() {
        const checkboxes = document.querySelectorAll('.question-select-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = true;
        });
        updateSelectionCounter();
    }
    
    // Fonction pour désélectionner toutes les questions
    function deselectAllQuestions() {
        const checkboxes = document.querySelectorAll('.question-select-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        updateSelectionCounter();
    }
    
    // Fonction pour mettre à jour le compteur de sélection
    function updateSelectionCounter() {
        const checkboxes = document.querySelectorAll('.question-select-checkbox:checked');
        const count = checkboxes.length;
        const counterElement = document.getElementById('selection-count');
        
        if (counterElement) {
            counterElement.textContent = count + ' question(s) sélectionnée(s)';
            
            // Changer la couleur selon le nombre sélectionné
            const counterDiv = document.getElementById('selection-counter');
            if (counterDiv) {
                if (count === 0) {
                    counterDiv.className = 'alert alert-info';
                } else if (count < 10) {
                    counterDiv.className = 'alert alert-warning';
                } else {
                    counterDiv.className = 'alert alert-danger';
                }
            }
        }
    }
    
    // Fonction pour rendre visibles les questions sélectionnées
    function unhideSelectedQuestions() {
        const checkboxes = document.querySelectorAll('.question-select-checkbox:checked');
        
        if (checkboxes.length === 0) {
            alert('❌ Veuillez sélectionner au moins une question à rendre visible.');
            return;
        }
        
        const questionIds = Array.from(checkboxes).map(cb => cb.value);
        const count = questionIds.length;
        
        let confirmMessage = '⚠️ CONFIRMATION\\n\\n';
        confirmMessage += 'Vous allez rendre visible ' + count + ' question(s) sélectionnée(s).\\n\\n';
        confirmMessage += 'IDs des questions : ' + questionIds.join(', ') + '\\n\\n';
        confirmMessage += 'Êtes-vous sûr de vouloir continuer ?';
        
        if (confirm(confirmMessage)) {
            // Créer un formulaire dynamique pour soumettre les IDs sélectionnés
            const form = document.createElement('form');
            form.method = 'post';
            form.action = window.location.href;
            
            // Ajouter les champs cachés
            const actionField = document.createElement('input');
            actionField.type = 'hidden';
            actionField.name = 'action';
            actionField.value = 'unhide_selected';
            form.appendChild(actionField);
            
            const questionIdsField = document.createElement('input');
            questionIdsField.type = 'hidden';
            questionIdsField.name = 'question_ids';
            questionIdsField.value = questionIds.join(',');
            form.appendChild(questionIdsField);
            
            const confirmField = document.createElement('input');
            confirmField.type = 'hidden';
            confirmField.name = 'confirm';
            confirmField.value = '1';
            form.appendChild(confirmField);
            
            const sesskeyField = document.createElement('input');
            sesskeyField.type = 'hidden';
            sesskeyField.name = 'sesskey';
            sesskeyField.value = '" . sesskey() . "';
            form.appendChild(sesskeyField);
            
            // Soumettre le formulaire
            document.body.appendChild(form);
            form.submit();
        }
    }
    
    // Initialiser le compteur au chargement de la page
    document.addEventListener('DOMContentLoaded', function() {
        updateSelectionCounter();
    });
    ";
    echo html_writer::end_tag('script');
}

