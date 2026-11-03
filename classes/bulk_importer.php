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
use core_php_time_limit;
use qformat_xml;
use question_bank;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->libdir . '/questionlib.php');

/**
 * Import many questions from one Moodle XML file, each as a new version of an existing question.
 *
 * A question in the file is matched to an existing question by category and idnumber. Questions that
 * do not match are skipped, never created: this plugin only adds versions to existing questions.
 * Like the single-question import, no comparison is made with the current version.
 *
 * The same code path produces the dry run and the real import, so what the dry run reports is what
 * the import does.
 *
 * Usage: create an instance, then set up the format the way the core import does
 * (setCategory(), setContexts(), setCourse(), setCatfromfile(), setContextfromfile(), setMatchgrades(),
 * setStoponerror()), then call run().
 *
 * @package   qbank_importasversion
 * @copyright 2026 LT&C
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulk_importer extends qformat_xml {
    /** @var string The question will be, or (dry run) would be, imported as a new version. */
    public const OUTCOME_NEWVERSION = 'newversion';

    /** @var string The question was imported as a new version. */
    public const OUTCOME_IMPORTED = 'imported';

    /** @var string The question is not imported; see the reason. */
    public const OUTCOME_SKIPPED = 'skipped';

    /** @var string Saving the question failed; nothing was saved for it. */
    public const OUTCOME_ERROR = 'error';

    /** @var string The question in the file has no idnumber, so it cannot be matched. */
    public const REASON_NOIDNUMBER = 'noidnumber';

    /** @var string The category path from the file does not exist. */
    public const REASON_CATEGORYNOTFOUND = 'categorynotfound';

    /** @var string More than one category matches the path from the file. */
    public const REASON_CATEGORYAMBIGUOUS = 'categoryambiguous';

    /** @var string No existing question has this idnumber in the category. */
    public const REASON_NOTFOUND = 'notfound';

    /** @var string More than one existing question has this idnumber in the category. */
    public const REASON_AMBIGUOUS = 'ambiguous';

    /** @var string The same category and idnumber occur more than once in the file. */
    public const REASON_DUPLICATEINFILE = 'duplicateinfile';

    /** @var string The existing question has another question type. */
    public const REASON_TYPEMISMATCH = 'typemismatch';

    /** @var string The user may not edit the existing question. */
    public const REASON_NOPERMISSION = 'nopermission';

    /** @var string The question has an answer grade that is not valid. */
    public const REASON_INVALIDGRADES = 'invalidgrades';

    /** @var string The question could be imported, but "Stop on error" is on and the file has errors. */
    public const REASON_BLOCKED = 'blocked';

    /** @var string[] Parse errors, collected here instead of being printed. */
    protected $parseerrors = [];

    /** @var category_finder Finds the category for a path from the file. */
    protected $finder;

    /** @var question_matcher Finds the existing question for a question from the file. */
    protected $matcher;

    /**
     * Collect parse errors instead of printing them, so the caller decides how to show them.
     *
     * The signature is that of the core method, so $text is not used: it is the offending text from the file,
     * which we do not show.
     *
     * @param string $message the error message.
     * @param string $text the offending text, if any.
     * @param string $questionname the name of the question, if known.
     */
    #[\Override]
    protected function error($message, $text = '', $questionname = '') {
        $this->parseerrors[] = trim(($questionname !== '' ? $questionname . ': ' : '') . $message);
        $this->importerrors++;
    }

    /**
     * Read the file, decide for each question what happens to it and, if asked, import it.
     *
     * Nothing is saved when $execute is false, or when the result is blocked.
     *
     * @param string $file path of the Moodle XML file.
     * @param bool $execute false for a dry run, true to save the new versions.
     * @param bool $mergetags if true, merge the file's tags with the replaced version's tags.
     * @param bool $force if true, retain a version that has save warnings, as Draft.
     * @return stdClass with rows (one object per question: index, name, category, idnumber, qtype,
     *      outcome, reason, message, questionid), errors (parse errors) and blocked (true if
     *      "stop on error" applies and errors were found, so nothing may be imported).
     */
    public function run(string $file, bool $execute, bool $mergetags = false, bool $force = false): stdClass {
        if (!$this->category) {
            throw new \coding_exception('Call setCategory() before run().');
        }

        core_php_time_limit::raise();
        raise_memory_limit(MEMORY_EXTRA);

        $this->displayprogress = false;
        $this->importerrors = 0;
        $this->finder = new category_finder(
            context::instance_by_id($this->category->contextid),
            (bool) $this->contextfromfile,
            $this->translator
        );
        $this->matcher = new question_matcher();

        $result = new stdClass();
        $result->rows = [];
        $result->errors = [];
        $result->blocked = true;

        $questions = $this->read_questions($file, $result);
        if ($questions === null) {
            return $result;
        }

        $result->blocked = false;
        $result->rows = $this->classify($questions, $result);
        unset($questions);
        if ($result->blocked) {
            $this->block_rows($result->rows);
        }
        if (!$result->blocked && $execute) {
            $this->save_rows($result->rows, $mergetags, $force);
        }
        return $result;
    }

    /**
     * Count the rows per outcome.
     *
     * @param stdClass[] $rows the rows from run().
     * @return int[] the number of rows, keyed by outcome.
     */
    public static function count_outcomes(array $rows): array {
        $counts = [
            self::OUTCOME_NEWVERSION => 0,
            self::OUTCOME_IMPORTED => 0,
            self::OUTCOME_SKIPPED => 0,
            self::OUTCOME_ERROR => 0,
        ];
        foreach ($rows as $row) {
            $counts[$row->outcome]++;
        }
        return $counts;
    }

    /**
     * Read and parse the file.
     *
     * @param string $file path of the Moodle XML file.
     * @param stdClass $result the result being built; its errors are filled in here.
     * @return stdClass[]|null the parsed questions and category entries, or null if nothing may be imported.
     */
    protected function read_questions(string $file, stdClass $result): ?array {
        $this->parseerrors = [];

        $lines = $this->readdata($file);
        if (!$lines) {
            $result->errors[] = get_string('cannotread', 'question');
            return null;
        }

        // Check for at least one question element, which readquestions() needs.
        $hasquestions = stripos(implode('', $lines), '<question') !== false;
        $questions = $hasquestions ? $this->readquestions($lines) : false;
        unset($lines);

        $result->errors = $this->parseerrors;
        if (!$questions) {
            $result->errors = $result->errors ?: [get_string('noquestionsinfile', 'question')];
            return null;
        }
        if ($this->stoponerror && $this->importerrors > 0) {
            return null;
        }
        return $questions;
    }

    /**
     * Decide for each parsed question what happens to it. Does not save anything.
     *
     * @param stdClass[] $questions the parsed questions and category entries.
     * @param stdClass $result the result being built; blocked is set here when "stop on error" applies.
     * @return stdClass[] the rows.
     */
    protected function classify(array $questions, stdClass $result): array {
        $gradeoptions = question_bank::fraction_options_full();
        $rows = [];
        $gradeerrors = 0;

        // Questions before the first category in the file go to the category the user selected.
        $category = $this->category;
        $categorypath = $this->finder->get_path($this->category);
        $categoryreason = null;

        foreach ($questions as $question) {
            if ($question->qtype === 'category') {
                if ($this->catfromfile) {
                    // Show the category in Moodle that the questions are matched in, or the path from the file if it is not found.
                    [$category, $categoryreason] = $this->finder->find($this->split_category_path($question->category));
                    $categorypath = $category ? $this->finder->get_path($category) : $question->category;
                }
                continue;
            }

            $row = $this->create_row(count($rows) + 1, $question, $categorypath);
            $rows[] = $row;
            if (!$this->normalise_grades($question, $gradeoptions)) {
                $gradeerrors++;
                $row->reason = self::REASON_INVALIDGRADES;
                continue;
            }
            $this->match_question($row, $question, $category, $categoryreason);
        }

        $result->blocked = $this->stoponerror && $gradeerrors > 0;
        $this->skip_duplicates($rows);
        return $rows;
    }

    /**
     * Create the row for a question from the file. It is skipped until a match is found.
     *
     * @param int $index the position of the question in the file, among the questions.
     * @param stdClass $question the parsed question.
     * @param string $categorypath the category path to show.
     * @return stdClass the row.
     */
    protected function create_row(int $index, stdClass $question, string $categorypath): stdClass {
        $row = new stdClass();
        $row->index = $index;
        $row->name = $question->name ?? '';
        $row->category = $categorypath;
        $row->idnumber = isset($question->idnumber) ? (string) $question->idnumber : '';
        $row->qtype = $question->qtype;
        $row->outcome = self::OUTCOME_SKIPPED;
        $row->reason = null;
        $row->message = '';
        $row->questionid = null;
        return $row;
    }

    /**
     * Check the answer grades of a question like the core import does, and replace them by valid ones.
     *
     * @param stdClass $question the parsed question.
     * @param array $gradeoptions the valid grades.
     * @return bool false if a grade is not valid.
     */
    protected function normalise_grades(stdClass $question, array $gradeoptions): bool {
        if (empty($question->fraction) || !is_array($question->fraction)) {
            return true;
        }
        $fractions = $question->fraction;
        foreach ($fractions as $key => $fraction) {
            $newfraction = match_grade_options($gradeoptions, $fraction, $this->matchgrades);
            if ($newfraction === false) {
                return false;
            }
            $fractions[$key] = $newfraction;
        }
        $question->fraction = $fractions;
        return true;
    }

    /**
     * Find the existing question for a question from the file and update its row.
     *
     * @param stdClass $row the row of the question.
     * @param stdClass $question the parsed question.
     * @param stdClass|null $category the category the question is in, or null if there is none.
     * @param string|null $categoryreason why there is no category.
     */
    protected function match_question(stdClass $row, stdClass $question, ?stdClass $category, ?string $categoryreason): void {
        if ($category === null) {
            $row->reason = $categoryreason;
            return;
        }
        if ($row->idnumber === '') {
            $row->reason = self::REASON_NOIDNUMBER;
            return;
        }

        [$existing, $reason] = $this->matcher->find($category->id, $row->idnumber, $question);
        if ($existing === null) {
            $row->reason = $reason;
            return;
        }

        $row->outcome = self::OUTCOME_NEWVERSION;
        $row->key = $category->id . '|' . $row->idnumber;
        $row->existing = $existing;
        $row->question = $question;
    }

    /**
     * Skip every question whose category and idnumber occur more than once in the file.
     *
     * We cannot know which of them is meant, so none is imported.
     *
     * @param stdClass[] $rows the rows.
     */
    protected function skip_duplicates(array $rows): void {
        $seen = [];
        foreach ($rows as $row) {
            if ($row->outcome === self::OUTCOME_NEWVERSION) {
                $seen[$row->key] = ($seen[$row->key] ?? 0) + 1;
            }
        }
        foreach ($rows as $row) {
            if ($row->outcome === self::OUTCOME_NEWVERSION && $seen[$row->key] > 1) {
                $row->outcome = self::OUTCOME_SKIPPED;
                $row->reason = self::REASON_DUPLICATEINFILE;
                unset($row->existing, $row->question);
            }
            unset($row->key);
        }
    }

    /**
     * When "Stop on error" blocks the import, no question will be imported: say so in the rows.
     *
     * @param stdClass[] $rows the rows.
     */
    protected function block_rows(array $rows): void {
        foreach ($rows as $row) {
            if ($row->outcome === self::OUTCOME_NEWVERSION) {
                $row->outcome = self::OUTCOME_SKIPPED;
                $row->reason = self::REASON_BLOCKED;
                unset($row->existing, $row->question);
            }
        }
    }

    /**
     * Save the rows that are to be imported, each in its own transaction.
     *
     * @param stdClass[] $rows the rows.
     * @param bool $mergetags whether to merge tags.
     * @param bool $force whether to retain a version that has save warnings (as Draft).
     */
    protected function save_rows(array $rows, bool $mergetags, bool $force): void {
        foreach ($rows as $row) {
            if ($row->outcome === self::OUTCOME_NEWVERSION) {
                $this->save_row($row, $mergetags, $force);
            }
            unset($row->existing, $row->question);
        }
    }

    /**
     * Save one row as a new version, in its own transaction.
     *
     * @param stdClass $row the row to save.
     * @param bool $mergetags whether to merge tags.
     * @param bool $force whether to retain a version that has save warnings (as Draft).
     */
    protected function save_row(stdClass $row, bool $mergetags, bool $force): void {
        global $DB;

        core_php_time_limit::raise();

        try {
            $saved = importer::import_question_as_version(
                $this,
                $row->existing,
                $row->question,
                $mergetags,
                $force,
                true // Retained warnings are imported as Draft, as in the single-question form.
            );
        } catch (\Throwable $e) {
            // Show the user a plugin message, not the exception text, which could contain server paths.
            if ($DB->is_transaction_started()) {
                $DB->force_transaction_rollback();
            }
            debugging($e->getMessage(), DEBUG_DEVELOPER);
            $row->outcome = self::OUTCOME_ERROR;
            return;
        }

        if (!empty($saved->error)) {
            $row->outcome = self::OUTCOME_ERROR;
            $row->message = $saved->error;
            return;
        }

        $row->outcome = self::OUTCOME_IMPORTED;
        $row->questionid = end($this->questionids);
        if (!empty($saved->notice)) {
            $row->message = $saved->notice;
        }
    }
}
