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

/** Reflect stored state onto whatever cards are currently in the DOM. */
function syncButtons(root = document) {
	const ids = read();
	for (const button of root.querySelectorAll('[data-wishlist]')) {
		button.setAttribute('aria-pressed', String(ids.includes(Number(button.dataset.wishlist))));
	}
}

/**
 * Fill the wishlist page.
 *
 * The cards are fetched from this same page rather than built here from Store API JSON.
 * The card is content-product.php and it carries the plate treatment, the badge, the
 * hover image, the swatches, the dimensions and four different quick actions; none of
 * that is in the API, and a lookalike assembled here would drift from the catalogue the
 * first time either side changed. Asking the server for its own markup cannot drift.
 *
 * @param {HTMLElement} list The [data-wishlist-list] container.
 */
async function fillWishlistPage(list) {
	const ids = read();

	// The server already rendered the empty state. Nothing to do but leave it alone.
	if (!ids.length) return;

	try {
		const url = new URL(window.location.href);
		url.searchParams.set('ids', ids.join(','));
		url.searchParams.set('partial', '1');

		const response = await fetch(url, { credentials: 'same-origin' });
		if (!response.ok) return; // keep the empty state rather than blanking the page

		const html = (await response.text()).trim();
		if (!html) return;

		list.innerHTML = html;
		syncButtons(list);
	} catch {
		// Offline or blocked: the empty state is wrong but it is not a broken page, and
		// the stored list is untouched either way.
	}
}

export function initWishlist() {
	syncButtons();
	syncCount();

	const list = document.querySelector('[data-wishlist-list]');
	if (list) fillWishlistPage(list);

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

		// On the wishlist page the heart is a REMOVE control, so the card has to go. Left
		// in place it would sit there un-hearted, and a reload would silently disappear it
		// — the page contradicting itself until you look away.
		if (!list || next.includes(id)) return;

		button.closest('.product-card')?.remove();
		if (!list.querySelector('.product-card')) window.location.reload();
	});
}
