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

/**
 * Tests for importing many questions as new versions.
 *
 * @package   qbank_importasversion
 * @copyright 2026 ETH Zurich
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \qbank_importasversion\bulk_import
 */
final class bulk_import_test extends \advanced_testcase {
    /** @var \context the question bank context. */
    private $context;

    /** @var \stdClass[] two categories in that context. */
    private $categories;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        // A failed save rolls back the import transaction, so the test cannot use an outer rollback.
        $this->preventResetByRollback();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $first = $generator->create_question_category();
        $this->context = \context::instance_by_id($first->contextid);
        $second = $generator->create_question_category(['contextid' => $first->contextid]);
        $this->categories = [$first, $second];
    }

    /**
     * Create a true/false question with an ID number.
     *
     * @param int $category index into $this->categories.
     * @param string $idnumber
     * @param string $qtype
     * @return \stdClass
     */
    private function create(int $category, string $idnumber, string $qtype = 'truefalse'): \stdClass {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        return $generator->create_question(
            $qtype,
            null,
            ['category' => $this->categories[$category]->id, 'idnumber' => $idnumber, 'name' => $idnumber]
        );
    }

    /**
     * Write a Moodle XML file with true/false questions.
     *
     * @param array $questions idnumber => question text.
     * @return string the file path.
     */
    private function file(array $questions): string {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><quiz>';
        foreach ($questions as $idnumber => $text) {
            $xml .= '<question type="truefalse"><name><text>' . $idnumber . '</text></name>'
                . '<questiontext format="html"><text>' . $text . '</text></questiontext>'
                . '<idnumber>' . $idnumber . '</idnumber>'
                . '<answer fraction="100" format="moodle_auto_format"><text>true</text><feedback><text></text></feedback></answer>'
                . '<answer fraction="0" format="moodle_auto_format"><text>false</text><feedback><text></text></feedback></answer>'
                . '</question>';
        }
        $file = make_request_directory() . '/import.xml';
        file_put_contents($file, $xml . '</quiz>');
        return $file;
    }

    /**
     * Get the latest version of the question bank entry of a question.
     *
     * @param \stdClass $question
     * @return \stdClass with version, questiontext and status.
     */
    private function latest(\stdClass $question): \stdClass {
        global $DB;
        return $DB->get_record_sql("SELECT qv.version, q.questiontext, qv.status
                                      FROM {question_versions} qv
                                      JOIN {question} q ON q.id = qv.questionid
                                     WHERE qv.questionbankentryid = ?
                                  ORDER BY qv.version DESC", [$question->questionbankentryid], IGNORE_MULTIPLE);
    }

    public function test_import_updates_matches_in_any_category_and_skips_the_rest(): void {
        $one = $this->create(0, 'one');
        $two = $this->create(1, 'two');
        $this->create(0, 'shared');
        $this->create(1, 'shared');
        $this->create(0, 'other', 'shortanswer');
        $file = $this->file(['one' => 'New one', 'two' => 'New two', 'missing' => 'x', 'shared' => 'x', 'other' => 'x',
            '' => 'No ID number']);

        $plan = bulk_import::plan($this->context, $file);
        $this->assertSame([], $plan->errors);
        $this->assertSame(['one' => bulk_import::READY, 'two' => bulk_import::READY,
            'missing' => bulk_import::NOTFOUND, 'shared' => bulk_import::AMBIGUOUS, 'other' => bulk_import::TYPEMISMATCH,
            '' => bulk_import::NOIDNUMBER], array_column($plan->rows, 'status', 'idnumber'));
        $this->assertEquals(1, $this->latest($one)->version, 'Planning must not save anything.');

        $result = bulk_import::import($this->context, $file, false, false);
        $this->assertSame(2, $result->imported);
        $this->assertEquals(2, $this->latest($one)->version);
        $this->assertSame('New one', $this->latest($one)->questiontext);
        $this->assertSame('New two', $this->latest($two)->questiontext);
        $this->assertSame(2, count(array_filter(array_column($result->rows, 'questionid'))));
    }

    public function test_duplicate_idnumbers_in_the_file_are_skipped(): void {
        $one = $this->create(0, 'one');
        $file = make_request_directory() . '/import.xml';
        $single = file_get_contents($this->file(['one' => 'A']));
        file_put_contents($file, str_replace('</quiz>', '', $single) . substr($single, strpos($single, '<question')));

        $result = bulk_import::import($this->context, $file, false, false);
        $this->assertSame([bulk_import::DUPLICATE, bulk_import::DUPLICATE], array_column($result->rows, 'status'));
        $this->assertSame(0, $result->imported);
        $this->assertEquals(1, $this->latest($one)->version);
    }

    public function test_a_failed_save_imports_nothing(): void {
        global $DB;
        $one = $this->create(0, 'one');
        $two = $this->create(0, 'two');
        $versions = $DB->count_records('question_versions');

        // The second save fails.
        $property = new \ReflectionProperty(\question_bank::class, 'questiontypes');
        $property->setAccessible(true);
        $original = $property->getValue();
        $types = $original;
        $types['truefalse'] = $this->getMockBuilder(\qtype_truefalse::class)
            ->onlyMethods(['save_question_options'])->getMock();
        $types['truefalse']->method('save_question_options')
            ->willReturnOnConsecutiveCalls(true, (object) ['error' => 'Broken']);
        $property->setValue(null, $types);
        try {
            $result = bulk_import::import($this->context, $this->file(['one' => 'A', 'two' => 'B']), false, false);
        } finally {
            $property->setValue(null, $original);
        }

        $this->assertSame(0, $result->imported);
        $this->assertSame('two', $result->failed->idnumber);
        $this->assertSame('Broken', $result->error);
        $this->assertEquals($versions, $DB->count_records('question_versions'));
        $this->assertEquals(1, $this->latest($one)->version);
    }

    public function test_a_warning_is_saved_as_draft_only_when_allowed(): void {
        $one = $this->create(0, 'one');
        $property = new \ReflectionProperty(\question_bank::class, 'questiontypes');
        $property->setAccessible(true);
        $original = $property->getValue();
        $types = $original;
        $types['truefalse'] = $this->getMockBuilder(\qtype_truefalse::class)
            ->onlyMethods(['save_question_options'])->getMock();
        $types['truefalse']->method('save_question_options')->willReturnCallback(function () {
            return (object) ['notice' => 'Check this'];
        });
        $property->setValue(null, $types);
        try {
            $strict = bulk_import::import($this->context, $this->file(['one' => 'A']), false, false);
            $draft = bulk_import::import($this->context, $this->file(['one' => 'A']), false, true);
        } finally {
            $property->setValue(null, $original);
        }

        $this->assertSame('Check this', $strict->error);
        $this->assertSame(1, $draft->imported);
        $this->assertSame('draft', $this->latest($one)->status);
    }

    public function test_a_user_who_can_only_edit_their_own_questions_cannot_update_others(): void {
        $one = $this->create(0, 'one');
        $user = $this->getDataGenerator()->create_user();
        $roleid = create_role('Own questions', 'ownquestions', '');
        assign_capability('moodle/question:editmine', CAP_ALLOW, $roleid, $this->context->id);
        role_assign($roleid, $user->id, $this->context->id);
        $this->setUser($user);

        $plan = bulk_import::plan($this->context, $this->file(['one' => 'A']));
        $this->assertSame(bulk_import::NOPERMISSION, $plan->rows[0]->status);
    }

    public function test_every_skip_status_has_a_string(): void {
        $statuses = (new \ReflectionClass(bulk_import::class))->getConstants();
        unset($statuses['READY'], $statuses['CHUNK']);
        foreach ($statuses as $status) {
            $this->assertTrue(get_string_manager()->string_exists('status_' . $status, 'qbank_importasversion'), $status);
        }
    }

    public function test_an_invalid_file_is_reported(): void {
        $file = make_request_directory() . '/import.xml';
        file_put_contents($file, 'This is not XML.');
        $plan = bulk_import::plan($this->context, $file);
        $this->assertNotEmpty($plan->errors);
        $this->assertSame([], $plan->rows);
    }
}
