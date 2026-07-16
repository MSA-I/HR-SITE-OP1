/**
 * M6 — the card-to-product shared element morph.
 *
 * The transition itself is pure CSS (@view-transition in motion.css). The only thing
 * JS does is name the element: view-transition-name must be UNIQUE per document, so
 * exactly one plate — the one being clicked — may carry it. Naming all 24 cards in CSS
 * would make the browser drop the transition entirely.
 */

export function initViewTransitions() {
	if (!('startViewTransition' in document)) return;

	document.addEventListener('pointerdown', (event) => {
		const link = event.target.closest('.product-card a[href]');
		if (!link) return;

		const plate = link.closest('.product-card')?.querySelector('[data-plate]');
		if (plate) plate.style.viewTransitionName = 'product-plate';
	});

	// Clear before the page is cached, or returning via bfcache restores a document
	// that already claims the name and the next transition silently does nothing.
	window.addEventListener('pagehide', () => {
		document.querySelectorAll('[data-plate]').forEach((el) => {
			el.style.viewTransitionName = '';
		});
	});
}
