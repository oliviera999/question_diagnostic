<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_question_diagnostic;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->libdir . '/filelib.php');

/**
 * Gestionnaire de vérification des liens dans les questions
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_link_checker {

    /**
     * Récupère toutes les questions avec des liens cassés
     *
     * @return array Tableau des questions avec détails des liens cassés
     */
    public static function get_questions_with_broken_links() {
        global $DB;

        $questions = $DB->get_records('question', null, 'id ASC');
        $broken = [];

        foreach ($questions as $question) {
            // Skip questions without a valid category property
            if (!isset($question->category) || empty($question->category)) {
                continue;
            }
            
            $broken_links = self::check_question_links($question);
            
            if (!empty($broken_links)) {
                $category = $DB->get_record('question_categories', ['id' => $question->category]);
                $broken[] = (object)[
                    'question' => $question,
                    'category' => $category,
                    'broken_links' => $broken_links,
                    'broken_count' => count($broken_links)
                ];
            }
        }

        return $broken;
    }

    /**
     * Vérifie les liens dans une question
     *
     * @param object $question Objet question
     * @return array Tableau des liens cassés
     */
    public static function check_question_links($question) {
        global $DB;
        
        $broken_links = [];
        
        // Récupérer tous les champs texte de la question
        $text_fields = [
            'questiontext' => $question->questiontext,
            'generalfeedback' => $question->generalfeedback
        ];
        
        // Ajouter les champs spécifiques selon le type de question
        $qtype = $question->qtype;
        
        // Pour les questions de type multichoice, truefalse, shortanswer, etc.
        if ($qtype == 'multichoice' || $qtype == 'truefalse') {
            $answers = $DB->get_records('question_answers', ['question' => $question->id]);
            foreach ($answers as $answer) {
                $text_fields['answer_' . $answer->id] = $answer->answer;
                $text_fields['feedback_' . $answer->id] = $answer->feedback;
            }
        }
        
        // Pour les questions de type ddimageortext (drag and drop sur image)
        if ($qtype == 'ddimageortext') {
            // Récupérer les informations spécifiques du plugin
            $ddimageortext = $DB->get_record('qtype_ddimageortext', ['questionid' => $question->id]);
            if ($ddimageortext) {
                // Le champ bgimage contient le fileitemid pour l'image de fond
                // On vérifie si les fichiers existent
                $bg_files = self::get_question_files($question->id, 'bgimage');
                if (empty($bg_files)) {
                    $broken_links[] = (object)[
                        'field' => 'bgimage (drag and drop)',
                        'url' => 'Background image missing',
                        'reason' => 'Image de fond manquante pour drag and drop'
                    ];
                }
            }
            
            // Vérifier les drag items
            $dragitems = $DB->get_records('qtype_ddimageortext_drags', ['questionid' => $question->id]);
            foreach ($dragitems as $item) {
                if (!empty($item->label)) {
                    $text_fields['dragitem_' . $item->id] = $item->label;
                }
            }
        }
        
        // Pour les questions de type ddmarker (drag and drop markers)
        if ($qtype == 'ddmarker') {
            $ddmarker = $DB->get_record('qtype_ddmarker', ['questionid' => $question->id]);
            if ($ddmarker) {
                $bg_files = self::get_question_files($question->id, 'bgimage');
                if (empty($bg_files)) {
                    $broken_links[] = (object)[
                        'field' => 'bgimage (drag and drop markers)',
                        'url' => 'Background image missing',
                        'reason' => 'Image de fond manquante pour drag and drop markers'
                    ];
                }
            }
        }
        
        // Pour les questions de type ddwtos (drag and drop into text)
        if ($qtype == 'ddwtos') {
            $answers = $DB->get_records('question_answers', ['question' => $question->id]);
            foreach ($answers as $answer) {
                $text_fields['ddwtos_answer_' . $answer->id] = $answer->answer;
            }
        }
        
        // Analyser tous les champs texte pour trouver les références aux fichiers
        foreach ($text_fields as $field_name => $text) {
            if (empty($text)) {
                continue;
            }
            
            // Rechercher les balises img
            $img_links = self::extract_image_links($text);
            foreach ($img_links as $link) {
                if (!self::verify_file_exists($link, $question->id)) {
                    $broken_links[] = (object)[
                        'field' => $field_name,
                        'url' => $link,
                        'reason' => 'Fichier image introuvable'
                    ];
                }
            }
            
            // Rechercher les liens vers pluginfile.php
            $plugin_files = self::extract_pluginfile_links($text);
            foreach ($plugin_files as $link) {
                if (!self::verify_pluginfile_exists($link, $question->id)) {
                    $broken_links[] = (object)[
                        'field' => $field_name,
                        'url' => $link,
                        'reason' => 'Fichier pluginfile introuvable'
                    ];
                }
            }
        }
        
        return $broken_links;
    }

    /**
     * Extrait les liens d'images depuis un texte HTML
     *
     * @param string $text Texte HTML
     * @return array Tableau d'URLs
     */
    private static function extract_image_links($text) {
        $links = [];
        
        // Rechercher les balises img avec src
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $text, $matches)) {
            $links = array_merge($links, $matches[1]);
        }
        
        return $links;
    }

    /**
     * Extrait les liens pluginfile.php depuis un texte
     *
     * @param string $text Texte HTML
     * @return array Tableau d'URLs
     */
    private static function extract_pluginfile_links($text) {
        $links = [];
        
        // Rechercher les liens vers pluginfile.php
        if (preg_match_all('/pluginfile\.php[^"\'\s]*/i', $text, $matches)) {
            $links = array_merge($links, $matches[0]);
        }
        
        return $links;
    }

    /**
     * Vérifie si un fichier existe
     *
     * @param string $url URL du fichier
     * @param int $questionid ID de la question
     * @return bool
     */
    private static function verify_file_exists($url, $questionid) {
        global $DB;
        
        // Si l'URL ne contient pas pluginfile.php, on considère que c'est un lien externe
        if (strpos($url, 'pluginfile.php') === false) {
            return true; // On ne vérifie pas les liens externes
        }
        
        return self::verify_pluginfile_exists($url, $questionid);
    }

    /**
     * Vérifie si un pluginfile existe
     *
     * @param string $url URL pluginfile
     * @param int $questionid ID de la question
     * @return bool
     */
    private static function verify_pluginfile_exists($url, $questionid) {
        global $DB;
        
        // Parser l'URL pour extraire le contenthash ou filename
        // Format typique: /pluginfile.php/contextid/component/filearea/itemid/filename
        
        // Extraire le filename de l'URL
        $parts = explode('/', $url);
        $filename = end($parts);
        
        if (empty($filename)) {
            return false;
        }
        
        // Rechercher le fichier dans mdl_files
        // On cherche les fichiers associés à cette question
        $fs = get_file_storage();
        $question_files = self::get_all_question_files($questionid);
        
        foreach ($question_files as $file) {
            if ($file->get_filename() === $filename) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Récupère tous les fichiers d'une question
     *
     * @param int $questionid ID de la question
     * @return array Tableau de stored_file
     */
    private static function get_all_question_files($questionid) {
        global $DB;
        
        $fs = get_file_storage();
        $files = [];
        
        // Récupérer le contexte de la question
        $question = $DB->get_record('question', ['id' => $questionid]);
        if (!$question) {
            return [];
        }
        
        // Check if category property exists
        if (!isset($question->category) || empty($question->category)) {
            return [];
        }
        
        $category = $DB->get_record('question_categories', ['id' => $question->category]);
        if (!$category) {
            return [];
        }
        
        try {
            $context = \context::instance_by_id($category->contextid, IGNORE_MISSING);
            if (!$context) {
                return [];
            }
            
            // Récupérer tous les fichiers dans les différentes zones possibles
            $fileareas = ['questiontext', 'generalfeedback', 'answer', 'answerfeedback', 'bgimage', 'correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback'];
            
            foreach ($fileareas as $filearea) {
                $area_files = $fs->get_area_files($context->id, 'question', $filearea, $questionid, 'filename', false);
                $files = array_merge($files, $area_files);
            }
            
        } catch (\Exception $e) {
            // En cas d'erreur, retourner un tableau vide
            return [];
        }
        
        return $files;
    }

    /**
     * Récupère les fichiers d'une question pour une zone spécifique
     *
     * @param int $questionid ID de la question
     * @param string $filearea Zone de fichier
     * @return array Tableau de stored_file
     */
    private static function get_question_files($questionid, $filearea) {
        global $DB;
        
        $fs = get_file_storage();
        
        $question = $DB->get_record('question', ['id' => $questionid]);
        if (!$question) {
            return [];
        }
        
        // Check if category property exists
        if (!isset($question->category) || empty($question->category)) {
            return [];
        }
        
        $category = $DB->get_record('question_categories', ['id' => $question->category]);
        if (!$category) {
            return [];
        }
        
        try {
            $context = \context::instance_by_id($category->contextid, IGNORE_MISSING);
            if (!$context) {
                return [];
            }
            
            $files = $fs->get_area_files($context->id, 'question', $filearea, $questionid, 'filename', false);
            return $files;
            
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Obtient les statistiques globales sur les liens cassés
     *
     * @return object Statistiques
     */
    public static function get_global_stats() {
        global $DB;
        
        $stats = new \stdClass();
        $stats->total_questions = $DB->count_records('question');
        
        $broken_questions = self::get_questions_with_broken_links();
        $stats->questions_with_broken_links = count($broken_questions);
        
        $total_broken_links = 0;
        foreach ($broken_questions as $item) {
            $total_broken_links += $item->broken_count;
        }
        $stats->total_broken_links = $total_broken_links;
        
        // Statistiques par type de question
        $stats->by_qtype = [];
        foreach ($broken_questions as $item) {
            $qtype = $item->question->qtype;
            if (!isset($stats->by_qtype[$qtype])) {
                $stats->by_qtype[$qtype] = 0;
            }
            $stats->by_qtype[$qtype]++;
        }
        
        return $stats;
    }

    /**
     * Génère l'URL pour accéder à une question dans la banque de questions
     *
     * @param object $question Objet question
     * @param object $category Objet catégorie
     * @return \moodle_url|null URL vers la banque de questions
     */
    public static function get_question_bank_url($question, $category) {
        if (!$category) {
            return null;
        }
        
        try {
            $context = \context::instance_by_id($category->contextid, IGNORE_MISSING);
            
            if (!$context) {
                return null;
            }
            
            $courseid = 0;
            
            if ($context->contextlevel == CONTEXT_COURSE) {
                $courseid = $context->instanceid;
            } else if ($context->contextlevel == CONTEXT_MODULE) {
                $coursecontext = $context->get_course_context(false);
                if ($coursecontext) {
                    $courseid = $coursecontext->instanceid;
                }
            }
            
            // URL vers la banque de questions avec filtre sur la catégorie
            $url = new \moodle_url('/question/edit.php', [
                'courseid' => $courseid,
                'cat' => $category->id . ',' . $category->contextid,
                'qid' => $question->id
            ]);
            
            return $url;
            
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Tente de réparer un lien cassé en cherchant un fichier similaire
     *
     * @param int $questionid ID de la question
     * @param string $field Champ contenant le lien
     * @param string $broken_url URL cassée
     * @return array ['success' => bool, 'message' => string, 'suggestions' => array]
     */
    public static function attempt_repair($questionid, $field, $broken_url) {
        global $DB;
        
        $result = [
            'success' => false,
            'message' => '',
            'suggestions' => []
        ];
        
        // Extraire le nom du fichier de l'URL cassée
        $parts = explode('/', $broken_url);
        $filename = end($parts);
        
        if (empty($filename)) {
            $result['message'] = 'Impossible d\'extraire le nom du fichier de l\'URL.';
            return $result;
        }
        
        // Chercher des fichiers similaires dans moodledata
        $similar_files = self::find_similar_files($filename, $questionid);
        
        if (empty($similar_files)) {
            $result['message'] = 'Aucun fichier similaire trouvé dans moodledata.';
            return $result;
        }
        
        $result['suggestions'] = $similar_files;
        $result['message'] = count($similar_files) . ' fichier(s) similaire(s) trouvé(s).';
        
        return $result;
    }

    /**
     * Cherche des fichiers similaires dans moodledata
     *
     * @param string $filename Nom du fichier recherché
     * @param int $questionid ID de la question
     * @return array Tableau de fichiers similaires
     */
    private static function find_similar_files($filename, $questionid) {
        global $DB;
        
        $fs = get_file_storage();
        $similar = [];
        
        // Rechercher par nom exact
        $files = $DB->get_records_sql("
            SELECT * FROM {files}
            WHERE filename = :filename
            AND filename != '.'
            ORDER BY timemodified DESC
            LIMIT 20
        ", ['filename' => $filename]);
        
        foreach ($files as $file_record) {
            $file = $fs->get_file_by_id($file_record->id);
            if ($file) {
                $similar[] = (object)[
                    'id' => $file->get_id(),
                    'filename' => $file->get_filename(),
                    'filepath' => $file->get_filepath(),
                    'filesize' => $file->get_filesize(),
                    'mimetype' => $file->get_mimetype(),
                    'timemodified' => $file->get_timemodified(),
                    'contenthash' => $file->get_contenthash()
                ];
            }
        }
        
        // Si aucun résultat exact, chercher par nom partiel
        if (empty($similar)) {
            $filename_pattern = '%' . $DB->sql_like_escape(pathinfo($filename, PATHINFO_FILENAME)) . '%';
            $files = $DB->get_records_sql("
                SELECT * FROM {files}
                WHERE " . $DB->sql_like('filename', ':pattern') . "
                AND filename != '.'
                ORDER BY timemodified DESC
                LIMIT 20
            ", ['pattern' => $filename_pattern]);
            
            foreach ($files as $file_record) {
                $file = $fs->get_file_by_id($file_record->id);
                if ($file) {
                    $similar[] = (object)[
                        'id' => $file->get_id(),
                        'filename' => $file->get_filename(),
                        'filepath' => $file->get_filepath(),
                        'filesize' => $file->get_filesize(),
                        'mimetype' => $file->get_mimetype(),
                        'timemodified' => $file->get_timemodified(),
                        'contenthash' => $file->get_contenthash()
                    ];
                }
            }
        }
        
        return $similar;
    }

    /**
     * Supprime une référence cassée d'une question
     *
     * @param int $questionid ID de la question
     * @param string $field Champ contenant le lien
     * @param string $broken_url URL cassée
     * @return bool|string true si succès, message d'erreur sinon
     */
    public static function remove_broken_link($questionid, $field, $broken_url) {
        global $DB;
        
        try {
            $question = $DB->get_record('question', ['id' => $questionid], '*', MUST_EXIST);
            
            // Déterminer quel champ modifier
            if (strpos($field, 'answer_') === 0) {
                // C'est une réponse
                $answer_id = str_replace('answer_', '', $field);
                $answer = $DB->get_record('question_answers', ['id' => $answer_id], '*', MUST_EXIST);
                $answer->answer = str_replace($broken_url, '[Image supprimée]', $answer->answer);
                $DB->update_record('question_answers', $answer);
            } else if (strpos($field, 'feedback_') === 0) {
                // C'est un feedback
                $answer_id = str_replace('feedback_', '', $field);
                $answer = $DB->get_record('question_answers', ['id' => $answer_id], '*', MUST_EXIST);
                $answer->feedback = str_replace($broken_url, '[Image supprimée]', $answer->feedback);
                $DB->update_record('question_answers', $answer);
            } else {
                // C'est un champ de la question elle-même
                if (isset($question->$field)) {
                    $question->$field = str_replace($broken_url, '[Image supprimée]', $question->$field);
                    $DB->update_record('question', $question);
                }
            }
            
            return true;
            
        } catch (\Exception $e) {
            return "Erreur lors de la suppression : " . $e->getMessage();
        }
    }
}

