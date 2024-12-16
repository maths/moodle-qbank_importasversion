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

use moodle_url;

// Support multiple Moodle versions. This can be cleaned up once 4.3 is the lowest supported version.
if (!class_exists('\core_question\local\bank\question_action_base')) {
    // Moodle up to 4.2.x.
    class_alias('\core_question\local\bank\menu_action_column_base',
            'qbank_importasversion\qbank_importasversion_column_parent_class');
} else {
    // Moodle 4.3+.
    class_alias('\core_question\local\bank\question_action_base',
            'qbank_importasversion\qbank_importasversion_column_parent_class');
}

/**
 * Action to import a question from a file as a new version of an existing question.
 *
 * @package   qbank_importasversion
 * @copyright 2023 MootDACH DevCamp
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_as_version_action_column extends qbank_importasversion_column_parent_class {

    /** @var string store the value of the name lang string for performance. */
    protected $actionname;

    #[\Override]
    public function init(): void {
        parent::init();
        $this->actionname = get_string('importasversion', 'qbank_importasversion');
    }

    /** @return string The name of this plugin */
    public function get_name(): string {
        return 'importasversion';
    }

    #[\Override]
    public function get_menu_position(): int {
        return 650;
    }

    #[\Override]
    protected function get_url_icon_and_label(\stdClass $question): array {
        if (!\question_bank::is_qtype_installed($question->qtype)) {
            // It sometimes happens that people end up with junk questions
            // in their question bank of a type that is no longer installed.
            // We cannot do most actions on them, because that leads to errors.
            return [null, null, null];
        }

        if (!question_has_capability_on($question, 'edit')) {
            return [null, null, null];
        }

        $params = [
            'id' => $question->id,
            'returnurl' => $this->qbank->returnurl,
        ];
        $context = $this->qbank->get_most_specific_context();
        if ($context->contextlevel == CONTEXT_MODULE) {
            $params['cmid'] = $context->instanceid;
        } else {
            $params['courseid'] = $context->instanceid;
        }

        return [
            new moodle_url('/question/bank/importasversion/import.php', $params),
            't/restore',
            $this->actionname,
        ];
    }
}
