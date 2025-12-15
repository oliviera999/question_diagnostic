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
require_once(__DIR__ . '/classes/olution_manager.php');

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

// String manager (pour éviter les warnings get_string si cache/pack non à jour).
$stringmanager = get_string_manager();

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
$returnurl = new moodle_url('/local/question_diagnostic/categories_by_context.php', [
    'course_category' => $coursecategoryid,
    'courseid' => $courseid,
    'course_search' => $coursesearch,
    'cmid' => $cmid,
    'scope' => $scope,
    'include_system' => $includesystem ? 1 : 0,
]);

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
$coursesearchlabel = $stringmanager->string_exists('tool_categories_by_context_course_search', 'local_question_diagnostic') ?
    get_string('tool_categories_by_context_course_search', 'local_question_diagnostic') :
    ((strpos(current_language(), 'fr') === 0) ? 'Rechercher un cours' : 'Search a course');
echo html_writer::tag('label', $coursesearchlabel, ['for' => 'qd-course-search']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'id' => 'qd-course-search',
    'name' => 'course_search',
    'value' => $coursesearch,
    'class' => 'form-control',
    'placeholder' => ($stringmanager->string_exists('tool_categories_by_context_course_search_placeholder', 'local_question_diagnostic') ?
        get_string('tool_categories_by_context_course_search_placeholder', 'local_question_diagnostic') :
        ((strpos(current_language(), 'fr') === 0) ? 'Nom du cours ou shortname (au moins 2 caractères)…' : 'Course name or shortname (at least 2 characters)…')),
]);
echo html_writer::end_div();

// Sélection du cours.
echo html_writer::start_div('qd-filter-group');
$courselabel = $stringmanager->string_exists('tool_categories_by_context_course', 'local_question_diagnostic') ?
    get_string('tool_categories_by_context_course', 'local_question_diagnostic') :
    ((strpos(current_language(), 'fr') === 0) ? 'Cours' : 'Course');
echo html_writer::tag('label', $courselabel, ['for' => 'qd-courseid']);
echo html_writer::start_tag('select', [
    'id' => 'qd-courseid',
    'name' => 'courseid',
    'class' => 'form-control',
]);
$courseplaceholder = $stringmanager->string_exists('tool_categories_by_context_course_placeholder', 'local_question_diagnostic') ?
    get_string('tool_categories_by_context_course_placeholder', 'local_question_diagnostic') :
    ((strpos(current_language(), 'fr') === 0) ? '— Sélectionner un cours —' : '— Select a course —');
echo html_writer::tag('option', $courseplaceholder, ['value' => 0]);
foreach ($courseoptions as $cid => $label) {
    echo html_writer::tag('option', $label, [
        'value' => (int)$cid,
        'selected' => ($courseid === (int)$cid),
    ]);
}
echo html_writer::end_tag('select');
$coursehelp = $stringmanager->string_exists('tool_categories_by_context_course_help', 'local_question_diagnostic') ?
    get_string('tool_categories_by_context_course_help', 'local_question_diagnostic') :
    ((strpos(current_language(), 'fr') === 0) ?
        'Choisissez une catégorie de cours pour obtenir une liste déroulante, ou tapez une recherche (nom/shortname) puis validez pour afficher une liste de résultats.' :
        'Pick a course category to populate a dropdown, or type a search (name/shortname) then submit to get a result list.');
echo html_writer::tag('div', $coursehelp, [
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
                // Si l'utilisateur a choisi le scope "quiz", on ne propose que les quiz.
                if ($scope === 'quiz' && $cm->modname !== 'quiz') {
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
  var scope = document.getElementById('qd-scope');
  var cmid = document.getElementById('qd-cmid');

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

  // Quand on change de périmètre / activité, on recharge la page si un cours est sélectionné.
  function hasCourseSelected() {
    return courseId && parseInt(courseId.value || '0', 10) > 0;
  }

  if (scope) {
    scope.addEventListener('change', function() {
      if (hasCourseSelected() && form) {
        form.submit();
      }
    });
  }

  if (cmid) {
    cmid.addEventListener('change', function() {
      if (hasCourseSelected() && form) {
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

// Contextes d'activités (modules) :
// - Par défaut (cmid=0) : toutes les activités du cours (ou seulement les quiz si scope=quiz)
// - Si cmid > 0 : uniquement l'activité sélectionnée (et éventuellement le cours selon scope).
if ($scope === 'all' || $scope === 'activities' || $scope === 'quiz') {
    if ($cmid > 0) {
        // Sécurité : vérifier que le cmid appartient bien au cours sélectionné.
        $cmsql = "SELECT cm.id, cm.course, m.name AS modname
                    FROM {course_modules} cm
                    INNER JOIN {modules} m ON m.id = cm.module
                   WHERE cm.id = :cmid";
        $cmrec = $DB->get_record_sql($cmsql, ['cmid' => $cmid], IGNORE_MISSING);
        if (!$cmrec || (int)$cmrec->course !== (int)$courseid) {
            echo html_writer::start_div('alert alert-danger', ['style' => 'margin-top: 15px;']);
            echo get_string('invalid_parameters', 'local_question_diagnostic') . ' (cmid=' . (int)$cmid . ')';
            echo html_writer::end_div();
            echo $OUTPUT->footer();
            exit;
        }
        if ($scope === 'quiz' && $cmrec->modname !== 'quiz') {
            echo html_writer::start_div('alert alert-warning', ['style' => 'margin-top: 15px; border-left: 4px solid #f0ad4e;']);
            echo get_string('tool_categories_by_context_activity_not_quiz', 'local_question_diagnostic', (object)[
                'modname' => (string)$cmrec->modname,
            ]);
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
    } else {
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

// Garder uniquement les catégories qui contiennent des questions DIRECTEMENT
// (ne pas inclure celles qui n'ont des questions que via des sous-catégories).
$filtered = [];
foreach ($categories as $cat) {
    $cid = (int)$cat->id;
    if (($directtotal[$cid] ?? 0) > 0) {
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

// ----------------------------------------------------------------------
// Détection Olution / commun (pour proposer un déplacement "vers commun/*")
// ----------------------------------------------------------------------
$olution = false;
$olutioncommun = null;
$olutioncommunoptions = [];
$olutioncommuncontextid = 0;
$olutiontriage = null;

try {
    $olution = local_question_diagnostic_find_olution_category();
    if ($olution && !empty($olution->id) && !empty($olution->contextid)) {
        $normalize = function(string $label): string {
            $label = trim($label);
            if (class_exists('\\core_text')) {
                if (method_exists('\\core_text', 'remove_accents')) {
                    $label = \core_text::remove_accents($label);
                } else if (method_exists('\\core_text', 'specialtoascii')) {
                    $label = \core_text::specialtoascii($label);
                }
                if (method_exists('\\core_text', 'strtolower')) {
                    $label = \core_text::strtolower($label);
                } else {
                    $label = strtolower($label);
                }
            } else {
                $label = strtolower($label);
            }
            $label = preg_replace('/\s+/', ' ', $label);
            return $label;
        };

        // Chercher la sous-catégorie DIRECTE "commun" sous la racine Olution (validation stricte).
        $children = $DB->get_records('question_categories', [
            'contextid' => (int)$olution->contextid,
            'parent' => (int)$olution->id,
        ], 'sortorder ASC, id ASC', 'id,name,parent,contextid');
        foreach ($children as $child) {
            if ($normalize((string)$child->name) === 'commun') {
                $olutioncommun = $child;
                $olutioncommuncontextid = (int)$child->contextid;
                break;
            }
        }

        if ($olutioncommun) {
            // Triage ("Question à trier") sous commun, si présent.
            $olutiontriage = \local_question_diagnostic\olution_manager::get_triage_category();

            $subcats = local_question_diagnostic_get_olution_subcategories((int)$olutioncommun->id);
            $targetids = [(int)$olutioncommun->id => true];
            foreach ($subcats as $sc) {
                if (!empty($sc->id)) {
                    $targetids[(int)$sc->id] = true;
                }
            }

            // Construire des libellés "commun → ..." (avec cache de lookup par id).
            $catcache = [];
            $getcat = function(int $id) use (&$catcache, $DB) {
                if (isset($catcache[$id])) {
                    return $catcache[$id];
                }
                $rec = $DB->get_record('question_categories', ['id' => (int)$id], 'id,name,parent,contextid', IGNORE_MISSING);
                $catcache[$id] = $rec ?: null;
                return $catcache[$id];
            };
            $buildlabel = function(int $id) use ($getcat, $olutioncommun): ?string {
                $parts = [];
                $current = $getcat($id);
                if (!$current) {
                    return null;
                }
                // Remonter jusqu'à commun (inclus).
                $guard = 0;
                while ($current && $guard < 200) {
                    $parts[] = format_string($current->name);
                    if ((int)$current->id === (int)$olutioncommun->id) {
                        break;
                    }
                    $pid = (int)($current->parent ?? 0);
                    if ($pid <= 0) {
                        return null;
                    }
                    $current = $getcat($pid);
                    $guard++;
                }
                if (!$current || (int)$current->id !== (int)$olutioncommun->id) {
                    return null;
                }
                $parts = array_reverse($parts);
                return implode(' → ', $parts);
            };

            foreach (array_keys($targetids) as $tid) {
                $label = $buildlabel((int)$tid);
                if ($label === null) {
                    continue;
                }
                $olutioncommunoptions[(int)$tid] = $label . ' (ID: ' . (int)$tid . ')';
            }
            asort($olutioncommunoptions);
        }
    }
} catch (Exception $e) {
    // Ignore : pas de bouton Olution/commun si non détecté.
}

// ----------------------------------------------------------------------
// Options de déplacement (nouveau parent) par contexte
// ----------------------------------------------------------------------
// On construit une liste "plate" des catégories disponibles comme parent, par contexte,
// avec indentation. Le déplacement de catégorie est limité au même contexte par Moodle.
$categoriesbyid = $categories; // map id => record
$childrenbycontext = [];
foreach ($categories as $cat) {
    $ctxid = (int)$cat->contextid;
    if (!isset($childrenbycontext[$ctxid])) {
        $childrenbycontext[$ctxid] = [];
    }
    $parentid = (int)$cat->parent;
    if (!isset($childrenbycontext[$ctxid][$parentid])) {
        $childrenbycontext[$ctxid][$parentid] = [];
    }
    $childrenbycontext[$ctxid][$parentid][] = (int)$cat->id;
}
// Tri stable par sortorder puis id (approx) : on réutilise l'ordre déjà trié en SQL,
// mais on sécurise quand même.
foreach ($childrenbycontext as $ctxid => $map) {
    foreach ($map as $pid => $ids) {
        sort($childrenbycontext[$ctxid][$pid]);
    }
}

$build_flat_options = function(int $contextid) use (&$build_flat_options, &$childrenbycontext, &$categoriesbyid): array {
    $options = [];

    // Option racine (parent=0).
    $options[0] = get_string('tool_categories_by_context_move_root', 'local_question_diagnostic');

    $walk = function(int $parentid, int $depth) use (&$walk, &$options, $contextid, &$childrenbycontext, &$categoriesbyid): void {
        if (empty($childrenbycontext[$contextid][$parentid])) {
            return;
        }
        foreach ($childrenbycontext[$contextid][$parentid] as $cid) {
            if (!isset($categoriesbyid[$cid])) {
                continue;
            }
            $cat = $categoriesbyid[$cid];
            $prefix = str_repeat('— ', max(0, $depth));
            $options[(int)$cid] = $prefix . format_string($cat->name) . ' (ID: ' . (int)$cid . ')';
            $walk((int)$cid, $depth + 1);
        }
    };

    $walk(0, 0);
    return $options;
};

$moveparentoptionsbycontext = [];
foreach ($contextids as $ctxid) {
    $moveparentoptionsbycontext[(int)$ctxid] = $build_flat_options((int)$ctxid);
}

$get_descendants = function(int $catid, int $contextid) use (&$get_descendants, &$childrenbycontext): array {
    $result = [];
    if (empty($childrenbycontext[$contextid][$catid])) {
        return $result;
    }
    foreach ($childrenbycontext[$contextid][$catid] as $childid) {
        $result[$childid] = true;
        foreach ($get_descendants((int)$childid, $contextid) as $d => $_) {
            $result[(int)$d] = true;
        }
    }
    return $result;
};

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

    // Déplacer vers Olution/commun/* (si Olution est détectée ET même contexte).
    if (!empty($olutioncommun) && !empty($olutioncommunoptions)) {
        if ((int)$cat->contextid === (int)$olutioncommuncontextid) {
            $actionurl = new moodle_url('/local/question_diagnostic/actions/move.php');
            echo html_writer::start_tag('form', [
                'method' => 'get',
                'action' => $actionurl->out(false),
                'style' => 'display:inline-flex; gap:6px; align-items:center; margin-left: 8px;',
            ]);
            echo html_writer::empty_tag('input', [
                'type' => 'hidden',
                'name' => 'id',
                'value' => $cid,
            ]);
            echo html_writer::empty_tag('input', [
                'type' => 'hidden',
                'name' => 'returnurl',
                'value' => $returnurl->out(false),
            ]);
            echo html_writer::empty_tag('input', [
                'type' => 'hidden',
                'name' => 'sesskey',
                'value' => sesskey(),
            ]);

            echo html_writer::start_tag('select', [
                'name' => 'parent',
                'class' => 'form-control form-control-sm',
                'title' => get_string('tool_categories_by_context_move_to_olution_commun', 'local_question_diagnostic'),
                'style' => 'max-width: 260px;',
            ]);
            foreach ($olutioncommunoptions as $pid => $label) {
                echo html_writer::tag('option', $label, [
                    'value' => (int)$pid,
                    'selected' => ((int)$cat->parent === (int)$pid),
                ]);
            }
            echo html_writer::end_tag('select');

            echo html_writer::tag('button', get_string('tool_categories_by_context_move_button', 'local_question_diagnostic'), [
                'type' => 'submit',
                'class' => 'btn btn-sm btn-primary',
                'title' => get_string('tool_categories_by_context_move_button_help', 'local_question_diagnostic'),
            ]);
            echo html_writer::end_tag('form');
        } else {
            // Contexte différent : Moodle n'autorise pas le re-parenting entre contextes.
            echo html_writer::tag('span',
                get_string('tool_categories_by_context_move_olution_commun_context_mismatch', 'local_question_diagnostic'),
                ['class' => 'text-muted', 'style' => 'margin-left: 8px; font-size: 12px;']
            );
        }
    }

    // Déplacer les QUESTIONS de cette catégorie vers Olution/commun/Question à trier (par défaut),
    // même si la cible est dans un autre contexte, si Moodle la considère "accessible" depuis le cours.
    if (!empty($olutiontriage) && !empty($olutiontriage->id) && $courseid > 0) {
        $actionurl = new moodle_url('/local/question_diagnostic/actions/move_category_questions_to_olution_triage.php');
        echo html_writer::start_tag('form', [
            'method' => 'get',
            'action' => $actionurl->out(false),
            'style' => 'display:inline-flex; gap:6px; align-items:center; margin-left: 8px;',
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sourcecatid', 'value' => $cid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => (int)$courseid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'returnurl', 'value' => $returnurl->out(false)]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::tag('button', get_string('tool_categories_by_context_move_questions_to_triage_button', 'local_question_diagnostic'), [
            'type' => 'submit',
            'class' => 'btn btn-sm btn-warning',
            'title' => get_string('tool_categories_by_context_move_questions_to_triage_button_help', 'local_question_diagnostic'),
        ]);
        echo html_writer::end_tag('form');
    }

    // Déplacer la catégorie (changer de parent) : sélecteur + confirmation via actions/move.php.
    $ctxid = (int)$cat->contextid;
    $options = $moveparentoptionsbycontext[$ctxid] ?? [];
    if (!empty($options)) {
        // Ne jamais proposer comme parent la catégorie elle-même ni ses descendants (évite les boucles).
        $invalid = $get_descendants($cid, $ctxid);
        $invalid[$cid] = true;

        $actionurl = new moodle_url('/local/question_diagnostic/actions/move.php');
        echo html_writer::start_tag('form', [
            'method' => 'get',
            'action' => $actionurl->out(false),
            'style' => 'display:inline-flex; gap:6px; align-items:center; margin-left: 8px;',
        ]);
        echo html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'id',
            'value' => $cid,
        ]);
        echo html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'returnurl',
            'value' => $returnurl->out(false),
        ]);
        echo html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'sesskey',
            'value' => sesskey(),
        ]);

        echo html_writer::start_tag('select', [
            'name' => 'parent',
            'class' => 'form-control form-control-sm',
            'title' => get_string('tool_categories_by_context_move_to', 'local_question_diagnostic'),
            'style' => 'max-width: 260px;',
        ]);
        foreach ($options as $pid => $label) {
            if (!empty($invalid[(int)$pid])) {
                continue;
            }
            echo html_writer::tag('option', $label, [
                'value' => (int)$pid,
                'selected' => ((int)$cat->parent === (int)$pid),
            ]);
        }
        echo html_writer::end_tag('select');

        echo html_writer::tag('button', get_string('move', 'local_question_diagnostic'), [
            'type' => 'submit',
            'class' => 'btn btn-sm btn-primary',
            'title' => get_string('tool_categories_by_context_move_button_help', 'local_question_diagnostic'),
        ]);
        echo html_writer::end_tag('form');
    }

    echo html_writer::end_tag('td');

    echo html_writer::end_tag('tr');
}

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');
echo html_writer::end_div();

echo $OUTPUT->footer();

