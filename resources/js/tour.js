// Guided tour: a card that walks a new user through their workspace, highlighting one part of the
// page at a time (data-tour="..." targets from config/tour.php). On a phone the card sits at the
// bottom of the screen. Finishing or skipping saves that the tour was seen, so it starts by itself
// only once; Help & tour can start it again.
const tour = window.giftOfHopeTour;

if (tour?.steps?.length) {
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    let layer = null;
    let index = 0;
    let target = null;
    let returnFocus = null;

    // The first target that is on screen (the sidebar is off-screen on phones, the tab bar on computers).
    function findTarget(step) {
        for (const name of step.targets ?? []) {
            const element = document.querySelector(`[data-tour="${name}"]`);
            if (!element) continue;
            const box = element.getBoundingClientRect();
            const style = getComputedStyle(element);
            if (box.width > 0 && box.height > 0 && box.right > 0 && box.left < innerWidth && style.visibility !== 'hidden' && style.display !== 'none') return element;
        }
        return null;
    }

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
            // Page content: sheet at the bottom, and the page scrolled so the part sits above it.
            card.classList.remove('is-top');
            const limit = innerHeight - card.offsetHeight - 24;
            const shift = box.bottom > limit ? Math.min(box.bottom - limit, box.top - 70) : 0;
            if (shift > 4) window.scrollBy({ top: shift, behavior: 'auto' });
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
        const step = tour.steps[index];
        const last = index === tour.steps.length - 1;
        target = findTarget(step);
        if (target) target.scrollIntoView({ block: 'center' });
        const card = layer.querySelector('.tour-card');
        card.innerHTML = `
            <p class="tour-count">Step ${index + 1} of ${tour.steps.length}</p>
            <h2 id="tour-title">${escapeHtml(step.title)}</h2>
            <p id="tour-text">${escapeHtml(step.text)}</p>
            <div class="tour-dots" aria-hidden="true">${tour.steps.map((_, i) => `<span class="${i === index ? 'is-on' : ''}"></span>`).join('')}</div>
            <div class="tour-actions">
                <button type="button" class="tour-skip" data-tour-skip>${last ? 'Close' : 'Skip tour'}</button>
                <span>
                    ${index > 0 ? '<button type="button" class="tour-back" data-tour-back>Back</button>' : ''}
                    <button type="button" class="tour-next" data-tour-next>${last ? 'Finish' : 'Next'}</button>
                </span>
            </div>`;
        // Place it now, and again on the next frame once smooth scrolling has settled.
        position();
        requestAnimationFrame(position);
        card.querySelector('[data-tour-next]').focus({ preventScroll: true });
    }

    function open() {
        if (layer) return;
        returnFocus = document.activeElement;
        layer = document.createElement('div');
        layer.className = 'tour-layer';
        layer.innerHTML = '<div class="tour-spot" hidden></div><section class="tour-card" role="dialog" aria-modal="true" aria-labelledby="tour-title" aria-describedby="tour-text"></section>';
        document.body.appendChild(layer);
        document.body.classList.add('tour-open');
        index = 0;
        render();
    }

    function close(save = true) {
        if (!layer) return;
        layer.remove();
        layer = null;
        document.body.classList.remove('tour-open');
        returnFocus?.focus?.();
        if (save) {
            fetch(tour.completeUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' } }).catch(() => {});
        }
        // Drop ?tour=1 so a reload does not start it again.
        if (new URLSearchParams(location.search).has('tour')) {
            const url = new URL(location.href);
            url.searchParams.delete('tour');
            history.replaceState(history.state, '', url);
        }
    }

    document.addEventListener('click', event => {
        if (!layer) {
            if (event.target.closest('[data-tour-start]')) { event.preventDefault(); open(); }
            return;
        }
        if (event.target.closest('[data-tour-next]')) {
            if (index === tour.steps.length - 1) close();
            else { index++; render(); }
        } else if (event.target.closest('[data-tour-back]')) {
            index = Math.max(0, index - 1);
            render();
        } else if (event.target.closest('[data-tour-skip]')) {
            close();
        }
    });

    document.addEventListener('keydown', event => {
        if (!layer) return;
        if (event.key === 'Escape') close();
        if (event.key === 'ArrowRight' && index < tour.steps.length - 1) { index++; render(); }
        if (event.key === 'ArrowLeft' && index > 0) { index--; render(); }
        // Keep keyboard focus inside the tour card.
        if (event.key === 'Tab') {
            const buttons = [...layer.querySelectorAll('button')];
            const first = buttons[0];
            const lastButton = buttons.at(-1);
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); lastButton.focus(); }
            else if (!event.shiftKey && document.activeElement === lastButton) { event.preventDefault(); first.focus(); }
        }
    });

    addEventListener('resize', position);
    addEventListener('scroll', position, { passive: true });

    if (tour.autostart) {
        // Let the page settle (sections, slideshow) before measuring where things are.
        setTimeout(open, 600);
    }
}
