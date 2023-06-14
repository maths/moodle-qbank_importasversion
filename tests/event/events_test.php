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

namespace qbank_importasversion\event;

use context_course;

/**
 * Events tests.
 *
 * @package   qbank_importasversion
 * @category  test
 * @copyright 2023 MootDACH DevCamp
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \qbank_importasversion\event\question_version_imported
 */
class events_test extends \advanced_testcase {

    public function test_imported_is_logged() {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $context = context_course::instance($course->id);

        $event = question_version_imported::create([
            'context' => $context,
            'objectid' => 123,
            'other' => [
                'categoryid' => 7,
                'questionbankentryid' => 45,
                'version' => 3,
            ],
        ]);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf(question_version_imported::class, $event);
        $this->assertEquals($context, $event->get_context());
        $this->assertEquals(45, $event->other['questionbankentryid']);
        $this->assertEquals(3, $event->other['version']);
        $this->assertDebuggingNotCalled();

        $this->assertEquals('Question version imported', question_version_imported::get_name());
        $this->assertEquals("The user with id '$USER->id' imported a new version '3' (question id '123') of the " .
                "question bank entry with id '45' in course '$course->id'.",
                $event->get_description());
    }
}
