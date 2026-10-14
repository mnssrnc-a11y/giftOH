// Tutorials: short, hands-on walkthroughs of each page (config/tour.php). A card points at one part
// of the page at a time; "Your turn" steps wait for the person to click or fill in the highlighted
// part and move on by themselves. A tutorial starts by itself the first time its page (or workspace
// section) is opened, and again from the "?" button at the top or Help & tour. On a phone the card
// sits at the bottom of the screen (at the top while typing).
const config = window.giftOfHopeTour;

if (config?.tutorials) {
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const NEXT_KEY = 'giftofhope:tutorial-next';
    const storage = {
        take() { try { const id = sessionStorage.getItem(NEXT_KEY); sessionStorage.removeItem(NEXT_KEY); return id; } catch { return null; } },
        put(id) { try { sessionStorage.setItem(NEXT_KEY, id); } catch { /* private mode: the next one simply starts if unseen */ } },
    };
    const seen = new Set(config.seen ?? []);
    let forced = config.start;
    let tutorial = null;
    let steps = [];
    let index = 0;
    let target = null;
    let layer = null;
    let returnFocus = null;
    let fillTimer = null;
    let startTimer = null;

    // "bell" means [data-tour="bell"]; anything that looks like CSS (".fr-stepper", "#documents") is a selector.
    function findAll(name) {
        try { return [...document.querySelectorAll(/[.#[\s:>]/.test(name) ? name : `[data-tour="${name}"]`)]; } catch { return []; }
    }

    function shown(element) {
        const box = element.getBoundingClientRect();
        return box.width > 0 && box.height > 0 && box.right > 0 && box.left < innerWidth && getComputedStyle(element).visibility !== 'hidden';
    }

    // The first target on screen (the sidebar is off-screen on phones, the tab bar on computers; other sections are hidden).
    function findTarget(step) {
        for (const name of [].concat(step.target ?? [])) {
            const element = findAll(name).find(shown);
            if (element) return element;
        }
        return null;
    }

    const usable = step => !step.target || findTarget(step);

    // The section of a workspace page (#funding, #accounts…), or null on an ordinary page.
    const currentSection = () => document.querySelector('[data-admin-page].is-active')?.dataset.adminPage
        ?? document.querySelector('[data-sa-page]:not([hidden])')?.dataset.saPage ?? null;
    const tutorialHere = () => config.tutorials.find(t => !t.section || t.section === currentSection()) ?? null;

    // Elements that stay put while the page scrolls (top bar, bottom tab bar).
    function pinned(element) {
        for (let node = element; node && node !== document.body; node = node.parentElement) {
            if (['fixed', 'sticky'].includes(getComputedStyle(node).position)) return true;
        }
        return false;
    }

    function position() {
        if (!layer) return;
        const spot = layer.querySelector('.tour-spot');
        const card = layer.querySelector('.tour-card');
        const phone = innerWidth <= 640;
        card.classList.toggle('is-sheet', phone);
        if (!target) {
            spot.hidden = true;
            card.classList.add('is-center');
            card.classList.remove('is-top');
            card.style.top = card.style.left = '';
            return;
        }
        card.classList.remove('is-center');
        const box = target.getBoundingClientRect();
        const pad = 6;
        Object.assign(spot.style, { top: `${box.top - pad}px`, left: `${box.left - pad}px`, width: `${box.width + pad * 2}px`, height: `${box.height + pad * 2}px` });
        spot.hidden = false;
        if (phone) {
            card.style.top = card.style.left = '';
            if (pinned(target)) {
                // Top bar or bottom bar: the sheet goes to the other half of the screen.
                card.classList.toggle('is-top', box.top + box.height / 2 > innerHeight / 2);
                return;
            }
            // Typing: the keyboard covers the bottom, so the sheet goes on top and the field just below it.
            const typing = steps[index]?.do === 'fill';
            card.classList.toggle('is-top', typing);
            const room = card.offsetHeight + 24;
            const shift = typing
                ? (box.top < room || box.bottom > innerHeight * 0.55 ? box.top - room : 0)
                : (box.bottom > innerHeight - room ? Math.min(box.bottom - (innerHeight - room), box.top - 70) : 0);
            if (Math.abs(shift) > 4) window.scrollBy({ top: shift, behavior: 'auto' });
            return;
        }
        // Below, above, right of or left of the part, whichever has room; otherwise below.
        const width = card.offsetWidth;
        const height = card.offsetHeight;
        const gap = 14;
        const clampTop = value => Math.min(Math.max(12, value), innerHeight - height - 12);
        const clampLeft = value => Math.min(Math.max(12, value), innerWidth - width - 12);
        const centeredLeft = clampLeft(box.left + box.width / 2 - width / 2);
        const places = [
            [box.bottom + gap + height <= innerHeight - 12, box.bottom + gap, centeredLeft],
            [box.top - gap - height >= 12, box.top - gap - height, centeredLeft],
            [box.right + gap + width <= innerWidth - 12, clampTop(box.top), box.right + gap],
            [box.left - gap - width >= 12, clampTop(box.top), box.left - gap - width],
        ];
        const [, top, left] = places.find(([fits]) => fits) ?? [true, clampTop(box.bottom + gap), centeredLeft];
        card.style.top = `${top}px`;
        card.style.left = `${left}px`;
    }

    function render() {
        const step = steps[index];
        const last = index === steps.length - 1;
        target = findTarget(step);
        const handsOn = Boolean(step.do && target);
        layer.classList.toggle('is-hands-on', handsOn);
        layer.querySelector('.tour-spot').classList.toggle('is-turn', handsOn);
        if (target) target.scrollIntoView({ block: 'center' });
        const card = layer.querySelector('.tour-card');
        card.setAttribute('aria-modal', String(!handsOn));
        card.innerHTML = `
            <p class="tour-count">${escapeHtml(tutorial.title)} · ${index + 1} of ${steps.length}</p>
            <h2 id="tour-title">${handsOn ? '<span aria-hidden="true">👉 </span>' : ''}${escapeHtml(step.title)}</h2>
            <p id="tour-text">${escapeHtml(step.text)}</p>
            <div class="tour-dots" aria-hidden="true">${steps.map((_, i) => `<span class="${i === index ? 'is-on' : ''}"></span>`).join('')}</div>
            <div class="tour-actions">
                <button type="button" class="tour-skip" data-tour-skip>${last ? 'Close' : 'Skip'}</button>
                <span>
                    ${index > 0 ? '<button type="button" class="tour-back" data-tour-back>Back</button>' : ''}
                    <button type="button" class="tour-next" data-tour-next>${last ? 'Done' : 'Next'}</button>
                </span>
            </div>`;
        // Place it now, and again on the next frame once scrolling has settled.
        position();
        requestAnimationFrame(position);
        // Your turn: the highlighted field or button gets the focus, so the keyboard works too.
        const control = handsOn && (target.matches('input, select, textarea, button, a[href]') ? target : target.querySelector('input:not([type=hidden]), select, textarea, button, a[href]'));
        (control || card.querySelector('[data-tour-next]')).focus({ preventScroll: true });
    }

    // Move to the next (or previous) step that is on this page; past the last one, it is finished.
    function go(delta) {
        clearTimeout(fillTimer);
        let next = index + delta;
        while (steps[next] && !usable(steps[next])) next += delta;
        if (next >= steps.length) return close();
        if (next < 0) return;
        index = next;
        render();
    }

    function open(chosen) {
        if (!chosen) return;
        if (layer) close({ save: false });
        steps = chosen.steps.filter(usable);
        if (!steps.length) return;
        tutorial = chosen;
        index = 0;
        returnFocus = document.activeElement;
        layer = document.createElement('div');
        layer.className = 'tour-layer';
        layer.innerHTML = '<div class="tour-spot" hidden></div><section class="tour-card" role="dialog" aria-modal="true" aria-labelledby="tour-title" aria-describedby="tour-text"></section>';
        document.body.appendChild(layer);
        render();
    }

    function close({ save = true } = {}) {
        if (!layer) return;
        clearTimeout(fillTimer);
        // Back to where the person was, unless they are busy in the page (typing, a dialog that opened).
        const focusInCard = layer.contains(document.activeElement);
        layer.remove();
        layer = null;
        target = null;
        if (focusInCard) returnFocus?.focus?.({ preventScroll: true });
        if (save && !seen.has(tutorial.id)) {
            seen.add(tutorial.id);
            // keepalive: the last step often opens another page.
            fetch(config.completeUrl, {
                method: 'POST', keepalive: true,
                headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: tutorial.id }),
            }).catch(() => {});
        }
        tutorial = null;
        // Drop ?tutorial= and ?tour=1 so a reload does not start it again.
        const url = new URL(location.href);
        if (url.searchParams.has('tutorial') || url.searchParams.has('tour')) {
            url.searchParams.delete('tutorial');
            url.searchParams.delete('tour');
            history.replaceState(history.state, '', url);
        }
    }

    // The person did what the step asked: clicking moves on right after the click has done its work.
    function stepDone() {
        const step = steps[index];
        if (step.next) storage.put(step.next);
        if (index === steps.length - 1) close();
        else setTimeout(() => go(1), 80);
    }

    // Start this page's tutorial if it was asked for, or never seen; show the "?" button if there is one.
    function maybeStart() {
        const here = tutorialHere();
        document.querySelectorAll('[data-tutorial-start]').forEach(button => { button.hidden = !here; });
        const next = storage.take();
        if (layer || !here) return;
        const asked = forced === here.id || next === here.id;
        if (forced === here.id) forced = null;
        if (asked || !seen.has(here.id)) open(here);
    }
    const scheduleStart = () => { clearTimeout(startTimer); startTimer = setTimeout(maybeStart, 500); };

    // While a tutorial is open, only its card and the highlighted part respond to the mouse.
    function guard(event) {
        if (!layer) {
            if (event.type === 'click' && event.target.closest('[data-tutorial-start]')) { event.preventDefault(); open(tutorialHere()); }
            return;
        }
        if (event.target.closest('.tour-card')) {
            if (event.type !== 'click') return;
            if (event.target.closest('[data-tour-next]')) go(1);
            else if (event.target.closest('[data-tour-back]')) go(-1);
            else if (event.target.closest('[data-tour-skip]')) close();
            return;
        }
        const step = steps[index];
        if (step?.do && target?.contains(event.target)) {
            if (event.type === 'click' && step.do === 'click') stepDone();
            return;
        }
        event.preventDefault();
        event.stopPropagation();
    }
    document.addEventListener('mousedown', guard, true);
    document.addEventListener('click', guard, true);

    // Filling in: a choice moves on at once, typing after a short pause.
    function filled(event) {
        if (!layer || steps[index]?.do !== 'fill' || !target?.contains(event.target)) return;
        const field = event.target;
        const typing = field.tagName === 'TEXTAREA' || (field.tagName === 'INPUT' && !['checkbox', 'radio', 'file', 'range', 'color'].includes(field.type));
        if (typing && !String(field.value).trim()) return;
        clearTimeout(fillTimer);
        fillTimer = setTimeout(() => go(1), typing ? 1200 : 450);
    }
    document.addEventListener('input', filled, true);
    document.addEventListener('change', filled, true);

    document.addEventListener('keydown', event => {
        if (!layer) return;
        if (event.key === 'Escape') { close(); return; }
        if (event.target.closest('input, select, textarea')) return;
        if (event.key === 'ArrowRight') go(1);
        if (event.key === 'ArrowLeft') go(-1);
        // Keep keyboard focus inside the card, except on "your turn" steps.
        if (event.key === 'Tab' && !layer.classList.contains('is-hands-on')) {
            const buttons = [...layer.querySelectorAll('button')];
            if (event.shiftKey && document.activeElement === buttons[0]) { event.preventDefault(); buttons.at(-1).focus(); }
            else if (!event.shiftKey && document.activeElement === buttons.at(-1)) { event.preventDefault(); buttons[0].focus(); }
        }
    });

    addEventListener('resize', position);
    addEventListener('scroll', position, { passive: true });
    // Workspace pages: each section has its own tutorial; leaving a section ends its tutorial.
    addEventListener('ws:section', () => {
        if (layer && tutorial.section && tutorial.section !== currentSection()) close({ save: false });
        scheduleStart();
    });
    // Let the page settle (sections, slideshow) before measuring where things are.
    scheduleStart();
}
