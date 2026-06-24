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
 * Code to make the random drop pool selector work.
 *
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Templates from 'core/templates';
import * as getItems from 'block_stash/local/datasources/items-getter';

let registered = false;

export const init = async() => {
    const editor = document.querySelector('.block-stash-random-drop-pool-editor');
    if (!editor || registered) {
        return;
    }

    const courseid = editor.dataset.courseId;
    const weightOptions = JSON.parse(editor.dataset.weightOptions || '[]');
    const defaultWeight = parseInt(editor.dataset.defaultWeight, 10);
    const itemdata = await getItems.getItems(courseid);

    const dropdownLists = editor.querySelectorAll('.dropdown-list');
    dropdownLists.forEach((dropdownList) => {
        const listNode = dropdownList.querySelector('ul');
        if (!listNode || listNode.childElementCount > 0) {
            return;
        }

        itemdata.items.forEach((item) => {
            const listItem = document.createElement('li');
            listItem.innerHTML = item.name;
            listItem.classList.add('dropdown-item');
            listItem.setAttribute('tabindex', '0');
            listItem.dataset.itemid = item.id;
            listItem.dataset.imgurl = item.imageurl;
            listItem.addEventListener('click', (e) => addItemToTable(editor, e, weightOptions, defaultWeight));
            listItem.addEventListener('keyup', (e) => {
                if (e.keyCode === 13) {
                    addItemToTable(editor, e, weightOptions, defaultWeight);
                }
            });
            listNode.appendChild(listItem);
        });
    });

    const toggleMenu = (e) => {
        const currentButton = e.currentTarget;
        const dropdownList = currentButton.parentNode.querySelector('.dropdown-list');
        dropdownList.style.display = (dropdownList.style.display === 'none') ? 'block' : 'none';
    };

    editor.querySelectorAll('.block-stash-random-drop-pool-add').forEach((button) => {
        button.addEventListener('click', toggleMenu);
        button.addEventListener('keyup', (e) => {
            if (e.keyCode === 13 || e.keyCode === 32) {
                toggleMenu(e);
            }
        });
    });

    editor.querySelectorAll('.dropdown-search').forEach((searchBox) => {
        searchBox.addEventListener('keyup', (e) => {
            const currentElement = e.currentTarget;
            const dropdownContainer = currentElement.closest('.dropdown-container');
            const dropdownList = dropdownContainer.querySelector('.dropdown-list');
            const searchTerm = currentElement.value.toLowerCase();
            const listItems = dropdownList.querySelectorAll('.dropdown-item');
            listItems.forEach((item) => {
                const itemText = item.innerText.toLowerCase();
                item.style.display = (itemText.indexOf(searchTerm) === -1) ? 'none' : 'block';
            });
        });
    });

    document.addEventListener('mouseup', (e) => {
        editor.querySelectorAll('.dropdown-search').forEach((searchBox) => {
            searchBox.value = '';
        });
        editor.querySelectorAll('.dropdown-container').forEach((dropdownContainer) => {
            const dropdownList = dropdownContainer.querySelector('.dropdown-list');
            if (!dropdownContainer.contains(e.target)) {
                dropdownList.style.display = 'none';
                dropdownList.querySelectorAll('.dropdown-item').forEach((item) => {
                    item.style.display = 'block';
                });
            }
        });
    });

    registerActions(editor);
    registered = true;
};

const addItemToTable = (editor, e, weightOptions, defaultWeight) => {
    const itemNode = e.currentTarget;
    const existing = editor.querySelector('.block-stash-random-drop-pool-item[data-itemid="' + itemNode.dataset.itemid + '"]');
    if (existing) {
        const selector = existing.querySelector('select');
        if (selector) {
            selector.focus();
        }
        return;
    }

    const data = {
        id: itemNode.dataset.itemid,
        itemid: itemNode.dataset.itemid,
        imageurl: itemNode.dataset.imgurl,
        name: itemNode.innerText,
        weightoptions: weightOptions.map((option) => ({
            value: option.value,
            label: option.label,
            selected: parseInt(option.value, 10) === defaultWeight,
        })),
    };

    Templates.render('block_stash/random_drop_pool_item', data).done((html, js) => {
        const itemsBox = editor.querySelector('[data-region="pool-items"]');
        Templates.appendNodeContents(itemsBox, html, js);
        registerActions(editor);
    });
};

const registerActions = (editor) => {
    editor.querySelectorAll('.block-stash-delete-pool-item').forEach((deleteElement) => {
        deleteElement.removeEventListener('click', deleteItem);
        deleteElement.addEventListener('click', deleteItem);
    });
};

const deleteItem = (event) => {
    const child = event.currentTarget;
    const parent = child.closest('.block-stash-random-drop-pool-item');
    if (parent) {
        parent.remove();
    }
    event.preventDefault();
};
