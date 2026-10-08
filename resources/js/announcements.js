// Announcement slideshow (home feed and landing page). With two or more announcements the next
// one slides in every few seconds, and after the last it carries on to the first (a copy of the
// first slide sits at the end so the loop never jumps backwards). Hover, keyboard focus, a hidden
// tab or the pause button stop it.
const INTERVAL_MS = 6000;

document.querySelectorAll('[data-carousel]').forEach(carousel => {
    const track = carousel.querySelector('[data-carousel-track]');
    const slides = [...track.querySelectorAll('[data-carousel-slide]')];
    const dots = [...carousel.querySelectorAll('[data-carousel-dot]')];
    const pauseButton = carousel.querySelector('[data-carousel-pause]');
    const count = slides.length;
    if (count < 2) return;

    const clone = slides[0].cloneNode(true);
    clone.setAttribute('aria-hidden', 'true');
    clone.removeAttribute('data-carousel-slide');
    clone.querySelectorAll('img').forEach(img => img.setAttribute('alt', ''));
    track.appendChild(clone);

    let index = 0;
    let timer = null;
    let pausedByUser = false;
    let hovering = false;

    const show = (next, animate = true) => {
        index = next;
        track.style.transition = animate ? '' : 'none';
        track.style.transform = `translateX(-${index * 100}%)`;
        const real = index % count;
        slides.forEach((slide, i) => {
            slide.setAttribute('aria-hidden', String(i !== real));
            slide.inert = i !== real;
        });
        dots.forEach((dot, i) => dot.toggleAttribute('aria-current', i === real));
        if (!animate) {
            track.getBoundingClientRect(); // apply the jump before transitions come back
            track.style.transition = '';
        }
    };

    // After sliding onto the copy of the first slide, jump to the real first slide unseen.
    track.addEventListener('transitionend', event => {
        if (event.target === track && index === count) show(0, false);
    });

    const running = () => !pausedByUser && !hovering && document.visibilityState === 'visible';
    const schedule = () => {
        clearInterval(timer);
        timer = running() ? setInterval(() => show(index >= count ? 1 : index + 1), INTERVAL_MS) : null;
    };
    const go = next => {
        if (index === count) show(0, false);
        show((next + count) % count);
        schedule();
    };

    carousel.querySelector('[data-carousel-next]')?.addEventListener('click', () => go(index + 1));
    carousel.querySelector('[data-carousel-prev]')?.addEventListener('click', () => go(index - 1));
    dots.forEach((dot, i) => dot.addEventListener('click', () => go(i)));
    pauseButton?.addEventListener('click', () => {
        pausedByUser = !pausedByUser;
        pauseButton.setAttribute('aria-pressed', String(pausedByUser));
        pauseButton.setAttribute('aria-label', pausedByUser ? 'Play the slideshow' : 'Pause the slideshow');
        pauseButton.textContent = pausedByUser ? '▶' : '❚❚';
        schedule();
    });
    carousel.addEventListener('mouseenter', () => { hovering = true; schedule(); });
    carousel.addEventListener('mouseleave', () => { hovering = false; schedule(); });
    carousel.addEventListener('focusin', () => { hovering = true; schedule(); });
    carousel.addEventListener('focusout', event => {
        if (!carousel.contains(event.relatedTarget)) { hovering = false; schedule(); }
    });
    carousel.addEventListener('keydown', event => {
        if (event.key === 'ArrowRight') go(index + 1);
        if (event.key === 'ArrowLeft') go(index - 1);
    });
    document.addEventListener('visibilitychange', schedule);

    show(0, false);
    schedule();
});
