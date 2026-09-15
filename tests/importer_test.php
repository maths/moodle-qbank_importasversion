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

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/question/format/xml/format.php');

/**
 * Import validation and persistence tests.
 *
 * @package qbank_importasversion
 * @copyright 2026 Oleksandr Kulkov
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \qbank_importasversion\importer
 */
final class importer_test extends \advanced_testcase {
    /** @var \question_definition Original Ready question. */
    private $question;

    /** @var \qformat_xml Import format. */
    private $format;

    /** @var array Original question-version records. */
    private $versions;

    /** @var int Original number of questions. */
    private $questioncount;

    /** @var \phpunit_event_sink Captures events emitted by the import. */
    private $events;

    /** @var array|null Question-type registry before installing the mock. */
    private $originaltypes;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        // Rejection rolls back the import transaction, so the fixture cannot use an outer rollback.
        $this->preventResetByRollback();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();
        $data = $generator->create_question('truefalse', null, ['category' => $category->id]);
        $this->question = \question_bank::load_question($data->id);
        $this->format = new \qformat_xml();
        $this->format->displayprogress = false;
        $this->versions = $DB->get_records('question_versions');
        $this->questioncount = $DB->count_records('question');
        $this->events = $this->redirectEvents();
    }

    protected function tearDown(): void {
        if ($this->originaltypes !== null) {
            $property = new \ReflectionProperty(\question_bank::class, 'questiontypes');
            $property->setAccessible(true);
            $property->setValue(null, $this->originaltypes);
        }
        parent::tearDown();
    }

    /**
     * Check each save outcome against a literal expected status, not a second copy of the import policy.
     *
     * @dataProvider save_results
     * @param mixed $outcome Question-type save result.
     * @param bool $force Whether notices are allowed.
     * @param bool $draftonnotice Whether retained notices create a Draft.
     * @param string|null $expectedstatus Ready, Draft, or null when no version should be saved.
     */
    public function test_save_result_policy($outcome, bool $force, bool $draftonnotice, ?string $expectedstatus): void {
        $this->mock_save_result($outcome);
        $result = importer::import_file(
            $this->format,
            $this->question,
            __DIR__ . '/fixtures/edited-true-false-question.xml',
            false,
            $force,
            $draftonnotice
        );
        $this->assert_import_result($result, $outcome, $expectedstatus);
    }

    /**
     * Expected outcomes for all explicit flag combinations.
     *
     * A null save result is success: some question types save without returning a value.
     * The importer normalizes it to true. It is not a notice and never makes a version Draft.
     * Here, null in the LAST column means rejection, not a null save result.
     *
     * @return array
     */
    public static function save_results(): array {
        $notice = (object) ['notice' => 'Question-type warning'];
        $error = (object) ['error' => 'Question-type failure'];
        $both = (object) ['error' => 'Question-type failure', 'notice' => 'Question-type warning'];
        return [
            // Save result, force, draftonnotice, expected saved status.
            'strict: true' => [true, false, false, 'ready'],
            'strict: null' => [null, false, false, 'ready'],
            'strict: notice' => [$notice, false, false, null],
            'strict: error' => [$error, false, false, null],
            'strict: false' => [false, false, false, null],
            'strict: error and notice' => [$both, false, false, null],

            // The unchecked upload form: Draft handling does not allow warnings by itself.
            'form unchecked: true' => [true, false, true, 'ready'],
            'form unchecked: null' => [null, false, true, 'ready'],
            'form unchecked: notice' => [$notice, false, true, null],
            'form unchecked: error' => [$error, false, true, null],
            'form unchecked: false' => [false, false, true, null],
            'form unchecked: error and notice' => [$both, false, true, null],

            // Explicit API compatibility mode permits notices without changing status.
            'allow notices: true' => [true, true, false, 'ready'],
            'allow notices: null' => [null, true, false, 'ready'],
            'allow notices: notice' => [$notice, true, false, 'ready'],
            'allow notices: error' => [$error, true, false, null],
            'allow notices: false' => [false, true, false, null],
            'allow notices: error and notice' => [$both, true, false, null],

            // The checked upload form: only a notice makes the imported version Draft.
            'form checked: true' => [true, true, true, 'ready'],
            'form checked: null' => [null, true, true, 'ready'],
            'form checked: notice' => [$notice, true, true, 'draft'],
            'form checked: error' => [$error, true, true, null],
            'form checked: false' => [false, true, true, null],
            'form checked: error and notice' => [$both, true, true, null],
        ];
    }

    /**
     * Existing callers omit both new arguments and must retain their previous behavior.
     *
     * @dataProvider legacy_save_results
     * @param mixed $outcome Question-type save result.
     * @param string|null $expectedstatus Ready, or null when no version should be saved.
     */
    public function test_legacy_call($outcome, ?string $expectedstatus): void {
        $this->mock_save_result($outcome);
        $result = importer::import_file(
            $this->format,
            $this->question,
            __DIR__ . '/fixtures/edited-true-false-question.xml'
        );
        $this->assert_import_result($result, $outcome, $expectedstatus);
    }

    /**
     * Literal expectations for the three-argument API.
     *
     * @return array
     */
    public static function legacy_save_results(): array {
        return [
            'true' => [true, 'ready'],
            'null is also success' => [null, 'ready'],
            'notice remains Ready' => [(object) ['notice' => 'Question-type warning'], 'ready'],
            'error' => [(object) ['error' => 'Question-type failure'], null],
            'false' => [false, null],
            'error wins over notice' => [(object) ['error' => 'Failure', 'notice' => 'Warning'], null],
        ];
    }

    /**
     * A real true/false save remains Ready with either form choice when there are no warnings.
     *
     * @dataProvider warning_choices
     * @param bool $force Whether the repair checkbox is checked.
     */
    public function test_valid_question_imports(bool $force): void {
        $result = importer::import_file(
            $this->format,
            $this->question,
            __DIR__ . '/fixtures/edited-true-false-question.xml',
            false,
            $force,
            true
        );
        $this->assert_import_result($result, true, 'ready');
        $loaded = \question_bank::load_question($this->format->questionids[0]);
        $this->assertInstanceOf(\qtype_truefalse_question::class, $loaded);
    }

    /**
     * Both choices on the upload form.
     *
     * @return array
     */
    public static function warning_choices(): array {
        return [[false], [true]];
    }

    /**
     * Replace only the save result; parsing still uses Moodle's true/false question type.
     *
     * @param mixed $outcome Result to return from save_question_options().
     */
    private function mock_save_result($outcome): void {
        $property = new \ReflectionProperty(\question_bank::class, 'questiontypes');
        $property->setAccessible(true);
        $this->originaltypes = $property->getValue();
        $types = $this->originaltypes;
        $types['truefalse'] = $this->getMockBuilder(\qtype_truefalse::class)
            ->onlyMethods(['save_question_options'])->getMock();
        // The importer may add an error or a Draft explanation; keep the provider's fixture unchanged.
        $types['truefalse']->expects($this->once())->method('save_question_options')
            ->willReturn(is_object($outcome) ? clone $outcome : $outcome);
        $property->setValue(null, $types);
    }

    /**
     * Check persistence and diagnostics for the expected outcome given in the table.
     *
     * @param mixed $result Importer's return value.
     * @param mixed $outcome Original question-type save result.
     * @param string|null $expectedstatus Ready, Draft, or null for rejection.
     */
    private function assert_import_result($result, $outcome, ?string $expectedstatus): void {
        global $DB;
        $after = $DB->get_records('question_versions');
        if ($expectedstatus === null) {
            $this->assertNotEmpty($result->error ?? null);
            $this->assertEquals($this->versions, $after);
            $this->assertEquals($this->questioncount, $DB->count_records('question'));
            $this->assertEmpty($this->events->get_events());
            $this->assertEmpty($this->format->questionids);
            if ($outcome === false) {
                $this->assertEquals(get_string('unknownerror', 'qbank_importasversion'), $result->error);
            } else {
                $this->assertEquals($outcome->error ?? $outcome->notice, $result->error);
            }
            return;
        }

        $this->assertEmpty($result->error ?? null);
        $new = array_values(array_diff_key($after, $this->versions));
        $this->assertCount(1, $new);
        $this->assertEquals($this->questioncount + 1, $DB->count_records('question'));
        $this->assertEquals($expectedstatus, $new[0]->status);
        $this->assertEquals([$new[0]->questionid], $this->format->questionids);
        $events = array_filter($this->events->get_events(), static function ($event) {
            return $event instanceof \qbank_importasversion\event\question_version_imported;
        });
        $this->assertCount(1, $events);
        foreach ($this->versions as $id => $version) {
            $this->assertEquals($version, $after[$id]);
        }
        $available = \question_bank::get_finder()->get_questions_from_categories([$this->question->category], '');
        if ($expectedstatus === 'draft') {
            $this->assertEquals([$this->question->id], array_values($available));
            $this->assertEquals(
                get_string('importedwithwarningsasdraft', 'qbank_importasversion') . '<br>' . $outcome->notice,
                $result->notice
            );
        } else {
            // Moodle 4.0 also returns older Ready versions; the new version must be available in either case.
            $this->assertContains($new[0]->questionid, $available);
            if ($outcome === true || $outcome === null) {
                $this->assertSame(true, $result);
            } else {
                $this->assertEquals($outcome->notice, $result->notice);
            }
        }
    }
}
