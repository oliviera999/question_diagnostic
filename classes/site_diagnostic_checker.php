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

/**
 * Site-wide diagnostic helper (DB + resources).
 *
 * This class is intentionally read-only: it only computes metrics.
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class site_diagnostic_checker {

    /**
     * Check if a list of tables exists in the current DB.
     *
     * @param string[] $tables
     * @return array[] Each item: ['table' => string, 'exists' => bool]
     */
    public static function get_table_existence(array $tables): array {
        global $DB;

        $dbman = $DB->get_manager();
        $out = [];

        foreach ($tables as $table) {
            $table = (string)$table;
            if ($table === '') {
                continue;
            }
            $exists = false;
            try {
                $exists = (bool)$dbman->table_exists($table);
            } catch (\Throwable $e) {
                $exists = false;
            }
            $out[] = [
                'table' => $table,
                'exists' => $exists,
            ];
        }

        return $out;
    }

    /**
     * Basic course + context integrity metrics.
     *
     * @return \stdClass
     */
    public static function get_course_integrity_stats(): \stdClass {
        global $DB;

        $stats = new \stdClass();

        // Courses referencing missing course categories.
        $sql = "SELECT COUNT(1)
                  FROM {course} c
             LEFT JOIN {course_categories} cc ON cc.id = c.category
                 WHERE c.id <> :siteid
                   AND cc.id IS NULL";
        $stats->courses_missing_category = (int)$DB->count_records_sql($sql, ['siteid' => SITEID]);

        // Course categories referencing a missing parent.
        $sql = "SELECT COUNT(1)
                  FROM {course_categories} cc
             LEFT JOIN {course_categories} parent ON parent.id = cc.parent
                 WHERE cc.parent <> 0
                   AND parent.id IS NULL";
        $stats->coursecats_missing_parent = (int)$DB->count_records_sql($sql);

        // Courses without a course context.
        $sql = "SELECT COUNT(1)
                  FROM {course} c
             LEFT JOIN {context} ctx
                    ON ctx.contextlevel = :lvl
                   AND ctx.instanceid = c.id
                 WHERE c.id <> :siteid
                   AND ctx.id IS NULL";
        $stats->courses_missing_context = (int)$DB->count_records_sql($sql, [
            'lvl' => CONTEXT_COURSE,
            'siteid' => SITEID,
        ]);

        // Course categories without a coursecat context.
        $sql = "SELECT COUNT(1)
                  FROM {course_categories} cc
             LEFT JOIN {context} ctx
                    ON ctx.contextlevel = :lvl
                   AND ctx.instanceid = cc.id
                 WHERE ctx.id IS NULL";
        $stats->coursecats_missing_context = (int)$DB->count_records_sql($sql, [
            'lvl' => CONTEXT_COURSECAT,
        ]);

        // Orphan course contexts (context exists but course is missing).
        $sql = "SELECT COUNT(1)
                  FROM {context} ctx
             LEFT JOIN {course} c ON c.id = ctx.instanceid
                 WHERE ctx.contextlevel = :lvl
                   AND c.id IS NULL";
        $stats->orphan_course_contexts = (int)$DB->count_records_sql($sql, ['lvl' => CONTEXT_COURSE]);

        // Orphan course category contexts (context exists but course category is missing).
        $sql = "SELECT COUNT(1)
                  FROM {context} ctx
             LEFT JOIN {course_categories} cc ON cc.id = ctx.instanceid
                 WHERE ctx.contextlevel = :lvl
                   AND cc.id IS NULL";
        $stats->orphan_coursecat_contexts = (int)$DB->count_records_sql($sql, ['lvl' => CONTEXT_COURSECAT]);

        return $stats;
    }

    /**
     * Lightweight check: sample of latest files whose contenthash is missing from filedir.
     *
     * Important: this does NOT detect logical orphans (use orphan_file_detector for that).
     *
     * @param int $samplelimit Number of DB records to check (max is enforced)
     * @param int $maxexamples Max number of missing examples returned
     * @return \stdClass {checked:int, missing:int, examples:array, dataroot:string, filedir:string}
     */
    public static function get_missing_filedir_content_stats(int $samplelimit = 500, int $maxexamples = 15): \stdClass {
        global $DB, $CFG;

        $samplelimit = max(1, min((int)$samplelimit, 5000));
        $maxexamples = max(0, min((int)$maxexamples, 50));

        $stats = new \stdClass();
        $stats->checked = 0;
        $stats->missing = 0;
        $stats->examples = [];
        $stats->dataroot = (string)($CFG->dataroot ?? '');
        $stats->filedir = rtrim((string)($CFG->dataroot ?? ''), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'filedir';

        // Latest files (excluding directories).
        $sql = "SELECT id, contenthash, filesize, filename, component, filearea, itemid, contextid, timecreated
                  FROM {files}
                 WHERE filename <> '.'
                   AND filesize > 0
                   AND contenthash <> ''
              ORDER BY id DESC";

        $records = $DB->get_records_sql($sql, [], 0, $samplelimit);
        if (!$records) {
            return $stats;
        }

        foreach ($records as $rec) {
            $hash = (string)($rec->contenthash ?? '');
            if ($hash === '' || strlen($hash) < 4) {
                continue;
            }

            $stats->checked++;
            $path = self::get_filedir_path($hash);

            // If not readable, consider missing content (can indicate filedir corruption).
            if (!is_readable($path)) {
                $stats->missing++;
                if (count($stats->examples) < $maxexamples) {
                    $stats->examples[] = (object)[
                        'id' => (int)$rec->id,
                        'filename' => (string)$rec->filename,
                        'component' => (string)$rec->component,
                        'filearea' => (string)$rec->filearea,
                        'itemid' => (int)$rec->itemid,
                        'contextid' => (int)$rec->contextid,
                        'filesize' => (int)$rec->filesize,
                        'contenthash' => $hash,
                        'expected_path' => $path,
                        'timecreated' => (int)$rec->timecreated,
                    ];
                }
            }
        }

        return $stats;
    }

    /**
     * Disk usage information for moodledata.
     *
     * @return \stdClass {free_bytes:int|null, total_bytes:int|null, free_formatted:string, total_formatted:string}
     */
    public static function get_moodledata_disk_stats(): \stdClass {
        global $CFG;

        $stats = new \stdClass();
        $stats->free_bytes = null;
        $stats->total_bytes = null;
        $stats->free_formatted = '-';
        $stats->total_formatted = '-';

        $path = (string)($CFG->dataroot ?? '');
        if ($path === '') {
            return $stats;
        }

        try {
            $free = @disk_free_space($path);
            $total = @disk_total_space($path);
            if ($free !== false) {
                $stats->free_bytes = (int)$free;
                $stats->free_formatted = self::format_bytes((int)$free);
            }
            if ($total !== false) {
                $stats->total_bytes = (int)$total;
                $stats->total_formatted = self::format_bytes((int)$total);
            }
        } catch (\Throwable $e) {
            // Keep defaults.
        }

        return $stats;
    }

    /**
     * Compute the expected filedir path for a given contenthash.
     *
     * @param string $contenthash
     * @return string Absolute path in moodledata/filedir
     */
    private static function get_filedir_path(string $contenthash): string {
        global $CFG;

        $hash = (string)$contenthash;
        $hash = preg_replace('/[^a-f0-9]/i', '', $hash);
        if (strlen($hash) < 4) {
            return '';
        }

        $base = rtrim((string)($CFG->dataroot ?? ''), DIRECTORY_SEPARATOR);
        return $base . DIRECTORY_SEPARATOR . 'filedir'
            . DIRECTORY_SEPARATOR . substr($hash, 0, 2)
            . DIRECTORY_SEPARATOR . substr($hash, 2, 2)
            . DIRECTORY_SEPARATOR . $hash;
    }

    /**
     * Format bytes into a human-friendly string.
     *
     * @param int $bytes
     * @return string
     */
    private static function format_bytes(int $bytes): string {
        $bytes = max(0, (int)$bytes);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $value = (float)$bytes;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }
        if ($i === 0) {
            return (string)$bytes . ' ' . $units[$i];
        }
        return number_format($value, 2) . ' ' . $units[$i];
    }
}

