/**
 * Count-up for the benefits numbers.
 *
 * Counts every integer inside the element's text, so "7-14" runs as one odometer over
 * both ends rather than needing markup split into two counters. The template opts in per
 * item with data-count; nothing counts by default.
 *
 * ponytail: no easing curve and no configurable duration. One linear-out ramp over 900ms
 * is what this section needs, and a tween library for four numbers on one page is the
 * kind of thing that gets deleted in six months.
 */

import { isFull } from '../lib/motion.js';

const DURATION = 900;

// Cubic ease-out: fast off the mark, settles onto the final value instead of slamming
// into it. The last ~200ms is where a counter reads as deliberate rather than as a
// number that failed to load.
const ease = (t) => 1 - (1 - t) ** 3;

/**
 * Run one element's counter.
 *
 * @param {HTMLElement} el Element whose text contains the numbers.
 */
function run(el) {
	const final = el.textContent;
	const parts = final.split(/(\d+)/); // odd indices are the numbers
	const targets = parts.map((p) => (/^\d+$/.test(p) ? Number(p) : null));

	if (!targets.some((t) => t !== null)) return;

	// Hold the final width before counting: "7-14" is wider than "0-0", and letting the
	// element resize mid-count shoves the unit and the label around for 900ms. The
	// section is a four-column grid and the reflow is visible across all of it.
	el.style.minInlineSize = `${el.getBoundingClientRect().width}px`;

	const start = performance.now();

	const frame = (now) => {
		const t = Math.min((now - start) / DURATION, 1);
		const k = ease(t);

		el.textContent = parts
			.map((p, i) => (targets[i] === null ? p : String(Math.round(targets[i] * k))))
			.join('');

		if (t < 1) {
			requestAnimationFrame(frame);
			return;
		}

		// Never leave the DOM holding a computed value — the last frame writes the
		// original string back verbatim, so a rounding slip cannot change a number the
		// client has to honour.
		el.textContent = final;
		el.style.minInlineSize = '';
	};

	requestAnimationFrame(frame);
}

/**
 * Watch [data-count] elements and count them once, on entry.
 *
 * @param {string} [selector] Elements to count.
 */
export function initCount(selector = '[data-count]') {
	const targets = document.querySelectorAll(selector);
	if (!targets.length) return;

	// Reduced motion and no-IO both land on the same answer: the number is already in the
	// markup, so doing nothing shows it. There is no fallback to write.
	if (!isFull() || !('IntersectionObserver' in window)) return;

	const io = new IntersectionObserver(
		(entries) => {
			for (const entry of entries) {
				if (!entry.isIntersecting) continue;
				io.unobserve(entry.target);
				run(entry.target);
			}
		},
		{ threshold: 0.6 }
	);

	targets.forEach((el) => io.observe(el));
}
