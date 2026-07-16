/**
 * Quick add-to-cart, and the confirmation (M12/M13/M14).
 *
 * Three confirmations, each doing a different job:
 *   - the label flips IN PLACE, where the eye already is
 *   - the cart badge ticks, so the destination confirms
 *   - a toast names the product, and it is aria-live, so the confirmation also reaches
 *     screen readers — which a flying product image never does
 *
 * There is deliberately no flying image: our plates use mix-blend-mode: multiply, and a
 * fixed-position clone creates a new stacking context that blends against the wrong
 * backdrop, so the product would change colour mid-flight or vanish over a dark section.
 */

import { addToCart, getCart } from '../lib/store-api.js';
import { isFull } from '../lib/motion.js';

const strings = window.hrdStore?.i18n ?? {};

function tickBadge(count) {
	const badge = document.querySelector('[data-cart-count]');
	if (!badge) return;

	badge.textContent = String(count);
	badge.hidden = count === 0;

	if (!isFull() || !badge.animate) return;
	badge.animate(
		[{ transform: 'scale(1)' }, { transform: 'scale(1.35)' }, { transform: 'scale(1)' }],
		{ duration: 320, easing: 'cubic-bezier(.34,1.36,.64,1)' }
	);
}

function toast(message, variant = 'ok') {
	let el = document.querySelector('.toast');
	if (!el) {
		el = document.createElement('div');
		el.className = 'toast';
		// polite, not assertive: an add-to-cart is not an interruption.
		el.setAttribute('role', 'status');
		el.setAttribute('aria-live', 'polite');
		document.body.append(el);
	}

	el.className = `toast toast--${variant}`;
	el.innerHTML = '';

	// A bare text node would run straight into the link's text in the accessible name
	// ("…נוסף לסללצפייה בסל"). The flex gap only fixes the visual.
	const text = document.createElement('span');
	text.textContent = message;
	el.append(text);

	if (variant === 'ok') {
		const link = document.createElement('a');
		link.href = window.hrdStore?.cartUrl ?? '/cart/';
		link.textContent = strings.viewCart ?? 'לצפייה בסל';
		el.append(link);
	}

	el.classList.add('is-in');

	clearTimeout(el._timer);
	const dismiss = () => el.classList.remove('is-in');
	el._timer = setTimeout(dismiss, 4000);

	// Pause the auto-dismiss while the user is reading or tabbing through it.
	el.onpointerenter = () => clearTimeout(el._timer);
	el.onpointerleave = () => (el._timer = setTimeout(dismiss, 1500));
	el.onfocusin = () => clearTimeout(el._timer);
}

export function initCart() {
	document.addEventListener('click', async (event) => {
		const button = event.target.closest('[data-add-to-cart]');
		if (!button) return;

		// The card's control is an <a href="?add-to-cart=ID"> so it works without JS.
		// `disabled` does nothing on a link, hence the explicit busy flag — otherwise a
		// double-click adds the item twice.
		if (button.dataset.busy) return;

		event.preventDefault();
		button.dataset.busy = '1';
		const id = button.dataset.addToCart;

		// The same button appears on a card, in a Shop-the-Space mini card, and in the
		// mobile buy bar. Naming the product is the whole point of the toast — it is what
		// makes the confirmation reach a screen reader — so find it wherever we are.
		const context = button.closest('.product-card, .mini-card, .pdp, .buy-bar');
		const name = context
			?.querySelector('.product-card__name, .mini-card__name, .pdp__title')
			?.textContent.trim()
			?? document.querySelector('.pdp__title')?.textContent.trim();

		button.setAttribute('aria-busy', 'true');
		try {
			const cart = await addToCart(id);
			button.classList.add('is-added');
			tickBadge(cart.items_count);
			toast(name ? `${name} ${strings.added ?? 'נוסף לסל'}` : (strings.added ?? 'נוסף לסל'));

			setTimeout(() => {
				button.classList.remove('is-added');
				button.removeAttribute('aria-busy');
				delete button.dataset.busy;
			}, 1800);
		} catch (err) {
			// Never leave the control dead on failure — the user must be able to retry.
			button.removeAttribute('aria-busy');
			delete button.dataset.busy;
			toast(strings.addFailed ?? 'ההוספה לסל נכשלה. נסו שוב.', 'err');
			console.error('[hrd] add to cart:', err.message);
		}
	});

	// The page may be served from a cache with a stale badge; reconcile once on load.
	const badge = document.querySelector('[data-cart-count]');
	if (badge) {
		getCart()
			.then((cart) => {
				badge.textContent = String(cart.items_count);
				badge.hidden = cart.items_count === 0;
			})
			.catch(() => {});
	}
}
