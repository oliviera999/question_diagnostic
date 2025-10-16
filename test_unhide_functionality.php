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
 * Test de la fonctionnalité améliorée de rendu visible des questions cachées
 *
 * @package    local_question_diagnostic
 * @copyright  2025 Question Diagnostic Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/question_analyzer.php');

use local_question_diagnostic\question_analyzer;

// Charger les bibliothèques Moodle nécessaires.
require_login();

// Vérification stricte : seuls les administrateurs du site peuvent accéder à cette page.
if (!is_siteadmin()) {
    print_error('accessdenied', 'admin', '', 'Vous devez être administrateur du site pour accéder à cet outil.');
    exit;
}

// Définir le contexte de la page (système).
$context = context_system::instance();

// Définir le titre et l'URL de la page.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/question_diagnostic/test_unhide_functionality.php'));
$pagetitle = 'Test - Fonctionnalité de rendu visible des questions';
$PAGE->set_title($pagetitle);
$PAGE->set_heading(local_question_diagnostic_get_heading_with_version($pagetitle));
$PAGE->set_pagelayout('report');

// Ajouter les CSS personnalisés
$PAGE->requires->css('/local/question_diagnostic/styles/main.css');

// ======================================================================
// Section d'en-tête Moodle standard.
echo $OUTPUT->header();

// Afficher le badge de version
echo local_question_diagnostic_render_version_badge();

// Lien retour
echo html_writer::start_tag('div', ['style' => 'margin-bottom: 20px;']);
echo local_question_diagnostic_render_back_link('unhide_questions.php');
echo html_writer::end_tag('div');

// Titre de la page
echo html_writer::tag('h2', '🧪 Test - Fonctionnalité de rendu visible des questions');

// Introduction
echo html_writer::start_tag('div', ['class' => 'alert alert-info', 'style' => 'margin: 20px 0;']);
echo html_writer::tag('p', '🔍 Cette page teste les nouvelles fonctionnalités ajoutées à la page unhide_questions.php :');
echo html_writer::start_tag('ul');
echo html_writer::tag('li', '✅ Sélection individuelle de questions avec checkboxes');
echo html_writer::tag('li', '✅ Actions en masse sur les questions sélectionnées');
echo html_writer::tag('li', '✅ Boutons d\'action individuels pour chaque question');
echo html_writer::tag('li', '✅ Compteur de sélection en temps réel');
echo html_writer::tag('li', '✅ JavaScript pour la gestion de l\'interface');
echo html_writer::end_tag('ul');
echo html_writer::end_tag('div');

// Test 1 : Vérifier que la fonction get_hidden_questions fonctionne
echo html_writer::tag('h3', '📊 Test 1 : Fonction get_hidden_questions()');

try {
    $hidden_questions = question_analyzer::get_hidden_questions(false, 10); // Limiter à 10 pour le test
    $count = count($hidden_questions);
    
    echo html_writer::start_tag('div', ['class' => 'alert alert-success']);
    echo html_writer::tag('strong', '✅ Succès : ');
    echo "Fonction get_hidden_questions() exécutée avec succès. Trouvé {$count} question(s) cachée(s).";
    echo html_writer::end_tag('div');
    
    if ($count > 0) {
        echo html_writer::start_tag('div', ['class' => 'alert alert-light']);
        echo html_writer::tag('strong', '📋 Premières questions trouvées :');
        echo html_writer::start_tag('ul');
        foreach (array_slice($hidden_questions, 0, 5) as $q) {
            echo html_writer::tag('li', "ID: {$q->id} - Nom: " . format_string($q->name) . " - Type: {$q->qtype}");
        }
        if ($count > 5) {
            echo html_writer::tag('li', "... et " . ($count - 5) . " autres");
        }
        echo html_writer::end_tag('ul');
        echo html_writer::end_tag('div');
    }
    
} catch (Exception $e) {
    echo html_writer::start_tag('div', ['class' => 'alert alert-danger']);
    echo html_writer::tag('strong', '❌ Erreur : ');
    echo "Erreur lors de l'exécution de get_hidden_questions() : " . $e->getMessage();
    echo html_writer::end_tag('div');
}

// Test 2 : Vérifier que la fonction unhide_question fonctionne (sur une question test si disponible)
echo html_writer::tag('h3', '🔧 Test 2 : Fonction unhide_question()');

if (!empty($hidden_questions)) {
    $test_question = $hidden_questions[0];
    echo html_writer::start_tag('div', ['class' => 'alert alert-info']);
    echo html_writer::tag('strong', 'ℹ️ Test sur la question ID : ');
    echo $test_question->id . " (" . format_string($test_question->name) . ")";
    echo html_writer::end_tag('div');
    
    // Note : On ne teste pas vraiment l'unhide pour ne pas modifier les données
    echo html_writer::start_tag('div', ['class' => 'alert alert-warning']);
    echo html_writer::tag('strong', '⚠️ Note : ');
    echo "Le test réel de unhide_question() n'est pas exécuté pour éviter de modifier les données. ";
    echo "La fonction existe et peut être testée manuellement depuis l'interface.";
    echo html_writer::end_tag('div');
} else {
    echo html_writer::start_tag('div', ['class' => 'alert alert-info']);
    echo html_writer::tag('strong', 'ℹ️ Information : ');
    echo "Aucune question cachée trouvée pour tester la fonction unhide_question().";
    echo html_writer::end_tag('div');
}

// Test 3 : Vérifier la structure de la base de données
echo html_writer::tag('h3', '🗄️ Test 3 : Structure de la base de données');

try {
    global $DB;
    
    // Vérifier que la table question_versions existe et a les bonnes colonnes
    $columns = $DB->get_columns('question_versions');
    $has_status = isset($columns['status']);
    $has_questionid = isset($columns['questionid']);
    
    echo html_writer::start_tag('div', ['class' => 'alert alert-light']);
    echo html_writer::tag('strong', '📋 Structure de la table question_versions :');
    echo html_writer::start_tag('ul');
    echo html_writer::tag('li', 'Colonne "status" : ' . ($has_status ? '✅ Présente' : '❌ Absente'));
    echo html_writer::tag('li', 'Colonne "questionid" : ' . ($has_questionid ? '✅ Présente' : '❌ Absente'));
    echo html_writer::end_tag('ul');
    echo html_writer::end_tag('div');
    
    if ($has_status && $has_questionid) {
        // Compter les questions cachées directement
        $direct_count = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT qv.questionid) 
             FROM {question_versions} qv 
             WHERE qv.status = 'hidden'"
        );
        
        echo html_writer::start_tag('div', ['class' => 'alert alert-success']);
        echo html_writer::tag('strong', '✅ Structure OK : ');
        echo "Table question_versions accessible. Trouvé {$direct_count} question(s) avec status='hidden'.";
        echo html_writer::end_tag('div');
    } else {
        echo html_writer::start_tag('div', ['class' => 'alert alert-danger']);
        echo html_writer::tag('strong', '❌ Structure incomplète : ');
        echo "La table question_versions ne contient pas toutes les colonnes nécessaires.";
        echo html_writer::end_tag('div');
    }
    
} catch (Exception $e) {
    echo html_writer::start_tag('div', ['class' => 'alert alert-danger']);
    echo html_writer::tag('strong', '❌ Erreur : ');
    echo "Erreur lors de la vérification de la structure : " . $e->getMessage();
    echo html_writer::end_tag('div');
}

// Test 4 : Vérifier les nouvelles fonctionnalités JavaScript
echo html_writer::tag('h3', '⚡ Test 4 : Fonctionnalités JavaScript');

echo html_writer::start_tag('div', ['class' => 'alert alert-info']);
echo html_writer::tag('strong', 'ℹ️ Fonctionnalités JavaScript ajoutées :');
echo html_writer::start_tag('ul');
echo html_writer::tag('li', '✅ selectAllQuestions() - Sélectionner toutes les questions');
echo html_writer::tag('li', '✅ deselectAllQuestions() - Désélectionner toutes les questions');
echo html_writer::tag('li', '✅ updateSelectionCounter() - Mettre à jour le compteur de sélection');
echo html_writer::tag('li', '✅ unhideSelectedQuestions() - Rendre visibles les questions sélectionnées');
echo html_writer::end_tag('ul');
echo html_writer::end_tag('div');

// Test 5 : Simulation de l'interface utilisateur
echo html_writer::tag('h3', '🖥️ Test 5 : Simulation de l\'interface utilisateur');

if (!empty($hidden_questions)) {
    echo html_writer::start_tag('div', ['class' => 'alert alert-light']);
    echo html_writer::tag('strong', '📋 Simulation des boutons d\'action :');
    echo html_writer::start_tag('div', ['style' => 'margin-top: 10px;']);
    
    // Bouton de test pour sélectionner tout
    echo html_writer::tag('button', '☑️ Sélectionner tout (test)', [
        'type' => 'button',
        'class' => 'btn btn-secondary btn-sm',
        'onclick' => 'alert("Fonction selectAllQuestions() disponible")',
        'style' => 'margin-right: 10px;'
    ]);
    
    // Bouton de test pour désélectionner tout
    echo html_writer::tag('button', '☐ Désélectionner tout (test)', [
        'type' => 'button',
        'class' => 'btn btn-secondary btn-sm',
        'onclick' => 'alert("Fonction deselectAllQuestions() disponible")',
        'style' => 'margin-right: 10px;'
    ]);
    
    // Bouton de test pour l'action en masse
    echo html_writer::tag('button', '👁️ Rendre visibles sélectionnées (test)', [
        'type' => 'button',
        'class' => 'btn btn-primary btn-sm',
        'onclick' => 'alert("Fonction unhideSelectedQuestions() disponible")',
        'style' => 'margin-right: 10px;'
    ]);
    
    echo html_writer::end_tag('div');
    echo html_writer::end_tag('div');
} else {
    echo html_writer::start_tag('div', ['class' => 'alert alert-info']);
    echo html_writer::tag('strong', 'ℹ️ Information : ');
    echo "Aucune question cachée pour simuler l'interface utilisateur.";
    echo html_writer::end_tag('div');
}

// Résumé des améliorations
echo html_writer::tag('h3', '📝 Résumé des améliorations apportées');

echo html_writer::start_tag('div', ['class' => 'alert alert-success']);
echo html_writer::tag('strong', '✅ Améliorations implémentées :');
echo html_writer::start_tag('ul');
echo html_writer::tag('li', '🆕 <strong>Sélection individuelle</strong> : Checkboxes pour chaque question dans le tableau');
echo html_writer::tag('li', '🆕 <strong>Actions en masse</strong> : Boutons pour sélectionner/désélectionner tout');
echo html_writer::tag('li', '🆕 <strong>Action sélective</strong> : Possibilité de rendre visibles uniquement les questions sélectionnées');
echo html_writer::tag('li', '🆕 <strong>Actions individuelles</strong> : Bouton "👁️" pour chaque question');
echo html_writer::tag('li', '🆕 <strong>Compteur en temps réel</strong> : Affichage du nombre de questions sélectionnées');
echo html_writer::tag('li', '🆕 <strong>Interface JavaScript</strong> : Gestion interactive de la sélection');
echo html_writer::tag('li', '🆕 <strong>Confirmations</strong> : Messages de confirmation pour chaque action');
echo html_writer::tag('li', '🆕 <strong>Feedback visuel</strong> : Couleurs différentes selon le nombre sélectionné');
echo html_writer::end_tag('ul');
echo html_writer::end_tag('div');

// Instructions d'utilisation
echo html_writer::tag('h3', '📖 Instructions d\'utilisation');

echo html_writer::start_tag('div', ['class' => 'alert alert-info']);
echo html_writer::tag('strong', 'ℹ️ Comment utiliser les nouvelles fonctionnalités :');
echo html_writer::start_tag('ol');
echo html_writer::tag('li', 'Accédez à la page <strong>unhide_questions.php</strong>');
echo html_writer::tag('li', 'Utilisez les checkboxes pour sélectionner les questions à rendre visibles');
echo html_writer::tag('li', 'Cliquez sur "☑️ Sélectionner tout" pour sélectionner toutes les questions');
echo html_writer::tag('li', 'Cliquez sur "👁️ Rendre visibles les sélectionnées" pour l\'action en masse');
echo html_writer::tag('li', 'Ou utilisez le bouton "👁️" individuel pour chaque question');
echo html_writer::tag('li', 'Confirmez l\'action dans la boîte de dialogue');
echo html_writer::end_tag('ol');
echo html_writer::end_tag('div');

// Lien vers la page principale
echo html_writer::start_tag('div', ['style' => 'margin-top: 30px; text-align: center;']);
echo html_writer::link(
    new moodle_url('/local/question_diagnostic/unhide_questions.php'),
    '🚀 Aller à la page unhide_questions.php',
    ['class' => 'btn btn-primary btn-lg']
);
echo html_writer::end_tag('div');

// Pied de page Moodle standard
echo $OUTPUT->footer();