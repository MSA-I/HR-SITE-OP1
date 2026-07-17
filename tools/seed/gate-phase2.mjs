/**
 * Phase 2 gate.
 *
 * WooCommerce layered nav can only filter GLOBAL attribute taxonomies. This asks the
 * local Store API to filter by pa_color and asserts it comes back non-empty. If this
 * fails, phase 5 (the brief's filters) is unbuildable and nothing downstream matters.
 *
 *   node tools/seed/gate-phase2.mjs
 */

const BASE = 'http://localhost:8080/wp-json/wc/store/v1';

const get = async (path) => {
	const res = await fetch(BASE + path);
	if (!res.ok) throw new Error(`${res.status} ${res.statusText} on ${path}`);
	return { total: res.headers.get('x-wp-total'), body: await res.json() };
};

let failed = false;
const check = (label, pass, detail) => {
	console.log(`  ${pass ? 'PASS' : 'FAIL'}  ${label.padEnd(46)} ${detail}`);
	if (!pass) failed = true;
};

console.log('\nPhase 2 gate — global attributes must be filterable\n');

// The attribute must exist as a taxonomy at all. This is what returned [] on the live
// store and is the entire reason phase 2 exists.
const attrs = await get('/products/attributes');
const color = attrs.body.find((a) => a.taxonomy === 'pa_color');
check('pa_color exists as a taxonomy', !!color, color ? `id ${color.id}` : 'MISSING');

if (color) {
	const terms = await get(`/products/attributes/${color.id}/terms`);
	check('pa_color has terms', terms.body.length > 0, `${terms.body.length} terms`);

	const withCounts = terms.body.filter((t) => t.count > 0).sort((a, b) => b.count - a.count);
	check('terms have products attached', withCounts.length > 0, withCounts.slice(0, 5).map((t) => `${t.name}(${t.count})`).join(' '));

	// The real gate: filter the catalogue by a colour term and get products back.
	// The param is attributes[0][attribute], NOT [taxonomy] — the latter throws an
	// undefined-key warning inside ProductQuery and silently returns the whole catalogue.
	if (withCounts.length) {
		const target = withCounts[0];
		const filtered = await get(`/products?attributes[0][attribute]=pa_color&attributes[0][slug]=${encodeURIComponent(target.slug)}&per_page=5`);
		check(
			`filter by pa_color=${target.name}`,
			filtered.body.length > 0,
			`${filtered.total} products — expected ${target.count}`
		);
		check('filtered count matches term count', String(filtered.total) === String(target.count), `api=${filtered.total} term=${target.count}`);
	}

	const swatches = await get(`/products/attributes/${color.id}/terms`);
	console.log(`\n  colour terms: ${swatches.body.map((t) => `${t.name}(${t.count})`).join(', ')}`);
}

// Price filter is core and needs no normalization, but prove it before relying on it.
const cheap = await get('/products?max_price=50000&per_page=1');
check('price filter responds', Number(cheap.total) > 0, `${cheap.total} products under ₪500`);

const stock = await get('/products?stock_status=outofstock&per_page=1');
check('stock filter responds', stock.total !== null, `${stock.total} out of stock`);

console.log(failed ? '\nGATE FAILED — phase 5 is blocked.\n' : '\nGATE PASSED — filters are buildable.\n');
process.exit(failed ? 1 : 0);
