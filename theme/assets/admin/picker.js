/**
 * By Light admin: the product field's typeahead. That is all that is left.
 *
 * Not bundled by Vite: it is admin-only, has no imports, and keeping it out of the
 * storefront bundle is the whole point.
 *
 * This was a 173-line click-to-place hotspot picker, then a lamp-anchor picker with a
 * keyed ghost overlay. Both are gone, and not because they were bad — because the section
 * stopped having anything to place. The lamp is inside the photographs now, so there is no
 * coordinate for an author to get wrong, which is a better outcome than a good tool for
 * getting it right.
 */
(function () {
	const search = document.querySelector('[data-hrd-search]');
	const results = document.querySelector('[data-hrd-results]');
	const productField = document.querySelector('[data-hrd-product]');
	if (!search || !results || !productField) return;

	const cfg = window.hrdPicker || {};

	const escapeHtml = (s) =>
		String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

	let timer;

	search.addEventListener('input', () => {
		clearTimeout(timer);
		timer = setTimeout(async () => {
			const q = search.value.trim();
			if (q.length < 2) {
				results.hidden = true;
				return;
			}

			const res = await fetch(`${cfg.ajax}?action=hrd_search_products&nonce=${cfg.nonce}&q=${encodeURIComponent(q)}`);
			const json = await res.json();
			if (!json.success) return;

			results.innerHTML = json.data
				.map(
					(p) => `<button type="button" class="hrd-result" data-id="${p.id}" data-name="${escapeHtml(p.name)}">
						${p.thumb ? `<img src="${p.thumb}" alt="">` : '<span class="hrd-result__noimg"></span>'}
						<span>${escapeHtml(p.name)}</span><small>${escapeHtml(p.price)}</small>
					</button>`
				)
				.join('');
			results.hidden = false;
		}, 250);
	});

	results.addEventListener('click', (event) => {
		const hit = event.target.closest('.hrd-result');
		if (!hit) return;
		productField.value = hit.dataset.id;
		search.value = hit.dataset.name;
		results.hidden = true;
	});
})();
