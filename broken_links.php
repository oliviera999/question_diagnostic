<?php
// ======================================================================
// Moodle Question Link Checker - Détection et réparation des liens cassés
// ======================================================================

// Inclure la configuration de Moodle.
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/question_link_checker.php');
require_once(__DIR__ . '/classes/question_analyzer.php');

use local_question_diagnostic\question_link_checker;
use local_question_diagnostic\question_analyzer;

// Charger les bibliothèques Moodle nécessaires.
require_login();

// Vérification stricte : seuls les administrateurs du site peuvent accéder à cette page.
if (!is_siteadmin()) {
    throw new \moodle_exception('accessdenied', 'admin', '', 'Vous devez être administrateur du site pour accéder à cet outil.');
}

// Définir le contexte de la page (système).
$context = context_system::instance();

// Définir le titre et l'URL de la page.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/question_diagnostic/broken_links.php'));
$pagetitle = get_string('brokenlinks_heading', 'local_question_diagnostic');
$PAGE->set_title(get_string('brokenlinks', 'local_question_diagnostic'));
$PAGE->set_heading(local_question_diagnostic_get_heading_with_version($pagetitle));
$PAGE->set_pagelayout('report');

// Ajouter les CSS et JavaScript personnalisés
$PAGE->requires->css('/local/question_diagnostic/styles/main.css');
$PAGE->requires->js('/local/question_diagnostic/scripts/main.js', true);

// Traitement des actions
$action = optional_param('action', '', PARAM_ALPHA);
$questionid = optional_param('questionid', 0, PARAM_INT);
$field = optional_param('field', '', PARAM_TEXT);
$url = optional_param('url', '', PARAM_RAW_TRIMMED);
$refresh = optional_param('refresh', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);
$newurl = optional_param('newurl', '', PARAM_RAW_TRIMMED);
$sourcequestionid = optional_param('sourcequestionid', 0, PARAM_INT);

$show_repair_confirm = false;
$repair_result = null;

// Action de rafraîchissement du cache
if ($refresh) {
    require_sesskey();
    question_link_checker::purge_broken_links_cache();
    redirect($PAGE->url, '✅ Cache purgé. Analyse des liens en cours...', null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($action) {
    require_sesskey();
    if ($action === 'remove') {
        $result = question_link_checker::remove_broken_link($questionid, $field, $url);
        if ($result === true) {
            // Purger le cache après modification
            question_link_checker::purge_broken_links_cache();
            redirect($PAGE->url, 'Lien cassé supprimé avec succès.', null, \core\output\notification::NOTIFY_SUCCESS);
        } else {
            redirect($PAGE->url, $result, null, \core\output\notification::NOTIFY_ERROR);
        }
    } else if ($action === 'repair') {
        // Deux étapes:
        // - confirm=0 : proposer des suggestions (sans modifier la BDD)
        // - confirm=1 : appliquer le remplacement (MODIFIE la BDD)
        if ($confirm && !empty($newurl)) {
            $apply = question_link_checker::replace_broken_link_with_url($questionid, $field, $url, $newurl, $sourcequestionid ?: null);
            if ($apply === true) {
                question_link_checker::purge_broken_links_cache();
                redirect($PAGE->url, 'Réparation appliquée avec succès.', null, \core\output\notification::NOTIFY_SUCCESS);
            } else {
                redirect($PAGE->url, (string)$apply, null, \core\output\notification::NOTIFY_ERROR);
            }
        } else {
            $repair_result = question_link_checker::attempt_repair($questionid, $field, $url);
            if (is_array($repair_result) && !empty($repair_result['success']) && !empty($repair_result['suggestions'])) {
                $show_repair_confirm = true;
            } else {
                $msg = is_array($repair_result) && !empty($repair_result['message'])
                    ? $repair_result['message']
                    : 'Aucune suggestion de réparation disponible.';
                redirect($PAGE->url, $msg, null, \core\output\notification::NOTIFY_INFO);
            }
        }
    } else if ($action === 'search_similar') {
        // 🔧 NOUVEAU v1.12.6 : Recherche de fichiers similaires par nom
        $search_result = question_link_checker::search_similar_files($url, $questionid, 20);
        if (is_array($search_result) && !empty($search_result['success']) && !empty($search_result['suggestions'])) {
            $show_repair_confirm = true;
            $repair_result = $search_result; // Réutiliser la même structure pour l'affichage
        } else {
            $msg = is_array($search_result) && !empty($search_result['message'])
                ? $search_result['message']
                : 'Aucun fichier similaire trouvé.';
            redirect($PAGE->url, $msg, null, \core\output\notification::NOTIFY_INFO);
        }
    }
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

// Page de confirmation "réparer" ou "recherche similaire" (évite d'afficher tout le tableau si on est en mode réparation)
if ($show_repair_confirm && is_array($repair_result)) {
    $is_search_similar = ($action === 'search_similar');
    $page_title = $is_search_similar ? '🔍 Recherche de fichiers similaires' : '🔧 Réparation de lien cassé';
    echo html_writer::tag('h2', $page_title);

    echo html_writer::start_div('alert alert-info');
    if ($is_search_similar) {
        echo '<strong>Principe :</strong> recherche de fichiers avec des noms similaires dans toute la base de données (nom exact, même base, même extension, ou nom partiel). ';
        echo 'Vous pouvez sélectionner un fichier pour remplacer le lien cassé.';
    } else {
        echo '<strong>Principe :</strong> recherche de fichiers existants dans le contexte de la question et/ou de doublons stricts (même type + même texte). ';
        echo 'Vous devez confirmer avant toute modification.';
    }
    echo html_writer::end_div();

    echo html_writer::start_div('qd-card', ['style' => 'padding: 15px; margin-bottom: 20px;']);
    echo html_writer::tag('div', '<strong>Question:</strong> #' . (int)$questionid);
    echo html_writer::tag('div', '<strong>Champ:</strong> ' . s($field));
    echo html_writer::tag('div', '<strong>URL cassée:</strong> <code style="word-break: break-all;">' . s($url) . '</code>');
    echo html_writer::end_div();

    $suggestions = (array)($repair_result['suggestions'] ?? []);
    if (empty($suggestions)) {
        echo html_writer::start_div('alert alert-warning');
        echo 'Aucune suggestion disponible.';
        echo html_writer::end_div();
    } else {
        echo html_writer::tag('h3', 'Suggestions');
        echo html_writer::start_tag('ul');
        foreach ($suggestions as $sugg) {
            $replacement_url = is_array($sugg) ? ($sugg['replacement_url'] ?? '') : '';
            $srcid = is_array($sugg) ? (int)($sugg['sourcequestionid'] ?? 0) : 0;
            $desc = is_array($sugg) ? ($sugg['description'] ?? '') : '';
            $sugg_type = is_array($sugg) ? ($sugg['type'] ?? '') : '';
            $confidence = is_array($sugg) ? ($sugg['confidence'] ?? 0) : 0;

            // 🔧 v1.12.3 : Permettre les suggestions sans sourcequestionid (fichiers trouvés dans le contexte)
            if (empty($replacement_url)) {
                continue;
            }

            $apply_url = new moodle_url('/local/question_diagnostic/broken_links.php', [
                'action' => 'repair',
                'confirm' => 1,
                'questionid' => (int)$questionid,
                'field' => $field,
                'url' => $url,
                'newurl' => $replacement_url,
                'sourcequestionid' => $srcid > 0 ? $srcid : 0,
                'sesskey' => sesskey(),
            ]);

            // Afficher différemment selon le type de suggestion
            $source_info = '';
            if ($srcid > 0) {
                $source_info = html_writer::tag('div', '<strong>Source:</strong> Question #' . $srcid, ['style' => 'font-size: 12px; color: #666;']);
            } else if ($sugg_type === 'similar_file_in_database') {
                $file_info = is_array($sugg) && isset($sugg['file_info']) ? $sugg['file_info'] : null;
                if ($file_info) {
                    $original_filename = isset($file_info['original_filename']) ? s($file_info['original_filename']) : 'N/A';
                    $similarity_score = isset($file_info['similarity_score']) ? (int)$file_info['similarity_score'] : 0;
                    $source_info = html_writer::tag('div', 
                        '<strong>Fichier trouvé:</strong> ' . $original_filename . 
                        ' | <strong>Contexte:</strong> ID ' . (int)($file_info['contextid'] ?? 0) . 
                        ' | <strong>Zone:</strong> ' . s($file_info['filearea'] ?? '') . 
                        ' | <strong>Similarité:</strong> ' . $similarity_score . '%' .
                        ' | <strong>Confiance:</strong> ' . (int)$confidence . '%',
                        ['style' => 'font-size: 12px; color: #666;']
                    );
                } else {
                    $source_info = html_writer::tag('div', '<strong>Type:</strong> Fichier similaire trouvé dans la base | <strong>Confiance:</strong> ' . (int)$confidence . '%', ['style' => 'font-size: 12px; color: #666;']);
                }
            } else if ($sugg_type === 'file_found_in_context') {
                $file_info = is_array($sugg) && isset($sugg['file_info']) ? $sugg['file_info'] : null;
                if ($file_info) {
                    $source_info = html_writer::tag('div', 
                        '<strong>Contexte:</strong> ID ' . (int)($file_info['contextid'] ?? 0) . 
                        ' | <strong>Zone:</strong> ' . s($file_info['filearea'] ?? '') . 
                        ' | <strong>Confiance:</strong> ' . (int)$confidence . '%',
                        ['style' => 'font-size: 12px; color: #666;']
                    );
                } else {
                    $source_info = html_writer::tag('div', '<strong>Type:</strong> Fichier trouvé dans le contexte | <strong>Confiance:</strong> ' . (int)$confidence . '%', ['style' => 'font-size: 12px; color: #666;']);
                }
            }

            $confirm_text = '';
            if ($sugg_type === 'similar_file_in_database') {
                $confirm_text = 'Confirmer le remplacement du lien cassé par le fichier similaire trouvé dans la base ?';
            } else if ($sugg_type === 'file_found_in_context') {
                $confirm_text = 'Confirmer le remplacement du lien cassé par le fichier trouvé dans le contexte ?';
            } else {
                $confirm_text = 'Confirmer le remplacement du lien cassé par celui du doublon ?';
            }

            echo html_writer::tag('li',
                html_writer::tag('div', s($desc)) .
                $source_info .
                html_writer::tag('div', '<strong>Nouvelle URL:</strong> <code style="word-break: break-all;">' . s($replacement_url) . '</code>', ['style' => 'margin: 6px 0;']) .
                html_writer::link($apply_url, '✅ Appliquer cette réparation', ['class' => 'btn btn-primary btn-sm', 'onclick' => "return confirm('" . addslashes($confirm_text) . "')"])
            );
        }
        echo html_writer::end_tag('ul');
    }

    echo html_writer::link(new moodle_url('/local/question_diagnostic/broken_links.php'), '← Retour', ['class' => 'btn btn-secondary']);
    echo $OUTPUT->footer();
    exit;
}

// 🆕 v1.9.44 : Lien retour hiérarchique + Bouton rafraîchir
echo html_writer::start_tag('div', ['style' => 'margin-bottom: 20px; display: flex; gap: 10px;']);
echo local_question_diagnostic_render_back_link('broken_links.php');
echo html_writer::link(
    new moodle_url('/local/question_diagnostic/broken_links.php', ['refresh' => 1, 'sesskey' => sesskey()]),
    '🔄 Rafraîchir l\'analyse',
    [
        'class' => 'btn btn-warning',
        'title' => 'Forcer une nouvelle analyse des liens (purge le cache)'
    ]
);

// 🆕 v1.9.35 : Lien vers le centre d'aide
echo html_writer::link(
    new moodle_url('/local/question_diagnostic/help.php'),
    '📚 Aide',
    ['class' => 'btn btn-outline-info']
);

echo html_writer::end_tag('div');

// ======================================================================
// STATISTIQUES GLOBALES
// ======================================================================

echo html_writer::tag('h2', '📊 ' . get_string('brokenlinks_stats', 'local_question_diagnostic'));

$globalstats = question_link_checker::get_global_stats();

echo html_writer::start_tag('div', ['class' => 'qd-dashboard']);

// Carte 1 : Total questions
echo html_writer::start_tag('div', ['class' => 'qd-card']);
echo html_writer::tag('div', get_string('total_questions_stats', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', $globalstats->total_questions, ['class' => 'qd-card-value']);
echo html_writer::tag('div', get_string('in_database', 'local_question_diagnostic'), ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

// Carte 2 : Questions avec liens cassés
echo html_writer::start_tag('div', ['class' => 'qd-card danger']);
echo html_writer::tag('div', get_string('questions_with_broken_links', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', $globalstats->questions_with_broken_links, ['class' => 'qd-card-value']);
echo html_writer::tag('div', get_string('questions_with_problems', 'local_question_diagnostic'), ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

// Carte 3 : Total liens cassés
echo html_writer::start_tag('div', ['class' => 'qd-card warning']);
echo html_writer::tag('div', get_string('total_broken_links', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', $globalstats->total_broken_links, ['class' => 'qd-card-value']);
echo html_writer::tag('div', get_string('questions_broken_links', 'local_question_diagnostic'), ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

// Carte 4 : Pourcentage de questions OK
$percentage_ok = $globalstats->total_questions > 0 
    ? round(($globalstats->total_questions - $globalstats->questions_with_broken_links) / $globalstats->total_questions * 100, 1)
    : 100;
$card_class = $percentage_ok >= 90 ? 'success' : ($percentage_ok >= 70 ? 'warning' : 'danger');
echo html_writer::start_tag('div', ['class' => 'qd-card ' . $card_class]);
echo html_writer::tag('div', get_string('global_health', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', $percentage_ok . '%', ['class' => 'qd-card-value']);
echo html_writer::tag('div', get_string('questions_ok', 'local_question_diagnostic'), ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

echo html_writer::end_tag('div'); // fin dashboard

// ======================================================================
// STATISTIQUES PAR TYPE DE QUESTION
// ======================================================================

if (!empty($globalstats->by_qtype)) {
    echo html_writer::tag('h3', '📈 ' . get_string('brokenlinks_by_type', 'local_question_diagnostic'));
    
    echo html_writer::start_tag('div', ['class' => 'qd-stats-by-type']);
    foreach ($globalstats->by_qtype as $qtype => $count) {
        echo html_writer::start_tag('div', ['class' => 'qd-stat-item']);
        echo html_writer::tag('span', ucfirst($qtype), ['class' => 'qd-stat-label']);
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
    'placeholder' => get_string('filter_search_placeholder', 'local_question_diagnostic'),
    'class' => 'form-control'
]);
echo html_writer::end_tag('div');

// Filtre par type
echo html_writer::start_tag('div', ['class' => 'qd-filter-group']);
echo html_writer::tag('label', get_string('question_type', 'local_question_diagnostic'), ['for' => 'filter-qtype']);
echo html_writer::start_tag('select', ['id' => 'filter-qtype', 'class' => 'form-control']);
echo html_writer::tag('option', get_string('all', 'local_question_diagnostic'), ['value' => 'all']);
foreach ($globalstats->by_qtype as $qtype => $count) {
    echo html_writer::tag('option', ucfirst($qtype) . " ($count)", ['value' => $qtype]);
}
echo html_writer::end_tag('select');
echo html_writer::end_tag('div');

echo html_writer::end_tag('div'); // fin qd-filters-row
echo html_writer::end_tag('div'); // fin qd-filters

// ======================================================================
// TABLEAU DES QUESTIONS AVEC LIENS CASSÉS
// ======================================================================

echo html_writer::tag('h3', '🔗 ' . get_string('brokenlinks_table', 'local_question_diagnostic'), ['style' => 'margin-top: 30px;']);

// Utiliser le cache par défaut (limite de 1000 questions pour éviter timeout)
$broken_questions = question_link_checker::get_questions_with_broken_links(true, 1000);

// 🆕 v1.9.55 : Récupérer les infos de versions (statut caché + nombre de versions) pour toutes les questions
$question_ids = array_map(function($item) { return $item->question->id; }, $broken_questions);
$version_info_map = [];
$usage_map = []; // 🆕 v1.9.57
if (!empty($question_ids)) {
    $version_info_map = question_analyzer::get_questions_version_info_batch($question_ids);
    // 🆕 v1.9.57 : Charger aussi les infos d'usage pour distinguer cachée vs supprimée
    $usage_map = question_analyzer::get_questions_usage_by_ids($question_ids);
}

// Afficher un avertissement si limite atteinte
if (count($broken_questions) >= 1000) {
    echo html_writer::start_div('alert alert-info', ['style' => 'margin-bottom: 20px;']);
    echo '<strong>ℹ️ Note :</strong> L\'analyse est limitée aux 1000 questions les plus récentes pour des raisons de performance. ';
    echo 'Les résultats sont mis en cache pendant 1 heure. Utilisez le bouton "Rafraîchir" pour forcer une nouvelle analyse.';
    echo html_writer::end_div();
}

if (empty($broken_questions)) {
    echo html_writer::tag('div', '✅ ' . get_string('no_broken_links', 'local_question_diagnostic'), [
        'class' => 'alert alert-success',
        'style' => 'padding: 20px; text-align: center; font-size: 16px;'
    ]);
} else {
    echo html_writer::start_tag('div', ['class' => 'qd-table-wrapper']);
    echo html_writer::start_tag('table', ['class' => 'qd-table']);
    
    // En-tête du tableau
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('question_id', 'local_question_diagnostic'), ['class' => 'sortable', 'data-column' => 'id']);
    echo html_writer::tag('th', get_string('question_name', 'local_question_diagnostic'), ['class' => 'sortable', 'data-column' => 'name']);
    echo html_writer::tag('th', get_string('question_type', 'local_question_diagnostic'), ['class' => 'sortable', 'data-column' => 'qtype']);
    echo html_writer::tag('th', get_string('question_hidden_status', 'local_question_diagnostic'), ['class' => 'sortable', 'data-column' => 'visibility', 'title' => 'Statut de visibilité de la question']);
    echo html_writer::tag('th', get_string('question_version_count', 'local_question_diagnostic'), ['class' => 'sortable', 'data-column' => 'versions', 'title' => get_string('question_version_count_tooltip', 'local_question_diagnostic')]);
    echo html_writer::tag('th', get_string('question_category', 'local_question_diagnostic'), ['class' => 'sortable', 'data-column' => 'category']);
    echo html_writer::tag('th', get_string('broken_links_count', 'local_question_diagnostic'), ['class' => 'sortable', 'data-column' => 'brokencount']);
    echo html_writer::tag('th', get_string('broken_links_details', 'local_question_diagnostic'));
    echo html_writer::tag('th', get_string('actions', 'local_question_diagnostic'));
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    
    // Corps du tableau
    echo html_writer::start_tag('tbody');
    
    foreach ($broken_questions as $item) {
        $question = $item->question;
        $category = $item->category;
        $broken_links = $item->broken_links;
        
        // 🆕 v1.9.55 : Récupérer les infos de versions pour cette question
        $version_info = isset($version_info_map[$question->id]) ? $version_info_map[$question->id] : (object)[
            'is_hidden' => false,
            'version_count' => 0,
            'status' => 'unknown'
        ];
        
        // Attributs pour le filtrage et le tri
        $row_attrs = [
            'data-id' => $question->id,
            'data-qtype' => $question->qtype,
            'data-name' => format_string($question->name),
            'data-visibility' => $version_info->is_hidden ? '0' : '1',
            'data-versions' => $version_info->version_count,
            'data-category' => $category ? format_string($category->name) : 'Inconnue',
            'data-brokencount' => $item->broken_count
        ];
        
        echo html_writer::start_tag('tr', $row_attrs);
        
        // ID
        echo html_writer::tag('td', $question->id);
        
        // Nom
        echo html_writer::start_tag('td');
        echo html_writer::tag('strong', format_string($question->name));
        echo html_writer::end_tag('td');
        
        // Type
        echo html_writer::tag('td', html_writer::tag('span', 
            ucfirst($question->qtype), 
            ['class' => 'badge badge-info']
        ));
        
        // 🆕 v1.9.57 : Colonne Visibilité (visible/cachée/supprimée)
        $visibility_status = question_analyzer::get_question_visibility_status($question->id, $version_info, $usage_map);
        
        switch ($visibility_status) {
            case 'deleted':
                $visibility_text = get_string('question_deleted', 'local_question_diagnostic');
                $visibility_style = 'color: #d9534f; font-weight: bold;';
                $visibility_tooltip = get_string('question_deleted_tooltip', 'local_question_diagnostic');
                break;
            case 'hidden':
                $visibility_text = get_string('question_hidden', 'local_question_diagnostic');
                $visibility_style = 'color: #f0ad4e; font-weight: bold;';
                $visibility_tooltip = get_string('question_hidden_tooltip', 'local_question_diagnostic');
                break;
            default: // 'visible'
                $visibility_text = get_string('question_visible', 'local_question_diagnostic');
                $visibility_style = 'color: #5cb85c;';
                $visibility_tooltip = 'Question visible et active';
                break;
        }
        
        echo html_writer::tag('td', $visibility_text, [
            'style' => $visibility_style . ' text-align: center;',
            'title' => $visibility_tooltip . ' (Statut: ' . $version_info->status . ')'
        ]);
        
        // 🆕 v1.9.55 : Colonne Nombre de versions
        $version_count_style = $version_info->version_count > 1 
            ? 'font-weight: bold; color: #0f6cbf;' 
            : 'color: #666;';
        echo html_writer::tag('td', $version_info->version_count, [
            'style' => $version_count_style . ' text-align: center;',
            'title' => $version_info->version_count > 1 
                ? 'Cette question a ' . $version_info->version_count . ' versions' 
                : 'Version unique'
        ]);
        
        // Catégorie
        echo html_writer::start_tag('td');
        if ($category) {
            $cat_url = new moodle_url('/question/edit.php', [
                'courseid' => 0,
                'cat' => $category->id . ',' . $category->contextid
            ]);
            $cat_name = format_string($category->name) . ' <span style="color: #666; font-size: 11px;">(ID: ' . $category->id . ')</span>';
            echo html_writer::link($cat_url, $cat_name, ['target' => '_blank']);
        } else {
            echo '-';
        }
        echo html_writer::end_tag('td');
        
        // Nombre de liens cassés
        echo html_writer::tag('td', html_writer::tag('span', 
            $item->broken_count, 
            ['class' => 'qd-badge qd-badge-empty', 'style' => 'font-size: 14px;']
        ));
        
        // Détails des liens cassés
        echo html_writer::start_tag('td');
        echo html_writer::start_tag('div', ['class' => 'qd-broken-links-details']);
        foreach ($broken_links as $link) {
            // Extraire le nom de fichier de l'URL
            $filename = '';
            $url_clean = $link->url;
            // Retirer querystring si présent
            $qpos = strpos($url_clean, '?');
            if ($qpos !== false) {
                $url_clean = substr($url_clean, 0, $qpos);
            }
            // Extraire le filename
            $parsed = @parse_url($url_clean);
            if (is_array($parsed) && !empty($parsed['path'])) {
                $path = str_replace('\\', '/', $parsed['path']);
                $filename = basename($path);
            } else {
                // Fallback : prendre le dernier segment après /
                $path = str_replace('\\', '/', $url_clean);
                $filename = basename($path);
            }
            
            echo html_writer::start_tag('div', ['class' => 'qd-broken-link-item', 'style' => 'margin-bottom: 8px; padding: 8px; background: #f9f9f9; border-left: 3px solid #d9534f;']);
            echo html_writer::tag('div', '📍 Champ: ' . htmlspecialchars($link->field), ['style' => 'font-size: 11px; color: #666; font-weight: bold;']);
            if (!empty($filename) && $filename !== '.' && $filename !== 'pluginfile.php') {
                echo html_writer::tag('div', '📄 Fichier: <strong>' . htmlspecialchars($filename) . '</strong>', ['style' => 'font-size: 11px; color: #0f6cbf; font-weight: bold; margin: 4px 0;']);
            }
            echo html_writer::tag('div', '🔗 URL: ' . htmlspecialchars(substr($link->url, 0, 80)) . (strlen($link->url) > 80 ? '...' : ''), ['style' => 'font-size: 10px; color: #666; word-break: break-all;']);
            echo html_writer::tag('div', '⚠️ ' . htmlspecialchars($link->reason), ['style' => 'font-size: 11px; color: #d9534f; margin-top: 4px;']);
            echo html_writer::end_tag('div');
        }
        echo html_writer::end_tag('div');
        echo html_writer::end_tag('td');
        
        // Actions
        echo html_writer::start_tag('td');
        echo html_writer::start_tag('div', ['class' => 'qd-actions', 'style' => 'display: flex; flex-direction: column; gap: 5px;']);
        
        // Bouton voir dans la banque
        $questionbank_url = question_link_checker::get_question_bank_url($question, $category);
        if ($questionbank_url) {
            echo html_writer::link(
                $questionbank_url, 
                '👁️ Voir',
                [
                    'class' => 'qd-btn qd-btn-view',
                    'title' => 'Voir dans la banque de questions',
                    'target' => '_blank'
                ]
            );
        }
        
        // Bouton tentative de réparation
        echo html_writer::tag('button', '🔧 ' . get_string('repair_options', 'local_question_diagnostic'), [
            'class' => 'qd-btn qd-btn-merge repair-btn',
            'data-questionid' => $question->id,
            'data-name' => format_string($question->name),
            'data-links' => json_encode($broken_links),
            'onclick' => 'showRepairModal(' . $question->id . ', ' . json_encode(format_string($question->name)) . ', ' . json_encode($broken_links) . ')'
        ]);
        
        echo html_writer::end_tag('div');
        echo html_writer::end_tag('td');
        
        echo html_writer::end_tag('tr');
    }
    
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_tag('div'); // fin qd-table-wrapper
}

// ======================================================================
// MODAL DE RÉPARATION
// ======================================================================

echo html_writer::start_tag('div', ['class' => 'qd-modal', 'id' => 'repair-modal']);
echo html_writer::start_tag('div', ['class' => 'qd-modal-content']);

echo html_writer::start_tag('div', ['class' => 'qd-modal-header']);
echo html_writer::tag('h3', '🔧 ' . get_string('repair_modal_title', 'local_question_diagnostic'), ['class' => 'qd-modal-title']);
echo html_writer::tag('button', '&times;', ['class' => 'qd-modal-close', 'onclick' => 'closeRepairModal()']);
echo html_writer::end_tag('div');

// ⚠️ Important: l'ID est utilisé par le JS (showRepairModal). Il doit être dans le tableau d'attributs.
echo html_writer::start_tag('div', ['class' => 'qd-modal-body', 'id' => 'repair-modal-body']);
echo html_writer::tag('p', get_string('loading_questions', 'local_question_diagnostic'));
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'qd-modal-footer']);
echo html_writer::tag('button', get_string('close', 'moodle'), ['class' => 'btn btn-secondary', 'onclick' => 'closeRepairModal()']);
echo html_writer::end_tag('div');

echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

// ======================================================================
// JavaScript pour le modal de réparation
// ======================================================================

echo html_writer::start_tag('script');
?>
function showRepairModal(questionId, questionName, brokenLinks) {
    const modal = document.getElementById('repair-modal');
    const modalBody = document.getElementById('repair-modal-body');

    // Sécurité : si le DOM attendu n'est pas présent, ne pas planter silencieusement.
    if (!modal || !modalBody) {
        console.error('Repair modal DOM not found', {modal, modalBody});
        alert('Erreur: impossible d’ouvrir le panneau d’options (modal introuvable dans la page).');
        return;
    }
    
    // Construire le contenu du modal
    let content = '<h4>Question: ' + questionName + ' (ID: ' + questionId + ')</h4>';
    content += '<p>Liens cassés détectés dans cette question:</p>';
    
    content += '<div style="max-height: 400px; overflow-y: auto;">';
    brokenLinks.forEach(function(link, index) {
        content += '<div style="margin: 15px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; background: #f9f9f9;">';
        content += '<strong>Lien #' + (index + 1) + '</strong><br>';
        content += '<div style="margin: 10px 0;">';
        content += '<strong>Champ:</strong> ' + link.field + '<br>';
        content += '<strong>URL:</strong> <code style="word-break: break-all; background: #fff; padding: 2px 5px; border-radius: 3px;">' + link.url + '</code><br>';
        content += '<strong>Raison:</strong> <span style="color: #d9534f;">' + link.reason + '</span>';
        content += '</div>';
        
        // Options (la "réparation automatique" n'est pas implémentée, mais certaines corrections sont possibles)
        content += '<div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd;">';
        content += '<strong>Options:</strong><br>';
        content += '<div style="margin-top: 8px;">';
        
        // Bouton supprimer la référence / désactiver bgimage
        const removeUrl = new URL(window.location.href);
        removeUrl.searchParams.set('action', 'remove');
        removeUrl.searchParams.set('questionid', questionId);
        removeUrl.searchParams.set('field', link.field);
        removeUrl.searchParams.set('url', link.url);
        removeUrl.searchParams.set('sesskey', M.cfg.sesskey);

        // Bouton "réparer via doublon strict" (ouvre une page de confirmation avec suggestions).
        const repairUrl = new URL(window.location.href);
        repairUrl.searchParams.set('action', 'repair');
        repairUrl.searchParams.set('questionid', questionId);
        repairUrl.searchParams.set('field', link.field);
        repairUrl.searchParams.set('url', link.url);
        repairUrl.searchParams.set('sesskey', M.cfg.sesskey);

        const isBgImage = (String(link.field || '').toLowerCase().indexOf('bgimage') !== -1);
        const actionLabel = isBgImage
            ? '🧹 Désactiver l’image de fond'
            : '🗑️ <?php echo addslashes(get_string('remove_reference', 'local_question_diagnostic')); ?>';
        const confirmText = isBgImage
            ? 'Êtes-vous sûr de vouloir désactiver l’image de fond pour ce type de question ?'
            : '<?php echo addslashes(get_string('remove_reference_confirm', 'local_question_diagnostic')); ?>';
        const hint = isBgImage
            ? '(Met le champ bgimage à 0 : la question s’affichera sans image de fond)'
            : '(<?php echo addslashes(get_string('remove_reference_desc', 'local_question_diagnostic')); ?>)';

        content += '<a href="' + removeUrl.toString() + '" class="btn btn-danger btn-sm" style="margin-right: 5px;" onclick="return confirm(\'' + confirmText + '\')">' + actionLabel + '</a>';

        // Ne proposer la réparation via doublon strict que si l'URL est un pluginfile.
        // Sinon, attempt_repair() refusera (ex: bgimage missing, messages, etc.).
        const isPluginfile = (String(link.url || '').toLowerCase().indexOf('pluginfile.php') !== -1);
        if (isPluginfile) {
            // Bouton recherche par nom similaire
            const searchSimilarUrl = new URL(window.location.href);
            searchSimilarUrl.searchParams.set('action', 'search_similar');
            searchSimilarUrl.searchParams.set('questionid', questionId);
            searchSimilarUrl.searchParams.set('field', link.field);
            searchSimilarUrl.searchParams.set('url', link.url);
            searchSimilarUrl.searchParams.set('sesskey', M.cfg.sesskey);
            
            content += '<a href="' + searchSimilarUrl.toString() + '" class="btn btn-outline-info btn-sm" style="margin-right: 5px;" target="_blank">🔍 Rechercher par nom similaire</a>';
            content += '<a href="' + repairUrl.toString() + '" class="btn btn-outline-primary btn-sm" style="margin-right: 5px;" target="_blank">🔎 Trouver via doublon strict</a>';
        } else {
            // Afficher quand même l'option (désactivée) pour éviter l'impression qu'elle a "disparu".
            content += '<span class="btn btn-outline-secondary btn-sm disabled" style="margin-right: 5px;" title="Disponible uniquement pour les liens de type pluginfile.php">🔍 Rechercher par nom similaire</span>';
            content += '<span class="btn btn-outline-secondary btn-sm disabled" style="margin-right: 5px;" title="Disponible uniquement pour les liens de type pluginfile.php">🔎 Trouver via doublon strict</span>';
        }
        content += '<span style="font-size: 12px; color: #666;">' + hint + '</span>';
        content += '</div>';
        content += '</div>';
        
        content += '</div>';
    });
    content += '</div>';
    
    content += '<div style="margin-top: 20px; padding: 15px; background: #d9edf7; border: 1px solid #bce8f1; border-radius: 5px;">';
    content += '<strong>💡 Recommandation:</strong> ';
    content += '<?php echo addslashes(get_string('repair_recommendation', 'local_question_diagnostic')); ?>';
    content += '</div>';
    
    modalBody.innerHTML = content;
    modal.style.display = 'block';
}

function closeRepairModal() {
    const modal = document.getElementById('repair-modal');
    modal.style.display = 'none';
}

// Fermer le modal en cliquant en dehors
window.onclick = function(event) {
    const modal = document.getElementById('repair-modal');
    if (event.target === modal) {
        closeRepairModal();
    }
}

// Filtres
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('filter-search');
    const qtypeFilter = document.getElementById('filter-qtype');
    
    function applyFilters() {
        const searchValue = searchInput ? searchInput.value.toLowerCase() : '';
        const qtypeValue = qtypeFilter ? qtypeFilter.value : 'all';
        
        const rows = document.querySelectorAll('.qd-table tbody tr');
        let visibleCount = 0;
        
        rows.forEach(function(row) {
            const name = (row.getAttribute('data-name') || '').toLowerCase();
            const category = (row.getAttribute('data-category') || '').toLowerCase();
            const qtype = row.getAttribute('data-qtype') || '';
            
            const matchesSearch = searchValue === '' || name.includes(searchValue) || category.includes(searchValue);
            const matchesQtype = qtypeValue === 'all' || qtype === qtypeValue;
            
            if (matchesSearch && matchesQtype) {
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
    if (qtypeFilter) {
        qtypeFilter.addEventListener('change', applyFilters);
    }
});
<?php
echo html_writer::end_tag('script');

// ======================================================================
// Pied de page Moodle standard
// ======================================================================
echo $OUTPUT->footer();

