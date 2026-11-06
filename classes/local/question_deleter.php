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
 * Suppression sûre de questions : seul point du plugin qui supprime des questions.
 *
 * Pour chaque question demandée, c'est toute son ENTRÉE (toutes les versions) qui est supprimée, et seulement si :
 * - aucune version n'est utilisée (questions_in_use() du cœur : tentatives, références, plugins) ;
 * - sa catégorie n'est pas un réservoir de tirage aléatoire (sauf autorisation explicite).
 *
 * Avant toute suppression, les versions concernées sont exportées au format XML de Moodle dans
 * $CFG->dataroot/local_question_diagnostic/exports/ (hors du dossier temporaire, purgé après la durée
 * de conservation réglée). Si l'export échoue, rien n'est supprimé.
 *
 * Après l'appel du cœur, on vérifie que la ligne a bien disparu : une question que le cœur s'est contenté
 * de cacher est signalée comme telle, jamais comme supprimée.
 *
 * @package    local_question_diagnostic
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_deleter {

    /** Raison de refus : une version est utilisée. */
    const SKIP_USED = 'used';
    /** Raison de refus : la catégorie est un réservoir de tirage aléatoire. */
    const SKIP_RANDOM = 'randompool';
    /** Raison de refus : question introuvable. */
    const SKIP_MISSING = 'missing';
    /** Raison de refus : le cœur a caché la question au lieu de la supprimer. */
    const SKIP_HIDDEN = 'hiddenbycore';
    /** Raison de refus : erreur pendant la suppression. */
    const SKIP_ERROR = 'error';

    /**
     * Dossier des exports de sauvegarde.
     *
     * @return string
     */
    public static function export_dir(): string {
        global $CFG;
        return $CFG->dataroot . '/local_question_diagnostic/exports';
    }

    /**
     * Vérifie, sans rien supprimer, quelles questions peuvent l'être.
     *
     * @param int[] $questionids
     * @param bool $allowrandompool autoriser la suppression dans un réservoir de tirage aléatoire
     * @return array<int,string|null> questionid => null si supprimable, sinon la raison (constante SKIP_*)
     */
    public static function check(array $questionids, bool $allowrandompool = false): array {
        global $DB;
        $questionids = array_values(array_unique(array_filter(array_map('intval', $questionids))));
        if (!$questionids) {
            return [];
        }
        $out = [];
        [$insql, $params] = $DB->get_in_or_equal($questionids);
        $existing = $DB->get_fieldset_select('question', 'id', "id $insql", $params);
        $existing = array_flip(array_map('intval', $existing));
        $inuse = usage_checker::entries_in_use($questionids);
        $inpool = $allowrandompool ? [] : usage_checker::questions_in_random_pool($questionids);
        foreach ($questionids as $qid) {
            if (!isset($existing[$qid])) {
                $out[$qid] = self::SKIP_MISSING;
            } else if (!empty($inuse[$qid]) || !array_key_exists($qid, $inuse)) {
                $out[$qid] = self::SKIP_USED;
            } else if (!$allowrandompool && (!empty($inpool[$qid]) || !array_key_exists($qid, $inpool))) {
                $out[$qid] = self::SKIP_RANDOM;
            } else {
                $out[$qid] = null;
            }
        }
        return $out;
    }

    /**
     * Supprime les entrées des questions données, après contrôle et export.
     *
     * @param int[] $questionids
     * @param string $reason libellé court de l'opération (nom du fichier d'export, journal)
     * @param bool $allowrandompool autoriser la suppression dans un réservoir de tirage aléatoire
     * @return \stdClass {deleted: int[], skipped: array<int,string>, exportfile: ?string}
     */
    public static function delete_entries(array $questionids, string $reason, bool $allowrandompool = false): \stdClass {
        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');

        $result = (object)['deleted' => [], 'skipped' => [], 'exportfile' => null];
        $checks = self::check($questionids, $allowrandompool);
        $todelete = [];
        foreach ($checks as $qid => $skip) {
            if ($skip === null) {
                $todelete[] = $qid;
            } else {
                $result->skipped[$qid] = $skip;
            }
        }
        if (!$todelete) {
            return $result;
        }

        $versions = usage_checker::get_entry_versions($todelete);

        // Export préalable : sans export, pas de suppression.
        $result->exportfile = self::export($versions, $reason);

        foreach ($versions as $qid => $versionids) {
            try {
                // Les versions les plus récentes d'abord, pour garder l'entrée cohérente jusqu'au bout.
                rsort($versionids);
                foreach ($versionids as $vid) {
                    question_delete_question($vid);
                }
                $remaining = self::remaining($versionids);
                if ($remaining) {
                    $result->skipped[$qid] = self::SKIP_HIDDEN;
                } else {
                    $result->deleted[] = $qid;
                }
            } catch (\Throwable $e) {
                debugging('question_deleter : ' . $e->getMessage(), DEBUG_DEVELOPER);
                $result->skipped[$qid] = self::SKIP_ERROR;
            }
        }
        usage_checker::reset_caches();
        \local_question_diagnostic\audit_logger::log_action('questions_deleted', [
            'reason' => $reason,
            'deleted' => $result->deleted,
            'skipped' => $result->skipped,
            'exportfile' => $result->exportfile ? basename($result->exportfile) : null,
        ]);
        return $result;
    }

    /**
     * Versions encore présentes en base parmi celles données.
     *
     * @param int[] $questionids
     * @return int[]
     */
    private static function remaining(array $questionids): array {
        global $DB;
        if (!$questionids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($questionids);
        return array_map('intval', $DB->get_fieldset_select('question', 'id', "id $insql", $params));
    }

    /**
     * Exporte les versions au format XML de Moodle avant suppression.
     *
     * @param array<int,int[]> $versions questionid demandé => versions
     * @param string $reason
     * @return string chemin du fichier écrit
     * @throws \moodle_exception si l'export échoue
     */
    public static function export(array $versions, string $reason): string {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<quiz>\n";
        foreach ($versions as $versionids) {
            foreach ($versionids as $vid) {
                $xml .= question_fingerprint::export_xml((int)$vid);
            }
        }
        $xml .= "</quiz>\n";

        $dir = self::export_dir();
        if (!check_dir_exists($dir, true, true)) {
            throw new \moodle_exception('error_export_dir', 'local_question_diagnostic', '', $dir);
        }
        $slug = preg_replace('~[^a-z0-9_-]+~', '-', \core_text::strtolower($reason));
        $file = $dir . '/' . date('Ymd-His') . '-' . trim($slug, '-') . '-' . random_string(6) . '.xml';
        if (file_put_contents($file, $xml) === false) {
            throw new \moodle_exception('error_export_write', 'local_question_diagnostic', '', $file);
        }
        return $file;
    }

    /**
     * Supprime les exports plus anciens que la durée de conservation.
     *
     * @param int $days durée de conservation en jours
     * @return int nombre de fichiers supprimés
     */
    public static function purge_old_exports(int $days): int {
        $dir = self::export_dir();
        if ($days <= 0 || !is_dir($dir)) {
            return 0;
        }
        $limit = time() - $days * DAYSECS;
        $count = 0;
        foreach (glob($dir . '/*.xml') ?: [] as $file) {
            if (filemtime($file) < $limit && @unlink($file)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Libellé lisible d'une raison de refus.
     *
     * @param string $skip constante SKIP_*
     * @return string
     */
    public static function skip_label(string $skip): string {
        return get_string('deleter_skip_' . $skip, 'local_question_diagnostic');
    }
}
