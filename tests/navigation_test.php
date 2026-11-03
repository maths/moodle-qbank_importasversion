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

/**
 * Tests for the entry of the bulk import in the question bank navigation menu.
 *
 * @package qbank_importasversion
 * @copyright 2026 LT&C
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \qbank_importasversion\navigation
 * @covers \qbank_importasversion\plugin_feature
 */
final class navigation_test extends \advanced_testcase {
    /**
     * The plugin offers the bulk import page as a navigation node, with its title and URL.
     */
    public function test_the_plugin_adds_the_bulk_import_to_the_navigation(): void {
        if (!class_exists('\core_question\local\bank\navigation_node_base')) {
            $this->markTestSkipped('This Moodle version has no navigation nodes for question bank plugins.');
        }

        $node = (new plugin_feature())->get_navigation_node();

        $this->assertInstanceOf(navigation::class, $node);
        $this->assertSame('Import new versions in bulk', $node->get_navigation_title());
        $this->assertSame('/question/bank/importasversion/bulkimport.php', $node->get_navigation_url()->out_as_local_url(false));
    }
}
