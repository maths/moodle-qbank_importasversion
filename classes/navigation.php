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

use core_question\local\bank\navigation_node_base;
use moodle_url;

/**
 * The entry for the bulk import in the question bank navigation menu.
 *
 * @package   qbank_importasversion
 * @copyright 2026 LT&C
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class navigation extends navigation_node_base {
    #[\Override]
    public function get_navigation_title(): string {
        return get_string('bulkimport', 'qbank_importasversion');
    }

    #[\Override]
    public function get_navigation_key(): string {
        return 'importasversion';
    }

    #[\Override]
    public function get_navigation_url(): moodle_url {
        return new moodle_url('/question/bank/importasversion/bulkimport.php');
    }
}
