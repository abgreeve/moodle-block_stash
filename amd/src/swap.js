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
 * User swap code
 *
 * @copyright  2019 Adrian Greeve - adriangreeve.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Modal from 'core/modal_save_cancel';
import ModalEvents from 'core/modal_events';
import Templates from 'core/templates';
import Ajax from 'core/ajax';
import {add as addToast} from 'core/toast';
import {get_string as getString} from 'core/str';

const showModal = async(e) => {
    let swapbtn = e.currentTarget;
    let courseid = swapbtn.getAttribute('data-courseid');
    let userid = swapbtn.getAttribute('data-userid');
    let itemid = swapbtn.dataset.itemid;
    let myuserid = swapbtn.getAttribute('data-myuserid');
    let [userstash, mystash] = await Promise.all([getUserStash(courseid, userid), getUserStash(courseid, myuserid)])
        .catch(() => {
            addToast(getString('noitemstotrade', 'block_stash'), {
                type: 'warning',
                autohide: true,
                closeButton: true,
            });
            return [];
        });

    if (mystash.length == 0) {
        return;
    }

    const modal = await buildModal(courseid, userstash, mystash, userid, myuserid, itemid);
    displayModal(modal);
};

const buildModal = async(courseid, userstash, mystash, userid, myuserid, itemid = null) => {
    let context = {
            courseid: courseid,
            yourstash: userstash,
            mystash: mystash,
            userid: userid,
            myuserid: myuserid,
            itemid: itemid
    };
    return Modal.create({
        title: getString('createtrade', 'block_stash'),
        body: Templates.render('block_stash/local/swap/swap_form', context),
    });
};

const displayModal = async(modal) => {
    let savetext = getString('sendtraderequest', 'block_stash');
    modal.setSaveButtonText(savetext);
    modal.getRoot().on(ModalEvents.save, () => {
        // Do stuff here.

        let myitems = [];
        let youritems = [];

        let swapitems = document.getElementsByClassName('block-stash-quantity');
        Object.entries(swapitems).forEach((item) => {
            if (item[1].getAttribute('data-select-type') == 'your-items') {
                youritems.push({id: item[1].getAttribute('data-itemid'), quantity: item[1].value});
            } else {
                myitems.push({id: item[1].getAttribute('data-itemid'), quantity: item[1].value});
            }
        });

        let formelement = document.querySelector('form');
        let courseid = formelement.getAttribute('data-courseid');
        let userid = formelement.getAttribute('data-userid');
        let myuserid = formelement.getAttribute('data-myuserid');

        submitSwap(userid, myuserid, courseid, youritems, myitems);
        modal.destroy();

    });

    modal.getRoot().on(ModalEvents.hidden, () => {
        modal.destroy();
    });

    modal.show();
};

const submitSwap = (userid, myuserid, courseid, items, myitems) => {
        return Ajax.call([{
            methodname: 'block_stash_create_swap_request',
            args: {
                userid: userid,
                myuserid: myuserid,
                courseid: courseid,
                items: items,
                myitems: myitems
            }
        }])[0].then((allitems) => {
            addToast(getString('requestsent', 'block_stash'), {
                type: 'info',
                autohide: true,
                closeButton: true,
            });
            return allitems;
        });
};


const getUserStash = (courseid, userid) => {
    return Ajax.call([{
        methodname: 'block_stash_get_user_stash_items',
        args: {
            courseid: courseid,
            userid: userid
        }
    }])[0].then((allitems) => {
        return allitems;
    });
};

export const init = () => {
    let swapbtns = document.querySelectorAll('[data-swap]');
    for (let swapbtn of swapbtns) {
        swapbtn.addEventListener('click', showModal);
    }
};
