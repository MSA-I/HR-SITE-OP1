/**
 * M17 — the horizontal section: progress rail, pointer drag, arrows, keyboard.
 *
 * Native overflow does the scrolling and nothing here hijacks it. Touch, trackpad,
 * shift+wheel and screen readers already work; the mouse user is the one native overflow
 * leaves with nothing, because the scrollbar is hidden by design. Drag and arrows exist
 * for them, and touch is left strictly alone.
 */

import { isFull } from '../lib/motion.js';

/*
 * THE RTL SCROLL TRAP.
 *
 * In an RTL container, modern engines report scrollLeft as 0 at the RIGHT edge and
 * NEGATIVE travelling left. Anything reading an ABSOLUTE position must take the absolute
 * value; assuming a 0->positive range is the number one bug in RTL horizontal sections.
 *
 * Deltas are exempt, which is why the drag below has no direction branch and must not
 * grow one: it applies a delta to a scrollLeft it captured itself, so it never needs to
 * know which end of the range that number counts from.
 */
const magnitude = (track) => Math.abs(track.scrollLeft);
const maxScroll = (track) => track.scrollWidth - track.clientWidth;
const isRtl = (track) => getComputedStyle(track).direction === 'rtl';

/* Under reduced motion an arrow press should land, not glide. */
const behavior = () => (isFull() ? 'smooth' : 'auto');

/**
 * Every frame's snap position, as a scroll magnitude (0..max, whatever the direction).
 *
 * Measured off the live boxes rather than derived from a frame width: the frames are
 * deliberately unequal — the title and the end card are nothing like product width — so a
 * fixed step would drift off the snap points within two presses.
 *
 * @param {HTMLElement} track Scroll container.
 * @returns {number[]} Ascending scroll magnitudes.
 */
function snapPoints(track) {
	const pad = parseFloat(getComputedStyle(track).scrollPaddingInlineStart) || 0;
	const box = track.getBoundingClientRect();
	const here = magnitude(track);
	const max = maxScroll(track);
	const rtl = isRtl(track);

	return [...track.children].map((frame) => {
		const b = frame.getBoundingClientRect();
		// Distance from the scrollport's inline-start edge, which is the right in RTL.
		const fromStart = rtl ? box.right - b.right : b.left - box.left;
		return Math.min(max, Math.max(0, here + fromStart - pad));
	});
}

/**
 * Scroll to an absolute magnitude, re-signing it for the container's direction.
 *
 * @param {HTMLElement} track Scroll container.
 * @param {number} mag Magnitude, 0..max.
 */
function scrollToMagnitude(track, mag) {
	track.scrollTo({ left: isRtl(track) ? -mag : mag, behavior: behavior() });
}

/**
 * Move one frame along.
 *
 * @param {HTMLElement} track Scroll container.
 * @param {number} dir +1 travels towards the end, -1 back towards the start.
 */
function stepBy(track, dir) {
	const points = snapPoints(track);
	const here = magnitude(track);
	// A pixel of slop, so the frame we are already parked on is never "the next one".
	const target =
		dir > 0 ? points.find((p) => p > here + 1) : points.filter((p) => p < here - 1).pop();

	if (target !== undefined) scrollToMagnitude(track, target);
}

export function initCollection() {
	const track = document.querySelector('[data-collection-track]');
	if (!track) return;

	initProgress(track);
	initDrag(track);
	initArrows(track);
	initKeys(track);
}

/**
 * The progress rail.
 *
 * @param {HTMLElement} track Scroll container.
 */
function initProgress(track) {
	const fill = document.querySelector('[data-collection-progress]');
	if (!fill) return;

	/*
	 * Where CSS can do it, let it: zero JS on the scroll path.
	 *
	 * The gate is timeline-scope, not scroll(nearest inline). The rail is a SIBLING of
	 * the track, not a descendant, so `nearest` finds no inline scroll container, resolves
	 * to an inactive timeline and never ticks — while CSS.supports() cheerfully answers
	 * yes and skipped this fallback. The rail sat at scaleX(0) in every Chrome. home.css
	 * now hands the track's timeline to the rail by name, and this asks about the feature
	 * that actually makes that work.
	 */
	if (CSS.supports('timeline-scope', '--x') && CSS.supports('animation-timeline', '--x')) return;

	let scheduled = false;

	const update = () => {
		const max = maxScroll(track);
		const progress = max > 0 ? magnitude(track) / max : 0;
		fill.style.transform = `scaleX(${Math.min(1, progress)})`;
		scheduled = false;
	};

	track.addEventListener(
		'scroll',
		() => {
			if (scheduled) return;
			scheduled = true;
			requestAnimationFrame(update);
		},
		{ passive: true }
	);

	update();
}

/**
 * Click-and-drag, for the mouse.
 *
 * @param {HTMLElement} track Scroll container.
 */
function initDrag(track) {
	let dragging = false;
	let startX = 0;
	let startScroll = 0;
	let moved = 0;
	let suppressor = null;

	const dropSuppressor = () => {
		if (!suppressor) return;
		track.removeEventListener('click', suppressor, { capture: true });
		suppressor = null;
	};

	/*
	 * Every plate is an <a>, and links are natively draggable. Left alone, the first
	 * pointermove starts a link drag: the browser seizes the pointer, fires pointercancel
	 * and the track never moves a pixel — which is exactly what it did. Note that CSS
	 * -webkit-user-drag on the IMAGES does not fix this, though it looks like it should;
	 * the draggable element is the anchor around them, and the property is WebKit-only
	 * besides. This track drags itself; it does not hand its links to the desktop.
	 */
	track.addEventListener('dragstart', (e) => e.preventDefault());

	const onMove = (e) => {
		const dx = e.clientX - startX;
		moved = Math.max(moved, Math.abs(dx));
		// Delta against a scrollLeft we captured ourselves: correct in LTR and RTL alike,
		// because it never assumes which end of the range that number counts from. A
		// direction branch here would not fix a bug, it would add one.
		track.scrollLeft = startScroll - dx;
	};

	const onEnd = () => {
		if (!dragging) return;
		dragging = false;

		track.classList.remove('is-dragging');
		window.removeEventListener('pointermove', onMove);
		window.removeEventListener('pointerup', onEnd);
		window.removeEventListener('pointercancel', onEnd);

		// A drag that finishes over a product must not also open it. Capture phase is not
		// optional: the <a> would otherwise act on the click long before it reached here.
		if (moved > 5) {
			suppressor = (ev) => {
				ev.preventDefault();
				ev.stopPropagation();
				suppressor = null;
			};
			track.addEventListener('click', suppressor, { capture: true, once: true });
		}
	};

	track.addEventListener('pointerdown', (e) => {
		// Touch keeps its native swipe and proximity snap, which beat anything JS does
		// here. Secondary buttons are the context menu's, not ours.
		if (e.pointerType === 'touch' || e.button !== 0) return;

		// A previous drag that never got its click gets it dropped now, rather than
		// swallowing the first real click of this one.
		dropSuppressor();

		dragging = true;
		startX = e.clientX;
		startScroll = track.scrollLeft;
		moved = 0;
		track.classList.add('is-dragging');

		/*
		 * Deliberately NOT setPointerCapture, which is the obvious way to write this and
		 * is why the obvious way is wrong: capture retargets the compatibility mouse
		 * events at the capturing element, so `click` arrives at the track instead of the
		 * <a> inside it and every product becomes unopenable. Measured, not theorised —
		 * with capture, a clean click on a plate reported target DIV and went nowhere.
		 * Listening on the window instead keeps the click on the link where it belongs,
		 * and still follows a pointer dragged outside the track.
		 */
		window.addEventListener('pointermove', onMove);
		window.addEventListener('pointerup', onEnd);
		window.addEventListener('pointercancel', onEnd);
	});
}

/**
 * The arrow buttons. Rendered server-side and hidden; JS is what reveals them.
 *
 * @param {HTMLElement} track Scroll container.
 */
function initArrows(track) {
	const nav = document.querySelector('[data-collection-nav]');
	if (!nav) return;

	const prev = nav.querySelector('[data-collection-prev]');
	const next = nav.querySelector('[data-collection-next]');
	if (!prev || !next) return;

	nav.hidden = false;

	prev.addEventListener('click', () => stepBy(track, -1));
	next.addEventListener('click', () => stepBy(track, 1));

	let scheduled = false;

	const sync = () => {
		const max = maxScroll(track);
		const here = magnitude(track);
		prev.disabled = here <= 1;
		next.disabled = here >= max - 1;
		scheduled = false;
	};

	track.addEventListener(
		'scroll',
		() => {
			if (scheduled) return;
			scheduled = true;
			requestAnimationFrame(sync);
		},
		{ passive: true }
	);

	sync();
}

/**
 * Explicit arrow keys, so a press moves a frame rather than the UA's arbitrary nudge.
 *
 * @param {HTMLElement} track Scroll container.
 */
function initKeys(track) {
	track.addEventListener('keydown', (e) => {
		// Only when the track itself holds focus. A product link inside it gets to keep
		// its own key handling.
		if (e.target !== track) return;

		switch (e.key) {
			// Visually literal, and correct in both directions because of it: left always
			// means travel left, which in RTL is onwards and in LTR is back.
			case 'ArrowLeft':
				stepBy(track, isRtl(track) ? 1 : -1);
				break;
			case 'ArrowRight':
				stepBy(track, isRtl(track) ? -1 : 1);
				break;
			case 'Home':
				scrollToMagnitude(track, 0);
				break;
			case 'End':
				scrollToMagnitude(track, maxScroll(track));
				break;
			default:
				return;
		}

		e.preventDefault();
	});
}
