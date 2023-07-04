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
 * External service for creating a new trade
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
use external_multiple_structure;
use external_single_structure;
use stdClass;
use block_stash\manager;

class create_trade extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT),
            'stashid' => new external_value(PARAM_INT),
            'hashcode' => new external_value(PARAM_ALPHANUM),
            'title' => new external_value(PARAM_TEXT),
            'gain' => new external_value(PARAM_TEXT),
            'loss' => new external_value(PARAM_TEXT),
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
        ]);
    }

    /**
     * Create a new trade
     *
     * @param int $courseid The course id
     * @param int $stashid The stash id
     * @param string $hashcode The hashcode of the trade
     * @param string $title The title of the trade
     * @param string $gain The gain title
     * @param string $loss The loss title
     * @param array $additems The items to be added
     * @param array $lossitems The items to be lost
     * @return string hashcode of the new trade
     */
    public static function execute(int $courseid, int $stashid, string $hashcode, string $title, string $gain, string $loss,
            array $additems, array $lossitems): string {
        $info = self::validate_parameters(self::execute_parameters(),
            compact('courseid','stashid','hashcode','title','gain','loss','additems','lossitems'));

        $manager = manager::get($info['courseid']);
        self::validate_context($manager->get_context());

        $data = new stdClass();
        $data->id = 0;
        $data->name = $info['title'];
        $data->gaintitle = !empty($info['gain']) ? $info['gain'] : get_string('gain', 'block_stash') ;
        $data->losstitle = !empty($info['loss']) ? $info['loss'] : get_string('loss', 'block_stash') ;
        $data->hashcode = $info['hashcode'];
        $data->stashid = $info['stashid'];

        $trade = $manager->create_or_update_trade($data);

        foreach ($info['additems'] as $item) {
            $data = new stdClass();
            $data->tradeid = $trade->get_id();
            $data->itemid = $item['itemid'];
            $data->quantity = ($item['quantity'] <= 1) ? 1 : $item['quantity'];
            $data->gainloss = true;
            $manager->create_or_update_tradeitem($data);
        }

        foreach ($info['lossitems'] as $item) {
            $data = new stdClass();
            $data->tradeid = $trade->get_id();
            $data->itemid = $item['itemid'];
            $data->quantity = ($item['quantity'] <= 1) ? 1 : $item['quantity'];
            $data->gainloss = false;
            $manager->create_or_update_tradeitem($data);
        }

        return $trade->get_hashcode();
    }

    public static function execute_returns() {
        return new external_value(PARAM_ALPHANUM);
    }
}
