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

/**
 * Export CSV des fichiers orphelins (sélection ou totalité), cellules neutralisées contre l'injection de formules.
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../classes/orphan_file_detector.php');

use local_question_diagnostic\orphan_file_detector;

require_login();

// Vérification stricte : seuls les administrateurs du site peuvent accéder à cette page.
if (!is_siteadmin()) {
    throw new \moodle_exception('accessdenied', 'admin');
}

require_sesskey();

$selectedids = optional_param_array('ids', [], PARAM_INT);
$exportall = optional_param('all', 0, PARAM_BOOL);

if (!$exportall && empty($selectedids)) {
    redirect(new moodle_url('/local/question_diagnostic/orphan_files.php'),
        get_string('orphan_export_empty', 'local_question_diagnostic'), null,
        \core\output\notification::NOTIFY_WARNING);
}

// Sélection : revérification en base (seuls les fichiers réellement orphelins sont exportés).
// Totalité : parcours par recordset, sans tout charger en mémoire.
$orphans = $exportall ? orphan_file_detector::iterate_orphans(0) : orphan_file_detector::get_orphans_by_ids($selectedids);

$filename = 'orphan_files_' . date('Y-m-d_H-i-s') . '.csv';

\core\session\manager::write_close();
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: private, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

$out = fopen('php://output', 'w');
$count = orphan_file_detector::write_csv($orphans, $out);
fclose($out);

\local_question_diagnostic\audit_logger::log_action('orphan_files_exported', ['count' => $count, 'all' => (int)$exportall]);

exit;
