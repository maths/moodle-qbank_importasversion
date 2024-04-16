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

use context;
use context_course;
use core_question\local\bank\question_version_status;
use core_tag_tag;
use exception;
use qbank_importasversion\event\question_version_imported;
use qformat_xml;
use question_bank;
use question_definition;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/format/xml/format.php');

/**
 * Information about which features are provided by this plugin.
 *
 * @package   qbank_importasversion
 * @copyright 2023 MootDACH DevCamp
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class importer extends qformat_xml {

    /**
     * Import a file to update a given question.
     *
     * @param qformat_xml $qformat an instance of
     * @param question_definition $question the question to add a version to.
     * @param string $importedquestionfile filename of the file to import.
     * @return bool true on success. False on failure.
     */
    public static function import_file(
        qformat_xml $qformat,
        question_definition $question,
        string $importedquestionfile
    ) {
        global $USER, $DB, $OUTPUT;

        $context = context::instance_by_id($question->contextid);

        // STAGE 1: Parse the file.
        if (! $importedlines = $qformat->readdata($importedquestionfile)) {
            throw new exception(get_string('cannotread', 'question'));
        }

        // Extract all the questions.
        if (!$importedquestions = $qformat->readquestions($importedlines)) {
            throw new exception(get_string('noquestionsinfile', 'question'));
        }

        // Check if there's only one question in the file -- remove this once we do batch processing!
        if (count($importedquestions) != 1) {
            throw new exception(get_string('toomanyquestionsinfile', 'qbank_importasversion'));
        }

        // Count number of questions processed.
        $count = 0;

        // For now, single question.
        $importedquestion = $importedquestions[0];

        $transaction = $DB->start_delegated_transaction();

        $count++;

        if ($qformat->displayprogress) {
            echo "<hr /><p><b>{$count}</b>. " . $qformat->format_question_text($question) . "</p>";
        }

        $fileoptions = [
                'subdirs' => true,
                'maxfiles' => -1,
                'maxbytes' => 0,
        ];

        // Create new question object from imported question.
        $newquestion = $importedquestion;
        $newquestion->createdby = $USER->id;
        $newquestion->timecreated = time();
        $newquestion->modifiedby = $USER->id;
        $newquestion->timemodified = time();
        $newquestion->context = $context; // Context is taken from the existing question.
        $newquestion->category = $question->category;
        $newquestion->id = $DB->insert_record('question', $importedquestion);

        // Create a version for each question imported.
        $questionversion = new stdClass();
        $questionversion->questionbankentryid = $question->questionbankentryid;
        $questionversion->questionid = $newquestion->id;
        $questionversion->version = get_next_version($question->questionbankentryid);
        $questionversion->status = question_version_status::QUESTION_STATUS_READY; // TODO: Give an option on the form.
        $questionversion->id = $DB->insert_record('question_versions', $questionversion);

        if (isset($newquestion->questiontextitemid)) {
            $newquestion->questiontext = file_save_draft_area_files($newquestion->questiontextitemid,
                    $context->id, 'question', 'questiontext', $newquestion->id,
                    $fileoptions, $newquestion->questiontext);
        } else if (isset($newquestion->questiontextfiles)) {
            foreach ($newquestion->questiontextfiles as $file) {
                question_bank::get_qtype($newquestion->qtype)->import_file(
                        $context, 'question', 'questiontext', $newquestion->id, $file);
            }
        }
        if (isset($newquestion->generalfeedbackitemid)) {
            $newquestion->generalfeedback = file_save_draft_area_files($newquestion->generalfeedbackitemid,
                    $context->id, 'question', 'generalfeedback', $newquestion->id,
                    $fileoptions, $newquestion->generalfeedback);
        } else if (isset($newquestion->generalfeedbackfiles)) {
            foreach ($newquestion->generalfeedbackfiles as $file) {
                question_bank::get_qtype($newquestion->qtype)->import_file(
                        $context, 'question', 'generalfeedback', $newquestion->id, $file);
            }
        }
        $DB->update_record('question', $newquestion);

        $qformat->questionids[] = $newquestion->id;

        // Now to save all the answers and type-specific options.

        $result = question_bank::get_qtype($newquestion->qtype)->save_question_options($newquestion);

        if (core_tag_tag::is_enabled('core_question', 'question')) {
            // Is the current context we're importing in a course context?
            $importingcontext = $context;
            $importingcoursecontext = $importingcontext->get_course_context(false);
            $isimportingcontextcourseoractivity = !empty($importingcoursecontext);

            if (!empty($newquestion->coursetags)) {
                if ($isimportingcontextcourseoractivity) {
                    $mergedtags = array_merge($newquestion->coursetags, $newquestion->tags);

                    core_tag_tag::set_item_tags('core_question', 'question', $newquestion->id,
                        $newquestion->context, $mergedtags);
                } else {
                    core_tag_tag::set_item_tags('core_question', 'question', $newquestion->id,
                        context_course::instance($qformat->course->id), $newquestion->coursetags);

                    if (!empty($newquestion->tags)) {
                        core_tag_tag::set_item_tags('core_question', 'question', $newquestion->id,
                            $importingcontext, $newquestion->tags);
                    }
                }
            } else if (!empty($newquestion->tags)) {
                core_tag_tag::set_item_tags('core_question', 'question', $newquestion->id,
                    $newquestion->context, $newquestion->tags);
            }
        }

        if (!empty($result->error)) {
            echo $OUTPUT->notification($result->error);
            // Can't use $transaction->rollback(); since it requires an exception,
            // and I don't want to rewrite this code to change the error handling now.
            $DB->force_transaction_rollback();
            return false;
        }

        question_version_imported::create([
            'context' => $context,
            'objectid' => $newquestion->id,
            'other' => [
                'categoryid' => $question->category,
                'questionbankentryid' => $question->questionbankentryid,
                'version' => $questionversion->version,
            ],
        ])->trigger();

        $transaction->allow_commit();

        if (!empty($result->notice)) {
            echo $OUTPUT->notification($result->notice);
            return true;
        }

        return true;
    }
}
