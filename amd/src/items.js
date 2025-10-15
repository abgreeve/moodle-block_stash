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
 * JS for the items.php page.
 *
 * @copyright  2023 Adrian Greeve <adriangreeve.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Item from 'block_stash/item';
import Drop from 'block_stash/drop';
import Modal from 'core/modal_cancel';
import ModalEvents from 'core/modal_events';
import Templates from 'core/templates';
import SimpleDropSnippetMaker from 'block_stash/drop-snippet-maker';
import DropSnippetMaker from 'block_stash/drop-snippet-shortcode-maker';

const showModal = async(e, altsnippetmaker, warnings) => {
        let node = e.currentTarget;
        let item = new Item(JSON.parse(node.dataset.item));
        let drop = new Drop(JSON.parse(node.dataset.json), item);

        const modal = await buildModal(drop, altsnippetmaker, warnings);
        displayModal(modal);
};

const buildModal = async(drop, altsnippetmaker, warnings) => {
    let context = {
        drop: drop.getData(),
        dropjson: JSON.stringify(drop.getData()),
        item: drop.getItem().getData(),
        itemjson: JSON.stringify(drop.getItem().getData()),
        altsnippetmaker: altsnippetmaker,
        warnings: warnings,
        haswarnings: warnings && warnings.length,
    };

    if (altsnippetmaker == 'block_stash\//drop-snippet-shortcode-maker') {
        let dropsnippetmaker = new DropSnippetMaker(drop);
        context.snippet = dropsnippetmaker.getSnippet();
    } else {
        let dropsnippetmaker = new SimpleDropSnippetMaker(drop);
        context.snippet = dropsnippetmaker.getSnippet();
    }

    return Modal.create({
        title: drop.get('name'),
        body: Templates.render('block_stash/drop_snippet_dialogue', context),
    });
};

const displayModal = async(modal) => {
    modal.getRoot().on(ModalEvents.hidden, () => {
        modal.destroy();
    });
    modal.show();
};

export const init = (altsnippetmaker, warnings) => {
    let tablenodes = document.querySelectorAll('table.itemstable [rel=block-stash-drop]');
    tablenodes.forEach((tablenode) => {
        tablenode.addEventListener('click', (e) => {
            showModal(e, altsnippetmaker, warnings);
        });
    });
};
