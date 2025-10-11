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
            
            // Obtenir les statistiques
            $stats = question_link_checker::get_global_stats();
            
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
        global $CFG, $DB;
        
        try {
            // Récupérer tous les admins site
            $admins = get_admins();
            
            if (empty($admins)) {
                mtrace('  ⚠️  Aucun administrateur site trouvé pour envoi email');
                return;
            }
            
            // Préparer le message
            $subject = '[Moodle] Question Diagnostic : ' . $stats->questions_with_broken_links . ' question(s) avec liens cassés';
            
            $message_html = '<h2>🔍 Scan Automatique des Liens Cassés</h2>';
            $message_html .= '<p><strong>Un scan automatique a détecté des liens cassés dans votre banque de questions.</strong></p>';
            $message_html .= '<hr>';
            $message_html .= '<h3>📊 Résultats :</h3>';
            $message_html .= '<ul>';
            $message_html .= '<li><strong>Total questions :</strong> ' . $stats->total_questions . '</li>';
            $message_html .= '<li><strong>Questions avec liens cassés :</strong> <span style="color: #d9534f; font-weight: bold;">' . $stats->questions_with_broken_links . '</span></li>';
            $message_html .= '<li><strong>Total liens cassés :</strong> ' . $stats->total_broken_links . '</li>';
            $message_html .= '</ul>';
            $message_html .= '<hr>';
            $message_html .= '<p><strong>Action recommandée :</strong> Consultez la page de vérification des liens pour identifier et corriger les problèmes.</p>';
            $message_html .= '<p><a href="' . $CFG->wwwroot . '/local/question_diagnostic/broken_links.php" style="display: inline-block; padding: 10px 20px; background: #0f6cbf; color: white; text-decoration: none; border-radius: 5px;">→ Consulter les liens cassés</a></p>';
            $message_html .= '<hr>';
            $message_html .= '<p style="font-size: 12px; color: #666;">Ce message est envoyé automatiquement par le plugin Question Diagnostic.<br>';
            $message_html .= 'Pour désactiver ces alertes, allez dans Administration → Plugins → Tâches planifiées.</p>';
            
            $message_text = strip_tags(str_replace(['<br>', '</p>', '</li>'], ["\n", "\n", "\n"], $message_html));
            
            // Envoyer à chaque admin
            $sent_count = 0;
            foreach ($admins as $admin) {
                if (email_to_user($admin, \core_user::get_noreply_user(), $subject, $message_text, $message_html)) {
                    $sent_count++;
                }
            }
            
            mtrace('  ✓ Email d\'alerte envoyé à ' . $sent_count . ' administrateur(s)');
            
        } catch (\Exception $e) {
            mtrace('  ⚠️  Erreur envoi email : ' . $e->getMessage());
        }
    }
}

