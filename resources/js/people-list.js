// "Add person" lists: each click adds a row for one person (last, first and middle name), up to the
// limit. Removing a row renumbers the others so the form sends people[0], people[1], ...
// People with alike names (two "Juan Dela Cruz") get a "No." box and must each be given a number;
// the rule matches FundingRules::similarNameGroups on the server.

// "Dela Cruz", "DELACRUZ" and "Dela Cruz." compare the same; accents are dropped (Peña → pena).
const nameKey = value => value.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]/g, '');

// Same first and last name, and middle names that do not differ (one left out, or a matching initial).
const alike = (a, b) => a.name === b.name && (!a.middle || !b.middle || a.middle === b.middle
    || ((a.middle.length === 1 || b.middle.length === 1) && a.middle[0] === b.middle[0]));

/** Groups of row indexes (two or more each) whose names are alike. */
export function similarNameGroups(people) {
    const keyed = people.map((person, index) => ({
        index,
        name: `${nameKey(person.first)}|${nameKey(person.last)}`,
        middle: nameKey(person.middle),
        filled: Boolean(nameKey(person.first) || nameKey(person.last)),
    })).filter(person => person.filled);
    const parent = new Map(keyed.map(person => [person.index, person.index]));
    const root = index => (parent.get(index) === index ? index : root(parent.get(index)));
    keyed.forEach((first, i) => keyed.slice(i + 1).forEach(second => {
        if (alike(first, second)) parent.set(root(second.index), root(first.index));
    }));
    const groups = new Map();
    keyed.forEach(person => groups.set(root(person.index), [...(groups.get(root(person.index)) ?? []), person.index]));

    return [...groups.values()].filter(group => group.length > 1);
}

document.querySelectorAll('[data-people]').forEach(box => {
    const list = box.querySelector('[data-people-list]');
    const template = box.querySelector('[data-people-template]');
    const addButton = box.querySelector('[data-people-add]');
    const count = box.querySelector('[data-people-count]');
    const alikeNote = box.querySelector('[data-people-alike]');
    const alikeText = box.querySelector('[data-people-alike-text]');
    const name = box.dataset.name;
    const limit = Number(box.dataset.limit) || Infinity;
    const rows = () => [...list.querySelectorAll('[data-people-row]')];
    const part = (row, key) => row.querySelector(`[data-part=${key}]`);

    function markAlike() {
        const people = rows().map(row => ({ last: part(row, 'last').value, first: part(row, 'first').value, middle: part(row, 'middle').value }));
        const groups = similarNameGroups(people);
        const numbered = new Set(groups.flat());
        rows().forEach((row, index) => {
            row.classList.toggle('needs-number', numbered.has(index));
            part(row, 'number').required = numbered.has(index);
        });
        list.classList.toggle('has-numbers', groups.length > 0);
        alikeNote.hidden = groups.length === 0;
        const positions = group => {
            const labels = group.map(index => `#${index + 1}`);
            return `${labels.slice(0, -1).join(', ')} and ${labels.at(-1)}`;
        };
        alikeText.textContent = groups.map(group => {
            const first = people[group[0]];
            return `persons ${positions(group)} (${`${first.first} ${first.last}`.replace(/\s+/g, ' ').trim()})`;
        }).join('; ');
    }

    function renumber() {
        rows().forEach((row, index) => {
            row.querySelectorAll('input').forEach(input => {
                input.name = `${name}[${index}][${input.dataset.part}]`;
                input.setAttribute('aria-label', input.dataset.part === 'number'
                    ? `Number for person ${index + 1} (similar name)`
                    : `${input.placeholder.replace(' (optional)', '')}, person ${index + 1}`);
            });
        });
        const total = rows().length;
        const filled = rows().filter(row => part(row, 'last').value.trim() || part(row, 'first').value.trim()).length;
        addButton.disabled = total >= limit;
        count.textContent = Number.isFinite(limit) ? `${filled} of ${limit} people` : `${filled} ${filled === 1 ? 'person' : 'people'}`;
        count.classList.toggle('is-over', total > limit);
        markAlike();
    }

    addButton.addEventListener('click', () => {
        if (rows().length >= limit) return;
        list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(rows().length)));
        renumber();
        part(rows().at(-1), 'last').focus();
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
