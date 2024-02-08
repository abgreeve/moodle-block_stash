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
        // window.console.log('get the form data, and save');
        let itemsinfo = document.querySelectorAll('.block-stash-quantity');
        // window.console.log(itemsinfo);
        let items = [];
        itemsinfo.forEach((item) => {
            // window.console.log(item);
            let itemid = item.closest('.block-stash-trade-item').getAttribute('data-id');
            items.push({
                'itemid': parseInt(itemid),
                'quantity': parseInt(item.value)
            });
        });
        window.console.log(items);
        // Quiz data
        let quizinfo = document.querySelector('.block-stash-quiz-select').value;
        window.console.log(quizinfo);
        // Now send off to be saved.
        saveRemovalEntry(courseid, parseInt(quizinfo), items);
    });

    modal.getRoot().on(ModalEvents.hidden, () => {
        modal.destroy();
    });
    modal.show();
};

export const init = (courseid) => {

    let configbutton = document.querySelector('.block-config-removal');
    configbutton.addEventListener('click', (e) => {
        e.preventDefault();
        showModal(courseid);
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
