/**
 * By Light — the one-shot demo, and nothing else.
 *
 * The section does not need this file. The control is four native radios and :has(), so
 * with JS disabled every stop is still drivable and the feature is complete. All this does
 * is play the argument once, unprompted, and then get out of the way for good.
 *
 * It rests on 23:00 rather than returning to 07:00, because 23:00 is where the lamp is lit
 * and for sale. The demo IS the pitch.
 */

import { isFull } from './lib/motion.js';

export function initByLight() {
	const section = document.querySelector('[data-byl]');
	if (!section) return;

	// Never autoplay under reduced motion. The stops still work; the room simply waits to
	// be driven, which is the whole point of that mode.
	if (!isFull()) return;

	const radios = [...section.querySelectorAll('.byl__radio')];
	if (radios.length < 2) return;

	const frames = [...section.querySelectorAll('.byl__frame')];

	let timers = [];
	let done = false;

	/*
	 * focusin is the critical one. pointerdown and keydown cover someone reaching for the
	 * control; focusin covers a screen-reader user landing on the radio group — and a
	 * value that changes under their cursor is exactly the bug this line exists to
	 * prevent. `done` is separate from clearing the timers because the observer can still
	 * be pending: without it, interacting BEFORE the section scrolls into view would cancel
	 * nothing and the demo would hijack the control later.
	 */
	const cancel = () => {
		done = true;
		timers.forEach(clearTimeout);
		timers = [];
	};

	for (const type of ['pointerdown', 'keydown', 'focusin']) {
		section.addEventListener(type, cancel, { once: true });
	}

	const observer = new IntersectionObserver(
		async (entries) => {
			for (const entry of entries) {
				if (!entry.isIntersecting || done) continue;
				observer.disconnect();

				/*
				 * Wait for all four frames to decode before playing.
				 *
				 * They are lazy, and the demo is a 3.3s cross-fade between them. Firing it
				 * the instant the section is 55% visible means dissolving to an image that
				 * has not arrived — the room flashes to the ink ground and back, which
				 * reads as a bug rather than as nightfall. decode() rather than a load
				 * listener: a decoded image is one that can be painted this frame, which is
				 * the thing actually being waited on.
				 *
				 * Failures are swallowed on purpose. If a frame will not decode the demo
				 * simply does not play, and the control still works — a broken image must
				 * not take the feature down with it.
				 */
				await Promise.all(frames.map((img) => img.decode().catch(() => {})));
				if (done) return;

				// 07 is already checked, so this walks 12 -> 18 -> 23 and stops there.
				radios.forEach((radio, i) => {
					timers.push(setTimeout(() => (radio.checked = true), i * 1100));
				});
			}
		},
		{ threshold: 0.55 }
	);

	observer.observe(section);
}
