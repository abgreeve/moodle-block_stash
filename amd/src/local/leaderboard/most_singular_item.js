export const init = (id, itemid) => {
    const formelement = document.querySelector('input[name="leaderboard-options-' + id + '"]');
    formelement.dataset.options = itemid;
    const options = document.querySelector('select[name="msi_options"]');
    options.addEventListener('change', (e) => {
        formelement.dataset.options = e.currentTarget.value;
    });
};
