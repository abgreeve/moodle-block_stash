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
 * External service for creating a new item
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
use block_stash\external\item_exporter;


class add_item extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT),
            'itemname' => new external_value(PARAM_TEXT),
            'scarceitem' => new external_value(PARAM_BOOL),
            'amountlimit' => new external_value(PARAM_INT),
            'itemimage' => new external_value(PARAM_INT),
            'description' => new external_value(PARAM_TEXT),
        ]);
    }


    public static function execute($courseid, $itemname, $scarceitem, $amountlimit, $itemimage, $description) {
        global $PAGE;

        $data = (object) self::validate_parameters(self::execute_parameters(),
            compact('courseid', 'itemname', 'scarceitem', 'amountlimit', 'itemimage', 'description'));

        $manager = manager::get($data->courseid);
        self::validate_context($manager->get_context());

        $itemdata = (object) [
            'stashid' => $manager->get_stash()->get_id(),
            'name' => $data->itemname,
            'scarceitem' => $data->scarceitem,
            'detail' => $data->description,
            'detailformat' => 1,
            'fileareaoptions' => ['maxfiles' => 1],
            'detail_editor' => ['text' => $data->description, 'format' => 1],
            'editoroptions' => [],
            'amountlimit' => $data->amountlimit,
        ];

        $item = $manager->create_or_update_item($itemdata, $data->itemimage);

        $output = $PAGE->get_renderer('block_stash');
        $exporter = new item_exporter($item, array('context' => $manager->get_context()));
        $record = $exporter->export($output);
        // TODO Formatting of the details should be done in the exporter.
        $record->detail = file_rewrite_pluginfile_urls($record->detail, 'pluginfile.php', $manager->get_context()->id,
                'block_stash', 'detail', $item->get_id());

        return $record;
    }

    public static function execute_returns() {
        return item_exporter::get_read_structure();
    }
}
