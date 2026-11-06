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

namespace local_question_diagnostic;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');
require_once(__DIR__ . '/orphan_file_detector.php');

/**
 * « Réparation » d'un fichier orphelin : COPIE vers une question choisie par l'administrateur.
 *
 * Principes de sécurité :
 * - aucune recherche automatique de cible (plus de recherche « par nom similaire » sur tout le site) ;
 * - aucun déplacement d'un enregistrement existant, jamais d'update_record() sur {files} :
 *   la copie passe par l'API de fichiers (create_file_from_storedfile), qui recalcule pathnamehash ;
 * - le fichier d'origine reste intact (il pourra être supprimé ensuite via le circuit sûr, avec archivage) ;
 * - le statut orphelin est revérifié au moment de l'exécution (on ne fait pas confiance aux ids reçus) ;
 * - les zones protégées (brouillons, réponses d'élèves, devoirs, sauvegardes) ne sont jamais copiées.
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class orphan_file_repairer {

    /** @var string Zone de fichiers cible dans la question. */
    const TARGET_FILEAREA = 'questiontext';

    /**
     * Indique si un fichier peut être copié vers une question : orphelin et hors zones protégées.
     *
     * @param \stdClass|false|null $filerecord Enregistrement de {files}
     * @return bool
     */
    public static function can_repair($filerecord): bool {
        return !empty($filerecord) && orphan_file_detector::is_safe_to_delete($filerecord);
    }

    /**
     * Récupère une question cible et son contexte (catégorie → contexte de la banque, mod_qbank en 5.x).
     *
     * @param int $questionid
     * @return \stdClass|null Objet {id, name, qtype, contextid, categoryname} ou null si introuvable
     */
    public static function get_question_target(int $questionid): ?\stdClass {
        global $DB;
        if ($questionid <= 0) {
            return null;
        }
        $sql = "SELECT q.id, q.name, q.qtype, qc.contextid, qc.name AS categoryname
                  FROM {question} q
                  JOIN {question_versions} qv ON qv.questionid = q.id
                  JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                  JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                  JOIN {context} ctx ON ctx.id = qc.contextid
                 WHERE q.id = :questionid";
        $target = $DB->get_record_sql($sql, ['questionid' => $questionid], IGNORE_MULTIPLE);
        return $target ?: null;
    }

    /**
     * Copie un fichier orphelin dans la zone « texte de la question » d'une question choisie.
     *
     * @param int $fileid Id du fichier orphelin
     * @param int $questionid Id de la question cible (choisie explicitement par l'administrateur)
     * @param bool $dryrun Mode simulation
     * @return array ['success' => bool, 'message' => string, 'newfileid' => int|null]
     */
    public static function copy_to_question(int $fileid, int $questionid, bool $dryrun = false): array {
        global $DB;

        $record = $DB->get_record('files', ['id' => $fileid]);
        if (!$record) {
            return self::result(false, get_string('repair_file_not_found', 'local_question_diagnostic'));
        }
        // Revérification au moment de l'exécution.
        if (!self::can_repair($record)) {
            return self::result(false, get_string('orphan_repair_not_allowed', 'local_question_diagnostic'));
        }
        $target = self::get_question_target($questionid);
        if (!$target) {
            return self::result(false, get_string('repair_target_not_found', 'local_question_diagnostic'));
        }

        $fs = get_file_storage();
        $filename = $fs->get_unused_filename($target->contextid, 'question', self::TARGET_FILEAREA,
            $target->id, '/', $record->filename);

        if ($dryrun) {
            return self::result(true, get_string('orphan_repair_dryrun', 'local_question_diagnostic',
                (object)['fileid' => $fileid, 'questionid' => $target->id]));
        }

        $storedfile = $fs->get_file_by_id($record->id);
        if (!$storedfile) {
            return self::result(false, get_string('repair_file_not_found', 'local_question_diagnostic'));
        }

        $newrecord = [
            'contextid' => $target->contextid,
            'component' => 'question',
            'filearea' => self::TARGET_FILEAREA,
            'itemid' => $target->id,
            'filepath' => '/',
            'filename' => $filename,
        ];
        try {
            $newfile = $fs->create_file_from_storedfile($newrecord, $storedfile);
        } catch (\Throwable $e) {
            return self::result(false, get_string('repair_error', 'local_question_diagnostic'));
        }

        // Traçabilité sans données personnelles.
        audit_logger::log_action('orphan_file_copied', [
            'fileid' => $fileid,
            'newfileid' => (int)$newfile->get_id(),
            'questionid' => (int)$target->id,
            'contextid' => (int)$target->contextid,
        ]);
        orphan_file_detector::purge_orphan_cache();

        return self::result(true, get_string('orphan_repair_success', 'local_question_diagnostic',
            (object)['fileid' => $fileid, 'questionid' => $target->id]), (int)$newfile->get_id());
    }

    /**
     * Construit un tableau de résultat homogène.
     *
     * @param bool $success
     * @param string $message
     * @param int|null $newfileid
     * @return array
     */
    private static function result(bool $success, string $message, ?int $newfileid = null): array {
        return ['success' => $success, 'message' => $message, 'newfileid' => $newfileid];
    }
}
