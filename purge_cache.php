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
    die('Accès réservé aux administrateurs du site');
}

// Vérifier si confirmation
$confirm = optional_param('confirm', 0, PARAM_INT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/question_diagnostic/purge_cache.php'));
$PAGE->set_title('Purge des caches');

echo $OUTPUT->header();

echo html_writer::tag('h1', '🔧 Purge des Caches Moodle');

if (!$confirm) {
    // Afficher la page de confirmation
    echo html_writer::start_div('alert alert-info', ['style' => 'margin: 20px 0;']);
    echo html_writer::tag('h3', 'Pourquoi purger les caches ?');
    echo html_writer::tag('p', 'La purge des caches est nécessaire après :');
    echo html_writer::start_tag('ul');
    echo html_writer::tag('li', 'Modification du fichier <code>lib.php</code>');
    echo html_writer::tag('li', 'Mise à jour du plugin');
    echo html_writer::tag('li', 'Ajout de nouvelles fonctions');
    echo html_writer::tag('li', 'Correction de bugs');
    echo html_writer::end_tag('ul');
    echo html_writer::end_div();
    
    echo html_writer::start_div('alert alert-warning', ['style' => 'margin: 20px 0;']);
    echo html_writer::tag('h3', '⚠️ Avertissement');
    echo html_writer::tag('p', 'La purge des caches va :');
    echo html_writer::start_tag('ul');
    echo html_writer::tag('li', '✅ Forcer le rechargement de tous les fichiers PHP');
    echo html_writer::tag('li', '✅ Corriger l\'erreur "Call to undefined function"');
    echo html_writer::tag('li', '⚠️ Ralentir temporairement le site (le temps de reconstruire les caches)');
    echo html_writer::tag('li', '⚠️ Déconnecter éventuellement certains utilisateurs');
    echo html_writer::end_tag('ul');
    echo html_writer::tag('p', '<strong>Recommandation :</strong> Effectuez cette opération en dehors des heures de pointe si possible.');
    echo html_writer::end_div();
    
    // Boutons
    echo html_writer::start_div('', ['style' => 'margin: 30px 0; display: flex; gap: 20px;']);
    
    $confirm_url = new moodle_url('/local/question_diagnostic/purge_cache.php', [
        'confirm' => 1,
        'sesskey' => sesskey()
    ]);
    echo html_writer::link($confirm_url, '🔧 Purger les Caches Maintenant', [
        'class' => 'btn btn-primary btn-lg'
    ]);
    
    echo html_writer::link(
        new moodle_url('/local/question_diagnostic/index.php'),
        '← Annuler',
        ['class' => 'btn btn-secondary btn-lg']
    );
    
    echo html_writer::end_div();
    
} else {
    // Vérifier le sesskey
    require_sesskey();
    
    // Purger les caches
    echo html_writer::start_div('alert alert-info', ['style' => 'margin: 20px 0;']);
    echo html_writer::tag('h3', '🔄 Purge en cours...');
    echo html_writer::tag('p', 'Veuillez patienter, cette opération peut prendre quelques secondes.');
    echo html_writer::end_div();
    
    // Forcer l'affichage immédiat
    flush();
    
    try {
        // Purger tous les caches
        purge_all_caches();
        
        // Succès
        echo html_writer::start_div('alert alert-success', ['style' => 'margin: 20px 0;']);
        echo html_writer::tag('h3', '✅ Caches purgés avec succès !');
        echo html_writer::tag('p', 'Tous les caches de Moodle ont été purgés.');
        echo html_writer::end_div();
        
        // Instructions post-purge
        echo html_writer::start_div('alert alert-info', ['style' => 'margin: 20px 0;']);
        echo html_writer::tag('h3', '📋 Prochaines étapes');
        echo html_writer::start_tag('ol');
        echo html_writer::tag('li', '<strong>Videz le cache de votre navigateur</strong> (Ctrl+Shift+Delete ou Cmd+Shift+Delete)');
        echo html_writer::tag('li', '<strong>Fermez et rouvrez votre navigateur</strong> (optionnel mais recommandé)');
        echo html_writer::tag('li', '<strong>Testez la fonctionnalité</strong> : Essayez de supprimer une question');
        echo html_writer::end_tag('ol');
        echo html_writer::end_div();
        
        // Liens de test
        echo html_writer::start_div('', ['style' => 'margin: 30px 0;']);
        echo html_writer::tag('h4', '🧪 Tester maintenant');
        echo html_writer::start_div('', ['style' => 'display: flex; gap: 15px; flex-wrap: wrap;']);
        
        echo html_writer::link(
            new moodle_url('/local/question_diagnostic/test_function.php'),
            '🔍 Tester les Fonctions',
            ['class' => 'btn btn-info', 'target' => '_blank']
        );
        
        echo html_writer::link(
            new moodle_url('/local/question_diagnostic/questions_cleanup.php'),
            '📊 Gestion des Questions',
            ['class' => 'btn btn-primary']
        );
        
        echo html_writer::link(
            new moodle_url('/local/question_diagnostic/index.php'),
            '← Menu Principal',
            ['class' => 'btn btn-secondary']
        );
        
        echo html_writer::end_div();
        echo html_writer::end_div();
        
    } catch (Exception $e) {
        // Erreur
        echo html_writer::start_div('alert alert-danger', ['style' => 'margin: 20px 0;']);
        echo html_writer::tag('h3', '❌ Erreur lors de la purge');
        echo html_writer::tag('p', 'Une erreur s\'est produite : ' . htmlspecialchars($e->getMessage()));
        echo html_writer::tag('p', '<strong>Solution alternative :</strong> Allez dans Administration du site → Développement → Purger les caches');
        echo html_writer::end_div();
    }
}

echo $OUTPUT->footer();

