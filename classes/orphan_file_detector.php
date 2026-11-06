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

/**
 * Détecteur de fichiers orphelins dans Moodle.
 *
 * Définition (volontairement stricte) : un fichier est orphelin UNIQUEMENT si son
 * contexte (files.contextid) n'existe plus dans {context}. Aucune comparaison n'est
 * faite entre files.itemid et l'id de la table « principale » d'un composant : la
 * signification de itemid dépend de la zone de fichiers (question_answers.id,
 * question_hints.id, question_attempt_steps.id, forum_posts.id, book_chapters.id,
 * course_sections.id, identifiant de brouillon…) et cette comparaison signalait
 * comme « orphelins » des fichiers bien vivants (travaux d'élèves, images de réponses).
 *
 * Certaines zones sont en outre exclues de toute suppression ou réparation
 * (signalement uniquement) : brouillons utilisateur, réponses aux questions
 * (response_*), remises et feedbacks de devoirs, sauvegardes.
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class orphan_file_detector {

    /** @var string Seul type d'orphelin reconnu : contexte disparu. */
    const TYPE_CONTEXT_MISSING = 'context_missing';

    /** @var int Nombre maximal de fichiers traités par lot (suppression, archivage). */
    const MAX_BATCH = 100;

    /** @var string Sous-dossier de moodledata (hors temp/, donc non purgé par le cron). */
    const ARCHIVE_SUBDIR = 'local_question_diagnostic/orphan_archive';

    /** @var string[] Préfixes de composants protégés (travaux et feedbacks de devoirs). */
    const PROTECTED_COMPONENT_PREFIXES = ['assignsubmission_', 'assignfeedback_'];

    /** @var string[] Composants protégés (sauvegardes). */
    const PROTECTED_COMPONENTS = ['backup'];

    /**
     * Fragment SQL commun : fichiers dont le contexte n'existe plus (hors entrées de dossier).
     *
     * @return string
     */
    private static function orphan_from_where_sql(): string {
        return "FROM {files} f
           LEFT JOIN {context} ctx ON ctx.id = f.contextid
               WHERE ctx.id IS NULL
                 AND f.filename <> '.'";
    }

    /**
     * Ouvre un recordset sur les fichiers orphelins (contexte disparu), du plus récent au plus ancien.
     *
     * @param int $limitfrom Décalage (pagination)
     * @param int $limitnum Nombre maximal d'enregistrements (0 = tous)
     * @return \moodle_recordset
     */
    public static function get_orphan_recordset(int $limitfrom = 0, int $limitnum = 0): \moodle_recordset {
        global $DB;
        $sql = "SELECT f.* " . self::orphan_from_where_sql() . " ORDER BY f.timecreated DESC, f.id DESC";
        return $DB->get_recordset_sql($sql, [], $limitfrom, $limitnum);
    }

    /**
     * Parcourt les fichiers orphelins sous forme d'objets enrichis (générateur, mémoire constante).
     *
     * @param int $limit Limite (0 = tous)
     * @param int $offset Décalage
     * @return \Generator
     */
    public static function iterate_orphans(int $limit = 0, int $offset = 0): \Generator {
        $rs = self::get_orphan_recordset($offset, $limit);
        try {
            foreach ($rs as $file) {
                yield self::create_orphan_object($file);
            }
        } finally {
            $rs->close();
        }
    }

    /**
     * Récupère les fichiers orphelins (contexte disparu).
     *
     * @param bool $use_cache Utiliser le cache
     * @param int $limit Limite de résultats (0 = tous)
     * @param int $offset Décalage (pagination)
     * @return \stdClass[] Objets orphelins
     */
    public static function get_orphan_files($use_cache = true, $limit = 1000, $offset = 0) {
        require_once(__DIR__ . '/cache_manager.php');
        $limit = max(0, (int)$limit);
        $offset = max(0, (int)$offset);
        $cache_key = 'orphan_files_v2_' . $limit . '_' . $offset;
        if ($use_cache) {
            $cached = cache_manager::get(cache_manager::CACHE_ORPHANFILES, $cache_key);
            if ($cached !== false && is_array($cached)) {
                return $cached;
            }
        }

        $orphans = iterator_to_array(self::iterate_orphans($limit, $offset), false);

        if ($use_cache) {
            cache_manager::set(cache_manager::CACHE_ORPHANFILES, $cache_key, $orphans);
        }
        return $orphans;
    }

    /**
     * Récupère, parmi une liste d'ids, ceux qui sont réellement orphelins (revérification en base).
     *
     * @param int[] $fileids
     * @return \stdClass[] Objets orphelins indexés par id de fichier
     */
    public static function get_orphans_by_ids(array $fileids): array {
        global $DB;
        $fileids = array_values(array_unique(array_filter(array_map('intval', $fileids))));
        if (empty($fileids)) {
            return [];
        }
        $result = [];
        foreach (array_chunk($fileids, 500) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'fid');
            $sql = "SELECT f.* " . self::orphan_from_where_sql() .
                   " AND f.id {$insql} ORDER BY f.timecreated DESC, f.id DESC";
            $rs = $DB->get_recordset_sql($sql, $params);
            foreach ($rs as $file) {
                $result[$file->id] = self::create_orphan_object($file);
            }
            $rs->close();
        }
        return $result;
    }

    /**
     * Crée un objet orphelin enrichi.
     *
     * @param \stdClass $file Enregistrement de {files}
     * @return \stdClass
     */
    private static function create_orphan_object($file) {
        $exclusion = self::get_exclusion_reason($file);
        return (object)[
            'file' => $file,
            'orphan_type' => self::TYPE_CONTEXT_MISSING,
            'reason' => get_string('orphan_reason_context_missing', 'local_question_diagnostic', (int)$file->contextid),
            'deletable' => ($exclusion === null),
            'exclusion' => $exclusion,
            'filesize_formatted' => self::format_filesize($file->filesize),
            'timecreated_formatted' => userdate($file->timecreated, get_string('strftimedatetime', 'langconfig')),
            'age_days' => (int)floor((time() - $file->timecreated) / DAYSECS),
        ];
    }

    /**
     * Statistiques globales (requêtes agrégées, sans plafond de 1000 fichiers).
     *
     * @param bool $use_cache Utiliser le cache
     * @return \stdClass
     */
    public static function get_global_stats($use_cache = true) {
        global $DB;

        require_once(__DIR__ . '/cache_manager.php');
        if ($use_cache) {
            $cached = cache_manager::get(cache_manager::CACHE_ORPHANFILES, 'global_stats_v2');
            if ($cached !== false && is_object($cached)) {
                return $cached;
            }
        }

        $now = time();
        $sql = "SELECT f.component, f.filearea,
                       COUNT(1) AS cnt,
                       SUM(f.filesize) AS totalsize,
                       SUM(CASE WHEN f.timecreated > :recentlimit THEN 1 ELSE 0 END) AS recent,
                       SUM(CASE WHEN f.timecreated <= :oldlimit THEN 1 ELSE 0 END) AS old
                " . self::orphan_from_where_sql() . "
              GROUP BY f.component, f.filearea";
        $params = ['recentlimit' => $now - 30 * DAYSECS, 'oldlimit' => $now - 180 * DAYSECS];

        $stats = new \stdClass();
        $stats->total_orphans = 0;
        $stats->total_filesize = 0;
        $stats->deletable = 0;
        $stats->protected = 0;
        $stats->by_component = [];
        $stats->by_type = [self::TYPE_CONTEXT_MISSING => 0];
        $stats->by_age = ['recent' => 0, 'medium' => 0, 'old' => 0];

        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $row) {
            $cnt = (int)$row->cnt;
            $stats->total_orphans += $cnt;
            $stats->total_filesize += (int)$row->totalsize;
            $stats->by_type[self::TYPE_CONTEXT_MISSING] += $cnt;
            $component = (string)$row->component;
            $stats->by_component[$component] = ($stats->by_component[$component] ?? 0) + $cnt;
            $stats->by_age['recent'] += (int)$row->recent;
            $stats->by_age['old'] += (int)$row->old;
            $stats->by_age['medium'] += $cnt - (int)$row->recent - (int)$row->old;
            $probe = (object)['component' => $row->component, 'filearea' => $row->filearea, 'filename' => 'x'];
            if (self::get_exclusion_reason($probe) === null) {
                $stats->deletable += $cnt;
            } else {
                $stats->protected += $cnt;
            }
        }
        $rs->close();
        arsort($stats->by_component);

        $stats->total_filesize_formatted = self::format_filesize($stats->total_filesize);

        if ($use_cache) {
            cache_manager::set(cache_manager::CACHE_ORPHANFILES, 'global_stats_v2', $stats);
        }
        return $stats;
    }

    /**
     * Indique si le fichier appartient à une zone protégée (jamais supprimée ni réparée).
     *
     * @param \stdClass $file Enregistrement de {files} (component, filearea, filename)
     * @return string|null Code de la raison d'exclusion, ou null si le fichier n'est pas protégé
     */
    public static function get_exclusion_reason($file): ?string {
        $component = (string)($file->component ?? '');
        $filearea = (string)($file->filearea ?? '');

        if (($file->filename ?? '') === '.') {
            return 'directory';
        }
        if ($component === '') {
            return 'nocomponent';
        }
        if ($component === 'user' && $filearea === 'draft') {
            return 'draft';
        }
        if (strpos($filearea, 'response_') === 0) {
            return 'response';
        }
        foreach (self::PROTECTED_COMPONENT_PREFIXES as $prefix) {
            if (strpos($component, $prefix) === 0) {
                return 'assign';
            }
        }
        if (in_array($component, self::PROTECTED_COMPONENTS, true)) {
            return 'backup';
        }
        return null;
    }

    /**
     * Indique si un fichier est orphelin : son contexte n'existe plus (vérification en base, à l'instant T).
     *
     * @param \stdClass $file Enregistrement de {files}
     * @return bool
     */
    public static function is_orphan($file): bool {
        global $DB;
        if (empty($file) || ($file->filename ?? '.') === '.') {
            return false;
        }
        return !$DB->record_exists('context', ['id' => (int)$file->contextid]);
    }

    /**
     * Vérifie qu'un fichier peut être supprimé : orphelin ET hors zones protégées.
     *
     * @param \stdClass $file Enregistrement de {files}
     * @return bool
     */
    public static function is_safe_to_delete($file) {
        if (empty($file)) {
            return false;
        }
        if (self::get_exclusion_reason($file) !== null) {
            return false;
        }
        return self::is_orphan($file);
    }

    /**
     * Supprime un fichier orphelin après l'avoir copié dans l'archive durable.
     *
     * @param int $fileid ID du fichier
     * @param bool $dry_run Mode simulation (rien n'est écrit ni supprimé)
     * @return array ['success' => bool, 'message' => string]
     */
    public static function delete_orphan_file($fileid, $dry_run = false) {
        global $DB;

        $fileid = (int)$fileid;
        $record = $DB->get_record('files', ['id' => $fileid]);
        if (!$record) {
            return ['success' => false, 'message' => get_string('orphan_file_not_found', 'local_question_diagnostic', $fileid)];
        }
        // Revérification au moment de l'exécution : on ne fait jamais confiance aux ids reçus.
        if (!self::is_safe_to_delete($record)) {
            return ['success' => false, 'message' => get_string('orphan_not_deletable', 'local_question_diagnostic', $fileid)];
        }
        if ($dry_run) {
            return ['success' => true, 'message' => get_string('orphan_dryrun_would_delete', 'local_question_diagnostic', $fileid)];
        }

        $fs = get_file_storage();
        $file = $fs->get_file_by_id($record->id);
        if (!$file) {
            return ['success' => false, 'message' => get_string('orphan_file_not_found', 'local_question_diagnostic', $fileid)];
        }

        // 1. Archivage durable du contenu : si la copie échoue, on ne supprime pas.
        try {
            self::archive_stored_file($file, 'delete');
        } catch (\Throwable $e) {
            return ['success' => false,
                'message' => get_string('orphan_archive_failed_nodelete', 'local_question_diagnostic', $fileid)];
        }

        // 2. Suppression via l'API de fichiers (enregistrement + contenu si plus référencé ailleurs).
        try {
            $file->delete();
            self::delete_empty_directories($record);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => get_string('orphan_delete_failed', 'local_question_diagnostic', $fileid)];
        }

        return ['success' => true, 'message' => get_string('orphan_deleted_one', 'local_question_diagnostic', $fileid)];
    }

    /**
     * Supprime les entrées « dossier » ('.') d'une zone orpheline devenue vide.
     *
     * @param \stdClass $record Enregistrement du fichier supprimé
     */
    private static function delete_empty_directories(\stdClass $record): void {
        global $DB;
        if (!self::is_orphan((object)['contextid' => $record->contextid, 'filename' => 'x'])) {
            return;
        }
        $params = [
            'contextid' => $record->contextid,
            'component' => $record->component,
            'filearea' => $record->filearea,
            'itemid' => $record->itemid,
        ];
        $remaining = $DB->count_records_select('files',
            "contextid = :contextid AND component = :component AND filearea = :filearea AND itemid = :itemid
             AND filename <> '.'", $params);
        if ($remaining > 0) {
            return;
        }
        $fs = get_file_storage();
        $dirs = $DB->get_records('files', $params + ['filename' => '.'], '', 'id');
        foreach ($dirs as $dir) {
            if ($stored = $fs->get_file_by_id($dir->id)) {
                $stored->delete();
            }
        }
    }

    /**
     * Supprime plusieurs fichiers orphelins (lot limité à MAX_BATCH), chacun archivé au préalable.
     *
     * @param int[] $fileids
     * @param bool $dry_run Mode simulation
     * @return array ['success' => int, 'failed' => int, 'messages' => string[], 'deletedids' => int[]]
     */
    public static function delete_multiple_orphans($fileids, $dry_run = false) {
        $results = ['success' => 0, 'failed' => 0, 'messages' => [], 'deletedids' => []];

        $fileids = array_values(array_unique(array_map('intval', (array)$fileids)));
        if (count($fileids) > self::MAX_BATCH) {
            $fileids = array_slice($fileids, 0, self::MAX_BATCH);
            $results['messages'][] = get_string('orphan_batch_limited', 'local_question_diagnostic', self::MAX_BATCH);
        }

        foreach ($fileids as $fileid) {
            $result = self::delete_orphan_file($fileid, $dry_run);
            if ($result['success']) {
                $results['success']++;
                if (!$dry_run) {
                    $results['deletedids'][] = $fileid;
                }
            } else {
                $results['failed']++;
            }
            $results['messages'][] = $result['message'];
        }

        if (!$dry_run && !empty($results['deletedids'])) {
            // Traçabilité sans données personnelles : uniquement des identifiants techniques.
            audit_logger::log_action('orphan_files_deleted', [
                'count' => count($results['deletedids']),
                'fileids' => $results['deletedids'],
                'archive' => self::ARCHIVE_SUBDIR . '/' . date('Y-m-d'),
            ]);
        }
        if (!$dry_run) {
            self::purge_orphan_cache();
        }
        return $results;
    }

    /**
     * Archive (copie) un fichier orphelin dans l'archive durable, sans le supprimer.
     *
     * @param int $fileid ID du fichier
     * @return array ['success' => bool, 'message' => string, 'archive_path' => string]
     */
    public static function archive_orphan_file($fileid) {
        global $DB;

        $fileid = (int)$fileid;
        $record = $DB->get_record('files', ['id' => $fileid]);
        if (!$record || !self::is_orphan($record)) {
            return ['success' => false, 'archive_path' => '',
                'message' => get_string('orphan_not_orphan', 'local_question_diagnostic', $fileid)];
        }
        $file = get_file_storage()->get_file_by_id($record->id);
        if (!$file) {
            return ['success' => false, 'archive_path' => '',
                'message' => get_string('orphan_file_not_found', 'local_question_diagnostic', $fileid)];
        }
        try {
            $path = self::archive_stored_file($file, 'archive');
        } catch (\Throwable $e) {
            return ['success' => false, 'archive_path' => '',
                'message' => get_string('archive_error', 'local_question_diagnostic')];
        }
        return ['success' => true, 'archive_path' => $path,
            'message' => get_string('orphan_archived_one', 'local_question_diagnostic', $fileid)];
    }

    /**
     * Archive plusieurs fichiers orphelins (lot limité à MAX_BATCH) et trace l'opération.
     *
     * @param int[] $fileids
     * @return array ['success' => int, 'failed' => int, 'archive_dir' => string, 'archivedids' => int[]]
     */
    public static function archive_multiple_orphans(array $fileids): array {
        $fileids = array_slice(array_values(array_unique(array_map('intval', $fileids))), 0, self::MAX_BATCH);
        $results = ['success' => 0, 'failed' => 0, 'archive_dir' => self::get_archive_dir(), 'archivedids' => []];
        foreach ($fileids as $fileid) {
            $result = self::archive_orphan_file($fileid);
            if ($result['success']) {
                $results['success']++;
                $results['archivedids'][] = $fileid;
            } else {
                $results['failed']++;
            }
        }
        if (!empty($results['archivedids'])) {
            audit_logger::log_action('orphan_files_archived', [
                'count' => count($results['archivedids']),
                'fileids' => $results['archivedids'],
                'archive' => self::ARCHIVE_SUBDIR . '/' . date('Y-m-d'),
            ]);
        }
        return $results;
    }

    /**
     * Chemin du dossier d'archive du jour, hors de temp/ pour ne pas être purgé par le cron.
     *
     * @return string
     */
    public static function get_archive_dir(): string {
        global $CFG;
        return $CFG->dataroot . '/' . self::ARCHIVE_SUBDIR . '/' . date('Y-m-d');
    }

    /**
     * Copie le contenu d'un fichier dans l'archive durable et ajoute ses métadonnées à metadata.json.
     *
     * Les métadonnées ne contiennent aucune donnée personnelle (ni auteur, ni utilisateur) :
     * uniquement des identifiants techniques, le nom du fichier et ses dates.
     *
     * @param \stored_file $file
     * @param string $operation 'archive' ou 'delete'
     * @return string Chemin du fichier archivé
     * @throws \moodle_exception si la copie échoue
     */
    public static function archive_stored_file(\stored_file $file, string $operation): string {
        $dir = make_writable_directory(self::get_archive_dir());
        $contenthash = $file->get_contenthash();
        $target = $dir . '/' . $file->get_id() . '_' . $contenthash;

        if (!file_exists($target) || sha1_file($target) !== $contenthash) {
            if (!$file->copy_content_to($target) || !file_exists($target)) {
                throw new \moodle_exception('archive_error', 'local_question_diagnostic');
            }
            if (sha1_file($target) !== $contenthash) {
                @unlink($target);
                throw new \moodle_exception('archive_error', 'local_question_diagnostic');
            }
        }

        self::append_archive_metadata($dir . '/metadata.json', [
            'fileid' => (int)$file->get_id(),
            'contextid' => (int)$file->get_contextid(),
            'component' => $file->get_component(),
            'filearea' => $file->get_filearea(),
            'itemid' => (int)$file->get_itemid(),
            'filepath' => $file->get_filepath(),
            'filename' => $file->get_filename(),
            'contenthash' => $contenthash,
            'filesize' => (int)$file->get_filesize(),
            'mimetype' => $file->get_mimetype(),
            'timecreated' => (int)$file->get_timecreated(),
            'archivedat' => time(),
            'operation' => $operation,
            'archivefile' => basename($target),
        ]);
        return $target;
    }

    /**
     * Ajoute une entrée au fichier metadata.json sous verrou exclusif.
     *
     * @param string $metadatafile
     * @param array $entry
     */
    private static function append_archive_metadata(string $metadatafile, array $entry): void {
        $handle = fopen($metadatafile, 'c+');
        if (!$handle) {
            throw new \moodle_exception('archive_error', 'local_question_diagnostic');
        }
        try {
            flock($handle, LOCK_EX);
            $existing = stream_get_contents($handle);
            $metadata = $existing ? json_decode($existing, true) : [];
            if (!is_array($metadata)) {
                $metadata = [];
            }
            $metadata[] = $entry;
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Formate une taille de fichier de manière lisible.
     *
     * @param int $bytes Taille en octets
     * @return string
     */
    public static function format_filesize($bytes) {
        return display_size((int)$bytes);
    }

    /**
     * Purge le cache des fichiers orphelins.
     *
     * @return bool
     */
    public static function purge_orphan_cache() {
        require_once(__DIR__ . '/cache_manager.php');
        return cache_manager::purge_cache(cache_manager::CACHE_ORPHANFILES);
    }

    /**
     * Neutralise une cellule CSV contre l'injection de formules (=, +, -, @, tabulation, retour chariot).
     *
     * @param mixed $value
     * @return string
     */
    public static function csv_safe_cell($value): string {
        $value = (string)$value;
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            $value = "'" . $value;
        }
        return $value;
    }

    /**
     * Écrit les fichiers orphelins au format CSV dans un flux.
     *
     * @param iterable $orphans Objets orphelins (voir create_orphan_object)
     * @param resource $handle Flux de sortie
     * @return int Nombre de lignes écrites (hors en-tête)
     */
    public static function write_csv(iterable $orphans, $handle): int {
        fwrite($handle, "\xEF\xBB\xBF"); // BOM UTF-8 pour Excel.
        $headers = ['ID', 'Filename', 'Component', 'Filearea', 'Item ID', 'Filesize', 'Orphan Type',
            'Deletable', 'Reason', 'Created', 'Age (days)', 'Context ID'];
        fputcsv($handle, $headers, ',', '"', '');
        $count = 0;
        foreach ($orphans as $orphan) {
            $file = $orphan->file;
            $row = [
                $file->id,
                $file->filename,
                $file->component,
                $file->filearea,
                $file->itemid,
                $file->filesize,
                $orphan->orphan_type,
                $orphan->deletable ? 'yes' : 'no (' . $orphan->exclusion . ')',
                $orphan->reason,
                $orphan->timecreated_formatted,
                $orphan->age_days,
                $file->contextid,
            ];
            fputcsv($handle, array_map([self::class, 'csv_safe_cell'], $row), ',', '"', '');
            $count++;
        }
        return $count;
    }

    /**
     * Export des fichiers orphelins en CSV (chaîne).
     *
     * @param iterable $orphans
     * @return string
     */
    public static function export_to_csv($orphans) {
        $handle = fopen('php://temp', 'w+');
        self::write_csv($orphans, $handle);
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);
        return $csv;
    }

    /**
     * Rend un bouton de formulaire POST (sesskey inclus) ; gère les paramètres tableaux (ids[]).
     *
     * @param \moodle_url $action URL cible (ses paramètres de requête sont ignorés)
     * @param array $params Paramètres cachés ; une valeur tableau produit plusieurs champs name[]
     * @param string $label Libellé (texte brut, échappé ici)
     * @param string $class Classes CSS du bouton
     * @return string HTML
     */
    public static function render_post_button(\moodle_url $action, array $params, string $label,
            string $class = 'btn btn-primary'): string {
        $html = \html_writer::start_tag('form', ['method' => 'post', 'action' => $action->out_omit_querystring(),
            'class' => 'd-inline-block mr-2']);
        $html .= \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        foreach ($params as $name => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $html .= \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name . '[]', 'value' => $v]);
                }
            } else {
                $html .= \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
            }
        }
        $html .= \html_writer::tag('button', s($label), ['type' => 'submit', 'class' => $class]);
        $html .= \html_writer::end_tag('form');
        return $html;
    }
}
