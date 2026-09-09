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
    /** Invalid parsed content must not produce a new version or import event. */
    public function test_rejects_question_type_validation_errors_before_creating_version(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();
        $data = $generator->create_question('truefalse', null, ['category' => $category->id]);
        $question = \question_bank::load_question($data->id);
        $format = new class extends \qformat_xml {
            /**
             * Simulate a question type reporting a hard authoring error while retaining parsed data.
             *
             * @param array $lines XML lines.
             * @return array
             */
            public function readquestions($lines) {
                $questions = parent::readquestions($lines);
                $questions[0]->validationerrors = 'questiontext: Missing required validation placeholder.';
                return $questions;
            }
        };
        $format->displayprogress = false;
        $count = $DB->count_records('question');
        $versions = $DB->count_records('question_versions');
        $sink = $this->redirectEvents();
        $result = importer::import_file($format, $question, __DIR__ . '/fixtures/edited-true-false-question.xml');
        $this->assertNotEmpty($result->error ?? null);
        $this->assertStringContainsString('Missing required validation placeholder', $result->error);
        $this->assertEquals($count, $DB->count_records('question'));
        $this->assertEquals($versions, $DB->count_records('question_versions'));
        $this->assertEmpty($sink->get_events());
        $this->assertEmpty($format->questionids);
    }

    /**
     * Exercise the real STACK parser when the optional question type is installed.
     *
     * @dataProvider stack_input_types
     * @param string $type STACK input type.
     */
    public function test_stack_missing_validation_does_not_replace_ready_version(string $type): void {
        global $DB, $CFG, $PAGE;
        if (!is_dir($CFG->dirroot . '/question/type/stack')) {
            $this->markTestSkipped('Optional integration test requires STACK.');
        }
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();
        $data = $generator->create_question('stack', 'test1', ['category' => $category->id]);
        $question = \question_bank::load_question($data->id);
        $PAGE->set_pagetype('question-bank-importasversion-import');
        $xml = '<quiz><question type="stack"><name><text>Invalid dropdown</text></name>
            <questiontext format="html"><text>[[input:ans1]]</text></questiontext>
            <questionvariables><text>ta1:[[1,true],[2,false]];</text></questionvariables>
            <specificfeedback format="html"><text></text></specificfeedback>
            <questionnote><text>Dropdown with no validation marker</text></questionnote>
            <input><name>ans1</name><type>' . $type . '</type><tans>ta1</tans>
            <mustverify>0</mustverify><showvalidation>0</showvalidation></input>
            </question></quiz>';
        $file = make_request_directory() . '/invalid-dropdown.xml';
        file_put_contents($file, $xml);
        $format = new \qformat_xml();
        $format->displayprogress = false;
        $before = $DB->get_records('question_versions', ['questionbankentryid' => $question->questionbankentryid]);
        $questions = $DB->count_records('question');
        $result = importer::import_file($format, $question, $file);
        $after = $DB->get_records('question_versions', ['questionbankentryid' => $question->questionbankentryid]);
        $this->assertEquals($before, $after, 'Invalid import must not create a new Ready version.');
        $this->assertEquals($questions, $DB->count_records('question'));
        $this->assertNotEmpty($result->error ?? null);
        $this->assertStringContainsString('[[validation:ans1]]', $result->error);
    }

    /**
     * Selection inputs and expression inputs must all preserve the existing version on failure.
     *
     * @return array
     */
    public static function stack_input_types(): array {
        return [
            'algebraic' => ['algebraic'],
            'boolean' => ['boolean'],
            'checkbox' => ['checkbox'],
            'dropdown' => ['dropdown'],
            'equiv' => ['equiv'],
            'freetext' => ['freetext'],
            'geogebra' => ['geogebra'],
            'json' => ['json'],
            'matrix' => ['matrix'],
            'notes' => ['notes'],
            'numerical' => ['numerical'],
            'parsons' => ['parsons'],
            'radio' => ['radio'],
            'singlechar' => ['singlechar'],
            'string' => ['string'],
            'textarea' => ['textarea'],
            'units' => ['units'],
            'varmatrix' => ['varmatrix'],
        ];
    }

    /** Valid imports retain their existing version-creation behavior. */
    public function test_valid_question_still_creates_new_version(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();
        $data = $generator->create_question('truefalse', null, ['category' => $category->id]);
        $question = \question_bank::load_question($data->id);
        $format = new \qformat_xml();
        $format->displayprogress = false;
        $count = $DB->count_records('question_versions');
        $result = importer::import_file($format, $question, __DIR__ . '/fixtures/edited-true-false-question.xml');
        $this->assertEmpty($result->error ?? null);
        $this->assertEquals($count + 1, $DB->count_records('question_versions'));
    }
}
