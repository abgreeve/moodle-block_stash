import Ajax from 'core/ajax';
import Templates from 'core/templates';

export const init = async () => {
    // Fetch items to populate the select box.
    let maindiv = document.querySelector('.block-stash-item-adder');
    let courseid = maindiv.dataset.courseId;
    let itemdata = await fetchItemData(courseid);

    // populate the list
    let listnode = maindiv.querySelector('.dropdown-list ul');

    for (let item of itemdata.items) {
        // window.console.log(item);
        let listitem = document.createElement('li');
        listitem.innerHTML = item.name;
        listitem.dataset.itemid = item.id;
        listitem.dataset.imgurl = item.imageurl;
        listitem.addEventListener('click', addItemToTable);
        listnode.appendChild(listitem);
    }

    // window.console.log(listnode);
    // window.console.log(itemdata);

    let selector = document.querySelector('.item-adder-add');
    selector.addEventListener('click', (e) => {
        let currentbutton = e.currentTarget;
        let dropdownlist = currentbutton.parentNode.querySelector('.dropdown-list');
        dropdownlist.style.display = (dropdownlist.style.display == 'none') ? 'block' : 'none';
    });

    document.addEventListener('mouseup', (e) => {
        let dropdowncontainer = document.querySelector('.dropdown-container');
        let currentelement = e.target;
        let dropdownlist = dropdowncontainer.querySelector('.dropdown-list');
        if (!dropdowncontainer.contains(currentelement)) {
            dropdownlist.style.display = 'none';
        }
    });
};

const addItemToTable = (e) => {
    let itemnode = e.currentTarget;
    let data = {
        itemid: itemnode.dataset.itemid,
        imageurl: itemnode.dataset.imgurl,
        name: itemnode.innerText,
        quantity: 1
    };

    let type = 'gain';

    let templatename = (type === 'gain') ? 'block_stash/trade_add_item_detail' : 'block_stash/trade_loss_item_detail';
    Templates.render(templatename, data).done((html, js) => {
        let itemsbox = document.querySelector('.block_stash_item_box[data-type="' + type + '"]');
        Templates.appendNodeContents(itemsbox, html, js);
        registerActions();
    });
};

const registerActions = () => {
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

const fetchItemData = (courseid) => Ajax.call([{
    methodname: 'block_stash_get_items',
    args: {courseid: courseid}
}])[0];
