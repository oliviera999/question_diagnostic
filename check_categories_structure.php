<?php
// This file is part of Moodle - http://moodle.org/
//
// Diagnostic : Vérifier la structure de la table question_categories

require_once(__DIR__ . '/../../config.php');
require_login();

if (!is_siteadmin()) {
    print_error('accessdenied', 'admin');
}

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/question_diagnostic/check_categories_structure.php'));
$PAGE->set_title('Structure table question_categories');
$PAGE->set_heading('Diagnostic Structure BDD');

echo $OUTPUT->header();

echo html_writer::tag('h2', '🔍 Structure de la table question_categories');

// Récupérer la structure de la table
$columns = $DB->get_columns('question_categories');

echo html_writer::start_tag('table', ['class' => 'table table-bordered', 'style' => 'width: 100%; max-width: 800px;']);
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
echo html_writer::tag('th', 'Nom de la colonne');
echo html_writer::tag('th', 'Type');
echo html_writer::tag('th', 'Null');
echo html_writer::tag('th', 'Default');
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');

echo html_writer::start_tag('tbody');
foreach ($columns as $column_name => $column_info) {
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', $column_name, ['style' => 'font-weight: bold;']);
    echo html_writer::tag('td', $column_info->meta_type);
    echo html_writer::tag('td', $column_info->not_null ? 'NOT NULL' : 'NULL');
    echo html_writer::tag('td', $column_info->has_default ? $column_info->default_value : '-');
    echo html_writer::end_tag('tr');
}
echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');

// Vérifier si un champ 'hidden' ou 'status' existe
$has_hidden_field = isset($columns['hidden']) || isset($columns['status']);

if ($has_hidden_field) {
    echo html_writer::start_tag('div', ['class' => 'alert alert-success', 'style' => 'margin-top: 20px;']);
    echo html_writer::tag('strong', '✅ Champ trouvé : ');
    if (isset($columns['hidden'])) {
        echo 'La table question_categories possède un champ <code>hidden</code>.';
    }
    if (isset($columns['status'])) {
        echo 'La table question_categories possède un champ <code>status</code>.';
    }
    echo html_writer::end_tag('div');
    
    // Compter les catégories cachées
    try {
        if (isset($columns['hidden'])) {
            $hidden_count = $DB->count_records('question_categories', ['hidden' => 1]);
            echo html_writer::start_tag('div', ['class' => 'alert alert-info']);
            echo '📊 Nombre de catégories cachées (hidden = 1) : <strong>' . $hidden_count . '</strong>';
            echo html_writer::end_tag('div');
        }
    } catch (Exception $e) {
        echo html_writer::start_tag('div', ['class' => 'alert alert-danger']);
        echo '⚠️ Erreur : ' . $e->getMessage();
        echo html_writer::end_tag('div');
    }
} else {
    echo html_writer::start_tag('div', ['class' => 'alert alert-warning', 'style' => 'margin-top: 20px;']);
    echo html_writer::tag('strong', '⚠️ Conclusion : ');
    echo 'La table <code>question_categories</code> <strong>N\'A PAS</strong> de champ <code>hidden</code> ou <code>status</code>. ';
    echo '<br><br>Les catégories de questions <strong>ne peuvent pas être cachées</strong> dans Moodle 4.5. ';
    echo 'Seules les <strong>questions</strong> peuvent être cachées (via question_versions.status).';
    echo html_writer::end_tag('div');
}

// Lien retour
echo html_writer::start_tag('div', ['style' => 'margin-top: 30px;']);
echo html_writer::link(
    new moodle_url('/local/question_diagnostic/index.php'),
    '← Retour au menu',
    ['class' => 'btn btn-secondary']
);
echo html_writer::end_tag('div');

echo $OUTPUT->footer();

