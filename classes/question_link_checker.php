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
     * @param bool $use_cache Utiliser le cache (défaut: true)
     * @param int $limit Limite de questions à vérifier (0 = toutes, défaut: 1000)
     * @return array Tableau des questions avec détails des liens cassés
     */
    public static function get_questions_with_broken_links($use_cache = true, $limit = 1000) {
        global $DB;

        // Essayer le cache d'abord
        require_once(__DIR__ . '/cache_manager.php');
        if ($use_cache) {
            $cached_broken = cache_manager::get(cache_manager::CACHE_BROKENLINKS, 'broken_links_list');
            if ($cached_broken !== false) {
                return $cached_broken;
            }
        }

        // Limiter le nombre de questions pour éviter timeout/memory
        if ($limit > 0) {
            $questions = $DB->get_records('question', null, 'id DESC', '*', 0, $limit);
        } else {
            $questions = $DB->get_records('question', null, 'id DESC');
        }
        
        $broken = [];

        foreach ($questions as $question) {
            $broken_links = self::check_question_links($question);
            
            if (!empty($broken_links)) {
                // Récupérer la catégorie via question_bank_entries (Moodle 4.x)
                $category_sql = "SELECT qc.* 
                                FROM {question_categories} qc
                                INNER JOIN {question_bank_entries} qbe ON qbe.questioncategoryid = qc.id
                                INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                                WHERE qv.questionid = :questionid
                                LIMIT 1";
                $category = $DB->get_record_sql($category_sql, ['questionid' => $question->id]);
                
                $broken[] = (object)[
                    'question' => $question,
                    'category' => $category,
                    'broken_links' => $broken_links,
                    'broken_count' => count($broken_links)
                ];
            }
        }

        // Mettre en cache pour 1 heure
        if ($use_cache) {
            cache_manager::set(cache_manager::CACHE_BROKENLINKS, 'broken_links_list', $broken);
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
                // 🔧 FIX: Les fichiers bgimage pour ddimageortext sont stockés avec le composant qtype_ddimageortext
                // et l'itemid peut être différent du questionid (souvent le champ bgimage ou 0)
                $bg_files = self::get_dd_bgimage_files($question->id, 'qtype_ddimageortext', $ddimageortext->bgimage ?? 0);
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
                // 🔧 FIX: Les fichiers bgimage pour ddmarker sont stockés avec le composant qtype_ddmarker
                $bg_files = self::get_dd_bgimage_files($question->id, 'qtype_ddmarker', $ddmarker->bgimage ?? 0);
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
     * 🔧 AMÉLIORÉ v1.11.28 : Recherche étendue dans le contexte si l'URL exacte n'existe pas
     *
     * @param string $url URL pluginfile
     * @param int $questionid ID de la question
     * @return bool
     */
    private static function verify_pluginfile_exists($url, $questionid) {
        global $DB;
        
        // D'abord, vérifier l'URL exacte (méthode rapide)
        $info = self::parse_pluginfile_url($url);
        if ($info && self::pluginfile_url_exists_in_files($url)) {
            return true;
        }
        
        // Si l'URL exacte n'existe pas, chercher le fichier par nom dans le contexte
        // (le fichier peut exister mais avec une URL différente)
        $filename = self::extract_filename_from_url($url);
        if (empty($filename)) {
            return false;
        }
        
        // Recherche rapide : d'abord dans les fichiers de la question (méthode existante)
        $fs = get_file_storage();
        $question_files = self::get_all_question_files($questionid);
        
        foreach ($question_files as $file) {
            if ($file->get_filename() === $filename) {
                return true;
            }
        }
        
        // Si toujours rien, chercher dans le contexte étendu (plus lent mais plus complet)
        // On limite cette recherche pour éviter les performances dégradées
        $found_files = self::find_file_by_name_in_context($filename, $questionid);
        
        // Si on trouve au moins un fichier avec le même nom, 
        // on considère que le fichier existe (même si l'URL est incorrecte)
        return !empty($found_files);
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
        
        // Récupérer le contexte de la question via question_bank_entries (Moodle 4.x)
        $category_sql = "SELECT qc.* 
                        FROM {question_categories} qc
                        INNER JOIN {question_bank_entries} qbe ON qbe.questioncategoryid = qc.id
                        INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                        WHERE qv.questionid = :questionid
                        LIMIT 1";
        $category = $DB->get_record_sql($category_sql, ['questionid' => $questionid]);
        
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
        
        // Récupérer la catégorie via question_bank_entries (Moodle 4.x)
        $category_sql = "SELECT qc.* 
                        FROM {question_categories} qc
                        INNER JOIN {question_bank_entries} qbe ON qbe.questioncategoryid = qc.id
                        INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                        WHERE qv.questionid = :questionid
                        LIMIT 1";
        $category = $DB->get_record_sql($category_sql, ['questionid' => $questionid]);
        
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
     * Récupère les fichiers bgimage pour les questions drag and drop (ddmarker, ddimageortext)
     * 
     * Ces types de questions stockent les fichiers différemment :
     * - Composant: qtype_ddmarker ou qtype_ddimageortext (pas 'question')
     * - ItemID: peut être 0, questionid, ou la valeur du champ bgimage
     *
     * @param int $questionid ID de la question
     * @param string $component Composant (qtype_ddmarker ou qtype_ddimageortext)
     * @param int $bgimage_itemid ItemID depuis la table qtype_*
     * @return array Tableau de stored_file
     */
    private static function get_dd_bgimage_files($questionid, $component, $bgimage_itemid) {
        global $DB;
        
        $fs = get_file_storage();
        
        // Récupérer la catégorie via question_bank_entries (Moodle 4.x)
        $category_sql = "SELECT qc.* 
                        FROM {question_categories} qc
                        INNER JOIN {question_bank_entries} qbe ON qbe.questioncategoryid = qc.id
                        INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                        WHERE qv.questionid = :questionid
                        LIMIT 1";
        $category = $DB->get_record_sql($category_sql, ['questionid' => $questionid]);
        
        if (!$category) {
            return [];
        }
        
        try {
            $context = \context::instance_by_id($category->contextid, IGNORE_MISSING);
            if (!$context) {
                return [];
            }
            
            // Essayer plusieurs combinaisons pour trouver les fichiers bgimage
            $files = [];
            
            // Tentative 1 : Avec le composant spécifique et itemid du champ bgimage
            if ($bgimage_itemid > 0) {
                $files = $fs->get_area_files($context->id, $component, 'bgimage', $bgimage_itemid, 'filename', false);
            }
            
            // Tentative 2 : Si rien trouvé, essayer avec itemid = questionid
            if (empty($files)) {
                $files = $fs->get_area_files($context->id, $component, 'bgimage', $questionid, 'filename', false);
            }
            
            // Tentative 3 : Si toujours rien, essayer avec itemid = 0
            if (empty($files)) {
                $files = $fs->get_area_files($context->id, $component, 'bgimage', 0, 'filename', false);
            }
            
            // Tentative 4 : Fallback avec composant 'question' (anciennes versions)
            if (empty($files)) {
                $files = $fs->get_area_files($context->id, 'question', 'bgimage', $questionid, 'filename', false);
            }
            
            return $files;
            
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Obtient les statistiques globales sur les liens cassés
     *
     * @param bool $use_cache Utiliser le cache (défaut: true)
     * @return object Statistiques
     */
    public static function get_global_stats($use_cache = true) {
        global $DB;
        
        // Essayer le cache d'abord
        require_once(__DIR__ . '/cache_manager.php');
        if ($use_cache) {
            $cached_stats = cache_manager::get(cache_manager::CACHE_BROKENLINKS, 'global_stats');
            if ($cached_stats !== false) {
                return $cached_stats;
            }
        }
        
        $stats = new \stdClass();
        $stats->total_questions = $DB->count_records('question');
        
        $broken_questions = self::get_questions_with_broken_links($use_cache);
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
        
        // Mettre en cache pour 1 heure
        if ($use_cache) {
            cache_manager::set(cache_manager::CACHE_BROKENLINKS, 'global_stats', $stats);
        }
        
        return $stats;
    }
    
    /**
     * Purge le cache des liens cassés
     * 
     * 🔧 REFACTORED v1.9.27 : Utilise maintenant la classe CacheManager centralisée
     * @see \local_question_diagnostic\cache_manager::purge_cache()
     *
     * @return bool Succès de l'opération
     */
    public static function purge_broken_links_cache() {
        require_once(__DIR__ . '/cache_manager.php');
        return cache_manager::purge_cache(cache_manager::CACHE_BROKENLINKS);
    }

    /**
     * Génère l'URL pour accéder à une question dans la banque de questions
     *
     * 🔧 REFACTORED: Cette méthode utilise maintenant la fonction centralisée dans lib.php
     * @see local_question_diagnostic_get_question_bank_url()
     * 
     * @param object $question Objet question
     * @param object $category Objet catégorie
     * @return \moodle_url|null URL vers la banque de questions
     */
    public static function get_question_bank_url($question, $category) {
        if (!$category) {
            return null;
        }
        
        // Utiliser la fonction centralisée avec l'ID de la question
        return local_question_diagnostic_get_question_bank_url($category, $question->id);
    }

    /**
     * Cherche un fichier par son nom dans le contexte de la question et ses parents
     * 
     * 🔧 NOUVEAU v1.11.28 : Recherche multi-niveaux pour trouver les fichiers existants
     * 
     * @param string $filename Nom du fichier recherché
     * @param int $questionid ID de la question
     * @return array Tableau de fichiers trouvés avec leurs métadonnées, triés par priorité
     */
    private static function find_file_by_name_in_context($filename, $questionid) {
        global $DB;
        
        $fs = get_file_storage();
        $found_files = [];
        
        if (empty($filename) || $filename === '.') {
            return [];
        }
        
        // 1. Récupérer le contexte de la question
        $category_sql = "SELECT qc.* 
                        FROM {question_categories} qc
                        INNER JOIN {question_bank_entries} qbe ON qbe.questioncategoryid = qc.id
                        INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                        WHERE qv.questionid = :questionid
                        LIMIT 1";
        $category = $DB->get_record_sql($category_sql, ['questionid' => $questionid]);
        
        if (!$category) {
            return [];
        }
        
        try {
            $context = \context::instance_by_id($category->contextid, IGNORE_MISSING);
            if (!$context) {
                return [];
            }
            
            // 2. Chercher dans tous les fileareas du contexte de la question
            $fileareas = ['questiontext', 'generalfeedback', 'answer', 'answerfeedback', 
                         'bgimage', 'correctfeedback', 'partiallycorrectfeedback', 
                         'incorrectfeedback'];
            
            foreach ($fileareas as $filearea) {
                // Chercher dans plusieurs itemids possibles
                $itemids = [0, $questionid];
                
                foreach ($itemids as $itemid) {
                    $files = $fs->get_area_files(
                        $context->id, 
                        'question', 
                        $filearea, 
                        $itemid, 
                        'filename', 
                        false
                    );
                    
                    foreach ($files as $file) {
                        if ($file->get_filename() === $filename) {
                            $found_files[] = [
                                'file' => $file,
                                'priority' => 1, // Priorité haute : même contexte, filearea question
                                'contextid' => $context->id,
                                'component' => 'question',
                                'filearea' => $filearea,
                                'itemid' => $itemid
                            ];
                        }
                    }
                }
            }
            
            // 3. Si rien trouvé, chercher dans les contextes parents
            if (empty($found_files)) {
                $parent_contexts = $context->get_parent_contexts();
                foreach ($parent_contexts as $parent_context) {
                    // Chercher dans les fileareas question des contextes parents
                    foreach ($fileareas as $filearea) {
                        $files = $fs->get_area_files(
                            $parent_context->id,
                            'question',
                            $filearea,
                            0,
                            'filename',
                            false
                        );
                        
                        foreach ($files as $file) {
                            if ($file->get_filename() === $filename) {
                                $found_files[] = [
                                    'file' => $file,
                                    'priority' => 2, // Priorité moyenne : contexte parent
                                    'contextid' => $parent_context->id,
                                    'component' => 'question',
                                    'filearea' => $filearea,
                                    'itemid' => 0
                                ];
                            }
                        }
                    }
                }
            }
            
            // 4. Si toujours rien, chercher par filename dans toute la table files
            // (dernier recours, plus lent mais plus complet)
            if (empty($found_files)) {
                $sql = "SELECT f.* 
                        FROM {files} f
                        WHERE f.filename = :filename
                          AND f.filename != '.'
                          AND f.component = 'question'
                        ORDER BY f.timemodified DESC
                        LIMIT 10";
                
                $file_records = $DB->get_records_sql($sql, ['filename' => $filename]);
                
                foreach ($file_records as $file_record) {
                    try {
                        $file = $fs->get_file(
                            $file_record->contextid,
                            $file_record->component,
                            $file_record->filearea,
                            $file_record->itemid,
                            $file_record->filepath,
                            $file_record->filename
                        );
                        
                        if ($file) {
                            $found_files[] = [
                                'file' => $file,
                                'priority' => 3, // Priorité basse : autre contexte
                                'contextid' => $file_record->contextid,
                                'component' => $file_record->component,
                                'filearea' => $file_record->filearea,
                                'itemid' => $file_record->itemid
                            ];
                        }
                    } catch (\Exception $e) {
                        // Ignorer les erreurs de récupération de fichier
                        continue;
                    }
                }
            }
            
            // Trier par priorité (plus spécifique en premier)
            usort($found_files, function($a, $b) {
                return $a['priority'] <=> $b['priority'];
            });
            
            return $found_files;
            
        } catch (\Exception $e) {
            debugging('Erreur lors de la recherche de fichier : ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
    }

    /**
     * Génère l'URL pluginfile correcte pour un fichier
     * 
     * 🔧 NOUVEAU v1.11.28 : Génération d'URL pluginfile valide
     * 
     * @param object $file stored_file
     * @param int $contextid Context ID
     * @param string $component Component
     * @param string $filearea File area
     * @param int $itemid Item ID
     * @return string URL pluginfile
     */
    private static function generate_pluginfile_url($file, $contextid, $component, $filearea, $itemid) {
        global $CFG;
        
        $filepath = $file->get_filepath();
        $filename = $file->get_filename();
        
        // Construire l'URL pluginfile
        // Format: /pluginfile.php/contextid/component/filearea/itemid/filepath/filename
        $url = $CFG->wwwroot . '/pluginfile.php/' . 
               $contextid . '/' . 
               $component . '/' . 
               $filearea . '/' . 
               $itemid . 
               $filepath . 
               $filename;
        
        return $url;
    }

    /**
     * Tente de réparer un lien cassé en cherchant un fichier similaire
     * 
     * 🔧 AMÉLIORÉ v1.11.28 : Recherche de fichiers dans le contexte en plus des doublons stricts
     * 
     * @param int $questionid ID de la question
     * @param string $field Champ contenant le lien
     * @param string $broken_url URL cassée
     * @return array ['success' => bool, 'message' => string, 'suggestions' => array]
     */
    public static function attempt_repair($questionid, $field, $broken_url) {
        global $DB;

        $questionid = (int)$questionid;
        $field = (string)$field;
        $broken_url = (string)$broken_url;

        $result = [
            'success' => false,
            'message' => 'Aucune réparation automatique fiable n’a été trouvée.',
            'suggestions' => []
        ];

        // On ne tente pas de réparer des "URL" non pluginfile : la logique est basée sur la table files.
        if (strpos($broken_url, 'pluginfile.php') === false) {
            $result['message'] = 'Réparation non supportée : URL non pluginfile.';
            return $result;
        }

        try {
            $question = $DB->get_record('question', ['id' => $questionid], 'id,qtype,questiontext', MUST_EXIST);
        } catch (\Exception $e) {
            $result['message'] = 'Question introuvable.';
            return $result;
        }

        $suggestions = [];

        // 🔧 NOUVEAU v1.11.28 : Chercher le fichier par nom dans le contexte (priorité haute)
        $filename = self::extract_filename_from_url($broken_url);
        
        if (!empty($filename)) {
            $found_files = self::find_file_by_name_in_context($filename, $questionid);
            
            if (!empty($found_files)) {
                foreach ($found_files as $file_info) {
                    $file = $file_info['file'];
                    
                    // Déterminer le bon itemid selon le champ
                    $itemid = $questionid; // Par défaut
                    
                    // Pour les fileareas spécifiques, utiliser l'itemid approprié
                    if (strpos($field, 'answer_') === 0 || strpos($field, 'feedback_') === 0) {
                        // Pour les réponses, l'itemid peut être 0 ou questionid
                        $itemid = $file_info['itemid'] > 0 ? $file_info['itemid'] : $questionid;
                    } else {
                        // Pour les champs de question, utiliser l'itemid du fichier trouvé ou questionid
                        $itemid = $file_info['itemid'] > 0 ? $file_info['itemid'] : $questionid;
                    }
                    
                    // Générer l'URL correcte
                    $replacement_url = self::generate_pluginfile_url(
                        $file,
                        $file_info['contextid'],
                        $file_info['component'],
                        $file_info['filearea'],
                        $itemid
                    );
                    
                    // Vérifier que l'URL générée est valide
                    if (self::pluginfile_url_exists_in_files($replacement_url)) {
                        $priority_label = [
                            1 => 'même contexte',
                            2 => 'contexte parent',
                            3 => 'autre contexte'
                        ];
                        
                        $suggestions[] = [
                            'type' => 'file_found_in_context',
                            'confidence' => 90 - ($file_info['priority'] * 10), // 90%, 80%, 70%
                            'sourcequestionid' => null,
                            'sourcequestionname' => null,
                            'replacement_url' => $replacement_url,
                            'description' => 'Fichier trouvé dans le ' . ($priority_label[$file_info['priority']] ?? 'contexte') . 
                                           ' (priorité: ' . $file_info['priority'] . ')',
                            'file_info' => [
                                'contextid' => $file_info['contextid'],
                                'filearea' => $file_info['filearea'],
                                'itemid' => $itemid
                            ]
                        ];
                    }
                }
            }
        }

        // Heuristique complémentaire: chercher un doublon strict (même qtype + même questiontext),
        // puis récupérer une URL pluginfile valide pointant vers la même ressource (même filename).
        $duplicates = self::find_strict_duplicates($question, 10);
        if (!empty($duplicates)) {
            foreach ($duplicates as $dup) {
                $replacement = self::find_replacement_url_in_duplicate((int)$dup->id, $field, $broken_url);
                if (!$replacement) {
                    continue;
                }

                $suggestions[] = [
                    'type' => 'strict_duplicate',
                    'confidence' => 85,
                    'sourcequestionid' => (int)$dup->id,
                    'sourcequestionname' => (string)($dup->name ?? ''),
                    'replacement_url' => $replacement,
                    'description' => 'Doublon strict détecté : lien valide trouvé dans la question #' . (int)$dup->id
                ];

                // On propose au max quelques suggestions de doublons.
                if (count($suggestions) >= 5) {
                    break;
                }
            }
        }

        // Trier les suggestions par confiance décroissante
        usort($suggestions, function($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });

        if (empty($suggestions)) {
            $result['message'] = 'Aucun fichier correspondant trouvé dans le contexte ni via doublon strict.';
            return $result;
        }

        $result['success'] = true;
        $result['message'] = count($suggestions) . ' suggestion(s) trouvée(s).';
        $result['suggestions'] = array_slice($suggestions, 0, 5); // Limiter à 5 suggestions max
        return $result;
    }

    /**
     * Applique une réparation de type "remplacement d'URL" (ex: par URL d'un doublon strict).
     *
     * @param int $questionid
     * @param string $field
     * @param string $broken_url
     * @param string $replacement_url
     * @param int|null $sourcequestionid
     * @return bool|string
     */
    public static function replace_broken_link_with_url($questionid, $field, $broken_url, $replacement_url, $sourcequestionid = null) {
        global $DB;

        $questionid = (int)$questionid;
        $field = (string)$field;
        $broken_url = (string)$broken_url;
        $replacement_url = (string)$replacement_url;

        // Vérifier que l'URL de remplacement correspond à un fichier existant (sinon on remplace par une autre URL cassée).
        if (strpos($replacement_url, 'pluginfile.php') !== false) {
            if (!self::pluginfile_url_exists_in_files($replacement_url)) {
                return 'URL de remplacement invalide : fichier introuvable dans la table files.';
            }
        }

        try {
            $question = $DB->get_record('question', ['id' => $questionid], '*', MUST_EXIST);

            // Déterminer quel champ modifier (mêmes règles que remove_broken_link).
            if (strpos($field, 'answer_') === 0) {
                $answer_id = (int)str_replace('answer_', '', $field);
                $answer = $DB->get_record('question_answers', ['id' => $answer_id], '*', MUST_EXIST);
                $before = (string)$answer->answer;
                $after = str_replace($broken_url, $replacement_url, $before);
                if ($after === $before) {
                    return 'Aucune occurrence du lien n’a été trouvée (rien modifié).';
                }
                $answer->answer = $after;
                $DB->update_record('question_answers', $answer);
            } else if (strpos($field, 'feedback_') === 0) {
                $answer_id = (int)str_replace('feedback_', '', $field);
                $answer = $DB->get_record('question_answers', ['id' => $answer_id], '*', MUST_EXIST);
                $before = (string)$answer->feedback;
                $after = str_replace($broken_url, $replacement_url, $before);
                if ($after === $before) {
                    return 'Aucune occurrence du lien n’a été trouvée (rien modifié).';
                }
                $answer->feedback = $after;
                $DB->update_record('question_answers', $answer);
            } else {
                // Champ de la question.
                if (!is_string($field) || $field === '' || !property_exists($question, $field)) {
                    return 'Champ non supporté pour réparation : ' . s((string)$field);
                }
                $before = (string)$question->$field;
                $after = str_replace($broken_url, $replacement_url, $before);
                if ($after === $before) {
                    return 'Aucune occurrence du lien n’a été trouvée (rien modifié).';
                }
                $question->$field = $after;
                $DB->update_record('question', $question);
            }

            // Optionnel: audit/debug.
            if ($sourcequestionid) {
                // Ne pas supposer que lib.php est chargé (tâches planifiées, CLI, etc.).
                if (function_exists('local_question_diagnostic_debug_log')) {
                    local_question_diagnostic_debug_log(
                        '🔧 Broken link replaced using strict duplicate. question=' . $questionid .
                        ', source=' . (int)$sourcequestionid .
                        ', field=' . $field,
                        DEBUG_DEVELOPER
                    );
                }
            }

            return true;
        } catch (\Exception $e) {
            return 'Erreur lors de la réparation : ' . $e->getMessage();
        }
    }

    /**
     * Trouve des doublons stricts : même qtype + même questiontext (hors question courante).
     *
     * @param object $question Question record (id,qtype,questiontext)
     * @param int $limit
     * @return array
     */
    private static function find_strict_duplicates($question, $limit = 10) {
        global $DB;

        $limit = max(1, (int)$limit);
        try {
            // Comparaison stricte du champ questiontext.
            $sql = "SELECT id, name, qtype
                      FROM {question}
                     WHERE id <> :id
                       AND qtype = :qtype
                       AND questiontext = :questiontext
                  ORDER BY timemodified DESC, id DESC";
            return array_values($DB->get_records_sql($sql, [
                'id' => (int)$question->id,
                'qtype' => (string)$question->qtype,
                'questiontext' => (string)$question->questiontext,
            ], 0, $limit));
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Cherche une URL pluginfile valide dans une question doublon, correspondant au même filename que l'URL cassée.
     *
     * @param int $duplicate_questionid
     * @param string $field
     * @param string $broken_url
     * @return string|null
     */
    private static function find_replacement_url_in_duplicate($duplicate_questionid, $field, $broken_url) {
        global $DB;

        $duplicate_questionid = (int)$duplicate_questionid;
        $field = (string)$field;
        $broken_url = (string)$broken_url;

        $broken_filename = self::extract_filename_from_url($broken_url);
        if ($broken_filename === '') {
            return null;
        }

        try {
            if (strpos($field, 'answer_') === 0 || strpos($field, 'feedback_') === 0) {
                // On ne peut pas mapper 1:1 les answer_id entre doublons : on scanne toutes les réponses.
                $answers = $DB->get_records('question_answers', ['question' => $duplicate_questionid], 'id ASC', 'id,answer,feedback');
                foreach ($answers as $ans) {
                    $html = (strpos($field, 'feedback_') === 0) ? (string)$ans->feedback : (string)$ans->answer;
                    $urls = self::extract_urls_from_html($html);
                    foreach ($urls as $u) {
                        if (self::extract_filename_from_url($u) !== $broken_filename) {
                            continue;
                        }
                        if (strpos($u, 'pluginfile.php') !== false && self::pluginfile_url_exists_in_files($u)) {
                            return $u;
                        }
                    }
                }
                return null;
            }

            $q = $DB->get_record('question', ['id' => $duplicate_questionid], '*', MUST_EXIST);
            if (!property_exists($q, $field)) {
                return null;
            }
            $html = (string)$q->$field;
            $urls = self::extract_urls_from_html($html);

            foreach ($urls as $u) {
                if (self::extract_filename_from_url($u) !== $broken_filename) {
                    continue;
                }
                if (strpos($u, 'pluginfile.php') !== false && self::pluginfile_url_exists_in_files($u)) {
                    return $u;
                }
            }
        } catch (\Exception $e) {
            return null;
        }

        return null;
    }

    /**
     * Extrait les URLs pertinentes d'un HTML (img src + pluginfile occurrences).
     *
     * @param string $html
     * @return array
     */
    private static function extract_urls_from_html($html) {
        $html = (string)$html;
        if ($html === '') {
            return [];
        }

        $urls = [];

        // img src.
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $m)) {
            $urls = array_merge($urls, $m[1]);
        }

        // pluginfile occurrences.
        if (preg_match_all('/pluginfile\\.php[^"\'\\s]*/i', $html, $m2)) {
            $urls = array_merge($urls, $m2[0]);
        }

        // Dédupliquer.
        $urls = array_values(array_unique(array_filter($urls)));
        return $urls;
    }

    /**
     * Extrait le filename depuis une URL (avec ou sans querystring).
     *
     * @param string $url
     * @return string
     */
    private static function extract_filename_from_url($url) {
        $url = (string)$url;
        if ($url === '') {
            return '';
        }
        // Retirer querystring.
        $path = $url;
        $qpos = strpos($path, '?');
        if ($qpos !== false) {
            $path = substr($path, 0, $qpos);
        }
        // Si URL absolue, garder uniquement le path.
        $parsed = @parse_url($path);
        if (is_array($parsed) && !empty($parsed['path'])) {
            $path = (string)$parsed['path'];
        }
        $path = str_replace('\\', '/', $path);
        $base = basename($path);
        return (string)$base;
    }

    /**
     * Parse une URL pluginfile et retourne les composantes nécessaires pour {files}.
     *
     * @param string $url
     * @return array|null
     */
    private static function parse_pluginfile_url($url) {
        $url = (string)$url;
        if ($url === '' || strpos($url, 'pluginfile.php') === false) {
            return null;
        }

        // Enlever querystring.
        $qpos = strpos($url, '?');
        if ($qpos !== false) {
            $url = substr($url, 0, $qpos);
        }

        // Extraire la partie après pluginfile.php
        $pos = strpos($url, 'pluginfile.php');
        $after = substr($url, $pos + strlen('pluginfile.php'));
        $after = ltrim($after, '/');
        $parts = array_values(array_filter(explode('/', $after), 'strlen'));

        // Attendu: contextid/component/filearea/itemid/.../filename
        if (count($parts) < 5) {
            return null;
        }

        $contextid = (int)$parts[0];
        $component = (string)$parts[1];
        $filearea = (string)$parts[2];
        $itemid = (int)$parts[3];

        $filename = (string)$parts[count($parts) - 1];
        $pathparts = array_slice($parts, 4, -1);
        $filepath = '/';
        if (!empty($pathparts)) {
            $filepath = '/' . implode('/', $pathparts) . '/';
        }

        if ($contextid <= 0 || $component === '' || $filearea === '' || $filename === '') {
            return null;
        }

        return [
            'contextid' => $contextid,
            'component' => $component,
            'filearea' => $filearea,
            'itemid' => $itemid,
            'filepath' => $filepath,
            'filename' => $filename,
        ];
    }

    /**
     * Vérifie qu'une URL pluginfile pointe vers un enregistrement existant dans {files}.
     *
     * @param string $url
     * @return bool
     */
    private static function pluginfile_url_exists_in_files($url) {
        global $DB;
        $info = self::parse_pluginfile_url($url);
        if (!$info) {
            return false;
        }

        try {
            return $DB->record_exists('files', [
                'contextid' => $info['contextid'],
                'component' => $info['component'],
                'filearea' => $info['filearea'],
                'itemid' => $info['itemid'],
                'filepath' => $info['filepath'],
                'filename' => $info['filename'],
            ]);
        } catch (\Exception $e) {
            return false;
        }
    }

    // 🗑️ REMOVED v1.9.27 : find_similar_files() supprimée (code mort)
    // Cette méthode cherchait des fichiers similaires mais n'était jamais vraiment utilisée.
    // La fonctionnalité de réparation automatique reste à implémenter complètement.
    // Si besoin de réactiver, voir l'historique git ou le fichier attempt_repair() ligne 565.

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
            
            // Cas particuliers : certains "liens cassés" ne sont pas dans du HTML, mais dans des champs qtype_*.
            // Exemple: bgimage manquante pour ddimageortext / ddmarker.
            if (is_string($field) && stripos($field, 'bgimage') !== false) {
                if ($question->qtype === 'ddimageortext') {
                    // Champ bgimage dans la table qtype_ddimageortext.
                    if ($DB->record_exists('qtype_ddimageortext', ['questionid' => $questionid])) {
                        $DB->set_field('qtype_ddimageortext', 'bgimage', 0, ['questionid' => $questionid]);
                        return true;
                    }
                    return 'Aucun enregistrement trouvé dans qtype_ddimageortext pour cette question.';
                } else if ($question->qtype === 'ddmarker') {
                    // Champ bgimage dans la table qtype_ddmarker.
                    if ($DB->record_exists('qtype_ddmarker', ['questionid' => $questionid])) {
                        $DB->set_field('qtype_ddmarker', 'bgimage', 0, ['questionid' => $questionid]);
                        return true;
                    }
                    return 'Aucun enregistrement trouvé dans qtype_ddmarker pour cette question.';
                }
                // Autres qtypes : pas supporté (ne pas faire semblant de réussir).
                return 'Suppression automatique non supportée pour ce type de question (bgimage).';
            }

            // Déterminer quel champ modifier
            if (strpos($field, 'answer_') === 0) {
                // C'est une réponse
                $answer_id = str_replace('answer_', '', $field);
                $answer = $DB->get_record('question_answers', ['id' => $answer_id], '*', MUST_EXIST);
                $before = (string)$answer->answer;
                $after = str_replace($broken_url, '[Image supprimée]', $before);
                if ($after === $before) {
                    return 'Aucune occurrence du lien n\'a été trouvée dans la réponse (rien modifié).';
                }
                $answer->answer = $after;
                $DB->update_record('question_answers', $answer);
            } else if (strpos($field, 'feedback_') === 0) {
                // C'est un feedback
                $answer_id = str_replace('feedback_', '', $field);
                $answer = $DB->get_record('question_answers', ['id' => $answer_id], '*', MUST_EXIST);
                $before = (string)$answer->feedback;
                $after = str_replace($broken_url, '[Image supprimée]', $before);
                if ($after === $before) {
                    return 'Aucune occurrence du lien n\'a été trouvée dans le feedback (rien modifié).';
                }
                $answer->feedback = $after;
                $DB->update_record('question_answers', $answer);
            } else {
                // C'est un champ de la question elle-même
                if (!is_string($field) || $field === '' || !property_exists($question, $field)) {
                    return 'Champ non supporté pour suppression automatique : ' . s((string)$field);
                }

                $before = (string)$question->$field;
                $after = str_replace($broken_url, '[Image supprimée]', $before);
                if ($after === $before) {
                    return 'Aucune occurrence du lien n\'a été trouvée dans ce champ (rien modifié).';
                }
                $question->$field = $after;
                $DB->update_record('question', $question);
            }
            
            return true;
            
        } catch (\Exception $e) {
            return "Erreur lors de la suppression : " . $e->getMessage();
        }
    }
}

