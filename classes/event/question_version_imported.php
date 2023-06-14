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

use core\event\base;
use core\event\question_base;

/**
 * Log event for when a new version of a question is created by importing.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - int categoryid: the question category id.
 *      - int questionbankentryid: the id of the question bank entry that was updated.
 *      - int version: the version number of the new version that was created.
 * }
 *
 * @package   qbank_importasversion
 * @category  event
 * @copyright 2023 MootDACH DevCamp
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_version_imported extends question_base {

    protected function init() {
        parent::init();
        $this->data['crud'] = 'u';
    }

    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['questionbankentryid'])) {
            throw new \coding_exception('The \'questionbankentryid\' value must be set in other.');
        }
        if (!isset($this->other['version'])) {
            throw new \coding_exception('The \'version\' value must be set in other.');
        }
    }

    public static function get_name() {
        return get_string('event:question_version_imported', 'qbank_importasversion');
    }

    public function get_description() {
        return "The user with id '$this->userid' imported a new version " .
                "'{$this->other['version']}' (question id '$this->objectid') of the " .
                "question bank entry with id '{$this->other['questionbankentryid']}' in course '$this->courseid'.";
    }

    public static function get_objectid_mapping() {
        return ['db' => 'question', 'restore' => 'question'];
    }

    public static function get_other_mapping() {
        return [
            'questionbankentryid' => ['db' => 'question_bank_entries', 'restore' => 'question_bank_entries'],
            'version' => base::NOT_MAPPED,
        ];
    }
}
