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

/**
 * External service for fetching activities to remove items when accessing them.
 *
 * @package    block_stash\external
 * @copyright  2024 Adrian Greeve <adriangreeve.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_stash\external;

use block_stash\manager;
use block_stash\local\stash_elements\removal_helper;

class removal_activities extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT),
        ]);
    }

    public static function execute($courseid) {
        $data = (object) self::validate_parameters(self::execute_parameters(), compact('courseid'));

        $manager = manager::get($data->courseid);
        self::validate_context($manager->get_context());
        if (!$manager->can_manage()) {
            throw new \moodle_exception('invalidaccess');
        }

        $removalhelper = new removal_helper($manager);

        $thing = [];
        foreach ($removalhelper->get_quizzes_for_course() as $key => $value) {
            $thing[] = [
                'id' => $key,
                'name' => $value,
            ];
        }

        $tmep = ['activities' => $thing, 'warnings' => []];
        return $tmep;
    }

    public static function execute_returns() {
        return new external_single_structure([
            'activities' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'The name of the activity', VALUE_OPTIONAL),
                    'name' => new external_value(PARAM_TEXT, 'The name of the activity', VALUE_OPTIONAL),
                ])
            ),
            'warnings' => new external_warnings(),
        ]);
    }
}
