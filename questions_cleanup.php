<?php
// ======================================================================
// Moodle Question Cleanup Tool - Statistiques et nettoyage des questions
// ======================================================================

// Inclure la configuration de Moodle.
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/question_analyzer.php');

use local_question_diagnostic\question_analyzer;

// Charger les bibliothèques Moodle nécessaires.
require_login();

// Vérification stricte : seuls les administrateurs du site peuvent accéder à cette page.
if (!is_siteadmin()) {
    print_error('accesinterdit', 'admin', '', 'Vous devez être administrateur du site pour accéder à cet outil.');
    exit;
}

// Définir le contexte de la page (système).
$context = context_system::instance();

// Définir le titre et l'URL de la page.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/question_diagnostic/questions_cleanup.php'));
$pagetitle = get_string('questions_cleanup_heading', 'local_question_diagnostic');
$PAGE->set_title(get_string('questions_cleanup', 'local_question_diagnostic'));
$PAGE->set_heading(local_question_diagnostic_get_heading_with_version($pagetitle));
$PAGE->set_pagelayout('report');

// Ajouter les CSS et JavaScript personnalisés
$PAGE->requires->css('/local/question_diagnostic/styles/main.css');
$PAGE->requires->js('/local/question_diagnostic/scripts/main.js', true);
$PAGE->requires->js('/local/question_diagnostic/scripts/questions.js', true);

// ======================================================================
// Section d'en-tête Moodle standard.
echo $OUTPUT->header();

// Lien retour vers le menu principal et bouton de purge de cache
echo html_writer::start_tag('div', ['style' => 'margin-bottom: 20px; display: flex; gap: 10px; align-items: center;']);
echo html_writer::link(
    new moodle_url('/local/question_diagnostic/index.php'),
    '← ' . get_string('backtomenu', 'local_question_diagnostic'),
    ['class' => 'btn btn-secondary']
);

// Traiter la purge de cache si demandée
$purgecache = optional_param('purgecache', 0, PARAM_INT);
if ($purgecache && confirm_sesskey()) {
    question_analyzer::purge_all_caches();
    redirect($PAGE->url, '✅ Cache purgé avec succès.', null, \core\output\notification::NOTIFY_SUCCESS);
}

// Bouton de purge de cache
$purgecache_url = new moodle_url($PAGE->url, ['purgecache' => 1, 'sesskey' => sesskey()]);
echo html_writer::link(
    $purgecache_url,
    '🔄 Purger le cache',
    [
        'class' => 'btn btn-warning',
        'title' => 'Vider le cache pour forcer le recalcul des statistiques'
    ]
);

// 🆕 v1.7.0 : Bouton de test aléatoire pour détecter les doublons
$randomtest_url = new moodle_url($PAGE->url, ['randomtest' => 1, 'sesskey' => sesskey()]);
echo html_writer::link(
    $randomtest_url,
    '🎲 Test Aléatoire Doublons',
    [
        'class' => 'btn btn-info',
        'title' => 'Sélectionner une question au hasard et afficher tous ses doublons stricts'
    ]
);

// 🆕 v1.8.0 : Bouton test aléatoire doublons UTILISÉS
$randomtest_used_url = new moodle_url($PAGE->url, ['randomtest_used' => 1, 'sesskey' => sesskey()]);
echo html_writer::link(
    $randomtest_used_url,
    '🎲 Test Doublons Utilisés',
    [
        'class' => 'btn btn-success',
        'title' => 'Tester un groupe de doublons dont au moins 1 version est utilisée'
    ]
);

echo html_writer::end_tag('div');

// 🆕 v1.7.0 : MODE TEST ALÉATOIRE - Afficher le résultat si demandé
$randomtest = optional_param('randomtest', 0, PARAM_INT);
if ($randomtest && confirm_sesskey()) {
    echo html_writer::tag('h2', '🎲 Test de Détection de Doublons - Question Aléatoire');
    
    // Sélectionner une question aléatoire
    $random_question = $DB->get_record_sql("SELECT * FROM {question} ORDER BY RAND() LIMIT 1");
    
    if (!$random_question) {
        echo html_writer::start_tag('div', ['class' => 'alert alert-danger']);
        echo 'Aucune question trouvée dans la base de données.';
        echo html_writer::end_tag('div');
        echo $OUTPUT->footer();
        exit;
    }
    
    // Trouver tous les doublons stricts
    $duplicates = question_analyzer::find_exact_duplicates($random_question);
    
    echo html_writer::start_tag('div', ['class' => 'alert alert-info', 'style' => 'margin: 20px 0;']);
    echo html_writer::tag('h3', '🎯 Question Sélectionnée', ['style' => 'margin-top: 0;']);
    echo html_writer::tag('p', '<strong>ID :</strong> ' . $random_question->id);
    echo html_writer::tag('p', '<strong>Nom :</strong> ' . format_string($random_question->name));
    echo html_writer::tag('p', '<strong>Type :</strong> ' . $random_question->qtype);
    echo html_writer::tag('p', '<strong>Texte :</strong> ' . substr(strip_tags($random_question->questiontext), 0, 200) . '...');
    echo html_writer::end_tag('div');
    
    if (count($duplicates) > 0) {
        echo html_writer::start_tag('div', ['class' => 'alert alert-warning']);
        echo html_writer::tag('h3', '⚠️ ' . count($duplicates) . ' Doublon(s) Strict(s) Trouvé(s)');
        echo 'Questions avec exactement le même nom, type et texte que la question ' . $random_question->id;
        echo html_writer::end_tag('div');
        
        // Tableau détaillé des doublons
        echo html_writer::tag('h3', '📋 Détails des Doublons');
        echo html_writer::start_tag('table', ['class' => 'qd-table', 'style' => 'width: 100%;']);
        
        // En-tête
        echo html_writer::start_tag('thead');
        echo html_writer::start_tag('tr');
        echo html_writer::tag('th', 'ID');
        echo html_writer::tag('th', 'Nom');
        echo html_writer::tag('th', 'Type');
        echo html_writer::tag('th', 'Catégorie');
        echo html_writer::tag('th', 'Contexte');
        echo html_writer::tag('th', 'Quiz');
        echo html_writer::tag('th', 'Tentatives');
        echo html_writer::tag('th', 'Créée le');
        echo html_writer::tag('th', 'Actions');
        echo html_writer::end_tag('tr');
        echo html_writer::end_tag('thead');
        
        echo html_writer::start_tag('tbody');
        
        // Ajouter la question originale + tous les doublons
        $all_questions = array_merge([$random_question], $duplicates);
        
        foreach ($all_questions as $q) {
            $stats = question_analyzer::get_question_stats($q);
            
            echo html_writer::start_tag('tr', ['style' => $q->id == $random_question->id ? 'background: #d4edda; font-weight: bold;' : '']);
            echo html_writer::tag('td', $q->id . ($q->id == $random_question->id ? ' 🎯' : ''));
            echo html_writer::tag('td', format_string($q->name));
            echo html_writer::tag('td', $q->qtype);
            echo html_writer::tag('td', isset($stats->category_name) ? $stats->category_name : 'N/A');
            echo html_writer::tag('td', isset($stats->context_name) ? $stats->context_name : 'N/A');
            echo html_writer::tag('td', isset($stats->quiz_count) ? $stats->quiz_count : 0);
            echo html_writer::tag('td', isset($stats->attempt_count) ? $stats->attempt_count : 0);
            echo html_writer::tag('td', userdate($q->timecreated, '%d/%m/%Y %H:%M'));
            
            // Actions
            echo html_writer::start_tag('td');
            $view_url = question_analyzer::get_question_bank_url($q);
            if ($view_url) {
                echo html_writer::link($view_url, '👁️', ['class' => 'btn btn-sm btn-primary', 'target' => '_blank', 'title' => 'Voir']);
            }
            echo html_writer::end_tag('td');
            
            echo html_writer::end_tag('tr');
        }
        
        echo html_writer::end_tag('tbody');
        echo html_writer::end_tag('table');
        
        // Résumé
        echo html_writer::start_tag('div', ['class' => 'alert alert-info', 'style' => 'margin-top: 20px;']);
        echo html_writer::tag('h4', '📊 Résumé du Test');
        echo html_writer::tag('p', '<strong>Total de doublons stricts :</strong> ' . count($duplicates));
        echo html_writer::tag('p', '<strong>Total de versions :</strong> ' . count($all_questions) . ' (1 originale + ' . count($duplicates) . ' doublon(s))');
        
        $used_count = 0;
        $unused_count = 0;
        foreach ($all_questions as $q) {
            $s = question_analyzer::get_question_stats($q);
            if ((isset($s->quiz_count) && $s->quiz_count > 0) || (isset($s->attempt_count) && $s->attempt_count > 0)) {
                $used_count++;
            } else {
                $unused_count++;
            }
        }
        echo html_writer::tag('p', '<strong>Versions utilisées :</strong> ' . $used_count);
        echo html_writer::tag('p', '<strong>Versions inutilisées (supprimables) :</strong> ' . $unused_count);
        echo html_writer::end_tag('div');
        
    } else {
        echo html_writer::start_tag('div', ['class' => 'alert alert-success']);
        echo html_writer::tag('h3', '✅ Aucun Doublon Trouvé');
        echo 'La question sélectionnée (ID: ' . $random_question->id . ') est unique dans votre base de données.';
        echo html_writer::end_tag('div');
    }
    
    echo html_writer::start_tag('div', ['style' => 'margin-top: 30px; text-align: center;']);
    $randomtest_url_again = new moodle_url($PAGE->url, ['randomtest' => 1, 'sesskey' => sesskey()]);
    echo html_writer::link(
        $randomtest_url_again,
        '🔄 Tester une autre question aléatoire',
        ['class' => 'btn btn-primary btn-lg']
    );
    echo ' ';
    echo html_writer::link(
        new moodle_url('/local/question_diagnostic/questions_cleanup.php', ['loadstats' => 1]),
        '← Retour à la liste',
        ['class' => 'btn btn-secondary']
    );
    echo html_writer::end_tag('div');
    
    echo $OUTPUT->footer();
    exit;
}

// 🆕 v1.8.0 : MODE TEST ALÉATOIRE DOUBLONS UTILISÉS
$randomtest_used = optional_param('randomtest_used', 0, PARAM_INT);
if ($randomtest_used && confirm_sesskey()) {
    echo html_writer::tag('h2', '🎲 Test Doublons Utilisés - Question Aléatoire');
    
    // 🆕 v1.9.2 : APPROCHE SIMPLIFIÉE - Sélectionner directement un groupe de doublons utilisés
    // Au lieu de chercher parmi des candidats, on identifie directement les groupes de doublons
    
    // Étape 1 : Trouver les signatures de questions qui ont des doublons ET sont utilisées
    $sql = "SELECT CONCAT(q.name, '|', q.qtype) as signature,
                   MIN(q.id) as sample_id,
                   COUNT(DISTINCT q.id) as question_count
            FROM {question} q
            GROUP BY q.name, q.qtype
            HAVING COUNT(DISTINCT q.id) > 1
            ORDER BY RAND()
            LIMIT 5";
    
    $duplicate_groups = $DB->get_records_sql($sql);
    
    if (empty($duplicate_groups)) {
        $found = false;
        $random_question = null;
    } else {
        // Étape 2 : Pour chaque groupe, vérifier si au moins 1 version est utilisée
        $found = false;
        $random_question = null;
        
        foreach ($duplicate_groups as $group) {
            // Récupérer la question exemple
            $sample = $DB->get_record('question', ['id' => $group->sample_id]);
            if (!$sample) {
                continue;
            }
            
            // Trouver toutes les questions de ce groupe
            $all_in_group = $DB->get_records('question', [
                'name' => $sample->name,
                'qtype' => $sample->qtype
            ]);
            
            if (count($all_in_group) <= 1) {
                continue; // Pas vraiment un groupe
            }
            
            // Vérifier l'usage du groupe entier
            $group_ids = array_keys($all_in_group);
            $usage_map = question_analyzer::get_questions_usage_by_ids($group_ids);
            
            // Vérifier si au moins une version est utilisée
            $has_used = false;
            
            // 🔍 v1.9.10 DEBUG : Afficher les données pour comprendre le problème
            $debug_usage = [];
            
            foreach ($group_ids as $qid) {
                // 🐛 v1.9.9 FIX : !empty() sur un tableau retourne toujours true, même avec des 0 !
                // ✅ Vérifier explicitement le flag is_used ou les compteurs
                
                // 🔍 DEBUG : Collecter les infos
                if (isset($usage_map[$qid])) {
                    $debug_usage[$qid] = [
                        'is_used' => $usage_map[$qid]['is_used'],
                        'quiz_count' => $usage_map[$qid]['quiz_count'],
                        'attempt_count' => $usage_map[$qid]['attempt_count']
                    ];
                }
                
                if (isset($usage_map[$qid]) && 
                    ($usage_map[$qid]['is_used'] === true || 
                     $usage_map[$qid]['quiz_count'] > 0 || 
                     $usage_map[$qid]['attempt_count'] > 0)) {
                    $has_used = true;
                    break;
                }
            }
            
            // 🔍 v1.9.10 DEBUG : Si ce groupe est marqué comme utilisé, afficher pourquoi
            if ($has_used && count($debug_usage) > 0) {
                debugging('GROUPE MARQUÉ COMME UTILISÉ - Détails : ' . json_encode($debug_usage), DEBUG_DEVELOPER);
            }
            
            if ($has_used) {
                $random_question = $sample;
                $found = true;
                break;
            }
        }
    }
    
    if (!$found || !$random_question) {
        echo html_writer::start_tag('div', ['class' => 'alert alert-warning']);
        echo html_writer::tag('h3', '⚠️ Aucun groupe de doublons utilisés trouvé');
        echo 'Après 5 tentatives, aucun groupe de doublons avec au moins 1 version utilisée n\'a été trouvé. ';
        echo 'Cela peut signifier que vos doublons ne sont pas utilisés, ou qu\'ils sont rares.';
        echo html_writer::end_tag('div');
        
        echo html_writer::start_tag('div', ['style' => 'margin-top: 30px;']);
        echo html_writer::link($randomtest_used_url, '🔄 Réessayer', ['class' => 'btn btn-primary']);
        echo ' ';
        echo html_writer::link(new moodle_url('/local/question_diagnostic/questions_cleanup.php', ['loadstats' => 1]), '← Retour', ['class' => 'btn btn-secondary']);
        echo html_writer::end_tag('div');
        
        echo $OUTPUT->footer();
        exit;
    }
    
    // Trouver tous les doublons de cette question (même nom + même type)
    $all_questions = $DB->get_records('question', [
        'name' => $random_question->name,
        'qtype' => $random_question->qtype
    ]);
    
    echo html_writer::start_tag('div', ['class' => 'alert alert-success', 'style' => 'margin: 20px 0;']);
    echo html_writer::tag('h3', '🎯 Groupe de Doublons Utilisés Trouvé !', ['style' => 'margin-top: 0;']);
    echo html_writer::tag('p', '<strong>Question sélectionnée ID :</strong> ' . $random_question->id);
    echo html_writer::tag('p', '<strong>Nom :</strong> ' . format_string($random_question->name));
    echo html_writer::tag('p', '<strong>Type :</strong> ' . $random_question->qtype);
    $duplicate_count = count($all_questions) - 1; // -1 pour exclure la question elle-même
    echo html_writer::tag('p', '<strong>Nombre de versions totales :</strong> ' . count($all_questions) . ' (1 originale + ' . $duplicate_count . ' doublon(s))');
    echo html_writer::end_tag('div');
    
    // Tableau détaillé
    echo html_writer::tag('h3', '📋 Détails de Toutes les Versions');
    echo html_writer::start_tag('table', ['class' => 'qd-table', 'style' => 'width: 100%;']);
    
    // En-tête
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'ID');
    echo html_writer::tag('th', 'Nom');
    echo html_writer::tag('th', 'Type');
    echo html_writer::tag('th', 'Catégorie');
    echo html_writer::tag('th', 'Cours');
    echo html_writer::tag('th', '📊 Dans Quiz', ['title' => 'Nombre de quiz utilisant cette question']);
    echo html_writer::tag('th', '🔢 Utilisations', ['title' => 'Nombre total d\'utilisations (dans différents quiz)']);
    echo html_writer::tag('th', 'Statut');
    echo html_writer::tag('th', 'Créée le');
    echo html_writer::tag('th', 'Actions');
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    
    echo html_writer::start_tag('tbody');
    
    // 🆕 v1.9.1 : OPTIMISATION - Charger les stats de toutes les questions du groupe en batch
    $group_question_ids = array_map(function($q) { return $q->id; }, $all_questions);
    $group_usage_map = question_analyzer::get_questions_usage_by_ids($group_question_ids);
    
    // 🆕 v1.9.6 : Vérifier la supprimabilité de toutes les questions du groupe en batch
    $group_question_ids_for_delete = array_map(function($q) { return $q->id; }, $all_questions);
    $deletability_map = question_analyzer::can_delete_questions_batch($group_question_ids_for_delete);
    
    foreach ($all_questions as $q) {
        $stats = question_analyzer::get_question_stats($q);
        
        // 🆕 v1.9.7 : FIX CRITIQUE - Utiliser les bonnes clés du map
        $quiz_count = 0;      // Nombre de quiz différents POUR CETTE QUESTION
        $total_usages = 0;    // Nombre total d'utilisations POUR CETTE QUESTION
        
        // Vérifier l'usage spécifique de CETTE question (pas du groupe)
        if (isset($group_usage_map[$q->id]) && is_array($group_usage_map[$q->id])) {
            // ✅ CORRECTION : Utiliser les clés correctes de la structure retournée
            $quiz_count = isset($group_usage_map[$q->id]['quiz_count']) ? $group_usage_map[$q->id]['quiz_count'] : 0;
            
            // Compter le nombre total d'utilisations = nombre de quiz contenant cette question
            // (dans quiz_list, chaque entrée = 1 quiz utilisant cette question)
            $total_usages = isset($group_usage_map[$q->id]['quiz_list']) ? count($group_usage_map[$q->id]['quiz_list']) : 0;
        }
        
        $is_used = $quiz_count > 0;
        
        // Mettre à jour les stats avec les vraies valeurs pour CETTE question
        $stats->quiz_count = $quiz_count;
        $stats->total_usages = $total_usages;
        
        $row_style = '';
        if ($q->id == $random_question->id) {
            $row_style = 'background: #d4edda; font-weight: bold;';
        } else if ($is_used) {
            $row_style = 'background: #fff3cd;'; // Jaune pour les utilisées
        }
        
        echo html_writer::start_tag('tr', ['style' => $row_style]);
        echo html_writer::tag('td', $q->id . ($q->id == $random_question->id ? ' 🎯' : ''));
        echo html_writer::tag('td', format_string($q->name));
        echo html_writer::tag('td', $q->qtype);
        echo html_writer::tag('td', isset($stats->category_name) ? $stats->category_name : 'N/A');
        echo html_writer::tag('td', isset($stats->course_name) ? '📚 ' . $stats->course_name : '-');
        
        // Colonne "Dans Quiz" - Nombre de quiz différents
        $quiz_style = $quiz_count > 0 ? 'font-weight: bold; color: #28a745;' : 'color: #999;';
        echo html_writer::tag('td', $quiz_count, [
            'style' => $quiz_style,
            'title' => $quiz_count > 0 ? "Cette question est utilisée dans $quiz_count quiz" : "Non utilisée"
        ]);
        
        // Colonne "Utilisations" - Nombre total d'utilisations
        $usage_style = $total_usages > 0 ? 'font-weight: bold; color: #0f6cbf;' : 'color: #999;';
        echo html_writer::tag('td', $total_usages, [
            'style' => $usage_style,
            'title' => $total_usages > 0 ? "Total de $total_usages utilisation(s)" : "Aucune utilisation"
        ]);
        
        echo html_writer::tag('td', $is_used ? '✅ Utilisée' : '⚠️ Inutilisée');
        echo html_writer::tag('td', userdate($q->timecreated, '%d/%m/%Y %H:%M'));
        
        // Actions
        echo html_writer::start_tag('td', ['style' => 'white-space: nowrap;']);
        
        // Bouton Voir
        $view_url = question_analyzer::get_question_bank_url($q);
        if ($view_url) {
            echo html_writer::link($view_url, '👁️', [
                'class' => 'btn btn-sm btn-primary', 
                'target' => '_blank', 
                'title' => 'Voir',
                'style' => 'margin-right: 5px;'
            ]);
        }
        
        // 🆕 v1.9.6 : Bouton Supprimer avec protection
        $can_delete_check = isset($deletability_map[$q->id]) ? $deletability_map[$q->id] : null;
        if ($can_delete_check && $can_delete_check->can_delete) {
            // Question supprimable
            $delete_url = new moodle_url('/local/question_diagnostic/actions/delete_question.php', [
                'id' => $q->id,
                'sesskey' => sesskey()
            ]);
            echo html_writer::link($delete_url, '🗑️', [
                'class' => 'btn btn-sm btn-danger',
                'title' => 'Supprimer ce doublon inutilisé',
                'style' => 'background: #d9534f; color: white; padding: 3px 8px; margin-right: 5px;'
            ]);
        } else {
            // Question protégée
            $reason = $can_delete_check ? $can_delete_check->reason : 'Vérification impossible';
            echo html_writer::tag('span', '🔒', [
                'class' => 'btn btn-sm btn-secondary',
                'title' => 'PROTÉGÉE : ' . $reason,
                'style' => 'background: #6c757d; color: white; padding: 3px 8px; cursor: not-allowed; margin-right: 5px;'
            ]);
        }
        
        echo html_writer::end_tag('td');
        
        echo html_writer::end_tag('tr');
    }
    
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    
    // Résumé détaillé
    echo html_writer::start_tag('div', ['class' => 'alert alert-info', 'style' => 'margin-top: 20px;']);
    echo html_writer::tag('h4', '📊 Analyse du Groupe');
    echo html_writer::tag('p', '<strong>Total de versions :</strong> ' . count($all_questions));
    
    $used_count = 0;
    $unused_count = 0;
    $total_quiz_count = 0;         // Nombre total de quiz différents
    $total_usages = 0;             // Nombre total d'utilisations
    
    // 🆕 v1.9.7 : FIX CRITIQUE - Calculer correctement avec les bonnes clés
    foreach ($all_questions as $q) {
        $quiz_count = 0;
        $question_usages = 0;
        
        if (isset($group_usage_map[$q->id]) && is_array($group_usage_map[$q->id])) {
            // ✅ CORRECTION : Utiliser les clés correctes
            $quiz_count = isset($group_usage_map[$q->id]['quiz_count']) ? $group_usage_map[$q->id]['quiz_count'] : 0;
            $question_usages = isset($group_usage_map[$q->id]['quiz_list']) ? count($group_usage_map[$q->id]['quiz_list']) : 0;
        }
        
        if ($quiz_count > 0) {
            $used_count++;
        } else {
            $unused_count++;
        }
        
        $total_quiz_count += $quiz_count;
        $total_usages += $question_usages;
    }
    
    echo html_writer::tag('p', '<strong>Versions utilisées :</strong> ' . $used_count . ' (présentes dans au moins 1 quiz)');
    echo html_writer::tag('p', '<strong>Versions inutilisées (supprimables) :</strong> ' . $unused_count);
    echo html_writer::tag('p', '<strong>Total quiz utilisant ces versions :</strong> ' . $total_quiz_count . ' quiz');
    echo html_writer::tag('p', '<strong>Total utilisations :</strong> ' . $total_usages . ' utilisation(s) dans des quiz');
    
    echo html_writer::start_tag('div', ['style' => 'margin-top: 20px; padding: 15px; background: #e7f3ff; border-left: 4px solid #0f6cbf;']);
    echo html_writer::tag('strong', '💡 Recommandation : ');
    if ($unused_count > 0) {
        echo 'Ce groupe contient <strong>' . $unused_count . ' version(s) inutilisée(s)</strong> qui pourrai(en)t être supprimée(s) pour nettoyer la base. ';
        echo 'Les versions utilisées (' . $used_count . ') doivent être conservées.';
    } else {
        echo 'Toutes les versions de cette question sont utilisées. Aucune suppression recommandée.';
    }
    echo html_writer::end_tag('div');
    
    echo html_writer::end_tag('div');
    
    echo html_writer::start_tag('div', ['style' => 'margin-top: 30px; text-align: center;']);
    echo html_writer::link(
        $randomtest_used_url,
        '🔄 Tester un autre groupe',
        ['class' => 'btn btn-primary btn-lg']
    );
    echo ' ';
    echo html_writer::link(
        new moodle_url('/local/question_diagnostic/questions_cleanup.php', ['loadstats' => 1]),
        '← Retour à la liste',
        ['class' => 'btn btn-secondary']
    );
    echo html_writer::end_tag('div');
    
    echo $OUTPUT->footer();
    exit;
}

// ======================================================================
// STATISTIQUES GLOBALES (Dashboard)
// ======================================================================

echo html_writer::tag('h2', '📊 ' . get_string('questions_stats', 'local_question_diagnostic'));

// Afficher un message d'avertissement sur le temps de calcul (masqué si loadstats=0)
if (optional_param('loadstats', 0, PARAM_INT) == 1) {
    echo html_writer::start_tag('div', ['class' => 'alert alert-info', 'style' => 'margin-bottom: 20px;']);
    echo html_writer::tag('strong', '⚠️ Information : ');
    echo get_string('loading_stats', 'local_question_diagnostic') . ' ';
    echo html_writer::tag('span', get_string('loading_questions', 'local_question_diagnostic'), ['id' => 'loading-indicator', 'style' => 'font-weight: bold;']);
    echo html_writer::end_tag('div');
}

// 🚨 v1.6.1 : CHARGEMENT MINIMAL - Ne charger que le strict nécessaire
$load_stats = optional_param('loadstats', 0, PARAM_INT);
$load_used_duplicates = optional_param('loadusedduplicates', 0, PARAM_INT);

if (!$load_stats && !$load_used_duplicates) {
    // Affichage minimal ultra-rapide
    echo html_writer::start_tag('div', ['class' => 'alert alert-info', 'style' => 'margin: 30px 0; padding: 40px; text-align: center;']);
    echo html_writer::tag('h2', '📊 Statistiques des Questions', ['style' => 'margin-top: 0;']);
    
    // Comptage ultra-simple
    try {
        global $DB;
        $total_questions = $DB->count_records('question');
        echo html_writer::tag('p', "Votre base contient <strong style='font-size: 24px; color: #0f6cbf;'>" . number_format($total_questions, 0, ',', ' ') . " questions</strong>.", ['style' => 'font-size: 18px; margin: 20px 0;']);
    } catch (Exception $e) {
        echo html_writer::tag('p', "Impossible de compter les questions.", ['style' => 'color: red;']);
    }
    
    echo html_writer::tag('p', '⚡ Pour optimiser les performances sur votre grande base de données,<br>les statistiques détaillées et la liste des questions ne sont pas chargées automatiquement.', ['style' => 'margin: 30px 0; font-size: 14px;']);
    
    // Boutons de chargement
    echo html_writer::start_tag('div', ['style' => 'margin-top: 40px; display: flex; gap: 20px; justify-content: center; flex-wrap: wrap;']);
    
    // Bouton 1 : Charger toutes les questions
    $loadstats_url = new moodle_url('/local/question_diagnostic/questions_cleanup.php', ['loadstats' => 1, 'show' => 50]);
    echo html_writer::start_tag('div', ['style' => 'text-align: center;']);
    echo html_writer::link(
        $loadstats_url,
        '🚀 Charger Toutes les Questions',
        ['class' => 'btn btn-lg btn-success', 'style' => 'font-size: 18px; padding: 20px 30px;']
    );
    echo html_writer::tag('p', '⏱️ ~30 secondes', ['style' => 'margin-top: 10px; font-size: 12px; color: #666;']);
    echo html_writer::end_tag('div');
    
    // Bouton 2 : Charger uniquement doublons utilisés
    $loadused_url = new moodle_url('/local/question_diagnostic/questions_cleanup.php', ['loadusedduplicates' => 1, 'show' => 100]);
    echo html_writer::start_tag('div', ['style' => 'text-align: center;']);
    echo html_writer::link(
        $loadused_url,
        '📋 Charger Doublons Utilisés',
        ['class' => 'btn btn-lg btn-primary', 'style' => 'font-size: 18px; padding: 20px 30px;']
    );
    echo html_writer::tag('p', '⏱️ ~20 secondes (liste ciblée)', ['style' => 'margin-top: 10px; font-size: 12px; color: #666;']);
    echo html_writer::tag('p', 'Questions en doublon avec ≥1 version utilisée', ['style' => 'font-size: 11px; color: #999; font-style: italic;']);
    echo html_writer::end_tag('div');
    
    echo html_writer::end_tag('div');
    
    echo html_writer::tag('p', '💡 <strong>Astuce</strong> : Commencez par "Doublons Utilisés" pour cibler rapidement les questions à nettoyer.', ['style' => 'margin-top: 30px; text-align: center; font-style: italic; color: #666;']);
    echo html_writer::end_tag('div');
    
    echo $OUTPUT->footer();
    exit;
}

// Charger les statistiques avec gestion d'erreurs
try {
    // 🚨 v1.6.1 : Désactiver la détection de doublons dans les stats globales (trop lourd)
    $globalstats = question_analyzer::get_global_stats(true, false);
} catch (Exception $e) {
    echo html_writer::start_tag('div', ['class' => 'alert alert-danger']);
    echo html_writer::tag('strong', '⚠️ Erreur : ');
    echo 'Impossible de charger les statistiques globales. ';
    echo html_writer::tag('p', 'Détails : ' . $e->getMessage(), ['style' => 'margin-top: 10px; font-size: 12px;']);
    echo html_writer::end_tag('div');
    echo $OUTPUT->footer();
    exit;
}

// Masquer l'indicateur de chargement via JavaScript (seulement si l'élément existe)
echo html_writer::start_tag('script');
echo "
var loadingIndicator = document.getElementById('loading-indicator');
if (loadingIndicator) {
    loadingIndicator.style.display = 'none';
}
";
echo html_writer::end_tag('script');

// Message si statistiques simplifiées
if (isset($globalstats->simplified) && $globalstats->simplified) {
    echo html_writer::start_tag('div', ['class' => 'alert alert-warning', 'style' => 'margin-bottom: 20px; border-left: 4px solid #f0ad4e;']);
    echo html_writer::tag('h4', '⚡ Mode Performance Activé', ['style' => 'margin-top: 0;']);
    echo html_writer::tag('p', 'Votre base contient <strong>' . number_format($globalstats->total_questions, 0, ',', ' ') . ' questions</strong>. Pour éviter les timeouts, certaines statistiques sont des <strong>approximations</strong> :', ['style' => 'margin-bottom: 15px;']);
    
    echo html_writer::start_tag('ul', ['style' => 'margin-bottom: 15px;']);
    echo html_writer::tag('li', '✅ <strong>Total questions</strong> et <strong>Répartition par type</strong> : Valeurs exactes');
    echo html_writer::tag('li', '✅ <strong>Questions Utilisées/Inutilisées</strong> : Valeurs exactes (comptage simplifié)');
    echo html_writer::tag('li', '⚠️ <strong>Questions Cachées</strong> : Affiché comme 0 (non calculé)');
    echo html_writer::tag('li', '⚠️ <strong>Doublons</strong> : Non calculés');
    echo html_writer::tag('li', '⚠️ <strong>Liens Cassés</strong> : Non calculés');
    echo html_writer::end_tag('ul');
    
    echo html_writer::tag('p', '💡 <strong>Pour voir les vraies utilisations</strong> : Consultez la colonne "Quiz" et "Tentatives" dans le tableau ci-dessous (données exactes pour les questions affichées).', ['style' => 'font-weight: bold; color: #0f6cbf;']);
    echo html_writer::end_tag('div');
}

echo html_writer::start_tag('div', ['class' => 'qd-dashboard']);

// Carte 1 : Total questions
echo html_writer::start_tag('div', ['class' => 'qd-card']);
echo html_writer::tag('div', get_string('total_questions_stats', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', $globalstats->total_questions, ['class' => 'qd-card-value']);
echo html_writer::tag('div', get_string('in_database', 'local_question_diagnostic'), ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

// Carte 2 : Questions utilisées (valeur exacte maintenant)
$is_simplified = isset($globalstats->simplified) && $globalstats->simplified;
echo html_writer::start_tag('div', ['class' => 'qd-card success']);
echo html_writer::tag('div', get_string('questions_used', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', $globalstats->used_questions, ['class' => 'qd-card-value']);
echo html_writer::tag('div', get_string('in_quizzes_or_attempts', 'local_question_diagnostic'), ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

// Carte 3 : Questions inutilisées (valeur exacte maintenant)
echo html_writer::start_tag('div', ['class' => 'qd-card warning']);
echo html_writer::tag('div', get_string('questions_unused', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', $globalstats->unused_questions, ['class' => 'qd-card-value']);
echo html_writer::tag('div', get_string('never_used', 'local_question_diagnostic'), ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

// Carte 4 : Doublons (approximé en mode simplifié)
$approx_style = $is_simplified ? 'opacity: 0.6; border: 2px dashed #f0ad4e;' : '';
echo html_writer::start_tag('div', ['class' => 'qd-card danger', 'style' => $approx_style]);
echo html_writer::tag('div', ($is_simplified ? '⚠️ ' : '') . get_string('questions_duplicates', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', ($is_simplified ? '~' : '') . $globalstats->duplicate_questions, ['class' => 'qd-card-value']);
echo html_writer::tag('div', $globalstats->total_duplicates . ' ' . get_string('total_duplicates_found', 'local_question_diagnostic') . ($is_simplified ? ' (non calculé)' : ''), ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

// Carte 5 : Questions cachées
echo html_writer::start_tag('div', ['class' => 'qd-card', 'style' => $approx_style]);
echo html_writer::tag('div', ($is_simplified ? '⚠️ ' : '') . get_string('questions_hidden', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', ($is_simplified ? '~' : '') . $globalstats->hidden_questions, ['class' => 'qd-card-value']);
echo html_writer::tag('div', get_string('not_visible', 'local_question_diagnostic') . ($is_simplified ? ' (non calculé)' : ''), ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

// Carte 6 : Liens cassés
if ($globalstats->questions_with_broken_links > 0) {
    echo html_writer::start_tag('div', ['class' => 'qd-card danger']);
} else {
    echo html_writer::start_tag('div', ['class' => 'qd-card success']);
}
echo html_writer::tag('div', get_string('questions_broken_links', 'local_question_diagnostic'), ['class' => 'qd-card-title']);
echo html_writer::tag('div', $globalstats->questions_with_broken_links, ['class' => 'qd-card-value']);
echo html_writer::tag('div', get_string('questions_with_problems', 'local_question_diagnostic'), ['class' => 'qd-card-subtitle']);
echo html_writer::end_tag('div');

echo html_writer::end_tag('div'); // fin dashboard

// ======================================================================
// RÉPARTITION PAR TYPE DE QUESTION
// ======================================================================

if (!empty($globalstats->by_type) && $globalstats->total_questions > 0) {
    echo html_writer::tag('h3', '📈 ' . get_string('distribution_by_type', 'local_question_diagnostic'), ['style' => 'margin-top: 30px;']);
    
    echo html_writer::start_tag('div', ['class' => 'qd-stats-by-type']);
    foreach ($globalstats->by_type as $qtype => $count) {
        $percentage = round(($count / $globalstats->total_questions) * 100, 1);
        echo html_writer::start_tag('div', ['class' => 'qd-stat-item']);
        echo html_writer::tag('span', ucfirst($qtype), ['class' => 'qd-stat-label']);
        echo html_writer::tag('span', $count . ' (' . $percentage . '%)', ['class' => 'qd-stat-value']);
        echo html_writer::end_tag('div');
    }
    echo html_writer::end_tag('div');
}

// ======================================================================
// BARRE D'ACTIONS ET EXPORT
// ======================================================================

echo html_writer::start_tag('div', ['style' => 'margin: 30px 0 20px 0; display: flex; gap: 10px; flex-wrap: wrap;']);

$exporturl = new moodle_url('/local/question_diagnostic/actions/export.php', [
    'type' => 'questions_csv',
    'sesskey' => sesskey()
]);
echo html_writer::link($exporturl, '📥 ' . get_string('export_questions_csv', 'local_question_diagnostic'), ['class' => 'btn btn-success']);

echo html_writer::tag('button', '⚙️ ' . get_string('toggle_columns', 'local_question_diagnostic'), [
    'id' => 'toggle-columns-btn',
    'class' => 'btn btn-info',
    'onclick' => 'toggleColumnsPanel()'
]);

echo html_writer::end_tag('div');

// ======================================================================
// PANNEAU DE GESTION DES COLONNES
// ======================================================================

echo html_writer::start_tag('div', ['id' => 'columns-panel', 'class' => 'qd-columns-panel', 'style' => 'display: none;']);
echo html_writer::tag('h4', get_string('columns_to_display', 'local_question_diagnostic'), ['style' => 'margin-top: 0;']);

$columns = [
    'id' => 'ID',
    'name' => 'Nom',
    'type' => 'Type',
    'category' => 'Catégorie',
    'course' => 'Cours',
    'module' => 'Module',
    'context' => 'Contexte',
    'creator' => 'Créateur',
    'created' => 'Date création',
    'modified' => 'Date modification',
    'visible' => 'Visible',
    'quizzes' => 'Quiz',
    'attempts' => 'Tentatives',
    'duplicates' => 'Doublons',
    'excerpt' => 'Extrait',
    'actions' => 'Actions'
];

echo html_writer::start_tag('div', ['class' => 'qd-columns-grid']);
foreach ($columns as $col_id => $col_name) {
    // Par défaut : afficher id, name, type, category, course, quizzes, duplicates, actions
    $checked = in_array($col_id, ['id', 'name', 'type', 'category', 'course', 'quizzes', 'duplicates', 'actions']);
    echo html_writer::start_tag('label', ['class' => 'qd-column-toggle', 'for' => 'column_' . $col_id]);
    echo html_writer::checkbox('column_' . $col_id, 1, $checked, ' ' . $col_name, [
        'id' => 'column_' . $col_id,  // 🔧 v1.9.11 FIX: Ajouter id explicite pour accessibilité
        'class' => 'column-toggle-checkbox',
        'data-column' => $col_id,
        'onchange' => 'toggleColumn(this)'
    ]);
    echo html_writer::end_tag('label');
}
echo html_writer::end_tag('div');

echo html_writer::end_tag('div');

// ======================================================================
// FILTRES ET RECHERCHE
// ======================================================================

echo html_writer::start_tag('div', ['class' => 'qd-filters', 'style' => 'margin-top: 30px;']);
echo html_writer::tag('h4', '🔍 Filtres et recherche', ['style' => 'margin-top: 0;']);

echo html_writer::start_tag('div', ['class' => 'qd-filters-row']);

// Recherche par nom/ID/texte
echo html_writer::start_tag('div', ['class' => 'qd-filter-group']);
echo html_writer::tag('label', 'Rechercher', ['for' => 'filter-search-questions']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'id' => 'filter-search-questions',
    'placeholder' => 'Nom, ID, cours, module, texte...',
    'class' => 'form-control'
]);
echo html_writer::end_tag('div');

// Filtre par type de question
echo html_writer::start_tag('div', ['class' => 'qd-filter-group']);
echo html_writer::tag('label', 'Type de question', ['for' => 'filter-type-questions']);
echo html_writer::start_tag('select', ['id' => 'filter-type-questions', 'class' => 'form-control']);
echo html_writer::tag('option', 'Tous', ['value' => 'all']);

// Récupérer les types uniques
$types_list = [];
if (isset($globalstats->by_type)) {
    foreach ($globalstats->by_type as $qtype => $count) {
        $types_list[$qtype] = $qtype . ' (' . $count . ')';
    }
    asort($types_list);
    foreach ($types_list as $qtype => $label) {
        echo html_writer::tag('option', $label, ['value' => $qtype]);
    }
}
echo html_writer::end_tag('select');
echo html_writer::end_tag('div');

// Filtre par usage
echo html_writer::start_tag('div', ['class' => 'qd-filter-group']);
echo html_writer::tag('label', 'Usage', ['for' => 'filter-usage-questions']);
echo html_writer::start_tag('select', ['id' => 'filter-usage-questions', 'class' => 'form-control']);
echo html_writer::tag('option', 'Toutes', ['value' => 'all']);
echo html_writer::tag('option', 'Utilisées (dans quiz ou tentatives)', ['value' => 'used']);
echo html_writer::tag('option', 'Inutilisées (supprimables)', ['value' => 'unused']);
echo html_writer::end_tag('select');
echo html_writer::end_tag('div');

// Filtre par doublons
echo html_writer::start_tag('div', ['class' => 'qd-filter-group']);
echo html_writer::tag('label', 'Doublons', ['for' => 'filter-duplicates-questions']);
echo html_writer::start_tag('select', ['id' => 'filter-duplicates-questions', 'class' => 'form-control']);
echo html_writer::tag('option', 'Toutes', ['value' => 'all']);
echo html_writer::tag('option', 'Avec doublons', ['value' => 'yes']);
echo html_writer::tag('option', 'Sans doublons', ['value' => 'no']);
echo html_writer::end_tag('select');
echo html_writer::end_tag('div');

echo html_writer::end_tag('div'); // fin qd-filters-row

echo html_writer::tag('div', '', ['id' => 'filter-stats-questions', 'style' => 'margin-top: 10px; font-size: 14px; color: #666;']);

echo html_writer::end_tag('div'); // fin qd-filters

// ======================================================================
// TABLEAU DES QUESTIONS
// ======================================================================

echo html_writer::tag('h3', '📝 Liste détaillée des questions', ['style' => 'margin-top: 30px;']);

echo html_writer::start_tag('div', ['id' => 'loading-questions', 'style' => 'text-align: center; padding: 40px;']);
echo html_writer::tag('p', '⏳ Chargement des questions en cours...', ['style' => 'font-size: 16px;']);
echo html_writer::tag('p', 'Cela peut prendre quelques instants pour les grandes bases de données.', ['style' => 'font-size: 14px; color: #666;']);
echo html_writer::end_tag('div');

// Charger les questions avec gestion d'erreurs optimisée
try {
    // 🚨 v1.6.0 : PROTECTION RENFORCÉE pour grandes bases (30 000+ questions)
    // Paramètre pour augmenter la limite si besoin
    $max_questions_display = optional_param('show', 10, PARAM_INT); // Par défaut : 10 questions (ultra rapide)
    $max_questions_display = min($max_questions_display, 5000); // Limite absolue : 5000
    
    $total_questions = $globalstats->total_questions;
    
    // 🚫 DÉSACTIVER la détection de doublons par défaut (trop lourd)
    $include_duplicates = false;
    
    // Message d'information pour les grandes bases
    echo html_writer::start_tag('div', ['class' => 'alert alert-info', 'style' => 'margin-bottom: 20px; border-left: 4px solid #0f6cbf;']);
    echo html_writer::tag('strong', '📊 Votre base de données : ');
    echo "<strong>" . number_format($total_questions, 0, ',', ' ') . " questions au total</strong>. ";
    echo '<br><br>';
    echo '🎯 <strong>Affichage actuel</strong> : Les <strong>' . min($max_questions_display, $total_questions) . ' premières questions</strong> sont affichées ci-dessous.';
    echo '<br><br>';
    echo '💡 <strong>Options</strong> :';
    echo '<ul style="margin-top: 10px;">';
    echo '<li>Utilisez les <strong>filtres</strong> pour affiner les résultats</li>';
    echo '<li>Changez le nombre de questions affichées : ';
    
    // Construire les URLs selon le mode de chargement
    $base_params = $load_used_duplicates ? ['loadusedduplicates' => 1] : ['loadstats' => 1];
    $url_10 = new moodle_url('/local/question_diagnostic/questions_cleanup.php', array_merge($base_params, ['show' => 10]));
    $url_50 = new moodle_url('/local/question_diagnostic/questions_cleanup.php', array_merge($base_params, ['show' => 50]));
    $url_100 = new moodle_url('/local/question_diagnostic/questions_cleanup.php', array_merge($base_params, ['show' => 100]));
    $url_500 = new moodle_url('/local/question_diagnostic/questions_cleanup.php', array_merge($base_params, ['show' => 500]));
    $url_1000 = new moodle_url('/local/question_diagnostic/questions_cleanup.php', array_merge($base_params, ['show' => 1000]));
    
    echo html_writer::link($url_10, '10', ['class' => $max_questions_display == 10 ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-secondary']);
    echo ' ';
    echo html_writer::link($url_50, '50', ['class' => $max_questions_display == 50 ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-secondary']);
    echo ' ';
    echo html_writer::link($url_100, '100', ['class' => $max_questions_display == 100 ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-secondary']);
    echo ' ';
    echo html_writer::link($url_500, '500', ['class' => $max_questions_display == 500 ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-secondary']);
    echo ' ';
    echo html_writer::link($url_1000, '1000', ['class' => $max_questions_display == 1000 ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-secondary']);
    echo '</li>';
    echo '</ul>';
    echo '<p style="margin-top: 15px;"><em>Les statistiques globales ci-dessus concernent bien <strong>TOUTES les ' . number_format($total_questions, 0, ',', ' ') . ' questions</strong>.</em></p>';
    echo html_writer::end_tag('div');
    
    // 🆕 v1.8.0 : Mode de chargement ciblé pour doublons utilisés
    if ($load_used_duplicates) {
        // Charger uniquement les doublons avec au moins 1 version utilisée
        $questions = question_analyzer::get_used_duplicates_questions($max_questions_display);
        
        // Enrichir avec les stats
        $questions_with_stats = [];
        foreach ($questions as $q) {
            $stats = question_analyzer::get_question_stats($q);
            $questions_with_stats[] = $stats;
        }
        
        // Message d'information spécifique
        echo html_writer::start_tag('div', ['class' => 'alert alert-success', 'style' => 'margin: 20px 0; border-left: 4px solid #28a745;']);
        echo html_writer::tag('h3', '📋 Mode Doublons Utilisés Activé', ['style' => 'margin-top: 0; color: #28a745;']);
        echo html_writer::tag('p', '<strong>' . count($questions_with_stats) . ' questions</strong> en doublon avec au moins 1 version utilisée ont été chargées.');
        echo html_writer::tag('p', '✅ Ce mode affiche uniquement les groupes de doublons qui sont actuellement utilisés dans des quiz ou ont des tentatives.');
        echo html_writer::tag('p', '<strong>💡 Conseil</strong> : Utilisez les filtres "Usage = Inutilisées" pour identifier rapidement les versions à supprimer.');
        echo html_writer::end_tag('div');
        
    } else {
        // Mode normal : charger toutes les questions
        $limit = min($max_questions_display, $total_questions);
        $questions_with_stats = question_analyzer::get_all_questions_with_stats($include_duplicates, $limit);
    }
    
} catch (Exception $e) {
    echo html_writer::start_tag('script');
    echo "
var loadingQuestions = document.getElementById('loading-questions');
if (loadingQuestions) {
    loadingQuestions.style.display = 'none';
}
";
    echo html_writer::end_tag('script');
    
    echo html_writer::start_tag('div', ['class' => 'alert alert-danger']);
    echo html_writer::tag('strong', '⚠️ Erreur : ');
    echo 'Impossible de charger les questions. Cela peut être dû à une base de données trop volumineuse ou à un timeout. ';
    echo html_writer::tag('p', 'Détails : ' . $e->getMessage(), ['style' => 'margin-top: 10px; font-size: 12px;']);
    echo html_writer::tag('p', 'Suggestions :', ['style' => 'margin-top: 10px; font-weight: bold;']);
    echo html_writer::start_tag('ul');
    echo html_writer::tag('li', 'Augmenter le timeout PHP dans votre configuration');
    echo html_writer::tag('li', 'Augmenter la limite de mémoire PHP');
    echo html_writer::tag('li', 'Vider le cache Moodle (Administration du site > Développement > Purger tous les caches)');
    echo html_writer::tag('li', 'Contacter votre administrateur système');
    echo html_writer::end_tag('ul');
    echo html_writer::end_tag('div');
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::start_tag('script');
echo "
var loadingQuestions = document.getElementById('loading-questions');
if (loadingQuestions) {
    loadingQuestions.style.display = 'none';
}
";
echo html_writer::end_tag('script');

echo html_writer::start_tag('div', ['class' => 'qd-table-wrapper']);
echo html_writer::start_tag('table', ['class' => 'qd-table', 'id' => 'questions-table']);

// En-tête du tableau
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
echo html_writer::tag('th', 'ID', ['class' => 'sortable col-id', 'data-column' => 'id']);
echo html_writer::tag('th', 'Nom', ['class' => 'sortable col-name', 'data-column' => 'name']);
echo html_writer::tag('th', 'Type', ['class' => 'sortable col-type', 'data-column' => 'type']);
echo html_writer::tag('th', 'Catégorie', ['class' => 'sortable col-category', 'data-column' => 'category']);
echo html_writer::tag('th', 'Cours', ['class' => 'sortable col-course', 'data-column' => 'course']);
echo html_writer::tag('th', 'Module', ['class' => 'sortable col-module', 'data-column' => 'module', 'style' => 'display: none;']);
echo html_writer::tag('th', 'Contexte', ['class' => 'sortable col-context', 'data-column' => 'context', 'style' => 'display: none;']);
echo html_writer::tag('th', 'Créateur', ['class' => 'sortable col-creator', 'data-column' => 'creator', 'style' => 'display: none;']);
echo html_writer::tag('th', 'Créée le', ['class' => 'sortable col-created', 'data-column' => 'created', 'style' => 'display: none;']);
echo html_writer::tag('th', 'Modifiée le', ['class' => 'sortable col-modified', 'data-column' => 'modified', 'style' => 'display: none;']);
echo html_writer::tag('th', 'Visible', ['class' => 'sortable col-visible', 'data-column' => 'visible', 'style' => 'display: none;']);
echo html_writer::tag('th', 'Quiz', ['class' => 'sortable col-quizzes', 'data-column' => 'quizzes']);
echo html_writer::tag('th', 'Tentatives', ['class' => 'sortable col-attempts', 'data-column' => 'attempts', 'style' => 'display: none;']);
echo html_writer::tag('th', 'Doublons', ['class' => 'sortable col-duplicates', 'data-column' => 'duplicates']);
echo html_writer::tag('th', 'Extrait', ['class' => 'col-excerpt', 'style' => 'display: none;']);
echo html_writer::tag('th', 'Actions', ['class' => 'col-actions']);
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');

// Corps du tableau
echo html_writer::start_tag('tbody');

// 🆕 v1.9.0 : VÉRIFICATION BATCH pour les boutons de suppression (performance optimisée)
// Extraire tous les IDs de questions
$question_ids = array_map(function($item) { return $item->question->id; }, $questions_with_stats);
// Vérifier en une seule fois si elles peuvent être supprimées
$deletability_map = question_analyzer::can_delete_questions_batch($question_ids);

foreach ($questions_with_stats as $item) {
    $q = $item->question;
    $s = $item->stats;
    
    // Attributs data pour le filtrage et le tri
    $row_attrs = [
        'data-id' => $q->id,
        'data-name' => format_string($q->name),
        'data-type' => $q->qtype,
        'data-category' => $s->category_name,
        'data-course' => $s->course_name ?? '',
        'data-module' => $s->module_name ?? '',
        'data-context' => $s->context_name,
        'data-creator' => $s->creator_name,
        'data-created' => $s->created_date,
        'data-modified' => $s->modified_date,
        'data-visible' => $s->is_hidden ? '0' : '1',
        'data-quizzes' => $s->used_in_quizzes,
        'data-attempts' => $s->attempt_count,
        'data-duplicates' => $s->duplicate_count,
        'data-used' => $s->is_used ? '1' : '0',
        'data-is-duplicate' => $s->is_duplicate ? '1' : '0',
        'data-excerpt' => htmlspecialchars($s->questiontext_excerpt)
    ];
    
    echo html_writer::start_tag('tr', $row_attrs);
    
    // ID
    echo html_writer::tag('td', $q->id, ['class' => 'col-id']);
    
    // Nom
    echo html_writer::start_tag('td', ['class' => 'col-name']);
    echo html_writer::tag('strong', format_string($q->name));
    echo html_writer::end_tag('td');
    
    // Type
    echo html_writer::tag('td', html_writer::tag('span', 
        ucfirst($q->qtype), 
        ['class' => 'badge badge-info']
    ), ['class' => 'col-type']);
    
    // Catégorie
    echo html_writer::start_tag('td', ['class' => 'col-category']);
    $cat_url = new moodle_url('/question/edit.php', [
        'courseid' => 0,
        'cat' => $s->category_id . ',' . $s->context_id
    ]);
    echo html_writer::link($cat_url, $s->category_name, ['target' => '_blank', 'title' => 'Voir la catégorie']);
    echo html_writer::end_tag('td');
    
    // Cours
    echo html_writer::start_tag('td', ['class' => 'col-course']);
    if (!empty($s->course_name)) {
        echo html_writer::tag('span', '📚 ' . $s->course_name, [
            'style' => 'font-size: 13px;',
            'title' => $s->course_name
        ]);
    } else {
        echo html_writer::tag('span', '-', ['style' => 'color: #999;']);
    }
    echo html_writer::end_tag('td');
    
    // Module
    echo html_writer::start_tag('td', ['class' => 'col-module', 'style' => 'display: none;']);
    if (!empty($s->module_name)) {
        echo html_writer::tag('span', '📝 ' . $s->module_name, [
            'style' => 'font-size: 13px;',
            'title' => $s->module_name
        ]);
    } else {
        echo html_writer::tag('span', '-', ['style' => 'color: #999;']);
    }
    echo html_writer::end_tag('td');
    
    // Contexte (avec info cours/module)
    $context_display = $s->context_name;
    $tooltip_parts = [];
    if (!empty($s->course_name)) {
        $tooltip_parts[] = '📚 Cours : ' . $s->course_name;
    }
    if (!empty($s->module_name)) {
        $tooltip_parts[] = '📝 Module : ' . $s->module_name;
    }
    $tooltip = !empty($tooltip_parts) ? implode("\n", $tooltip_parts) : '';
    
    if ($tooltip) {
        $context_html = html_writer::tag('span', $context_display, [
            'title' => $tooltip,
            'style' => 'cursor: help; border-bottom: 1px dotted #666;'
        ]);
    } else {
        $context_html = $context_display;
    }
    echo html_writer::tag('td', $context_html, ['class' => 'col-context', 'style' => 'display: none;']);
    
    // Créateur
    echo html_writer::tag('td', $s->creator_name, ['class' => 'col-creator', 'style' => 'display: none;']);
    
    // Date création
    echo html_writer::tag('td', $s->created_formatted, ['class' => 'col-created', 'style' => 'display: none;']);
    
    // Date modification
    echo html_writer::tag('td', $s->modified_formatted, ['class' => 'col-modified', 'style' => 'display: none;']);
    
    // Visible
    echo html_writer::tag('td', 
        $s->is_hidden ? '❌ Non' : '✅ Oui',
        ['class' => 'col-visible', 'style' => 'display: none;']
    );
    
    // Quiz
    echo html_writer::start_tag('td', ['class' => 'col-quizzes']);
    if ($s->used_in_quizzes > 0) {
        echo html_writer::tag('span', $s->used_in_quizzes, [
            'class' => 'qd-badge qd-badge-ok',
            'title' => 'Utilisée dans ' . $s->used_in_quizzes . ' quiz'
        ]);
        
        // Afficher la liste des quiz au survol
        if (!empty($s->quiz_list)) {
            $quiz_titles = array_map(function($quiz) {
                return format_string($quiz->name);
            }, $s->quiz_list);
            echo html_writer::tag('span', ' ℹ️', [
                'title' => implode(', ', $quiz_titles),
                'style' => 'cursor: help;'
            ]);
        }
    } else {
        echo html_writer::tag('span', '0', ['class' => 'qd-badge qd-badge-empty']);
    }
    echo html_writer::end_tag('td');
    
    // Tentatives
    echo html_writer::start_tag('td', ['class' => 'col-attempts', 'style' => 'display: none;']);
    if ($s->attempt_count > 0) {
        echo html_writer::tag('span', $s->attempt_count, ['class' => 'qd-badge qd-badge-ok']);
    } else {
        echo html_writer::tag('span', '0', ['class' => 'qd-badge qd-badge-empty']);
    }
    echo html_writer::end_tag('td');
    
    // Doublons
    echo html_writer::start_tag('td', ['class' => 'col-duplicates']);
    if ($s->duplicate_count > 0) {
        echo html_writer::tag('button', $s->duplicate_count, [
            'class' => 'qd-badge qd-badge-danger duplicate-btn',
            'data-questionid' => $q->id,
            'data-duplicateids' => json_encode($s->duplicate_ids),
            'onclick' => 'showDuplicatesModal(' . $q->id . ', ' . json_encode(format_string($q->name)) . ', ' . json_encode($s->duplicate_ids) . ')',
            'style' => 'cursor: pointer; border: none;',
            'title' => 'Cliquer pour voir les doublons'
        ]);
    } else {
        echo html_writer::tag('span', '0', ['class' => 'qd-badge qd-badge-ok']);
    }
    echo html_writer::end_tag('td');
    
    // Extrait
    echo html_writer::tag('td', htmlspecialchars($s->questiontext_excerpt), ['class' => 'col-excerpt', 'style' => 'display: none; font-size: 12px; color: #666;']);
    
    // Actions
    echo html_writer::start_tag('td', ['class' => 'col-actions']);
    echo html_writer::start_tag('div', ['class' => 'qd-actions']);
    
    // Bouton voir
    $questionbank_url = question_analyzer::get_question_bank_url($q);
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
     
     // 🆕 v1.9.0 : Bouton supprimer (OPTIMISÉ avec vérification batch)
     $can_delete_check = isset($deletability_map[$q->id]) ? $deletability_map[$q->id] : null;
     if ($can_delete_check && $can_delete_check->can_delete) {
         $delete_url = new moodle_url('/local/question_diagnostic/actions/delete_question.php', [
             'id' => $q->id,
             'sesskey' => sesskey()
         ]);
         echo html_writer::link(
             $delete_url,
             '🗑️',
             [
                 'class' => 'qd-btn qd-btn-delete',
                 'title' => 'Supprimer ce doublon inutilisé',
                 'style' => 'background: #d9534f; color: white; padding: 5px 10px; border-radius: 3px; margin-left: 5px; text-decoration: none;'
             ]
         );
     } else {
         // Bouton désactivé avec tooltip expliquant pourquoi
         $reason = $can_delete_check ? $can_delete_check->reason : 'Vérification impossible';
         echo html_writer::tag('span', '🔒', [
             'class' => 'qd-btn qd-btn-disabled',
             'title' => 'Protection : ' . $reason,
             'style' => 'background: #e0e0e0; color: #999; padding: 5px 10px; border-radius: 3px; cursor: not-allowed; margin-left: 5px; display: inline-block;'
         ]);
     }
     
     echo html_writer::end_tag('div');
    echo html_writer::end_tag('td');
    
    echo html_writer::end_tag('tr');
}

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');
echo html_writer::end_tag('div'); // fin qd-table-wrapper

// ======================================================================
// MODAL DES DOUBLONS
// ======================================================================

echo html_writer::start_tag('div', ['class' => 'qd-modal', 'id' => 'duplicates-modal']);
echo html_writer::start_tag('div', ['class' => 'qd-modal-content', 'style' => 'max-width: 900px;']);

echo html_writer::start_tag('div', ['class' => 'qd-modal-header']);
echo html_writer::tag('h3', '🔀 Questions en doublon', ['class' => 'qd-modal-title']);
echo html_writer::tag('button', '&times;', ['class' => 'qd-modal-close', 'onclick' => 'closeDuplicatesModal()']);
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'qd-modal-body'], ['id' => 'duplicates-modal-body']);
echo html_writer::tag('p', 'Chargement...');
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'qd-modal-footer']);
echo html_writer::tag('button', 'Fermer', ['class' => 'btn btn-secondary', 'onclick' => 'closeDuplicatesModal()']);
echo html_writer::end_tag('div');

echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

// ======================================================================
// JavaScript pour les interactions
// ======================================================================

echo html_writer::start_tag('script');
?>
// ======================================================================
// GESTION DES COLONNES
// ======================================================================

function toggleColumnsPanel() {
    const panel = document.getElementById('columns-panel');
    if (panel.style.display === 'none') {
        panel.style.display = 'block';
    } else {
        panel.style.display = 'none';
    }
}

function toggleColumn(checkbox) {
    const columnClass = 'col-' + checkbox.getAttribute('data-column');
    const cells = document.querySelectorAll('.' + columnClass);
    
    cells.forEach(function(cell) {
        if (checkbox.checked) {
            cell.style.display = '';
        } else {
            cell.style.display = 'none';
        }
    });
    
    // Sauvegarder les préférences dans localStorage
    const prefs = JSON.parse(localStorage.getItem('qd_column_prefs') || '{}');
    prefs[checkbox.getAttribute('data-column')] = checkbox.checked;
    localStorage.setItem('qd_column_prefs', JSON.stringify(prefs));
}

// Restaurer les préférences au chargement
document.addEventListener('DOMContentLoaded', function() {
    const prefs = JSON.parse(localStorage.getItem('qd_column_prefs') || '{}');
    
    document.querySelectorAll('.column-toggle-checkbox').forEach(function(checkbox) {
        const col = checkbox.getAttribute('data-column');
        if (prefs.hasOwnProperty(col)) {
            checkbox.checked = prefs[col];
            toggleColumn(checkbox);
        }
    });
});

// ======================================================================
// FILTRES
// ======================================================================

document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('filter-search');
    const qtypeFilter = document.getElementById('filter-qtype');
    const usageFilter = document.getElementById('filter-usage');
    const duplicateFilter = document.getElementById('filter-duplicate');
    
    function applyFilters() {
        const searchValue = searchInput ? searchInput.value.toLowerCase() : '';
        const qtypeValue = qtypeFilter ? qtypeFilter.value : 'all';
        const usageValue = usageFilter ? usageFilter.value : 'all';
        const duplicateValue = duplicateFilter ? duplicateFilter.value : 'all';
        
        const rows = document.querySelectorAll('.qd-table tbody tr');
        let visibleCount = 0;
        
        rows.forEach(function(row) {
            const id = (row.getAttribute('data-id') || '').toLowerCase();
            const name = (row.getAttribute('data-name') || '').toLowerCase();
            const type = row.getAttribute('data-type') || '';
            const category = (row.getAttribute('data-category') || '').toLowerCase();
            const course = (row.getAttribute('data-course') || '').toLowerCase();
            const module = (row.getAttribute('data-module') || '').toLowerCase();
            const excerpt = (row.getAttribute('data-excerpt') || '').toLowerCase();
            const used = row.getAttribute('data-used') === '1';
            const isDuplicate = row.getAttribute('data-is-duplicate') === '1';
            
            const matchesSearch = searchValue === '' || 
                                 id.includes(searchValue) || 
                                 name.includes(searchValue) || 
                                 category.includes(searchValue) ||
                                 course.includes(searchValue) ||
                                 module.includes(searchValue) ||
                                 excerpt.includes(searchValue);
            const matchesQtype = qtypeValue === 'all' || type === qtypeValue;
            const matchesUsage = usageValue === 'all' || 
                                (usageValue === 'used' && used) || 
                                (usageValue === 'unused' && !used);
            const matchesDuplicate = duplicateValue === 'all' || 
                                    (duplicateValue === 'duplicate' && isDuplicate) || 
                                    (duplicateValue === 'no_duplicate' && !isDuplicate);
            
            if (matchesSearch && matchesQtype && matchesUsage && matchesDuplicate) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Mettre à jour le compteur
        const statsDiv = document.getElementById('filter-stats');
        if (statsDiv) {
            statsDiv.innerHTML = visibleCount + ' question(s) affichée(s) sur ' + rows.length;
        }
    }
    
    if (searchInput) {
        let debounceTimer;
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(applyFilters, 300);
        });
    }
    if (qtypeFilter) qtypeFilter.addEventListener('change', applyFilters);
    if (usageFilter) usageFilter.addEventListener('change', applyFilters);
    if (duplicateFilter) duplicateFilter.addEventListener('change', applyFilters);
    
    // Appliquer les filtres initiaux
    applyFilters();
});

// ======================================================================
// TRI DES COLONNES
// ======================================================================

document.addEventListener('DOMContentLoaded', function() {
    const headers = document.querySelectorAll('.qd-table th.sortable');
    let currentSort = { column: null, direction: 'asc' };
    
    headers.forEach(function(header) {
        header.style.cursor = 'pointer';
        header.addEventListener('click', function() {
            const column = this.getAttribute('data-column');
            
            if (currentSort.column === column) {
                currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
            } else {
                currentSort.column = column;
                currentSort.direction = 'asc';
            }
            
            sortTable(column, currentSort.direction);
            
            // Mettre à jour les indicateurs visuels
            headers.forEach(h => {
                h.classList.remove('sort-asc', 'sort-desc');
            });
            this.classList.add('sort-' + currentSort.direction);
        });
    });
});

function sortTable(column, direction) {
    const tbody = document.querySelector('.qd-table tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    rows.sort(function(a, b) {
        let aVal = a.getAttribute('data-' + column) || '';
        let bVal = b.getAttribute('data-' + column) || '';
        
        // Tenter de convertir en nombre si possible
        const aNum = parseFloat(aVal);
        const bNum = parseFloat(bVal);
        
        if (!isNaN(aNum) && !isNaN(bNum)) {
            return direction === 'asc' ? aNum - bNum : bNum - aNum;
        } else {
            aVal = aVal.toLowerCase();
            bVal = bVal.toLowerCase();
            if (direction === 'asc') {
                return aVal < bVal ? -1 : aVal > bVal ? 1 : 0;
            } else {
                return aVal > bVal ? -1 : aVal < bVal ? 1 : 0;
            }
        }
    });
    
    rows.forEach(row => tbody.appendChild(row));
}

// ======================================================================
// MODAL DES DOUBLONS
// ======================================================================

function showDuplicatesModal(questionId, questionName, duplicateIds) {
    const modal = document.getElementById('duplicates-modal');
    const modalBody = document.getElementById('duplicates-modal-body');
    
    let content = '<h4>Question : ' + questionName + ' (ID: ' + questionId + ')</h4>';
    content += '<p><strong>' + duplicateIds.length + ' question(s) en doublon détectée(s)</strong></p>';
    content += '<p style="color: #666; font-size: 14px;">Ces questions ont un contenu similaire (nom, texte, type).</p>';
    
    content += '<div style="margin-top: 20px;">';
    content += '<table class="table table-bordered" style="width: 100%;">';
    content += '<thead><tr><th>ID</th><th>Actions</th></tr></thead>';
    content += '<tbody>';
    
    duplicateIds.forEach(function(dupId) {
        const dupRow = document.querySelector('tr[data-id="' + dupId + '"]');
        if (dupRow) {
            const dupName = dupRow.getAttribute('data-name');
            const dupType = dupRow.getAttribute('data-type');
            const dupCategory = dupRow.getAttribute('data-category');
            
            content += '<tr>';
            content += '<td>' + dupId + '</td>';
            content += '<td>';
            content += '<strong>' + dupName + '</strong><br>';
            content += '<small>Type: ' + dupType + ' | Catégorie: ' + dupCategory + '</small><br>';
            content += '<a href="' + M.cfg.wwwroot + '/question/edit.php?courseid=1&qid=' + dupId + '" target="_blank" class="btn btn-sm btn-primary" style="margin-top: 5px;">👁️ Voir</a>';
            content += '</td>';
            content += '</tr>';
        }
    });
    
    content += '</tbody></table>';
    content += '</div>';
    
    content += '<div style="margin-top: 20px; padding: 15px; background: #d9edf7; border: 1px solid #bce8f1; border-radius: 5px;">';
    content += '<strong>💡 Recommandation :</strong> ';
    content += 'Vérifiez manuellement ces questions pour confirmer qu\'il s\'agit bien de doublons. ';
    content += 'Vous pouvez ensuite supprimer ou fusionner les questions redondantes.';
    content += '</div>';
    
    modalBody.innerHTML = content;
    modal.style.display = 'block';
}

function closeDuplicatesModal() {
    const modal = document.getElementById('duplicates-modal');
    modal.style.display = 'none';
}

// Fermer le modal en cliquant en dehors
window.onclick = function(event) {
    const modal = document.getElementById('duplicates-modal');
    if (event.target === modal) {
        closeDuplicatesModal();
    }
}

<?php
echo html_writer::end_tag('script');

// ======================================================================
// CSS supplémentaire pour cette page
// ======================================================================

echo html_writer::start_tag('style');
?>
.qd-columns-panel {
    background: #f9f9f9;
    border: 2px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.qd-columns-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 10px;
}

.qd-column-toggle {
    display: flex;
    align-items: center;
    cursor: pointer;
    padding: 5px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    transition: background 0.2s;
}

.qd-column-toggle:hover {
    background: #e9ecef;
}

.qd-column-toggle input {
    margin-right: 8px;
}

.qd-stats-by-type {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.qd-stat-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 15px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 6px;
}

.qd-stat-label {
    font-weight: 600;
    color: #333;
}

.qd-stat-value {
    font-size: 16px;
    color: #0066cc;
    font-weight: bold;
}

.sort-asc::after {
    content: ' ▲';
    font-size: 10px;
}

.sort-desc::after {
    content: ' ▼';
    font-size: 10px;
}

.qd-badge-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #dc3545;
}

.qd-badge-danger:hover {
    background: #f5c6cb;
}

@media (max-width: 768px) {
    .qd-columns-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .qd-stats-by-type {
        grid-template-columns: 1fr;
    }
}
<?php
echo html_writer::end_tag('style');

// ======================================================================
// Pied de page Moodle standard
// ======================================================================
echo $OUTPUT->footer();

