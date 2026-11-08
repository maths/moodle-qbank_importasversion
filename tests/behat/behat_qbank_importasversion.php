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
 * Behat page names for the import-as-version plugin.
 *
 * @package   qbank_importasversion
 * @copyright 2026 ETH Zurich
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_qbank_importasversion extends behat_base {
    /**
     * Resolve "<course> bulk import" pages.
     *
     * The question bank menu lists plugin pages from Moodle 4.3, so the scenario opens the page directly.
     *
     * @param string $type the page type.
     * @param string $identifier the course short or full name.
     * @return moodle_url
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        global $DB;
        if (strtolower($type) !== 'bulk import') {
            throw new Exception('Unrecognised qbank_importasversion page type "' . $type . '."');
        }
        $courseid = $this->get_course_id($identifier);
        // From Moodle 5.0, course questions live in a question bank module.
        if (core_component::get_component_directory('mod_qbank')) {
            $cmid = $DB->get_field_sql("SELECT cm.id
                                          FROM {course_modules} cm
                                          JOIN {modules} m ON m.id = cm.module
                                         WHERE m.name = 'qbank' AND cm.course = ?
                                      ORDER BY cm.id", [$courseid], IGNORE_MULTIPLE);
            if ($cmid) {
                return new moodle_url('/question/bank/importasversion/bulkimport.php', ['cmid' => $cmid]);
            }
        }
        return new moodle_url('/question/bank/importasversion/bulkimport.php', ['courseid' => $courseid]);
    }
}
