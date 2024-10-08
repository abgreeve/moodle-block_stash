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
 * User item module.
 *
 * @copyright  2016 Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'block_stash/base'
], function(Base) {

    /**
     * UserItem class.
     *
     * @param {Object} data Data of the item.
     * @param {Object} item Item information.
     */
    function UserItem(data, item) {
        Base.prototype.constructor.apply(this, [data]);
        this._item = item;
    }
    UserItem.prototype = Object.create(Base.prototype);

    /**
     * Return the item of this user item.
     *
     * @return {Item}
     */
    UserItem.prototype.getItem = function() {
        return this._item;
    };

    return /** @alias module:block_stash/user-item */ UserItem;

});
