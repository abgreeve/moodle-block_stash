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
 * Code to make the item selector work.
 *
 * @copyright  2024 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Templates from 'core/templates';
import * as getItems from 'block_stash/local/datasources/items-getter';

export const init = async () => {
    // Fetch items to populate the select box.
    let maindiv = document.querySelector('.block-stash-item-adder');
    let courseid = maindiv.dataset.courseId;
    let itemdata = await getItems.getItems(courseid);

    // populate the list
    let listelements = document.querySelectorAll('.dropdown-list');
    listelements.forEach((listelement) => {
        let listnode = listelement.querySelector('ul');
        for (let item of itemdata.items) {

            let type = listelement.closest('.block_stash_item_box').dataset.type;

            let listitem = document.createElement('li');
            listitem.innerHTML = item.name;
            listitem.classList.add('dropdown-item');
            listitem.setAttribute('tabindex', '0');
            listitem.dataset.itemid = item.id;
            listitem.dataset.imgurl = item.imageurl;
            listitem.addEventListener('click', (e) => addItemToTable(e, type));
            listitem.addEventListener('keyup', (e) => {
                if (e.keyCode === 13) {
                    addItemToTable(e, type);
                }
            });
            listnode.appendChild(listitem);
        }
    });

    /**
     * @param {Event} e
     */
    function toggleMenu(e) {
        let currentbutton = e.currentTarget;
        let dropdownlist = currentbutton.parentNode.querySelector('.dropdown-list');
        dropdownlist.style.display = (dropdownlist.style.display == 'none') ? 'block' : 'none';
    }

    let selectors = document.querySelectorAll('.item-adder-add');
    selectors.forEach((selector) => {
        selector.addEventListener('click', toggleMenu);
        selector.addEventListener('keyup', (e) => {
            if (e.keyCode === 13 || e.keyCode === 32) {
                toggleMenu(e);
            }
        });
    });

    let searchboxes = document.querySelectorAll('.dropdown-search');
    searchboxes.forEach((searchbox) => {
        searchbox.addEventListener('keyup', (e) => {
            let currentelement = e.currentTarget;
            let dropdowncontainer = currentelement.closest('.dropdown-container');
            let dropdownlist = dropdowncontainer.querySelector('.dropdown-list');
            let searchterm = currentelement.value.toLowerCase();
            let listitems = dropdownlist.querySelectorAll('.dropdown-item');
            for (let item of listitems) {
                let itemtext = item.innerText.toLowerCase();
                if (itemtext.indexOf(searchterm) === -1) {
                    item.style.display = 'none';
                } else {
                    item.style.display = 'block';
                }
            }
        });
    });

    document.addEventListener('mouseup', (e) => {
        let searchboxes = document.querySelectorAll('.dropdown-search');
        searchboxes.forEach((searchbox) => {
            searchbox.value = '';
        });
        let dropdowncontainers = document.querySelectorAll('.dropdown-container');
        let currentelement = e.target;
        dropdowncontainers.forEach((dropdowncontainer) => {
            let dropdownlist = dropdowncontainer.querySelector('.dropdown-list');
            if (!dropdowncontainer.contains(currentelement)) {
                dropdownlist.style.display = 'none';
                let listitems = dropdownlist.querySelectorAll('.dropdown-item');
                for (let item of listitems) {
                    item.style.display = 'block';
                }
            }
        });
    });
};

const addItemToTable = (e, typeinfo) => {
    let itemnode = e.currentTarget;
    let data = {
        itemid: itemnode.dataset.itemid,
        imageurl: itemnode.dataset.imgurl,
        name: itemnode.innerText,
        quantity: 1
    };

    let type = typeinfo;

    let templatename = (type === 'gain') ? 'block_stash/trade_add_item_detail' : 'block_stash/trade_loss_item_detail';
    Templates.render(templatename, data).done((html, js) => {
        let itemsbox = document.querySelector('.block_stash_item_box[data-type="' + type + '"]');
        Templates.appendNodeContents(itemsbox, html, js);
        registerActions();
    });
};

export const registerActions = () => {
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
