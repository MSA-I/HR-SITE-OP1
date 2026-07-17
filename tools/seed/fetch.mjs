/**
 * Seeds a local dev catalogue from the live HR Design store.
 *
 * The live site exposes the WooCommerce Store API publicly, so this is a paginated
 * fetch, not a scrape — no HTML parsing, no selectors to maintain, zero npm deps.
 *
 * This hits someone else's live production store. Every knob below is set for
 * politeness over speed, and the run is resumable so that iterating on the importer
 * costs zero further requests. Run it once.
 *
 *   node tools/seed/fetch.mjs
 */

import { mkdir, writeFile, readFile, access } from 'node:fs/promises';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const SEED = join(ROOT, 'seed');
const IMAGES = join(SEED, 'images');

const API = 'https://hr-design.co.il/wp-json/wc/store/v1';
const UA = 'HR-Design-Dev-Seeder/1.0 (+local dev seed; contact: studentmoshe@gmail.com)';

const TARGET_PRODUCTS = 250;
const IMAGES_PER_PRODUCT = 2; // primary + hover. That is all the card renders.
const MAX_IMAGE_WIDTH = 1024; // pick from srcset; full-size is ~6x the bytes for no dev benefit
const DELAY_JSON = 1000;
const DELAY_IMAGE = 300;
const MAX_CONSECUTIVE_FAILURES = 5;

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const exists = (p) => access(p).then(() => true, () => false);

let consecutiveFailures = 0;

/**
 * Fetch with backoff. Honours Retry-After on 429/503 — if the origin asks us to slow
 * down, arguing with it is how you get blocked.
 */
async function politeFetch(url, { binary = false } = {}) {
	for (let attempt = 0; attempt < 5; attempt++) {
		try {
			const res = await fetch(url, { headers: { 'User-Agent': UA } });

			if (res.status === 429 || res.status === 503) {
				const retryAfter = Number(res.headers.get('retry-after')) || 2 ** attempt;
				console.warn(`  ${res.status} — backing off ${retryAfter}s`);
				await sleep(retryAfter * 1000);
				continue;
			}
			if (!res.ok) throw new Error(`HTTP ${res.status}`);

			consecutiveFailures = 0;
			return { res, body: binary ? Buffer.from(await res.arrayBuffer()) : await res.json() };
		} catch (err) {
			const wait = 2 ** attempt;
			console.warn(`  ${err.message} — retry in ${wait}s (${attempt + 1}/5)`);
			await sleep(wait * 1000);
		}
	}

	if (++consecutiveFailures >= MAX_CONSECUTIVE_FAILURES) {
		throw new Error(`Aborting: ${MAX_CONSECUTIVE_FAILURES} consecutive failures. The origin may be rate-limiting us.`);
	}
	return null;
}

/** Pick the largest srcset candidate at or under MAX_IMAGE_WIDTH, else fall back to src. */
function pickImageUrl(image) {
	if (!image?.srcset) return image?.src ?? null;

	const candidates = image.srcset
		.split(',')
		.map((part) => {
			const [url, width] = part.trim().split(/\s+/);
			return { url, width: parseInt(width, 10) || 0 };
		})
		.filter((c) => c.url && c.width && c.width <= MAX_IMAGE_WIDTH)
		.sort((a, b) => b.width - a.width);

	return candidates[0]?.url ?? image.src;
}

async function fetchCategories() {
	console.log('Fetching categories...');
	const out = await politeFetch(`${API}/products/categories?per_page=100`);
	const categories = out?.body ?? [];
	await writeFile(join(SEED, 'categories.json'), JSON.stringify(categories, null, 2));
	console.log(`  ${categories.length} categories`);
	return categories;
}

async function fetchProducts() {
	const perPage = 100;
	const pages = Math.ceil(TARGET_PRODUCTS / perPage);
	const products = [];

	for (let page = 1; page <= pages; page++) {
		const cached = join(SEED, `products-page-${page}.json`);
		if (await exists(cached)) {
			console.log(`Products page ${page}: cached`);
			products.push(...JSON.parse(await readFile(cached, 'utf8')));
			continue;
		}

		console.log(`Products page ${page}/${pages}...`);
		const out = await politeFetch(`${API}/products?per_page=${perPage}&page=${page}&orderby=popularity`);
		if (!out) break;

		await writeFile(cached, JSON.stringify(out.body, null, 2));
		products.push(...out.body);
		console.log(`  ${out.body.length} products (total ${products.length}) of ${out.res.headers.get('x-wp-total')} live`);

		if (page < pages) await sleep(DELAY_JSON);
	}

	return products.slice(0, TARGET_PRODUCTS);
}

async function fetchImages(products) {
	const jobs = [];
	for (const p of products) {
		for (const image of (p.images ?? []).slice(0, IMAGES_PER_PRODUCT)) {
			const url = pickImageUrl(image);
			if (!url) continue;
			const name = `${p.id}-${image.id}.${(url.split('.').pop() || 'jpg').split('?')[0]}`;
			jobs.push({ url, path: join(IMAGES, name), name, productId: p.id, imageId: image.id });
		}
	}

	console.log(`\n${jobs.length} images to fetch`);
	const map = {};
	let fetched = 0;
	let skipped = 0;

	for (const job of jobs) {
		map[`${job.productId}:${job.imageId}`] = job.name;

		// Resumability is the point: a re-run after a crash costs ~zero requests.
		if (await exists(job.path)) {
			skipped++;
			continue;
		}

		const out = await politeFetch(job.url, { binary: true });
		if (!out) continue;

		await writeFile(job.path, out.body);
		fetched++;
		if (fetched % 25 === 0) console.log(`  ${fetched} fetched, ${skipped} cached`);
		await sleep(DELAY_IMAGE);
	}

	await writeFile(join(SEED, 'image-map.json'), JSON.stringify(map, null, 2));
	console.log(`  done: ${fetched} fetched, ${skipped} already on disk`);
}

async function main() {
	await mkdir(IMAGES, { recursive: true });

	const categories = await fetchCategories();
	await sleep(DELAY_JSON);

	const products = await fetchProducts();
	await writeFile(join(SEED, 'products.json'), JSON.stringify(products, null, 2));

	const variable = products.filter((p) => p.type === 'variable').length;
	const withGallery = products.filter((p) => (p.images ?? []).length > 1).length;
	console.log(`\n${products.length} products | ${variable} variable | ${withGallery} with a second image`);

	await fetchImages(products);

	console.log(`\nSeed complete: ${products.length} products, ${categories.length} categories.`);
}

main().catch((err) => {
	console.error(`\nFAILED: ${err.message}`);
	process.exit(1);
});
