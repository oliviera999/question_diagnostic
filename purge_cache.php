<?php
/**
 * Script de purge du cache Moodle
 * 
 * Accès : http://votresite.moodle/local/question_diagnostic/purge_cache.php
 * 
 * Ce script purge les caches de Moodle pour forcer le rechargement de lib.php
 * et corriger l'erreur "Call to undefined function"
 */

require_once(__DIR__ . '/../../config.php');

require_login();

if (!is_siteadmin()) {
    print_error('accessdenied', 'admin');
}

// URL de retour (page précédemment affichée).
// Compat : le paramètre historique `return_url` est encore accepté.
$returnurlraw = optional_param('returnurl', '', PARAM_LOCALURL);
if (empty($returnurlraw)) {
    $returnurlraw = optional_param('return_url', '', PARAM_LOCALURL);
}
$returnurl = !empty($returnurlraw)
    ? new moodle_url($returnurlraw)
    : new moodle_url('/local/question_diagnostic/index.php');

// Vérifier si confirmation
$confirm = optional_param('confirm', 0, PARAM_INT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/question_diagnostic/purge_cache.php', [
    'returnurl' => $returnurl->out(false),
]));
$PAGE->set_title(get_string('purge_cache_title', 'local_question_diagnostic'));

echo $OUTPUT->header();

echo html_writer::tag('h1', '🔧 ' . get_string('purge_cache_heading', 'local_question_diagnostic'));

if (!$confirm) {
    // Afficher la page de confirmation
    echo html_writer::start_div('alert alert-info', ['style' => 'margin: 20px 0;']);
    echo html_writer::tag('h3', get_string('purge_cache_why_title', 'local_question_diagnostic'));
    echo html_writer::tag('p', get_string('purge_cache_why_desc', 'local_question_diagnostic'));
    echo html_writer::start_tag('ul');
    echo html_writer::tag('li', get_string('purge_cache_reason_lib', 'local_question_diagnostic'));
    echo html_writer::tag('li', get_string('purge_cache_reason_update', 'local_question_diagnostic'));
    echo html_writer::tag('li', get_string('purge_cache_reason_functions', 'local_question_diagnostic'));
    echo html_writer::tag('li', get_string('purge_cache_reason_bugs', 'local_question_diagnostic'));
    echo html_writer::end_tag('ul');
    echo html_writer::end_div();
    
    echo html_writer::start_div('alert alert-warning', ['style' => 'margin: 20px 0;']);
    echo html_writer::tag('h3', get_string('purge_cache_warning_title', 'local_question_diagnostic'));
    echo html_writer::tag('p', get_string('purge_cache_warning_desc', 'local_question_diagnostic'));
    echo html_writer::start_tag('ul');
    echo html_writer::tag('li', get_string('purge_cache_effect_reload', 'local_question_diagnostic'));
    echo html_writer::tag('li', get_string('purge_cache_effect_fix', 'local_question_diagnostic'));
    echo html_writer::tag('li', get_string('purge_cache_effect_slow', 'local_question_diagnostic'));
    echo html_writer::tag('li', get_string('purge_cache_effect_logout', 'local_question_diagnostic'));
    echo html_writer::end_tag('ul');
    echo html_writer::tag('p', get_string('purge_cache_recommendation', 'local_question_diagnostic'));
    echo html_writer::end_div();
    
    // Boutons
    echo html_writer::start_div('', ['style' => 'margin: 30px 0; display: flex; gap: 20px;']);
    
    $confirm_url = new moodle_url('/local/question_diagnostic/purge_cache.php', [
        'confirm' => 1,
        'sesskey' => sesskey(),
        'returnurl' => $returnurl->out(false),
    ]);
    echo html_writer::link($confirm_url, '🔧 ' . get_string('purge_cache_confirm', 'local_question_diagnostic'), [
        'class' => 'btn btn-primary btn-lg'
    ]);
    
    echo html_writer::link(
        $returnurl,
        '← ' . get_string('purge_cache_back_previous', 'local_question_diagnostic'),
        ['class' => 'btn btn-secondary btn-lg']
    );

    echo html_writer::link(
        new moodle_url('/local/question_diagnostic/index.php'),
        get_string('backtomenu', 'local_question_diagnostic'),
        ['class' => 'btn btn-secondary btn-lg']
    );
    
    echo html_writer::end_div();
    
} else {
    // Vérifier le sesskey
    require_sesskey();
    
    // Purger les caches
    echo html_writer::start_div('alert alert-info', ['style' => 'margin: 20px 0;']);
    echo html_writer::tag('h3', get_string('purge_cache_running_title', 'local_question_diagnostic'));
    echo html_writer::tag('p', get_string('purge_cache_running_desc', 'local_question_diagnostic'));
    echo html_writer::end_div();
    
    // Forcer l'affichage immédiat
    flush();
    
    try {
        // Purger tous les caches
        purge_all_caches();
        
        // Succès
        echo html_writer::start_div('alert alert-success', ['style' => 'margin: 20px 0;']);
        echo html_writer::tag('h3', get_string('purge_cache_success_title', 'local_question_diagnostic'));
        echo html_writer::tag('p', get_string('purge_cache_success_desc', 'local_question_diagnostic'));
        echo html_writer::end_div();
        
        // Instructions post-purge
        echo html_writer::start_div('alert alert-info', ['style' => 'margin: 20px 0;']);
        echo html_writer::tag('h3', get_string('purge_cache_next_steps_title', 'local_question_diagnostic'));
        echo html_writer::start_tag('ol');
        echo html_writer::tag('li', get_string('purge_cache_step_browser_cache', 'local_question_diagnostic'));
        echo html_writer::tag('li', get_string('purge_cache_step_restart_browser', 'local_question_diagnostic'));
        echo html_writer::tag('li', get_string('purge_cache_step_test', 'local_question_diagnostic'));
        echo html_writer::end_tag('ol');
        echo html_writer::end_div();
        
        // Liens de test
        echo html_writer::start_div('', ['style' => 'margin: 30px 0;']);
        echo html_writer::tag('h4', get_string('purge_cache_test_now', 'local_question_diagnostic'));
        echo html_writer::start_div('', ['style' => 'display: flex; gap: 15px; flex-wrap: wrap;']);
        
        echo html_writer::link(
            new moodle_url('/local/question_diagnostic/test_function.php'),
            get_string('purge_cache_test_functions', 'local_question_diagnostic'),
            ['class' => 'btn btn-info', 'target' => '_blank']
        );
        
        echo html_writer::link(
            new moodle_url('/local/question_diagnostic/questions_cleanup.php'),
            get_string('purge_cache_questions_tool', 'local_question_diagnostic'),
            ['class' => 'btn btn-primary']
        );
        
        echo html_writer::link(
            $returnurl,
            '← ' . get_string('purge_cache_back_previous', 'local_question_diagnostic'),
            ['class' => 'btn btn-secondary']
        );

        echo html_writer::link(
            new moodle_url('/local/question_diagnostic/index.php'),
            get_string('backtomenu', 'local_question_diagnostic'),
            ['class' => 'btn btn-secondary']
        );
        
        echo html_writer::end_div();
        echo html_writer::end_div();
        
    } catch (Exception $e) {
        // Erreur
        echo html_writer::start_div('alert alert-danger', ['style' => 'margin: 20px 0;']);
        echo html_writer::tag('h3', get_string('purge_cache_error_title', 'local_question_diagnostic'));
        echo html_writer::tag('p', get_string('purge_cache_error_desc', 'local_question_diagnostic', s($e->getMessage())));
        echo html_writer::tag('p', get_string('purge_cache_error_alternative', 'local_question_diagnostic'));
        echo html_writer::end_div();

        echo html_writer::start_div('', ['style' => 'margin: 30px 0; display: flex; gap: 15px; flex-wrap: wrap;']);
        echo html_writer::link($returnurl, '← ' . get_string('purge_cache_back_previous', 'local_question_diagnostic'), ['class' => 'btn btn-secondary']);
        echo html_writer::link(new moodle_url('/local/question_diagnostic/index.php'), get_string('backtomenu', 'local_question_diagnostic'), ['class' => 'btn btn-secondary']);
        echo html_writer::end_div();
    }
}

echo $OUTPUT->footer();

