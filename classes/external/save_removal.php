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
 * External service for saving a removal entry.
 *
 * @package    block_stash\external
 * @copyright  2024 Adrian Greeve <adriangreeve.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_stash\external;

use block_stash\manager;
use block_stash\local\stash_elements\removal_helper;

class save_removal extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT),
            'cmid' => new external_value(PARAM_INT),
            'items' => new external_multiple_structure(
                new external_single_structure([
                    'itemid' => new external_value(PARAM_INT),
                    'quantity' => new external_value(PARAM_INT),
                ])
            ),
        ]);
    }

    public static function execute($courseid, $cmid, $items) {
        $data = (object) self::validate_parameters(self::execute_parameters(), compact('courseid', 'cmid', 'items'));

        $manager = manager::get($data->courseid);
        self::validate_context($manager->get_context());
        if (!$manager->can_manage()) {
            throw new \moodle_exception('invalidaccess');
        }

        $removalhelper = new removal_helper($manager);

        $formdata = (object) [
            'quizcmid' => $data->cmid,
            'detail_editor' => ['text' => 'Placeholder for possible text', 'format' => 1],
            'items' => $data->items
        ];

        $removalhelper->handle_form_data($formdata);
        return true;

    }

    public static function execute_returns() {
        return new external_value(PARAM_BOOL);
    }
}
