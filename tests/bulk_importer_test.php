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
 * Tests for importing many questions as new versions at once.
 *
 * @package qbank_importasversion
 * @copyright 2026 LT&C
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \qbank_importasversion\bulk_importer
 */
final class bulk_importer_test extends \advanced_testcase {
    /** @var \stdClass Question category the existing questions are in. */
    private $category;

    /** @var \core_question_generator Question generator. */
    private $generator;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        // A failed save rolls back the import transaction, so the fixture cannot use an outer rollback.
        $this->preventResetByRollback();
        $this->setAdminUser();
        $this->generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $this->category = $this->generator->create_question_category(['name' => 'Cat A']);
    }

    /**
     * Create an existing true/false question with an idnumber in the category.
     *
     * @param string $idnumber the idnumber.
     * @return \stdClass the question data, including questionbankentryid.
     */
    private function create_existing(string $idnumber): \stdClass {
        return $this->generator->create_question(
            'truefalse',
            null,
            ['category' => $this->category->id, 'idnumber' => $idnumber, 'name' => 'Existing ' . $idnumber]
        );
    }

    /**
     * Set up an importer the way the core import page does.
     *
     * @param array $options catfromfile (default false): take the category from the file;
     *      stoponerror (default true): stop when the file has errors.
     * @return bulk_importer
     */
    private function create_importer(array $options = []): bulk_importer {
        $importer = new bulk_importer();
        $importer->setCategory($this->category);
        $importer->setContexts([\context::instance_by_id($this->category->contextid)]);
        $importer->setCatfromfile($options['catfromfile'] ?? false);
        $importer->setContextfromfile(false);
        $importer->setMatchgrades('error');
        $importer->setStoponerror($options['stoponerror'] ?? true);
        return $importer;
    }

    /**
     * XML for a true/false question.
     *
     * @param string $idnumber the idnumber, may be empty.
     * @param string $text the question text.
     * @return string
     */
    private static function truefalse_xml(string $idnumber, string $text): string {
        return '<question type="truefalse"><name><text>File ' . $idnumber . '</text></name>'
            . '<questiontext format="html"><text><![CDATA[<p>' . $text . '</p>]]></text></questiontext>'
            . '<generalfeedback format="html"><text></text></generalfeedback>'
            . '<defaultgrade>1</defaultgrade><penalty>1</penalty><hidden>0</hidden>'
            . '<idnumber>' . $idnumber . '</idnumber>'
            . '<answer fraction="100" format="moodle_auto_format"><text>true</text>'
            . '<feedback format="html"><text></text></feedback></answer>'
            . '<answer fraction="0" format="moodle_auto_format"><text>false</text>'
            . '<feedback format="html"><text></text></feedback></answer></question>';
    }

    /**
     * XML for a short answer question.
     *
     * @param string $idnumber the idnumber.
     * @param string $fraction the grade (a percentage) of the one answer.
     * @return string
     */
    private static function shortanswer_xml(string $idnumber, string $fraction = '100'): string {
        return '<question type="shortanswer"><name><text>Short ' . $idnumber . '</text></name>'
            . '<questiontext format="html"><text>Short text</text></questiontext>'
            . '<generalfeedback format="html"><text></text></generalfeedback>'
            . '<defaultgrade>1</defaultgrade><penalty>0.3333333</penalty><hidden>0</hidden>'
            . '<idnumber>' . $idnumber . '</idnumber><usecase>0</usecase>'
            . '<answer fraction="' . $fraction . '" format="moodle_auto_format"><text>a</text>'
            . '<feedback format="html"><text></text></feedback></answer></question>';
    }

    /**
     * XML for a category entry.
     *
     * @param string $path the category path.
     * @return string
     */
    private static function category_xml(string $path): string {
        return '<question type="category"><category><text>' . $path . '</text></category></question>';
    }

    /**
     * Write questions to a Moodle XML file.
     *
     * @param string[] $questions the XML of each question or category entry.
     * @return string the file path.
     */
    private function write_file(array $questions): string {
        $path = make_request_directory() . '/bulk.xml';
        file_put_contents($path, '<?xml version="1.0" encoding="UTF-8"?><quiz>' . implode('', $questions) . '</quiz>');
        return $path;
    }

    /**
     * Get the highest version of a question bank entry.
     *
     * @param int $questionbankentryid the entry.
     * @return int the version.
     */
    private function latest_version(int $questionbankentryid): int {
        global $DB;
        return (int) $DB->get_field_sql(
            'SELECT MAX(version) FROM {question_versions} WHERE questionbankentryid = ?',
            [$questionbankentryid]
        );
    }

    /**
     * Dry run saves nothing.
     */
    public function test_dry_run_saves_nothing(): void {
        global $DB;
        $a = $this->create_existing('ID-A');
        $b = $this->create_existing('ID-B');
        $file = $this->write_file([self::truefalse_xml('ID-A', 'New A'), self::truefalse_xml('ID-B', 'New B')]);
        $versions = $DB->count_records('question_versions');
        $questions = $DB->count_records('question');

        $result = $this->create_importer()->run($file, false);

        $this->assertFalse($result->blocked);
        $this->assertSame(
            [bulk_importer::OUTCOME_NEWVERSION, bulk_importer::OUTCOME_NEWVERSION],
            array_column($result->rows, 'outcome')
        );
        $this->assertSame($versions, $DB->count_records('question_versions'));
        $this->assertSame($questions, $DB->count_records('question'));
        $this->assertSame(1, $this->latest_version($a->questionbankentryid));
        $this->assertSame(1, $this->latest_version($b->questionbankentryid));
    }

    /**
     * Import creates new versions of existing questions.
     */
    public function test_import_creates_new_versions_of_existing_questions(): void {
        global $DB;
        $a = $this->create_existing('ID-A');
        $b = $this->create_existing('ID-B');
        $file = $this->write_file([self::truefalse_xml('ID-A', 'New A'), self::truefalse_xml('ID-B', 'New B')]);
        $entries = $DB->count_records('question_bank_entries');

        $result = $this->create_importer()->run($file, true);

        $this->assertFalse($result->blocked);
        $this->assertSame(
            [bulk_importer::OUTCOME_IMPORTED, bulk_importer::OUTCOME_IMPORTED],
            array_column($result->rows, 'outcome')
        );
        // A new version of the same entries, not new entries.
        $this->assertSame($entries, $DB->count_records('question_bank_entries'));
        $this->assertSame(2, $this->latest_version($a->questionbankentryid));
        $this->assertSame(2, $this->latest_version($b->questionbankentryid));
        $this->assertSame('<p>New A</p>', $DB->get_field('question', 'questiontext', ['id' => $result->rows[0]->questionid]));
        $this->assertSame('<p>New B</p>', $DB->get_field('question', 'questiontext', ['id' => $result->rows[1]->questionid]));
        // The idnumber of the entry is left alone.
        $this->assertSame('ID-A', $DB->get_field('question_bank_entries', 'idnumber', ['id' => $a->questionbankentryid]));
    }

    /**
     * Questions that cannot be matched are skipped.
     */
    public function test_questions_that_cannot_be_matched_are_skipped(): void {
        global $DB;
        $a = $this->create_existing('ID-A');
        $file = $this->write_file([
            self::truefalse_xml('', 'No idnumber'),
            self::truefalse_xml('NOPE', 'Unknown idnumber'),
            self::shortanswer_xml('ID-A'),
        ]);
        $versions = $DB->count_records('question_versions');

        $result = $this->create_importer()->run($file, true);

        $this->assertSame(array_fill(0, 3, bulk_importer::OUTCOME_SKIPPED), array_column($result->rows, 'outcome'));
        $this->assertSame(
            [bulk_importer::REASON_NOIDNUMBER, bulk_importer::REASON_NOTFOUND, bulk_importer::REASON_TYPEMISMATCH],
            array_column($result->rows, 'reason')
        );
        $this->assertSame($versions, $DB->count_records('question_versions'));
        $this->assertSame(1, $this->latest_version($a->questionbankentryid));
    }

    /**
     * An idnumber twice in the file skips both.
     */
    public function test_an_idnumber_twice_in_the_file_skips_both(): void {
        $a = $this->create_existing('ID-A');
        $b = $this->create_existing('ID-B');
        $file = $this->write_file([
            self::truefalse_xml('ID-A', 'First'),
            self::truefalse_xml('ID-A', 'Second'),
            self::truefalse_xml('ID-B', 'Only one'),
        ]);

        $result = $this->create_importer()->run($file, true);

        $this->assertSame(
            [bulk_importer::OUTCOME_SKIPPED, bulk_importer::OUTCOME_SKIPPED, bulk_importer::OUTCOME_IMPORTED],
            array_column($result->rows, 'outcome')
        );
        $this->assertSame(bulk_importer::REASON_DUPLICATEINFILE, $result->rows[0]->reason);
        $this->assertSame(1, $this->latest_version($a->questionbankentryid));
        $this->assertSame(2, $this->latest_version($b->questionbankentryid));
    }

    /**
     * Category from file is looked up and never created.
     */
    public function test_category_from_file_is_looked_up_and_never_created(): void {
        global $DB;
        $a = $this->create_existing('ID-A');
        $categories = $DB->count_records('question_categories');
        $file = $this->write_file([
            self::category_xml('Missing'),
            self::truefalse_xml('ID-A', 'In a category that does not exist'),
            self::category_xml('Cat A'),
            self::truefalse_xml('ID-A', 'In the right category'),
        ]);

        $result = $this->create_importer(['catfromfile' => true])->run($file, true);

        $this->assertSame(
            [bulk_importer::OUTCOME_SKIPPED, bulk_importer::OUTCOME_IMPORTED],
            array_column($result->rows, 'outcome')
        );
        $this->assertSame(bulk_importer::REASON_CATEGORYNOTFOUND, $result->rows[0]->reason);
        // A category that is not found is shown as in the file, a found one as it is in Moodle.
        $this->assertSame('Missing', $result->rows[0]->category);
        $this->assertSame('Cat A', $result->rows[1]->category);
        $this->assertSame($categories, $DB->count_records('question_categories'));
        $this->assertSame(2, $this->latest_version($a->questionbankentryid));
    }

    /**
     * Without category from file the selected category is used.
     */
    public function test_without_category_from_file_the_selected_category_is_used(): void {
        $a = $this->create_existing('ID-A');
        // The category in the file does not exist, but it is ignored.
        $file = $this->write_file([self::category_xml('Missing'), self::truefalse_xml('ID-A', 'New')]);

        $result = $this->create_importer()->run($file, true);

        $this->assertSame([bulk_importer::OUTCOME_IMPORTED], array_column($result->rows, 'outcome'));
        $this->assertSame('Cat A', $result->rows[0]->category);
        $this->assertSame(2, $this->latest_version($a->questionbankentryid));
    }

    /**
     * A user who cannot edit the question is skipped.
     */
    public function test_a_user_who_cannot_edit_the_question_is_skipped(): void {
        $a = $this->create_existing('ID-A');
        $this->setUser($this->getDataGenerator()->create_user());
        $file = $this->write_file([self::truefalse_xml('ID-A', 'New')]);

        $result = $this->create_importer()->run($file, true);

        $this->assertSame(bulk_importer::REASON_NOPERMISSION, $result->rows[0]->reason);
        $this->assertSame(1, $this->latest_version($a->questionbankentryid));
    }

    /**
     * With stop on error an invalid grade blocks the whole import, without it only that question is skipped.
     */
    public function test_stop_on_error_blocks_the_whole_import(): void {
        $a = $this->create_existing('ID-A');
        // 42% is not a valid answer grade.
        $file = $this->write_file([self::truefalse_xml('ID-A', 'New'), self::shortanswer_xml('ID-S', '42')]);

        $result = $this->create_importer()->run($file, true);

        $this->assertTrue($result->blocked);
        // A question that could be imported says that it is not, and why.
        $this->assertSame(
            [bulk_importer::REASON_BLOCKED, bulk_importer::REASON_INVALIDGRADES],
            array_column($result->rows, 'reason')
        );
        $this->assertSame(1, $this->latest_version($a->questionbankentryid));

        // Without stop on error the same file imports the valid question and skips the invalid one.
        $result = $this->create_importer(['stoponerror' => false])->run($file, true);

        $this->assertFalse($result->blocked);
        $this->assertSame(
            [bulk_importer::OUTCOME_IMPORTED, bulk_importer::OUTCOME_SKIPPED],
            array_column($result->rows, 'outcome')
        );
        $this->assertSame(bulk_importer::REASON_INVALIDGRADES, $result->rows[1]->reason);
        $this->assertSame(2, $this->latest_version($a->questionbankentryid));
    }

    /**
     * File without readable questions is blocked.
     */
    public function test_file_without_readable_questions_is_blocked(): void {
        $empty = $this->write_file([]);
        $notxml = make_request_directory() . '/notxml.xml';
        file_put_contents($notxml, 'This is not XML.');

        foreach ([$empty, $notxml] as $file) {
            $result = $this->create_importer()->run($file, true);

            $this->assertTrue($result->blocked);
            $this->assertNotEmpty($result->errors);
            $this->assertSame([], $result->rows);
        }
    }

    /**
     * Count outcomes.
     */
    public function test_count_outcomes(): void {
        $rows = [
            (object) ['outcome' => bulk_importer::OUTCOME_IMPORTED],
            (object) ['outcome' => bulk_importer::OUTCOME_IMPORTED],
            (object) ['outcome' => bulk_importer::OUTCOME_SKIPPED],
        ];

        $counts = bulk_importer::count_outcomes($rows);

        $this->assertSame(2, $counts[bulk_importer::OUTCOME_IMPORTED]);
        $this->assertSame(1, $counts[bulk_importer::OUTCOME_SKIPPED]);
        $this->assertSame(0, $counts[bulk_importer::OUTCOME_ERROR]);
    }
}
