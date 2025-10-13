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
 * Action de nettoyage automatique des groupes de doublons
 * 
 * Supprime toutes les versions inutilisées d'un ou plusieurs groupes de doublons
 *
 * @package    local_question_diagnostic
 * @copyright  2025 Question Diagnostic Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/../classes/question_analyzer.php');

use local_question_diagnostic\question_analyzer;

require_login();

if (!is_siteadmin()) {
    print_error('accessdenied', 'admin');
}

require_sesskey();

/**
 * Calcule le score d'accessibilité d'une question basé sur son contexte
 * Score plus élevé = contexte plus large/accessible
 * 
 * Priorité :
 * 1. CONTEXT_SYSTEM (50) = Niveau site, accessible partout
 * 2. CONTEXT_COURSECAT (40) = Catégorie de cours
 * 3. CONTEXT_COURSE (30) = Cours spécifique
 * 4. CONTEXT_MODULE (20) = Module d'activité
 * 5. Autres/Invalide (10) = Cas d'erreur
 * 
 * @param object $question Question Moodle
 * @return object {score: int, contextlevel: int, contextid: int, timecreated: int, info: string}
 */
function local_question_diagnostic_get_accessibility_score($question) {
    global $DB;
    
    $result = (object)[
        'score' => 0,
        'contextlevel' => null,
        'contextid' => null,
        'timecreated' => $question->timecreated,
        'info' => 'Contexte inconnu'
    ];
    
    try {
        // Récupérer la catégorie et le contexte de la question
        // Via question_bank_entries (Moodle 4.x)
        $sql = "SELECT qc.contextid, ctx.contextlevel, qc.name as category_name
                FROM {question_categories} qc
                INNER JOIN {question_bank_entries} qbe ON qbe.questioncategoryid = qc.id
                INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                INNER JOIN {context} ctx ON ctx.id = qc.contextid
                WHERE qv.questionid = :questionid
                LIMIT 1";
        
        $record = $DB->get_record_sql($sql, ['questionid' => $question->id]);
        
        if (!$record) {
            $result->info = 'Catégorie/contexte introuvable';
            $result->score = 5; // Score très bas
            return $result;
        }
        
        $result->contextid = $record->contextid;
        $result->contextlevel = $record->contextlevel;
        
        // Attribuer le score selon le niveau de contexte
        switch ($record->contextlevel) {
            case CONTEXT_SYSTEM:
                $result->score = 50;
                $result->info = '🌐 Site entier (le plus accessible)';
                break;
            case CONTEXT_COURSECAT:
                $result->score = 40;
                $result->info = '📂 Catégorie de cours';
                break;
            case CONTEXT_COURSE:
                $result->score = 30;
                $result->info = '📚 Cours spécifique';
                break;
            case CONTEXT_MODULE:
                $result->score = 20;
                $result->info = '📝 Module d\'activité';
                break;
            default:
                $result->score = 10;
                $result->info = 'Contexte non standard (niveau ' . $record->contextlevel . ')';
                break;
        }
        
    } catch (Exception $e) {
        debugging('Erreur calcul accessibilité pour question ' . $question->id . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
        $result->score = 1;
        $result->info = 'Erreur: ' . $e->getMessage();
    }
    
    return $result;
}

// Définir le contexte de la page
$context = context_system::instance();
$PAGE->set_context($context);

// Déterminer le mode : nettoyage individuel ou en masse
$bulk = optional_param('bulk', 0, PARAM_INT);

$groups_to_clean = [];

if ($bulk) {
    // Mode masse : récupérer la liste des groupes depuis le paramètre JSON
    $groups_json = required_param('groups', PARAM_RAW);
    $groups_data = json_decode($groups_json);
    
    if (!$groups_data || !is_array($groups_data)) {
        print_error('Données invalides pour le nettoyage en masse');
    }
    
    // Décoder chaque groupe (format: "nom|type")
    foreach ($groups_data as $group_encoded) {
        $parts = explode('|', $group_encoded);
        if (count($parts) == 2) {
            $groups_to_clean[] = [
                'name' => $parts[0],
                'qtype' => $parts[1]
            ];
        }
    }
} else {
    // Mode individuel : récupérer nom et type
    $name = required_param('name', PARAM_TEXT);
    $qtype = required_param('qtype', PARAM_TEXT);
    
    $groups_to_clean[] = [
        'name' => $name,
        'qtype' => $qtype
    ];
}

if (empty($groups_to_clean)) {
    print_error('Aucun groupe à nettoyer');
}

// Vérifier si c'est une confirmation ou une première demande
$confirm = optional_param('confirm', 0, PARAM_INT);

if (!$confirm) {
    // ========================================
    // ÉTAPE 1 : PAGE DE CONFIRMATION
    // ========================================
    
    $PAGE->set_url(new moodle_url('/local/question_diagnostic/actions/cleanup_duplicate_groups.php'));
    $PAGE->set_title('Confirmation du nettoyage');
    $PAGE->set_heading(local_question_diagnostic_get_heading_with_version('Confirmation du nettoyage'));
    $PAGE->set_pagelayout('admin');
    
    echo $OUTPUT->header();
    
    echo html_writer::tag('h2', '🧹 Confirmation du nettoyage des doublons');
    
    // Analyser tous les groupes à nettoyer
    $total_to_delete = 0;
    $questions_to_delete = [];
    
    foreach ($groups_to_clean as $group) {
        // Récupérer toutes les questions de ce groupe
        $all_questions = $DB->get_records('question', [
            'name' => $group['name'],
            'qtype' => $group['qtype']
        ]);
        
        // Charger l'usage
        $question_ids = array_keys($all_questions);
        $usage_map = question_analyzer::get_questions_usage_by_ids($question_ids);
        
        // Identifier les questions à supprimer (inutilisées)
        foreach ($all_questions as $q) {
            $quiz_count = 0;
            if (isset($usage_map[$q->id]) && isset($usage_map[$q->id]['quiz_count'])) {
                $quiz_count = $usage_map[$q->id]['quiz_count'];
            }
            
            if ($quiz_count == 0) {
                // Question inutilisée = à supprimer
                $questions_to_delete[] = $q;
                $total_to_delete++;
            }
        }
    }
    
    if ($total_to_delete == 0) {
        echo html_writer::start_tag('div', ['class' => 'alert alert-info']);
        echo html_writer::tag('h3', '✅ Aucune question à supprimer');
        echo 'Tous les groupes sélectionnés ne contiennent que des versions utilisées.';
        echo html_writer::end_tag('div');
        
        echo html_writer::start_tag('div', ['style' => 'margin-top: 20px;']);
        echo html_writer::link(
            new moodle_url('/local/question_diagnostic/questions_cleanup.php', ['loadstats' => 1]),
            '← Retour à la liste',
            ['class' => 'btn btn-secondary']
        );
        echo html_writer::end_tag('div');
        
        echo $OUTPUT->footer();
        exit;
    }
    
    // Afficher le résumé
    echo html_writer::start_tag('div', ['class' => 'alert alert-warning', 'style' => 'margin: 20px 0;']);
    echo html_writer::tag('h3', '⚠️ Vous êtes sur le point de supprimer ' . $total_to_delete . ' question(s)', ['style' => 'margin-top: 0;']);
    echo html_writer::tag('p', '<strong>Groupes concernés :</strong> ' . count($groups_to_clean));
    echo html_writer::tag('p', '<strong>Questions à supprimer :</strong> ' . $total_to_delete . ' version(s) inutilisée(s)');
    echo html_writer::tag('p', '<strong style="color: #d9534f;">⚠️ Cette action est IRRÉVERSIBLE !</strong>');
    echo html_writer::end_tag('div');
    
    // Afficher les détails des questions à supprimer
    echo html_writer::tag('h3', '📋 Détails des questions à supprimer');
    
    echo html_writer::start_tag('table', ['class' => 'generaltable', 'style' => 'width: 100%;']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'ID');
    echo html_writer::tag('th', 'Nom de la question');
    echo html_writer::tag('th', 'Type');
    echo html_writer::tag('th', 'Créée le');
    echo html_writer::tag('th', 'Statut');
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    
    echo html_writer::start_tag('tbody');
    foreach ($questions_to_delete as $q) {
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', $q->id);
        echo html_writer::tag('td', format_string($q->name));
        echo html_writer::tag('td', $q->qtype);
        echo html_writer::tag('td', userdate($q->timecreated, '%d/%m/%Y %H:%M'));
        echo html_writer::tag('td', '⚠️ Inutilisée', ['style' => 'color: #f0ad4e;']);
        echo html_writer::end_tag('tr');
    }
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    
    // Règles de sécurité
    echo html_writer::start_tag('div', ['class' => 'alert alert-info', 'style' => 'margin-top: 20px;']);
    echo html_writer::tag('h4', '🔒 Règles de sécurité', ['style' => 'margin-top: 0;']);
    echo html_writer::start_tag('ul');
    echo html_writer::tag('li', '✅ Les versions utilisées dans des quiz seront CONSERVÉES');
    echo html_writer::tag('li', '✅ Seules les versions inutilisées seront supprimées');
    echo html_writer::tag('li', '✅ Au moins 1 version sera toujours conservée (même si toutes inutilisées)');
    echo html_writer::tag('li', '🌐 <strong>Logique de conservation intelligente :</strong> Si aucune version n\'est utilisée, la version conservée sera celle du contexte le plus large (site > catégorie > cours > module), puis la plus ancienne en cas d\'égalité');
    echo html_writer::end_tag('ul');
    echo html_writer::end_tag('div');
    
    // Boutons de confirmation
    echo html_writer::start_tag('div', ['style' => 'margin-top: 30px; text-align: center;']);
    
    // Construire l'URL de confirmation
    $confirm_params = ['confirm' => 1, 'sesskey' => sesskey()];
    if ($bulk) {
        $confirm_params['bulk'] = 1;
        $confirm_params['groups'] = $groups_json;
    } else {
        $confirm_params['name'] = $groups_to_clean[0]['name'];
        $confirm_params['qtype'] = $groups_to_clean[0]['qtype'];
    }
    $confirm_url = new moodle_url('/local/question_diagnostic/actions/cleanup_duplicate_groups.php', $confirm_params);
    
    echo html_writer::link(
        $confirm_url,
        '✓ Confirmer la suppression',
        ['class' => 'btn btn-danger btn-lg', 'style' => 'margin-right: 10px;']
    );
    
    echo html_writer::link(
        new moodle_url('/local/question_diagnostic/questions_cleanup.php', ['loadstats' => 1]),
        '✗ Annuler',
        ['class' => 'btn btn-secondary btn-lg']
    );
    
    echo html_writer::end_tag('div');
    
    echo $OUTPUT->footer();
    exit;
}

// ========================================
// ÉTAPE 2 : EXÉCUTION DU NETTOYAGE
// ========================================

$deleted_count = 0;
$kept_count = 0;
$errors = [];

foreach ($groups_to_clean as $group) {
    // Récupérer toutes les questions de ce groupe
    $all_questions = $DB->get_records('question', [
        'name' => $group['name'],
        'qtype' => $group['qtype']
    ], 'id ASC');
    
    if (count($all_questions) <= 1) {
        // Si une seule version, on ne supprime rien
        $kept_count += count($all_questions);
        continue;
    }
    
    // Charger l'usage
    $question_ids = array_keys($all_questions);
    $usage_map = question_analyzer::get_questions_usage_by_ids($question_ids);
    
    // Identifier les questions à supprimer et à garder
    $to_delete = [];
    $to_keep = [];
    
    foreach ($all_questions as $q) {
        $quiz_count = 0;
        if (isset($usage_map[$q->id]) && isset($usage_map[$q->id]['quiz_count'])) {
            $quiz_count = $usage_map[$q->id]['quiz_count'];
        }
        
        if ($quiz_count > 0) {
            // Question utilisée = à garder
            $to_keep[] = $q;
        } else {
            // Question inutilisée = candidat à la suppression
            $to_delete[] = $q;
        }
    }
    
    // 🆕 v1.9.46 : Sécurité intelligente - garder la version la plus accessible
    if (empty($to_keep) && !empty($to_delete)) {
        // Calculer le score d'accessibilité pour chaque question inutilisée
        $questions_with_scores = [];
        foreach ($to_delete as $q) {
            $score_info = local_question_diagnostic_get_accessibility_score($q);
            $questions_with_scores[] = (object)[
                'question' => $q,
                'score' => $score_info->score,
                'timecreated' => $q->timecreated,
                'info' => $score_info->info
            ];
        }
        
        // Trier par score décroissant (contexte le plus large d'abord)
        // puis par ancienneté croissante (plus ancien = prioritaire)
        usort($questions_with_scores, function($a, $b) {
            // Priorité 1 : Score d'accessibilité (plus élevé = mieux)
            if ($a->score != $b->score) {
                return $b->score - $a->score; // Décroissant
            }
            // Priorité 2 : Ancienneté (plus petit timestamp = plus vieux = mieux)
            return $a->timecreated - $b->timecreated; // Croissant
        });
        
        // Garder la meilleure (première après tri)
        $best = array_shift($questions_with_scores);
        $to_keep[] = $best->question;
        
        // Mettre à jour $to_delete pour exclure la meilleure
        $to_delete = array_map(function($item) { return $item->question; }, $questions_with_scores);
        
        // 📝 Log pour traçabilité
        debugging('Groupe "' . $group['name'] . '" : Version conservée ID ' . $best->question->id . 
                  ' (Score: ' . $best->score . ', ' . $best->info . ', créée le ' . 
                  userdate($best->timecreated, '%d/%m/%Y') . ')', DEBUG_DEVELOPER);
    }
    
    // Supprimer les questions inutilisées
    foreach ($to_delete as $q) {
        try {
            question_delete_question($q->id);
            $deleted_count++;
        } catch (Exception $e) {
            $errors[] = 'Erreur suppression question ID ' . $q->id . ': ' . $e->getMessage();
        }
    }
    
    $kept_count += count($to_keep);
}

// Redirection avec message de succès/erreur
$return_url = new moodle_url('/local/question_diagnostic/questions_cleanup.php', ['loadstats' => 1]);

if ($deleted_count > 0) {
    $message = '✅ Nettoyage terminé : ' . $deleted_count . ' question(s) supprimée(s), ' . $kept_count . ' version(s) conservée(s)';
    $type = \core\output\notification::NOTIFY_SUCCESS;
} else {
    $message = 'Aucune question supprimée (toutes les versions sont utilisées ou protégées)';
    $type = \core\output\notification::NOTIFY_INFO;
}

if (!empty($errors)) {
    $message .= ' | Erreurs : ' . implode(', ', $errors);
    $type = \core\output\notification::NOTIFY_WARNING;
}

redirect($return_url, $message, null, $type);

