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
 * Liste les catégories de questions liées à un cours / une activité qui contiennent des questions.
 *
 * Moodle 4.5 :
 * - Les questions sont liées aux catégories via question_bank_entries
 * - La visibilité est portée par question_versions.status
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
if (!is_siteadmin()) {
    print_error('accesinterdit', 'admin', '', 'Vous devez être administrateur du site pour accéder à cet outil.');
    exit;
}

$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/question_diagnostic/categories_by_context.php'));
$pagetitle = get_string('tool_categories_by_context_title', 'local_question_diagnostic');
$PAGE->set_title($pagetitle);
$PAGE->set_heading(local_question_diagnostic_get_heading_with_version($pagetitle));
$PAGE->set_pagelayout('report');
$PAGE->requires->css('/local/question_diagnostic/styles/main.css');

// ----------------------------------------------------------------------
// Paramètres
// ----------------------------------------------------------------------
$coursecategoryid = optional_param('course_category', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$coursesearch = optional_param('course_search', '', PARAM_TEXT);
$cmid = optional_param('cmid', 0, PARAM_INT);
$scope = optional_param('scope', 'all', PARAM_ALPHA);
$includesystem = optional_param('include_system', 0, PARAM_BOOL);

$allowedscopes = ['all', 'course', 'activities', 'quiz', 'activity'];
if (!in_array($scope, $allowedscopes, true)) {
    $scope = 'all';
}
$coursecategoryid = max(0, (int)$coursecategoryid);
$courseid = max(0, (int)$courseid);
$coursesearch = trim((string)$coursesearch);
$cmid = max(0, (int)$cmid);

// ----------------------------------------------------------------------
// Header
// ----------------------------------------------------------------------
echo $OUTPUT->header();
echo local_question_diagnostic_render_version_badge();

echo html_writer::start_div('text-right', ['style' => 'margin-bottom: 20px;']);
echo local_question_diagnostic_render_cache_purge_button();
echo html_writer::end_div();

echo html_writer::start_div('', ['style' => 'margin-bottom: 20px;']);
echo local_question_diagnostic_render_back_link('categories_by_context.php');
echo html_writer::end_div();

echo html_writer::tag('h3', '📚 ' . format_string($pagetitle));

// ----------------------------------------------------------------------
// Filtres
// ----------------------------------------------------------------------
$coursecategories = local_question_diagnostic_get_course_categories();
$coursesincategory = [];
if ($coursecategoryid > 0) {
    $coursesincategory = local_question_diagnostic_get_courses_in_category_recursive($coursecategoryid);
}

$courseoptions = [];
if (!empty($coursesincategory)) {
    foreach ($coursesincategory as $c) {
        $courseoptions[(int)$c->id] = format_string($c->shortname) . ' — ' . format_string($c->fullname);
    }
    asort($courseoptions);
} else if ($coursesearch !== '' && core_text::strlen($coursesearch) >= 2) {
    // Recherche globale (limite) si on ne passe pas par une catégorie de cours.
    $like = '%' . $DB->sql_like_escape($coursesearch) . '%';
    $sql = "SELECT id, fullname, shortname
              FROM {course}
             WHERE id <> :siteid
               AND (" . $DB->sql_like('fullname', ':q', false, false) . "
                    OR " . $DB->sql_like('shortname', ':q', false, false) . ")
          ORDER BY fullname ASC";
    $records = $DB->get_records_sql($sql, ['siteid' => SITEID, 'q' => $like], 0, 50);
    foreach ($records as $c) {
        $courseoptions[(int)$c->id] = format_string($c->shortname) . ' — ' . format_string($c->fullname);
    }
}

$baseurl = new moodle_url('/local/question_diagnostic/categories_by_context.php');

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => $baseurl->out(false),
    'class' => 'qd-filters',
]);

echo html_writer::tag('h4', '🔍 ' . get_string('filters', 'local_question_diagnostic'), ['style' => 'margin-top: 0;']);
echo html_writer::start_div('qd-filters-row');

// Catégorie de cours.
echo html_writer::start_div('qd-filter-group');
echo html_writer::tag('label', get_string('course_category_filter', 'local_question_diagnostic'), ['for' => 'qd-course-category']);
echo html_writer::start_tag('select', [
    'id' => 'qd-course-category',
    'class' => 'form-control',
    'name' => 'course_category',
]);
echo html_writer::tag('option', get_string('all_course_categories', 'local_question_diagnostic'), [
    'value' => 0,
    'selected' => ($coursecategoryid === 0),
]);
foreach ($coursecategories as $cc) {
    $label = $cc->formatted_name;
    if ((int)$cc->course_count > 0) {
        $label .= ' (' . (int)$cc->course_count . ' cours)';
    }
    echo html_writer::tag('option', $label, [
        'value' => (int)$cc->id,
        'selected' => ($coursecategoryid === (int)$cc->id),
    ]);
}
echo html_writer::end_tag('select');
echo html_writer::end_div();

// Recherche de cours (si on ne veut pas saisir un ID).
echo html_writer::start_div('qd-filter-group');
echo html_writer::tag('label', get_string('tool_categories_by_context_course_search', 'local_question_diagnostic'), ['for' => 'qd-course-search']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'id' => 'qd-course-search',
    'name' => 'course_search',
    'value' => $coursesearch,
    'class' => 'form-control',
    'placeholder' => get_string('tool_categories_by_context_course_search_placeholder', 'local_question_diagnostic'),
]);
echo html_writer::end_div();

// Sélection du cours.
echo html_writer::start_div('qd-filter-group');
echo html_writer::tag('label', get_string('tool_categories_by_context_course', 'local_question_diagnostic'), ['for' => 'qd-courseid']);
echo html_writer::start_tag('select', [
    'id' => 'qd-courseid',
    'name' => 'courseid',
    'class' => 'form-control',
]);
echo html_writer::tag('option', get_string('tool_categories_by_context_course_placeholder', 'local_question_diagnostic'), ['value' => 0]);
foreach ($courseoptions as $cid => $label) {
    echo html_writer::tag('option', $label, [
        'value' => (int)$cid,
        'selected' => ($courseid === (int)$cid),
    ]);
}
echo html_writer::end_tag('select');
echo html_writer::tag('div', get_string('tool_categories_by_context_course_help', 'local_question_diagnostic'), [
    'style' => 'margin-top: 6px; font-size: 12px; color: #666;',
]);
echo html_writer::end_div();

// Scope.
echo html_writer::start_div('qd-filter-group');
echo html_writer::tag('label', get_string('tool_categories_by_context_scope', 'local_question_diagnostic'), ['for' => 'qd-scope']);
echo html_writer::start_tag('select', [
    'id' => 'qd-scope',
    'name' => 'scope',
    'class' => 'form-control',
]);
$scopeoptions = [
    'all' => get_string('tool_categories_by_context_scope_all', 'local_question_diagnostic'),
    'course' => get_string('tool_categories_by_context_scope_course', 'local_question_diagnostic'),
    'activities' => get_string('tool_categories_by_context_scope_activities', 'local_question_diagnostic'),
    'quiz' => get_string('tool_categories_by_context_scope_quiz', 'local_question_diagnostic'),
    'activity' => get_string('tool_categories_by_context_scope_activity', 'local_question_diagnostic'),
];
foreach ($scopeoptions as $value => $label) {
    echo html_writer::tag('option', $label, [
        'value' => $value,
        'selected' => ($scope === $value),
    ]);
}
echo html_writer::end_tag('select');
echo html_writer::end_div();

// Activité (cmid).
echo html_writer::start_div('qd-filter-group');
echo html_writer::tag('label', get_string('tool_categories_by_context_activity', 'local_question_diagnostic'), ['for' => 'qd-cmid']);
echo html_writer::start_tag('select', [
    'id' => 'qd-cmid',
    'name' => 'cmid',
    'class' => 'form-control',
]);
echo html_writer::tag('option', get_string('tool_categories_by_context_activity_all', 'local_question_diagnostic'), [
    'value' => 0,
    'selected' => ($cmid === 0),
]);

if ($courseid > 0) {
    try {
        $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname, shortname', IGNORE_MISSING);
        if ($course) {
            require_once($CFG->dirroot . '/course/lib.php');
            $modinfo = get_fast_modinfo($course);
            foreach ($modinfo->get_cms() as $cm) {
                if (!$cm->uservisible) {
                    continue;
                }
                $label = $cm->modname . ': ' . format_string($cm->name) . ' (cmid: ' . (int)$cm->id . ')';
                echo html_writer::tag('option', $label, [
                    'value' => (int)$cm->id,
                    'selected' => ($cmid === (int)$cm->id),
                ]);
            }
        }
    } catch (Exception $e) {
        // Ignorer : dropdown vide.
    }
}

echo html_writer::end_tag('select');
echo html_writer::end_div();

// Inclure le système.
echo html_writer::start_div('qd-filter-group');
echo html_writer::tag('label', get_string('tool_categories_by_context_include_system', 'local_question_diagnostic'), ['for' => 'qd-include-system']);
echo html_writer::empty_tag('input', [
    'type' => 'checkbox',
    'id' => 'qd-include-system',
    'name' => 'include_system',
    'value' => 1,
    'checked' => (bool)$includesystem,
    'style' => 'margin-top: 10px;',
]);
echo html_writer::end_div();

// Bouton appliquer.
echo html_writer::start_div('qd-filter-group');
echo html_writer::tag('label', '&nbsp;', ['style' => 'visibility:hidden;']);
echo html_writer::tag('button', get_string('tool_categories_by_context_apply', 'local_question_diagnostic'), [
    'type' => 'submit',
    'class' => 'btn btn-primary',
    'style' => 'width: 100%;',
]);
echo html_writer::end_div();

echo html_writer::end_div(); // qd-filters-row

echo html_writer::start_tag('script');
?>
(function() {
  var form = document.querySelector('form.qd-filters');
  if (!form) { return; }

  var courseCategory = document.getElementById('qd-course-category');
  var courseId = document.getElementById('qd-courseid');
  var courseSearch = document.getElementById('qd-course-search');

  if (courseCategory) {
    courseCategory.addEventListener('change', function() {
      // Quand on change de catégorie de cours, on recharge la page pour remplir la liste.
      if (form) { form.submit(); }
    });
  }

  if (courseId) {
    courseId.addEventListener('change', function() {
      if (parseInt(courseId.value || '0', 10) > 0 && form) {
        form.submit();
      }
    });
  }

  if (courseSearch) {
    courseSearch.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        if (form) { form.submit(); }
      }
    });
  }
})();
<?php
echo html_writer::end_tag('script');

echo html_writer::end_tag('form');

// ----------------------------------------------------------------------
// Si pas de cours : message d'aide et stop.
// ----------------------------------------------------------------------
if ($courseid <= 0) {
    echo html_writer::start_div('alert alert-info', ['style' => 'margin-top: 15px; border-left: 4px solid #17a2b8;']);
    echo html_writer::tag('strong', get_string('tool_categories_by_context_intro_title', 'local_question_diagnostic'));
    echo html_writer::tag('div', get_string('tool_categories_by_context_intro', 'local_question_diagnostic'), ['style' => 'margin-top: 6px;']);
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

// ----------------------------------------------------------------------
// Construire la liste des contextes à analyser
// ----------------------------------------------------------------------
$contextids = [];

if (!empty($includesystem)) {
    $contextids[] = (int)context_system::instance()->id;
}

$coursecontext = context_course::instance($courseid, IGNORE_MISSING);
if (!$coursecontext) {
    echo html_writer::start_div('alert alert-danger');
    echo 'Contexte de cours introuvable pour courseid=' . (int)$courseid;
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

if ($scope === 'all' || $scope === 'course') {
    $contextids[] = (int)$coursecontext->id;
}

if ($scope === 'all' || $scope === 'activities' || $scope === 'quiz') {
    $params = [
        'contextlevel' => CONTEXT_MODULE,
        'courseid' => $courseid,
    ];
    $sql = "SELECT ctx.id
              FROM {context} ctx
              INNER JOIN {course_modules} cm ON cm.id = ctx.instanceid
              INNER JOIN {modules} m ON m.id = cm.module
             WHERE ctx.contextlevel = :contextlevel
               AND cm.course = :courseid";
    if ($scope === 'quiz') {
        $sql .= " AND m.name = :modname";
        $params['modname'] = 'quiz';
    }
    $modulecontextids = $DB->get_fieldset_sql($sql, $params);
    foreach ($modulecontextids as $mid) {
        $contextids[] = (int)$mid;
    }
}

if ($scope === 'activity') {
    if ($cmid <= 0) {
        echo html_writer::start_div('alert alert-warning', ['style' => 'margin-top: 15px; border-left: 4px solid #f0ad4e;']);
        echo html_writer::tag('strong', get_string('tool_categories_by_context_activity_required', 'local_question_diagnostic'));
        echo html_writer::end_div();
        echo $OUTPUT->footer();
        exit;
    }
    $modulecontext = context_module::instance($cmid, IGNORE_MISSING);
    if ($modulecontext) {
        $contextids[] = (int)$modulecontext->id;
    } else {
        echo html_writer::start_div('alert alert-danger');
        echo 'Contexte de module introuvable pour cmid=' . (int)$cmid;
        echo html_writer::end_div();
        echo $OUTPUT->footer();
        exit;
    }
}

$contextids = array_values(array_unique(array_filter($contextids)));

if (empty($contextids)) {
    echo html_writer::start_div('alert alert-warning');
    echo get_string('tool_categories_by_context_no_contexts', 'local_question_diagnostic');
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

// ----------------------------------------------------------------------
// Charger les catégories pour ces contextes
// ----------------------------------------------------------------------
list($ctxinsql, $ctxparams) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'ctx');
$catsql = "SELECT qc.id, qc.name, qc.parent, qc.contextid, qc.sortorder, qc.info, qc.infoformat
             FROM {question_categories} qc
            WHERE qc.contextid " . $ctxinsql . "
         ORDER BY qc.contextid ASC, qc.parent ASC, qc.sortorder ASC, qc.id ASC";
$categories = $DB->get_records_sql($catsql, $ctxparams);

if (empty($categories)) {
    echo html_writer::start_div('alert alert-warning', ['style' => 'margin-top: 15px;']);
    echo get_string('tool_categories_by_context_no_categories', 'local_question_diagnostic');
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

// ----------------------------------------------------------------------
// Compter les questions (entrées) par catégorie - 1 requête
// ----------------------------------------------------------------------
$categoryids = array_keys($categories);
list($catinsql, $catparams) = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED, 'cat');

$countsql = "SELECT qbe.questioncategoryid AS categoryid,
                    COUNT(DISTINCT qbe.id) AS total_questions,
                    COUNT(DISTINCT CASE WHEN qv.status <> :hiddenstatus THEN qbe.id ELSE NULL END) AS visible_questions
               FROM {question_bank_entries} qbe
               INNER JOIN (
                    SELECT questionbankentryid, MAX(version) AS maxversion
                      FROM {question_versions}
                  GROUP BY questionbankentryid
               ) mv ON mv.questionbankentryid = qbe.id
               INNER JOIN {question_versions} qv
                       ON qv.questionbankentryid = mv.questionbankentryid
                      AND qv.version = mv.maxversion
              WHERE qbe.questioncategoryid " . $catinsql . "
           GROUP BY qbe.questioncategoryid";

$countparams = array_merge($catparams, ['hiddenstatus' => 'hidden']);
$counts = $DB->get_records_sql($countsql, $countparams);

// ----------------------------------------------------------------------
// Construire l'arbre et calculer totals (direct + sous-catégories)
// ----------------------------------------------------------------------
$children = [];
$directtotal = [];
$directvisible = [];

foreach ($categories as $cat) {
    $children[(int)$cat->parent][] = (int)$cat->id;
    $directtotal[(int)$cat->id] = 0;
    $directvisible[(int)$cat->id] = 0;
}
foreach ($counts as $row) {
    $cid = (int)$row->categoryid;
    $directtotal[$cid] = (int)$row->total_questions;
    $directvisible[$cid] = (int)$row->visible_questions;
}

$totaltree = [];
$visibletree = [];

$compute = function(int $catid) use (&$compute, &$children, &$directtotal, &$directvisible, &$totaltree, &$visibletree): void {
    if (isset($totaltree[$catid])) {
        return;
    }
    $total = $directtotal[$catid] ?? 0;
    $visible = $directvisible[$catid] ?? 0;
    if (!empty($children[$catid])) {
        foreach ($children[$catid] as $childid) {
            $compute($childid);
            $total += $totaltree[$childid] ?? 0;
            $visible += $visibletree[$childid] ?? 0;
        }
    }
    $totaltree[$catid] = $total;
    $visibletree[$catid] = $visible;
};

foreach ($categories as $cat) {
    $compute((int)$cat->id);
}

// Garder uniquement les catégories qui contiennent des questions (direct ou dans l'arbre).
$filtered = [];
foreach ($categories as $cat) {
    $cid = (int)$cat->id;
    if (($totaltree[$cid] ?? 0) > 0) {
        $filtered[$cid] = $cat;
    }
}

// ----------------------------------------------------------------------
// Résumé
// ----------------------------------------------------------------------
$course = $DB->get_record('course', ['id' => $courseid], 'id, fullname, shortname', IGNORE_MISSING);
$courselabel = $course ? (format_string($course->shortname) . ' — ' . format_string($course->fullname)) : ('courseid=' . (int)$courseid);

$totalquestionssum = 0;
$visiblequestionssum = 0;
foreach ($filtered as $cat) {
    $cid = (int)$cat->id;
    $totalquestionssum += $directtotal[$cid] ?? 0;
    $visiblequestionssum += $directvisible[$cid] ?? 0;
}

echo html_writer::start_div('alert alert-secondary', ['style' => 'margin-top: 15px; border-left: 4px solid #6c757d;']);
echo html_writer::tag('strong', get_string('tool_categories_by_context_summary_title', 'local_question_diagnostic'));
echo html_writer::tag('div', get_string('tool_categories_by_context_summary', 'local_question_diagnostic', (object)[
    'course' => $courselabel,
    'contexts' => count($contextids),
    'categories' => count($filtered),
    'directquestions' => $totalquestionssum,
    'directvisible' => $visiblequestionssum,
]), ['style' => 'margin-top: 6px;']);
echo html_writer::end_div();

if (empty($filtered)) {
    echo html_writer::start_div('alert alert-warning', ['style' => 'margin-top: 15px;']);
    echo get_string('tool_categories_by_context_none_with_questions', 'local_question_diagnostic');
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

// ----------------------------------------------------------------------
// Table
// ----------------------------------------------------------------------
echo html_writer::start_div('qd-table-wrapper', ['style' => 'margin-top: 10px;']);
echo html_writer::start_tag('table', ['class' => 'qd-table']);

echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
echo html_writer::tag('th', 'ID');
echo html_writer::tag('th', get_string('categoryname', 'local_question_diagnostic'));
echo html_writer::tag('th', get_string('categorycontext', 'local_question_diagnostic'));
echo html_writer::tag('th', get_string('tool_categories_by_context_direct', 'local_question_diagnostic'), [
    'title' => get_string('tool_categories_by_context_direct_help', 'local_question_diagnostic'),
]);
echo html_writer::tag('th', get_string('tool_categories_by_context_total', 'local_question_diagnostic'), [
    'title' => get_string('tool_categories_by_context_total_help', 'local_question_diagnostic'),
]);
echo html_writer::tag('th', get_string('tool_categories_by_context_visible_direct', 'local_question_diagnostic'));
echo html_writer::tag('th', get_string('tool_categories_by_context_visible_total', 'local_question_diagnostic'));
echo html_writer::tag('th', get_string('categorysubcats', 'local_question_diagnostic'));
echo html_writer::tag('th', get_string('actions', 'local_question_diagnostic'));
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');

echo html_writer::start_tag('tbody');

// Labels de contexte (cache).
$contextlabels = [];
foreach ($contextids as $ctxid) {
    $details = local_question_diagnostic_get_context_details((int)$ctxid);
    $contextlabels[(int)$ctxid] = $details->context_name;
}

foreach ($filtered as $cat) {
    $cid = (int)$cat->id;
    $subcats = !empty($children[$cid]) ? count($children[$cid]) : 0;

    $contextlabel = $contextlabels[(int)$cat->contextid] ?? ('contextid=' . (int)$cat->contextid);
    $qbankurl = local_question_diagnostic_get_question_bank_url($cat);

    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', $cid);

    echo html_writer::start_tag('td');
    if ($qbankurl) {
        echo html_writer::link($qbankurl, format_string($cat->name), [
            'target' => '_blank',
            'title' => get_string('view_in_bank', 'local_question_diagnostic'),
        ]);
        echo ' ' . html_writer::tag('span', '🔗', ['style' => 'opacity: 0.5; font-size: 0.9em;']);
    } else {
        echo format_string($cat->name);
    }
    echo html_writer::end_tag('td');

    echo html_writer::tag('td', s($contextlabel));
    echo html_writer::tag('td', (int)($directtotal[$cid] ?? 0));
    echo html_writer::tag('td', (int)($totaltree[$cid] ?? 0));
    echo html_writer::tag('td', (int)($directvisible[$cid] ?? 0));
    echo html_writer::tag('td', (int)($visibletree[$cid] ?? 0));
    echo html_writer::tag('td', (int)$subcats);

    echo html_writer::start_tag('td');
    if ($qbankurl) {
        echo html_writer::link($qbankurl, '👁️ ' . get_string('view_in_bank', 'local_question_diagnostic'), [
            'class' => 'btn btn-sm btn-secondary',
            'target' => '_blank',
        ]);
    } else {
        echo html_writer::tag('span', '-', ['class' => 'text-muted']);
    }
    echo html_writer::end_tag('td');

    echo html_writer::end_tag('tr');
}

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');
echo html_writer::end_div();

echo $OUTPUT->footer();
