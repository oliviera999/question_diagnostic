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
    
    // Décoder chaque groupe (format: "representative_id").
    foreach ($groups_data as $group_encoded) {
        $repid = (int)$group_encoded;
        if ($repid > 0) {
            $groups_to_clean[] = [
                'repid' => $repid,
            ];
        }
    }
} else {
    // Mode individuel : utiliser l'ID représentatif (stable).
    $repid = required_param('id', PARAM_INT);
    $groups_to_clean[] = [
        'repid' => (int)$repid,
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
    $fallback_kept = []; // Versions conservées quand aucune version n'est utilisée.
    
    foreach ($groups_to_clean as $group) {
        $repid = (int)($group['repid'] ?? 0);
        if ($repid <= 0) {
            continue;
        }

        $rep = $DB->get_record('question', ['id' => $repid], 'id,name,qtype', IGNORE_MISSING);
        if (!$rep) {
            continue;
        }

        // Récupérer toutes les questions de ce groupe selon la définition standard du plugin.
        $question_ids = question_analyzer::get_duplicate_group_question_ids_by_representative_id($repid);
        if (empty($question_ids)) {
            continue;
        }

        $all_questions = $DB->get_records_list('question', 'id', $question_ids, 'id ASC');
        
        // Charger l'usage
        $question_ids = array_keys($all_questions);
        $usage_map = question_analyzer::get_questions_usage_by_ids($question_ids);
        
        // Identifier les questions à supprimer et celles à conserver
        $to_keep = [];
        $to_delete = [];
        
        foreach ($all_questions as $q) {
            $quiz_count = 0;
            if (isset($usage_map[$q->id]) && isset($usage_map[$q->id]['quiz_count'])) {
                $quiz_count = $usage_map[$q->id]['quiz_count'];
            }
            
            if ($quiz_count > 0) {
                $to_keep[] = $q; // Utilisée = conservée.
            } else {
                $to_delete[] = $q; // Inutilisée = candidate à suppression.
            }
        }
        
        // Si aucune version utilisée, on CONSERVE 1 version (sélection intelligente) et on supprime le reste.
        if (empty($to_keep) && !empty($to_delete)) {
            $best = question_analyzer::select_best_question_to_keep($to_delete);
            if ($best) {
                $to_keep[] = $best->question;
                $to_delete = array_values(array_filter($to_delete, function($q) use ($best) {
                    return (int)$q->id !== (int)$best->question->id;
                }));
                $fallback_kept[] = (object)[
                    'name' => $rep->name,
                    'qtype' => $rep->qtype,
                    'question' => $best->question,
                    'score' => $best->score,
                    'info' => $best->info
                ];
            } else {
                // Fallback : conserver la première, supprimer le reste.
                $kept = array_shift($to_delete);
                $to_keep[] = $kept;
                $fallback_kept[] = (object)[
                    'name' => $rep->name,
                    'qtype' => $rep->qtype,
                    'question' => $kept,
                    'score' => null,
                    'info' => 'Fallback: première version'
                ];
            }
        }
        
        // Accumuler la liste finale à supprimer (après application de la règle "au moins 1 conservée").
        foreach ($to_delete as $q) {
            $questions_to_delete[] = (object)[
                'groupname' => $rep->name,
                'qtype' => $rep->qtype,
                'question' => $q
            ];
            $total_to_delete++;
        }
    }
    
    if ($total_to_delete == 0) {
        echo html_writer::start_tag('div', ['class' => 'alert alert-info']);
        echo html_writer::tag('h3', '✅ Aucune question à supprimer');
        echo 'Tous les groupes sélectionnés ne contiennent aucune version inutilisée à supprimer.';
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
    
    // Afficher quelles versions seront conservées quand aucune n'est utilisée (point demandé).
    if (!empty($fallback_kept)) {
        echo html_writer::start_tag('div', ['class' => 'alert alert-success', 'style' => 'margin: 20px 0;']);
        echo html_writer::tag('h3', '✅ Version(s) conservée(s) (aucune version utilisée)', ['style' => 'margin-top: 0;']);
        echo html_writer::tag('p',
            'Pour chaque groupe sans version utilisée, une version sera conservée pour éviter toute suppression complète.');
        echo html_writer::end_tag('div');
        
        echo html_writer::start_tag('table', ['class' => 'generaltable', 'style' => 'width: 100%; margin-bottom: 25px;']);
        echo html_writer::start_tag('thead');
        echo html_writer::start_tag('tr');
        echo html_writer::tag('th', 'Groupe');
        echo html_writer::tag('th', 'Type');
        echo html_writer::tag('th', 'ID conservée');
        echo html_writer::tag('th', 'Créée le');
        echo html_writer::tag('th', 'Raison (contexte)');
        echo html_writer::end_tag('tr');
        echo html_writer::end_tag('thead');
        
        echo html_writer::start_tag('tbody');
        foreach ($fallback_kept as $k) {
            echo html_writer::start_tag('tr');
            echo html_writer::tag('td', format_string($k->name));
            echo html_writer::tag('td', s($k->qtype));
            echo html_writer::tag('td', (int)$k->question->id, ['style' => 'font-weight: bold; color: #28a745;']);
            echo html_writer::tag('td', userdate($k->question->timecreated, '%d/%m/%Y %H:%M'));
            $reason = $k->info;
            if ($k->score !== null) {
                $reason .= ' (score: ' . (int)$k->score . ')';
            }
            echo html_writer::tag('td', s($reason));
            echo html_writer::end_tag('tr');
        }
        echo html_writer::end_tag('tbody');
        echo html_writer::end_tag('table');
    }
    
    // Afficher les détails des questions à supprimer
    echo html_writer::tag('h3', '📋 Détails des questions à supprimer');
    
    echo html_writer::start_tag('table', ['class' => 'generaltable', 'style' => 'width: 100%;']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'Groupe');
    echo html_writer::tag('th', 'ID');
    echo html_writer::tag('th', 'Nom de la question');
    echo html_writer::tag('th', 'Type');
    echo html_writer::tag('th', 'Créée le');
    echo html_writer::tag('th', 'Statut');
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    
    echo html_writer::start_tag('tbody');
    foreach ($questions_to_delete as $item) {
        $q = $item->question;
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', format_string($item->groupname));
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
    
    // Boutons de confirmation (POST pour éviter les URLs trop longues en mode bulk).
    echo html_writer::start_tag('div', ['style' => 'margin-top: 30px; text-align: center;']);
    
    $action_url = new moodle_url('/local/question_diagnostic/actions/cleanup_duplicate_groups.php');
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $action_url->out(false),
        'style' => 'display: inline-block; margin-right: 10px;'
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => 1]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    
    if ($bulk) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'bulk', 'value' => 1]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'groups', 'value' => $groups_json]);
    } else {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => (int)$groups_to_clean[0]['repid']]);
    }
    
    echo html_writer::tag('button', '✓ Confirmer la suppression', [
        'type' => 'submit',
        'class' => 'btn btn-danger btn-lg'
    ]);
    echo html_writer::end_tag('form');
    
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
    $repid = (int)($group['repid'] ?? 0);
    if ($repid <= 0) {
        continue;
    }

    $rep = $DB->get_record('question', ['id' => $repid], 'id,name,qtype', IGNORE_MISSING);
    if (!$rep) {
        continue;
    }

    // Récupérer toutes les questions de ce groupe selon la définition standard du plugin.
    $question_ids = question_analyzer::get_duplicate_group_question_ids_by_representative_id($repid);
    if (empty($question_ids)) {
        continue;
    }

    $all_questions = $DB->get_records_list('question', 'id', $question_ids, 'id ASC');
    
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
        $best = question_analyzer::select_best_question_to_keep($to_delete);
        if ($best) {
            $to_keep[] = $best->question;
            $to_delete = array_values(array_filter($to_delete, function($q) use ($best) {
                return (int)$q->id !== (int)$best->question->id;
            }));
            
            // 📝 Log pour traçabilité
            debugging('Groupe "' . $rep->name . '" : Version conservée ID ' . (int)$best->question->id .
                ' (Score: ' . (int)$best->score . ', ' . $best->info . ', créée le ' .
                userdate((int)$best->timecreated, '%d/%m/%Y') . ')', DEBUG_DEVELOPER);
        } else {
            // Fallback : garder la première (ordre id ASC).
            $oldest = array_shift($to_delete);
            if ($oldest) {
                $to_keep[] = $oldest;
            }
        }
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

