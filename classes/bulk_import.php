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
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->libdir . '/questionlib.php');

/**
 * Import a Moodle XML file with many questions as new versions of existing questions.
 *
 * Each question in the file updates the question with the same ID number in the same question bank
 * (any category of the given context). The whole file is saved in one transaction: either every
 * matched question gets a new version or nothing changes.
 *
 * It extends the XML format only to call the format's protected readdata().
 *
 * @package   qbank_importasversion
 * @copyright 2026 ETH Zurich
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulk_import extends qformat_xml {
    /** The question will get a new version. */
    const READY = 'ready';
    /** The question has no ID number. */
    const NOIDNUMBER = 'noidnumber';
    /** No question in the bank has this ID number. */
    const NOTFOUND = 'notfound';
    /** Several categories in the bank have a question with this ID number. */
    const AMBIGUOUS = 'ambiguous';
    /** The file contains this ID number more than once. */
    const DUPLICATE = 'duplicate';
    /** The existing question has a different question type. */
    const TYPEMISMATCH = 'typemismatch';
    /** The user may not edit the existing question. */
    const NOPERMISSION = 'nopermission';

    /** @var int ID numbers per database query, to stay within every database's parameter limit. */
    const CHUNK = 1000;

    /**
     * Read a file and match its questions, without changing anything.
     *
     * @param context $context the question bank context.
     * @param string $file path of the Moodle XML file.
     * @return stdClass with errors (string[]) and rows (one per question: index, name, idnumber, status,
     *      and for READY rows, target and question).
     */
    public static function plan(context $context, string $file): stdClass {
        $plan = (object) ['errors' => [], 'rows' => []];
        $format = self::new_format($context);
        $questions = self::read($format, $file, $plan->errors);
        if (!$questions) {
            return $plan;
        }

        $idnumbers = [];
        foreach ($questions as $i => $question) {
            $idnumber = trim((string) ($question->idnumber ?? ''));
            $plan->rows[] = (object) [
                'index' => $i + 1,
                'name' => $question->name ?? '',
                'idnumber' => $idnumber,
                'status' => $idnumber === '' ? self::NOIDNUMBER : self::READY,
                'question' => $question,
            ];
            if ($idnumber !== '') {
                $idnumbers[$idnumber] = ($idnumbers[$idnumber] ?? 0) + 1;
            }
        }

        $targets = self::find_targets($context, array_keys($idnumbers));
        foreach ($plan->rows as $row) {
            if ($row->status === self::READY) {
                $row->status = self::check($row, $targets[$row->idnumber] ?? [], $idnumbers[$row->idnumber]);
            }
            if ($row->status !== self::READY) {
                unset($row->question);
            }
        }
        $plan->format = $format;
        return $plan;
    }

    /**
     * Import a file: plan it, then save every ready question as a new version, in one transaction.
     *
     * @param context $context the question bank context.
     * @param string $file path of the Moodle XML file.
     * @param bool $mergetags keep the existing version's tags as well as the file's tags.
     * @param bool $warningsasdraft save questions with save warnings as Draft instead of stopping.
     * @return stdClass the plan, with imported (int) and, if nothing was saved, failed (the row
     *      that stopped the import) and error (string).
     */
    public static function import(context $context, string $file, bool $mergetags, bool $warningsasdraft): stdClass {
        global $DB;

        $plan = self::plan($context, $file);
        $plan->imported = 0;
        if ($plan->errors) {
            return $plan;
        }

        core_php_time_limit::raise();
        $transaction = $DB->start_delegated_transaction();
        foreach ($plan->rows as $row) {
            if ($row->status !== self::READY) {
                continue;
            }
            $result = importer::save_version(
                $plan->format,
                $row->target,
                $row->question,
                $mergetags,
                $warningsasdraft,
                $warningsasdraft
            );
            if (is_object($result) && !empty($result->error)) {
                // The saver has already rolled back the whole transaction.
                $plan->imported = 0;
                $plan->failed = $row;
                $plan->error = $result->error;
                return $plan;
            }
            $row->questionid = end($plan->format->questionids);
            $row->notice = is_object($result) ? ($result->notice ?? '') : '';
            unset($row->question);
            $plan->imported++;
        }
        $transaction->allow_commit();
        return $plan;
    }

    /**
     * Count rows by status.
     *
     * @param array $rows the rows of a plan.
     * @return int[] status => count.
     */
    public static function count(array $rows): array {
        return array_count_values(array_column($rows, 'status'));
    }

    /**
     * Create the format instance used to parse the file.
     *
     * @param context $context the question bank context.
     * @return qformat_xml
     */
    protected static function new_format(context $context): qformat_xml {
        $format = new qformat_xml();
        $format->setContexts([$context]);
        $format->displayprogress = false;
        return $format;
    }

    /**
     * Parse the questions in a file, skipping category entries.
     *
     * @param qformat_xml $format
     * @param string $file
     * @param string[] $errors receives a message when the file cannot be used.
     * @return stdClass[] the questions.
     */
    protected static function read(qformat_xml $format, string $file, array &$errors): array {
        $lines = $format->readdata($file);
        if (!$lines) {
            $errors[] = get_string('cannotread', 'question');
            return [];
        }
        ob_start(); // The XML format prints parse errors as HTML.
        $questions = $format->readquestions($lines) ?: [];
        $output = trim(strip_tags(ob_get_clean()));
        if ($output !== '') {
            $errors[] = $output;
            return [];
        }
        $questions = array_values(array_filter($questions, function ($question) {
            return $question->qtype !== 'category';
        }));
        if (!$questions) {
            $errors[] = get_string('noquestionsinfile', 'question');
        }
        return $questions;
    }

    /**
     * Find the latest version of every question in the bank with one of the given ID numbers.
     *
     * @param context $context the question bank context.
     * @param string[] $idnumbers
     * @return array idnumber => list of matching questions (id, category, contextid,
     *      questionbankentryid, qtype, createdby).
     */
    protected static function find_targets(context $context, array $idnumbers): array {
        global $DB;

        $targets = [];
        foreach (array_chunk($idnumbers, self::CHUNK) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED);
            $params['contextid'] = $context->id;
            $sql = "SELECT q.id, qbe.idnumber, qbe.questioncategoryid AS category, qc.contextid,
                           qbe.id AS questionbankentryid, q.qtype, q.createdby
                      FROM {question_bank_entries} qbe
                      JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                      JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                      JOIN {question} q ON q.id = qv.questionid
                     WHERE qc.contextid = :contextid
                       AND qbe.idnumber $insql
                       AND qv.version = (SELECT MAX(v.version)
                                           FROM {question_versions} v
                                          WHERE v.questionbankentryid = qbe.id)";
            foreach ($DB->get_records_sql($sql, $params) as $target) {
                $targets[$target->idnumber][] = $target;
            }
        }
        return $targets;
    }

    /**
     * Decide whether a row can be imported, and set its target if so.
     *
     * @param stdClass $row
     * @param stdClass[] $matches existing questions with the row's ID number.
     * @param int $occurrences how often the file contains the row's ID number.
     * @return string the row status.
     */
    protected static function check(stdClass $row, array $matches, int $occurrences): string {
        if ($occurrences > 1) {
            return self::DUPLICATE;
        }
        if (!$matches) {
            return self::NOTFOUND;
        }
        if (count($matches) > 1) {
            return self::AMBIGUOUS;
        }
        $target = reset($matches);
        if ($target->qtype !== $row->question->qtype) {
            return self::TYPEMISMATCH;
        }
        if (!question_has_capability_on($target, 'edit')) {
            return self::NOPERMISSION;
        }
        $row->target = $target;
        return self::READY;
    }
}
