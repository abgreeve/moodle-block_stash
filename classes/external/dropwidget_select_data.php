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
 * External drop widget stuff.
 *
 * @package    block_stash
 * @copyright  2023 Adrian Greeve <adriangreeve.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_stash\external;

require_once("$CFG->libdir/externallib.php");

use context_course;
use context_user;
use coding_exception;
use external_api;
use external_function_parameters;
use external_value;
use external_format_value;
use external_single_structure;
use external_multiple_structure;
use stdClass;

use block_stash\external\item_exporter;
use block_stash\manager;
use block_stash\external\user_item_summary_exporter;
use block_stash\external\trade_items_exporter;
use block_stash\external\trade_summary_exporter;
use block_stash\external\trade_exporter;
use block_stash\external\items_exporter;

/**
 * External API class.
 *
 * @package    block_stash
 * @copyright  2023 Adrian Greeve <adriangreeve.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dropwidget_select_data extends external_api {

    /**
     * External function parameter structure.
     *
     * @return external_function_parameters
     */
    public static function get_all_drop_data_parameters() {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT)
        ]);
    }

    public static function get_all_drop_data($contextid) {
        global $PAGE;

        $params = self::validate_parameters(self::get_all_drop_data_parameters(), compact('contextid'));

        $context = \context::instance_by_id($params['contextid']);

        // If the context is a module then find the course context.
        if ($context->contextlevel == CONTEXT_MODULE) {
            $courseid = $context->get_parent_context()->instanceid;
            // print_object($context->get_parent_context());
        } else {
            $courseid = $context->instanceid;
        } // Could be some other context. We should handle that.

        $manager = manager::get($courseid);
        self::validate_context($manager->get_context());
        $result = $manager->get_drops_for_items_and_trade();

        // $output = $PAGE->get_renderer('block_stash');
        // $records = [];
        // foreach ($result['trades'] as $trade) {
        //     $exporter = new trade_exporter($trade, ['context' => $manager->get_context()]);
        //     $records[] = $exporter->export($output);
        // }
        return ['items' => array_values($result['items']), 'trades' => $manager->get_all_trade_data()];
    }

    public static function get_all_drop_data_returns() {
        return new external_single_structure(
            [
                'items' => new external_multiple_structure(new external_single_structure(
                    [
                        'id' => new external_value(PARAM_INT, 'The drop ID'),
                        'name' => new external_value(PARAM_TEXT, 'The name of the item'),
                        'hashcode' => new external_value(PARAM_ALPHANUM, 'The hashcode for the drop'),
                        'itemid' => new external_value(PARAM_INT, 'The item ID'),
                        'location' => new external_value(PARAM_TEXT, 'The location of the item')
                    ]
                )),
                'trades' => new external_multiple_structure(new external_single_structure(
                    [
                        'tradeid' => new external_value(PARAM_INT, 'The trade ID'),
                        'name' => new external_value(PARAM_TEXT, 'The name of the trade'),
                        'losstitle' => new external_value(PARAM_TEXT, 'The title for losing items'),
                        'gaintitle' => new external_value(PARAM_TEXT, 'The title for gaining items'),
                        'hashcode' => new external_value(PARAM_ALPHANUM, 'The hashcode for the trade'),
                        'additems' => new external_multiple_structure (
                            new external_single_structure(
                                [
                                    'itemid' => new external_value(PARAM_INT),
                                    'quantity' => new external_value(PARAM_INT)
                                ]
                            )
                        ),
                        'lossitems' => new external_multiple_structure (
                            new external_single_structure(
                                [
                                    'itemid' => new external_value(PARAM_INT),
                                    'quantity' => new external_value(PARAM_INT)
                                ]
                            )
                        ),
                    ]
                )),
            ]
        );
    }
}
