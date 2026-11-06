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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/question_diagnostic/lib.php');

/**
 * Tests des fonctions de lib.php (liens, banque Olution, CSV) et du journal en événements.
 *
 * @package    local_question_diagnostic
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('local_question_diagnostic_get_question_bank_url')]
#[\PHPUnit\Framework\Attributes\CoversFunction('local_question_diagnostic_find_olution_category')]
#[\PHPUnit\Framework\Attributes\CoversFunction('local_question_diagnostic_csv_cell')]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_question_diagnostic\audit_logger::class)]
final class lib_test extends \advanced_testcase {

    public function test_csv_cell_neutralises_formulas(): void {
        $this->assertSame('"\'=SUM(A1)"', local_question_diagnostic_csv_cell('=SUM(A1)'));
        $this->assertSame('"\'@cmd"', local_question_diagnostic_csv_cell('@cmd'));
        $this->assertSame('"a ""b"""', local_question_diagnostic_csv_cell('a "b"'));
        $this->assertSame('"12"', local_question_diagnostic_csv_cell(12));
    }

    public function test_question_bank_url_uses_cmid(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $qbank = $this->getDataGenerator()->create_module('qbank', ['course' => $course->id]);
        $context = \context_module::instance($qbank->cmid);
        $qgen = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $qgen->create_question_category(['contextid' => $context->id]);
        $q = $qgen->create_question('truefalse', null, ['category' => $cat->id]);

        $url = local_question_diagnostic_get_question_bank_url($cat, $q->id);
        $this->assertStringEndsWith('/question/edit.php', $url->get_path());
        $this->assertEquals($qbank->cmid, $url->get_param('cmid'));
        $this->assertSame($cat->id . ',' . $context->id, $url->get_param('cat'));

        $byquestion = local_question_diagnostic_get_question_bank_url(null, $q->id);
        $this->assertEquals($qbank->cmid, $byquestion->get_param('cmid'));

        $system = (object)['id' => 1, 'contextid' => \context_system::instance()->id];
        $this->assertNull(local_question_diagnostic_get_question_bank_url($system));
    }

    public function test_find_olution_category_by_setting_and_by_name(): void {
        $this->resetAfterTest();
        $this->assertFalse(local_question_diagnostic_find_olution_category());

        $course = $this->getDataGenerator()->create_course();
        $qbank = $this->getDataGenerator()->create_module('qbank',
            ['course' => $course->id, 'name' => 'olution banques de questions partagées']);
        $context = \context_module::instance($qbank->cmid);
        question_get_top_category($context->id, true);

        $root = local_question_diagnostic_find_olution_category();
        $this->assertNotFalse($root, 'Seule banque nommée « olution » : retenue sans réglage.');
        $this->assertEquals(0, $root->parent);
        $this->assertEquals($context->id, $root->contextid);
        $this->assertEquals($qbank->cmid, $root->cmid);

        $other = $this->getDataGenerator()->create_module('qbank', ['course' => $course->id, 'name' => 'Olution bis']);
        $this->assertFalse(local_question_diagnostic_find_olution_category(), 'Deux candidates : réglage obligatoire.');

        set_config('olution_qbank_cmid', $other->cmid, 'local_question_diagnostic');
        $root = local_question_diagnostic_find_olution_category();
        $this->assertEquals($other->cmid, $root->cmid);
    }

    public function test_audit_logger_writes_an_event(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $sink = $this->redirectEvents();
        \local_question_diagnostic\audit_logger::log_action('test_action', ['a' => 1], 42);
        $events = $sink->get_events();
        $sink->close();
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf(\local_question_diagnostic\event\operation_performed::class, $event);
        $this->assertSame('test_action', $event->other['action']);
        $this->assertSame(42, $event->other['objectid']);
    }
}
