// "Add person" lists: each click adds a row for one person (last, first and middle name), up to the
// limit. Removing a row renumbers the others so the form sends people[0], people[1], ...
document.querySelectorAll('[data-people]').forEach(box => {
    const list = box.querySelector('[data-people-list]');
    const template = box.querySelector('[data-people-template]');
    const addButton = box.querySelector('[data-people-add]');
    const count = box.querySelector('[data-people-count]');
    const name = box.dataset.name;
    const limit = Number(box.dataset.limit) || Infinity;
    const rows = () => [...list.querySelectorAll('[data-people-row]')];

    function renumber() {
        rows().forEach((row, index) => {
            row.querySelectorAll('input').forEach(input => {
                input.name = `${name}[${index}][${input.dataset.part}]`;
                input.setAttribute('aria-label', `${input.placeholder.replace(' (optional)', '')}, person ${index + 1}`);
            });
        });
        const total = rows().length;
        const filled = rows().filter(row => row.querySelector('[data-part=last]').value.trim() || row.querySelector('[data-part=first]').value.trim()).length;
        addButton.disabled = total >= limit;
        count.textContent = Number.isFinite(limit) ? `${filled} of ${limit} people` : `${filled} ${filled === 1 ? 'person' : 'people'}`;
        count.classList.toggle('is-over', total > limit);
    }

    addButton.addEventListener('click', () => {
        if (rows().length >= limit) return;
        list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(rows().length)));
        renumber();
        rows().at(-1).querySelector('[data-part=last]').focus();
    });

    list.addEventListener('click', event => {
        const remove = event.target.closest('[data-people-remove]');
        if (!remove) return;
        if (rows().length === 1) {
            remove.closest('[data-people-row]').querySelectorAll('input').forEach(input => { input.value = ''; });
        } else {
            remove.closest('[data-people-row]').remove();
        }
        renumber();
    });

    list.addEventListener('input', renumber);
    renumber();
});
