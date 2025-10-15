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
 * Select items to give to students.
 *
 * @copyright  2023 Adrian Greeve <adriangreeve.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Modal from 'core/modal_save_cancel';
import ModalEvents from 'core/modal_events';
import Ajax from 'core/ajax';
import Templates from 'core/templates';
import {get_string as getString} from 'core/str';
import {add as addToast} from 'core/toast';

const showModal = async(e) => {
    let targetnode = e.currentTarget,
        userid = targetnode.dataset.userid,
        courseid = targetnode.dataset.courseid;

    const modal = await buildModal(courseid);
    displayModal(modal, userid, courseid);
};

const buildModal = async(courseid) => {
    let context = await getItems(courseid);
    context.type = '';

    return Modal.create({
        title: getString('additem', 'block_stash'),
        body: Templates.render('block_stash/trade_item_picker', context),
    });
};

const displayModal = async(modal, userid, courseid) => {
    let savetext = getString('additem', 'block_stash');
    modal.setSaveButtonText(savetext);
    modal.getRoot().on(ModalEvents.save, () => {

        // Get the item id and the quantity.
        let itemnode = document.getElementById('block-stash-item-select');
        let itemid = itemnode.options[itemnode.selectedIndex].value;
        let quantitynode = document.getElementById('amount');
        let quantity = quantitynode.value;

        const itemdetails = saveItem(courseid, itemid, userid, quantity);
        itemdetails.then((iteminfo) => {
            // Get the table.
            let tablenode = document.querySelector('.block-stash-report-table');
            let tablething = tablenode.children[0];
            // See if the item exists in the table and update the quantity.

            let existingrow = document.querySelector('.block-stash-item[data-id="' + itemid + '"]');
            if (existingrow) {
                // Update the picture number.
                existingrow.querySelector('div.item-quantity').innerHTML = quantity;
                // Update the text element.
                existingrow.parentNode.parentNode.querySelector('input[name="quantity"]').value = quantity;
            } else {
                // Add a new row to the table.
                let rowcontext = {
                    item: {
                        id: iteminfo.id,
                        name: iteminfo.name,
                        imageurl: iteminfo.imageurl
                    },
                    useritem: {
                        userid: userid,
                        quantity: quantity
                    },
                    courseid: courseid
                };
                Templates.render('block_stash/local/report_table/item_row', rowcontext).done((html, js) => {
                    Templates.appendNodeContents(tablething, html, js);
                    registerSaveListeners();
                    registerDeleteListeners();
                });
            }

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

const saveItem = (courseid, itemid, userid, quantity) => Ajax.call([{
    methodname: 'block_stash_add_user_item',
    args: {
        courseid: courseid,
        itemid: itemid,
        userid: userid,
        quantity: quantity
    }
}, {
    methodname: 'block_stash_get_item',
    args: {
        itemid: itemid,
    }
}])[1];

const registerSaveListeners = () => {
    let savebuttons = document.getElementsByClassName('block-stash-save-button');
    savebuttons.forEach((button) => {
        button.addEventListener('click', saveItemInformation);
    });
};

const saveItemInformation = (e) => {
    let target = e.currentTarget;
    let quantity = target.parentNode.parentNode.querySelector('input[name="quantity"]').value;
    let itemid = target.parentNode.parentNode.querySelector('input[name="itemid"]').value;
    let userid = target.parentNode.parentNode.querySelector('input[name="userid"]').value;
    let courseid = target.parentNode.parentNode.querySelector('input[name="courseid"]').value;
    saveItem(courseid, itemid, userid, quantity);
    // maybe put this in the .then At the moment this notifies regardless of whether the save was successful or not.
    addToast(getString('itemamountupdate', 'block_stash'), {
        type: 'info',
        autohide: true,
        closeButton: true,
    });
    let quantityindicator = document.querySelector('.block-stash-item[data-id="' + itemid + '"] div.item-quantity');
    quantityindicator.innerHTML = quantity;
};

const registerDeleteListeners = () => {
    let deletebuttons = document.getElementsByClassName('block-stash-delete-button');
    deletebuttons.forEach((button) => {
        button.addEventListener('click', deleteItem);
    });
};

const deleteItem = (e) => {
    let target = e.currentTarget;
    let quantity = 0;
    let itemid = target.parentNode.parentNode.querySelector('input[name="itemid"]').value;
    let userid = target.parentNode.parentNode.querySelector('input[name="userid"]').value;
    let courseid = target.parentNode.parentNode.querySelector('input[name="courseid"]').value;
    saveItem(courseid, itemid, userid, quantity);
    // maybe put this in the .then At the moment this notifies regardless of whether the save was successful or not.
    addToast(getString('itemdeleted', 'block_stash'), {
        type: 'info',
        autohide: true,
        closeButton: true,
    });
    let itemimagenode = document.querySelector('.block-stash-item[data-id="' + itemid + '"]');
    itemimagenode.parentNode.parentNode.remove();
};

export const init = () => {
    let additembutton = document.querySelector("[data-additem]");
    additembutton.addEventListener('click', showModal);
    registerSaveListeners();
    registerDeleteListeners();
};
