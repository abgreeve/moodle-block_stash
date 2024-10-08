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
 * A small modal to search grade items within the gradebook.
 *
 * @copyright 2023 Adrian Greeve <abgreeve@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Pending from 'core/pending';
import * as Templates from 'core/templates';
import * as WidgetBase from 'core_grades/searchwidget/basewidget';
import $ from 'jquery';
import ajax from 'core/ajax';

/**
 * Our entry point into starting to build the search widget.
 * It'll eventually, based upon the listeners, open the search widget and allow filtering.
 *
 * @method init
 */
export const init = () => {
    const pendingPromise = new Pending();
    registerListenerEvents();
    pendingPromise.resolve();
};

/**
 * Register grade item search widget related event listeners.
 *
 * @method registerListenerEvents
 */
const registerListenerEvents = () => {
    let {bodyPromiseResolver, bodyPromise} = WidgetBase.promisesAndResolvers();
    let dropdownmenuselector = '.search-widget[data-searchtype="block_stash-user"] .dropdown-menu';
    let dropdownselector = '.search-widget[data-searchtype="block_stash-user"]';
    const dropdownMenuContainer = document.querySelector(dropdownmenuselector);

    // Handle the 'shown.bs.dropdown' event (Fired when the dropdown menu is fully displayed).
    $(dropdownselector).on('show.bs.dropdown', async(e) => {
        const courseID = e.relatedTarget.dataset.courseid;
        // Display a loading icon in the dropdown menu container until the body promise is resolved.
        await WidgetBase.showLoader(dropdownMenuContainer);

        // If an error occurs while fetching the data, display the error within the modal.
        const data = await swapUserFetch(courseID).catch(async(e) => {
            const errorTemplateData = {
                'errormessage': e.message
            };
            bodyPromiseResolver(
                await Templates.render('core_grades/searchwidget/error', errorTemplateData)
            );
        });

        // Early return if there is no module data.
        if (data === []) {
            return;
        }

        await WidgetBase.init(
            dropdownMenuContainer,
            bodyPromise,
            data.users,
            searchGradeitems()
        );

        // Resolvers for passed functions in the modal creation.
        bodyPromiseResolver(Templates.render('block_stash/usersearch_body', []));
    });

    // Handle the 'hide.bs.dropdown' event (Fired when the dropdown menu is being closed).
    $(dropdownselector).on('hide.bs.dropdown', () => {
        // Reset the state once the grade item menu dropdown is closed.
        dropdownMenuContainer.innerHTML = '';
    });
};

const swapUserFetch = (courseid) => {
    const request = {
        methodname: 'block_stash_get_users_for_search_widget',
        args: {
            courseid: courseid,
        },
    };
    return ajax.call([request])[0];
};

/**
 * Define how we want to search and filter grade items when the user decides to input a search value.
 *
 * @method registerListenerEvents
 * @returns {function(): function(*, *): (*)}
 */
const searchGradeitems = () => {
    return () => {
        return (modules, searchTerm) => {
            if (searchTerm === '') {
                return modules;
            }
            searchTerm = searchTerm.toLowerCase();
            const searchResults = [];
            modules.forEach((module) => {
                const moduleName = module.fullname.toLowerCase();
                if (moduleName.includes(searchTerm)) {
                    searchResults.push(module);
                }
            });
            return searchResults;
        };
    };
};
