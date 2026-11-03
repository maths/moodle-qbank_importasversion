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
 * Import many questions from one file, each as a new version of an existing question.
 *
 * Step 1 shows the form. Step 2 is a dry run that saves nothing. Step 3 does the import, after confirmation.
 *
 * @package   qbank_importasversion
 * @copyright 2026 LT&C
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use qbank_importasversion\bulk_importer;
use qbank_importasversion\form\bulk_import_form;
use qbank_importasversion\results_renderer;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->dirroot . '/question/format.php');

require_login();
core_question\local\bank\helper::require_plugin_enabled('qbank_importasversion');
[$thispageurl, $contexts, $cmid, $cm, $module, $pagevars] =
    question_edit_setup('import', '/question/bank/importasversion/bulkimport.php');
$allowedcontexts = $contexts->having_one_edit_tab_cap('import');
$returnurl = new moodle_url('/question/edit.php', $thispageurl->params());

$PAGE->set_url($thispageurl);
$title = get_string('bulkimport', 'qbank_importasversion');
$PAGE->set_title($title);
$PAGE->set_heading($COURSE->fullname);
$PAGE->activityheader->disable();

$importform = new bulk_import_form($thispageurl, ['contexts' => $allowedcontexts, 'defaultcategory' => $pagevars['cat']]);
if ($importform->is_cancelled()) {
    redirect($returnurl);
}

// What to import and how: from the confirmation (step 3) or from the form (step 2).
$options = null;
$importfile = make_request_directory() . '/bulk.xml';
$execute = optional_param('confirm', 0, PARAM_BOOL);
if ($execute) {
    require_sesskey();
    $options = [
        'itemid' => required_param('itemid', PARAM_INT),
        'catid' => required_param('catid', PARAM_INT),
        'catfromfile' => optional_param('catfromfile', 0, PARAM_BOOL),
        'contextfromfile' => optional_param('contextfromfile', 0, PARAM_BOOL),
        'matchgrades' => optional_param('matchgrades', 'error', PARAM_ALPHA),
        'stoponerror' => optional_param('stoponerror', 1, PARAM_BOOL),
        'mergetags' => optional_param('mergetags', 0, PARAM_BOOL),
        'force' => optional_param('force', 0, PARAM_BOOL),
    ];
    // The file is in the draft area of the current user, which is where the form put it.
    $draftfiles = get_file_storage()->get_area_files(
        context_user::instance($USER->id)->id,
        'user',
        'draft',
        $options['itemid'],
        'id DESC',
        false
    );
    if (!$draftfiles) {
        redirect(
            $thispageurl,
            get_string('filenolongeravailable', 'qbank_importasversion'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    reset($draftfiles)->copy_content_to($importfile);
} else if ($formdata = $importform->get_data()) {
    $options = [
        'itemid' => $formdata->newfile,
        'catid' => (int) explode(',', $formdata->category)[0],
        'catfromfile' => !empty($formdata->catfromfile),
        'contextfromfile' => !empty($formdata->contextfromfile),
        'matchgrades' => $formdata->matchgrades,
        'stoponerror' => $formdata->stoponerror,
        'mergetags' => !empty($formdata->mergetags),
        'force' => !empty($formdata->force),
    ];
    if (!$importform->save_file('newfile', $importfile, true)) {
        throw new moodle_exception('uploadproblem');
    }
}

echo $OUTPUT->header();
$renderer = $PAGE->get_renderer('core_question', 'bank');
if (class_exists('\core_question\output\qbank_action_menu')) {
    echo $renderer->render(new \core_question\output\qbank_action_menu($thispageurl));
} else if (class_exists('\core_question\output\qbank_actionbar')) {
    // Moodle 4.0 to 4.2.
    echo $renderer->render(new \core_question\output\qbank_actionbar($thispageurl));
}
echo $OUTPUT->heading($title);

if ($options === null) {
    $importform->display();
    echo $OUTPUT->footer();
    exit;
}

// The category must be one the user may import into.
$category = $DB->get_record('question_categories', ['id' => $options['catid']], '*', MUST_EXIST);
$allowedids = array_map(function ($allowedcontext) {
    return $allowedcontext->id;
}, $allowedcontexts);
if (!in_array($category->contextid, $allowedids)) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('bulkimport', 'qbank_importasversion'));
}

$importer = new bulk_importer();
$importer->setCategory($category);
$importer->setContexts($allowedcontexts);
$importer->setCourse($COURSE);
$importer->setFilename($importfile);
$importer->setRealfilename('bulk.xml');
$importer->setMatchgrades($options['matchgrades'] === 'nearest' ? 'nearest' : 'error');
$importer->setCatfromfile($options['catfromfile']);
$importer->setContextfromfile($options['contextfromfile']);
$importer->setStoponerror($options['stoponerror']);

$result = $importer->run($importfile, $execute, $options['mergetags'], $options['force']);

foreach ($result->errors as $error) {
    echo $OUTPUT->notification(s($error), \core\output\notification::NOTIFY_ERROR);
}
if ($result->blocked) {
    echo $OUTPUT->notification(get_string('importblocked', 'qbank_importasversion'), \core\output\notification::NOTIFY_ERROR);
}

if ($result->rows) {
    $heading = get_string($execute ? 'resultheading' : 'dryrunheading', 'qbank_importasversion');
    echo $OUTPUT->heading($heading, 3);
    echo results_renderer::summary($result->rows, !$execute);
    $caption = get_string($execute ? 'resulttablecaption' : 'dryruntablecaption', 'qbank_importasversion');
    echo results_renderer::table($result->rows, $caption);
}

if ($execute) {
    // The file has been used. Removing it means that reloading this page cannot import it a second time.
    get_file_storage()->delete_area_files(context_user::instance($USER->id)->id, 'user', 'draft', $options['itemid']);
    echo $OUTPUT->continue_button($returnurl);
} else {
    $counts = bulk_importer::count_outcomes($result->rows);
    $buttons = '';
    if (!$result->blocked && $counts[bulk_importer::OUTCOME_NEWVERSION] > 0) {
        $confirmurl = new moodle_url($thispageurl, ['confirm' => 1, 'sesskey' => sesskey()]);
        foreach ($options as $name => $value) {
            $confirmurl->param($name, (int) $value);
        }
        $confirmurl->param('matchgrades', $options['matchgrades']);
        $label = get_string('confirmimport', 'qbank_importasversion', $counts[bulk_importer::OUTCOME_NEWVERSION]);
        // From Moodle 4.5 the button can be primary. The older option for that is deprecated and gives a debugging message.
        $buttonoptions = [];
        if (defined('\core\output\single_button::BUTTON_PRIMARY')) {
            $buttonoptions['type'] = \core\output\single_button::BUTTON_PRIMARY;
        }
        $buttons .= $OUTPUT->single_button($confirmurl, $label, 'post', $buttonoptions);
    }
    $buttons .= $OUTPUT->single_button($returnurl, get_string('cancel'), 'get');
    echo html_writer::div($buttons, 'd-flex gap-2 mt-3');
}

echo $OUTPUT->footer();
