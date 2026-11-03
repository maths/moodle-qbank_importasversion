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

use html_table;
use html_writer;
use stdClass;

/**
 * Builds the HTML for the result of a bulk import: a summary and a table with one row per question.
 *
 * Everything that comes from the file or from the database is escaped here.
 *
 * @package   qbank_importasversion
 * @copyright 2026 LT&C
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class results_renderer {
    /**
     * The summary line, announced to screen readers as a status.
     *
     * @param stdClass[] $rows the rows from bulk_importer::run().
     * @param bool $dryrun true for the dry run, false after the import.
     * @return string HTML.
     */
    public static function summary(array $rows, bool $dryrun): string {
        $counts = (object) bulk_importer::count_outcomes($rows);
        if ($dryrun) {
            if ($counts->newversion == 0) {
                return html_writer::div(
                    get_string('nothingtoimport', 'qbank_importasversion'),
                    'alert alert-warning',
                    ['role' => 'status']
                );
            }
            return html_writer::div(
                get_string('summarydryrun', 'qbank_importasversion', $counts),
                'alert alert-info',
                ['role' => 'status']
            );
        }

        $failed = $counts->error > 0;
        return html_writer::div(
            get_string('summaryresult', 'qbank_importasversion', $counts),
            'alert ' . ($failed ? 'alert-danger' : 'alert-success'),
            ['role' => $failed ? 'alert' : 'status']
        );
    }

    /**
     * The table with the outcome for each question.
     *
     * @param stdClass[] $rows the rows from bulk_importer::run().
     * @param string $caption the caption of the table.
     * @return string HTML.
     */
    public static function table(array $rows, string $caption): string {
        $table = new html_table();
        $table->caption = $caption;
        $table->attributes['class'] = 'generaltable table-sm';
        $table->head = [
            get_string('columnnumber', 'qbank_importasversion'),
            get_string('columnname', 'qbank_importasversion'),
            get_string('columncategory', 'qbank_importasversion'),
            get_string('columnidnumber', 'qbank_importasversion'),
            get_string('columnqtype', 'qbank_importasversion'),
            get_string('columnoutcome', 'qbank_importasversion'),
            get_string('columnreason', 'qbank_importasversion'),
        ];
        foreach ($rows as $row) {
            $table->data[] = [
                (int) $row->index,
                s($row->name),
                s($row->category),
                s($row->idnumber),
                s($row->qtype),
                s(get_string('outcome_' . $row->outcome, 'qbank_importasversion')),
                self::reason($row),
            ];
        }
        return html_writer::table($table);
    }

    /**
     * The reason text of a row: why it was skipped, and any message from saving it.
     *
     * @param stdClass $row the row.
     * @return string HTML.
     */
    protected static function reason(stdClass $row): string {
        $parts = [];
        if ($row->reason) {
            $parts[] = s(get_string('reason_' . $row->reason, 'qbank_importasversion'));
        }
        if ($row->message !== '') {
            $parts[] = s($row->message);
        }
        return implode(' ', $parts);
    }
}
