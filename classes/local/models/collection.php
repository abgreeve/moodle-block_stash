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
 * Collection model
 *
 * @package    block_stash
 * @copyright  2024 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_stash\local\models;

/**
 * Collection model
 *
 * @package    block_stash
 * @copyright  2024 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class collection {

    private $id;
    private $stashid;
    private $name;
    private $showtostudent;
    private $removeoncompletion;

    public function __construct($stashid, $name, $showtostudent, $removeoncompletion, $id = null) {
        $this->id = $id;
        $this->stashid = $stashid;
        $this->name = $name;
        $this->showtostudent = $showtostudent;
        $this->removeoncompletion = $removeoncompletion;
    }

    public function set_id($id) {
        $this->id = $id;
    }

    public function get_id() {
        return $this->id;
    }

    public function show_to_student() {
        return ($this->showtostudent);
    }

    public function get_name() {
        return $this->name;
    }

    public function to_array() {
        return [
            'stashid' => $this->stashid,
            'name' => $this->name,
            'showtostudent' => $this->showtostudent,
            'removeoncompletion' => $this->removeoncompletion,
        ];
    }
}
