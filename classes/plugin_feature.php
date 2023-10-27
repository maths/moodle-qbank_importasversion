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

use core_question\local\bank\plugin_features_base;
use core_question\local\bank\view;

/**
 * Information about which features are provided by this plugin.
 *
 * @package   qbank_importasversion
 * @copyright 2023 MootDACH DevCamp
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugin_feature extends plugin_features_base {

    public function get_question_actions(view $qbank): array {
        // This is what is used in Moodle 4.3+.
        return [
            new import_as_version_action_column($qbank),
        ];
    }

    // Support multiple Moodle versions. This method can be removed once 4.3 is the lowest supported version.
    public function get_question_columns($qbank): array {
        if (class_exists('\core_question\local\bank\question_action_base')) {
            // We are in Moodle 4.3+. We don't need to implement this method.
            return [];
        }

        // Moodle up to 4.2.x.
        return [
            new import_as_version_action_column($qbank),
        ];
    }
}
