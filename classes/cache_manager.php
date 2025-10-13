<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_question_diagnostic;

defined('MOODLE_INTERNAL') || die();

/**
 * Gestionnaire centralisé de cache pour le plugin Question Diagnostic
 * 
 * 🔧 NOUVEAU v1.9.27 : Centralise la gestion des 4 caches du plugin
 * Avant cette classe, chaque classe gérait son propre cache séparément :
 * - question_analyzer::purge_all_caches() (ligne 1388)
 * - question_link_checker::purge_broken_links_cache() (ligne 490)
 * - Pas de méthode centralisée pour purger TOUS les caches
 * 
 * Cette classe résout :
 * - ✅ Incohérence dans la gestion des caches
 * - ✅ Impossibilité de purger tous les caches en une seule action
 * - ✅ Code dupliqué pour accès aux caches
 * 
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cache_manager {

    /** @var string Cache pour les doublons de questions */
    const CACHE_DUPLICATES = 'duplicates';
    
    /** @var string Cache pour les statistiques globales */
    const CACHE_GLOBALSTATS = 'globalstats';
    
    /** @var string Cache pour l'usage des questions */
    const CACHE_QUESTIONUSAGE = 'questionusage';
    
    /** @var string Cache pour les liens cassés */
    const CACHE_BROKENLINKS = 'brokenlinks';
    
    /** @var string Cache pour les fichiers orphelins */
    const CACHE_ORPHANFILES = 'orphanfiles';

    /**
     * Récupère une instance de cache
     *
     * @param string $cache_name Nom du cache (utiliser les constantes CACHE_*)
     * @return \cache Cache instance
     * @throws \coding_exception Si nom de cache invalide
     */
    public static function get_cache($cache_name) {
        $valid_caches = [
            self::CACHE_DUPLICATES,
            self::CACHE_GLOBALSTATS,
            self::CACHE_QUESTIONUSAGE,
            self::CACHE_BROKENLINKS,
            self::CACHE_ORPHANFILES
        ];
        
        if (!in_array($cache_name, $valid_caches)) {
            throw new \coding_exception('Cache name invalid: ' . $cache_name);
        }
        
        return \cache::make('local_question_diagnostic', $cache_name);
    }

    /**
     * Purge un cache spécifique
     *
     * @param string $cache_name Nom du cache à purger
     * @return bool Succès de l'opération
     */
    public static function purge_cache($cache_name) {
        try {
            $cache = self::get_cache($cache_name);
            $cache->purge();
            debugging('Cache purgé : ' . $cache_name, DEBUG_DEVELOPER);
            return true;
        } catch (\Exception $e) {
            debugging('Erreur purge cache ' . $cache_name . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Purge TOUS les caches du plugin
     * 
     * 🔧 MÉTHODE CENTRALE : Remplace les méthodes éparpillées dans les différentes classes
     *
     * @return array Résultats [cache_name => success_bool]
     */
    public static function purge_all_caches() {
        $results = [];
        
        $results[self::CACHE_DUPLICATES] = self::purge_cache(self::CACHE_DUPLICATES);
        $results[self::CACHE_GLOBALSTATS] = self::purge_cache(self::CACHE_GLOBALSTATS);
        $results[self::CACHE_QUESTIONUSAGE] = self::purge_cache(self::CACHE_QUESTIONUSAGE);
        $results[self::CACHE_BROKENLINKS] = self::purge_cache(self::CACHE_BROKENLINKS);
        $results[self::CACHE_ORPHANFILES] = self::purge_cache(self::CACHE_ORPHANFILES);
        
        $success_count = count(array_filter($results));
        $total_count = count($results);
        
        debugging("Caches purgés : $success_count/$total_count", DEBUG_DEVELOPER);
        
        return $results;
    }

    /**
     * Récupère une valeur depuis un cache
     *
     * @param string $cache_name Nom du cache
     * @param string $key Clé de la valeur
     * @return mixed|false Valeur ou false si non trouvée
     */
    public static function get($cache_name, $key) {
        try {
            $cache = self::get_cache($cache_name);
            return $cache->get($key);
        } catch (\Exception $e) {
            debugging('Erreur get cache ' . $cache_name . '/' . $key . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Stocke une valeur dans un cache
     *
     * @param string $cache_name Nom du cache
     * @param string $key Clé de la valeur
     * @param mixed $value Valeur à stocker
     * @return bool Succès de l'opération
     */
    public static function set($cache_name, $key, $value) {
        try {
            $cache = self::get_cache($cache_name);
            $cache->set($key, $value);
            return true;
        } catch (\Exception $e) {
            debugging('Erreur set cache ' . $cache_name . '/' . $key . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Supprime une clé spécifique d'un cache
     *
     * @param string $cache_name Nom du cache
     * @param string $key Clé à supprimer
     * @return bool Succès de l'opération
     */
    public static function delete($cache_name, $key) {
        try {
            $cache = self::get_cache($cache_name);
            $cache->delete($key);
            return true;
        } catch (\Exception $e) {
            debugging('Erreur delete cache ' . $cache_name . '/' . $key . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Obtient des statistiques sur l'utilisation des caches
     * 
     * Utile pour le monitoring et le debug
     *
     * @return array Statistiques par cache
     */
    public static function get_cache_stats() {
        $stats = [];
        
        $cache_names = [
            self::CACHE_DUPLICATES,
            self::CACHE_GLOBALSTATS,
            self::CACHE_QUESTIONUSAGE,
            self::CACHE_BROKENLINKS,
            self::CACHE_ORPHANFILES
        ];
        
        foreach ($cache_names as $cache_name) {
            try {
                $cache = self::get_cache($cache_name);
                // Note: L'API de cache Moodle ne fournit pas de stats détaillées
                // On retourne juste si le cache est accessible
                $stats[$cache_name] = [
                    'accessible' => true,
                    'definition' => 'local_question_diagnostic/' . $cache_name
                ];
            } catch (\Exception $e) {
                $stats[$cache_name] = [
                    'accessible' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $stats;
    }
}

