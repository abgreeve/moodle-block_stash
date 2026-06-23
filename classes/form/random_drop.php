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
 * Random drop form.
 *
 * @package    block_stash
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_stash\form;

defined('MOODLE_INTERNAL') || die();

use block_stash\drop;

/**
 * Random drop form class.
 *
 * @package    block_stash
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class random_drop extends drop {

    /**
     * Return the drop type this form should submit.
     *
     * @return int
     */
    protected function get_drop_type(): int {
        return drop::TYPE_RANDOM;
    }

    /**
     * Random drops only need a single save action at creation time for now.
     *
     * @return bool
     */
    protected function should_show_save_and_next(): bool {
        return false;
    }
}
