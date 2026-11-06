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

use local_question_diagnostic\question_analyzer;

/**
 * Tests du démasquage et de la réaffectation des entrées orphelines.
 *
 * @package    local_question_diagnostic
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(orphan_entry_repairer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(question_analyzer::class)]
final class repair_test extends \advanced_testcase {

    /** @var \core_question_generator */
    private $qgen;
    /** @var \context_module */
    private $context;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $qbank = $this->getDataGenerator()->create_module('qbank', ['course' => $course->id]);
        $this->context = \context_module::instance($qbank->cmid);
        $this->qgen = $this->getDataGenerator()->get_plugin_generator('core_question');
    }

    public function test_unhide_question_sets_ready_and_triggers_event(): void {
        global $DB;
        $cat = $this->qgen->create_question_category(['contextid' => $this->context->id]);
        $q = $this->qgen->create_question('truefalse', null, ['category' => $cat->id]);
        $DB->set_field('question_versions', 'status', 'hidden', ['questionid' => $q->id]);

        $sink = $this->redirectEvents();
        $this->assertTrue(question_analyzer::unhide_question($q->id));
        $events = $sink->get_events();
        $sink->close();

        $this->assertSame('ready', $DB->get_field('question_versions', 'status', ['questionid' => $q->id]));
        $this->assertInstanceOf(\core\event\question_updated::class, reset($events));
        $this->assertIsString(question_analyzer::unhide_question($q->id), 'Une question visible n\'est pas modifiée.');
    }

    public function test_unhide_batch_is_bounded(): void {
        $result = question_analyzer::unhide_questions_batch(range(1, question_analyzer::UNHIDE_MAX + 50));
        $this->assertSame(question_analyzer::UNHIDE_MAX, $result['success'] + $result['failed']);
    }

    public function test_reassign_orphan_entry(): void {
        global $DB;
        $lost = $this->qgen->create_question_category(['contextid' => $this->context->id]);
        $target = $this->qgen->create_question_category(['contextid' => $this->context->id]);
        $q = $this->qgen->create_question('truefalse', null, ['category' => $lost->id]);
        $DB->delete_records('question_categories', ['id' => $lost->id]);

        $this->assertSame(1, orphan_entry_repairer::reassign((int)$q->questionbankentryid, (int)$target->id));
        $this->assertEquals($target->id, $DB->get_field('question_bank_entries', 'questioncategoryid',
            ['id' => $q->questionbankentryid]));
    }

    public function test_reassign_refuses_top_category_and_idnumber_collision(): void {
        global $DB;
        $lost = $this->qgen->create_question_category(['contextid' => $this->context->id]);
        $target = $this->qgen->create_question_category(['contextid' => $this->context->id]);
        $q = $this->qgen->create_question('truefalse', null, ['category' => $lost->id, 'idnumber' => 'X1']);
        $this->qgen->create_question('truefalse', null, ['category' => $target->id, 'idnumber' => 'X1']);
        $DB->delete_records('question_categories', ['id' => $lost->id]);

        $top = question_get_top_category($this->context->id);
        try {
            orphan_entry_repairer::reassign((int)$q->questionbankentryid, (int)$top->id);
            $this->fail('Une catégorie top est refusée.');
        } catch (\moodle_exception $e) {
            $this->assertSame('orphan_entry_refused', $e->errorcode);
        }
        try {
            orphan_entry_repairer::reassign((int)$q->questionbankentryid, (int)$target->id);
            $this->fail('Un idnumber en collision est refusé.');
        } catch (\moodle_exception $e) {
            $this->assertSame('orphan_entry_refused', $e->errorcode);
        }
        $this->assertEquals($lost->id, $DB->get_field('question_bank_entries', 'questioncategoryid',
            ['id' => $q->questionbankentryid]), 'Rien n\'a changé.');
    }

    public function test_delete_empty_orphan_entry_only(): void {
        global $DB;
        $lost = $this->qgen->create_question_category(['contextid' => $this->context->id]);
        $q = $this->qgen->create_question('truefalse', null, ['category' => $lost->id]);
        $DB->delete_records('question_categories', ['id' => $lost->id]);
        $this->assertFalse(orphan_entry_repairer::delete_empty((int)$q->questionbankentryid), 'Elle contient une question.');

        $emptyid = $DB->insert_record('question_bank_entries', (object)['questioncategoryid' => $lost->id, 'ownerid' => 2]);
        $this->assertTrue(orphan_entry_repairer::delete_empty($emptyid));
        $this->assertFalse($DB->record_exists('question_bank_entries', ['id' => $emptyid]));
    }
}
