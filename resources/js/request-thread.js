// Request conversation (requester ↔ foundation): send without reloading and refresh every 15 seconds.
document.querySelectorAll('[data-thread]').forEach(thread => {
    const list = thread.querySelector('[data-thread-list]');
    const form = thread.querySelector('[data-thread-form]');
    const url = thread.dataset.thread;
    const scrollDown = () => { list.scrollTop = list.scrollHeight; };

    async function refresh() {
        try {
            const response = await fetch(url, { headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) return;
            const atBottom = list.scrollHeight - list.scrollTop - list.clientHeight < 40;
            list.innerHTML = await response.text();
            if (atBottom) scrollDown();
        } catch { /* offline: try again on the next tick */ }
    }

    form?.addEventListener('submit', async event => {
        event.preventDefault();
        const body = form.elements.body.value.trim();
        if (!body) return;
        const button = form.querySelector('[type=submit]');
        button.disabled = true;
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': form.elements._token.value },
                body: new FormData(form),
            });
            if (!response.ok) throw new Error(`Send failed (${response.status})`);
            form.elements.body.value = '';
            await refresh();
            scrollDown();
        } catch {
            form.submit(); // fall back to a normal form post
        } finally {
            button.disabled = false;
        }
    });

    form?.elements.body.addEventListener('keydown', event => {
        if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) form.requestSubmit();
    });

    scrollDown();
    setInterval(refresh, 15000);
});
