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

/**
 * Defines the import questions form.
 *
 * @package    qbank_importasversion
 * @copyright  2023 Andreas Steiger andreas.steiger@math.ethz.ch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qbank_importasversion\form;

defined('MOODLE_INTERNAL') || die();

use moodle_exception;
use moodleform;
use qformat_xml;
use stdClass;

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/question/format.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');

/**
 * Form to import questions as new versions into the question bank.
 *
 * @copyright  2023 Andreas Steiger andreas.steiger@math.ethz.ch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_form extends moodleform {

    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('filepicker', 'newfile', get_string('import'),
                null, ['accepted_types' => '.xml']);
        $mform->addRule('newfile', null, 'required', null, 'client');

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);

        $mform->addElement('hidden', 'returnurl');
        $mform->setType('returnurl', PARAM_LOCALURL);

        // Submit and cancel buttons.
        $this->add_action_buttons(true, get_string('import'));
    }

    /**
     * Checks that a file has been uploaded, and that it is of a plausible type.
     *
     * @param array $data the submitted data.
     * @param array $errors the errors so far.
     * @return array the updated errors.
     */
    protected function validate_uploaded_file($data, $errors) {
        global $CFG;

        if (empty($data['newfile'])) {
            $errors['newfile'] = get_string('required');
            return $errors;
        }

        $files = $this->get_draft_files('newfile');
        if (!is_array($files) || count($files) < 1) {
            $errors['newfile'] = get_string('required');
            return $errors;
        }

        $qformat = new qformat_xml();

        $file = reset($files);
        if (!$qformat->can_import_file($file)) {
            $a = new stdClass();
            $a->actualtype = $file->get_mimetype();
            $a->expectedtype = $qformat->mime_type();
            $errors['newfile'] = get_string('importwrongfiletype', 'question', $a);
            return $errors;
        }

        $fileerrors = $qformat->validate_file($file);
        if ($fileerrors) {
            $errors['newfile'] = $fileerrors;
        }

        return $errors;
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array the errors that were found
     * @throws \dml_exception|\coding_exception|moodle_exception
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $errors = $this->validate_uploaded_file($data, $errors);
        return $errors;
    }
}
