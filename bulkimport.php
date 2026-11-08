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
 * Preview and import a Moodle XML file with many questions as new versions of existing questions.
 *
 * @package   qbank_importasversion
 * @copyright 2026 ETH Zurich
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use qbank_importasversion\bulk_import;
use qbank_importasversion\form\import_form;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/question/editlib.php');

core_question\local\bank\helper::require_plugin_enabled('qbank_importasversion');

if ($cmid = optional_param('cmid', 0, PARAM_INT)) {
    $cm = get_coursemodule_from_id(false, $cmid, 0, false, MUST_EXIST);
    require_login($cm->course, false, $cm);
    $context = context_module::instance($cmid);
    $urlparams = ['cmid' => $cmid];
} else {
    $courseid = required_param('courseid', PARAM_INT);
    require_login($courseid);
    $context = context_course::instance($courseid);
    $urlparams = ['courseid' => $courseid];
}
if (!has_any_capability(['moodle/question:editall', 'moodle/question:editmine'], $context)) {
    throw new required_capability_exception($context, 'moodle/question:editmine', 'nopermissions', '');
}

$thispageurl = new moodle_url('/question/bank/importasversion/bulkimport.php', $urlparams);
$returnurl = new moodle_url('/question/edit.php', $urlparams);
$PAGE->set_url($thispageurl);
$PAGE->set_title(get_string('bulkimport', 'qbank_importasversion'));
$PAGE->set_heading($COURSE->fullname);
$PAGE->activityheader->disable();

$form = new import_form($thispageurl, ['preview' => true]);
$form->set_data($urlparams);
if ($form->is_cancelled()) {
    redirect($returnurl);
}

$confirm = optional_param('confirm', 0, PARAM_BOOL);
$file = make_request_directory() . '/import.xml';
if ($confirm) {
    require_sesskey();
    $draftitemid = required_param('draftitemid', PARAM_INT);
    $options = ['force' => optional_param('force', 0, PARAM_BOOL), 'mergetags' => optional_param('mergetags', 0, PARAM_BOOL)];
    $usercontext = context_user::instance($USER->id);
    $draft = get_file_storage()->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id DESC', false);
    if (!$draft) {
        redirect(
            $thispageurl,
            get_string('bulkfilegone', 'qbank_importasversion'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    reset($draft)->copy_content_to($file);
    $result = bulk_import::import($context, $file, $options['mergetags'], $options['force']);
    // Removing the file stops a page reload from importing it again.
    get_file_storage()->delete_area_files($usercontext->id, 'user', 'draft', $draftitemid);
} else if ($data = $form->get_data()) {
    $form->save_file('newfile', $file, true);
    $draftitemid = $data->newfile;
    $options = ['force' => (int) !empty($data->force), 'mergetags' => (int) isset($data->mergetagsbutton)];
    $result = bulk_import::plan($context, $file);
}

echo $OUTPUT->header();
// The question bank menu: qbank_action_menu from Moodle 4.3, qbank_actionbar before.
$bankrenderer = $PAGE->get_renderer('core_question', 'bank');
if (class_exists('\core_question\output\qbank_action_menu')) {
    echo $bankrenderer->render(new \core_question\output\qbank_action_menu($thispageurl));
} else if (class_exists('\core_question\output\qbank_actionbar')) {
    echo $bankrenderer->render(new \core_question\output\qbank_actionbar($thispageurl));
}
echo $OUTPUT->heading(get_string('bulkimport', 'qbank_importasversion'));
if (!isset($result)) {
    echo html_writer::tag('p', get_string('bulkimport_help', 'qbank_importasversion'));
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

foreach ($result->errors as $error) {
    echo $OUTPUT->notification(s($error), \core\output\notification::NOTIFY_ERROR);
}
$counts = bulk_import::count($result->rows);
$ready = $counts[bulk_import::READY] ?? 0;
if (!empty($result->error)) {
    $a = ['index' => $result->failed->index, 'name' => format_string($result->failed->name), 'error' => s($result->error)];
    echo $OUTPUT->notification(get_string('bulkfailed', 'qbank_importasversion', $a), \core\output\notification::NOTIFY_ERROR);
} else if ($confirm) {
    echo $OUTPUT->notification(
        get_string('bulkimported', 'qbank_importasversion', $result->imported),
        \core\output\notification::NOTIFY_SUCCESS
    );
} else if (!$result->errors) {
    $preview = $ready ? get_string('bulkpreview', 'qbank_importasversion', $ready)
        : get_string('bulknothing', 'qbank_importasversion');
    echo html_writer::div($preview, 'alert ' . ($ready ? 'alert-info' : 'alert-warning'), ['role' => 'status']);
}

// List only the questions that need attention: skipped ones, and imported ones with warnings.
$table = new html_table();
$table->caption = get_string('bulkattention', 'qbank_importasversion');
$table->head = ['#', get_string('questionname', 'question'), get_string('idnumber', 'question'),
    get_string('status')];
foreach ($result->rows as $row) {
    $message = $row->status === bulk_import::READY ? clean_text($row->notice ?? '') : get_string(
        'status_' . $row->status,
        'qbank_importasversion'
    );
    if ($message !== '') {
        $table->data[] = [$row->index, format_string($row->name), s($row->idnumber), $message];
    }
}
if ($table->data) {
    echo html_writer::table($table);
}

if ($confirm) {
    echo $OUTPUT->continue_button($returnurl);
} else {
    $buttons = '';
    if ($ready && !$result->errors) {
        $params = $urlparams + $options + ['confirm' => 1, 'draftitemid' => $draftitemid];
        // Moodle 4.5 added primary single buttons. Older versions show a secondary button.
        $primary = defined('\core\output\single_button::BUTTON_PRIMARY')
            ? ['type' => \core\output\single_button::BUTTON_PRIMARY] : [];
        $buttons .= $OUTPUT->single_button(
            new moodle_url($thispageurl, $params),
            get_string('bulkconfirm', 'qbank_importasversion', $ready),
            'post',
            $primary
        );
    }
    $buttons .= $OUTPUT->single_button($returnurl, get_string('cancel'), 'get');
    echo html_writer::div($buttons, 'd-flex gap-2 mt-3');
}
echo $OUTPUT->footer();
