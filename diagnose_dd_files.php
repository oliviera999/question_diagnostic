<?php
/**
 * Script de diagnostic pour les fichiers drag and drop
 * Analyser comment sont stockés les fichiers bgimage
 */

require_once(__DIR__ . '/../../config.php');

require_login();
if (!is_siteadmin()) {
    print_error('accessdenied', 'admin');
}

$PAGE->set_url(new moodle_url('/local/question_diagnostic/diagnose_dd_files.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Diagnostic Drag & Drop Files');
$PAGE->set_heading('Diagnostic des Fichiers Drag & Drop');

echo $OUTPUT->header();

echo html_writer::tag('h2', '🔍 Diagnostic des Fichiers pour Questions Drag and Drop');

// Trouver des questions ddmarker et ddimageortext
$dd_questions = $DB->get_records_sql("
    SELECT * FROM {question}
    WHERE qtype IN ('ddmarker', 'ddimageortext')
    ORDER BY id DESC
    LIMIT 10
");

if (empty($dd_questions)) {
    echo html_writer::div('Aucune question drag and drop trouvée.', 'alert alert-warning');
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::div(count($dd_questions) . ' question(s) drag and drop trouvée(s)', 'alert alert-info');

foreach ($dd_questions as $question) {
    echo html_writer::tag('h3', "Question ID: {$question->id} - Type: {$question->qtype}");
    echo html_writer::tag('h4', s($question->name), ['style' => 'color: #666;']);
    
    // Récupérer la catégorie via question_bank_entries
    $category_sql = "SELECT qc.* 
                    FROM {question_categories} qc
                    INNER JOIN {question_bank_entries} qbe ON qbe.questioncategoryid = qc.id
                    INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                    WHERE qv.questionid = :questionid
                    LIMIT 1";
    $category = $DB->get_record_sql($category_sql, ['questionid' => $question->id]);
    
    if (!$category) {
        echo html_writer::div('❌ Catégorie introuvable', 'alert alert-danger');
        continue;
    }
    
    echo '<p><strong>Catégorie:</strong> ' . s($category->name) . ' (ID: ' . $category->id . ')<br>';
    echo '<strong>Contexte ID:</strong> ' . $category->contextid . '</p>';
    
    try {
        $context = context::instance_by_id($category->contextid, IGNORE_MISSING);
        if (!$context) {
            echo html_writer::div('❌ Contexte invalide', 'alert alert-danger');
            continue;
        }
        
        echo '<p><strong>Type contexte:</strong> ' . context_helper::get_level_name($context->contextlevel) . '</p>';
        
        // Récupérer les données spécifiques au type
        if ($question->qtype == 'ddimageortext') {
            $dddata = $DB->get_record('qtype_ddimageortext', ['questionid' => $question->id]);
        } else if ($question->qtype == 'ddmarker') {
            $dddata = $DB->get_record('qtype_ddmarker', ['questionid' => $question->id]);
        }
        
        if ($dddata) {
            echo '<h4>Données spécifiques du plugin:</h4>';
            echo '<pre style="background: #f5f5f5; padding: 10px; border-radius: 4px;">';
            print_r($dddata);
            echo '</pre>';
        }
        
        // Lister TOUS les fichiers associés à cette question dans ce contexte
        echo '<h4>Fichiers trouvés dans le contexte (toutes zones):</h4>';
        
        $fs = get_file_storage();
        $all_areas = ['questiontext', 'generalfeedback', 'answer', 'answerfeedback', 'bgimage', 'dragimage'];
        $component = 'qtype_' . $question->qtype; // Composant spécifique au type de question
        
        $found_files = [];
        
        // Chercher avec composant 'question'
        foreach ($all_areas as $area) {
            // Essayer avec itemid = questionid
            $files1 = $fs->get_area_files($context->id, 'question', $area, $question->id, 'filename', false);
            if (!empty($files1)) {
                $found_files[$area . ' (question, itemid=' . $question->id . ')'] = $files1;
            }
            
            // Essayer avec itemid = 0
            $files2 = $fs->get_area_files($context->id, 'question', $area, 0, 'filename', false);
            if (!empty($files2)) {
                $found_files[$area . ' (question, itemid=0)'] = $files2;
            }
        }
        
        // Chercher avec composant spécifique (qtype_ddmarker ou qtype_ddimageortext)
        foreach ($all_areas as $area) {
            // Essayer avec itemid = questionid
            $files3 = $fs->get_area_files($context->id, $component, $area, $question->id, 'filename', false);
            if (!empty($files3)) {
                $found_files[$area . ' (' . $component . ', itemid=' . $question->id . ')'] = $files3;
            }
            
            // Essayer avec itemid = 0
            $files4 = $fs->get_area_files($context->id, $component, $area, 0, 'filename', false);
            if (!empty($files4)) {
                $found_files[$area . ' (' . $component . ', itemid=0)'] = $files4;
            }
            
            // Essayer avec itemid = bgimage field value si disponible
            if ($dddata && isset($dddata->bgimage)) {
                $files5 = $fs->get_area_files($context->id, $component, $area, $dddata->bgimage, 'filename', false);
                if (!empty($files5)) {
                    $found_files[$area . ' (' . $component . ', itemid=' . $dddata->bgimage . ')'] = $files5;
                }
            }
        }
        
        if (empty($found_files)) {
            echo html_writer::div('❌ Aucun fichier trouvé', 'alert alert-warning');
        } else {
            echo '<table class="generaltable" style="width: 100%;">';
            echo '<thead><tr>
                    <th>FileArea (Component, ItemID)</th>
                    <th>Filename</th>
                    <th>Size</th>
                    <th>Mimetype</th>
                    <th>Contenthash</th>
                  </tr></thead>';
            echo '<tbody>';
            
            foreach ($found_files as $area_key => $files) {
                $first = true;
                foreach ($files as $file) {
                    echo '<tr' . ($first ? ' style="border-top: 2px solid #333;"' : '') . '>';
                    echo '<td>' . ($first ? '<strong>' . $area_key . '</strong>' : '') . '</td>';
                    echo '<td>' . s($file->get_filename()) . '</td>';
                    echo '<td>' . display_size($file->get_filesize()) . '</td>';
                    echo '<td>' . $file->get_mimetype() . '</td>';
                    echo '<td style="font-size: 0.7em;">' . substr($file->get_contenthash(), 0, 12) . '...</td>';
                    echo '</tr>';
                    $first = false;
                }
            }
            
            echo '</tbody></table>';
        }
        
        // Tester l'URL générée
        echo '<h4>URL générée pour accéder à cette question:</h4>';
        
        try {
            $courseid = 0;
            
            if ($context->contextlevel == CONTEXT_COURSE) {
                $courseid = $context->instanceid;
            } else if ($context->contextlevel == CONTEXT_MODULE) {
                $coursecontext = $context->get_course_context(false);
                if ($coursecontext) {
                    $courseid = $coursecontext->instanceid;
                }
            } else if ($context->contextlevel == CONTEXT_SYSTEM) {
                // Pour contexte système, utiliser SITE course (id=1)
                $courseid = SITEID;
            }
            
            $url = new moodle_url('/question/edit.php', [
                'courseid' => $courseid,
                'cat' => $category->id . ',' . $category->contextid,
                'qid' => $question->id
            ]);
            
            echo '<p><strong>URL:</strong> ' . html_writer::link($url, $url->out(), ['target' => '_blank']) . '</p>';
            echo '<p><strong>courseid utilisé:</strong> ' . $courseid . ' (Context level: ' . $context->contextlevel . ')</p>';
            
            // Vérifier si le cours existe
            if ($courseid > 0) {
                $course_exists = $DB->record_exists('course', ['id' => $courseid]);
                if ($course_exists) {
                    echo html_writer::div('✅ Le cours ID ' . $courseid . ' existe dans la BDD', 'alert alert-success');
                } else {
                    echo html_writer::div('❌ ERREUR: Le cours ID ' . $courseid . ' n\'existe PAS dans la BDD !', 'alert alert-danger');
                }
            }
            
        } catch (Exception $e) {
            echo html_writer::div('❌ Erreur génération URL: ' . $e->getMessage(), 'alert alert-danger');
        }
        
    } catch (Exception $e) {
        echo html_writer::div('❌ Erreur: ' . $e->getMessage(), 'alert alert-danger');
    }
    
    echo '<hr style="margin: 30px 0; border: 1px solid #ddd;">';
}

echo html_writer::start_div('alert alert-info', ['style' => 'margin-top: 40px;']);
echo '<h4>💡 Ce que ce diagnostic permet de vérifier:</h4>';
echo '<ul>';
echo '<li>Le <strong>composant</strong> utilisé pour stocker bgimage (question vs qtype_ddmarker)</li>';
echo '<li>L\'<strong>itemid</strong> utilisé (questionid, 0, ou autre valeur)</li>';
echo '<li>Si l\'<strong>URL générée</strong> est valide (courseid correct)</li>';
echo '<li>Les <strong>filearea</strong> existantes pour ces types de questions</li>';
echo '</ul>';
echo html_writer::end_div();

echo $OUTPUT->footer();

