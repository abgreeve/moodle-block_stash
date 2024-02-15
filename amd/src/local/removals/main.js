import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';
import Templates from 'core/templates';
import * as tradeAdder from 'block_stash/local/trade_adder/main';
import Ajax from 'core/ajax';

const showModal = async(courseid) => {
    const modal = await buildModal(courseid);
    displayModal(modal, courseid);
};

const buildModal = async(courseid) => {

    // Fetch quizzes.
    let quizzes = await fetchItemData(courseid);

    // let items = await Item.getItem(itemId);
    // let context = items.getData();
    let context = {'courseid': courseid, 'quizzes': quizzes.activities};

    return ModalFactory.create({
        title: context.name,
        body: Templates.render('block_stash/local/removal/removal_form', context),
        type: ModalFactory.types.SAVE_CANCEL
    });
};

const displayModal = async(modal, courseid) => {

    modal.getRoot().on(ModalEvents.bodyRendered, () => {
        tradeAdder.init();
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
    let removalid = await saveRemovalEntry(courseid, parseInt(cmid), items);
    let context = {
        'cmname': quizselect.item(quizselect.selectedIndex).text,
        'courseid': courseid,
        'removalid': removalid,
        'items': returnitemdata
    };
    // window.console.log(context);
    Templates.render('block_stash/local/removal/table_row', context).then((html, js) => {
        let tableobject = document.querySelector('.block-stash-removal-body');
        let things = Templates.appendNodeContents(tableobject, html, js);
        registerDeleteEvent(courseid, things[0].querySelector('.block-stash-removal-icon'));
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
        });
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
};

const fetchItemData = (courseid) => Ajax.call([{
    methodname: 'block_stash_get_removal_activities',
    args: {courseid: courseid}
}])[0];

const saveRemovalEntry = (courseid, cmid, items) => Ajax.call([{
    methodname: 'block_stash_save_removal',
    args: {'courseid': courseid, 'cmid': cmid, 'items': items}
}])[0];

const deleteRemovalEntry = (courseid, removalid) => Ajax.call([{
    methodname: 'block_stash_delete_removal',
    args: {'courseid': courseid, 'removalid': removalid}
}])[0];
