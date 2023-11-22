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
 * External service to add an item to a user's stash.
 *
 * @package    block_stash\external
 * @copyright  2023 Adrian Greeve <adriangreeve.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_stash\external;

class add_user_item extends external_api {

    /**
     * External function parameter structure.
     * @return external_function_parameters
     */
    public static function add_user_item_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT),
            'itemid' => new external_value(PARAM_INT),
            'userid' => new external_value(PARAM_INT),
            'quantity' => new external_value(PARAM_INT),
        ]);
    }

    /**
     * Add an item to a user's stash.
     *
     * @param int $courseid The course ID.
     * @param int $itemid The item ID.
     * @param int $userid The user ID.
     * @param int $quantity The quantity to add.
     * @return bool True
     */
    public static function add_user_item(int $courseid, int $itemid, int $userid, int $quantity) {
        $params = self::validate_parameters(self::add_user_item_parameters(), compact('courseid', 'itemid', 'userid', 'quantity'));
        $courseid = $params['courseid'];
        $itemid = $params['itemid'];
        $userid = $params['userid'];
        $quantity = $params['quantity'];

        $manager = \block_stash\manager::get($courseid);
        self::validate_context($manager->get_context());

        // It is better to create a second webservice for this instead of muddying this code. TODO fix.
        if ($quantity == 0) {
            $manager->reset_user_item($userid, $itemid);
        } else {
            $manager->update_user_item_amount($itemid, $userid, $quantity);
        }

        return true;
    }

    /**
     * External function return structure.
     * @return external_value
     */
    public static function add_user_item_returns() {
        return new external_value(PARAM_BOOL);
    }
}
