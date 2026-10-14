// Pampanga addresses: the barangay list follows the city / municipality chosen in the same form
// (resources/views/partials/barangay-select.blade.php). Without JavaScript every barangay stays
// listed, grouped by town, and the server checks that the two match.
document.querySelectorAll('[data-place-barangay]').forEach(select => {
    const town = select.form?.querySelector('[data-place-town]');
    if (!town) return;
    const byTown = Object.fromEntries([...select.querySelectorAll('optgroup')].map(group => [group.label, [...group.children].map(option => option.value)]));
    const chosen = select.value;

    function fill(keep) {
        const names = byTown[town.value] ?? [];
        select.replaceChildren(
            new Option(town.value ? 'Choose the barangay…' : 'Choose the city or town first', ''),
            ...names.map(name => new Option(name, name, false, name === keep)),
        );
        select.disabled = !town.value;
    }

    town.addEventListener('change', () => {
        fill('');
        // Previews and error messages watching the barangay see that it was cleared.
        ['input', 'change'].forEach(type => select.dispatchEvent(new Event(type, { bubbles: true })));
    });
    fill(chosen);
});
