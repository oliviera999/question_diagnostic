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
 * Nettoyage global des catégories de questions vides.
 *
 * Modes :
 * - preview : statistiques et page de confirmation (lecture seule) ;
 * - download_csv : liste des catégories qui seraient supprimées (lecture seule) ;
 * - execute (POST + sesskey) : calcule UNE fois la liste des catégories supprimables, la fige, et la confie à
 *   une tâche de fond (cleanup_categories_task). Pas de traitement par lots enchaînés dans le navigateur,
 *   pas de cascade (un parent devenu vide n'est pas supprimé), pas de contournement des protections.
 *
 * @package    local_question_diagnostic
 * @copyright  2025 Question Diagnostic Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/../classes/category_manager.php');

use local_question_diagnostic\category_manager;

// Contrôle d'accès : capacité du plugin + administrateur du site (écriture).
local_question_diagnostic_require_access('deletecategories', true);
// Aperçu accessible en GET sans sesskey ; export CSV et lancement : POST + sesskey uniquement.

$context = context_system::instance();
$PAGE->set_context($context);

$download_csv = optional_param('download_csv', 0, PARAM_INT);
$execute = optional_param('execute', 0, PARAM_INT);

if ($download_csv) {
    require_sesskey();
    handle_csv_download();
    exit;
} else if ($execute) {
    queue_cleanup();
} else {
    show_preview_page();
}

/**
 * Libellé d'une raison de conservation.
 *
 * @param string $reason
 * @return string
 */
function local_question_diagnostic_cleanup_reason_label(string $reason): string {
    return get_string('cleanup_categories_reason_' . $reason, 'local_question_diagnostic');
}

/**
 * Affiche la page de prévisualisation avec statistiques et confirmation.
 */
function show_preview_page() {
    global $OUTPUT, $PAGE;

    $PAGE->set_url(new moodle_url('/local/question_diagnostic/actions/cleanup_all_categories.php',
                   ['preview' => 1]));
    $PAGE->set_title(get_string('cleanup_all_categories_preview_title', 'local_question_diagnostic'));
    $PAGE->set_heading(local_question_diagnostic_get_heading_with_version(
        get_string('cleanup_all_categories_preview_title', 'local_question_diagnostic')
    ));
    $PAGE->set_pagelayout('admin');

    echo $OUTPUT->header();

    try {
        $stats = category_manager::get_cleanup_candidates();
    } catch (Throwable $e) {
        echo html_writer::start_tag('div', ['class' => 'alert alert-danger']);
        echo html_writer::tag('strong', '⚠️ Erreur : ');
        echo 'Impossible de calculer les statistiques de nettoyage. ';
        echo html_writer::tag('p', 'Détails : ' . s($e->getMessage()), ['style' => 'margin-top: 10px; font-size: 12px;']);
        echo html_writer::end_tag('div');
        echo $OUTPUT->footer();
        exit;
    }

    echo html_writer::start_tag('div', ['style' => 'margin-bottom: 20px;']);
    echo local_question_diagnostic_render_back_link('cleanup_all_categories.php');
    echo html_writer::end_tag('div');

    echo html_writer::tag('h2', '📊 ' . get_string('cleanup_all_categories_preview_title', 'local_question_diagnostic'));

    echo html_writer::start_tag('div', ['class' => 'alert alert-info', 'style' => 'margin-bottom: 20px;']);
    echo get_string('cleanup_all_categories_preview_desc', 'local_question_diagnostic');
    echo html_writer::end_tag('div');

    $todelete = count($stats->candidates);
    if ($todelete === 0) {
        echo html_writer::start_tag('div', ['class' => 'alert alert-success', 'style' => 'padding: 30px; text-align: center;']);
        echo html_writer::tag('h3', '✅ ' . get_string('cleanup_all_nothing_to_delete', 'local_question_diagnostic'));
        echo html_writer::tag('p', get_string('cleanup_all_categories_nothing_desc', 'local_question_diagnostic'));
        echo html_writer::end_tag('div');
        render_kept_reasons($stats);

        $back_url = new moodle_url('/local/question_diagnostic/categories.php');
        echo html_writer::start_tag('div', ['style' => 'text-align: center; margin-top: 20px;']);
        echo html_writer::link($back_url, '← ' . get_string('back', 'core'), ['class' => 'btn btn-secondary']);
        echo html_writer::end_tag('div');

        echo $OUTPUT->footer();
        exit;
    }

    $total = max(1, (int)$stats->total);
    echo html_writer::start_tag('div', ['class' => 'qd-dashboard', 'style' => 'margin-bottom: 30px;']);

    echo html_writer::start_tag('div', ['class' => 'qd-card']);
    echo html_writer::tag('div', get_string('total_categories', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
    echo html_writer::tag('div', (int)$stats->total, ['class' => 'qd-card-value']);
    echo html_writer::tag('div', get_string('in_database', 'local_question_diagnostic'), ['class' => 'qd-card-subtitle']);
    echo html_writer::end_tag('div');

    echo html_writer::start_tag('div', ['class' => 'qd-card danger']);
    echo html_writer::tag('div', get_string('cleanup_categories_to_delete', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
    echo html_writer::tag('div', $todelete, ['class' => 'qd-card-value']);
    echo html_writer::tag('div', round(($todelete / $total) * 100, 1) . ' %', ['class' => 'qd-card-subtitle']);
    echo html_writer::end_tag('div');

    echo html_writer::start_tag('div', ['class' => 'qd-card success']);
    echo html_writer::tag('div', get_string('cleanup_categories_to_keep', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
    echo html_writer::tag('div', (int)$stats->kept, ['class' => 'qd-card-value']);
    echo html_writer::tag('div', round(((int)$stats->kept / $total) * 100, 1) . ' %', ['class' => 'qd-card-subtitle']);
    echo html_writer::end_tag('div');

    echo html_writer::end_tag('div');

    render_kept_reasons($stats);

    // Avertissement et règles.
    echo html_writer::start_tag('div', ['class' => 'alert alert-danger', 'style' => 'margin: 30px 0; padding: 20px;']);
    echo html_writer::tag('h4', '⚠️ ' . get_string('action_irreversible', 'local_question_diagnostic'), ['style' => 'margin-top: 0;']);
    echo html_writer::tag('p', get_string('cleanup_all_categories_warning', 'local_question_diagnostic', $todelete),
        ['style' => 'font-size: 16px; margin-bottom: 15px;']);
    echo html_writer::tag('p', get_string('cleanup_categories_rules', 'local_question_diagnostic'));
    echo html_writer::tag('p', get_string('cleanup_categories_background', 'local_question_diagnostic'));
    echo html_writer::end_tag('div');

    echo html_writer::start_tag('div', ['style' => 'text-align: center; margin: 30px 0; display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;']);

    // Export CSV : formulaire POST (le sesskey n'apparaît jamais dans une URL).
    $csv_url = new moodle_url('/local/question_diagnostic/actions/cleanup_all_categories.php', [
        'download_csv' => 1,
    ]);
    echo $OUTPUT->single_button($csv_url, '📥 ' . get_string('cleanup_all_download_csv', 'local_question_diagnostic'),
        'post', ['type' => single_button::BUTTON_SECONDARY]);

    // Lancement : formulaire POST uniquement.
    $execute_url = new moodle_url('/local/question_diagnostic/actions/cleanup_all_categories.php', [
        'execute' => 1,
    ]);
    echo $OUTPUT->render(new single_button($execute_url,
        '🚀 ' . get_string('cleanup_all_confirm_button', 'local_question_diagnostic'), 'post', single_button::BUTTON_DANGER));

    $cancel_url = new moodle_url('/local/question_diagnostic/categories.php');
    echo html_writer::link($cancel_url, '← ' . get_string('cancel', 'core'), ['class' => 'btn btn-secondary btn-lg']);

    echo html_writer::end_tag('div');

    echo $OUTPUT->footer();
}

/**
 * Affiche la répartition des catégories conservées par raison.
 *
 * @param stdClass $stats
 */
function render_kept_reasons(stdClass $stats): void {
    if (empty($stats->reasons)) {
        return;
    }
    echo html_writer::tag('h3', '🛡️ ' . get_string('cleanup_categories_kept_reasons', 'local_question_diagnostic'));
    echo html_writer::start_tag('div', ['class' => 'qd-table-wrapper']);
    echo html_writer::start_tag('table', ['class' => 'qd-table', 'style' => 'width: 100%;']);
    echo html_writer::start_tag('tbody');
    foreach ($stats->reasons as $reason => $count) {
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', s(local_question_diagnostic_cleanup_reason_label((string)$reason)));
        echo html_writer::tag('td', (int)$count, ['style' => 'text-align: center;']);
        echo html_writer::end_tag('tr');
    }
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_tag('div');
}

/**
 * Fige la liste des catégories supprimables et programme leur suppression en tâche de fond.
 */
function queue_cleanup() {
    if (!data_submitted()) {
        throw new \moodle_exception('invalidrequest');
    }
    require_sesskey();

    // Liste calculée une seule fois, maintenant : elle ne sera plus jamais recalculée.
    $stats = category_manager::get_cleanup_candidates();
    $ids = array_map(fn($c) => (int)$c->id, $stats->candidates);
    $returnurl = new moodle_url('/local/question_diagnostic/categories.php');

    if (empty($ids)) {
        redirect($returnurl, get_string('cleanup_all_nothing_to_delete', 'local_question_diagnostic'), null,
            \core\output\notification::NOTIFY_INFO);
    }

    \local_question_diagnostic\task\cleanup_categories_task::queue($ids, 'cleanup_all_categories');

    redirect($returnurl, get_string('cleanup_categories_queued', 'local_question_diagnostic', count($ids)), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

/**
 * Gère le téléchargement CSV (lecture seule).
 */
function handle_csv_download() {
    $stats = category_manager::get_cleanup_candidates();

    $filename = 'cleanup_categories_' . date('Y-m-d_His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    fputcsv($output, ['ID', 'Nom', 'Context ID', 'Contexte', 'Parent ID', 'Action'], ';');
    foreach ($stats->candidates as $cat) {
        fputcsv($output, [
            $cat->id,
            $cat->name,
            $cat->contextid,
            $cat->context_name,
            $cat->parent,
            'Supprimer',
        ], ';');
    }

    fclose($output);
}
