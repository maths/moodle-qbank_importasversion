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

use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/questionlib.php');

/**
 * Finds the existing question that a question from a file is a new version of.
 *
 * A question is matched by category and idnumber, using the latest version of each existing question.
 *
 * @package   qbank_importasversion
 * @copyright 2026 LT&C
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_matcher {
    /** @var array[] The latest version of each question with an idnumber, per category id and then idnumber. */
    protected $cache = [];

    /**
     * Find the existing question for a question from the file.
     *
     * @param int $categoryid the question category id.
     * @param string $idnumber the idnumber of the question in the file.
     * @param stdClass $question the question as parsed from the file.
     * @return array [existing question record or null, reason code or null].
     */
    public function find(int $categoryid, string $idnumber, stdClass $question): array {
        $matches = $this->get_entries($categoryid)[$idnumber] ?? [];
        if (!$matches) {
            return [null, bulk_importer::REASON_NOTFOUND];
        }
        if (count($matches) > 1) {
            return [null, bulk_importer::REASON_AMBIGUOUS];
        }
        $existing = reset($matches);
        if ($existing->qtype !== $question->qtype) {
            return [null, bulk_importer::REASON_TYPEMISMATCH];
        }
        if (!question_has_capability_on($existing, 'edit')) {
            return [null, bulk_importer::REASON_NOPERMISSION];
        }
        return [$existing, null];
    }

    /**
     * Get the latest version of each question with an idnumber in a category, keyed by idnumber.
     *
     * Each question has the properties the single-question importer needs: id (of the latest version),
     * category, contextid and questionbankentryid, plus qtype and createdby. Sub-questions are left out.
     *
     * @param int $categoryid the question category id.
     * @return array[] lists of question records, keyed by idnumber.
     */
    protected function get_entries(int $categoryid): array {
        global $DB;

        if (!isset($this->cache[$categoryid])) {
            $sql = "SELECT qbe.id AS questionbankentryid, qbe.idnumber, qbe.questioncategoryid AS category,
                           qc.contextid, qv.questionid AS id, q.qtype, q.createdby
                      FROM {question_bank_entries} qbe
                      JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                      JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                      JOIN {question} q ON q.id = qv.questionid
                     WHERE qbe.questioncategoryid = :categoryid
                           AND qbe.idnumber IS NOT NULL
                           AND q.parent = 0
                           AND qv.version = (SELECT MAX(qv2.version)
                                               FROM {question_versions} qv2
                                              WHERE qv2.questionbankentryid = qbe.id)";
            $entries = [];
            foreach ($DB->get_records_sql($sql, ['categoryid' => $categoryid]) as $entry) {
                $entries[(string) $entry->idnumber][] = $entry;
            }
            $this->cache[$categoryid] = $entries;
        }
        return $this->cache[$categoryid];
    }
}
