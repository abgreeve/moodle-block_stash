import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';
import Templates from 'core/templates';
import * as tradeAdder from 'block_stash/local/trade_adder/main';
import Ajax from 'core/ajax';
import * as getItems from 'block_stash/local/datasources/items-getter';
import {get_string as getString} from 'core/str';
import * as Toast from 'core/toast';

const showModal = async(courseid, editdetails = []) => {
    const modal = await buildModal(courseid, editdetails);
    displayModal(modal, courseid);
};

const buildModal = async(courseid, editdetails) => {

    // Fetch quizzes.
    let quizzes = await fetchQuizData(courseid);

    quizzes.activities.forEach((quiz) => {
        quiz['selected'] = false;
        if (editdetails.length !== 0) {
            if (quiz.id == editdetails.quizid) {
                quiz['selected'] = true;
            }
        }
    });

    let context = {'courseid': courseid, 'quizzes': quizzes.activities};
    if (editdetails.length !== 0) {
        let allitems = await getItems.getIndexedItems(courseid);
        let additemsdata = [];
        editdetails.items.forEach((item) => {
            additemsdata.push(
                {
                    'itemid': item.itemid,
                    'name': allitems[item.itemid].name,
                    'quantity': item.quantity,
                    'imageurl': allitems[item.itemid].imageurl
                }
            );
        });
        context['additems'] = additemsdata;
        context['removalid'] = editdetails.removalid;
    }
    window.console.log(context);

    return ModalFactory.create({
        title: getString('configureremoval', 'block_stash'),
        body: Templates.render('block_stash/local/removal/removal_form', context),
        type: ModalFactory.types.SAVE_CANCEL
    });
};

const displayModal = async(modal, courseid) => {

    modal.getRoot().on(ModalEvents.bodyRendered, () => {
        tradeAdder.init();
        tradeAdder.registerActions();
    });

    modal.getRoot().on(ModalEvents.save, () => {
        saveData(courseid);
    });

    modal.getRoot().on(ModalEvents.hidden, () => {
        modal.destroy();
    });
    modal.show();
};

const saveData = async (courseid) => {
    let itemsinfo = document.querySelectorAll('.block-stash-quantity');
    let items = [];
    let returnitemdata = [];
    itemsinfo.forEach((item) => {
        let itemid = item.closest('.block-stash-trade-item').getAttribute('data-id');
        let basedata = {
            'itemid': parseInt(itemid),
            'quantity': parseInt(item.value)
        };
        // Do it again, but duplicating objects just ends up with a reference which is not what I want.
        let fulldata = {
            'itemid': parseInt(itemid),
            'quantity': parseInt(item.value),
            'name': item.closest('.block-stash-trade-item').children[0].innerText.trim()
        };
        items.push(basedata);
        returnitemdata.push(fulldata);
    });
    let quizselect = document.querySelector('.block-stash-quiz-select');
    let cmid = quizselect.value;
    if (cmid === '0') {
        await Toast.addToastRegion(document.querySelector('.modal-body'));
        Toast.add(getString('selectquizcheck', 'block_stash'), {
            type: 'danger',
            autohide: true,
            closeButton: true,
        });
        return false;
    }
    let removalid = 0;
    let removalelement = document.getElementById('block_stash_removal_id');
    if (removalelement) {
        removalid = updateRemovalEntry(courseid, parseInt(cmid), items, removalelement.dataset.id).then(() => {
            Toast.add(getString('configupdated', 'block_stash'), {
                type: 'info',
                autohide: true,
                closeButton: true,
            });
        });
    } else {
        removalid = await saveRemovalEntry(courseid, parseInt(cmid), items);
    }

    let context = {
        'cmid': cmid,
        'cmname': quizselect.item(quizselect.selectedIndex).text,
        'courseid': courseid,
        'removalid': removalid,
        'items': returnitemdata,
        'editinfo': JSON.stringify(items)
    };
    // window.console.log(context);
    Templates.render('block_stash/local/removal/table_row', context).then((html, js) => {
        if (!removalelement) {
            let tableobject = document.querySelector('.block-stash-removal-body');
            let things = Templates.appendNodeContents(tableobject, html, js);
            registerDeleteEvent(courseid, things[0].querySelector('.block-stash-removal-icon'));
            registerEditEvent(courseid, things[0].querySelector('.block-stash-removal-edit'));
        } else {
            let rowelement = document.querySelector('.block-stash-removal-edit[data-id="' + removalid + '"');
            let tmpe = rowelement.closest('tr');
            let things = Templates.replaceNode(tmpe, html, js);
            registerDeleteEvent(courseid, things[0].querySelector('.block-stash-removal-icon'));
            registerEditEvent(courseid, things[0].querySelector('.block-stash-removal-edit'));
        }
    });
};

const registerDeleteEvent = (courseid, deleteobject) => {
    deleteobject.addEventListener('click', (e) => {
        e.preventDefault();
        let deletionelement = e.currentTarget;
        let removalid = deletionelement.dataset.id;
        // Make ajax request to delete this removal configuration.
        deleteRemovalEntry(courseid, parseInt(removalid)).then(() => {
            // If the request was okay then remove the table row.
            let row = deletionelement.closest('tr');
            row.remove();
            Toast.add(getString('configdeleted', 'block_stash'), {
                type: 'info',
                autohide: true,
                closeButton: true,
            });
        });
    });
};

const registerEditEvent = (courseid, editobject) => {
    editobject.addEventListener('click', (e) => {
        e.preventDefault();
        let jsondata = JSON.parse(editobject.dataset.json);
        let details = {'removalid': editobject.dataset.id, 'quizid': editobject.dataset.quiz, 'items': jsondata};
        showModal(courseid, details);
    });
};

export const init = (courseid) => {

    let configbutton = document.querySelector('.block-config-removal');
    configbutton.addEventListener('click', (e) => {
        e.preventDefault();
        showModal(courseid);
    });

    let deletebutton = document.querySelectorAll('.block-stash-removal-icon');
    deletebutton.forEach((deleteobject) => {
        registerDeleteEvent(courseid, deleteobject);
    });

    let editbutton = document.querySelectorAll('.block-stash-removal-edit');
    editbutton.forEach((editobject) => {
        registerEditEvent(courseid, editobject);
    });
};

const fetchQuizData = (courseid) => Ajax.call([{
    methodname: 'block_stash_get_removal_activities',
    args: {courseid: courseid}
}])[0];

const saveRemovalEntry = (courseid, cmid, items) => Ajax.call([{
    methodname: 'block_stash_save_removal',
    args: {'courseid': courseid, 'cmid': cmid, 'items': items}
}])[0];

const updateRemovalEntry = (courseid, cmid, items, removalid) => Ajax.call([{
    methodname: 'block_stash_save_removal',
    args: {'courseid': courseid, 'cmid': cmid, 'items': items, 'removalid': removalid}
}])[0];

const deleteRemovalEntry = (courseid, removalid) => Ajax.call([{
    methodname: 'block_stash_delete_removal',
    args: {'courseid': courseid, 'removalid': removalid}
}])[0];
