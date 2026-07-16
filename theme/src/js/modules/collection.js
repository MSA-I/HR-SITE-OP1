/**
 * M17 — the horizontal section's progress rail.
 *
 * CSS scroll-driven animation handles this where supported; this is the fallback.
 */

export function initCollection() {
	const track = document.querySelector('[data-collection-track]');
	const fill = document.querySelector('[data-collection-progress]');
	if (!track || !fill) return;

	// Where CSS can do it, let it: zero JS on the scroll path.
	if (CSS.supports('animation-timeline', 'scroll(nearest inline)')) return;

	let scheduled = false;

	const update = () => {
		const max = track.scrollWidth - track.clientWidth;
		/*
		 * THE RTL SCROLL TRAP.
		 *
		 * In an RTL container, modern engines report scrollLeft as 0 at the RIGHT edge
		 * and NEGATIVE travelling left. Any progress maths must take the absolute value;
		 * assuming a 0->positive range is the number one bug in RTL horizontal sections.
		 */
		const progress = max > 0 ? Math.abs(track.scrollLeft) / max : 0;
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
