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
 * Random drop pool selector.
 *
 * @package    block_stash
 * @copyright  2026 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_stash;
defined('MOODLE_INTERNAL') || die();

use coding_exception;

/**
 * Selects one random pool entry using weighted probability.
 *
 * This class only performs in-memory selection from the provided pool items.
 * It does not query storage or mutate the pool.
 *
 * @package    block_stash
 * @copyright  2026 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class random_drop_pool_selector {

    /**
     * Select one pool entry using its configured weight.
     *
     * @param drop_pool_item[] $poolitems Pool entries to select from.
     * @return drop_pool_item
     */
    public function select(array $poolitems): drop_pool_item {
        if (empty($poolitems)) {
            throw new coding_exception('Pool items are required.');
        }

        $totalweight = 0;
        foreach ($poolitems as $poolitem) {
            $weight = $poolitem->get_weight();
            if ($weight <= 0) {
                throw new coding_exception('Pool item weights must be positive integers.');
            }
            $totalweight += $weight;
        }

        if ($totalweight <= 0) {
            throw new coding_exception('The total pool weight must be positive.');
        }

        $target = random_int(1, $totalweight);
        $runningtotal = 0;

        foreach ($poolitems as $poolitem) {
            $runningtotal += $poolitem->get_weight();
            if ($target <= $runningtotal) {
                return $poolitem;
            }
        }

        throw new coding_exception('Unable to select a pool item.');
    }
}
