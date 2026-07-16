/**
 * M16 — the mobile buy bar.
 *
 * Watches the in-flow add-to-cart button. The bar enters only once that button has left
 * the viewport, and leaves again when it returns. It is never fixed on load.
 */

export function initBuyBar() {
	const bar = document.querySelector('[data-buy-bar]');
	const anchor = document.querySelector('.single_add_to_cart_button');
	if (!bar || !anchor || !('IntersectionObserver' in window)) return;

	const io = new IntersectionObserver(
		([entry]) => bar.classList.toggle('is-in', !entry.isIntersecting),
		// A little slack: the bar should not flicker as the button grazes the edge.
		{ rootMargin: '-8px 0px 0px 0px', threshold: 0 }
	);

	io.observe(anchor);
}
