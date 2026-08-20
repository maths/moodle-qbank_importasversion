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

namespace qbank_importasversion;

use context;
use context_course;
use context_system;
use core_tag_area;
use core_tag_tag;
use moodle_exception;
use moodle_url;
use qbank_importasversion\form\import_form;
use qformat_xml;
use question_bank;
use ReflectionMethod;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once(__DIR__ . '/../classes/form/import_form.php');

/**
 * Tests for the "Import and merge tags" behaviour of {@see importer::import_file()}.
 *
 * @package   qbank_importasversion
 * @category  test
 * @copyright 2026 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \qbank_importasversion\importer
 */
final class importer_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Create a course and a question of the given type inside it.
     *
     * @param string $qtype
     * @param context|null $context
     * @return array [\stdClass $course, \question_definition $question]
     */
    private function create_source_question(string $qtype = 'truefalse', ?context $context = null): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $context = $context ?? context_course::instance($course->id);
        $qgenerator = $generator->get_plugin_generator('core_question');
        $category = $qgenerator->create_question_category(['contextid' => $context->id]);
        $questiondata = $qgenerator->create_question($qtype, null, ['category' => $category->id]);
        return [$course, question_bank::load_question($questiondata->id)];
    }

    /**
     * Helper to attach a tag to a question.
     *
     * @param object $question
     * @param string $tagname
     * @param context|null $context Defaults to question context
     */
    private function add_tag(object $question, string $tagname, ?context $context = null): void {
        $context = $context ?? context::instance_by_id($question->contextid);
        core_tag_tag::add_item_tag('core_question', 'question', $question->id, $context, $tagname);
    }

    /**
     * Build a qformat_xml instance configured for the given course.
     *
     * @param \stdClass $course
     * @return qformat_xml
     */
    private function make_qformat(\stdClass $course): qformat_xml {
        $qformat = new qformat_xml();
        $qformat->setCourse($course);
        $qformat->displayprogress = false;
        return $qformat;
    }

    /**
     * Full path to a fixture file.
     *
     * @param string $name
     * @return string
     */
    private function fixture(string $name): string {
        return __DIR__ . '/fixtures/' . $name;
    }

    /**
     * The rawnames of the tags on a question, in whatever order core_tag_tag returns them.
     *
     * @param int $questionid
     * @return array
     */
    private function tagnames_of(int $questionid): array {
        return array_column(core_tag_tag::get_item_tags('core_question', 'question', $questionid), 'rawname');
    }

    public function test_merge_writes_union(): void {
        $this->setAdminUser();
        [$course, $question] = $this->create_source_question();
        $this->add_tag($question, 'source-tag');

        $qformat = $this->make_qformat($course);
        $result = importer::import_file($qformat, $question, $this->fixture('merge-source-tagged.xml'), true);

        $this->assertTrue($result === true || empty($result->error));
        $newid = end($qformat->questionids);
        $this->assertEqualsCanonicalizing(['file-tag', 'source-tag'], $this->tagnames_of($newid));
    }

    public function test_merge_tagnames(): void {
        $method = new ReflectionMethod(importer::class, 'merge_tagnames');

        $this->assertCount(1, $method->invoke(null, ['Algebra'], ['algebra']));
        $this->assertSame(['a'], $method->invoke(null, [], ['a']));
        $this->assertSame(['a'], $method->invoke(null, ['a'], []));
        $this->assertSame(['File', 'existing'], $method->invoke(null, ['File'], ['existing']));
    }

    public function test_course_level_bank_keeps_own_tags(): void {
        global $DB;
        $this->setAdminUser();

        // Build a genuine legacy course-context category directly (bypassing auto-migration).
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $coursecontext = context_course::instance($course->id);
        $categoryid = $DB->insert_record('question_categories', (object) [
            'name' => 'Course-level category',
            'contextid' => $coursecontext->id,
            'info' => '',
            'infoformat' => FORMAT_HTML,
            'stamp' => make_unique_id_code(),
            'parent' => 0,
            'sortorder' => 999,
        ]);

        $qgenerator = $generator->get_plugin_generator('core_question');
        $questiondata = $qgenerator->create_question('truefalse', null, ['category' => $categoryid]);
        $question = question_bank::load_question($questiondata->id);

        $this->assertEquals(CONTEXT_COURSE, context::instance_by_id($question->contextid)->contextlevel);
        $this->add_tag($question, 'own-tag');

        $qformat = $this->make_qformat($course);
        importer::import_file($qformat, $question, $this->fixture('edited-true-false-question.xml'), true);

        $newid = end($qformat->questionids);
        $this->assertSame(['own-tag'], $this->tagnames_of($newid));
    }

    public function test_three_arg_call_does_not_merge(): void {
        $this->setAdminUser();
        [$course, $question] = $this->create_source_question();
        $this->add_tag($question, 'source-tag');

        $qformat = $this->make_qformat($course);
        importer::import_file($qformat, $question, $this->fixture('edited-true-false-question.xml'));

        $newid = end($qformat->questionids);
        $this->assertSame([], $this->tagnames_of($newid));
    }

    public function test_course_tag_not_flattened(): void {
        $this->setAdminUser();
        [$course, $question] = $this->create_source_question('truefalse', context_system::instance());

        $this->add_tag($question, 'ordinary-tag');
        $this->add_tag($question, 'course-tag', context_course::instance($course->id));

        $qformat = $this->make_qformat($course);
        importer::import_file($qformat, $question, $this->fixture('edited-true-false-question.xml'), true);

        $newid = end($qformat->questionids);
        $this->assertSame(['ordinary-tag'], $this->tagnames_of($newid));

        // The source question's course-context instance is left intact.
        $tags = core_tag_tag::get_item_tags('core_question', 'question', $question->id);
        $coursetag = array_values(array_filter($tags, fn($t) => $t->rawname === 'course-tag'))[0] ?? null;

        $this->assertNotNull($coursetag);
        $this->assertEquals(context_course::instance($course->id)->id, $coursetag->taginstancecontextid);
    }

    public function test_legacy_context_instance_is_merged(): void {
        $this->setAdminUser();
        [$course, $question] = $this->create_source_question();
        $this->add_tag($question, 'legacy-tag', context_system::instance());

        $qformat = $this->make_qformat($course);
        importer::import_file($qformat, $question, $this->fixture('edited-true-false-question.xml'), true);

        $newid = end($qformat->questionids);
        $this->assertSame(['legacy-tag'], $this->tagnames_of($newid));
    }

    public function test_merge_button_hidden_when_tagging_disabled(): void {
        global $PAGE, $DB;
        $this->setAdminUser();
        $PAGE->set_url(new moodle_url('/question/bank/importasversion/import.php'));

        $area = $DB->get_record('tag_area', ['component' => 'core_question', 'itemtype' => 'question'], '*', MUST_EXIST);
        core_tag_area::update($area, ['enabled' => 0]);

        $form = new import_form(null, null, 'post', '', null, true);
        $html = $form->render();

        $this->assertStringNotContainsString('Import and merge tags', $html);
        $this->assertStringContainsString('Import', $html);
    }

    public function test_failed_import_leaves_no_tagged_version(): void {
        global $DB;
        $this->preventResetByRollback();
        $this->setAdminUser();

        [$course, $question] = $this->create_source_question('shortanswer');
        $this->add_tag($question, 'source-tag');

        $taginstancesbefore = $DB->count_records('tag_instance', ['itemtype' => 'question']);
        $versionsbefore = $DB->count_records('question_versions', ['questionbankentryid' => $question->questionbankentryid]);

        $qformat = $this->make_qformat($course);
        $result = importer::import_file($qformat, $question, $this->fixture('broken-shortanswer-nomax.xml'), true);

        $this->assertNotEmpty($result->error);
        $this->assertSame(
            $versionsbefore,
            $DB->count_records('question_versions', ['questionbankentryid' => $question->questionbankentryid])
        );
        $this->assertSame($taginstancesbefore, $DB->count_records('tag_instance', ['itemtype' => 'question']));
    }

    /**
     * The replaced version's own tag rows are never mutated by the merge.
     */
    public function test_source_version_tags_unchanged(): void {
        global $DB;
        $this->setAdminUser();

        [$course, $question] = $this->create_source_question();
        $this->add_tag($question, 'source-tag');

        $before = $DB->get_records('tag_instance', ['itemid' => $question->id, 'itemtype' => 'question'], 'id', 'id, contextid');

        $qformat = $this->make_qformat($course);
        importer::import_file($qformat, $question, $this->fixture('edited-true-false-question.xml'), true);

        $after = $DB->get_records('tag_instance', ['itemid' => $question->id, 'itemtype' => 'question'], 'id', 'id, contextid');
        $this->assertEquals($before, $after);
    }

    /**
     * A user without edit capability on the target question cannot reach the merge.
     */
    public function test_merge_requires_edit_capability_on_target(): void {
        global $DB;
        $this->setAdminUser();
        [$course, $question] = $this->create_source_question();
        $this->add_tag($question, 'source-tag');

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $before = $DB->count_records('tag_instance', ['itemtype' => 'question']);

        try {
            question_require_capability_on($question, 'edit');
            $this->fail('Expected a moodle_exception (nopermissions).');
        } catch (moodle_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
            $this->assertSame($before, $DB->count_records('tag_instance', ['itemtype' => 'question']));
        }
    }
}
