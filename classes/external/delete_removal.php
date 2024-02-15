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
 * External service for deleting a removal entry.
 *
 * @package    block_stash\external
 * @copyright  2024 Adrian Greeve <adriangreeve.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_stash\external;

use block_stash\manager;
use block_stash\local\stash_elements\removal_helper;

class delete_removal extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT),
            'removalid' => new external_value(PARAM_INT),
        ]);
    }

    public static function execute($courseid, $removalid) {
        $data = (object) self::validate_parameters(self::execute_parameters(), compact('courseid', 'removalid'));

        $manager = manager::get($data->courseid);
        self::validate_context($manager->get_context());
        if (!$manager->can_manage()) {
            throw new \moodle_exception('invalidaccess');
        }

        $removalhelper = new removal_helper($manager);

        $removalhelper->delete_removal_configuration($data->removalid);

        return true;
    }

    public static function execute_returns() {
        return new external_value(PARAM_BOOL);
    }
}
