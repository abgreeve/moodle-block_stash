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
 * Add items to a trade table.
 *
 * @copyright 2023 Adrian Greeve <adriangreeve.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Modal from 'core/modal_save_cancel';
import ModalEvents from 'core/modal_events';
import {get_string as getString} from 'core/str';
import Templates from 'core/templates';
import Ajax from 'core/ajax';

const showModal = async(e) => {
    let node = e.currentTarget;
    let type = node.dataset.type;
    const modal = await buildModal(type);
    displayModal(modal, type);
};

const buildModal = async(tradetype) => {
    let courseelement = document.querySelector('input[name="courseid"]');
    let courseid = courseelement.value;
    let context = await getItems(courseid);
    context.type = tradetype;
    return Modal.create({
        title: getString('addnewtradeitem', 'block_stash'),
        body: Templates.render('block_stash/trade_item_picker', context),
    });
};

const displayModal = async(modal, type) => {
    let savetext = getString('additem', 'block_stash');
    modal.setSaveButtonText(savetext);
    modal.getRoot().on(ModalEvents.save, () => {
        let itemnode = document.querySelector('#block-stash-item-select option:checked');
        let itemamount = document.getElementById('amount').value;
        itemamount = parseInt(itemamount);
        if (!Number.isInteger(itemamount) || itemamount <= 0) {
            itemamount = 1;
        }

        let data = {
            itemid: itemnode.value,
            imageurl: itemnode.dataset.imgurl,
            name: itemnode.innerText,
            quantity: itemamount
        };

        let templatename = (type === 'gain') ? 'block_stash/trade_add_item_detail' : 'block_stash/trade_loss_item_detail';
        Templates.render(templatename, data).done((html, js) => {
            let itemsbox = document.querySelector('.block_stash_item_box[data-type="' + type + '"]');
            Templates.appendNodeContents(itemsbox, html, js);
            registerActions();
        });

        modal.destroy();

    });

    modal.getRoot().on(ModalEvents.hidden, () => {
        modal.destroy();
    });

    modal.show();
};

const getItems = (courseid) => Ajax.call([{
    methodname: 'block_stash_get_items',
    args: {courseid: courseid}
}])[0];

const registerActions = () => {
    let deleteelements = document.getElementsByClassName('block-stash-delete-item');
    for (let delement of deleteelements) {
        delement.addEventListener('click', deleteItem);
    }
};

const deleteItem = (element) => {
    let child = element.currentTarget;
    let parent = child.closest('.block-stash-trade-item');
    parent.remove();
    element.preventDefault();
};

export const init = () => {
    registerActions();
    let additemelements = document.getElementsByClassName('additem');
    for (let additem of additemelements) {
        additem.addEventListener('click', showModal);
    }
};
