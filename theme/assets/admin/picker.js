/**
 * Hotspot picker. Click to place, drag to adjust, search to link.
 *
 * Not bundled by Vite: it is admin-only, has no imports, and keeping it out of the
 * storefront bundle is the whole point.
 */
(function () {
	const picker = document.querySelector('[data-hrd-picker]');
	if (!picker) return;

	const stage = picker.querySelector('[data-hrd-stage]');
	const list = picker.querySelector('[data-hrd-list]');
	const field = document.querySelector('[data-hrd-data]');
	const cfg = window.hrdPicker || {};

	let hotspots = [];
	try {
		hotspots = JSON.parse(field.value || '[]');
	} catch {
		hotspots = [];
	}

	const save = () => (field.value = JSON.stringify(hotspots));
	const uid = () => 'h' + Math.random().toString(36).slice(2, 8);

	/** Where did the pointer land, as a percentage of the stage? */
	function pointToPercent(event) {
		const box = stage.getBoundingClientRect();
		return {
			x: Math.max(0, Math.min(100, ((event.clientX - box.left) / box.width) * 100)),
			y: Math.max(0, Math.min(100, ((event.clientY - box.top) / box.height) * 100)),
		};
	}

	function render() {
		// Pins
		stage.querySelectorAll('.hrd-pin').forEach((el) => el.remove());

		hotspots.forEach((spot, index) => {
			const pin = document.createElement('button');
			pin.type = 'button';
			pin.className = 'hrd-pin';
			pin.style.left = spot.x_d + '%';
			pin.style.top = spot.y_d + '%';
			pin.textContent = String(index + 1);
			pin.dataset.index = index;
			stage.append(pin);
		});

		// Rows
		list.innerHTML = '';
		hotspots.forEach((spot, index) => {
			const row = document.createElement('div');
			row.className = 'hrd-row';
			row.innerHTML = `
				<span class="hrd-row__num">${index + 1}</span>
				<div class="hrd-row__main">
					<input type="search" class="hrd-row__search" placeholder="${cfg.i18n.search}"
						value="${spot.product_name ? escapeHtml(spot.product_name) : ''}" data-index="${index}">
					<div class="hrd-row__results" hidden></div>
					${spot.product_id ? '' : `<em class="hrd-row__empty">${cfg.i18n.noLink}</em>`}
				</div>
				<select class="hrd-row__layer" data-index="${index}">
					${['bg', 'mid', 'fore'].map((l) => `<option value="${l}"${spot.layer === l ? ' selected' : ''}>${l}</option>`).join('')}
				</select>
				<button type="button" class="button-link hrd-row__remove" data-index="${index}">${cfg.i18n.remove}</button>
			`;
			list.append(row);
		});

		save();
	}

	const escapeHtml = (s) =>
		String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

	// ---- Place -------------------------------------------------------------

	let dragging = null;

	stage.addEventListener('pointerdown', (event) => {
		const pin = event.target.closest('.hrd-pin');
		if (pin) {
			dragging = Number(pin.dataset.index);
			pin.setPointerCapture(event.pointerId);
			return;
		}
		if (event.target.tagName !== 'IMG') return;

		const { x, y } = pointToPercent(event);
		hotspots.push({ id: uid(), x_d: x, y_d: y, x_m: x, y_m: y, layer: 'mid', product_id: 0 });
		render();
	});

	stage.addEventListener('pointermove', (event) => {
		if (dragging === null) return;
		const { x, y } = pointToPercent(event);
		hotspots[dragging].x_d = x;
		hotspots[dragging].y_d = y;
		const pin = stage.querySelector(`.hrd-pin[data-index="${dragging}"]`);
		if (pin) {
			pin.style.left = x + '%';
			pin.style.top = y + '%';
		}
	});

	stage.addEventListener('pointerup', () => {
		if (dragging === null) return;
		dragging = null;
		save();
	});

	// ---- Link --------------------------------------------------------------

	let searchTimer;

	list.addEventListener('input', (event) => {
		const input = event.target.closest('.hrd-row__search');
		if (!input) return;

		const results = input.parentElement.querySelector('.hrd-row__results');
		clearTimeout(searchTimer);

		searchTimer = setTimeout(async () => {
			const q = input.value.trim();
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

	list.addEventListener('click', (event) => {
		const result = event.target.closest('.hrd-result');
		if (result) {
			const input = result.closest('.hrd-row__main').querySelector('.hrd-row__search');
			const index = Number(input.dataset.index);
			hotspots[index].product_id = Number(result.dataset.id);
			hotspots[index].product_name = result.dataset.name;
			render();
			return;
		}

		const remove = event.target.closest('.hrd-row__remove');
		if (remove) {
			hotspots.splice(Number(remove.dataset.index), 1);
			render();
		}
	});

	list.addEventListener('change', (event) => {
		const layer = event.target.closest('.hrd-row__layer');
		if (!layer) return;
		hotspots[Number(layer.dataset.index)].layer = layer.value;
		save();
	});

	render();
})();
