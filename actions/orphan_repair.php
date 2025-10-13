<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../classes/orphan_file_detector.php');
require_once(__DIR__ . '/../classes/orphan_file_repairer.php');

use local_question_diagnostic\orphan_file_detector;
use local_question_diagnostic\orphan_file_repairer;

require_login();

// Vérification stricte : seuls les administrateurs du site peuvent accéder à cette page.
if (!is_siteadmin()) {
    print_error('accessdenied', 'admin');
    exit;
}

require_sesskey();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/question_diagnostic/actions/orphan_repair.php'));
$PAGE->set_title(get_string('repair_orphan', 'local_question_diagnostic'));
$PAGE->set_pagelayout('admin');

// Récupérer les paramètres
$file_id = required_param('id', PARAM_INT);
$repair_type = optional_param('type', '', PARAM_ALPHA);
$target_id = optional_param('target_id', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);
$dry_run = optional_param('dryrun', 0, PARAM_INT);

// Récupérer le fichier orphelin
$file_record = $DB->get_record('files', ['id' => $file_id]);
if (!$file_record) {
    redirect(
        new moodle_url('/local/question_diagnostic/orphan_files.php'),
        get_string('repair_file_not_found', 'local_question_diagnostic'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// Analyser les options de réparation
$repair_options = orphan_file_repairer::analyze_repair_options($file_record);

if (empty($repair_options)) {
    redirect(
        new moodle_url('/local/question_diagnostic/orphan_files.php'),
        get_string('repair_no_target_found', 'local_question_diagnostic'),
        null,
        \core\output\notification::NOTIFY_WARNING
    );
}

// ======================================================================
// PAGE DE SÉLECTION DES OPTIONS (si pas encore de type sélectionné)
// ======================================================================

if (empty($repair_type) || !$confirm) {
    echo $OUTPUT->header();
    
    // Titre
    echo html_writer::tag('h2', '🔧 ' . get_string('repair_modal_title', 'local_question_diagnostic'));
    
    // Informations sur le fichier
    echo html_writer::start_div('alert alert-info', ['style' => 'margin: 20px 0;']);
    echo '<strong>📁 Fichier :</strong> ' . htmlspecialchars($file_record->filename) . '<br>';
    echo '<strong>💾 Taille :</strong> ' . orphan_file_detector::format_filesize($file_record->filesize) . '<br>';
    echo '<strong>📦 Composant :</strong> ' . htmlspecialchars($file_record->component) . '<br>';
    echo '<strong>🆔 ID :</strong> ' . $file_record->id;
    echo html_writer::end_div();
    
    // Options de réparation
    echo html_writer::tag('h3', get_string('repair_options', 'local_question_diagnostic'));
    
    echo html_writer::start_tag('div', ['class' => 'qd-repair-options']);
    
    foreach ($repair_options as $index => $option) {
        $card_class = 'qd-repair-option';
        if ($option['confidence'] >= 90) {
            $card_class .= ' high-confidence';
        } else if ($option['confidence'] >= 60) {
            $card_class .= ' medium-confidence';
        } else {
            $card_class .= ' low-confidence';
        }
        
        echo html_writer::start_tag('div', ['class' => $card_class, 'style' => 'margin: 15px 0; padding: 20px; border: 2px solid #ddd; border-radius: 8px;']);
        
        // En-tête de l'option
        echo html_writer::start_tag('div', ['style' => 'display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;']);
        echo html_writer::tag('h4', $option['icon'] . ' ' . get_string('repair_' . $option['type'], 'local_question_diagnostic'), ['style' => 'margin: 0;']);
        echo html_writer::tag('span', get_string('repair_confidence', 'local_question_diagnostic') . ': ' . $option['confidence'] . '%', 
                             ['class' => 'badge badge-primary', 'style' => 'font-size: 14px;']);
        echo html_writer::end_tag('div');
        
        // Description
        echo html_writer::tag('p', $option['description'], ['style' => 'margin: 10px 0; color: #666;']);
        
        // Détails selon le type
        if ($option['type'] === 'contenthash' && isset($option['target'])) {
            $target = $option['target'];
            echo html_writer::start_div('alert alert-success', ['style' => 'margin: 10px 0; padding: 10px;']);
            echo '<strong>✅ Fichier identique trouvé :</strong><br>';
            echo '• Parent : ' . htmlspecialchars($target->parent_name ?? 'N/A') . '<br>';
            echo '• ID : ' . ($target->parent_id ?? $target->itemid) . '<br>';
            echo '• Type : ' . ($target->parent_type ?? $target->component);
            echo html_writer::end_div();
        } else if ($option['type'] === 'filename' && isset($option['targets'])) {
            echo html_writer::start_div('alert alert-warning', ['style' => 'margin: 10px 0; padding: 10px;']);
            echo '<strong>🔍 ' . count($option['targets']) . ' candidat(s) trouvé(s) :</strong><br>';
            foreach ($option['targets'] as $target) {
                echo '• Question #' . $target->id . ': ' . htmlspecialchars(substr($target->name, 0, 60)) . 
                     (strlen($target->name) > 60 ? '...' : '') . 
                     ' (score: ' . ($target->match_score ?? 'N/A') . '%)<br>';
            }
            echo html_writer::end_div();
        } else if ($option['type'] === 'recovery_stub') {
            echo html_writer::start_div('alert alert-info', ['style' => 'margin: 10px 0; padding: 10px;']);
            echo '<strong>📝 Solution sûre à 100% :</strong><br>';
            echo '• Crée une question "description" dans "Recovered Files"<br>';
            echo '• Préserve le fichier sans risque de perte<br>';
            echo '• Permet révision manuelle ultérieure';
            echo html_writer::end_div();
        }
        
        // Bouton d'action
        $action_params = [
            'id' => $file_id,
            'type' => $option['action'],
            'sesskey' => sesskey()
        ];
        
        // Ajouter target_id si nécessaire
        if ($option['type'] === 'contenthash' && isset($option['target'])) {
            $action_params['target_id'] = $option['target']->parent_id ?? $option['target']->itemid;
        } else if ($option['type'] === 'filename' && count($option['targets']) == 1) {
            $action_params['target_id'] = $option['targets'][0]->id;
        }
        
        echo html_writer::start_div('', ['style' => 'margin-top: 15px; display: flex; gap: 10px;']);
        
        // Dry-Run
        $dryrun_params = array_merge($action_params, ['dryrun' => 1, 'confirm' => 1]);
        echo html_writer::link(
            new moodle_url('/local/question_diagnostic/actions/orphan_repair.php', $dryrun_params),
            '🧪 ' . get_string('repair_dry_run', 'local_question_diagnostic'),
            ['class' => 'btn btn-warning']
        );
        
        // Exécuter (seulement si cible unique ou pas besoin de sélection)
        if ($option['type'] === 'contenthash' || $option['type'] === 'recovery_stub' || 
            ($option['type'] === 'filename' && count($option['targets']) == 1)) {
            $execute_params = array_merge($action_params, ['confirm' => 1]);
            echo html_writer::link(
                new moodle_url('/local/question_diagnostic/actions/orphan_repair.php', $execute_params),
                '🔧 ' . get_string('repair_execute', 'local_question_diagnostic'),
                ['class' => 'btn btn-primary']
            );
        } else {
            echo html_writer::tag('em', get_string('repair_select_option', 'local_question_diagnostic'), ['style' => 'color: #666;']);
        }
        
        echo html_writer::end_div();
        
        echo html_writer::end_tag('div'); // fin qd-repair-option
    }
    
    echo html_writer::end_tag('div'); // fin qd-repair-options
    
    // Bouton Annuler
    echo html_writer::start_div('', ['style' => 'margin: 30px 0; text-align: center;']);
    echo html_writer::link(
        new moodle_url('/local/question_diagnostic/orphan_files.php'),
        get_string('cancel', 'core'),
        ['class' => 'btn btn-secondary btn-lg']
    );
    echo html_writer::end_div();
    
    echo $OUTPUT->footer();
    exit;
}

// ======================================================================
// EXÉCUTION DE LA RÉPARATION (après sélection)
// ======================================================================

// Convertir le type d'action en type de réparation
$repair_type_map = [
    'reassociate' => 'reassociate_contenthash',
    'reassign' => 'reassign_filename',
    'create_stub' => 'create_recovery'
];

$actual_repair_type = $repair_type_map[$repair_type] ?? $repair_type;

// Paramètres de réparation
$repair_params = [];
if ($target_id > 0) {
    $repair_params['target_id'] = $target_id;
}

// Si on a une cible contenthash, la récupérer
if ($repair_type === 'reassociate') {
    $contenthash_match = orphan_file_repairer::find_by_contenthash($file_record);
    if ($contenthash_match) {
        $repair_params['target'] = $contenthash_match;
    }
}

// Exécuter la réparation
$result = orphan_file_repairer::execute_repair($file_id, $actual_repair_type, $repair_params, $dry_run);

// Purger le cache
orphan_file_detector::purge_orphan_cache();

// Message de résultat
if ($dry_run) {
    $message = '[DRY-RUN] ' . $result['message'];
    $notification_type = \core\output\notification::NOTIFY_INFO;
} else {
    $message = $result['message'];
    $notification_type = $result['success'] ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_ERROR;
}

// Redirection
redirect(
    new moodle_url('/local/question_diagnostic/orphan_files.php'),
    $message,
    null,
    $notification_type
);

