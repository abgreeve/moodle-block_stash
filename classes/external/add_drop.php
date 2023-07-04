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
 * External service for creating a new drop
 *
 * @package    block_stash\external
 * @copyright  2023 Adrian Greeve <adriangreeve.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_stash\external;

require_once("$CFG->libdir/externallib.php");

use external_api;
use external_function_parameters;
use external_value;
use block_stash\manager;


class add_drop extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT),
            'itemid' => new external_value(PARAM_INT),
            'name' => new external_value(PARAM_NOTAGS),
            'maxpickup' => new external_value(PARAM_INT),
            'pickupinterval' => new external_value(PARAM_INT),
        ]);
    }

    public static function execute($courseid, $itemid, $name, $maxpickup, $pickupinterval) {
        $data = (object) self::validate_parameters(self::execute_parameters(),
            compact('courseid', 'itemid', 'name', 'maxpickup', 'pickupinterval'));

        $manager = manager::get($data->courseid);
        self::validate_context($manager->get_context());

        if ($data->maxpickup == 0) {
            $data->maxpickup = null;
        }
        $data->id = 0;
        unset($data->courseid);

        $manager->create_or_update_drop($data);
        return true;
    }

    public static function execute_returns() {
        return new external_value(PARAM_BOOL);
    }
}
