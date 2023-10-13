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

export const init = () => {
    // window.console.log('hi from settings');
    let temp = document.querySelector('.block-stash-lbsetting');
    temp.addEventListener('change', (e) => {

        let currenttarget = e.currentTarget;
        updateSetting(currenttarget.dataset.courseid, 'leaderboard', currenttarget.checked).then((result) => {
            if (result) {
                addToast('Setting updated', { // GetString!
                    type: 'info',
                    autohide: true,
                    closeButton: true,
                });
            }
        });
    });

    let lbgroupswitch = document.querySelector('.block-stash-lbgroups');
    lbgroupswitch.addEventListener('change', (e) => {
        let currenttarget = e.currentTarget;
        updateSetting(currenttarget.dataset.courseid, 'leaderboard_groups', currenttarget.checked).then((result) => {
            if (result) {
                addToast('Setting updated', { // GetString!
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
            updateLeaderboard(otherboardelement.dataset.courseid, otherboardelement.dataset.location, '', 'DESC', 5, enabled)
            .then((result) => {
                if (result) {
                    addToast('Setting updated', { // GetString!
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
