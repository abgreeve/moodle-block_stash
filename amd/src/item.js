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
 * Item module.
 *
 * @copyright  2016 Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import base from 'block_stash/baseclass';
import Ajax from 'core/ajax';

export default class Item extends base {

    /**
     * Create a new item instance from raw data.
     *
     * @param {Object} itemdata The raw item data received from the backend.
     */
    constructor(itemdata) {
        super(itemdata);
    }

    /**
     * Fetch an item by ID from the server.
     *
     * @param {number} itemId The item identifier.
     * @return {Promise<Item>} Resolves with the retrieved item instance.
     */
    static getItem(itemId) {
        return Ajax.call([{
            methodname: 'block_stash_get_item',
            args: {
                itemid: itemId
            }
        }])[0].then((data) => {
            return new Item(data);
        });
    }
}
