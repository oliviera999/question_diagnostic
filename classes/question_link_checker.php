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
     * Tente de réparer un lien cassé en cherchant un fichier similaire
     * 
     * 🚧 FONCTIONNALITÉ INCOMPLÈTE v1.9.27
     * Cette méthode est un stub pour une future fonctionnalité de réparation automatique.
     * Actuellement, seule la suppression de lien est implémentée (@see remove_broken_link).
     * 
     * TODO pour implémentation complète :
     * - Recherche intelligente de fichiers similaires (par contenthash, nom, taille)
     * - Interface de remplacement de fichier (drag & drop)
     * - Prévisualisation du fichier avant remplacement
     * - Logs de toutes les réparations effectuées
     *
     * @param int $questionid ID de la question
     * @param string $field Champ contenant le lien
     * @param string $broken_url URL cassée
     * @return array ['success' => bool, 'message' => string, 'suggestions' => array]
     */
    public static function attempt_repair($questionid, $field, $broken_url) {
        // Fonctionnalité à implémenter
        $result = [
            'success' => false,
            'message' => 'Fonctionnalité de réparation automatique non encore implémentée.',
            'suggestions' => []
        ];
        
        return $result;
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

