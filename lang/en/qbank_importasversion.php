<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin strings are defined here.
 *
 * @package   qbank_importasversion
 * @category  string
 * @copyright 2023 MootDACH DevCamp
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['bulkattention'] = 'Questions that need your attention';
$string['bulkconfirm'] = 'Import {$a} new versions';
$string['bulkfailed'] = 'Nothing was imported. Question {$a->index} ({$a->name}) could not be saved: {$a->error}';
$string['bulkfilegone'] = 'The uploaded file is no longer available. Upload it again.';
$string['bulkimport'] = 'Import new versions';
$string['bulkimport_help'] = 'Upload a Moodle XML file. Each question in it becomes a new version of the question with the same ID number in this question bank. Questions without a match are skipped. Either all matched questions are imported, or none.';
$string['bulkimported'] = 'Imported {$a} new versions.';
$string['bulknothing'] = 'No question in the file matches a question in this bank. Nothing will be imported.';
$string['bulkpreview'] = '{$a} questions will get a new version. Nothing has been imported yet.';
$string['bulkpreviewbutton'] = 'Preview';
$string['bulkpreviewmergetags'] = 'Preview with merged tags';
$string['event:question_version_imported'] = 'Question version imported';
$string['forceimport'] = 'Import with warnings as Draft';
$string['forceimport_help'] = 'By default, question-type save warnings stop this upload. Enable this option to retain a version with warnings as Draft for review or repair, leaving existing versions unchanged. Imports without warnings are still saved as Ready. Save errors cannot be overridden.';
$string['importandmergetags'] = 'Import and merge tags';
$string['importasversion'] = 'Import a new version';
$string['importasversion_help'] = 'Imports a question from a file but creates a new version of an existing question';
$string['importedwithwarningsasdraft'] = 'The new version was imported as Draft for review or repair.';
$string['importerror'] = 'Error while importing the file';
$string['importnewversionofx'] = 'Import a new version of question {$a}';
$string['pluginname'] = 'Import question as new version';
$string['privacy:metadata'] = 'The Import question as new version question bank plugin does not store any personal data.';
$string['questionimportedasversion'] = 'New version of question \'{$a}\' imported successfully.';
$string['questionimportedwithwarnings'] = 'New version of question \'{$a->name}\' imported successfully. However, an issue was raised: {$a->notice}';
$string['questionimportfailed'] = 'Question import failed for \'{$a->name}\'. {$a->error}';
$string['status_ambiguous'] = 'Skipped: several categories have a question with this ID number.';
$string['status_duplicate'] = 'Skipped: the file contains this ID number more than once.';
$string['status_noidnumber'] = 'Skipped: the question has no ID number.';
$string['status_nopermission'] = 'Skipped: you cannot edit the existing question.';
$string['status_notfound'] = 'Skipped: no question in this bank has this ID number.';
$string['status_typemismatch'] = 'Skipped: the existing question has a different type.';
$string['toomanyquestionsinfile'] = 'Your file did not contain exactly one question!';
$string['unknownerror'] = 'There was an error saving the question.';
