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
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/questionlib.php');

/**
 * Finds the question category for a category path from a Moodle XML file, without creating it.
 *
 * Follows create_category_path() in the core question format, but only looks.
 *
 * @package   qbank_importasversion
 * @copyright 2026 LT&C
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class category_finder {
    /** @var context The context to look in when the path names none, or "context from file" is off. */
    protected $defaultcontext;

    /** @var bool Whether the context in the path is used. */
    protected $contextfromfile;

    /** @var \core_question\local\bank\context_to_string_translator|null Translates $course$ and so on to contexts. */
    protected $translator;

    /**
     * Constructor.
     *
     * @param context $defaultcontext the context to look in when the path names none.
     * @param bool $contextfromfile whether to use the context named in the path.
     * @param \core_question\local\bank\context_to_string_translator|null $translator translates context names.
     */
    public function __construct(context $defaultcontext, bool $contextfromfile, $translator) {
        $this->defaultcontext = $defaultcontext;
        $this->contextfromfile = $contextfromfile;
        $this->translator = $translator;
    }

    /**
     * Find a category.
     *
     * @param string[] $catnames the names in the path, like $course$, top, parent, child.
     * @return array [category record or null, reason code or null].
     */
    public function find(array $catnames): array {
        [$catnames, $contextid] = $this->split_context($catnames);
        $context = $this->get_context($contextid);
        if (!$context) {
            return [null, bulk_importer::REASON_CATEGORYNOTFOUND];
        }
        return $this->walk($this->with_top($catnames), $context);
    }

    /**
     * The path of a category as shown to the user: the names from the top down, without the top category.
     *
     * @param stdClass $category the category record.
     * @return string the path, like "Parent / Child".
     */
    public function get_path(stdClass $category): string {
        global $DB;

        $names = [];
        while ($category && $category->parent) {
            $names[] = $category->name;
            $category = $DB->get_record('question_categories', ['id' => $category->parent], 'id, name, parent');
        }
        return implode(' / ', array_reverse($names));
    }

    /**
     * Take the context name, like $course$, off the front of the path.
     *
     * @param string[] $catnames the names in the path.
     * @return array [the names without the context name, the context id or false].
     */
    protected function split_context(array $catnames): array {
        $contextid = false;
        if (preg_match('/^\$([a-z]+)\$$/', $catnames[0], $matches)) {
            $contextid = $this->translator->string_to_context($matches[1]);
            array_shift($catnames);
        }
        return [$catnames, $contextid];
    }

    /**
     * Make sure the path starts with the top category, as it does from Moodle 3.5 on.
     *
     * @param string[] $catnames the names in the path.
     * @return string[] the names, starting with top.
     */
    protected function with_top(array $catnames): array {
        if (isset($catnames[0]) && (($catnames[0] != 'top') || (count($catnames) < 3))) {
            array_unshift($catnames, 'top');
        }
        return $catnames;
    }

    /**
     * Get the context to look in.
     *
     * @param int|false $contextid the context id from the path, or false if there is none.
     * @return context|null the context, or null if the path names a context that does not exist.
     */
    protected function get_context($contextid): ?context {
        if ($this->contextfromfile && $contextid !== false) {
            return context::instance_by_id($contextid, IGNORE_MISSING) ?: null;
        }
        return $this->defaultcontext;
    }

    /**
     * Follow the path from the top category down.
     *
     * @param string[] $catnames the names in the path, starting with top.
     * @param context $context the context to look in.
     * @return array [category record or null, reason code or null].
     */
    protected function walk(array $catnames, context $context): array {
        $category = question_get_top_category($context->id, false);
        if (!$category) {
            return [null, bulk_importer::REASON_CATEGORYNOTFOUND];
        }
        // The first name is the top category itself.
        array_shift($catnames);

        foreach ($catnames as $catname) {
            [$child, $reason] = $this->find_child($catname, $context->id, $category->id);
            if ($child) {
                $category = $child;
            } else if ($reason !== bulk_importer::REASON_CATEGORYNOTFOUND || $catname != 'top') {
                return [null, $reason];
            }
            // Otherwise: an extra nested top category from an old export. Core skips these too.
        }

        // The top category itself holds no questions.
        if ($category->parent == 0) {
            return [null, bulk_importer::REASON_CATEGORYNOTFOUND];
        }
        return [$category, null];
    }

    /**
     * Find a category by name below a parent.
     *
     * @param string $name the category name.
     * @param int $contextid the context id.
     * @param int $parentid the parent category id.
     * @return array [category record or null, reason code or null].
     */
    protected function find_child(string $name, int $contextid, int $parentid): array {
        global $DB;

        $found = $DB->get_records(
            'question_categories',
            ['name' => $name, 'contextid' => $contextid, 'parent' => $parentid],
            '',
            '*',
            0,
            2
        );
        if (!$found) {
            return [null, bulk_importer::REASON_CATEGORYNOTFOUND];
        }
        if (count($found) > 1) {
            return [null, bulk_importer::REASON_CATEGORYAMBIGUOUS];
        }
        return [reset($found), null];
    }
}
