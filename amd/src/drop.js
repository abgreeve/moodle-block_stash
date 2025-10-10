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
 * Drop module.
 *
 * @copyright  2016 Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import base from 'block_stash/baseclass';
import Ajax from 'core/ajax';
import Log from 'core/log';
import Item from 'block_stash/item';
import UserItem from 'block_stash/user-item';
import * as PubSub from 'core/pubsub';

export default class drop extends base {

    constructor(dropdata, item) {
        super(dropdata);
        this.item = item;
    }

    getItem() {
        return this.item;
    }

    isVisible() {
        return Ajax.call([{
            methodname: 'block_stash_is_drop_visible',
            args: {
                dropid: this.get('id'),
                hashcode: this.get('hashcode')
            }
        }])[0].then(function(visible) {
            if (!visible) {
                return Promise.reject();
            }
            return true;
        });
    }

    pickup() {
        return Ajax.call([{
            methodname: 'block_stash_pickup_drop',
            args: {
                dropid: this.get('id'),
                hashcode: this.get('hashcode')
            }
        }])[0].fail(function() {
            Log.debug('The item could not be picked up.');

        }).then(function(data) {
            // Do not change this._item as it's not a predictable behaviour.
            var userItem = new UserItem(data.useritem, new Item(data.item));
            PubSub.publish('block_stash/drop/pickedup', {
                id: this.get('id'),
                hashcode: this.get('hashcode'),
                useritem: userItem
            });
        }.bind(this));
    }

}
