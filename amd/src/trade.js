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
 * Trade module.
 *
 * @copyright  2017 Adrian Greeve - adriangreeve.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import base from 'block_stash/baseclass';
import Ajax from 'core/ajax';
import Log from 'core/log';
import Item from 'block_stash/item';
import UserItem from 'block_stash/user-item';
import * as PubSub from 'core/pubsub';

export default class Trade extends base {

    constructor(tradedata) {
        super(tradedata);
        this.EVENT_TRADE = 'trade:pickedup';
    }

    do() {
        return Ajax.call([{
            methodname: 'block_stash_complete_trade',
            args: {
                tradeid: this.get('id'),
                hashcode: this.get('hashcode')
            }
        }])[0].fail(function() {
            Log.debug('The trade could not be completed.');
        }).then(function(data) {

            // Notify other areas about item removal and acquirement.
            if (data) {
                for (var index in data.gaineditems) {

                    var userItem = new UserItem(data.gaineditems[index].useritem, new Item(data.gaineditems[index].item));
                    PubSub.publish(this.EVENT_TRADE, {
                        id: this.get('id'),
                        hashcode: this.get('hashcode'),
                        useritem: userItem
                    });
                }

                for (var index in data.removeditems) {
                    var userItem = new UserItem(data.removeditems[index].useritem, new Item(data.removeditems[index].item));
                    PubSub.publish(this.EVENT_TRADE, {
                        id: this.get('id'),
                        hashcode: this.get('hashcode'),
                        useritem: userItem
                    });
                }
            }

        }.bind(this));
    }

}
