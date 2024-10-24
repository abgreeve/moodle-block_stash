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
 * External service for retrieving collection information.
 *
 * @package    block_stash\external
 * @copyright  2024 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_stash\external;

use block_stash\manager;
use block_stash\local\stash_elements\collection_manager;
use block_stash\external\item_exporter;

class collection_data extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT),
        ]);
    }

    public static function execute($courseid) {
        $data = (object) self::validate_parameters(self::execute_parameters(),
            compact('courseid'));

        $manager = manager::get($data->courseid);
        self::validate_context($manager->get_context());

        // Get all of the collection data.
        $collectionmanager = collection_manager::init($manager);
        $data = $collectionmanager->get_collections_with_items();

        return $data;
    }

    public static function execute_returns() {
        return new external_multiple_structure((new external_single_structure(
            [
                'collection' => new external_single_structure(
                    [
                        'id' => new external_value(PARAM_INT, 'The collection ID'),
                        'name' => new external_value(PARAM_TEXT, 'The name of the collection'),
                        'stashid' => new external_value(PARAM_INT, 'The stash ID'),
                        'showtostudent' => new external_value(PARAM_INT, 'Is this collection shown to students'),
                        'removeoncompletion' => new external_value(PARAM_INT, 'Will the items be removed on completion')
                    ]
                ),
                'items' => new external_multiple_structure(
                    item_exporter::get_read_structure()
                ),
            ]
        )));
    }
}
