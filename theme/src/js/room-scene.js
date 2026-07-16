/**
 * Shop the Space — pointer parallax, hotspot reveal, mini-card positioning.
 *
 * The Popover API already handles opening, light-dismiss, Escape and focus. This file
 * only adds the depth illusion, the reveal choreography, and keeping the card on screen.
 */

import { isFull } from './lib/motion.js';

/*
 * A flat photograph has no depth to parallax. The illusion: move each layer at a
 * different rate and the brain reads it as depth.
 *
 * Total foreground travel is 52px across the whole stage. That is the correct number.
 * Everyone's first instinct is 3x this, and 3x reads as a parallax demo, not a room.
 * Y amplitude is exactly half X: vertical drift is both more nauseating and less
 * convincing — the depth cue here is lateral.
 */
const AMPLITUDE = { bg: 6, mid: 14, fore: 26 };
const LERP = 0.075;
const IDLE_MS = 1200;

export function initRoomScene() {
	const section = document.querySelector('[data-sts]');
	if (!section) return;

	const stage = section.querySelector('[data-sts-stage]');
	const layers = [...section.querySelectorAll('[data-sts-layer]')];
	if (!stage || !layers.length) return;

	revealHotspots(section);
	bindStrip(section);
	bindCards(section);

	// Never on touch: it would fight scrolling, and the brief bans heavy mobile effects.
	const fine = matchMedia('(hover: hover) and (pointer: fine)');
	if (!fine.matches || !isFull()) return;

	let target = { x: 0, y: 0 };
	let current = { x: 0, y: 0 };
	let visible = false;
    let lastMove = 0;
	let raf = null;

	const tick = () => {
		current.x += (target.x - current.x) * LERP;
		current.y += (target.y - current.y) * LERP;

		for (const layer of layers) {
			const amp = AMPLITUDE[layer.dataset.stsLayer] ?? AMPLITUDE.mid;
			layer.style.transform = `translate3d(${current.x * amp}px, ${current.y * amp * 0.5}px, 0)`;
		}

		const settled = Math.abs(target.x - current.x) < 0.001 && Math.abs(target.y - current.y) < 0.001;
		const idle = performance.now() - lastMove > IDLE_MS;

		// Exit the loop once it has nothing left to do. A permanent rAF on a homepage
		// is a battery bug, not an animation.
		if (visible && !(settled && idle)) {
			raf = requestAnimationFrame(tick);
		} else {
			raf = null;
		}
	};

	const start = () => {
		if (raf === null) raf = requestAnimationFrame(tick);
	};

	stage.addEventListener(
		'pointermove',
		(event) => {
			const box = stage.getBoundingClientRect();
			target.x = ((event.clientX - box.left) / box.width) * 2 - 1;
			target.y = ((event.clientY - box.top) / box.height) * 2 - 1;
			lastMove = performance.now();
			start();
		},
		{ passive: true }
	);

	// Drift home when the pointer leaves, rather than snapping.
	stage.addEventListener('pointerleave', () => {
		target.x = 0;
		target.y = 0;
		lastMove = performance.now();
		start();
	});

	// Only run while the section is actually on screen.
	new IntersectionObserver(
		([entry]) => {
			visible = entry.isIntersecting;
			if (visible) start();
		},
		{ threshold: 0 }
	).observe(section);

	// The pointer parallax is meaningless once motion is reduced mid-session.
	matchMedia('(prefers-reduced-motion: reduce)').addEventListener('change', () => {
		if (isFull()) return;
		for (const layer of layers) layer.style.transform = '';
		visible = false;
	});
}

/**
 * M20 — reveal the pins once the room is properly on screen, in RTL reading order.
 *
 * @param {Element} section The section.
 */
function revealHotspots(section) {
	const spots = [...section.querySelectorAll('.hotspot')];
	if (!spots.length) return;

	if (!('IntersectionObserver' in window)) {
		spots.forEach((s) => s.classList.add('is-in'));
		return;
	}

	const io = new IntersectionObserver(
		([entry]) => {
			if (!entry.isIntersecting) return;

			// Right to left, top to bottom — the order a Hebrew reader takes the room in.
			[...spots]
				.sort((a, b) => {
					const ax = parseFloat(a.style.getPropertyValue('--x'));
					const bx = parseFloat(b.style.getPropertyValue('--x'));
					return bx - ax;
				})
				.forEach((spot, i) => {
					spot.style.setProperty('--reveal-delay', `${i * 90}ms`);
					spot.classList.add('is-in');
				});

			io.disconnect();
		},
		// The room must be properly on screen, not peeking.
		{ threshold: 0.55 }
	);

	io.observe(section.querySelector('[data-sts-stage]'));
}

/**
 * Two-way binding between the pins and the mobile strip.
 *
 * @param {Element} section The section.
 */
function bindStrip(section) {
	for (const item of section.querySelectorAll('[data-sts-strip-item]')) {
		item.addEventListener('click', () => {
			const id = item.dataset.stsStripItem;
			const card = section.querySelector(`[data-mini-card="${id}"]`);
			const spot = section.querySelector(`[data-hotspot="${id}"]`);
			if (!card) return;

			section.querySelectorAll('.hotspot').forEach((s) => s.classList.remove('is-active'));
			spot?.classList.add('is-active');
			card.showPopover?.();
		});
	}
}

/**
 * Active state, pointer stilling, and keeping the card on screen.
 *
 * @param {Element} section The section.
 */
function bindCards(section) {
	for (const card of section.querySelectorAll('[data-mini-card]')) {
		const id = card.dataset.miniCard;
		const spot = section.querySelector(`[data-hotspot="${id}"]`);

		card.addEventListener('toggle', (event) => {
			const open = event.newState === 'open';

			// One card at a time. The pulse dies for good on first interaction — after
			// three cycles the user either understood or never will.
			if (open) {
				section.classList.add('has-interacted');
				section.querySelectorAll('.hotspot').forEach((s) => s.classList.remove('is-active'));
				spot?.classList.add('is-active');

				// Scroll the matching strip item into view (the reverse binding).
				section
					.querySelector(`[data-sts-strip-item="${id}"]`)
					?.scrollIntoView({ inline: 'center', block: 'nearest', behavior: isFull() ? 'smooth' : 'auto' });

				position(card, spot);
			} else {
				spot?.classList.remove('is-active');
			}
		});
	}

	// Anchoring must survive a resize or a scroll while the card is open.
	const reposition = () => {
		for (const card of section.querySelectorAll('[data-mini-card]')) {
			if (!card.matches(':popover-open')) continue;
			position(card, section.querySelector(`[data-hotspot="${card.dataset.miniCard}"]`));
		}
	};

	let scheduled = false;
	const onScrollOrResize = () => {
		if (scheduled) return;
		scheduled = true;
		requestAnimationFrame(() => {
			reposition();
			scheduled = false;
		});
	};

	addEventListener('scroll', onScrollOrResize, { passive: true });
	addEventListener('resize', onScrollOrResize);
}

/**
 * Place the card near its pin without letting it leave the viewport.
 *
 * Below 720px the CSS turns it into a bottom sheet and anchoring is abandoned entirely —
 * a floating card beside a thumb-obscured dot is offscreen-adjacent by definition.
 *
 * @param {HTMLElement} card Mini card.
 * @param {HTMLElement} spot Hotspot.
 */
function position(card, spot) {
	if (!spot || innerWidth <= 720) {
		card.style.insetInlineStart = '';
		card.style.insetBlockStart = '';
		return;
	}

	const pin = spot.getBoundingClientRect();
	const box = card.getBoundingClientRect();
	const gutter = 16;

	// Prefer the pin's inline-end side, then flip, then clamp. Physical coords: the
	// popover is in the top layer, positioned against the viewport, not the RTL flow.
	let left = pin.right + gutter;
	if (left + box.width > innerWidth - gutter) left = pin.left - box.width - gutter;
	left = Math.max(gutter, Math.min(left, innerWidth - box.width - gutter));

	let top = pin.top + pin.height / 2 - box.height / 2;
	top = Math.max(gutter, Math.min(top, innerHeight - box.height - gutter));

	card.style.insetInlineStart = 'auto';
	card.style.left = `${left}px`;
	card.style.top = `${top}px`;
}
