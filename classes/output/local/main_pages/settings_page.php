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
 * General settings output
 *
 * @package    block_stash\output\local\main_pages
 * @copyright  2025 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_stash\output\local\main_pages;

use renderable;
use renderer_base;
use templatable;
use moodle_url;
use confirm_action;
use action_link;
use sesskey;

class settings_page implements renderable, templatable {

    private $courseid;

    public function __construct($courseid) {
        $this->courseid = $courseid;
    }

     public function export_for_template(renderer_base $output) {

        $action = new confirm_action(get_string('reallyresetusersitems', 'block_stash'));
        $url = new moodle_url('/blocks/stash/settings.php', ['courseid' => $this->courseid, 'delete' => 1, 'sesskey' => sesskey()]);
        $attributes = ['class' => 'btn btn-primary'];
        $resetuserlink = new action_link($url, get_string('resetallusersitems', 'block_stash'), $action, $attributes);
        $actionlink = $resetuserlink->export_for_template($output);
        return (object) [
            'link' => $actionlink
        ];
     }

}
