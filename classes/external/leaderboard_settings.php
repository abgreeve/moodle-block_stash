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
 * External service for leaderboard settings
 *
 * @package    block_stash\external
 * @copyright  2023 Adrian Greeve <adriangreeve.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_stash\external;

use block_stash\manager;


class leaderboard_settings extends external_api {

    public static function update_block_setting_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT),
            'key' => new external_value(PARAM_ALPHANUMEXT),
            'value' => new external_value(PARAM_BOOL),
        ]);
    }

    public static function update_block_setting($courseid, $key, $value) {
        $data = (object) self::validate_parameters(self::update_block_setting_parameters(),
            compact('courseid', 'key', 'value'));

        $manager = manager::get($data->courseid);
        $context = $manager->get_context();
        self::validate_context($context);

        return $manager->set_config_entry($data->key, $data->value);
    }

    public static function update_block_setting_returns() {
        return new external_value(PARAM_BOOL);
    }

    public static function update_leaderboard_setting_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT),
            'boardname' => new external_value(PARAM_RAW), // Change to something better.
            'options' => new external_value(PARAM_ALPHANUMEXT),
            'sortorder' => new external_value(PARAM_ALPHANUMEXT),
            'rowlimit' => new external_value(PARAM_INT),
            'enabled' => new external_value(PARAM_BOOL),
        ]);
    }

    public static function update_leaderboard_setting($courseid, $boardname, $options, $sortorder, $rowlimit, $enabled) {
        $data = (object) self::validate_parameters(self::update_leaderboard_setting_parameters(),
            compact('courseid', 'boardname', 'options', 'sortorder', 'rowlimit', 'enabled'));

        $manager = manager::get($data->courseid);
        $context = $manager->get_context();
        self::validate_context($context);

        if (!$data->enabled) {
            $manager->delete_leaderboard_settings($data->boardname);
            return true;
        }

        $data = (object) [
            'stashid' => $manager->get_stash()->get_id(),
            'boardname' => $data->boardname,
            'options' => $data->options,
            'sortorder' => $data->sortorder,
            'rowlimit' => $data->rowlimit
        ];

        $manager->set_leaderboard_settings($data);

        return true;
    }

    public static function update_leaderboard_setting_returns() {
        return new external_value(PARAM_BOOL);
    }

}
