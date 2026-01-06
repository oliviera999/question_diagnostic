<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/orphan_file_detector.php');
require_once(__DIR__ . '/classes/orphan_file_repairer.php');

use local_question_diagnostic\orphan_file_detector;
use local_question_diagnostic\orphan_file_repairer;

// Charger les bibliothèques Moodle nécessaires.
require_login();

// Vérification stricte : seuls les administrateurs du site peuvent accéder à cette page.
if (!is_siteadmin()) {
    throw new \moodle_exception('accessdenied', 'admin', '', get_string('accessdenied', 'local_question_diagnostic'));
    exit;
}

// Définir le contexte de la page (système).
$context = context_system::instance();

// Définir le titre et l'URL de la page.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/question_diagnostic/orphan_files.php'));
$pagetitle = get_string('orphan_files_heading', 'local_question_diagnostic');
$PAGE->set_title(get_string('orphan_files', 'local_question_diagnostic'));
$PAGE->set_heading(local_question_diagnostic_get_heading_with_version($pagetitle));
$PAGE->set_pagelayout('report');

// Ajouter les CSS et JavaScript personnalisés
$PAGE->requires->css('/local/question_diagnostic/styles/main.css');
$PAGE->requires->js('/local/question_diagnostic/scripts/main.js', true);

// Traitement des actions
$action = optional_param('action', '', PARAM_ALPHA);
$refresh = optional_param('refresh', 0, PARAM_INT);

// Action de rafraîchissement du cache
if ($refresh) {
    require_sesskey();
    orphan_file_detector::purge_orphan_cache();
    redirect($PAGE->url, '✅ ' . get_string('refresh_orphan_analysis', 'local_question_diagnostic'), 
             null, \core\output\notification::NOTIFY_SUCCESS);
}

// ======================================================================
// Section d'en-tête Moodle standard.
echo $OUTPUT->header();

// Afficher le badge de version
echo local_question_diagnostic_render_version_badge();

// Afficher le bouton de purge des caches
echo html_writer::start_div('text-right', ['style' => 'margin-bottom: 20px;']);
echo local_question_diagnostic_render_cache_purge_button();
echo html_writer::end_div();

// Lien retour + Bouton rafraîchir
echo html_writer::start_tag('div', ['style' => 'margin-bottom: 20px; display: flex; gap: 10px;']);
echo local_question_diagnostic_render_back_link('orphan_files.php');
echo html_writer::link(
    new moodle_url('/local/question_diagnostic/orphan_files.php', ['refresh' => 1, 'sesskey' => sesskey()]),
    '🔄 ' . get_string('refresh_orphan_analysis', 'local_question_diagnostic'),
    [
        'class' => 'btn btn-warning',
        'title' => get_string('refresh_orphan_analysis', 'local_question_diagnostic')
    ]
);
echo html_writer::end_tag('div');

// ======================================================================
// STATISTIQUES GLOBALES
// ======================================================================

echo html_writer::tag('h2', '📊 ' . get_string('orphan_files_stats', 'local_question_diagnostic'));

$globalstats = orphan_file_detector::get_global_stats();

// Charger la liste des orphelins (utilisée par l'analyse + le tableau).
// Important: on doit la récupérer AVANT l'analyse de réparabilité.
$orphan_files = orphan_file_detector::get_orphan_files(true, 1000);

echo html_writer::start_tag('div', ['class' => 'qd-dashboard']);

// Carte 1 : Total fichiers orphelins
echo html_writer::start_tag('div', ['class' => 'qd-card warning']);
echo html_writer::tag('div', get_string('total_orphan_files', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', $globalstats->total_orphans, ['class' => 'qd-card-value']);
echo html_writer::tag('div', get_string('orphan_db_records', 'local_question_diagnostic'), ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

// Carte 2 : Espace disque occupé
$card_class = $globalstats->total_filesize > 1073741824 ? 'danger' : 'warning'; // > 1 GB = danger
echo html_writer::start_tag('div', ['class' => 'qd-card ' . $card_class]);
echo html_writer::tag('div', get_string('disk_space_used', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', $globalstats->total_filesize_formatted, ['class' => 'qd-card-value']);
echo html_writer::tag('div', get_string('orphan_files', 'local_question_diagnostic'), ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

// Carte 3 : Fichiers récents (< 1 mois)
echo html_writer::start_tag('div', ['class' => 'qd-card']);
echo html_writer::tag('div', get_string('age_recent', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', $globalstats->by_age['recent'], ['class' => 'qd-card-value']);
echo html_writer::tag('div', get_string('orphan_files', 'local_question_diagnostic'), ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

// Carte 4 : Fichiers anciens (> 6 mois)
$old_files_class = $globalstats->by_age['old'] > 100 ? 'warning' : 'success';
echo html_writer::start_tag('div', ['class' => 'qd-card ' . $old_files_class]);
echo html_writer::tag('div', get_string('age_old', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', $globalstats->by_age['old'], ['class' => 'qd-card-value']);
echo html_writer::tag('div', get_string('orphan_files', 'local_question_diagnostic'), ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

echo html_writer::end_tag('div'); // fin dashboard

// ======================================================================
// 🆕 v1.10.1 : ANALYSE DE RÉPARABILITÉ
// ======================================================================

echo html_writer::tag('h3', '🔧 ' . get_string('repair_analysis', 'local_question_diagnostic'), ['style' => 'margin-top: 30px;']);

// Analyser la réparabilité en masse
$repairability_stats = orphan_file_repairer::analyze_bulk_repairability($orphan_files);

echo html_writer::start_tag('div', ['class' => 'qd-dashboard', 'style' => 'margin: 20px 0;']);

// Haute fiabilité
echo html_writer::start_tag('div', ['class' => 'qd-card success']);
echo html_writer::tag('div', '🟢 ' . get_string('repairability_high', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', $repairability_stats['high_confidence'], ['class' => 'qd-card-value']);
echo html_writer::tag('div', get_string('repair_auto_recommended', 'local_question_diagnostic'), ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

// Fiabilité moyenne
echo html_writer::start_tag('div', ['class' => 'qd-card warning']);
echo html_writer::tag('div', '🟡 ' . get_string('repairability_medium', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', $repairability_stats['medium_confidence'], ['class' => 'qd-card-value']);
echo html_writer::tag('div', get_string('repair_manual_recommended', 'local_question_diagnostic'), ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

// Faible fiabilité
echo html_writer::start_tag('div', ['class' => 'qd-card']);
echo html_writer::tag('div', '🔴 ' . get_string('repairability_low', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', $repairability_stats['low_confidence'], ['class' => 'qd-card-value']);
echo html_writer::tag('div', get_string('repair_not_recommended', 'local_question_diagnostic'), ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

echo html_writer::end_tag('div'); // fin dashboard réparabilité

// ======================================================================
// RÉPARTITION PAR COMPOSANT
// ======================================================================

if (!empty($globalstats->by_component)) {
    echo html_writer::tag('h3', '📈 ' . get_string('orphan_by_component', 'local_question_diagnostic'));
    
    echo html_writer::start_tag('div', ['class' => 'qd-stats-by-type']);
    foreach ($globalstats->by_component as $component => $count) {
        echo html_writer::start_tag('div', ['class' => 'qd-stat-item']);
        echo html_writer::tag('span', $component, ['class' => 'qd-stat-label']);
        echo html_writer::tag('span', $count, ['class' => 'qd-stat-value']);
        echo html_writer::end_tag('div');
    }
    echo html_writer::end_tag('div');
}

// ======================================================================
// FILTRES
// ======================================================================

echo html_writer::start_tag('div', ['class' => 'qd-filters', 'style' => 'margin-top: 30px;']);
echo html_writer::tag('h4', '🔍 ' . get_string('filters', 'local_question_diagnostic'), ['style' => 'margin-top: 0;']);

echo html_writer::start_tag('div', ['class' => 'qd-filters-row']);

// Recherche par nom
echo html_writer::start_tag('div', ['class' => 'qd-filter-group']);
echo html_writer::tag('label', get_string('search', 'local_question_diagnostic'), ['for' => 'filter-search']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'id' => 'filter-search',
    'placeholder' => get_string('orphan_filename', 'local_question_diagnostic') . '...',
    'class' => 'form-control'
]);
echo html_writer::end_tag('div');

// Filtre par composant
echo html_writer::start_tag('div', ['class' => 'qd-filter-group']);
echo html_writer::tag('label', get_string('filter_by_component', 'local_question_diagnostic'), ['for' => 'filter-component']);
echo html_writer::start_tag('select', ['id' => 'filter-component', 'class' => 'form-control']);
echo html_writer::tag('option', get_string('all', 'local_question_diagnostic'), ['value' => 'all']);
foreach ($globalstats->by_component as $component => $count) {
    echo html_writer::tag('option', $component . " ($count)", ['value' => $component]);
}
echo html_writer::end_tag('select');
echo html_writer::end_tag('div');

// Filtre par âge
echo html_writer::start_tag('div', ['class' => 'qd-filter-group']);
echo html_writer::tag('label', get_string('filter_by_age', 'local_question_diagnostic'), ['for' => 'filter-age']);
echo html_writer::start_tag('select', ['id' => 'filter-age', 'class' => 'form-control']);
echo html_writer::tag('option', get_string('all', 'local_question_diagnostic'), ['value' => 'all']);
echo html_writer::tag('option', get_string('age_recent', 'local_question_diagnostic'), ['value' => 'recent']);
echo html_writer::tag('option', get_string('age_medium', 'local_question_diagnostic'), ['value' => 'medium']);
echo html_writer::tag('option', get_string('age_old', 'local_question_diagnostic'), ['value' => 'old']);
echo html_writer::end_tag('select');
echo html_writer::end_tag('div');

echo html_writer::end_tag('div'); // fin qd-filters-row
echo html_writer::end_tag('div'); // fin qd-filters

// ======================================================================
// ACTIONS GROUPÉES
// ======================================================================

echo html_writer::start_tag('div', ['class' => 'qd-bulk-actions', 'style' => 'margin: 20px 0; display: none;']);
echo html_writer::tag('span', '<span id="selected-count">0</span> ' . get_string('categoriesselected', 'local_question_diagnostic'), ['style' => 'margin-right: 10px;']);
echo html_writer::tag('button', '🗑️ ' . get_string('bulkdelete', 'local_question_diagnostic'), [
    'class' => 'btn btn-danger',
    'id' => 'bulk-delete-btn',
    'onclick' => 'bulkDeleteOrphans()'
]);
echo html_writer::tag('button', '🗄️ ' . get_string('archive_orphans', 'local_question_diagnostic'), [
    'class' => 'btn btn-warning',
    'id' => 'bulk-archive-btn',
    'onclick' => 'bulkArchiveOrphans()'
]);
echo html_writer::tag('button', '📥 ' . get_string('export_orphans', 'local_question_diagnostic'), [
    'class' => 'btn btn-info',
    'id' => 'bulk-export-btn',
    'onclick' => 'exportSelectedOrphans()'
]);
echo html_writer::end_tag('div');

// ======================================================================
// TABLEAU DES FICHIERS ORPHELINS
// ======================================================================

echo html_writer::tag('h3', '🗑️ ' . get_string('orphan_files', 'local_question_diagnostic'), ['style' => 'margin-top: 30px;']);

// Afficher un avertissement si limite atteinte
if (count($orphan_files) >= 1000) {
    echo html_writer::start_div('alert alert-info', ['style' => 'margin-bottom: 20px;']);
    echo '<strong>ℹ️ Note :</strong> ' . get_string('orphan_files_limit_notice', 'local_question_diagnostic', 1000);
    echo html_writer::end_div();
}

if (empty($orphan_files)) {
    echo html_writer::tag('div', '✅ ' . get_string('no_orphan_files', 'local_question_diagnostic'), [
        'class' => 'alert alert-success',
        'style' => 'padding: 20px; text-align: center; font-size: 16px;'
    ]);
    echo html_writer::tag('p', get_string('no_orphan_files_desc', 'local_question_diagnostic'), [
        'style' => 'text-align: center; color: #666;'
    ]);
} else {
    echo html_writer::start_tag('div', ['class' => 'qd-table-wrapper']);
    echo html_writer::start_tag('table', ['class' => 'qd-table', 'id' => 'orphan-files-table']);
    
    // En-tête du tableau
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', '<input type="checkbox" id="select-all-orphans">');
    echo html_writer::tag('th', get_string('orphan_file_id', 'local_question_diagnostic'));
    echo html_writer::tag('th', get_string('orphan_filename', 'local_question_diagnostic'));
    echo html_writer::tag('th', get_string('orphan_component', 'local_question_diagnostic'));
    echo html_writer::tag('th', get_string('orphan_filesize', 'local_question_diagnostic'));
    echo html_writer::tag('th', get_string('orphan_type', 'local_question_diagnostic'));
    echo html_writer::tag('th', get_string('repairability', 'local_question_diagnostic')); // 🆕 v1.10.1
    echo html_writer::tag('th', get_string('orphan_reason', 'local_question_diagnostic'));
    echo html_writer::tag('th', get_string('orphan_age', 'local_question_diagnostic'));
    echo html_writer::tag('th', get_string('actions', 'local_question_diagnostic'));
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    
    // Corps du tableau
    echo html_writer::start_tag('tbody');
    
    foreach ($orphan_files as $orphan) {
        $file = $orphan->file;
        
        // Déterminer la classe d'âge
        $age_class = 'recent';
        if ($orphan->age_days >= 180) {
            $age_class = 'old';
        } else if ($orphan->age_days >= 30) {
            $age_class = 'medium';
        }
        
        // Attributs pour le filtrage
        $row_attrs = [
            'data-file-id' => $file->id,
            'data-component' => $file->component,
            'data-filename' => $file->filename,
            'data-age-class' => $age_class,
            'class' => 'orphan-row'
        ];
        
        echo html_writer::start_tag('tr', $row_attrs);
        
        // Checkbox
        echo html_writer::start_tag('td');
        echo html_writer::empty_tag('input', [
            'type' => 'checkbox',
            'class' => 'orphan-checkbox',
            'data-file-id' => $file->id
        ]);
        echo html_writer::end_tag('td');
        
        // ID
        echo html_writer::tag('td', $file->id);
        
        // Nom du fichier
        echo html_writer::tag('td', html_writer::tag('strong', htmlspecialchars($file->filename)));
        
        // Composant
        echo html_writer::tag('td', html_writer::tag('span', 
            htmlspecialchars($file->component), 
            ['class' => 'badge badge-info']
        ));
        
        // Taille
        echo html_writer::tag('td', $orphan->filesize_formatted);
        
        // Type d'orphelin
        $type_badge_class = $orphan->orphan_type === 'context_invalid' ? 'badge-danger' : 'badge-warning';
        echo html_writer::tag('td', html_writer::tag('span', 
            get_string('orphan_reason_' . $orphan->orphan_type, 'local_question_diagnostic'), 
            ['class' => 'badge ' . $type_badge_class]
        ));
        
        // 🆕 v1.10.1 : Réparabilité
        $repairability = orphan_file_repairer::get_repairability_level($file);
        $repair_icon = '';
        $repair_class = 'badge ';
        $repair_text = '';
        
        if ($repairability === 'high') {
            $repair_icon = '🟢';
            $repair_class .= 'badge-success';
            $repair_text = get_string('repairability_high', 'local_question_diagnostic');
        } else if ($repairability === 'medium') {
            $repair_icon = '🟡';
            $repair_class .= 'badge-warning';
            $repair_text = get_string('repairability_medium', 'local_question_diagnostic');
        } else {
            $repair_icon = '🔴';
            $repair_class .= 'badge-secondary';
            $repair_text = get_string('repairability_low', 'local_question_diagnostic');
        }
        
        echo html_writer::tag('td', html_writer::tag('span', 
            $repair_icon . ' ' . $repair_text, 
            ['class' => $repair_class]
        ));
        
        // Raison
        echo html_writer::tag('td', htmlspecialchars($orphan->reason), ['style' => 'font-size: 11px;']);
        
        // Âge
        echo html_writer::tag('td', $orphan->age_days . ' ' . get_string('days', 'core'));
        
        // Actions
        echo html_writer::start_tag('td');
        echo html_writer::start_tag('div', ['class' => 'qd-actions', 'style' => 'display: flex; gap: 5px; flex-wrap: wrap;']);
        
        // 🆕 v1.10.1 : Bouton réparer (si réparation possible)
        if ($repairability !== 'low') {
            $repair_url = new moodle_url('/local/question_diagnostic/actions/orphan_repair.php', [
                'id' => $file->id,
                'sesskey' => sesskey()
            ]);
            $repair_btn_class = $repairability === 'high' ? 'qd-btn-success' : 'qd-btn-warning';
            echo html_writer::link(
                $repair_url, 
                '🔧',
                [
                    'class' => 'qd-btn ' . $repair_btn_class,
                    'title' => get_string('repair_orphan', 'local_question_diagnostic')
                ]
            );
        }
        
        // Bouton supprimer
        $delete_url = new moodle_url('/local/question_diagnostic/actions/orphan_delete.php', [
            'id' => $file->id,
            'sesskey' => sesskey()
        ]);
        echo html_writer::link(
            $delete_url, 
            '🗑️',
            [
                'class' => 'qd-btn qd-btn-delete',
                'title' => get_string('delete', 'local_question_diagnostic')
            ]
        );
        
        // Bouton archiver
        $archive_url = new moodle_url('/local/question_diagnostic/actions/orphan_archive.php', [
            'id' => $file->id,
            'sesskey' => sesskey()
        ]);
        echo html_writer::link(
            $archive_url, 
            '🗄️',
            [
                'class' => 'qd-btn qd-btn-warning',
                'title' => get_string('archive_orphan', 'local_question_diagnostic')
            ]
        );
        
        echo html_writer::end_tag('div');
        echo html_writer::end_tag('td');
        
        echo html_writer::end_tag('tr');
    }
    
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_tag('div'); // fin qd-table-wrapper
}

// ======================================================================
// JavaScript pour filtres et sélection multiple
// ======================================================================

echo html_writer::start_tag('script');
?>
// Gestion du select all
document.getElementById('select-all-orphans')?.addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.orphan-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
    updateBulkActionsBar();
});

// Gestion des checkboxes individuelles
document.querySelectorAll('.orphan-checkbox').forEach(function(checkbox) {
    checkbox.addEventListener('change', updateBulkActionsBar);
});

// Mise à jour de la barre d'actions groupées
function updateBulkActionsBar() {
    const checked = document.querySelectorAll('.orphan-checkbox:checked').length;
    const bulkBar = document.querySelector('.qd-bulk-actions');
    const countSpan = document.getElementById('selected-count');
    
    if (checked > 0) {
        bulkBar.style.display = 'block';
        countSpan.textContent = checked;
    } else {
        bulkBar.style.display = 'none';
    }
}

// Filtres
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('filter-search');
    const componentFilter = document.getElementById('filter-component');
    const ageFilter = document.getElementById('filter-age');
    
    function applyFilters() {
        const searchValue = searchInput ? searchInput.value.toLowerCase() : '';
        const componentValue = componentFilter ? componentFilter.value : 'all';
        const ageValue = ageFilter ? ageFilter.value : 'all';
        
        const rows = document.querySelectorAll('.orphan-row');
        let visibleCount = 0;
        
        rows.forEach(function(row) {
            const filename = (row.getAttribute('data-filename') || '').toLowerCase();
            const component = row.getAttribute('data-component') || '';
            const ageClass = row.getAttribute('data-age-class') || '';
            
            const matchesSearch = searchValue === '' || filename.includes(searchValue);
            const matchesComponent = componentValue === 'all' || component === componentValue;
            const matchesAge = ageValue === 'all' || ageClass === ageValue;
            
            if (matchesSearch && matchesComponent && matchesAge) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
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
});

// Actions groupées
function bulkDeleteOrphans() {
    const checked = document.querySelectorAll('.orphan-checkbox:checked');
    const ids = Array.from(checked).map(cb => cb.getAttribute('data-file-id'));
    
    if (confirm('<?php echo get_string('confirm_delete_orphans_message', 'local_question_diagnostic', ''); ?>' + ids.length + ' fichier(s) ?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'actions/orphan_delete.php';
        
        ids.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = id;
            form.appendChild(input);
        });
        
        const sesskey = document.createElement('input');
        sesskey.type = 'hidden';
        sesskey.name = 'sesskey';
        sesskey.value = '<?php echo sesskey(); ?>';
        form.appendChild(sesskey);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function bulkArchiveOrphans() {
    const checked = document.querySelectorAll('.orphan-checkbox:checked');
    const ids = Array.from(checked).map(cb => cb.getAttribute('data-file-id'));
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'actions/orphan_archive.php';
    
    ids.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = id;
        form.appendChild(input);
    });
    
    const sesskey = document.createElement('input');
    sesskey.type = 'hidden';
    sesskey.name = 'sesskey';
    sesskey.value = '<?php echo sesskey(); ?>';
    form.appendChild(sesskey);
    
    document.body.appendChild(form);
    form.submit();
}

function exportSelectedOrphans() {
    const checked = document.querySelectorAll('.orphan-checkbox:checked');
    const ids = Array.from(checked).map(cb => cb.getAttribute('data-file-id'));
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'actions/orphan_export.php';
    
    ids.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = id;
        form.appendChild(input);
    });
    
    const sesskey = document.createElement('input');
    sesskey.type = 'hidden';
    sesskey.name = 'sesskey';
    sesskey.value = '<?php echo sesskey(); ?>';
    form.appendChild(sesskey);
    
    document.body.appendChild(form);
    form.submit();
}
<?php
echo html_writer::end_tag('script');

// ======================================================================
// Pied de page Moodle standard
// ======================================================================
echo $OUTPUT->footer();

