/**
 * Wishlist — localStorage, no plugin, no account.
 *
 * ~90% of the value at ~2% of the cost. The honest limitation, which should be stated
 * to the client rather than hidden: no sync across devices.
 */

const KEY = 'hr-wishlist';

const read = () => {
	try {
		return JSON.parse(localStorage.getItem(KEY) ?? '[]');
	} catch {
		return []; // corrupt storage should not take the page down
	}
};

const write = (ids) => localStorage.setItem(KEY, JSON.stringify(ids));

export const getWishlist = read;

function syncCount() {
	const el = document.querySelector('[data-wishlist-count]');
	if (!el) return;
	const count = read().length;
	el.textContent = String(count);
	el.hidden = count === 0;
}

export function initWishlist() {
	const ids = read();

	// Reflect stored state onto whatever cards are on this page.
	for (const button of document.querySelectorAll('[data-wishlist]')) {
		if (ids.includes(Number(button.dataset.wishlist))) {
			button.setAttribute('aria-pressed', 'true');
		}
	}
	syncCount();

	document.addEventListener('click', (event) => {
		const button = event.target.closest('[data-wishlist]');
		if (!button) return;

		event.preventDefault();
		const id = Number(button.dataset.wishlist);
		const current = read();
		const next = current.includes(id) ? current.filter((x) => x !== id) : [...current, id];

		write(next);
		button.setAttribute('aria-pressed', String(next.includes(id)));
		syncCount();
	});
}
