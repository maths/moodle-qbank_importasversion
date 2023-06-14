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
 * Displays and handles the form for importing a question as a new version of an existing one.
 *
 * @package   qbank_importasversion
 * @copyright 2023 MootDACH DevCamp
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use qbank_importasversion\form\import_form;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->dirroot . '/question/format.php');
require_once(__DIR__ . '/classes/importer.php');

core_question\local\bank\helper::require_plugin_enabled('qbank_importasversion');

// Get and validate question id.
$id = required_param('id', PARAM_INT);
$returnurl = optional_param('returnurl', null, PARAM_LOCALURL);

$urlparams = ['id' => $id];
$question = question_bank::load_question($id);

if ($returnurl) {
    $returnurl = new moodle_url($returnurl);
    $urlparams['returnurl'] = $returnurl->out_as_local_url(false);
}

// Were we given a particular context to run the question in?
// This affects things like filter settings, or forced theme or language.
if ($cmid = optional_param('cmid', 0, PARAM_INT)) {
    $cm = get_coursemodule_from_id(false, $cmid);
    require_login($cm->course, false, $cm);
    $context = context_module::instance($cmid);
    $urlparams['cmid'] = $cmid;

} else {
    $courseid = required_param('courseid', PARAM_INT);
    require_login($courseid);
    $context = context_course::instance($courseid);
    $urlparams['courseid'] = $courseid;
}
$PAGE->set_url('/question/bank/importasversion/import.php', $urlparams);
$PAGE->set_pagelayout('popup');

question_require_capability_on($question, 'edit');

// Page header.
$title = get_string('importnewversionofx', 'qbank_importasversion',
        format_string($question->name, true, ['context' => $context]));
$PAGE->set_title($title);
$PAGE->set_heading($COURSE->fullname);
$PAGE->activityheader->disable();

$importform = new import_form();
$importform->set_data($urlparams);

// Handle form cancelled.
if ($importform->is_cancelled()) {
    redirect($returnurl);
}

// Handle to form being submitted.
if ($fromform = $importform->get_data()) {

    $fromform->format = 'xml';

    // File checks out ok.
    $fileisgood = false;

    // Work out if this is an uploaded file.
    // Or one from the filesarea.
    $realfilename = $importform->get_new_filename('newfile');
    $importfile = make_request_directory() . "/{$realfilename}";
    if (!$result = $importform->save_file('newfile', $importfile, true)) {
        throw new moodle_exception('uploadproblem');
    }
    
    $formatfile = $CFG->dirroot . '/question/format/' . $fromform->format . '/format.php';
    if (!is_readable($formatfile)) {
        throw new moodle_exception('formatnotfound', 'question', '', $fromform->format);
    }
    
    require_once($formatfile);
    
    $classname = 'qformat_' . $fromform->format;
    $qformat = new $classname();
    
    // Do anything before that we need to.
    if (!$qformat->importpreprocess()) {
        throw new moodle_exception('cannotimport', '', $thispageurl->out());
    }

    qbank_importasversion\importer::import_file($qformat, $question, $importfile);

    // In case anything needs to be done after.
    if (!$qformat->importpostprocess()) {
        throw new moodle_exception('cannotimport', '', $thispageurl->out());
    }
    
    redirect($returnurl, get_string('questionimportedasversion', 'qbank_importasversion', format_string($question->name)));
    exit;
}

// Display the form.
echo $OUTPUT->header();

echo $OUTPUT->heading($title);

$importform->display();

echo $OUTPUT->footer();
