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

$string['bulkimport'] = 'Import new versions in bulk';
$string['bulkimportintro'] = 'Import many questions from one Moodle XML file at once. Each question in the file is matched to an existing question by its category and ID number, and imported as a new version of that question. Questions that cannot be matched are skipped. No new questions are created.';
$string['columncategory'] = 'Category';
$string['columnidnumber'] = 'ID number';
$string['columnname'] = 'Question name';
$string['columnnumber'] = '#';
$string['columnoutcome'] = 'Outcome';
$string['columnqtype'] = 'Question type';
$string['columnreason'] = 'Reason';
$string['confirmimport'] = 'Import as new versions ({$a})';
$string['dryrunheading'] = 'Dry run: nothing has been saved yet';
$string['dryruntablecaption'] = 'What would happen to each question';
$string['event:question_version_imported'] = 'Question version imported';
$string['filenolongeravailable'] = 'The uploaded file is no longer available. This happens if you reload the page after importing. Upload the file again to import it.';
$string['forceimport'] = 'Import with warnings as Draft';
$string['forceimport_help'] = 'By default, question-type save warnings stop this upload. Enable this option to retain a version with warnings as Draft for review or repair, leaving existing versions unchanged. Imports without warnings are still saved as Ready. Save errors cannot be overridden.';
$string['importandmergetags'] = 'Import and merge tags';
$string['importasversion'] = 'Import a new version';
$string['importasversion_help'] = 'Imports a question from a file but creates a new version of an existing question';
$string['importblocked'] = 'Nothing was imported. The messages and the table show why.';
$string['importedwithwarningsasdraft'] = 'The new version was imported as Draft for review or repair.';
$string['importerror'] = 'Error while importing the file';
$string['importnewversionofx'] = 'Import a new version of question {$a}';
$string['mergetags'] = 'Merge the tags of the file with the tags of the replaced version';
$string['nothingtoimport'] = 'No question in the file can be imported as a new version.';
$string['outcome_error'] = 'Failed';
$string['outcome_imported'] = 'Imported';
$string['outcome_newversion'] = 'New version';
$string['outcome_skipped'] = 'Skipped';
$string['pluginname'] = 'Import question as new version';
$string['previewimport'] = 'Preview';
$string['privacy:metadata'] = 'The Import question as new version question bank plugin does not store any personal data.';
$string['questionimportedasversion'] = 'New version of question \'{$a}\' imported successfully.';
$string['questionimportedwithwarnings'] = 'New version of question \'{$a->name}\' imported successfully. However, an issue was raised: {$a->notice}';
$string['questionimportfailed'] = 'Question import failed for \'{$a->name}\'. {$a->error}';
$string['reason_ambiguous'] = 'More than one question in the category has this ID number';
$string['reason_blocked'] = 'Not imported: the file has errors and Stop on error is on';
$string['reason_categoryambiguous'] = 'More than one category matches the category in the file';
$string['reason_categorynotfound'] = 'The category in the file does not exist';
$string['reason_duplicateinfile'] = 'This category and ID number occur more than once in the file';
$string['reason_invalidgrades'] = 'An answer has a grade that is not valid';
$string['reason_noidnumber'] = 'The question in the file has no ID number';
$string['reason_nopermission'] = 'You are not allowed to edit the existing question';
$string['reason_notfound'] = 'No question in the category has this ID number';
$string['reason_typemismatch'] = 'The existing question has another question type';
$string['resultheading'] = 'Result';
$string['resulttablecaption'] = 'What happened to each question';
$string['summarydryrun'] = '{$a->newversion} would be imported as a new version, {$a->skipped} would be skipped.';
$string['summaryresult'] = '{$a->imported} imported as a new version, {$a->skipped} skipped, {$a->error} failed.';
$string['toomanyquestionsinfile'] = 'Your file did not contain exactly one question!';
$string['unknownerror'] = 'There was an error saving the question.';
