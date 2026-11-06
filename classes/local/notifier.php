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

namespace local_question_diagnostic\local;

/**
 * Envoi des rapports d'opération par l'API de messagerie de Moodle (fournisseur « operationreport »).
 *
 * @package    local_question_diagnostic
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notifier {

    /**
     * Envoie un rapport à un utilisateur.
     *
     * @param int $userid destinataire
     * @param string $subject sujet
     * @param string $body texte brut du rapport
     * @param \moodle_url|null $url page à ouvrir
     * @return bool
     */
    public static function send(int $userid, string $subject, string $body, ?\moodle_url $url = null): bool {
        $user = \core_user::get_user($userid);
        if (!$user || !empty($user->deleted)) {
            return false;
        }
        $message = new \core\message\message();
        $message->component = 'local_question_diagnostic';
        $message->name = 'operationreport';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $message->subject = $subject;
        $message->fullmessage = $body;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '<pre>' . s($body) . '</pre>';
        $message->smallmessage = $subject;
        $message->notification = 1;
        if ($url) {
            $message->contexturl = $url->out(false);
            $message->contexturlname = get_string('pluginname', 'local_question_diagnostic');
        }
        try {
            return (bool)message_send($message);
        } catch (\Throwable $e) {
            debugging('notifier::send : ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }
}
