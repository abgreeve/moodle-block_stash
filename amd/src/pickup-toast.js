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
 * Award confirmation toast.
 *
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {add as addToast} from 'core/toast';
import {get_string as getString} from 'core/str';

const getMessage = (item, quantityAwarded, singularKey, pluralKey) => {
    if (parseInt(quantityAwarded, 10) > 1) {
        return getString(pluralKey, 'block_stash', `${quantityAwarded} × ${item.name}`);
    }

    return getString(singularKey, 'block_stash', item.name);
};

export const showPickup = (item, quantityAwarded) => addToast(getMessage(item, quantityAwarded,
    'pickuptoastfound', 'pickuptoastreceived'), {
    type: 'success'
});

export const showTrade = (item, quantityAwarded) => addToast(getMessage(item, quantityAwarded,
    'tradetoastreceivedsingle', 'tradetoastreceivedmultiple'), {
    type: 'success'
});
