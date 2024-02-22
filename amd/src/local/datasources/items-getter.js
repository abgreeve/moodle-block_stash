import Ajax from 'core/ajax';

let Items = [];

export const getItems = async (courseid) => {
    if (Items.length === 0) {
        Items = await getItemsData(courseid);
    } else {
        window.console.log('cached version');
    }
    return Items;
};

export const getIndexedItems = async (courseid) => {

    let itemobject = [];
    let itemdata = await getItems(courseid);
    itemdata.items.forEach((item) => {
        itemobject[item.id] = item;
    });
    return itemobject;

};

// TODO implement this?
// export const addItem = () => {

// };

export const resetCache = () => {
    Items = [];
};

const getItemsData = (courseid) => Ajax.call([{
    methodname: 'block_stash_get_items',
    args: {courseid: courseid}
}])[0];
