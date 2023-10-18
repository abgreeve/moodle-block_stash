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
 * Leaderboard settings output
 *
 * @package    block_stash\output\local\main_pages
 * @copyright  2023 Adrian Greeve <adriangreeve.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_stash\output\local\main_pages;

use renderable;
use renderer_base;
use templatable;
use html_writer;

class leaderboard_content implements renderable, templatable {

    private $manager;

    public function __construct($manager) {
        $this->manager = $manager;
    }

    public function export_for_template(renderer_base $output) {
        $settingsenabled = $this->manager->leaderboard_enabled();
        $lbgroups = $this->manager->leaderboard_groups_enabled();

        $boards = $this->manager->get_leaderboards();
        $boardsettings = $this->manager->get_leaderboard_settings();
        $courseid = $this->manager->get_courseid();

        $data = (object) ['courseid' => $courseid, 'lbenabled' => $settingsenabled, 'lbgroups' => $lbgroups, 'boards' => []];
        foreach($boards as $key => $board) {
            $active = false;
            $rowlimit = 5;
            foreach($boardsettings as $boardvalues) {
                if ($boardvalues->boardname == $key) {
                    $active = true;
                    $rowlimit = $boardvalues->rowlimit;
                }
            }
            $id = html_writer::random_id();
            $data->boards[] = [
                'id' => $id,
                'location' => $key,
                'title' => $board->get_title(),
                'optionshtml' => method_exists($board, 'options_html') ? $board->options_html($id) : '',
                'active' => $active,
                'rowlimit' => $rowlimit
            ];
        }
        return $data;
    }
}
