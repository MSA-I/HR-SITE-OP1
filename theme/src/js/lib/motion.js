/**
 * The motion gate. Every module consults this rather than reading matchMedia itself.
 *
 * The mode is set by an inline <head> script before first paint; this module only
 * reads it, keeps it live, and wires the footer opt-out.
 */

const root = document.documentElement;

export const isFull = () => root.dataset.motion === 'full';

/** Follow the OS preference live, unless the user has made an explicit choice. */
matchMedia('(prefers-reduced-motion: reduce)').addEventListener('change', (e) => {
	if (localStorage.getItem('hr-motion')) return;
	root.dataset.motion = e.matches ? 'reduced' : 'full';
});

export function initMotionToggle() {
	const button = document.querySelector('[data-motion-toggle]');
	if (!button) return;

	const sync = () => button.setAttribute('aria-pressed', String(!isFull()));
	sync();

	button.addEventListener('click', () => {
		const next = isFull() ? 'reduced' : 'full';
		root.dataset.motion = next;
		localStorage.setItem('hr-motion', next);
		sync();
	});
}

/**
 * Reveal elements once as they enter view.
 *
 * One-shot by design — it unobserves after firing, so scrolling back up does not
 * replay anything and the observer list shrinks as the page is read.
 *
 * @param {string} selector Elements to watch.
 * @param {object} [options] IntersectionObserver options.
 */
export function observeReveal(selector, options = {}) {
	const targets = document.querySelectorAll(selector);
	if (!targets.length) return;

	// Reduced motion still reveals (via opacity) — the CSS decides how. Only a total
	// absence of IO support skips straight to the visible state.
	if (!('IntersectionObserver' in window)) {
		targets.forEach((el) => el.classList.add('is-in'));
		return;
	}

	const io = new IntersectionObserver(
		(entries) => {
			for (const entry of entries) {
				if (!entry.isIntersecting) continue;
				entry.target.classList.add('is-in');
				io.unobserve(entry.target);
			}
		},
		{ rootMargin: '0px 0px -12% 0px', threshold: 0.15, ...options }
	);

	targets.forEach((el) => io.observe(el));
}

/**
 * Promote to a layer only while the pointer is actually over the element.
 *
 * @param {string} selector Elements to manage.
 */
export function manageWillChange(selector) {
	for (const el of document.querySelectorAll(selector)) {
		el.addEventListener('pointerenter', () => el.classList.add('is-animating'));
		el.addEventListener('pointerleave', () => el.classList.remove('is-animating'));
	}
}
