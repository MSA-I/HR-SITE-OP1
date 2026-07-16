/**
 * Thin wrapper over the WooCommerce Store API.
 *
 * Cart mutations need the Nonce header, and the response returns a fresh one that must
 * replace it — miss that and the second add-to-cart of a session fails with 401 while
 * the first works, which is a bug that hides well in manual testing.
 */

const config = window.hrdStore ?? {};
let nonce = config.nonce ?? '';

async function request(path, options = {}) {
	const res = await fetch(`${config.root}wc/store/v1${path}`, {
		method: options.method ?? 'GET',
		credentials: 'include',
		headers: {
			'Content-Type': 'application/json',
			...(nonce ? { Nonce: nonce } : {}),
		},
		body: options.body ? JSON.stringify(options.body) : undefined,
	});

	const fresh = res.headers.get('nonce');
	if (fresh) nonce = fresh;

	if (!res.ok) {
		const err = await res.json().catch(() => ({}));
		throw new Error(err.message || `Store API ${res.status}`);
	}

	return res.json();
}

export const addToCart = (id, quantity = 1) =>
	request('/cart/add-item', { method: 'POST', body: { id: Number(id), quantity } });

export const getCart = () => request('/cart');

/** Hydrate a set of product ids — used by the wishlist, which stores ids only. */
export const getProducts = (ids) =>
	request(`/products?include=${ids.join(',')}&per_page=${Math.min(ids.length, 100)}`);
