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
 * Action : correction assistée des incohérences de questions (diagnostic de cohérence).
 *
 * ⚠️ Sécurité :
 * - Admin uniquement
 * - sesskey obligatoire
 * - confirmation obligatoire avant toute modification
 *
 * Corrections (ciblées, lecture/écriture minimales) :
 * - Supprimer les lignes orphelines dans {question_versions} pointant vers une question inexistante
 * - Résoudre les doublons de "dernière version" (garder 1 ligne maxversion, supprimer les autres)
 * - Supprimer les entrées {question_bank_entries} sans versions si elles ne sont référencées nulle part
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/../classes/question_analyzer.php');

use local_question_diagnostic\question_analyzer;

require_login();
require_sesskey();

if (!is_siteadmin()) {
    print_error('accessdenied', 'admin');
}

$confirm = optional_param('confirm', 0, PARAM_INT);
$returnurlraw = optional_param('returnurl', '', PARAM_LOCALURL);
$returnurl = !empty($returnurlraw)
    ? new moodle_url($returnurlraw)
    : new moodle_url('/local/question_diagnostic/questions_cleanup.php', ['questions_integrity' => 1]);

$PAGE->set_url(new moodle_url('/local/question_diagnostic/actions/fix_questions_integrity.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('admin');

/**
 * Construit un plan de correction (opérations + résumé + échantillons).
 *
 * @param int $samplelimit Nombre d'éléments à afficher par check (UI)
 * @return \stdClass {summary:object, samples:array}
 */
$build_plan = function(int $samplelimit = 50): \stdClass {
    global $DB;

    $plan = (object)[
        'summary' => (object)[
            'orphan_qv_missing_question' => 0,
            'duplicate_latest_versions' => 0,
            'entries_without_versions' => 0,
            'entries_without_versions_deletable' => 0,
        ],
        'samples' => [
            'orphan_qv_missing_question' => [],
            'duplicate_latest_versions' => [],
            'entries_without_versions_deletable' => [],
        ],
    ];

    $dbman = $DB->get_manager();
    if (!$dbman->table_exists('question_versions') || !$dbman->table_exists('question_bank_entries')) {
        return $plan;
    }

    // A) question_versions -> question manquante.
    try {
        $plan->summary->orphan_qv_missing_question = (int)$DB->count_records_sql("
            SELECT COUNT(1)
              FROM {question_versions} qv
         LEFT JOIN {question} q ON q.id = qv.questionid
             WHERE q.id IS NULL
        ");

        $qvcols = $DB->get_columns('question_versions');
        $fields = "qv.id, qv.questionid, qv.questionbankentryid, qv.version";
        if (isset($qvcols['status'])) {
            $fields .= ", qv.status";
        }

        $plan->samples['orphan_qv_missing_question'] = array_values($DB->get_records_sql("
            SELECT $fields
              FROM {question_versions} qv
         LEFT JOIN {question} q ON q.id = qv.questionid
             WHERE q.id IS NULL
          ORDER BY qv.id ASC
        ", [], 0, $samplelimit));
    } catch (\Exception $e) {
        // Ignore.
    }

    // B) Doublon de dernière version (maxversion dupliquée).
    try {
        $basesql = "
            SELECT qv.questionbankentryid AS entryid,
                   mv.maxversion AS maxversion,
                   COUNT(1) AS cnt
              FROM {question_versions} qv
              JOIN (
                    SELECT questionbankentryid, MAX(version) AS maxversion
                      FROM {question_versions}
                  GROUP BY questionbankentryid
                   ) mv
                ON mv.questionbankentryid = qv.questionbankentryid
               AND mv.maxversion = qv.version
          GROUP BY qv.questionbankentryid, mv.maxversion
            HAVING COUNT(1) > 1";
        $plan->summary->duplicate_latest_versions = (int)$DB->count_records_sql("SELECT COUNT(1) FROM ($basesql) t");
        $samplesql = $basesql . " ORDER BY cnt DESC, entryid ASC";
        $plan->samples['duplicate_latest_versions'] = array_values($DB->get_records_sql($samplesql, [], 0, $samplelimit));
    } catch (\Exception $e) {
        // Ignore.
    }

    // C) Entrées sans versions : suppression possible seulement si aucune référence.
    try {
        $plan->summary->entries_without_versions = (int)$DB->count_records_sql("
            SELECT COUNT(1)
              FROM {question_bank_entries} qbe
         LEFT JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
             WHERE qv.questionbankentryid IS NULL
        ");

        $candidates = array_values($DB->get_records_sql("
            SELECT qbe.id AS entryid, qbe.questioncategoryid
              FROM {question_bank_entries} qbe
         LEFT JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
             WHERE qv.questionbankentryid IS NULL
          ORDER BY qbe.id ASC
        ", [], 0, $samplelimit));

        $deletable = [];
        $deletablecount = 0;

        $hasrefs = $dbman->table_exists('question_references') && isset($DB->get_columns('question_references')['questionbankentryid']);
        foreach ($candidates as $cand) {
            $entryid = (int)($cand->entryid ?? 0);
            if ($entryid <= 0) {
                continue;
            }

            $isreferenced = false;
            if ($hasrefs) {
                $isreferenced = $DB->record_exists('question_references', ['questionbankentryid' => $entryid]);
            }

            if (!$isreferenced) {
                $deletable[] = $cand;
                $deletablecount++;
            }
        }

        $plan->summary->entries_without_versions_deletable = (int)$deletablecount;
        $plan->samples['entries_without_versions_deletable'] = $deletable;
    } catch (\Exception $e) {
        // Ignore.
    }

    return $plan;
};

$plan = $build_plan(50);

if (!$confirm) {
    $PAGE->set_title(get_string('questions_integrity_fix_confirm_title', 'local_question_diagnostic'));
    $PAGE->set_heading(get_string('questions_integrity_fix_confirm_title', 'local_question_diagnostic'));

    echo $OUTPUT->header();
    echo local_question_diagnostic_render_version_badge();

    echo html_writer::tag('h2', '🛠️ ' . get_string('questions_integrity_fix_confirm_title', 'local_question_diagnostic'));
    echo html_writer::tag('p', get_string('questions_integrity_fix_confirm_intro', 'local_question_diagnostic'));

    $totalops = (int)$plan->summary->orphan_qv_missing_question
        + (int)$plan->summary->duplicate_latest_versions
        + (int)$plan->summary->entries_without_versions_deletable;

    if ($totalops <= 0) {
        echo html_writer::start_div('alert alert-info');
        echo get_string('questions_integrity_fix_nothing', 'local_question_diagnostic');
        echo html_writer::end_div();
        echo html_writer::link($returnurl, get_string('backtoquestions', 'local_question_diagnostic'), ['class' => 'btn btn-secondary']);
        echo $OUTPUT->footer();
        exit;
    }

    echo html_writer::start_div('alert alert-warning');
    echo get_string('questions_integrity_fix_warning', 'local_question_diagnostic');
    echo html_writer::end_div();

    echo html_writer::tag('h3', get_string('questions_integrity_fix_operations', 'local_question_diagnostic'));

    echo html_writer::start_tag('ul');
    echo html_writer::tag('li', 'Supprimer ' . (int)$plan->summary->orphan_qv_missing_question . ' version(s) orpheline(s) (question_versions → question manquante).');
    echo html_writer::tag('li', 'Corriger ' . (int)$plan->summary->duplicate_latest_versions . ' entrée(s) avec plusieurs dernières versions (garder 1, supprimer le reste).');
    echo html_writer::tag('li', 'Supprimer ' . (int)$plan->summary->entries_without_versions_deletable . ' entrée(s) sans versions (question_bank_entries) non référencée(s).');
    echo html_writer::end_tag('ul');

    // Échantillons.
    if (!empty($plan->samples['orphan_qv_missing_question'])) {
        echo html_writer::tag('h4', 'Exemples — Versions orphelines');
        echo html_writer::start_tag('ul');
        foreach ($plan->samples['orphan_qv_missing_question'] as $item) {
            $line = 'qv.id=' . (int)($item->id ?? 0)
                . ' → questionid=' . (int)($item->questionid ?? 0)
                . ' / qbe=' . (int)($item->questionbankentryid ?? 0)
                . ' / v=' . (int)($item->version ?? 0);
            if (isset($item->status)) {
                $line .= ' / status=' . s($item->status);
            }
            echo html_writer::tag('li', $line);
        }
        echo html_writer::end_tag('ul');
    }

    if (!empty($plan->samples['duplicate_latest_versions'])) {
        echo html_writer::tag('h4', 'Exemples — Plusieurs dernières versions');
        echo html_writer::start_tag('ul');
        foreach ($plan->samples['duplicate_latest_versions'] as $item) {
            $line = 'qbe.id=' . (int)($item->entryid ?? 0)
                . ' / maxversion=' . (int)($item->maxversion ?? 0)
                . ' / count=' . (int)($item->cnt ?? 0);
            echo html_writer::tag('li', $line);
        }
        echo html_writer::end_tag('ul');
    }

    if (!empty($plan->samples['entries_without_versions_deletable'])) {
        echo html_writer::tag('h4', 'Exemples — Entrées sans versions (supprimables)');
        echo html_writer::start_tag('ul');
        foreach ($plan->samples['entries_without_versions_deletable'] as $item) {
            $line = 'qbe.id=' . (int)($item->entryid ?? 0)
                . ' / questioncategoryid=' . (int)($item->questioncategoryid ?? 0);
            echo html_writer::tag('li', $line);
        }
        echo html_writer::end_tag('ul');
    }

    $confirmurl = new moodle_url('/local/question_diagnostic/actions/fix_questions_integrity.php', [
        'confirm' => 1,
        'sesskey' => sesskey(),
        'returnurl' => $returnurl->out(false),
    ]);

    echo html_writer::start_div('mt-3', ['style' => 'margin-top: 20px;']);
    echo html_writer::link($confirmurl, get_string('confirm', 'core'), ['class' => 'btn btn-danger']);
    echo ' ';
    echo html_writer::link($returnurl, get_string('cancel', 'core'), ['class' => 'btn btn-secondary']);
    echo html_writer::end_div();

    echo $OUTPUT->footer();
    exit;
}

// Confirmé : appliquer les corrections.
$success = 0;
$failed = 0;
$errors = [];

try {
    global $DB;
    $dbman = $DB->get_manager();

    if ($dbman->table_exists('question_versions') && $dbman->table_exists('question_bank_entries')) {
        $transaction = $DB->start_delegated_transaction();

        // 1) Corriger les doublons de maxversion : garder 1 qv, supprimer les autres.
        try {
            $basesql = "
                SELECT qv.questionbankentryid AS entryid,
                       mv.maxversion AS maxversion,
                       COUNT(1) AS cnt
                  FROM {question_versions} qv
                  JOIN (
                        SELECT questionbankentryid, MAX(version) AS maxversion
                          FROM {question_versions}
                      GROUP BY questionbankentryid
                       ) mv
                    ON mv.questionbankentryid = qv.questionbankentryid
                   AND mv.maxversion = qv.version
              GROUP BY qv.questionbankentryid, mv.maxversion
                HAVING COUNT(1) > 1";

            $groups = $DB->get_records_sql($basesql);
            foreach ($groups as $g) {
                $entryid = (int)($g->entryid ?? 0);
                $maxversion = (int)($g->maxversion ?? 0);
                if ($entryid <= 0 || $maxversion <= 0) {
                    continue;
                }

                $qvcols = $DB->get_columns('question_versions');
                $hasstatus = isset($qvcols['status']);

                $fields = "qv.id, qv.questionid, qv.questionbankentryid, qv.version";
                if ($hasstatus) {
                    $fields .= ", qv.status";
                }

                $rows = array_values($DB->get_records_sql("
                    SELECT $fields
                      FROM {question_versions} qv
                     WHERE qv.questionbankentryid = :entryid
                       AND qv.version = :v
                  ORDER BY qv.id ASC
                ", ['entryid' => $entryid, 'v' => $maxversion]));

                if (count($rows) <= 1) {
                    continue;
                }

                // Choisir la ligne à conserver.
                $keepid = 0;
                $bestscore = -1;
                foreach ($rows as $row) {
                    $qid = (int)($row->questionid ?? 0);
                    $exists = $qid > 0 && $DB->record_exists('question', ['id' => $qid]);
                    $score = $exists ? 100 : 0;
                    if ($hasstatus) {
                        // Priorité à "ready" (puis le reste).
                        $status = (string)($row->status ?? '');
                        if ($status === 'ready') {
                            $score += 10;
                        } else if ($status === 'hidden') {
                            $score += 1;
                        }
                    }
                    // Départager avec l'id le plus petit (déterministe).
                    if ($score > $bestscore || ($score === $bestscore && (int)$row->id < $keepid) || $keepid === 0) {
                        $bestscore = $score;
                        $keepid = (int)$row->id;
                    }
                }

                foreach ($rows as $row) {
                    $id = (int)($row->id ?? 0);
                    if ($id > 0 && $id !== $keepid) {
                        if ($DB->delete_records('question_versions', ['id' => $id])) {
                            $success++;
                        } else {
                            $failed++;
                            $errors[] = 'delete qv.id=' . $id . ' failed';
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $failed++;
            $errors[] = 'duplicate_latest_versions: ' . $e->getMessage();
        }

        // 2) Supprimer question_versions orphelines (question manquante).
        try {
            $rs = $DB->get_recordset_sql("
                SELECT qv.id
                  FROM {question_versions} qv
             LEFT JOIN {question} q ON q.id = qv.questionid
                 WHERE q.id IS NULL
              ORDER BY qv.id ASC
            ");

            $batch = [];
            $batchsize = 500;
            foreach ($rs as $rec) {
                $batch[] = (int)$rec->id;
                if (count($batch) >= $batchsize) {
                    $DB->delete_records_list('question_versions', 'id', $batch);
                    $success += count($batch);
                    $batch = [];
                }
            }
            $rs->close();
            if (!empty($batch)) {
                $DB->delete_records_list('question_versions', 'id', $batch);
                $success += count($batch);
            }
        } catch (\Exception $e) {
            $failed++;
            $errors[] = 'orphan_qv_missing_question: ' . $e->getMessage();
        }

        // 3) Supprimer les entrées sans versions si non référencées.
        try {
            $hasrefs = $dbman->table_exists('question_references') && isset($DB->get_columns('question_references')['questionbankentryid']);

            $rs = $DB->get_recordset_sql("
                SELECT qbe.id
                  FROM {question_bank_entries} qbe
             LEFT JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                 WHERE qv.questionbankentryid IS NULL
              ORDER BY qbe.id ASC
            ");

            $batch = [];
            $batchsize = 500;
            foreach ($rs as $rec) {
                $entryid = (int)$rec->id;
                if ($entryid <= 0) {
                    continue;
                }
                if ($hasrefs && $DB->record_exists('question_references', ['questionbankentryid' => $entryid])) {
                    continue; // Ne jamais supprimer une entrée référencée.
                }
                $batch[] = $entryid;
                if (count($batch) >= $batchsize) {
                    $DB->delete_records_list('question_bank_entries', 'id', $batch);
                    $success += count($batch);
                    $batch = [];
                }
            }
            $rs->close();
            if (!empty($batch)) {
                $DB->delete_records_list('question_bank_entries', 'id', $batch);
                $success += count($batch);
            }
        } catch (\Exception $e) {
            $failed++;
            $errors[] = 'entries_without_versions: ' . $e->getMessage();
        }

        $transaction->allow_commit();
    }

    // Purger caches du plugin (important pour la banque de questions).
    try {
        question_analyzer::purge_all_caches();
    } catch (\Exception $e) {
        // Ignore.
    }

} catch (\Exception $e) {
    $failed++;
    $errors[] = $e->getMessage();
}

// Notifications.
if ($success > 0) {
    \core\notification::success(get_string('questions_integrity_fix_done', 'local_question_diagnostic', (object)[
        'success' => $success,
        'failed' => $failed,
    ]));
}
if (!empty($errors)) {
    \core\notification::error(get_string('questions_integrity_fix_failed', 'local_question_diagnostic') . '<br>' . implode('<br>', array_map('s', $errors)));
}
if ($success === 0 && $failed === 0) {
    \core\notification::info(get_string('questions_integrity_fix_nothing', 'local_question_diagnostic'));
}

redirect($returnurl);

