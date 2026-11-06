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

use local_question_diagnostic\orphan_file_detector;
use local_question_diagnostic\orphan_file_repairer;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/local/question_diagnostic/classes/orphan_file_detector.php');
require_once($CFG->dirroot . '/local/question_diagnostic/classes/orphan_file_repairer.php');

/**
 * Tests de la détection, de la suppression (avec archivage) et de la copie des fichiers orphelins.
 *
 * @package    local_question_diagnostic
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(orphan_file_detector::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(orphan_file_repairer::class)]
final class orphan_files_test extends \advanced_testcase {

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
        $this->course = $this->getDataGenerator()->create_course();
        $qbank = $this->getDataGenerator()->create_module('qbank', ['course' => $this->course->id]);
        $context = \context_module::instance($qbank->cmid);
        $this->qgen = $this->getDataGenerator()->get_plugin_generator('core_question');
        $this->category = $this->qgen->create_question_category(['contextid' => $context->id]);
    }

    /**
     * Crée un fichier via l'API.
     *
     * @param int $contextid
     * @param string $component
     * @param string $filearea
     * @param int $itemid
     * @param string $filename
     * @param string $content
     * @return \stored_file
     */
    private function make_file(int $contextid, string $component, string $filearea, int $itemid,
            string $filename = 'image.png', string $content = 'contenu'): \stored_file {
        return get_file_storage()->create_file_from_string([
            'contextid' => $contextid,
            'component' => $component,
            'filearea' => $filearea,
            'itemid' => $itemid,
            'filepath' => '/',
            'filename' => $filename,
        ], $content);
    }

    /**
     * Crée un fichier dans un contexte de cours, puis supprime la ligne de contexte : fichier réellement orphelin.
     *
     * @param string $component
     * @param string $filearea
     * @param string $filename
     * @param string $content
     * @return \stored_file
     */
    private function make_orphan(string $component = 'course', string $filearea = 'summary',
            string $filename = 'orphelin.png', string $content = 'contenu orphelin'): \stored_file {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $file = $this->make_file($context->id, $component, $filearea, 0, $filename, $content);
        $DB->delete_records('context', ['id' => $context->id]);
        \context_helper::reset_caches();
        return $file;
    }

    /**
     * Ids des fichiers détectés comme orphelins.
     *
     * @return int[]
     */
    private function orphan_ids(): array {
        return array_map(function ($o) {
            return (int)$o->file->id;
        }, orphan_file_detector::get_orphan_files(false, 0));
    }

    public function test_forum_attachment_with_post_id_greater_than_forum_id_is_not_orphan(): void {
        global $DB;
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $this->course->id]);
        $cm = get_coursemodule_from_instance('forum', $forum->id);
        $context = \context_module::instance($cm->id);
        $fgen = $this->getDataGenerator()->get_plugin_generator('mod_forum');
        $user = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        // On crée des messages jusqu'à obtenir un id de message qui ne correspond à aucun forum
        // (cas où l'ancienne détection « itemid = forum.id » signalait le fichier à tort).
        $postid = 0;
        for ($i = 0; $i < 5; $i++) {
            $discussion = $fgen->create_discussion(['course' => $this->course->id, 'forum' => $forum->id,
                'userid' => $user->id]);
            $postid = (int)$discussion->firstpost;
            if ($postid > $forum->id && !$DB->record_exists('forum', ['id' => $postid])) {
                break;
            }
        }
        $this->assertGreaterThan($forum->id, $postid);
        $this->assertFalse($DB->record_exists('forum', ['id' => $postid]));

        $file = $this->make_file($context->id, 'mod_forum', 'attachment', $postid, 'devoir_eleve.pdf');
        $record = $DB->get_record('files', ['id' => $file->get_id()]);

        $this->assertNotContains((int)$file->get_id(), $this->orphan_ids());
        $this->assertFalse(orphan_file_detector::is_orphan($record));
        $this->assertFalse(orphan_file_detector::is_safe_to_delete($record));

        $result = orphan_file_detector::delete_orphan_file($file->get_id());
        $this->assertFalse($result['success']);
        $this->assertTrue($DB->record_exists('files', ['id' => $file->get_id()]));
    }

    public function test_question_answer_image_is_not_orphan(): void {
        global $DB;
        $question = $this->qgen->create_question('multichoice', 'one_of_four', ['category' => $this->category->id]);
        $answer = $DB->get_records('question_answers', ['question' => $question->id], 'id DESC', '*', 0, 1);
        $answer = reset($answer);
        // L'itemid d'une image de réponse est question_answers.id, pas question.id.
        $this->assertNotEquals($question->id, $answer->id);

        $file = $this->make_file($this->category->contextid, 'question', 'answer', (int)$answer->id, 'reponse.png');
        $hint = $this->make_file($this->category->contextid, 'question', 'hint', 999999, 'indice.png');
        $record = $DB->get_record('files', ['id' => $file->get_id()]);

        $ids = $this->orphan_ids();
        $this->assertNotContains((int)$file->get_id(), $ids);
        $this->assertNotContains((int)$hint->get_id(), $ids);
        $this->assertFalse(orphan_file_detector::is_safe_to_delete($record));
    }

    public function test_file_with_deleted_context_is_orphan(): void {
        global $DB;
        $orphan = $this->make_orphan();
        $record = $DB->get_record('files', ['id' => $orphan->get_id()]);

        $ids = $this->orphan_ids();
        $this->assertContains((int)$orphan->get_id(), $ids);
        $this->assertTrue(orphan_file_detector::is_orphan($record));
        $this->assertTrue(orphan_file_detector::is_safe_to_delete($record));

        // Les entrées « dossier » ('.') ne sont jamais listées.
        foreach (orphan_file_detector::get_orphan_files(false, 0) as $o) {
            $this->assertNotSame('.', $o->file->filename);
        }

        $stats = orphan_file_detector::get_global_stats(false);
        $this->assertSame(1, $stats->total_orphans);
        $this->assertSame(1, $stats->deletable);
        $this->assertSame(0, $stats->protected);
        $this->assertSame(['course' => 1], $stats->by_component);

        $byids = orphan_file_detector::get_orphans_by_ids([(int)$orphan->get_id(), 123456789]);
        $this->assertSame([(int)$orphan->get_id()], array_map('intval', array_keys($byids)));
    }

    public function test_user_drafts_and_protected_areas_are_never_deletable(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $usercontext = \context_user::instance($user->id);
        $draft = $this->make_file($usercontext->id, 'user', 'draft', file_get_unused_draft_itemid(), 'brouillon.png');
        // Même si le contexte disparaît, le brouillon reste protégé (signalement uniquement).
        $DB->delete_records('context', ['id' => $usercontext->id]);
        \context_helper::reset_caches();
        $response = $this->make_orphan('question', 'response_attachments', 'copie_eleve.pdf');
        $submission = $this->make_orphan('assignsubmission_file', 'submission_files', 'devoir.pdf');

        foreach ([$draft, $response, $submission] as $file) {
            $record = $DB->get_record('files', ['id' => $file->get_id()]);
            $this->assertTrue(orphan_file_detector::is_orphan($record));
            $this->assertNotNull(orphan_file_detector::get_exclusion_reason($record));
            $this->assertFalse(orphan_file_detector::is_safe_to_delete($record));
            $this->assertFalse(orphan_file_repairer::can_repair($record));
        }

        $results = orphan_file_detector::delete_multiple_orphans([$draft->get_id(), $response->get_id(), $submission->get_id()]);
        $this->assertSame(0, $results['success']);
        $this->assertSame(3, $results['failed']);
        foreach ([$draft, $response, $submission] as $file) {
            $this->assertTrue($DB->record_exists('files', ['id' => $file->get_id()]));
        }

        $stats = orphan_file_detector::get_global_stats(false);
        $this->assertSame(3, $stats->protected);
        $this->assertSame(0, $stats->deletable);
    }

    public function test_deletion_archives_then_deletes(): void {
        global $DB;
        $orphan = $this->make_orphan('course', 'summary', 'schema.png', 'contenu à archiver');
        $fileid = (int)$orphan->get_id();
        $contenthash = $orphan->get_contenthash();

        // Simulation : rien n'est supprimé.
        $dry = orphan_file_detector::delete_multiple_orphans([$fileid], true);
        $this->assertSame(1, $dry['success']);
        $this->assertTrue($DB->record_exists('files', ['id' => $fileid]));

        $results = orphan_file_detector::delete_multiple_orphans([$fileid]);
        $this->assertSame(1, $results['success']);
        $this->assertSame(0, $results['failed']);
        $this->assertFalse($DB->record_exists('files', ['id' => $fileid]));
        // Les entrées « dossier » de la zone orpheline vidée sont aussi supprimées.
        $this->assertFalse($DB->record_exists('files', ['contextid' => $orphan->get_contextid()]));

        $dir = orphan_file_detector::get_archive_dir();
        $this->assertStringNotContainsString('/temp/', $dir);
        $archived = $dir . '/' . $fileid . '_' . $contenthash;
        $this->assertFileExists($archived);
        $this->assertSame('contenu à archiver', file_get_contents($archived));

        $metadata = json_decode(file_get_contents($dir . '/metadata.json'), true);
        $entries = array_values(array_filter($metadata, function ($e) use ($fileid) {
            return (int)$e['fileid'] === $fileid;
        }));
        $this->assertCount(1, $entries);
        $this->assertSame('schema.png', $entries[0]['filename']);
        $this->assertSame($contenthash, $entries[0]['contenthash']);
        $this->assertSame('delete', $entries[0]['operation']);
        // Aucune donnée personnelle dans les métadonnées.
        foreach (['userid', 'author', 'archived_by', 'email', 'fullname'] as $key) {
            $this->assertArrayNotHasKey($key, $entries[0]);
        }
    }

    public function test_archive_refuses_live_files(): void {
        $live = $this->make_file(\context_course::instance($this->course->id)->id, 'course', 'summary', 0, 'vivant.png');
        $result = orphan_file_detector::archive_orphan_file($live->get_id());
        $this->assertFalse($result['success']);

        $orphan = $this->make_orphan();
        $result = orphan_file_detector::archive_orphan_file($orphan->get_id());
        $this->assertTrue($result['success']);
        $this->assertFileExists($result['archive_path']);
    }

    public function test_repair_copies_via_file_api_and_keeps_original(): void {
        global $DB;
        $question = $this->qgen->create_question('shortanswer', null, ['category' => $this->category->id]);
        $orphan = $this->make_orphan('course', 'summary', 'figure.png', 'contenu figure');
        $originalrecord = $DB->get_record('files', ['id' => $orphan->get_id()]);

        $dry = orphan_file_repairer::copy_to_question((int)$orphan->get_id(), (int)$question->id, true);
        $this->assertTrue($dry['success']);
        $this->assertNull($dry['newfileid']);

        $result = orphan_file_repairer::copy_to_question((int)$orphan->get_id(), (int)$question->id);
        $this->assertTrue($result['success'], $result['message']);
        $this->assertNotEmpty($result['newfileid']);

        $fs = get_file_storage();
        $hash = \file_storage::get_pathname_hash($this->category->contextid, 'question', 'questiontext',
            $question->id, '/', 'figure.png');
        $newfile = $fs->get_file_by_hash($hash);
        $this->assertNotFalse($newfile);
        $this->assertEquals($result['newfileid'], $newfile->get_id());
        $this->assertSame($orphan->get_contenthash(), $newfile->get_contenthash());
        $this->assertSame('contenu figure', $newfile->get_content());
        $this->assertSame($hash, $DB->get_field('files', 'pathnamehash', ['id' => $newfile->get_id()]));

        // L'original est intact.
        $this->assertEquals($originalrecord, $DB->get_record('files', ['id' => $orphan->get_id()]));

        // Un second essai ne remplace pas le fichier copié : nom libre choisi par l'API.
        $again = orphan_file_repairer::copy_to_question((int)$orphan->get_id(), (int)$question->id);
        $this->assertTrue($again['success']);
        $this->assertNotEquals($result['newfileid'], $again['newfileid']);
        $this->assertNotFalse($fs->get_file_by_id($again['newfileid']));
    }

    public function test_repair_refuses_live_file_and_unknown_question(): void {
        global $DB;
        $question = $this->qgen->create_question('shortanswer', null, ['category' => $this->category->id]);
        $live = $this->make_file(\context_course::instance($this->course->id)->id, 'course', 'summary', 0, 'vivant.png');

        $result = orphan_file_repairer::copy_to_question((int)$live->get_id(), (int)$question->id);
        $this->assertFalse($result['success']);

        $orphan = $this->make_orphan();
        $before = $DB->count_records('files');
        $result = orphan_file_repairer::copy_to_question((int)$orphan->get_id(), 987654321);
        $this->assertFalse($result['success']);
        $this->assertSame($before, $DB->count_records('files'));
    }

    public function test_csv_export_neutralises_formulas(): void {
        $this->assertSame("'=1+1", orphan_file_detector::csv_safe_cell('=1+1'));
        $this->assertSame("'+cmd", orphan_file_detector::csv_safe_cell('+cmd'));
        $this->assertSame("'-2", orphan_file_detector::csv_safe_cell('-2'));
        $this->assertSame("'@SUM(A1)", orphan_file_detector::csv_safe_cell('@SUM(A1)'));
        $this->assertSame("'\tx", orphan_file_detector::csv_safe_cell("\tx"));
        $this->assertSame('normal.png', orphan_file_detector::csv_safe_cell('normal.png'));

        $orphan = $this->make_orphan('course', 'summary', '=HYPERLINK("x").png');
        $orphans = orphan_file_detector::get_orphans_by_ids([(int)$orphan->get_id()]);
        $csv = orphan_file_detector::export_to_csv($orphans);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
        $this->assertStringNotContainsString(',"=HYPERLINK', $csv);
    }
}
