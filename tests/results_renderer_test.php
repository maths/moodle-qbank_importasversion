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

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/question/format/xml/format.php');

/**
 * Tests for the result of a bulk import: strings, escaping and accessibility markup.
 *
 * @package qbank_importasversion
 * @copyright 2026 LT&C
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \qbank_importasversion\results_renderer
 */
final class results_renderer_test extends \advanced_testcase {
    /**
     * Create a row like bulk_importer::run() does.
     *
     * @param string $outcome the outcome.
     * @param string|null $reason the reason, if skipped.
     * @param string $name the question name.
     * @param string $message the message from saving.
     * @return \stdClass the row.
     */
    private function create_row(
        string $outcome,
        ?string $reason = null,
        string $name = 'Question',
        string $message = ''
    ): \stdClass {
        return (object) [
            'index' => 1,
            'name' => $name,
            'category' => 'Category',
            'idnumber' => 'ID-1',
            'qtype' => 'truefalse',
            'outcome' => $outcome,
            'reason' => $reason,
            'message' => $message,
            'questionid' => null,
        ];
    }

    /**
     * Every outcome and reason has a language string, so the table never shows a missing-string marker.
     */
    public function test_every_outcome_and_reason_has_a_string(): void {
        $constants = (new \ReflectionClass(bulk_importer::class))->getConstants();
        $checked = 0;
        foreach ($constants as $name => $value) {
            $prefix = strtolower(strtok($name, '_'));
            if (in_array($prefix, ['outcome', 'reason'])) {
                $this->assertTrue(
                    get_string_manager()->string_exists($prefix . '_' . $value, 'qbank_importasversion'),
                    "Missing string {$prefix}_{$value}"
                );
                $checked++;
            }
        }
        // 4 outcomes and 10 reasons: fails if the constants are not found at all.
        $this->assertSame(14, $checked);
    }

    /**
     * The table has a caption and column headers, and escapes everything that comes from the file.
     */
    public function test_table_has_caption_and_headers_and_escapes_text(): void {
        $row = $this->create_row(
            bulk_importer::OUTCOME_ERROR,
            bulk_importer::REASON_NOTFOUND,
            '<b>Name</b> & <script>alert(1)</script>',
            '<img src=x onerror=alert(1)>'
        );

        $html = results_renderer::table([$row], 'My caption');

        $this->assertStringContainsString('<caption', $html);
        $this->assertStringContainsString('My caption', $html);
        $this->assertSame(7, substr_count($html, 'scope="col"'));
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<b>Name</b>', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('Failed', $html);
    }

    /**
     * The dry run summary is a status, and says so when there is nothing to import.
     */
    public function test_dry_run_summary_is_a_status(): void {
        $newversion = $this->create_row(bulk_importer::OUTCOME_NEWVERSION);
        $skipped = $this->create_row(bulk_importer::OUTCOME_SKIPPED, bulk_importer::REASON_NOIDNUMBER);

        $html = results_renderer::summary([$newversion, $skipped], true);
        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString('1 would be imported as a new version, 1 would be skipped.', $html);

        $html = results_renderer::summary([$skipped], true);
        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString('No question in the file can be imported as a new version.', $html);
    }

    /**
     * After the import, a failure is an alert and otherwise the summary is a status.
     */
    public function test_result_summary_reports_failure_as_alert(): void {
        $imported = $this->create_row(bulk_importer::OUTCOME_IMPORTED);
        $failed = $this->create_row(bulk_importer::OUTCOME_ERROR);

        $html = results_renderer::summary([$imported], false);
        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString('1 imported as a new version, 0 skipped, 0 failed.', $html);

        $html = results_renderer::summary([$imported, $failed], false);
        $this->assertStringContainsString('role="alert"', $html);
        $this->assertStringContainsString('1 failed.', $html);
    }
}
