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
 * Snippet modal
 *
 * @copyright  2023 Adrian Greeve <adriangreeve.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Modal from 'core/modal_cancel';
import ModalEvents from 'core/modal_events';
import Templates from 'core/templates';
import Ajax from 'core/ajax';
import TradeSnippet from 'block_stash/trade-snippet-shortcode-maker';

const showModal = async(trade) => {
    const modal = await buildModal(trade);
    displayModal(modal);
};

const buildModal = async(trade) => {
    let context = {
        trade: trade.getData(),
    };

    window.console.log(context.trade);

    context.tradeitems = await getTradeItems(trade);
    let tradesnippetmaker = new TradeSnippet(trade);
    context.snippet = tradesnippetmaker.getSnippet();

    return Modal.create({
        title: context.trade.name,
        body: Templates.render('block_stash/trade_snippet_dialogue', context),
    });
};

const displayModal = async(modal) => {
    modal.getRoot().on(ModalEvents.hidden, () => {
        modal.destroy();
    });
    modal.show();
};

const getTradeItems = (trade) => Ajax.call([{
    methodname: 'block_stash_get_trade_items',
    args: {tradeid: trade.get('id')}
}])[0];

export const init = (trade) => {
    showModal(trade);
};
