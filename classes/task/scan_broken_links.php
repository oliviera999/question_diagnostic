<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Tâche planifiée : Scan automatique des liens cassés
 * 
 * 🆕 v1.9.40 : TODO BASSE #6 - Tâche planifiée maintenance proactive
 * 
 * Cette tâche s'exécute automatiquement (par défaut : 1x par semaine)
 * et scanne les questions pour détecter les liens cassés.
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_question_diagnostic\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/question_diagnostic/classes/question_link_checker.php');
require_once($CFG->dirroot . '/local/question_diagnostic/classes/audit_logger.php');

use local_question_diagnostic\question_link_checker;
use local_question_diagnostic\audit_logger;

/**
 * Tâche planifiée de scan des liens cassés
 */
class scan_broken_links extends \core\task\scheduled_task {

    /**
     * Retourne le nom de la tâche
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_scan_broken_links', 'local_question_diagnostic');
    }

    /**
     * Exécute la tâche
     */
    public function execute() {
        global $DB;
        
        mtrace('========================================');
        mtrace('Scan automatique des liens cassés');
        mtrace('========================================');
        mtrace('Démarrage : ' . date('Y-m-d H:i:s'));
        mtrace('');
        
        try {
            // Purger le cache avant scan pour forcer une nouvelle analyse
            question_link_checker::purge_broken_links_cache();
            mtrace('✓ Cache purgé');
            
            // v1.15.0 : toute la banque (dernières versions), pas seulement les 1000 questions les plus récentes.
            \core_php_time_limit::raise();
            $broken = question_link_checker::get_questions_with_broken_links(false, 0);
            $stats = (object)[
                'total_questions' => (int)$DB->count_records_sql("SELECT COUNT(DISTINCT questionbankentryid)
                    FROM {question_versions}"),
                'questions_with_broken_links' => count($broken),
                'total_broken_links' => array_sum(array_map(fn($b) => (int)$b->broken_count, $broken)),
            ];
            
            mtrace('');
            mtrace('Résultats du scan :');
            mtrace('  Total questions : ' . $stats->total_questions);
            mtrace('  Questions avec liens cassés : ' . $stats->questions_with_broken_links);
            mtrace('  Total liens cassés : ' . $stats->total_broken_links);
            
            // Si liens cassés détectés, envoyer une alerte
            if ($stats->questions_with_broken_links > 0) {
                mtrace('');
                mtrace('⚠️  ALERTE : ' . $stats->questions_with_broken_links . ' question(s) avec liens cassés détectées');
                
                // Envoyer email aux admins
                $this->send_alert_email($stats);
                
                // Log l'événement
                audit_logger::log_action('scheduled_scan_completed', [
                    'broken_links_found' => $stats->questions_with_broken_links,
                    'total_links' => $stats->total_broken_links,
                    'alert_sent' => true
                ]);
            } else {
                mtrace('');
                mtrace('✅ Aucun lien cassé détecté - Tout est OK !');
                
                audit_logger::log_action('scheduled_scan_completed', [
                    'broken_links_found' => 0,
                    'alert_sent' => false
                ]);
            }
            
            mtrace('');
            mtrace('Scan terminé : ' . date('Y-m-d H:i:s'));
            mtrace('========================================');
            
        } catch (\Exception $e) {
            mtrace('');
            mtrace('❌ ERREUR lors du scan : ' . $e->getMessage());
            mtrace('========================================');
            throw $e;
        }
    }

    /**
     * Envoie un email d'alerte aux administrateurs site
     *
     * @param object $stats Statistiques des liens cassés
     */
    private function send_alert_email($stats) {
        // v1.15.0 : messagerie Moodle (fournisseur « operationreport »), chaque administrateur règle ses préférences.
        $body = get_string('brokenlinks_report', 'local_question_diagnostic', $stats);
        $url = new \moodle_url('/local/question_diagnostic/broken_links.php');
        foreach (get_admins() as $admin) {
            \local_question_diagnostic\local\notifier::send((int)$admin->id,
                get_string('task_scan_broken_links', 'local_question_diagnostic'), $body, $url);
        }
    }
}

