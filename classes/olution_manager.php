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
require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/question_analyzer.php');
require_once(__DIR__ . '/ai_suggester.php');

/**
 * Gestionnaire des doublons Olution
 * 
 * Détecte les doublons de questions et les déplace vers les sous-catégories
 * de la catégorie de questions "Olution" (système)
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class olution_manager {
    /** @var array<int,int>|null Map catégorie_id => parent_id (cache requête) */
    private static $categoryparentmap = null;

    /** @var int|null Cache de l'ID de la catégorie racine Olution */
    private static $olutioncategoryid = null;

    /** @var int|null Cache de l'ID de la catégorie "référence" (commun sous Olution, sinon Olution) */
    private static $referencecategoryid = null;

    /** @var int|null Cache de l'ID de la catégorie "Question à trier" (sous commun) */
    private static $triagecategoryid = null;

    /** @var int|null Cache de l'ID de la catégorie "Catégories à trier" (sous commun) */
    private static $categoriestriagecategoryid = null;

    /** @var array<string,array>|null Cache map signature => target (triage) */
    private static $triagetargetmap = null;

    /** @var array<int,array>|null Cache catégories candidates pour auto-tri (dans commun/* hors triage) */
    private static $autosortcandidatecatscache = null;

    /** @var array<int,bool> Cache catégorie_id => is_in_olution */
    private static $inolutioncache = [];

    /** @var array<int,bool> Cache catégorie_id => is_in_reference */
    private static $inreferencecache = [];

    /** @var array<int,int> Cache catégorie_id => depth */
    private static $depthcache = [];

    /** @var array|null Cache des groupes Olution (non paginés) */
    private static $allolutionduplicategroupscache = null;

    /**
     * Construit une condition SQL "catégorie dans Olution" (racine ou sous-catégories),
     * avec paramètres associés.
     *
     * @param string $qcalias Alias SQL de {question_categories} (ex: 'qc')
     * @param array $params Params SQL (référence)
     * @param object|null $olutioncat Objet catégorie Olution (optionnel)
     * @return string Condition SQL
     */
    private static function build_in_olution_condition(string $qcalias, array &$params, $olutioncat = null): string {
        global $DB;

        $olutionid = self::get_olution_category_id();
        if ($olutionid <= 0) {
            // Condition impossible.
            $params['olutionid'] = 0;
            return "1=0";
        }

        $params['olutionid'] = $olutionid;

        // Essayer d'utiliser qc.path si disponible (évite une énorme clause IN).
        try {
            $cols = $DB->get_columns('question_categories');
            if (isset($cols['path'])) {
                if ($olutioncat === null) {
                    $olutioncat = local_question_diagnostic_find_olution_category();
                }
                if ($olutioncat && isset($olutioncat->path) && !empty($olutioncat->path)) {
                    // Descendants = path commence par "<path>/"
                    $params['olutionpath'] = rtrim($olutioncat->path, '/') . '/%';
                    return "({$qcalias}.id = :olutionid OR " . $DB->sql_like("{$qcalias}.path", ':olutionpath', false, false) . ")";
                }
            }
        } catch (\Exception $e) {
            // Fallback ci-dessous.
        }

        // Fallback: calculer la liste des descendants en PHP.
        $parentmap = self::get_category_parent_map();
        $children = [];
        foreach ($parentmap as $id => $parent) {
            if (!isset($children[$parent])) {
                $children[$parent] = [];
            }
            $children[$parent][] = $id;
        }

        $ids = [];
        $queue = [$olutionid];
        $seen = [];
        while (!empty($queue)) {
            $current = array_shift($queue);
            if (isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;
            $ids[] = $current;
            foreach (($children[$current] ?? []) as $childid) {
                $queue[] = (int)$childid;
            }
        }

        list($insql, $inparams) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'olcat');
        $params = array_merge($params, $inparams);
        return "{$qcalias}.id {$insql}";
    }

    /**
     * Normalise un label (insensible à la casse, accents, espaces).
     *
     * @param string $label
     * @return string
     */
    private static function normalize_label(string $label): string {
        $label = trim($label);
        // Normalisation robuste et compatible selon versions Moodle/PHP.
        // - Moodle 4.5+ : core_text::remove_accents()
        // - Versions plus anciennes : core_text::specialtoascii() ou textlib::specialtoascii()
        // - Dernier recours : iconv (si dispo), sinon strtolower()
        if (class_exists('\\core_text')) {
            if (method_exists('\\core_text', 'remove_accents')) {
                $label = \core_text::remove_accents($label);
            } else if (method_exists('\\core_text', 'specialtoascii')) {
                $label = \core_text::specialtoascii($label);
            } else if (function_exists('iconv')) {
                $translit = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $label);
                if ($translit !== false) {
                    $label = $translit;
                }
            }
            if (method_exists('\\core_text', 'strtolower')) {
                $label = \core_text::strtolower($label);
            } else {
                $label = strtolower($label);
            }
        } else if (class_exists('\\textlib') && method_exists('\\textlib', 'specialtoascii')) {
            $label = \textlib::specialtoascii($label);
            $label = strtolower($label);
        } else if (function_exists('iconv')) {
            $translit = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $label);
            if ($translit !== false) {
                $label = $translit;
            }
            $label = strtolower($label);
        } else {
            $label = strtolower($label);
        }
        $label = preg_replace('/\s+/', ' ', $label);
        return $label;
    }

    /**
     * Normalise un texte "libre" (titre, contenu) pour de la tokenisation :
     * - minuscules + suppression accents
     * - remplace la ponctuation par des espaces
     * - compacte les espaces
     *
     * @param string $text
     * @return string
     */
    private static function normalize_free_text(string $text): string {
        $text = self::normalize_label($text);
        // Remplacer tout sauf lettres/chiffres par des espaces.
        $text = preg_replace('/[^a-z0-9]+/i', ' ', $text);
        $text = preg_replace('/\s+/', ' ', (string)$text);
        return trim((string)$text);
    }

    /**
     * Tokenise un texte en mots uniques (avec stopwords FR/EN simples).
     *
     * @param string $text
     * @return string[] tokens uniques
     */
    private static function tokenize(string $text): array {
        $text = self::normalize_free_text($text);
        if ($text === '') {
            return [];
        }

        static $stop = null;
        if ($stop === null) {
            // Stopwords minimalistes (FR + EN) pour éviter de proposer "de", "the", etc.
            $stop = array_fill_keys([
                // FR
                'a','au','aux','avec','ce','ces','dans','de','des','du','elle','en','et','eux','il','je','la','le','les','leur','lui','ma','mais','me','meme','mes','moi',
                'mon','ne','nos','notre','nous','on','ou','par','pas','pour','qu','que','qui','sa','se','ses','son','sur','ta','te','tes','toi','ton','tu','un','une','vos','votre','vous',
                'c','d','l','m','n','s','t','y',
                // EN
                'a','an','and','are','as','at','be','by','for','from','has','have','i','in','is','it','its','of','on','or','that','the','their','them','there','this','to','was','were','with','you','your',
            ], true);
        }

        $parts = explode(' ', $text);
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '' || strlen($p) < 3) {
                continue;
            }
            if (isset($stop[$p])) {
                continue;
            }
            $out[$p] = true;
        }
        return array_keys($out);
    }

    /**
     * Propose un titre de nouvelle catégorie à partir des tokens d'une question.
     *
     * @param string[] $tokens
     * @return string
     */
    private static function propose_category_title_from_tokens(array $tokens): string {
        if (empty($tokens)) {
            return '';
        }
        // Trier par longueur puis alpha (favorise les mots "porteurs").
        usort($tokens, function(string $a, string $b): int {
            $la = strlen($a);
            $lb = strlen($b);
            if ($la !== $lb) {
                return $lb <=> $la;
            }
            return strcmp($a, $b);
        });

        $picked = array_slice($tokens, 0, 3);
        $picked = array_map(function(string $w): string {
            // Re-capitaliser légèrement (sans accents, mais OK pour une proposition).
            return ucfirst($w);
        }, $picked);
        return implode(' - ', $picked);
    }

    /**
     * Calcule un score de similarité "question → catégorie" (0..1).
     *
     * @param string $qname
     * @param string $qtext
     * @param array $catinfo {id:int,name:string,label:string,tokens:string[]}
     * @return float
     */
    private static function compute_text_similarity_score(string $qname, string $qtext, array $catinfo): float {
        $qname = self::normalize_free_text($qname);
        $qtext = self::normalize_free_text($qtext);

        $qtokens = self::tokenize($qname . ' ' . $qtext);
        $ctokens = (array)($catinfo['tokens'] ?? []);
        $ctokens = array_values(array_unique(array_filter($ctokens)));

        $overlap = 0.0;
        if (!empty($qtokens) && !empty($ctokens)) {
            $qset = array_fill_keys($qtokens, true);
            $inter = 0;
            foreach ($ctokens as $t) {
                if (isset($qset[$t])) {
                    $inter++;
                }
            }
            // Rappel côté catégorie : "tous les mots de la catégorie sont trouvés dans la question ?"
            $overlap = $inter / max(1, count($ctokens));
        }

        $sim = 0.0;
        $catlabel = self::normalize_free_text((string)($catinfo['label'] ?? $catinfo['name'] ?? ''));
        if ($qname !== '' && $catlabel !== '') {
            $pct = 0.0;
            similar_text($qname, $catlabel, $pct);
            $sim = max(0.0, min(1.0, (float)$pct / 100.0));
        }

        $bonus = 0.0;
        if ($catlabel !== '' && $qtext !== '' && strpos($qtext, $catlabel) !== false) {
            $bonus = 0.15;
        }

        $score = (0.65 * $overlap) + (0.35 * $sim) + $bonus;
        if ($score < 0.0) {
            $score = 0.0;
        } else if ($score > 1.0) {
            $score = 1.0;
        }
        return $score;
    }

    /**
     * Construit une condition SQL "catégorie dans un sous-arbre" (racine + descendants),
     * avec paramètres associés.
     *
     * Utilise qc.path si dispo, sinon fallback via liste IN (calculée en PHP).
     *
     * @param string $qcalias Alias SQL de {question_categories} (ex: 'qc')
     * @param array $params Params SQL (référence)
     * @param int $rootid ID de la catégorie racine
     * @param string $prefix Préfixe unique pour les paramètres (évite collisions)
     * @param object|null $rootcat Objet catégorie (optionnel) pour accéder à path
     * @return string Condition SQL
     */
    private static function build_in_category_tree_condition(string $qcalias, array &$params, int $rootid, string $prefix, $rootcat = null): string {
        global $DB;

        $rootid = (int)$rootid;
        if ($rootid <= 0) {
            $params[$prefix . 'id'] = 0;
            return '1=0';
        }

        $params[$prefix . 'id'] = $rootid;

        // Essayer d'utiliser qc.path si disponible.
        try {
            $cols = $DB->get_columns('question_categories');
            if (isset($cols['path'])) {
                if ($rootcat === null) {
                    $rootcat = $DB->get_record('question_categories', ['id' => $rootid], '*', IGNORE_MISSING);
                }
                if ($rootcat && isset($rootcat->path) && !empty($rootcat->path)) {
                    $params[$prefix . 'path'] = rtrim($rootcat->path, '/') . '/%';
                    return "({$qcalias}.id = :" . $prefix . "id OR " . $DB->sql_like("{$qcalias}.path", ':' . $prefix . 'path', false, false) . ')';
                }
            }
        } catch (\Exception $e) {
            // Fallback ci-dessous.
        }

        // Fallback: liste IN via parcours en PHP.
        $parentmap = self::get_category_parent_map();
        $children = [];
        foreach ($parentmap as $id => $parent) {
            if (!isset($children[$parent])) {
                $children[$parent] = [];
            }
            $children[$parent][] = $id;
        }

        $ids = [];
        $queue = [$rootid];
        $seen = [];
        while (!empty($queue)) {
            $current = array_shift($queue);
            if (isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;
            $ids[] = (int)$current;
            foreach (($children[$current] ?? []) as $childid) {
                $queue[] = (int)$childid;
            }
        }

        list($insql, $inparams) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, $prefix);
        $params = array_merge($params, $inparams);
        return "{$qcalias}.id {$insql}";
    }

    /**
     * Retourne l'ID de la catégorie "référence" pour la comparaison :
     * - si une catégorie enfant nommée exactement "commun" existe sous Olution, on la prend (et ses descendants)
     * - sinon, on retombe sur Olution (comportement historique).
     *
     * Objectif : sur certains sites, la "source de vérité" des questions est dans Olution/commun/*,
     * et non dans toutes les sous-catégories d'Olution.
     *
     * @param object|null $olutioncat Objet catégorie Olution (optionnel)
     * @return int ID catégorie racine du scope de comparaison
     */
    private static function get_reference_category_id($olutioncat = null): int {
        global $DB;

        if (self::$referencecategoryid !== null) {
            return (int)self::$referencecategoryid;
        }

        $olutionid = self::get_olution_category_id();
        if ($olutionid <= 0) {
            self::$referencecategoryid = 0;
            return 0;
        }

        // Chercher une sous-catégorie directe "commun" (case-insensitive).
        try {
            $children = $DB->get_records('question_categories', ['parent' => $olutionid], 'id ASC', 'id,name,parent');
            foreach ($children as $child) {
                if (self::normalize_label((string)$child->name) === 'commun') {
                    self::$referencecategoryid = (int)$child->id;
                    return (int)self::$referencecategoryid;
                }
            }
        } catch (\Exception $e) {
            // Fallback ci-dessous.
        }

        // Fallback : pas de "commun" détecté → Olution.
        self::$referencecategoryid = (int)$olutionid;
        return (int)self::$referencecategoryid;
    }

    /**
     * Retourne l'ID de la sous-catégorie "Question à trier" sous "commun" (si présente).
     *
     * @return int ID catégorie de questions "Question à trier", ou 0 si indisponible
     */
    private static function get_triage_category_id(): int {
        global $DB;

        if (self::$triagecategoryid !== null) {
            return (int)self::$triagecategoryid;
        }

        // Le triage n'est disponible que si "commun" existe (référence != Olution).
        $olutionid = self::get_olution_category_id();
        $refid = self::get_reference_category_id();

        if ($refid <= 0 || $olutionid <= 0 || (int)$refid === (int)$olutionid) {
            self::$triagecategoryid = 0;
            return 0;
        }

        $needle = self::normalize_label('Question à trier');
        $needle2 = self::normalize_label('Questions à trier');

        try {
            $children = $DB->get_records('question_categories', ['parent' => $refid], 'id ASC', 'id,name,parent');
            foreach ($children as $child) {
                $name = self::normalize_label((string)$child->name);
                if ($name === $needle || $name === $needle2) {
                    self::$triagecategoryid = (int)$child->id;
                    return (int)self::$triagecategoryid;
                }
            }
        } catch (\Exception $e) {
            // Fallback ci-dessous.
        }

        self::$triagecategoryid = 0;
        return 0;
    }

    /**
     * Retourne l'ID de la sous-catégorie "Catégories à trier" sous "commun" (si présente).
     *
     * Objectif : servir de "bac" pour ranger des catégories / contenus à traiter.
     *
     * @return int ID catégorie de questions "Catégories à trier", ou 0 si indisponible
     */
    private static function get_categories_triage_category_id(): int {
        global $DB;

        if (self::$categoriestriagecategoryid !== null) {
            return (int)self::$categoriestriagecategoryid;
        }

        $olutionid = self::get_olution_category_id();
        $refid = self::get_reference_category_id();
        if ($refid <= 0 || $olutionid <= 0 || (int)$refid === (int)$olutionid) {
            self::$categoriestriagecategoryid = 0;
            return 0;
        }

        $needle = self::normalize_label('Catégories à trier');
        $needle2 = self::normalize_label('Categories a trier');
        $needle3 = self::normalize_label('Catégorie à trier');
        $needle4 = self::normalize_label('Categories à trier');

        try {
            $children = $DB->get_records('question_categories', ['parent' => $refid], 'id ASC', 'id,name,parent');
            foreach ($children as $child) {
                $name = self::normalize_label((string)$child->name);
                if ($name === $needle || $name === $needle2 || $name === $needle3 || $name === $needle4) {
                    self::$categoriestriagecategoryid = (int)$child->id;
                    return (int)self::$categoriestriagecategoryid;
                }
            }
        } catch (\Exception $e) {
            // Fallback ci-dessous.
        }

        self::$categoriestriagecategoryid = 0;
        return 0;
    }

    /**
     * Récupère l'objet catégorie "Catégories à trier" (sous commun), si présent.
     *
     * @return object|false
     */
    public static function get_categories_triage_category() {
        global $DB;
        $id = self::get_categories_triage_category_id();
        if ($id <= 0) {
            return false;
        }
        return $DB->get_record('question_categories', ['id' => $id], '*', IGNORE_MISSING) ?: false;
    }

    /**
     * Récupère l'objet catégorie "Question à trier" (sous commun), si présent.
     *
     * @return object|false
     */
    public static function get_triage_category() {
        global $DB;

        $triageid = self::get_triage_category_id();
        if ($triageid <= 0) {
            return false;
        }

        return $DB->get_record('question_categories', ['id' => $triageid], '*', IGNORE_MISSING) ?: false;
    }

    /**
     * Retourne la catégorie de référence (commun sous Olution si présent, sinon Olution).
     *
     * @return object|false
     */
    public static function get_reference_category() {
        global $DB;
        $refid = self::get_reference_category_id();
        if ($refid <= 0) {
            return false;
        }
        return $DB->get_record('question_categories', ['id' => $refid], '*', IGNORE_MISSING) ?: false;
    }

    /**
     * Compte le nombre d'entrées de banque de questions (qbe) dans "Question à trier" (sous-arbre).
     * (Moodle 4.x : on compte les entries, pas directement `question.category`.)
     *
     * @return int
     */
    public static function get_triage_total_entries_count(): int {
        global $DB;

        $triage = self::get_triage_category();
        if (!$triage) {
            return 0;
        }
        $params = [];
        $triagecond = self::build_in_category_tree_condition('qc', $params, (int)$triage->id, 'tri', $triage);

        $sql = "SELECT COUNT(DISTINCT qbe.id)
                  FROM {question_bank_entries} qbe
                  INNER JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                 WHERE {$triagecond}";

        try {
            return (int)$DB->count_records_sql($sql, $params);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Récupère (et met en cache) la liste des catégories candidates pour l'auto-tri :
     * - dans le scope de référence (commun sous Olution si présent)
     * - excluant le sous-arbre "Question à trier"
     * - excluant la racine de référence elle-même
     *
     * @return array<int,array{cat:object,label:string,tokens:string[]}>
     */
    private static function get_autosort_candidate_categories(): array {
        global $DB;

        if (self::$autosortcandidatecatscache !== null) {
            return (array)self::$autosortcandidatecatscache;
        }
        self::$autosortcandidatecatscache = [];

        $olution = local_question_diagnostic_find_olution_category();
        $triage = self::get_triage_category();
        $ref = self::get_reference_category();
        if (!$olution || !$triage || !$ref) {
            return (array)self::$autosortcandidatecatscache;
        }

        // Le triage n'a de sens que si la référence est "commun" (et non Olution).
        if ((int)$ref->id === (int)$olution->id) {
            return (array)self::$autosortcandidatecatscache;
        }

        $params = [];
        $refcond = self::build_in_reference_condition('qc', $params, $olution);
        $triagecond = self::build_in_category_tree_condition('qc', $params, (int)$triage->id, 'tri', $triage);

        $sql = "SELECT qc.id, qc.name, qc.parent, qc.contextid
                  FROM {question_categories} qc
                 WHERE {$refcond}
                   AND qc.id <> :refid
                   AND NOT ({$triagecond})
              ORDER BY qc.name ASC, qc.id ASC";

        try {
            $records = $DB->get_records_sql($sql, $params + ['refid' => (int)$ref->id]);
        } catch (\Exception $e) {
            $records = [];
        }

        if (empty($records)) {
            return (array)self::$autosortcandidatecatscache;
        }

        // Filtrer vers des feuilles (réduit le bruit + améliore performance).
        $idset = [];
        foreach ($records as $r) {
            $idset[(int)$r->id] = true;
        }
        $haschild = [];
        foreach ($records as $r) {
            $pid = (int)$r->parent;
            if ($pid > 0 && isset($idset[$pid])) {
                $haschild[$pid] = true;
            }
        }

        $out = [];
        foreach ($records as $r) {
            $cid = (int)$r->id;
            // Conserver feuilles et aussi quelques catégories intermédiaires "porteuses" (si pas feuille, ignorer).
            if (isset($haschild[$cid])) {
                continue;
            }

            $crumb = local_question_diagnostic_get_question_category_breadcrumb($cid);
            $label = trim($crumb) !== '' ? $crumb : (string)$r->name;
            $tokens = self::tokenize($label);
            $out[$cid] = [
                'cat' => $r,
                'label' => $label,
                'tokens' => $tokens,
            ];
        }

        self::$autosortcandidatecatscache = $out;
        return (array)self::$autosortcandidatecatscache;
    }

    /**
     * Retourne une page d'entrées/questions de "Question à trier" avec suggestion de cible
     * basée sur la similarité (titre + contenu) vers une catégorie existante dans commun/*.
     *
     * @param int $limit
     * @param int $offset
     * @param int|null $total (OUT) total d'entrées dans triage
     * @param float $minscore Seuil d'acceptation "match trouvé"
     * @param string $mode 'heuristic' (défaut) ou 'ai' (si sous-système IA Moodle dispo)
     * @return array<int,array{
     *   bankentryid:int,
     *   question:object,
     *   source_category:object,
     *   best_target:?object,
     *   best_score:float,
     *   alternatives:array<int,array{cat:object,score:float}>,
     *   proposed_new_category:string,
     *   mode:string,
     *   ai_reason:string
     * }>
     */
    public static function get_triage_auto_sort_candidates_paginated(
        int $limit,
        int $offset,
        &$total = null,
        float $minscore = 0.30,
        string $mode = 'heuristic'
    ): array {
        global $DB;

        $triage = self::get_triage_category();
        if (!$triage) {
            $total = 0;
            return [];
        }

        $candidates = self::get_autosort_candidate_categories();
        if (empty($candidates)) {
            $total = 0;
            return [];
        }

        $limit = max(10, min(200, (int)$limit));
        $offset = max(0, (int)$offset);
        $minscore = max(0.0, min(1.0, (float)$minscore));
        $mode = ($mode === 'ai') ? 'ai' : 'heuristic';

        $params = [];
        $triagecond = self::build_in_category_tree_condition('qc', $params, (int)$triage->id, 'tri', $triage);

        // Total distinct qbe dans triage.
        $sqlcount = "SELECT COUNT(DISTINCT qbe.id)
                       FROM {question_bank_entries} qbe
                       INNER JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                      WHERE {$triagecond}";
        try {
            $total = (int)$DB->count_records_sql($sqlcount, $params);
        } catch (\Exception $e) {
            $total = 0;
        }
        if ((int)$total === 0) {
            return [];
        }

        // Récupérer une "version courante" par entry (max(version)).
        $sql = "SELECT qbe.id AS bankentryid,
                       q.id AS questionid,
                       q.name,
                       q.qtype,
                       q.questiontext,
                       q.questiontextformat,
                       q.timemodified,
                       qc.id AS categoryid,
                       qc.name AS categoryname,
                       qc.contextid AS categorycontextid
                  FROM {question_bank_entries} qbe
                  INNER JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                  INNER JOIN (
                      SELECT qv.questionbankentryid, MAX(qv.version) AS maxversion
                        FROM {question_versions} qv
                    GROUP BY qv.questionbankentryid
                  ) qvm ON qvm.questionbankentryid = qbe.id
                  INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id AND qv.version = qvm.maxversion
                  INNER JOIN {question} q ON q.id = qv.questionid
                 WHERE {$triagecond}
              ORDER BY qbe.id DESC";

        $rows = $DB->get_records_sql($sql, $params, $offset, $limit);
        if (empty($rows)) {
            return [];
        }

        // Préparer labels candidats et mapping index => catégorie.
        $candidateLabels = [];
        $candidateByIndex = [];
        foreach (array_values($candidates) as $i => $catinfo) {
            $idx = $i + 1;
            $candidateLabels[] = (string)$catinfo['label'];
            $candidateByIndex[$idx] = $catinfo['cat'];
        }

        // Cache MUC pour suggestions IA (réduit coût + latence).
        $aicache = null;
        if ($mode === 'ai') {
            try {
                if (class_exists('\\cache')) {
                    $aicache = \cache::make('local_question_diagnostic', 'ai_suggestions');
                }
            } catch (\Throwable $t) {
                $aicache = null;
            }
        }
        $cathash = '';
        if ($mode === 'ai') {
            $cathash = substr(sha1(implode("\n", $candidateLabels)), 0, 10);
        }

        $out = [];
        foreach ($rows as $r) {
            $qid = (int)$r->questionid;
            $qname = (string)($r->name ?? '');

            // Convertir questiontext HTML → texte brut, et limiter (perf).
            $qtext = (string)($r->questiontext ?? '');
            $qtext = html_entity_decode($qtext, ENT_QUOTES | ENT_HTML5);
            $qtext = trim(strip_tags($qtext));
            if (strlen($qtext) > 4000) {
                $qtext = substr($qtext, 0, 4000);
            }

            $scores = [];
            $bestcat = null;
            $bestscore = 0.0;
            $proposed = '';
            $aireason = '';
            $usedmode = $mode;

            // Mode IA (si disponible) : demander à Moodle IA de choisir parmi les labels.
            if ($usedmode === 'ai' && ai_suggester::is_available()) {
                $cachekey = 'autosort_q' . $qid
                    . '_t' . (int)($r->timemodified ?? 0)
                    . '_tri' . (int)$triage->id
                    . '_c' . $cathash;
                $ai = false;
                if ($aicache !== null) {
                    $ai = $aicache->get($cachekey);
                }
                if (!is_array($ai) || (($ai['status'] ?? '') !== 'ok')) {
                    $ai = ai_suggester::suggest($qname, $qtext, $candidateLabels);
                    // Ne mettre en cache que les réponses OK, pour ne pas figer un état "unavailable/error"
                    // après une correction de configuration côté Moodle/OpenAI.
                    if ($aicache !== null && is_array($ai) && ($ai['status'] ?? '') === 'ok') {
                        $aicache->set($cachekey, $ai);
                    }
                }

                if (is_array($ai) && ($ai['status'] ?? '') === 'ok') {
                    $choice = (int)($ai['choice_index'] ?? 0);
                    $conf = (float)($ai['confidence'] ?? 0.0);
                    $aireason = (string)($ai['reason'] ?? '');

                    if ($choice > 0 && isset($candidateByIndex[$choice])) {
                        $bestcat = $candidateByIndex[$choice];
                        $bestscore = max(0.0, min(1.0, $conf));
                    } else {
                        $proposed = trim((string)($ai['new_category'] ?? ''));
                    }
                } else {
                    // IA indisponible → fallback heuristique (sans bloquer la page).
                    $usedmode = 'heuristic';
                    if (is_array($ai)) {
                        $aireason = 'AI unavailable: ' . (string)($ai['message'] ?? ($ai['status'] ?? 'unknown'));
                        if (!empty($ai['debug']) && is_array($ai['debug'])) {
                            $aireason .= ' [' . json_encode($ai['debug']) . ']';
                        }
                    } else {
                        $aireason = 'AI unavailable';
                    }
                }
            }

            // Mode heuristique (fallback).
            if ($usedmode === 'heuristic') {
                foreach ($candidates as $cid => $catinfo) {
                    $cat = $catinfo['cat'];
                    $score = self::compute_text_similarity_score($qname, $qtext, [
                        'id' => (int)$cat->id,
                        'name' => (string)$cat->name,
                        'label' => (string)$catinfo['label'],
                        'tokens' => (array)$catinfo['tokens'],
                    ]);
                    if ($score <= 0.0) {
                        continue;
                    }
                    $scores[] = [
                        'cat' => $cat,
                        'score' => $score,
                    ];
                }

                usort($scores, function(array $a, array $b): int {
                    $sa = (float)$a['score'];
                    $sb = (float)$b['score'];
                    if ($sa === $sb) {
                        return ((int)$a['cat']->id) <=> ((int)$b['cat']->id);
                    }
                    return ($sb <=> $sa);
                });

                $best = $scores[0] ?? null;
                $bestcat = $best ? ($best['cat'] ?? null) : null;
                $bestscore = $best ? (float)$best['score'] : 0.0;

                $qtokens = self::tokenize($qname . ' ' . $qtext);
                if (!$bestcat || $bestscore < $minscore) {
                    $proposed = self::propose_category_title_from_tokens($qtokens);
                }
            }

            $out[] = [
                'bankentryid' => (int)$r->bankentryid,
                'question' => (object)[
                    'id' => $qid,
                    'name' => $r->name,
                    'qtype' => $r->qtype,
                    'questiontext' => $qtext,
                ],
                'source_category' => (object)[
                    'id' => (int)$r->categoryid,
                    'name' => $r->categoryname,
                    'contextid' => (int)$r->categorycontextid,
                ],
                'best_target' => ($bestcat && $bestscore >= $minscore) ? $bestcat : null,
                'best_score' => $bestscore,
                'alternatives' => array_slice($scores, 0, 3),
                'proposed_new_category' => $proposed,
                'mode' => $usedmode,
                'ai_reason' => $aireason,
            ];
        }

        return $out;
    }

    /**
     * Construit une clé stable pour un "doublon" (name + qtype).
     *
     * @param string $name
     * @param string $qtype
     * @return string
     */
    private static function signature_key(string $name, string $qtype): string {
        $name = trim($name);
        if (class_exists('\\core_text')) {
            $name = \core_text::strtolower($name);
        } else {
            $name = strtolower($name);
        }
        return $name . '|' . trim($qtype);
    }

    /**
     * Calcule (et met en cache) la map signature => catégorie cible (dans commun/* hors "Question à trier").
     *
     * @return array<string,array{targetcatid:int,count:int,depth:int}>
     */
    private static function get_triage_target_map(): array {
        global $DB;

        if (self::$triagetargetmap !== null) {
            return (array)self::$triagetargetmap;
        }

        self::$triagetargetmap = [];

        $olution = local_question_diagnostic_find_olution_category();
        if (!$olution) {
            return (array)self::$triagetargetmap;
        }

        $triage = self::get_triage_category();
        if (!$triage) {
            return (array)self::$triagetargetmap;
        }

        $params = [];
        $triagecond = self::build_in_category_tree_condition('qc', $params, (int)$triage->id, 'tri', $triage);

        // Candidats "ailleurs dans commun/*" = scope de référence, en excluant le sous-arbre triage.
        $refparams = $params;
        $refcond = self::build_in_reference_condition('qc2', $refparams, $olution);
        $triagecond2 = self::build_in_category_tree_condition('qc2', $refparams, (int)$triage->id, 'tri2', $triage);

        // Subquery: signatures présentes dans "Question à trier".
        $signaturesql = "SELECT DISTINCT q.name, q.qtype
                           FROM {question} q
                           INNER JOIN {question_versions} qv ON qv.questionid = q.id
                           INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                           INNER JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                          WHERE {$triagecond}";

        // Rechercher, pour chaque signature, dans quelles catégories (hors triage) existent des doublons dans le scope de référence.
        $sql = "SELECT tg.name,
                       tg.qtype,
                       qc2.id AS targetcatid,
                       COUNT(DISTINCT q2.id) AS cnt
                  FROM ({$signaturesql}) tg
                  JOIN {question} q2 ON q2.name = tg.name AND q2.qtype = tg.qtype
                  INNER JOIN {question_versions} qv2 ON qv2.questionid = q2.id
                  INNER JOIN {question_bank_entries} qbe2 ON qbe2.id = qv2.questionbankentryid
                  INNER JOIN {question_categories} qc2 ON qc2.id = qbe2.questioncategoryid
                 WHERE {$refcond}
                   AND NOT ({$triagecond2})
              GROUP BY tg.name, tg.qtype, qc2.id
              ORDER BY tg.name ASC, tg.qtype ASC, cnt DESC, qc2.id ASC";

        try {
            $rs = $DB->get_recordset_sql($sql, $refparams);
            foreach ($rs as $rec) {
                $key = self::signature_key((string)$rec->name, (string)$rec->qtype);
                $catid = (int)$rec->targetcatid;
                $cnt = (int)$rec->cnt;
                $depth = self::get_category_depth($catid);

                $current = self::$triagetargetmap[$key] ?? null;
                if ($current === null
                    || $cnt > (int)$current['count']
                    || ($cnt === (int)$current['count'] && $depth > (int)$current['depth'])
                    || ($cnt === (int)$current['count'] && $depth === (int)$current['depth'] && $catid < (int)$current['targetcatid'])) {
                    self::$triagetargetmap[$key] = [
                        'targetcatid' => $catid,
                        'count' => $cnt,
                        'depth' => $depth
                    ];
                }
            }
            $rs->close();
        } catch (\Exception $e) {
            local_question_diagnostic_debug_log('Error computing triage target map: ' . $e->getMessage(), DEBUG_DEVELOPER);
            self::$triagetargetmap = [];
        }

        return (array)self::$triagetargetmap;
    }

    /**
     * Statistiques rapides pour le triage (questions dans "Question à trier" ayant une correspondance ailleurs dans commun/*).
     *
     * @return object {triage_exists:bool, triage_name:string, triage_id:int, movable_questions:int, signatures:int}
     */
    public static function get_triage_stats(): object {
        $stats = (object)[
            'triage_exists' => false,
            'triage_name' => '',
            'triage_id' => 0,
            'movable_questions' => 0,
            'signatures' => 0,
        ];

        $triage = self::get_triage_category();
        if (!$triage) {
            return $stats;
        }

        $stats->triage_exists = true;
        $stats->triage_name = (string)$triage->name;
        $stats->triage_id = (int)$triage->id;

        $map = self::get_triage_target_map();
        $stats->signatures = count($map);

        // Estimer/compter les questions "déplaçables" via requête EXISTS.
        try {
            global $DB;
            $olution = local_question_diagnostic_find_olution_category();
            if ($olution) {
                $params = [];
                $triagecond = self::build_in_category_tree_condition('qc', $params, (int)$triage->id, 'tri', $triage);
                $refcond = self::build_in_reference_condition('qc2', $params, $olution);
                $triagecond2 = self::build_in_category_tree_condition('qc2', $params, (int)$triage->id, 'tri2', $triage);

                $sqlcount = "SELECT COUNT(DISTINCT q.id)
                               FROM {question} q
                               INNER JOIN {question_versions} qv ON qv.questionid = q.id
                               INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                               INNER JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                              WHERE {$triagecond}
                                AND EXISTS (
                                    SELECT 1
                                      FROM {question} q2
                                      INNER JOIN {question_versions} qv2 ON qv2.questionid = q2.id
                                      INNER JOIN {question_bank_entries} qbe2 ON qbe2.id = qv2.questionbankentryid
                                      INNER JOIN {question_categories} qc2 ON qc2.id = qbe2.questioncategoryid
                                     WHERE q2.name = q.name
                                       AND q2.qtype = q.qtype
                                       AND {$refcond}
                                       AND NOT ({$triagecond2})
                                )";
                $stats->movable_questions = (int)$DB->count_records_sql($sqlcount, $params);
            }
        } catch (\Exception $e) {
            $stats->movable_questions = 0;
        }

        return $stats;
    }

    /**
     * Récupère la liste paginée des questions de "Question à trier" qui ont une correspondance
     * dans une autre sous-catégorie de commun (doublon name+qtype), avec la catégorie cible calculée.
     *
     * @param int $limit
     * @param int $offset
     * @param int|null $total (OUT) nombre total de questions déplaçables
     * @return array Tableau de candidats: ['question'=>object,'target_category'=>object,'target_count'=>int]
     */
    public static function get_triage_move_candidates_paginated(int $limit, int $offset, &$total = null): array {
        global $DB;

        $triage = self::get_triage_category();
        if (!$triage) {
            $total = 0;
            return [];
        }

        $olution = local_question_diagnostic_find_olution_category();
        if (!$olution) {
            $total = 0;
            return [];
        }

        $targetmap = self::get_triage_target_map();
        if (empty($targetmap)) {
            $total = 0;
            return [];
        }

        $params = [];
        $triagecond = self::build_in_category_tree_condition('qc', $params, (int)$triage->id, 'tri', $triage);
        $refcond = self::build_in_reference_condition('qc2', $params, $olution);
        $triagecond2 = self::build_in_category_tree_condition('qc2', $params, (int)$triage->id, 'tri2', $triage);

        // Total déplaçable.
        $sqlcount = "SELECT COUNT(DISTINCT q.id)
                       FROM {question} q
                       INNER JOIN {question_versions} qv ON qv.questionid = q.id
                       INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                       INNER JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                      WHERE {$triagecond}
                        AND EXISTS (
                            SELECT 1
                              FROM {question} q2
                              INNER JOIN {question_versions} qv2 ON qv2.questionid = q2.id
                              INNER JOIN {question_bank_entries} qbe2 ON qbe2.id = qv2.questionbankentryid
                              INNER JOIN {question_categories} qc2 ON qc2.id = qbe2.questioncategoryid
                             WHERE q2.name = q.name
                               AND q2.qtype = q.qtype
                               AND {$refcond}
                               AND NOT ({$triagecond2})
                        )";
        $total = (int)$DB->count_records_sql($sqlcount, $params);
        if ($total === 0) {
            return [];
        }

        // Page de questions (déplaçables uniquement).
        $sql = "SELECT DISTINCT q.id, q.name, q.qtype, q.timecreated
                  FROM {question} q
                  INNER JOIN {question_versions} qv ON qv.questionid = q.id
                  INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                  INNER JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                 WHERE {$triagecond}
                   AND EXISTS (
                        SELECT 1
                          FROM {question} q2
                          INNER JOIN {question_versions} qv2 ON qv2.questionid = q2.id
                          INNER JOIN {question_bank_entries} qbe2 ON qbe2.id = qv2.questionbankentryid
                          INNER JOIN {question_categories} qc2 ON qc2.id = qbe2.questioncategoryid
                         WHERE q2.name = q.name
                           AND q2.qtype = q.qtype
                           AND {$refcond}
                           AND NOT ({$triagecond2})
                   )
              ORDER BY q.id DESC";
        $questions = $DB->get_records_sql($sql, $params, $offset, $limit);
        if (empty($questions)) {
            return [];
        }

        // Résoudre catégories cibles pour la page.
        $neededcatids = [];
        foreach ($questions as $q) {
            $key = self::signature_key((string)$q->name, (string)$q->qtype);
            if (!empty($targetmap[$key])) {
                $neededcatids[] = (int)$targetmap[$key]['targetcatid'];
            }
        }
        $neededcatids = array_values(array_unique($neededcatids));
        $cats = !empty($neededcatids) ? $DB->get_records_list('question_categories', 'id', $neededcatids) : [];

        $out = [];
        foreach ($questions as $q) {
            $key = self::signature_key((string)$q->name, (string)$q->qtype);
            if (empty($targetmap[$key])) {
                continue;
            }
            $targetid = (int)$targetmap[$key]['targetcatid'];
            $targetcat = $cats[$targetid] ?? null;
            if (!$targetcat) {
                continue;
            }
            $out[] = [
                'question' => $q,
                'target_category' => $targetcat,
                'target_count' => (int)$targetmap[$key]['count'],
            ];
        }

        return $out;
    }

    /**
     * Construit une condition SQL "catégorie dans le scope de référence" (commun sous Olution si dispo, sinon Olution),
     * avec paramètres associés.
     *
     * @param string $qcalias Alias SQL de {question_categories} (ex: 'qc')
     * @param array $params Params SQL (référence)
     * @param object|null $olutioncat Objet catégorie Olution (optionnel)
     * @return string Condition SQL
     */
    private static function build_in_reference_condition(string $qcalias, array &$params, $olutioncat = null): string {
        global $DB;

        $refid = self::get_reference_category_id($olutioncat);
        if ($refid <= 0) {
            $params['refid'] = 0;
            return '1=0';
        }
        $params['refid'] = $refid;

        // Si la référence est Olution, on peut réutiliser la path d'Olution si dispo.
        // Sinon, il faut charger la catégorie "commun" pour obtenir sa path.
        $refcat = null;
        if ($olutioncat !== null && (int)$refid === (int)($olutioncat->id ?? 0)) {
            $refcat = $olutioncat;
        } else {
            $refcat = $DB->get_record('question_categories', ['id' => $refid], '*', IGNORE_MISSING);
        }

        // Essayer qc.path si disponible (évite une énorme clause IN).
        try {
            $cols = $DB->get_columns('question_categories');
            if (isset($cols['path']) && $refcat && isset($refcat->path) && !empty($refcat->path)) {
                $params['refpath'] = rtrim($refcat->path, '/') . '/%';
                return "({$qcalias}.id = :refid OR " . $DB->sql_like("{$qcalias}.path", ':refpath', false, false) . ')';
            }
        } catch (\Exception $e) {
            // Fallback ci-dessous.
        }

        // Fallback: calculer la liste des descendants en PHP (map id=>parent déjà en cache).
        $parentmap = self::get_category_parent_map();
        $children = [];
        foreach ($parentmap as $id => $parent) {
            if (!isset($children[$parent])) {
                $children[$parent] = [];
            }
            $children[$parent][] = $id;
        }

        $ids = [];
        $queue = [$refid];
        $seen = [];
        while (!empty($queue)) {
            $current = array_shift($queue);
            if (isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;
            $ids[] = $current;
            foreach (($children[$current] ?? []) as $childid) {
                $queue[] = (int)$childid;
            }
        }

        list($insql, $inparams) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'refcat');
        $params = array_merge($params, $inparams);
        return "{$qcalias}.id {$insql}";
    }

    /**
     * Vérifie si une catégorie appartient au scope de référence (commun sous Olution si présent).
     *
     * @param int $categoryid
     * @return bool
     */
    private static function is_in_reference_scope(int $categoryid): bool {
        $categoryid = (int)$categoryid;
        if ($categoryid <= 0) {
            return false;
        }

        if (isset(self::$inreferencecache[$categoryid])) {
            return (bool)self::$inreferencecache[$categoryid];
        }

        $refid = self::get_reference_category_id();
        if ($refid <= 0) {
            self::$inreferencecache[$categoryid] = false;
            return false;
        }

        $parentmap = self::get_category_parent_map();
        $current = $categoryid;
        $visited = [];

        while ($current > 0) {
            if (isset($visited[$current])) {
                break;
            }
            $visited[$current] = true;

            if ($current === $refid) {
                self::$inreferencecache[$categoryid] = true;
                return true;
            }

            $parent = $parentmap[$current] ?? null;
            if ($parent === null || (int)$parent === 0) {
                break;
            }
            $current = (int)$parent;
        }

        self::$inreferencecache[$categoryid] = false;
        return false;
    }

    /**
     * Récupère les groupes de doublons CERTAINS (qtype + questiontext identique) qui ont une présence dans Olution,
     * paginés.
     *
     * Doublon certain (mode A) = même qtype + même questiontext (et même questiontextformat par sécurité),
     * sans heuristique/similarité.
     *
     * @param int $limit Nombre de groupes à retourner
     * @param int $offset Offset de groupes
     * @param int|null $totalgroups (OUT) Nombre total de groupes matchés
     * @return array Tableau d'objets (representative_id,qtype,dup_count)
     */
    private static function get_olution_duplicate_group_rows_paginated(int $limit, int $offset, &$totalgroups = null): array {
        global $DB;

        $olution = local_question_diagnostic_find_olution_category();
        if (!$olution) {
            $totalgroups = 0;
            return [];
        }

        $params = [];
        // ⚠️ Le scope "référence" peut être Olution/commun/* (si présent).
        $inrefcond = self::build_in_reference_condition('qc', $params, $olution);

        $qtext = $DB->sql_compare_text('q.questiontext');

        $sqlgroups = "SELECT MIN(q.id) AS representative_id,
                             q.qtype,
                             COUNT(DISTINCT q.id) AS dup_count
                        FROM {question} q
                        INNER JOIN {question_versions} qv ON qv.questionid = q.id
                        INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                        INNER JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                    GROUP BY q.qtype, q.questiontextformat, {$qtext}
                      HAVING COUNT(DISTINCT q.id) > 1
                         AND COUNT(DISTINCT CASE WHEN {$inrefcond} THEN q.id ELSE NULL END) > 0
                    ORDER BY dup_count DESC, representative_id ASC, q.qtype ASC";

        // Total groups.
        $sqlcount = "SELECT COUNT(1)
                       FROM (
                             SELECT q.qtype, q.questiontextformat, {$qtext}
                               FROM {question} q
                               INNER JOIN {question_versions} qv ON qv.questionid = q.id
                               INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                               INNER JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                           GROUP BY q.qtype, q.questiontextformat, {$qtext}
                             HAVING COUNT(DISTINCT q.id) > 1
                                AND COUNT(DISTINCT CASE WHEN {$inrefcond} THEN q.id ELSE NULL END) > 0
                            ) t";

        $totalgroups = (int)$DB->count_records_sql($sqlcount, $params);
        if ($totalgroups === 0) {
            return [];
        }

        return array_values($DB->get_records_sql($sqlgroups, $params, $offset, $limit));
    }

    /**
     * Retourne l'ID de la catégorie Olution détectée (cache en mémoire).
     *
     * @return int ID catégorie Olution, ou 0 si non trouvée
     */
    private static function get_olution_category_id(): int {
        if (self::$olutioncategoryid !== null) {
            return (int)self::$olutioncategoryid;
        }

        $olution = local_question_diagnostic_find_olution_category();
        self::$olutioncategoryid = $olution ? (int)$olution->id : 0;

        return (int)self::$olutioncategoryid;
    }

    /**
     * Charge une map (id => parent) pour TOUTES les catégories de questions.
     * Permet d'éviter les requêtes N+1 lors des parcours d'arborescence.
     *
     * @return array<int,int>
     */
    private static function get_category_parent_map(): array {
        global $DB;

        if (self::$categoryparentmap !== null) {
            return self::$categoryparentmap;
        }

        $map = [];
        $records = $DB->get_records('question_categories', null, '', 'id,parent');
        foreach ($records as $rec) {
            $map[(int)$rec->id] = (int)$rec->parent;
        }

        self::$categoryparentmap = $map;
        return self::$categoryparentmap;
    }

    /**
     * Charge en batch la catégorie de questions de chaque question (Moodle 4.x).
     *
     * @param int[] $questionids
     * @return array<int,object> Map questionid => (object) catégorie (id,name,parent,contextid)
     */
    private static function get_categories_for_questions(array $questionids): array {
        global $DB;

        $questionids = array_values(array_unique(array_map('intval', $questionids)));
        if (empty($questionids)) {
            return [];
        }

        list($insql, $params) = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
        $sql = "SELECT qv.questionid,
                       qc.id AS categoryid,
                       qc.name AS categoryname,
                       qc.parent AS categoryparent,
                       qc.contextid AS categorycontextid
                  FROM {question_versions} qv
                  INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                  INNER JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                 WHERE qv.questionid $insql";

        // questionid est unique ici, donc get_records_sql est OK.
        $records = $DB->get_records_sql($sql, $params);

        $result = [];
        foreach ($records as $rec) {
            $qid = (int)$rec->questionid;
            $cat = (object)[
                'id' => (int)$rec->categoryid,
                'name' => $rec->categoryname,
                'parent' => (int)$rec->categoryparent,
                'contextid' => (int)$rec->categorycontextid,
            ];
            $result[$qid] = $cat;
        }

        return $result;
    }

    /**
     * Obtient la profondeur d'une catégorie de questions dans l'arborescence
     * 
     * @param int $categoryid ID de la catégorie
     * @return int Profondeur (0 = racine, 1 = niveau 1, etc.)
     */
    private static function get_category_depth($categoryid) {
        $categoryid = (int)$categoryid;
        if ($categoryid <= 0) {
            return 0;
        }

        if (isset(self::$depthcache[$categoryid])) {
            return (int)self::$depthcache[$categoryid];
        }

        $parentmap = self::get_category_parent_map();
        $visited = [];
        $path = [];
        $current = $categoryid;
        $depth = 0;

        while ($current > 0) {
            if (isset(self::$depthcache[$current])) {
                $depth += (int)self::$depthcache[$current];
                break;
            }

            if (isset($visited[$current])) {
                // Boucle détectée.
                break;
            }
            $visited[$current] = true;
            $path[] = $current;

            $parent = $parentmap[$current] ?? null;
            if ($parent === null || (int)$parent === 0) {
                break;
            }

            $depth++;
            $current = (int)$parent;
        }

        // Mémoriser les profondeurs sur le chemin (meilleure perf).
        $d = $depth;
        foreach ($path as $id) {
            if (!isset(self::$depthcache[$id])) {
                self::$depthcache[$id] = $d;
            }
            $d = max(0, $d - 1);
        }

        return $depth;
    }

    /**
     * Vérifie si une catégorie est dans Olution ou une de ses sous-catégories
     * 
     * 🔧 v1.11.13 : CORRECTION - Fonction publique et logique améliorée
     * Utilise la même logique que l'arborescence pour garantir la cohérence.
     * 
     * @param int $categoryid ID de la catégorie à vérifier
     * @return bool True si dans Olution
     */
    public static function is_in_olution($categoryid) {
        $categoryid = (int)$categoryid;
        if ($categoryid <= 0) {
            return false;
        }

        if (isset(self::$inolutioncache[$categoryid])) {
            return (bool)self::$inolutioncache[$categoryid];
        }

        $olutionid = self::get_olution_category_id();
        if ($olutionid <= 0) {
            local_question_diagnostic_debug_log('❌ Olution category not found in is_in_olution()', DEBUG_DEVELOPER);
            self::$inolutioncache[$categoryid] = false;
            return false;
        }

        $parentmap = self::get_category_parent_map();
        $current = $categoryid;
        $visited = [];
        $path = [];

        while ($current > 0) {
            if (isset($visited[$current])) {
                local_question_diagnostic_debug_log('⚠️ Loop detected in is_in_olution() for category ' . $categoryid, DEBUG_DEVELOPER);
                break;
            }
            $visited[$current] = true;
            $path[] = $current;

            if ($current === $olutionid) {
                self::$inolutioncache[$categoryid] = true;
                return true;
            }

            $parent = $parentmap[$current] ?? null;
            if ($parent === null || (int)$parent === 0) {
                break;
            }

            $current = (int)$parent;
        }

        local_question_diagnostic_debug_log('❌ Category ' . $categoryid . ' is NOT in Olution (path: ' . implode(' -> ', $path) . ')', DEBUG_DEVELOPER);
        self::$inolutioncache[$categoryid] = false;
        return false;
    }

    /**
     * Détecte tous les groupes de doublons du site
     * Utilise la même logique que questions_cleanup.php (nom + type)
     * 
     * 🆕 v1.10.9 : Logique CORRECTE basée sur question_analyzer::get_duplicate_groups()
     * 
     * @param int $limit Limite du nombre de groupes (0 = tous)
     * @param int $offset Offset pour pagination
     * @return array Tableau de groupes de doublons avec infos Olution
     */
    public static function find_all_duplicates_for_olution($limit = 0, $offset = 0) {
        global $DB;
        
        try {
            // Cache simple (dans la même requête HTTP) pour éviter double calcul (page + stats).
            if ((int)$limit === 0 && (int)$offset === 0 && self::$allolutionduplicategroupscache !== null) {
                return self::$allolutionduplicategroupscache;
            }

            // Vérifier que la catégorie de questions Olution existe
            $olution = local_question_diagnostic_find_olution_category();
            if (!$olution) {
                local_question_diagnostic_debug_log('❌ Olution question category not found', DEBUG_DEVELOPER);
                return [];
            }
            
            local_question_diagnostic_debug_log('✅ Olution question category found: ' . $olution->name . ' (ID: ' . $olution->id . ')', DEBUG_DEVELOPER);
            
            // Utiliser la détection de doublons existante (nom + type)
            // Récupérer TOUS les groupes de doublons du site
            $duplicate_groups = question_analyzer::get_duplicate_groups(0, 0, false, false);
            
            local_question_diagnostic_debug_log('📊 Found ' . count($duplicate_groups) . ' duplicate groups', DEBUG_DEVELOPER);
            
            $results = [];
            
            // Pour chaque groupe de doublons
            foreach ($duplicate_groups as $group) {
                $question_ids = $group->all_question_ids;
                
                if (empty($question_ids)) {
                    continue;
                }
                
                // Récupérer les détails de toutes les questions du groupe
                list($insql, $params) = $DB->get_in_or_equal($question_ids);
                $questions = $DB->get_records_select('question', "id $insql", $params);

                // Récupérer en batch la catégorie de chaque question (évite N+1).
                $categoriesbyquestion = self::get_categories_for_questions($question_ids);
                
                // Récupérer les catégories de chaque question
                $questions_with_categories = [];
                $reference_questions = [];
                $non_olution_questions = [];
                
                foreach ($questions as $q) {
                    $cat = $categoriesbyquestion[(int)$q->id] ?? null;
                    if ($cat) {
                        // "Dans Olution" = dans l'arbre Olution (sécurité: ne pas déplacer ce qui est déjà dedans).
                        $is_in_olution = self::is_in_olution($cat->id);
                        // "Référence" = commun sous Olution si présent (sinon Olution).
                        $is_in_reference = self::is_in_reference_scope((int)$cat->id);
                        $depth = self::get_category_depth($cat->id);
                        
                        $questions_with_categories[] = [
                            'question' => $q,
                            'category' => $cat,
                            'is_in_olution' => $is_in_olution,
                            'is_in_reference' => $is_in_reference,
                            'depth' => $depth
                        ];
                        
                        if ($is_in_reference) {
                            $reference_questions[] = [
                                'question' => $q,
                                'category' => $cat,
                                'depth' => $depth
                            ];
                        }

                        if (!$is_in_olution) {
                            $non_olution_questions[] = [
                                'question' => $q,
                                'category' => $cat
                            ];
                        }
                    }
                }
                
                // Si au moins UN doublon est dans le scope de référence (commun/*), c'est intéressant.
                if (!empty($reference_questions)) {
                    // Choisir la catégorie cible de façon stable :
                    // - Priorité 1 : Catégorie Olution la plus fréquente dans le groupe (majorité)
                    // - Priorité 2 : Profondeur la plus élevée (plus spécifique)
                    // - Priorité 3 : ID le plus petit (déterministe)
                    $countsbycatid = [];
                    $depthbycatid = [];
                    $catbyid = [];

                    foreach ($reference_questions as $oq) {
                        $cid = (int)$oq['category']->id;
                        if (!isset($countsbycatid[$cid])) {
                            $countsbycatid[$cid] = 0;
                        }
                        $countsbycatid[$cid]++;
                        $depthbycatid[$cid] = max($depthbycatid[$cid] ?? -1, (int)$oq['depth']);
                        $catbyid[$cid] = $oq['category'];
                    }

                    $targetcat = null;
                    $targetdepth = -1;
                    $bestcount = -1;
                    $bestid = PHP_INT_MAX;

                    foreach ($countsbycatid as $cid => $count) {
                        $depth = $depthbycatid[$cid] ?? -1;
                        if ($count > $bestcount
                            || ($count === $bestcount && $depth > $targetdepth)
                            || ($count === $bestcount && $depth === $targetdepth && $cid < $bestid)) {
                            $bestcount = $count;
                            $bestid = $cid;
                            $targetdepth = $depth;
                            $targetcat = $catbyid[$cid] ?? null;
                        }
                    }
                    
                    $results[] = [
                        'group_name' => $group->question_name,
                        'group_type' => $group->qtype,
                        'total_count' => count($questions_with_categories),
                        // NB: "olution_count" = nombre dans le scope de référence (commun/* si présent).
                        'olution_count' => count($reference_questions),
                        'non_olution_count' => count($non_olution_questions),
                        'all_questions' => $questions_with_categories,
                        'olution_questions' => $reference_questions,
                        'non_olution_questions' => $non_olution_questions,
                        'target_category' => $targetcat,
                        'target_depth' => $targetdepth
                    ];
                }
            }
            
            local_question_diagnostic_debug_log('📊 Found ' . count($results) . ' duplicate groups with Olution presence', DEBUG_DEVELOPER);
            
            // Appliquer pagination
            if ($limit > 0) {
                $results = array_slice($results, $offset, $limit);
            }
            if ((int)$limit === 0 && (int)$offset === 0) {
                self::$allolutionduplicategroupscache = $results;
            }

            return $results;
            
        } catch (\Exception $e) {
            local_question_diagnostic_debug_log('Error in find_all_duplicates_for_olution: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
    }

    /**
     * Détecte tous les groupes de doublons Olution avec pagination, et retourne le total avant pagination.
     *
     * @param int $limit Limite du nombre de groupes (0 = tous)
     * @param int $offset Offset pour pagination
     * @param int|null $totalgroups (OUT) Nombre total de groupes avant pagination
     * @return array Tableau de groupes de doublons paginés
     */
    public static function find_all_duplicates_for_olution_paginated($limit = 0, $offset = 0, &$totalgroups = null) {
        global $DB;

        $limit = (int)$limit;
        $offset = (int)$offset;
        if ($limit <= 0) {
            $limit = 50;
        }
        if ($offset < 0) {
            $offset = 0;
        }

        $rows = self::get_olution_duplicate_group_rows_paginated($limit, $offset, $totalgroups);
        if (empty($rows)) {
            return [];
        }

        // Construire les mêmes structures que find_all_duplicates_for_olution(), mais seulement pour la page demandée.
        $results = [];
        foreach ($rows as $row) {
            $repid = (int)($row->representative_id ?? 0);
            if ($repid <= 0) {
                continue;
            }

            $rep = $DB->get_record('question', ['id' => $repid], 'id,name,qtype,questiontext,questiontextformat', IGNORE_MISSING);
            if (!$rep) {
                continue;
            }

            // Doublon certain (mode A) : qtype + questiontext identiques (+ format).
            $sqlids = "SELECT q.id
                         FROM {question} q
                        WHERE q.qtype = :qtype
                          AND q.questiontextformat = :fmt
                          AND " . $DB->sql_compare_text('q.questiontext') . ' = ' . $DB->sql_compare_text(':qtext');
            $question_ids = $DB->get_fieldset_sql($sqlids, [
                'qtype' => $rep->qtype,
                'fmt' => (int)$rep->questiontextformat,
                'qtext' => $rep->questiontext,
            ]);

            if (empty($question_ids)) {
                continue;
            }

            list($insql, $params) = $DB->get_in_or_equal($question_ids);
            $questions = $DB->get_records_select('question', "id $insql", $params);
            $categoriesbyquestion = self::get_categories_for_questions($question_ids);

            $questions_with_categories = [];
            $reference_questions = [];
            $non_olution_questions = [];

            foreach ($questions as $q) {
                $cat = $categoriesbyquestion[(int)$q->id] ?? null;
                if (!$cat) {
                    continue;
                }

                // "Dans Olution" = dans l'arbre Olution (sécurité: ne pas déplacer ce qui est déjà dedans).
                $is_in_olution = self::is_in_olution($cat->id);
                // "Référence" = commun sous Olution si présent (sinon Olution).
                $is_in_reference = self::is_in_reference_scope((int)$cat->id);
                $depth = self::get_category_depth($cat->id);

                $questions_with_categories[] = [
                    'question' => $q,
                    'category' => $cat,
                    'is_in_olution' => $is_in_olution,
                    'is_in_reference' => $is_in_reference,
                    'depth' => $depth
                ];

                if ($is_in_reference) {
                    $reference_questions[] = [
                        'question' => $q,
                        'category' => $cat,
                        'depth' => $depth
                    ];
                }

                if (!$is_in_olution) {
                    $non_olution_questions[] = [
                        'question' => $q,
                        'category' => $cat
                    ];
                }
            }

            if (empty($reference_questions)) {
                // Par sécurité: la requête SQL dit qu'il y a présence "référence",
                // mais si nos données ne le voient pas, on ignore.
                continue;
            }

            // Réutiliser la logique de choix de cible (majorité puis profondeur).
            $countsbycatid = [];
            $depthbycatid = [];
            $catbyid = [];
            foreach ($reference_questions as $oq) {
                $cid = (int)$oq['category']->id;
                $countsbycatid[$cid] = ($countsbycatid[$cid] ?? 0) + 1;
                $depthbycatid[$cid] = max($depthbycatid[$cid] ?? -1, (int)$oq['depth']);
                $catbyid[$cid] = $oq['category'];
            }

            $targetcat = null;
            $targetdepth = -1;
            $bestcount = -1;
            $bestid = PHP_INT_MAX;
            foreach ($countsbycatid as $cid => $count) {
                $depth = $depthbycatid[$cid] ?? -1;
                if ($count > $bestcount
                    || ($count === $bestcount && $depth > $targetdepth)
                    || ($count === $bestcount && $depth === $targetdepth && $cid < $bestid)) {
                    $bestcount = $count;
                    $bestid = $cid;
                    $targetdepth = $depth;
                    $targetcat = $catbyid[$cid] ?? null;
                }
            }

            $results[] = [
                'group_name' => $rep->name,
                'group_type' => $rep->qtype,
                'total_count' => count($questions_with_categories),
                // NB: "olution_count" = nombre dans le scope de référence (commun/* si présent).
                'olution_count' => count($reference_questions),
                'non_olution_count' => count($non_olution_questions),
                'all_questions' => $questions_with_categories,
                'olution_questions' => $reference_questions,
                'non_olution_questions' => $non_olution_questions,
                'target_category' => $targetcat,
                'target_depth' => $targetdepth
            ];
        }

        return $results;
    }

    /**
     * Obtient les statistiques globales des doublons Olution
     * 
     * @return object Statistiques
     */
    public static function get_duplicate_stats() {
        global $DB;
        
        $stats = new \stdClass();
        
        // Vérifier que la catégorie de questions Olution existe
        $olution = local_question_diagnostic_find_olution_category();
        $stats->olution_exists = ($olution !== false);
        
        if (!$stats->olution_exists) {
            $stats->olution_name = '';
            $stats->olution_courses_count = 0;
            $stats->total_duplicates = 0;
            $stats->movable_questions = 0;
            $stats->unmovable_questions = 0;
            return $stats;
        }
        
        $stats->olution_name = $olution->name;
        
        // Compter les sous-catégories d'Olution
        $stats->olution_courses_count = $DB->count_records('question_categories', [
            'parent' => $olution->id
        ]);

        // Stats "fast" basées SQL pour éviter recalcul complet.
        $params = [];
        // "Référence" (commun/* si présent) pour les groupes pertinents.
        $inrefcond = self::build_in_reference_condition('qc', $params, $olution);

        // Total de questions dans les groupes CERTAINS (qtype + questiontext identique) avec présence "référence".
        $qtext = $DB->sql_compare_text('q.questiontext');
        $sqlsum = "SELECT COALESCE(SUM(g.dup_count), 0) AS total_questions
                     FROM (
                           SELECT q.qtype, q.questiontextformat, {$qtext}, COUNT(DISTINCT q.id) AS dup_count
                             FROM {question} q
                             INNER JOIN {question_versions} qv ON qv.questionid = q.id
                             INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                             INNER JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                         GROUP BY q.qtype, q.questiontextformat, {$qtext}
                           HAVING COUNT(DISTINCT q.id) > 1
                              AND COUNT(DISTINCT CASE WHEN {$inrefcond} THEN q.id ELSE NULL END) > 0
                          ) g";
        $stats->total_duplicates = (int)$DB->get_field_sql($sqlsum, $params);

        // Questions "déplaçables" = questions hors Olution, mais appartenant à un groupe avec présence Olution.
        // On reconstruit les params pour éviter tout effet de bord.
        $params2 = [];
        $inolutioncond1 = self::build_in_olution_condition('qc', $params2, $olution); // arbre Olution (sécurité)
        $inrefcond2 = self::build_in_reference_condition('qc2', $params2, $olution); // scope de comparaison (commun/*)
        $sqlmovable = "SELECT COUNT(DISTINCT q.id)
                         FROM {question} q
                         INNER JOIN {question_versions} qv ON qv.questionid = q.id
                         INNER JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                         INNER JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                        WHERE NOT ({$inolutioncond1})
                          AND EXISTS (
                              SELECT 1
                                FROM {question} q2
                                INNER JOIN {question_versions} qv2 ON qv2.questionid = q2.id
                                INNER JOIN {question_bank_entries} qbe2 ON qbe2.id = qv2.questionbankentryid
                                INNER JOIN {question_categories} qc2 ON qc2.id = qbe2.questioncategoryid
                               WHERE q2.qtype = q.qtype
                                 AND q2.questiontextformat = q.questiontextformat
                                 AND " . $DB->sql_compare_text('q2.questiontext') . " = " . $DB->sql_compare_text('q.questiontext') . "
                               GROUP BY q2.qtype, q2.questiontextformat, " . $DB->sql_compare_text('q2.questiontext') . "
                                 HAVING COUNT(DISTINCT q2.id) > 1
                                    AND COUNT(DISTINCT CASE WHEN {$inrefcond2} THEN q2.id ELSE NULL END) > 0
                          )";
        $stats->movable_questions = (int)$DB->count_records_sql($sqlmovable, $params2);
        $stats->unmovable_questions = max(0, (int)$stats->total_duplicates - (int)$stats->movable_questions);
        
        return $stats;
    }

    /**
     * Déplace une question vers la catégorie Olution cible
     * 
     * 🔧 v1.11.13 : CORRECTION - Amélioration de la logique de déplacement
     * Utilise la même logique que l'arborescence et ajoute des vérifications robustes.
     * 
     * @param int $questionid ID de la question à déplacer
     * @param int $target_category_id ID de la catégorie Olution cible
     * @return bool|string True si succès, message d'erreur sinon
     */
    public static function move_question_to_olution($questionid, $target_category_id) {
        global $DB, $CFG;
        
        try {
            local_question_diagnostic_debug_log('🚀 Starting move_question_to_olution: question=' . $questionid . ', target=' . $target_category_id, DEBUG_DEVELOPER);
            
            require_once($CFG->libdir . '/questionlib.php');

            // Vérifier que la question existe
            $question = $DB->get_record('question', ['id' => $questionid]);
            if (!$question) {
                return 'Question introuvable (ID: ' . $questionid . ')';
            }
            
            local_question_diagnostic_debug_log('✅ Question found: ' . $question->name . ' (type: ' . $question->qtype . ')', DEBUG_DEVELOPER);
            
            // Vérifier que la catégorie cible existe et est dans Olution
            $target_category = $DB->get_record('question_categories', ['id' => $target_category_id]);
            if (!$target_category) {
                return 'Catégorie cible introuvable (ID: ' . $target_category_id . ')';
            }
            
            local_question_diagnostic_debug_log('✅ Target category found: ' . $target_category->name, DEBUG_DEVELOPER);
            
            // Vérifier que la catégorie cible est bien dans Olution
            if (!self::is_in_olution($target_category_id)) {
                return 'La catégorie cible n\'est pas dans Olution (ID: ' . $target_category_id . ')';
            }
            
            local_question_diagnostic_debug_log('✅ Target category is confirmed to be in Olution', DEBUG_DEVELOPER);
            
            // Récupérer la catégorie actuelle de la question
            $current_category_sql = "SELECT qc.*
                                    FROM {question_categories} qc
                                    INNER JOIN {question_bank_entries} qbe ON qbe.questioncategoryid = qc.id
                                    INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                                    WHERE qv.questionid = :questionid
                                    LIMIT 1";
            $current_category = $DB->get_record_sql($current_category_sql, ['questionid' => $questionid]);
            
            if (!$current_category) {
                return 'Impossible de déterminer la catégorie actuelle de la question';
            }
            
            local_question_diagnostic_debug_log('✅ Current category: ' . $current_category->name . ' (ID: ' . $current_category->id . ')', DEBUG_DEVELOPER);
            
            // Vérifier si la question est déjà dans la catégorie cible
            if ($current_category->id == $target_category_id) {
                return 'La question est déjà dans la catégorie cible';
            }
            
            // Démarrer une transaction
            $transaction = $DB->start_delegated_transaction();
            
            try {
                // Utiliser l'API native Moodle pour déplacer la question
                // Cette fonction gère automatiquement les événements, les contextes et les entrées/versions
                if (function_exists('question_move_questions_to_category')) {
                    // Moodle 4.x standard API
                    // question_move_questions_to_category(array $questionids, int $newcategoryid)
                    question_move_questions_to_category([$questionid], $target_category_id);
                    local_question_diagnostic_debug_log('✅ Moved using native question_move_questions_to_category', DEBUG_DEVELOPER);
                } else {
                    // Fallback manuel si la fonction n'existe pas (versions très anciennes ou modifiées)
                    // Mettre à jour question_bank_entries (Moodle 4.x)
                    $sql_update = "UPDATE {question_bank_entries}
                                  SET questioncategoryid = :newcatid
                                  WHERE id IN (
                                      SELECT questionbankentryid
                                      FROM {question_versions}
                                      WHERE questionid = :questionid
                                  )";
                    
                    $affected_rows = $DB->execute($sql_update, [
                        'newcatid' => $target_category_id,
                        'questionid' => $questionid
                    ]);
                    
                    local_question_diagnostic_debug_log('✅ Updated ' . $affected_rows . ' question_bank_entries (Manual fallback)', DEBUG_DEVELOPER);
                    
                    // Déclencher l'événement manuellement car on n'a pas utilisé l'API
                    $event = \core\event\question_moved::create([
                        'objectid' => $questionid,
                        'context' => \context::instance_by_id($current_category->contextid),
                        'other' => [
                            'oldcategoryid' => $current_category->id,
                            'newcategoryid' => $target_category_id
                        ]
                    ]);
                    $event->trigger();
                }
                
                // Vérifier que la mise à jour a fonctionné
                $verify_sql = "SELECT qc.id as category_id, qc.name as category_name
                              FROM {question_categories} qc
                              INNER JOIN {question_bank_entries} qbe ON qbe.questioncategoryid = qc.id
                              INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                              WHERE qv.questionid = :questionid
                              LIMIT 1";
                $verify_result = $DB->get_record_sql($verify_sql, ['questionid' => $questionid]);
                
                if (!$verify_result || (int)$verify_result->category_id !== (int)$target_category_id) {
                    throw new \Exception('Vérification échouée après déplacement');
                }
                
                local_question_diagnostic_debug_log('✅ Verification successful: question is now in ' . $verify_result->category_name . ' (ID: ' . $verify_result->category_id . ')', DEBUG_DEVELOPER);
                
                // Valider la transaction
                $transaction->allow_commit();
                
                // Log d'audit
                require_once(__DIR__ . '/audit_logger.php');
                audit_logger::log_action(
                    'question_moved_to_olution',
                    [
                        'question_id' => $questionid,
                        'question_name' => $question->name,
                        'question_type' => $question->qtype,
                        'old_category_id' => $current_category->id,
                        'old_category_name' => $current_category->name,
                        'target_category_id' => $target_category_id,
                        'target_category_name' => $target_category->name,
                        'message' => 'Question déplacée vers Olution: ' . $target_category->name
                    ],
                    $questionid
                );
                
                local_question_diagnostic_debug_log('✅ Question successfully moved to Olution: ' . $target_category->name, DEBUG_DEVELOPER);
                return true;
                
            } catch (\Exception $inner_e) {
                local_question_diagnostic_debug_log('❌ Error in transaction: ' . $inner_e->getMessage(), DEBUG_DEVELOPER);
                throw $inner_e;
            }
            
        } catch (\Exception $e) {
            local_question_diagnostic_debug_log('❌ Error in move_question_to_olution: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return 'Erreur lors du déplacement : ' . $e->getMessage();
        }
    }

    /**
     * Déplace plusieurs questions vers Olution en masse
     * 
     * @param array $move_operations Tableau d'opérations [['questionid' => X, 'target_category_id' => Y], ...]
     * @return array ['success' => count, 'failed' => count, 'errors' => []]
     */
    public static function move_questions_batch($move_operations) {
        $success = 0;
        $failed = 0;
        $errors = [];
        
        foreach ($move_operations as $op) {
            $result = self::move_question_to_olution($op['questionid'], $op['target_category_id']);
            
            if ($result === true) {
                $success++;
            } else {
                $failed++;
                $errors[] = "Question {$op['questionid']}: $result";
            }
        }
        
        // Purger les caches après déplacement en masse
        if ($success > 0) {
            require_once(__DIR__ . '/cache_manager.php');
            cache_manager::purge_all_caches();
        }
        
        return [
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors
        ];
    }

    /**
     * Teste le déplacement automatique vers Olution
     * 
     * 🔧 v1.11.13 : NOUVELLE FONCTION - Test du déplacement automatique
     * Cette fonction permet de tester le déplacement automatique vers Olution
     * en utilisant des questions réelles de la base de données.
     * 
     * @param int $limit Nombre maximum de questions à tester (défaut: 3)
     * @return array Résultats du test
     */
    public static function test_automatic_movement_to_olution($limit = 3) {
        global $DB;
        
        try {
            local_question_diagnostic_debug_log('🧪 Starting test_automatic_movement_to_olution with limit: ' . $limit, DEBUG_DEVELOPER);
            
            // Vérifier que la catégorie Olution existe
            $olution = local_question_diagnostic_find_olution_category();
            if (!$olution) {
                return [
                    'success' => false,
                    'message' => 'Catégorie Olution non trouvée',
                    'tested_questions' => 0,
                    'moved_questions' => 0,
                    'failed_questions' => 0,
                    'details' => []
                ];
            }
            
            local_question_diagnostic_debug_log('✅ Olution category found: ' . $olution->name . ' (ID: ' . $olution->id . ')', DEBUG_DEVELOPER);
            
            // Récupérer les sous-catégories d'Olution
            $olution_subcategories = local_question_diagnostic_get_olution_subcategories($olution->id);
            if (empty($olution_subcategories)) {
                return [
                    'success' => false,
                    'message' => 'Aucune sous-catégorie Olution trouvée',
                    'tested_questions' => 0,
                    'moved_questions' => 0,
                    'failed_questions' => 0,
                    'details' => []
                ];
            }
            
            local_question_diagnostic_debug_log('✅ Found ' . count($olution_subcategories) . ' Olution subcategories', DEBUG_DEVELOPER);
            
            // Récupérer quelques questions candidates, puis filtrer en PHP avec is_in_olution()
            // (évite le faux "hors Olution" quand la question est dans une sous-sous-catégorie).
            $candidateslimit = max(20, (int)$limit * 20);
            $candidates_sql = "SELECT DISTINCT q.*
                                 FROM {question} q
                                WHERE q.name IS NOT NULL
                                  AND q.name != ''
                             ORDER BY q.id DESC";

            $candidate_questions = $DB->get_records_sql($candidates_sql, [], 0, $candidateslimit);
            $candidate_ids = array_keys($candidate_questions);
            $catsbyqid = self::get_categories_for_questions($candidate_ids);

            $non_olution_questions = [];
            foreach ($candidate_questions as $q) {
                $qid = (int)$q->id;
                $cat = $catsbyqid[$qid] ?? null;
                if (!$cat) {
                    continue;
                }
                if (!self::is_in_olution((int)$cat->id)) {
                    $non_olution_questions[$qid] = $q;
                }
                if (count($non_olution_questions) >= (int)$limit) {
                    break;
                }
            }
            
            if (empty($non_olution_questions)) {
                return [
                    'success' => false,
                    'message' => 'Aucune question hors Olution trouvée pour le test',
                    'tested_questions' => 0,
                    'moved_questions' => 0,
                    'failed_questions' => 0,
                    'details' => []
                ];
            }
            
            local_question_diagnostic_debug_log('✅ Found ' . count($non_olution_questions) . ' questions outside Olution for testing', DEBUG_DEVELOPER);
            
            $test_results = [];
            $moved_count = 0;
            $failed_count = 0;
            
            foreach ($non_olution_questions as $question) {
                // Choisir une sous-catégorie Olution aléatoire comme cible
                $target_category = $olution_subcategories[array_rand($olution_subcategories)];
                
                local_question_diagnostic_debug_log('🧪 Testing move: question ' . $question->id . ' (' . $question->name . ') to ' . $target_category->name . ' (ID: ' . $target_category->id . ')', DEBUG_DEVELOPER);
                
                $move_result = self::move_question_to_olution($question->id, $target_category->id);
                
                $test_result = [
                    'question_id' => $question->id,
                    'question_name' => $question->name,
                    'question_type' => $question->qtype,
                    'target_category_id' => $target_category->id,
                    'target_category_name' => $target_category->name,
                    'move_result' => $move_result,
                    'success' => ($move_result === true)
                ];
                
                if ($move_result === true) {
                    $moved_count++;
                    local_question_diagnostic_debug_log('✅ Test successful: question ' . $question->id . ' moved to ' . $target_category->name, DEBUG_DEVELOPER);
                } else {
                    $failed_count++;
                    local_question_diagnostic_debug_log('❌ Test failed: question ' . $question->id . ' - ' . $move_result, DEBUG_DEVELOPER);
                }
                
                $test_results[] = $test_result;
            }
            
            $overall_success = ($moved_count > 0);
            
            local_question_diagnostic_debug_log('🏁 Test completed: ' . $moved_count . ' moved, ' . $failed_count . ' failed', DEBUG_DEVELOPER);
            
            return [
                'success' => $overall_success,
                'message' => 'Test terminé: ' . $moved_count . ' questions déplacées, ' . $failed_count . ' échecs',
                'tested_questions' => count($non_olution_questions),
                'moved_questions' => $moved_count,
                'failed_questions' => $failed_count,
                'olution_category' => [
                    'id' => $olution->id,
                    'name' => $olution->name
                ],
                'target_subcategories' => array_map(function($cat) {
                    return ['id' => $cat->id, 'name' => $cat->name];
                }, $olution_subcategories),
                'details' => $test_results
            ];
            
        } catch (\Exception $e) {
            local_question_diagnostic_debug_log('❌ Error in test_automatic_movement_to_olution: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [
                'success' => false,
                'message' => 'Erreur lors du test: ' . $e->getMessage(),
                'tested_questions' => 0,
                'moved_questions' => 0,
                'failed_questions' => 0,
                'details' => []
            ];
        }
    }
}
