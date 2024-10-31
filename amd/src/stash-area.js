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
import {get_string as getString} from 'core/str';

var _collections = [];
var _node = null;

export const init = async(courseid) => {
    _node = document.querySelector('.block-stash-main-block');
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
    let stashitems = _node.querySelectorAll('.block-stash-item');
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

    let itemlist = _node.querySelector('.item-list');
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
    const itemnode = _node.querySelector('.block-stash-item[data-id="' + itemId + '"]');
    return (itemnode);
};

const updateUserItemQuantity = (userItem) => {
    const itemnodes = _node.querySelectorAll('.block-stash-item[data-id="' + userItem.getItem().get('id') + '"]');
    itemnodes.forEach((itemnode) => {
        const quantityNode = itemnode.querySelector('.item-quantity'),
              newQuantity = userItem.get('quantity'),
              quantity = parseInt(quantityNode.textContent, 10);

        quantityNode.textContent = newQuantity;
        quantityNode.style.display = 'block';
        itemnode.classList.remove('item-quantity-' + quantity);
        itemnode.classList.add('item-quantity-' + newQuantity);
        if (newQuantity == "0") {
            window.console.log("I want to know about it");
            // Get collection ids
            const collectionids = getCollectionDisplayInfo(userItem);
            if (collectionids.length > 0) {
                collectionids.forEach(async(id) => {
                    const collectioncontainer = _node.querySelector('.block-stash-collections[data-collectionid="' + id + '"');
                    if (collectioncontainer) {
                        const existinglegendelement = collectioncontainer.querySelector('legend');
                        existinglegendelement.innerText = await getCollectionString(id, true);
                        // If the legend color style is set then remove
                        if (existinglegendelement.getAttribute("style") !== null) {
                            existinglegendelement.style.removeProperty("color");
                            existinglegendelement.style.removeProperty("border-color");
                        }
                    }
                });
            }
        }
    });
};

const addUserItem = (userItem) => {
    return renderUserItem(userItem).then((html, js) => {
        // We have two areas to update now, the all tab and the collections tab.
        // window.console.log(_collections);
        const collectionids = getCollectionDisplayInfo(userItem);
        const template = document.createElement('template');
        template.innerHTML = html;
        const node = template.content.firstChild;
        // All items display
        const container = document.querySelector('#allitems');
        node.dataset.useritem = userItem;
        makeUserItemNodeClickable(node);
        container.append(' ');  // A hacky separator to replicate natural rendering.
        container.append(node);

        // Collections display
        if (collectionids.length > 0) {
            collectionids.forEach(async(id) => {
                let collectionnode = node.cloneNode(true);
                const collectioncontainer = _node.querySelector('.block-stash-collections[data-collectionid="' + id + '"');
                if (collectioncontainer) {
                    const existinglegendelement = collectioncontainer.querySelector('legend');
                    existinglegendelement.innerText = await getCollectionString(id);
                    collectioncontainer.appendChild(collectionnode);
                    if (softCheckCollectioComplete(id)) {
                        existinglegendelement.style.borderColor = '#088208';
                        existinglegendelement.style.color = '#088208';
                    }
                    // window.console.log(existinglegendelement);

                } else {
                    // If there are no items in the collection then the collection needs to be added.
                    const collectionelement = document.createElement('fieldset');
                    collectionelement.classList.add("block-stash-collections", "mb-1", "p-2");
                    collectionelement.dataset.collectionid = id;
                    // Now we need a legend.
                    const legendelement = document.createElement('legend');
                    legendelement.classList.add('block-stash-collection-legend', 'p-1');
                    legendelement.innerText = await getCollectionString(id);
                    collectionelement.appendChild(legendelement);
                    collectionelement.appendChild(collectionnode);
                    const parentelement = _node.querySelector('.block-stash-collections-area');
                    parentelement.appendChild(collectionelement);
                }
            });
        }

        Templates.runTemplateJS(js);
    });
};

const getCollectionString = async(collectionid, check = false) => {
    const currentcount = countCollectionItems(collectionid);
    const collection = getInternalCollectionData(collectionid);
    let collectedcount = (check) ? currentcount : parseInt(currentcount+1);
    const data = {
        name: collection['collection'].name,
        collected: collectedcount,
        total: collection['items'].length
    };
    const colstring = await getString('collected', 'block_stash', data);
    return colstring;
};

const countCollectionItems = (collectionid) => {
    const collectioncontainer = _node.querySelector('.block-stash-collections[data-collectionid="' + collectionid + '"');
    if (!collectioncontainer) {
        return 0;
    }
    const items = collectioncontainer.querySelectorAll('.block-stash-item');
    let collectioncount = items.length;
    items.forEach((item) => {
        const quantitynode = item.querySelector('.item-quantity');
        if (quantitynode.innerText == '0') {
            collectioncount--;
        }
    });
    return collectioncount;
};

const softCheckCollectioComplete = (collectionid) => {
    const currentcount = countCollectionItems(collectionid);
    const collection = getInternalCollectionData(collectionid);
    return (collection['items'].length == currentcount);
};

const getInternalCollectionData = (collectionid) => {
    let collectionresult = null;
    _collections.forEach((collection) => {
        if (collection['collection'].id == collectionid) {
            collectionresult = collection;
        }
    });
    return collectionresult;
};

const getCollectionDisplayInfo = (userItem) => {
    // Loop through each collection
    let data = [];
    _collections.forEach((collection) => {
        // Loop through the collections items to see if we have a match.
        collection['items'].forEach((item) => {
            if (item.id == userItem.getItem().get('id')) {
                const quick = collection['collection'];
                data.push(quick.id);
            }

        });
    });
    return data;
};
