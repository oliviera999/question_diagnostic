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
 * Empreinte complète du contenu d'une question, pour la détection des doublons.
 *
 * L'empreinte est calculée sur l'export XML de Moodle (qformat_xml) : énoncé, réponses, options du type,
 * sous-questions, jeux de données, indices, retours, note par défaut, pénalité et CONTENU des fichiers
 * (encodé en base64 par l'export). On en retire ce qui n'est pas du contenu : nom, idnumber, statut,
 * étiquettes et commentaires portant des identifiants.
 *
 * Deux questions n'ont la même empreinte que si un élève ne peut pas les distinguer.
 *
 * @package    local_question_diagnostic
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_fingerprint {

    /** @var array<int,string|null> empreintes déjà calculées dans la requête */
    private static array $cache = [];

    /**
     * Charge les bibliothèques des questions et du format XML.
     */
    private static function require_libs(): void {
        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');
        require_once($CFG->dirroot . '/question/engine/bank.php');
        require_once($CFG->dirroot . '/question/format.php');
        require_once($CFG->dirroot . '/question/format/xml/format.php');
    }

    /**
     * Vide le cache interne.
     */
    public static function reset_cache(): void {
        self::$cache = [];
    }

    /**
     * Export XML d'une version de question, fichiers compris.
     *
     * @param int $questionid
     * @return string
     */
    public static function export_xml(int $questionid): string {
        self::require_libs();
        $data = \question_bank::load_question_data($questionid);
        $format = new \qformat_xml();
        return $format->writequestion($data);
    }

    /**
     * Retire de l'export XML ce qui n'est pas du contenu.
     *
     * @param string $xml
     * @return string
     */
    public static function normalise(string $xml): string {
        $xml = preg_replace('~<!--.*?-->~s', '', $xml);
        foreach (['name', 'idnumber', 'hidden', 'tags', 'coursetags'] as $tag) {
            $xml = preg_replace('~<' . $tag . '>.*?</' . $tag . '>~s', '', $xml);
        }
        // Les espaces de mise en forme de l'export ne sont pas du contenu.
        $xml = preg_replace('~>\s+<~', '><', $xml);
        return trim($xml);
    }

    /**
     * Empreinte d'une question, ou null si elle ne peut pas être calculée (question illisible, type absent…).
     *
     * Une question sans empreinte n'est JAMAIS un doublon.
     *
     * @param int $questionid
     * @return string|null
     */
    public static function compute(int $questionid): ?string {
        if (array_key_exists($questionid, self::$cache)) {
            return self::$cache[$questionid];
        }
        try {
            $fp = sha1(self::normalise(self::export_xml($questionid)));
        } catch (\Throwable $e) {
            debugging('question_fingerprint::compute(' . $questionid . ') : ' . $e->getMessage(), DEBUG_DEVELOPER);
            $fp = null;
        }
        self::$cache[$questionid] = $fp;
        return $fp;
    }

    /**
     * Ne garde, parmi des candidats, que ceux dont l'empreinte est celle de la référence.
     *
     * @param int[] $candidateids
     * @param int $referenceid
     * @return int[] identifiants identiques à la référence (référence comprise), vide si moins de 2
     */
    public static function filter_identical(array $candidateids, int $referenceid): array {
        $reffp = self::compute($referenceid);
        if ($reffp === null) {
            return [];
        }
        $out = [];
        foreach ($candidateids as $id) {
            $id = (int)$id;
            if ($id === $referenceid || self::compute($id) === $reffp) {
                $out[] = $id;
            }
        }
        if (!in_array($referenceid, $out, true)) {
            return [];
        }
        sort($out);
        return count($out) >= 2 ? $out : [];
    }
}
