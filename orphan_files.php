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
 * Liste des fichiers orphelins : fichiers dont le CONTEXTE n'existe plus.
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/orphan_file_detector.php');

use local_question_diagnostic\orphan_file_detector;

// Contrôle d'accès : capacité du plugin + administrateur du site (écriture).
local_question_diagnostic_require_access('configureplugin', true);

$page = optional_param('page', 0, PARAM_INT);
$perpage = 500;

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/question_diagnostic/orphan_files.php', $page ? ['page' => $page] : []));
$pagetitle = get_string('orphan_files_heading', 'local_question_diagnostic');
$PAGE->set_title(get_string('orphan_files', 'local_question_diagnostic'));
$PAGE->set_heading(local_question_diagnostic_get_heading_with_version($pagetitle));
$PAGE->set_pagelayout('report');

$PAGE->requires->css('/local/question_diagnostic/styles/main.css');
$PAGE->requires->js('/local/question_diagnostic/scripts/main.js', true);

$baseurl = new moodle_url('/local/question_diagnostic/orphan_files.php');

// Rafraîchissement de l'analyse (purge du cache) : POST + sesskey uniquement.
if (optional_param('refresh', 0, PARAM_BOOL) && data_submitted()) {
    require_sesskey();
    orphan_file_detector::purge_orphan_cache();
    redirect($baseurl, get_string('refresh_orphan_analysis', 'local_question_diagnostic'),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

$globalstats = orphan_file_detector::get_global_stats();
$orphan_files = orphan_file_detector::get_orphan_files(true, $perpage, $page * $perpage);

// ======================================================================
echo $OUTPUT->header();

echo local_question_diagnostic_render_version_badge();

echo html_writer::start_div('text-right', ['style' => 'margin-bottom: 20px;']);
echo local_question_diagnostic_render_cache_purge_button();
echo html_writer::end_div();

// Lien retour + bouton rafraîchir (POST).
echo html_writer::start_tag('div', ['style' => 'margin-bottom: 20px; display: flex; gap: 10px;']);
echo local_question_diagnostic_render_back_link('orphan_files.php');
echo orphan_file_detector::render_post_button($baseurl, ['refresh' => 1],
    get_string('refresh_orphan_analysis', 'local_question_diagnostic'), 'btn btn-warning');
echo html_writer::end_tag('div');

// Définition utilisée par l'outil.
echo $OUTPUT->notification(s(get_string('orphan_definition', 'local_question_diagnostic')),
    \core\output\notification::NOTIFY_INFO, false);

// ======================================================================
// STATISTIQUES GLOBALES
// ======================================================================
echo html_writer::tag('h2', s(get_string('orphan_files_stats', 'local_question_diagnostic')));

/**
 * Rend une carte de statistique.
 *
 * @param string $title
 * @param string|int $value
 * @param string $subtitle
 * @param string $class
 * @return string
 */
function local_question_diagnostic_orphan_card(string $title, $value, string $subtitle, string $class = ''): string {
    return html_writer::div(
        html_writer::div(s($title), 'qd-card-title') .
        html_writer::div(s($value), 'qd-card-value') .
        html_writer::div(s($subtitle), 'qd-card-subtitle'),
        trim('qd-card ' . $class));
}

$filesstr = get_string('orphan_files', 'local_question_diagnostic');
echo html_writer::start_div('qd-dashboard');
echo local_question_diagnostic_orphan_card(get_string('total_orphan_files', 'local_question_diagnostic'),
    $globalstats->total_orphans, get_string('orphan_db_records', 'local_question_diagnostic'), 'warning');
echo local_question_diagnostic_orphan_card(get_string('disk_space_used', 'local_question_diagnostic'),
    $globalstats->total_filesize_formatted, $filesstr, $globalstats->total_filesize > 1073741824 ? 'danger' : 'warning');
echo local_question_diagnostic_orphan_card(get_string('orphan_status_deletable', 'local_question_diagnostic'),
    $globalstats->deletable, get_string('orphan_status_deletable_desc', 'local_question_diagnostic'), 'success');
echo local_question_diagnostic_orphan_card(get_string('orphan_status_protected', 'local_question_diagnostic'),
    $globalstats->protected, get_string('orphan_status_protected_desc', 'local_question_diagnostic'));
echo local_question_diagnostic_orphan_card(get_string('age_recent', 'local_question_diagnostic'),
    $globalstats->by_age['recent'], $filesstr);
echo local_question_diagnostic_orphan_card(get_string('age_old', 'local_question_diagnostic'),
    $globalstats->by_age['old'], $filesstr, $globalstats->by_age['old'] > 100 ? 'warning' : 'success');
echo html_writer::end_div();

// ======================================================================
// RÉPARTITION PAR COMPOSANT
// ======================================================================
if (!empty($globalstats->by_component)) {
    echo html_writer::tag('h3', s(get_string('orphan_by_component', 'local_question_diagnostic')));
    echo html_writer::start_div('qd-stats-by-type');
    foreach ($globalstats->by_component as $component => $count) {
        echo html_writer::div(
            html_writer::span(s($component), 'qd-stat-label') . html_writer::span((int)$count, 'qd-stat-value'),
            'qd-stat-item');
    }
    echo html_writer::end_div();
}

// ======================================================================
// FILTRES (côté client, sur la page affichée)
// ======================================================================
echo html_writer::start_div('qd-filters', ['style' => 'margin-top: 30px;']);
echo html_writer::tag('h4', s(get_string('filters', 'local_question_diagnostic')), ['style' => 'margin-top: 0;']);
echo html_writer::start_div('qd-filters-row');

echo html_writer::start_div('qd-filter-group');
echo html_writer::tag('label', s(get_string('search', 'local_question_diagnostic')), ['for' => 'filter-search']);
echo html_writer::empty_tag('input', ['type' => 'text', 'id' => 'filter-search', 'class' => 'form-control',
    'placeholder' => get_string('orphan_filename', 'local_question_diagnostic') . '...']);
echo html_writer::end_div();

echo html_writer::start_div('qd-filter-group');
echo html_writer::tag('label', s(get_string('filter_by_component', 'local_question_diagnostic')), ['for' => 'filter-component']);
$componentoptions = ['all' => s(get_string('all', 'local_question_diagnostic'))];
foreach ($globalstats->by_component as $component => $count) {
    $componentoptions[$component] = s($component) . ' (' . (int)$count . ')';
}
// html_writer::select() échappe les valeurs (attributs) mais pas les libellés : échappés ci-dessus.
echo html_writer::select($componentoptions, 'filter-component', 'all', false,
    ['id' => 'filter-component', 'class' => 'form-control']);
echo html_writer::end_div();

echo html_writer::start_div('qd-filter-group');
echo html_writer::tag('label', s(get_string('filter_by_age', 'local_question_diagnostic')), ['for' => 'filter-age']);
echo html_writer::select([
    'all' => s(get_string('all', 'local_question_diagnostic')),
    'recent' => s(get_string('age_recent', 'local_question_diagnostic')),
    'medium' => s(get_string('age_medium', 'local_question_diagnostic')),
    'old' => s(get_string('age_old', 'local_question_diagnostic')),
], 'filter-age', 'all', false, ['id' => 'filter-age', 'class' => 'form-control']);
echo html_writer::end_div();

echo html_writer::end_div(); // Fin qd-filters-row.
echo html_writer::end_div(); // Fin qd-filters.

// ======================================================================
// TABLEAU DES FICHIERS ORPHELINS (dans un formulaire POST pour les actions groupées)
// ======================================================================
echo html_writer::tag('h3', s(get_string('orphan_files', 'local_question_diagnostic')), ['style' => 'margin-top: 30px;']);

if ($globalstats->total_orphans > 0) {
    echo html_writer::div(orphan_file_detector::render_post_button(
        new moodle_url('/local/question_diagnostic/actions/orphan_export.php'), ['all' => 1],
        get_string('orphan_export_all', 'local_question_diagnostic'), 'btn btn-info'), 'mb-3');
}

if (empty($orphan_files)) {
    echo html_writer::div(s(get_string('no_orphan_files', 'local_question_diagnostic')), 'alert alert-success',
        ['style' => 'padding: 20px; text-align: center; font-size: 16px;']);
    echo html_writer::tag('p', s(get_string('no_orphan_files_desc', 'local_question_diagnostic')),
        ['style' => 'text-align: center; color: #666;']);
} else {
    echo $OUTPUT->paging_bar($globalstats->total_orphans, $page, $perpage, $baseurl);

    echo html_writer::start_tag('form', ['method' => 'post', 'id' => 'orphan-bulk-form',
        'action' => (new moodle_url('/local/question_diagnostic/actions/orphan_archive.php'))->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

    // Actions groupées : chaque bouton cible sa page de confirmation (aucune action directe).
    echo html_writer::start_div('qd-bulk-actions', ['style' => 'margin: 20px 0; display: none;']);
    echo html_writer::span('0', '', ['id' => 'selected-count']) . ' ' .
        s(get_string('orphan_selected', 'local_question_diagnostic')) . ' ';
    echo html_writer::tag('button', s(get_string('bulkdelete', 'local_question_diagnostic')), [
        'type' => 'submit', 'class' => 'btn btn-danger',
        'formaction' => (new moodle_url('/local/question_diagnostic/actions/orphan_delete.php'))->out(false),
    ]);
    echo ' ';
    echo html_writer::tag('button', s(get_string('archive_orphans', 'local_question_diagnostic')), [
        'type' => 'submit', 'class' => 'btn btn-warning',
        'formaction' => (new moodle_url('/local/question_diagnostic/actions/orphan_archive.php'))->out(false),
    ]);
    echo ' ';
    echo html_writer::tag('button', s(get_string('export_orphans', 'local_question_diagnostic')), [
        'type' => 'submit', 'class' => 'btn btn-info',
        'formaction' => (new moodle_url('/local/question_diagnostic/actions/orphan_export.php'))->out(false),
    ]);
    echo html_writer::end_div();

    echo html_writer::start_div('qd-table-wrapper');
    echo html_writer::start_tag('table', ['class' => 'qd-table', 'id' => 'orphan-files-table']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', html_writer::empty_tag('input', ['type' => 'checkbox', 'id' => 'select-all-orphans']));
    foreach (['orphan_file_id', 'orphan_filename', 'orphan_component', 'orphan_filearea', 'orphan_filesize',
            'orphan_status', 'orphan_reason', 'orphan_age', 'actions'] as $key) {
        echo html_writer::tag('th', s(get_string($key, 'local_question_diagnostic')));
    }
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');

    echo html_writer::start_tag('tbody');
    foreach ($orphan_files as $orphan) {
        $file = $orphan->file;
        $fileid = (int)$file->id;

        $ageclass = 'recent';
        if ($orphan->age_days >= 180) {
            $ageclass = 'old';
        } else if ($orphan->age_days >= 30) {
            $ageclass = 'medium';
        }

        // Les attributs sont échappés par html_writer.
        echo html_writer::start_tag('tr', [
            'data-file-id' => $fileid,
            'data-component' => $file->component,
            'data-filename' => $file->filename,
            'data-age-class' => $ageclass,
            'class' => 'orphan-row',
        ]);

        echo html_writer::tag('td', html_writer::empty_tag('input',
            ['type' => 'checkbox', 'class' => 'orphan-checkbox', 'name' => 'ids[]', 'value' => $fileid]));
        echo html_writer::tag('td', $fileid);
        // Les noms de fichiers peuvent venir d'élèves : toujours échappés.
        echo html_writer::tag('td', html_writer::tag('strong', s($file->filename)));
        echo html_writer::tag('td', html_writer::span(s($file->component), 'badge badge-info'));
        echo html_writer::tag('td', s($file->filearea));
        echo html_writer::tag('td', s($orphan->filesize_formatted));

        if ($orphan->deletable) {
            $status = html_writer::span(s(get_string('orphan_status_deletable', 'local_question_diagnostic')),
                'badge badge-success');
        } else {
            $status = html_writer::span(s(get_string('orphan_status_protected', 'local_question_diagnostic')),
                'badge badge-secondary', ['title' => get_string('orphan_exclusion_' . $orphan->exclusion, 'local_question_diagnostic')]);
        }
        echo html_writer::tag('td', $status);
        echo html_writer::tag('td', s($orphan->reason), ['style' => 'font-size: 11px;']);
        echo html_writer::tag('td', (int)$orphan->age_days . ' ' . s(get_string('days')));

        // Actions : liens GET vers des pages de confirmation (aucun sesskey dans l'URL, aucune action directe).
        $actions = '';
        if ($orphan->deletable) {
            $actions .= html_writer::link(
                new moodle_url('/local/question_diagnostic/actions/orphan_repair.php', ['id' => $fileid]),
                '🔧', ['class' => 'qd-btn qd-btn-success', 'title' => get_string('repair_orphan', 'local_question_diagnostic')]);
            $actions .= html_writer::link(
                new moodle_url('/local/question_diagnostic/actions/orphan_delete.php', ['id' => $fileid]),
                '🗑️', ['class' => 'qd-btn qd-btn-delete', 'title' => get_string('delete', 'local_question_diagnostic')]);
        }
        $actions .= html_writer::link(
            new moodle_url('/local/question_diagnostic/actions/orphan_archive.php', ['id' => $fileid]),
            '🗄️', ['class' => 'qd-btn qd-btn-warning', 'title' => get_string('archive_orphan', 'local_question_diagnostic')]);
        echo html_writer::tag('td', html_writer::div($actions, 'qd-actions',
            ['style' => 'display: flex; gap: 5px; flex-wrap: wrap;']));

        echo html_writer::end_tag('tr');
    }
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_div(); // Fin qd-table-wrapper.
    echo html_writer::end_tag('form');

    echo $OUTPUT->paging_bar($globalstats->total_orphans, $page, $perpage, $baseurl);
}

// ======================================================================
// JavaScript : sélection multiple et filtres (aucune donnée injectée dans le script).
// ======================================================================
$js = <<<'JS'
(function() {
    function updateBulkActionsBar() {
        var checked = document.querySelectorAll('.orphan-checkbox:checked').length;
        var bulkBar = document.querySelector('.qd-bulk-actions');
        var countSpan = document.getElementById('selected-count');
        if (!bulkBar || !countSpan) {
            return;
        }
        bulkBar.style.display = checked > 0 ? 'block' : 'none';
        countSpan.textContent = checked;
    }
    var selectAll = document.getElementById('select-all-orphans');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            document.querySelectorAll('.orphan-row').forEach(function(row) {
                if (row.style.display !== 'none') {
                    var cb = row.querySelector('.orphan-checkbox');
                    if (cb) {
                        cb.checked = selectAll.checked;
                    }
                }
            });
            updateBulkActionsBar();
        });
    }
    document.querySelectorAll('.orphan-checkbox').forEach(function(cb) {
        cb.addEventListener('change', updateBulkActionsBar);
    });

    var searchInput = document.getElementById('filter-search');
    var componentFilter = document.getElementById('filter-component');
    var ageFilter = document.getElementById('filter-age');
    function applyFilters() {
        var searchValue = searchInput ? searchInput.value.toLowerCase() : '';
        var componentValue = componentFilter ? componentFilter.value : 'all';
        var ageValue = ageFilter ? ageFilter.value : 'all';
        document.querySelectorAll('.orphan-row').forEach(function(row) {
            var filename = (row.getAttribute('data-filename') || '').toLowerCase();
            var component = row.getAttribute('data-component') || '';
            var ageClass = row.getAttribute('data-age-class') || '';
            var visible = (searchValue === '' || filename.indexOf(searchValue) !== -1) &&
                (componentValue === 'all' || component === componentValue) &&
                (ageValue === 'all' || ageClass === ageValue);
            row.style.display = visible ? '' : 'none';
        });
    }
    if (searchInput) {
        searchInput.addEventListener('input', applyFilters);
    }
    if (componentFilter) {
        componentFilter.addEventListener('change', applyFilters);
    }
    if (ageFilter) {
        ageFilter.addEventListener('change', applyFilters);
    }
})();
JS;
echo html_writer::script($js);

echo $OUTPUT->footer();
