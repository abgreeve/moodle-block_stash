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
 * Stash module.
 *
 * @copyright  2016 Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import * as ItemModal from 'block_stash/item-modal';
import * as PubSub from 'core/pubsub';
import Templates from 'core/templates';

var _collections = [];

export const init = async(courseid) => {
    _collections = await getCollectionData(courseid);
    // window.console.log(_collections);
    setUpUserItemAreClickable();
    PubSub.subscribe('block_stash/drop/pickedup', dropPickedUpListener.bind(this));
    PubSub.subscribe('trade:pickedup', dropPickedUpListener.bind(this));
};

const getCollectionData = (courseid) => {
    return Ajax.call([
        {
            methodname: 'block_stash_get_collections',
            args: {courseid: courseid}
        }
    ])[0];
};

const renderUserItem = (userItem) => {
    return Templates.render('block_stash/user_item', {
        item: userItem.getItem().getData(),
        useritem: userItem.getData(),
    });
};

const setUpUserItemAreClickable = () => {
    let stashitems = document.querySelectorAll('.block-stash-item');
    stashitems.forEach(function(item) {
        makeUserItemNodeClickable(item);
    });

    const handler = (e) => {
        if (e.target.closest('.block-stash-item')) {
            const itemelement = e.target.closest('.block-stash-item');
            const itemId = itemelement.dataset.id;

            if (!itemId) {
                return;
            }

            ItemModal.init(itemId);
            e.preventDefault();
        }
    };

    let itemlist = document.querySelector('.item-list');
    itemlist.addEventListener('click', (e) => handler(e));
    itemlist.addEventListener('keydown', (e) => {
        if (e.keyCode != 13 && e.keyCode != 32) {
            return;
        }
        handler(e);
    });
};

const makeUserItemNodeClickable = (node) => {
    node.setAttribute('tabindex', 0);
    node.setAttribute('role', 'button');
    node.setAttribute('aria-haspopup', 'true');
};

const dropPickedUpListener = (e) => {
    let userItem = e.useritem;
    if (containsItem(userItem.getItem().get('id'))) {
        updateUserItemQuantity(userItem);
    } else {
        addUserItem(userItem).then(() => {
            const emptyelement = document.querySelector('.empty-content');
            if (emptyelement !== null) {
                document.querySelector('.empty-content').remove();
            }
        });
    }
};

const containsItem = (itemId) => {
    const itemnode = document.querySelector('.block-stash-item[data-id="' + itemId + '"]');
    return (itemnode);
};

const updateUserItemQuantity = (userItem) => {
    const itemnode = document.querySelector('.block-stash-item[data-id="' + userItem.getItem().get('id') + '"]'),
          quantityNode = itemnode.querySelector('.item-quantity'),
          newQuantity = userItem.get('quantity'),
          quantity = parseInt(quantityNode.textContent, 10);

    quantityNode.textContent = newQuantity;
    quantityNode.style.display = 'block';
    itemnode.classList.remove('item-quantity-' + quantity);
    itemnode.classList.add('item-quantity-' + newQuantity);
};

const addUserItem = (userItem) => {
    return renderUserItem(userItem).then((html, js) => {
        // We have two areas to update now, the all tab and the collections tab.
        window.console.log(_collections);
        const template = document.createElement('template');
        template.innerHTML = html;
        const node = template.content.firstChild;
        const container = document.querySelector('#allitems');
        node.dataset.useritem = userItem;
        makeUserItemNodeClickable(node);
        container.append(' ');  // A hacky separator to replicate natural rendering.
        container.append(node);
        Templates.runTemplateJS(js);
    });
};
