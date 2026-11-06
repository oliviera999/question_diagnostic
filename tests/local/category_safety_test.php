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
use local_question_diagnostic\category_manager;
use mod_quiz\quiz_settings;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/local/question_diagnostic/classes/category_manager.php');

/**
 * Tests des garde-fous des opérations sur les catégories : suppression, fusion, déplacement, nettoyage global.
 *
 * @package    local_question_diagnostic
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_question_diagnostic\category_manager::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_question_diagnostic\task\cleanup_categories_task::class)]
final class category_safety_test extends \advanced_testcase {

    /** @var \stdClass cours */
    private \stdClass $course;
    /** @var \context_module contexte de la banque mod_qbank */
    private \context_module $context;
    /** @var \stdClass catégorie racine (« top ») du contexte */
    private \stdClass $top;
    /** @var \core_question_generator */
    private $qgen;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->init_bank();
    }

    /**
     * Crée le cours, la banque mod_qbank et une catégorie témoin.
     */
    private function init_bank(): void {
        usage_checker::reset_caches();
        $this->course = $this->getDataGenerator()->create_course();
        $qbank = $this->getDataGenerator()->create_module('qbank', ['course' => $this->course->id]);
        $this->context = \context_module::instance($qbank->cmid);
        $this->qgen = $this->getDataGenerator()->get_plugin_generator('core_question');
        $this->top = question_get_top_category($this->context->id, true);
        // Une catégorie témoin sous « top » : aucune catégorie de test n'est la seule sous-catégorie de « top ».
        $this->cat(['name' => 'Témoin']);
    }

    /**
     * Nombre de transactions déléguées ouvertes.
     *
     * Sous PostgreSQL, chaque test tourne dans une transaction ouverte par advanced_testcase : on compare donc
     * la profondeur avant/après plutôt que is_transaction_started().
     *
     * @return int
     */
    private function transaction_depth(): int {
        global $DB;
        $property = new \ReflectionProperty(\moodle_database::class, 'transactions');
        return count((array)$property->getValue($DB));
    }

    /**
     * Sort de la transaction englobante du test, pour vérifier un vrai rollback.
     */
    private function leave_test_transaction(): void {
        global $DB;
        // Annuler (et marquer comme terminée) la transaction du test : advanced_testcase fera alors une
        // remise à zéro complète de la base à la fin du test.
        $property = new \ReflectionProperty(\advanced_testcase::class, 'testdbtransaction');
        $transaction = $property->getValue($this);
        if ($transaction && !$transaction->is_disposed()) {
            try {
                $transaction->rollback(new \moodle_exception('generalexceptionmessage', 'error', '', 'sortie du test'));
            } catch (\moodle_exception $e) {
                // Attendu : rollback() relance l'exception.
                unset($e);
            }
        }
        \context_helper::reset_caches();
        \cache_helper::purge_all();
        $this->setAdminUser();
        $this->init_bank();
        $this->assertFalse($DB->is_transaction_started());
    }

    /**
     * Crée une catégorie dans la banque (sous « top » par défaut).
     *
     * @param array $record
     * @return \stdClass
     */
    private function cat(array $record = []): \stdClass {
        $record['contextid'] = $this->context->id;
        $record['parent'] = $record['parent'] ?? $this->top->id;
        return $this->qgen->create_question_category($record);
    }

    /**
     * Crée une question dans une catégorie.
     *
     * @param \stdClass $category
     * @param array $overrides
     * @return \stdClass
     */
    private function question(\stdClass $category, array $overrides = []): \stdClass {
        $overrides['category'] = $category->id;
        return $this->qgen->create_question('shortanswer', null, $overrides);
    }

    /**
     * Ajoute un tirage aléatoire sur une catégorie.
     *
     * @param int $categoryid
     * @param bool $subcategories
     */
    private function add_random(int $categoryid, bool $subcategories = false): void {
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id, 'questionsperpage' => 0]);
        $quizobj = quiz_settings::create($quiz->id);
        $quizobj->get_structure()->add_random_questions(1, 1, [
            'filter' => [
                'category' => [
                    'jointype' => condition::JOINTYPE_DEFAULT,
                    'values' => [$categoryid],
                    'filteroptions' => ['includesubcategories' => $subcategories],
                ],
            ],
        ]);
        usage_checker::reset_caches();
    }

    /**
     * Catégorie d'une question.
     *
     * @param \stdClass $question
     * @return int
     */
    private function category_of(\stdClass $question): int {
        global $DB;
        return (int)$DB->get_field('question_bank_entries', 'questioncategoryid', ['id' => $question->questionbankentryid]);
    }

    public function test_merge_into_descendant_is_refused(): void {
        global $DB;
        $source = $this->cat(['name' => 'Source']);
        $child = $this->cat(['name' => 'Enfant', 'parent' => $source->id]);
        $grandchild = $this->cat(['name' => 'Petit-enfant', 'parent' => $child->id]);
        $q = $this->question($source);
        $depth = $this->transaction_depth();

        foreach ([$child, $grandchild] as $dest) {
            $result = category_manager::merge_categories($source->id, $dest->id);
            $this->assertIsString($result);
            $this->assertStringContainsString('boucle', $result);
        }
        $this->assertTrue($DB->record_exists('question_categories', ['id' => $source->id]));
        $this->assertEquals($this->top->id, $DB->get_field('question_categories', 'parent', ['id' => $source->id]));
        $this->assertEquals($source->id, $DB->get_field('question_categories', 'parent', ['id' => $child->id]));
        $this->assertSame((int)$source->id, $this->category_of($q));
        $this->assertSame($depth, $this->transaction_depth());
    }

    public function test_merge_moves_questions_via_core_and_deletes_source(): void {
        global $DB;
        $source = $this->cat(['name' => 'Source']);
        $sub = $this->cat(['name' => 'Sous-catégorie', 'parent' => $source->id]);
        $dest = $this->cat(['name' => 'Destination']);
        $q1 = $this->question($source, ['idnumber' => 'Q1']);
        $q2 = $this->question($source);
        $kept = $this->question($dest, ['idnumber' => 'Q2']);
        $depth = $this->transaction_depth();

        $sink = $this->redirectEvents();
        $result = category_manager::merge_categories($source->id, $dest->id);
        $events = $sink->get_events();
        $sink->close();

        $this->assertTrue($result, is_string($result) ? $result : '');
        $this->assertFalse($DB->record_exists('question_categories', ['id' => $source->id]));
        $this->assertSame((int)$dest->id, $this->category_of($q1));
        $this->assertSame((int)$dest->id, $this->category_of($q2));
        $this->assertSame((int)$dest->id, $this->category_of($kept));
        $this->assertSame('Q1', $DB->get_field('question_bank_entries', 'idnumber', ['id' => $q1->questionbankentryid]));
        $this->assertEquals($dest->id, $DB->get_field('question_categories', 'parent', ['id' => $sub->id]));

        $moved = array_filter($events, fn($e) => $e instanceof \core\event\question_moved);
        $this->assertCount(2, $moved, 'Le déplacement passe par l\'API du cœur (événements question_moved).');
        $deleted = array_filter($events, fn($e) => $e instanceof \core\event\question_category_deleted);
        $this->assertCount(1, $deleted);
        $this->assertSame($depth, $this->transaction_depth());
    }

    public function test_delete_and_merge_refused_when_used_by_random(): void {
        global $DB;
        $pool = $this->cat(['name' => 'Réservoir']);
        $dest = $this->cat(['name' => 'Destination']);
        $this->add_random((int)$pool->id);

        $result = category_manager::delete_category($pool->id);
        $this->assertIsString($result);
        $this->assertStringContainsString('aléatoire', $result);
        $this->assertTrue($DB->record_exists('question_categories', ['id' => $pool->id]));

        $q = $this->question($pool);
        $result = category_manager::merge_categories($pool->id, $dest->id);
        $this->assertIsString($result);
        $this->assertStringContainsString('aléatoire', $result);
        $this->assertTrue($DB->record_exists('question_categories', ['id' => $pool->id]));
        $this->assertSame((int)$pool->id, $this->category_of($q));

        // Sous-catégorie d'un tirage « inclure les sous-catégories » : protégée aussi.
        $parent = $this->cat(['name' => 'Parent tiré']);
        $child = $this->cat(['name' => 'Enfant vide', 'parent' => $parent->id]);
        $this->add_random((int)$parent->id, true);
        $result = category_manager::delete_category($child->id);
        $this->assertIsString($result);
        $this->assertTrue($DB->record_exists('question_categories', ['id' => $child->id]));
        $result = category_manager::move_category($child->id, $dest->id);
        $this->assertIsString($result);
        $this->assertEquals($parent->id, $DB->get_field('question_categories', 'parent', ['id' => $child->id]));
    }

    public function test_move_to_parent_zero_and_moving_top_are_refused(): void {
        global $DB;
        $cat = $this->cat(['name' => 'À déplacer']);
        $other = $this->cat(['name' => 'Autre']);
        $depth = $this->transaction_depth();

        $result = category_manager::move_category($cat->id, 0);
        $this->assertIsString($result);
        $this->assertEquals($this->top->id, $DB->get_field('question_categories', 'parent', ['id' => $cat->id]));
        $this->assertSame(1, $DB->count_records('question_categories', ['contextid' => $this->context->id, 'parent' => 0]));

        $result = category_manager::move_category($this->top->id, $other->id);
        $this->assertIsString($result);
        $this->assertEquals(0, $DB->get_field('question_categories', 'parent', ['id' => $this->top->id]));

        // Autre contexte : refusé.
        $qbank2 = $this->getDataGenerator()->create_module('qbank', ['course' => $this->course->id]);
        $elsewhere = $this->qgen->create_question_category(['contextid' => \context_module::instance($qbank2->cmid)->id]);
        $this->assertIsString(category_manager::move_category($cat->id, $elsewhere->id));

        // Cas normal : déplacement dans le même contexte par l'API du cœur.
        $this->assertTrue(category_manager::move_category($cat->id, $other->id));
        $this->assertEquals($other->id, $DB->get_field('question_categories', 'parent', ['id' => $cat->id]));
        // Et pas de boucle.
        $this->assertIsString(category_manager::move_category($other->id, $cat->id));
        $this->assertSame($depth, $this->transaction_depth());
    }

    public function test_delete_refuses_non_empty_top_and_protected_categories(): void {
        global $DB;
        $withquestion = $this->cat(['name' => 'Avec question']);
        $this->question($withquestion);
        $withchild = $this->cat(['name' => 'Avec enfant']);
        $this->cat(['name' => 'Enfant', 'parent' => $withchild->id]);
        $withinfo = $this->cat(['name' => 'Avec description', 'info' => 'Usage voulu']);
        $withidnumber = $this->cat(['name' => 'Avec idnumber', 'idnumber' => 'CAT1']);
        $empty = $this->cat(['name' => 'Vide']);
        $depth = $this->transaction_depth();

        foreach ([$withquestion, $withchild, $withinfo, $withidnumber, $this->top] as $category) {
            $result = category_manager::delete_category($category->id);
            $this->assertIsString($result, 'Refus attendu pour ' . $category->name);
            $this->assertTrue($DB->record_exists('question_categories', ['id' => $category->id]));
        }
        $this->assertSame(1, $DB->count_records('question_bank_entries', ['questioncategoryid' => $withquestion->id]));

        $this->assertTrue(category_manager::delete_category($empty->id));
        $this->assertFalse($DB->record_exists('question_categories', ['id' => $empty->id]));
        $this->assertSame($depth, $this->transaction_depth());
    }

    public function test_merge_refused_on_idnumber_collision(): void {
        global $DB;
        $source = $this->cat(['name' => 'Source']);
        $dest = $this->cat(['name' => 'Destination']);
        $q = $this->question($source, ['idnumber' => 'X']);
        $this->question($dest, ['idnumber' => 'X']);

        $result = category_manager::merge_categories($source->id, $dest->id);
        $this->assertIsString($result);
        $this->assertStringContainsString('idnumber', $result);
        $this->assertSame((int)$source->id, $this->category_of($q));
        $this->assertSame('X', $DB->get_field('question_bank_entries', 'idnumber', ['id' => $q->questionbankentryid]));
        $this->assertTrue($DB->record_exists('question_categories', ['id' => $source->id]));
    }

    public function test_merge_failure_rolls_back_and_closes_transaction(): void {
        global $DB;
        // Un vrai rollback n'est observable qu'en dehors de la transaction englobante du test.
        $this->leave_test_transaction();
        $source = $this->cat(['name' => 'Source']);
        $dest = $this->cat(['name' => 'Destination']);
        $first = $this->cat(['name' => 'Première', 'parent' => $source->id]);
        $broken = $this->cat(['name' => 'Seconde', 'parent' => $source->id]);
        // Nom vide (données restaurées abîmées) : le cœur refuse de mettre à jour cette sous-catégorie,
        // APRÈS avoir déplacé la première.
        $DB->set_field('question_categories', 'name', '', ['id' => $broken->id]);
        $DB->set_field('question_categories', 'sortorder', 1, ['id' => $first->id]);
        $DB->set_field('question_categories', 'sortorder', 2, ['id' => $broken->id]);
        $q = $this->question($source);

        $result = category_manager::merge_categories($source->id, $dest->id);
        $this->assertIsString($result);
        $this->assertFalse($DB->is_transaction_started(), 'La transaction doit être annulée, pas laissée ouverte.');
        $this->assertEquals($source->id, $DB->get_field('question_categories', 'parent', ['id' => $first->id]),
            'Le déplacement de la première sous-catégorie est annulé.');
        $this->assertEquals($source->id, $DB->get_field('question_categories', 'parent', ['id' => $broken->id]));
        $this->assertSame((int)$source->id, $this->category_of($q));
        $this->assertTrue($DB->record_exists('question_categories', ['id' => $source->id]));

        // Une opération suivante est réellement validée (pas imbriquée dans une transaction orpheline).
        $empty = $this->cat(['name' => 'Vide']);
        $this->assertTrue(category_manager::delete_category($empty->id));
        $this->assertFalse($DB->is_transaction_started());
        $this->assertFalse($DB->record_exists('question_categories', ['id' => $empty->id]));
    }

    public function test_frozen_cleanup_does_not_cascade(): void {
        global $DB;
        $parent = $this->cat(['name' => 'Parent']);
        $child = $this->cat(['name' => 'Enfant vide', 'parent' => $parent->id]);
        $later = $this->cat(['name' => 'Remplie plus tard']);
        $pool = $this->cat(['name' => 'Réservoir vide']);
        $described = $this->cat(['name' => 'Décrite', 'info' => 'Usage voulu']);
        $this->add_random((int)$pool->id);

        $candidates = category_manager::get_cleanup_candidates();
        $ids = array_map(fn($c) => (int)$c->id, $candidates->candidates);
        $this->assertContains((int)$child->id, $ids);
        $this->assertContains((int)$later->id, $ids);
        $this->assertNotContains((int)$parent->id, $ids, 'Un parent n\'est jamais candidat.');
        $this->assertNotContains((int)$pool->id, $ids, 'Une catégorie tirée au sort n\'est jamais candidate.');
        $this->assertNotContains((int)$described->id, $ids);
        $this->assertNotContains((int)$this->top->id, $ids);

        // Entre le lancement et l'exécution, une question arrive dans une catégorie de la liste.
        $this->question($later);

        $result = category_manager::delete_frozen_category_list($ids);
        $this->assertContains((int)$child->id, $result->deleted);
        $this->assertArrayHasKey((int)$later->id, $result->skipped);
        $this->assertFalse($DB->record_exists('question_categories', ['id' => $child->id]));
        $this->assertTrue($DB->record_exists('question_categories', ['id' => $parent->id]),
            'Le parent devenu vide n\'est pas supprimé (pas de cascade).');
        $this->assertTrue($DB->record_exists('question_categories', ['id' => $later->id]));
        $this->assertTrue($DB->record_exists('question_categories', ['id' => $pool->id]));
        $this->assertSame(1, $DB->count_records('question_categories', ['contextid' => $this->context->id, 'parent' => 0]));
    }

    public function test_cleanup_task_processes_only_the_frozen_list(): void {
        global $DB;
        $parent = $this->cat(['name' => 'Parent']);
        $child = $this->cat(['name' => 'Enfant vide', 'parent' => $parent->id]);

        $candidates = category_manager::get_cleanup_candidates();
        $ids = array_map(fn($c) => (int)$c->id, $candidates->candidates);
        \local_question_diagnostic\task\cleanup_categories_task::queue($ids, 'test');

        $sink = $this->redirectMessages();
        ob_start();
        $this->runAdhocTasks(\local_question_diagnostic\task\cleanup_categories_task::class);
        ob_end_clean();
        $sink->close();

        $this->assertFalse($DB->record_exists('question_categories', ['id' => $child->id]));
        $this->assertTrue($DB->record_exists('question_categories', ['id' => $parent->id]));
    }
}
