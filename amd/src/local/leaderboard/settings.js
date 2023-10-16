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
 * Leaderboard settings JavaScript
 *
 * @copyright 2023 Adrian Greeve <adriangreeve.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {add as addToast} from 'core/toast';
import Ajax from 'core/ajax';
import {get_string as getString} from 'core/str';

export const init = () => {
    let lbsetting = document.querySelector('.block-stash-lbsetting');
    lbsetting.addEventListener('change', (e) => {

        let currenttarget = e.currentTarget;
        updateSetting(currenttarget.dataset.courseid, 'leaderboard', currenttarget.checked).then((result) => {
            if (result) {
                addToast(getString('settingupdated', 'block_stash'), {
                    type: 'info',
                    autohide: true,
                    closeButton: true,
                });
            }
        });

        let mainboards = document.querySelector('.block_stash_lb_cards');
        window.console.log(mainboards);
        if (currenttarget.checked) {
            mainboards.classList.remove('invisible');
        } else {
            mainboards.classList.add('invisible');
        }
    });

    let lbgroupswitch = document.querySelector('.block-stash-lbgroups');
    lbgroupswitch.addEventListener('change', (e) => {
        let currenttarget = e.currentTarget;
        updateSetting(currenttarget.dataset.courseid, 'leaderboard_groups', currenttarget.checked).then((result) => {
            if (result) {
                addToast(getString('settingupdated', 'block_stash'), {
                    type: 'info',
                    autohide: true,
                    closeButton: true,
                });
            }
        });
    });

    let otherboards = document.querySelectorAll('.block_stash-leaderboard');
    otherboards.forEach((board) => {
        board.addEventListener('change', (e) => {
            let otherboardelement = e.currentTarget;
            let enabled = otherboardelement.checked;

            let boardlimit = otherboardelement.parentNode.parentNode.querySelector('.block_stash-leaderboard-limit');
            if (!enabled) {
                boardlimit.setAttribute('disabled', true);
            } else {
                boardlimit.removeAttribute('disabled');
            }

            updateLeaderboard(otherboardelement.dataset.courseid, otherboardelement.dataset.location, '', 'DESC', boardlimit.value,
                    enabled)
            .then((result) => {
                if (result) {
                    addToast(getString('settingupdated', 'block_stash'), {
                        type: 'info',
                        autohide: true,
                        closeButton: true,
                    });
                }
            });
        });
    });

    let rowlimits = document.querySelectorAll('.block_stash-leaderboard-limit');
    rowlimits.forEach((limit) => {
        limit.addEventListener('change', (e) => {
            let currenttarget = e.currentTarget;
            let rowlimit = currenttarget.value;
            let otherthing = currenttarget.parentNode.parentNode.parentNode.querySelector('.block_stash-leaderboard');
             updateLeaderboard(otherthing.dataset.courseid, otherthing.dataset.location, '', 'DESC', rowlimit, true)
            .then((result) => {
                if (result) {
                    addToast(getString('settingupdated', 'block_stash'), {
                        type: 'info',
                        autohide: true,
                        closeButton: true,
                    });
                }
            });
        });
    });
};

const updateSetting = (courseid, key, value) => {
    return Ajax.call([{
        methodname: 'block_stash_leaderboard_settings',
        args: {
            courseid: courseid,
            key: key,
            value: value
        }
    }])[0];
};

const updateLeaderboard = (courseid, title, options, sortorder, limit, enabled) => {
    return Ajax.call([{
        methodname: 'block_stash_leaderboard_update',
        args: {
            courseid: courseid,
            boardname: title,
            options: options,
            sortorder: sortorder,
            rowlimit: limit,
            enabled: enabled
        }
    }])[0];
};
