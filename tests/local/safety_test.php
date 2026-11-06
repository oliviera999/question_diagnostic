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

use core_question\local\bank\condition;
use local_question_diagnostic\question_analyzer;
use local_question_diagnostic\question_merger;
use mod_quiz\quiz_settings;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->libdir . '/questionlib.php');

/**
 * Tests des garde-fous de données : doublons, usage, suppression, fusion.
 *
 * @package    local_question_diagnostic
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(question_fingerprint::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(usage_checker::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(question_deleter::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(question_merger::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(question_analyzer::class)]
final class safety_test extends \advanced_testcase {

    /** @var \stdClass cours */
    private \stdClass $course;
    /** @var \stdClass catégorie de questions dans une banque mod_qbank */
    private \stdClass $category;
    /** @var \core_question_generator */
    private $qgen;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        usage_checker::reset_caches();
        question_fingerprint::reset_cache();
        $this->course = $this->getDataGenerator()->create_course();
        $qbank = $this->getDataGenerator()->create_module('qbank', ['course' => $this->course->id]);
        $context = \context_module::instance($qbank->cmid);
        $this->qgen = $this->getDataGenerator()->get_plugin_generator('core_question');
        $this->category = $this->qgen->create_question_category(['contextid' => $context->id]);
    }

    /**
     * Crée une question à choix multiple dans la catégorie (ou une autre).
     *
     * @param array $overrides
     * @param \stdClass|null $category
     * @return \stdClass
     */
    private function mc(array $overrides = [], ?\stdClass $category = null): \stdClass {
        $overrides = array_merge(['questiontext' => 'Quelle est la capitale ?'], $overrides);
        $overrides['questiontext'] = ['text' => $overrides['questiontext'], 'format' => FORMAT_HTML];
        $overrides['category'] = ($category ?? $this->category)->id;
        return $this->qgen->create_question('multichoice', 'one_of_four', $overrides);
    }

    /**
     * Crée un test dans le cours.
     *
     * @return \stdClass
     */
    private function quiz(): \stdClass {
        return $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id, 'questionsperpage' => 0]);
    }

    /**
     * Ajoute une tentative (ligne question_attempts) sur une question.
     *
     * @param int $questionid
     */
    private function attempt(int $questionid): void {
        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz',
            \context_course::instance($this->course->id));
        $quba->set_preferred_behaviour('deferredfeedback');
        $quba->add_question(\question_bank::load_question($questionid));
        $quba->start_all_questions();
        \question_engine::save_questions_usage_by_activity($quba);
    }

    /**
     * Change les réponses d'une question à choix multiple.
     *
     * @param int $questionid
     */
    private function change_first_answer(int $questionid): void {
        global $DB;
        $answer = $DB->get_records('question_answers', ['question' => $questionid], 'id ASC', '*', 0, 1);
        $answer = reset($answer);
        $DB->set_field('question_answers', 'answer', 'Réponse différente', ['id' => $answer->id]);
        \question_bank::notify_question_edited($questionid);
        question_fingerprint::reset_cache();
    }

    public function test_fingerprint_detects_identical_and_different_content(): void {
        $a = $this->mc();
        $b = $this->mc(['name' => 'Autre nom']);
        $c = $this->mc();
        $this->change_first_answer($c->id);

        $this->assertSame(question_fingerprint::compute($a->id), question_fingerprint::compute($b->id),
            'Le nom ne fait pas partie du contenu.');
        $this->assertNotSame(question_fingerprint::compute($a->id), question_fingerprint::compute($c->id),
            'Une réponse différente change l\'empreinte.');

        $group = question_analyzer::get_duplicate_group_question_ids_by_representative_id((int)$a->id);
        sort($group);
        $this->assertSame([(int)$a->id, (int)$b->id], $group, 'Même énoncé mais réponses différentes : pas un doublon.');
    }

    public function test_duplicates_are_limited_to_one_category(): void {
        $other = $this->qgen->create_question_category(['contextid' => $this->category->contextid]);
        $a = $this->mc();
        $this->mc([], $other);
        $this->assertSame([], question_analyzer::get_duplicate_group_question_ids_by_representative_id((int)$a->id));
    }

    public function test_usage_checker_sees_quiz_references_attempts_and_random_pools(): void {
        $inquiz = $this->mc();
        $attempted = $this->mc(['questiontext' => 'Autre']);
        $free = $this->mc(['questiontext' => 'Libre']);
        $quiz = $this->quiz();
        quiz_add_quiz_question($inquiz->id, $quiz);
        $this->attempt($attempted->id);

        $used = usage_checker::entries_in_use([$inquiz->id, $attempted->id, $free->id]);
        $this->assertTrue($used[$inquiz->id]);
        $this->assertTrue($used[$attempted->id]);
        $this->assertFalse($used[$free->id]);

        $this->assertFalse(usage_checker::category_used_by_random((int)$this->category->id));
        $this->add_random($quiz, (int)$this->category->id);
        usage_checker::reset_caches();
        $this->assertTrue(usage_checker::category_used_by_random((int)$this->category->id));
        $this->assertTrue(usage_checker::questions_in_random_pool([$free->id])[$free->id]);
    }

    public function test_usage_of_an_old_version_protects_the_entry(): void {
        $q = $this->mc();
        $this->attempt($q->id);
        $v2 = $this->qgen->update_question($q, null, ['questiontext' => ['text' => 'Version 2', 'format' => FORMAT_HTML]]);
        $this->assertTrue(usage_checker::entry_in_use((int)$v2->id), 'La tentative sur la v1 protège l\'entrée.');
    }

    public function test_usage_map_respects_pinned_versions(): void {
        global $DB;
        $q = $this->mc();
        $quiz = $this->quiz();
        quiz_add_quiz_question($q->id, $quiz);
        $v2 = $this->qgen->update_question($q, null, ['questiontext' => ['text' => 'Version 2', 'format' => FORMAT_HTML]]);
        // Figer la référence sur la version 1.
        $DB->set_field('question_references', 'version', 1, ['questionbankentryid' => $q->questionbankentryid]);

        $map = question_analyzer::get_questions_usage_by_ids([$q->id, $v2->id]);
        $this->assertSame(1, $map[$q->id]['quiz_count'], 'La version figée est utilisée.');
        $this->assertSame(0, $map[$v2->id]['quiz_count'], 'La version 2 n\'est pas servie par ce test.');
    }

    public function test_deleter_exports_then_deletes_unused_and_refuses_used(): void {
        global $DB;
        $unused = $this->mc();
        $used = $this->mc(['questiontext' => 'Utilisée']);
        quiz_add_quiz_question($used->id, $this->quiz());

        $res = question_deleter::delete_entries([$unused->id, $used->id], 'test');
        $this->assertSame([(int)$unused->id], $res->deleted);
        $this->assertSame(question_deleter::SKIP_USED, $res->skipped[$used->id]);
        $this->assertFalse($DB->record_exists('question', ['id' => $unused->id]));
        $this->assertTrue($DB->record_exists('question', ['id' => $used->id]));
        $this->assertFileExists($res->exportfile);
        $this->assertStringContainsString('Quelle est la capitale', file_get_contents($res->exportfile));
    }

    public function test_deleter_deletes_all_versions_and_refuses_random_pool(): void {
        global $DB;
        $q = $this->mc();
        $v2 = $this->qgen->update_question($q, null, ['questiontext' => ['text' => 'Version 2', 'format' => FORMAT_HTML]]);
        $res = question_deleter::delete_entries([$v2->id], 'test');
        $this->assertSame([(int)$v2->id], $res->deleted);
        $this->assertFalse($DB->record_exists('question', ['id' => $q->id]), 'La version 1 part aussi.');
        $this->assertFalse($DB->record_exists('question_bank_entries', ['id' => $q->questionbankentryid]));

        $pooled = $this->mc(['questiontext' => 'Réservoir']);
        $this->add_random($this->quiz(), (int)$this->category->id);
        usage_checker::reset_caches();
        $res = question_deleter::delete_entries([$pooled->id], 'test');
        $this->assertSame(question_deleter::SKIP_RANDOM, $res->skipped[$pooled->id]);
        $this->assertTrue($DB->record_exists('question', ['id' => $pooled->id]));
    }

    public function test_cleanup_duplicate_group_keeps_used_question(): void {
        global $DB;
        $a = $this->mc();
        $b = $this->mc();
        $c = $this->mc();
        quiz_add_quiz_question($b->id, $this->quiz());

        $res = question_analyzer::cleanup_duplicate_group((int)$a->id, 'test');
        $this->assertSame([(int)$b->id], $res->kept);
        sort($res->deleted);
        $this->assertSame([(int)$a->id, (int)$c->id], $res->deleted);
        $this->assertTrue($DB->record_exists('question', ['id' => $b->id]));
    }

    public function test_ui_deletion_path_deletes_unused_duplicates_only(): void {
        global $DB;
        $a = $this->mc();
        $b = $this->mc();
        $unique = $this->mc(['questiontext' => 'Unique']);
        quiz_add_quiz_question($b->id, $this->quiz());

        $checks = question_analyzer::can_delete_questions_batch([$a->id, $b->id, $unique->id]);
        $this->assertTrue($checks[$a->id]->can_delete, $checks[$a->id]->reason);
        $this->assertFalse($checks[$b->id]->can_delete, 'Utilisée.');
        $this->assertFalse($checks[$unique->id]->can_delete, 'Unique et visible.');

        $results = question_analyzer::delete_questions_safe([$a->id, $b->id], 'test');
        $this->assertTrue($results[$a->id]);
        $this->assertIsString($results[$b->id]);
        $this->assertFalse($DB->record_exists('question', ['id' => $a->id]));
        $this->assertTrue($DB->record_exists('question', ['id' => $b->id]));
    }

    public function test_merge_remaps_references_and_keeps_pinning(): void {
        global $DB;
        $ref = $this->mc();
        $dup = $this->mc();
        $this->attempt($ref->id);
        $quiza = $this->quiz();
        $quizb = $this->quiz();
        quiz_add_quiz_question($dup->id, $quiza);
        quiz_add_quiz_question($dup->id, $quizb);
        // Le test B fige la version 1 du doublon.
        $refb = $DB->get_record_sql("SELECT qr.* FROM {question_references} qr
            JOIN {quiz_slots} qs ON qs.id = qr.itemid AND qr.component = 'mod_quiz'
           WHERE qs.quizid = ?", [$quizb->id]);
        $DB->set_field('question_references', 'version', 1, ['id' => $refb->id]);

        $plan = question_merger::build_merge_plan((int)$ref->id);
        $this->assertSame([], $plan->errors);
        $this->assertSame((int)$ref->id, $plan->reference_questionid, 'La question avec tentatives est la référence.');
        $this->assertSame([(int)$dup->id], $plan->mergeable_questionids);

        $res = question_merger::apply_merge_plan($plan);
        $this->assertTrue($res->success, $res->message);
        $this->assertFalse($DB->record_exists('question', ['id' => $dup->id]));
        $this->assertSame(0, $DB->count_records('question_references', ['questionbankentryid' => $dup->questionbankentryid]));
        $this->assertSame(2, $DB->count_records('question_references', ['questionbankentryid' => $ref->questionbankentryid]));
        $pinned = $DB->get_record('question_references', ['id' => $refb->id]);
        $this->assertEquals(1, $pinned->version, 'La référence figée reste figée (dernière version de la référence).');
    }

    public function test_merge_skips_attempts_hidden_reference_and_old_pinned_versions(): void {
        global $DB;
        $hidden = $this->mc();
        $DB->set_field('question_versions', 'status', 'hidden', ['questionid' => $hidden->id]);
        $visible = $this->mc();
        $withattempts = $this->mc();
        $this->attempt($withattempts->id);
        $this->attempt($hidden->id);
        $this->attempt($hidden->id);

        $plan = question_merger::build_merge_plan((int)$hidden->id);
        $this->assertSame((int)$withattempts->id, $plan->reference_questionid, 'Une question cachée n\'est jamais la référence.');
        $this->assertContains((int)$hidden->id, $plan->skipped['has_attempts']);
        $this->assertSame([(int)$visible->id], $plan->mergeable_questionids);
    }

    public function test_merge_skips_random_pool(): void {
        $a = $this->mc();
        $this->mc();
        $this->add_random($this->quiz(), (int)$this->category->id);
        usage_checker::reset_caches();
        $plan = question_merger::build_merge_plan((int)$a->id);
        $this->assertSame([], $plan->mergeable_questionids);
        $this->assertCount(1, $plan->skipped['random_pool']);
    }

    public function test_used_question_ids_fails_closed(): void {
        // Une liste vide d'usage ne doit jamais venir d'une erreur : en cas d'erreur, tout est utilisé.
        $map = question_analyzer::get_questions_usage_by_ids([PHP_INT_MAX]);
        $this->assertArrayHasKey(PHP_INT_MAX, $map);
    }

    /**
     * Ajoute un tirage aléatoire sur une catégorie.
     *
     * @param \stdClass $quiz
     * @param int $categoryid
     */
    private function add_random(\stdClass $quiz, int $categoryid): void {
        $quizobj = quiz_settings::create($quiz->id);
        $quizobj->get_structure()->add_random_questions(1, 1, [
            'filter' => [
                'category' => [
                    'jointype' => condition::JOINTYPE_DEFAULT,
                    'values' => [$categoryid],
                    'filteroptions' => ['includesubcategories' => false],
                ],
            ],
        ]);
    }
}
