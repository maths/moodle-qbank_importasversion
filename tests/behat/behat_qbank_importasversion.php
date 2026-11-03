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

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

/**
 * Behat steps for the Import question as new version plugin.
 *
 * @package   qbank_importasversion
 * @copyright 2026 LT&C
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_qbank_importasversion extends behat_base {
    /**
     * Convert a page name to a URL, for steps like: I am on the "Course 1" "qbank_importasversion > course bulk import" page.
     *
     * Recognised page names are:
     * | pagetype         | name meaning | description                                  |
     * | course bulk import | Course name  | The bulk import of new versions for a course |
     *
     * @param string $type identifies which type of page this is.
     * @param string $identifier identifies the particular page, e.g. 'Course 1'.
     * @return moodle_url the corresponding URL.
     * @throws Exception with a meaningful error message if the specified page cannot be found.
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        switch (strtolower($type)) {
            case 'course bulk import':
                return new moodle_url(
                    '/question/bank/importasversion/bulkimport.php',
                    $this->get_question_bank_params($this->get_course_id($identifier))
                );

            default:
                throw new Exception('Unrecognised qbank_importasversion page type "' . $type . '."');
        }
    }

    /**
     * The parameters that identify the question bank of a course on a question bank page.
     *
     * From Moodle 5.0 the question bank of a course is an activity (mod_qbank), and its pages need the cmid.
     * Before that they need the course id.
     *
     * @param int $courseid the course id.
     * @return array the page parameters.
     */
    protected function get_question_bank_params(int $courseid): array {
        global $DB;

        if (core_component::get_component_directory('mod_qbank')) {
            $cmid = $DB->get_field_sql(
                'SELECT cm.id
                   FROM {course_modules} cm
                   JOIN {modules} m ON m.id = cm.module
                  WHERE m.name = :name AND cm.course = :course
               ORDER BY cm.id',
                ['name' => 'qbank', 'course' => $courseid],
                IGNORE_MULTIPLE
            );
            if ($cmid) {
                return ['cmid' => $cmid];
            }
        }
        return ['courseid' => $courseid];
    }
}
