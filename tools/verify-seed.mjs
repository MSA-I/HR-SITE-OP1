/**
 * Checks a restored seed/ against what the import scripts expect.
 *
 *   node tools/verify-seed.mjs
 *
 * seed/ is excluded from git — the repo is public and HR Design's catalogue is not ours to
 * republish — so it arrives either from `node tools/seed/fetch.mjs` or from a hand-carried
 * bundle. Both can arrive incomplete, and the failure is quiet: import.php skips a product
 * whose image is missing and reports success, so the site comes up looking finished with
 * holes in it. That is the exact class of bug this project spent a day removing.
 *
 * Exits 1 if anything the site needs is absent.
 */

import { readdir, stat, readFile } from 'node:fs/promises';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const SEED = join(ROOT, 'seed');

/** The four לפי אור frames. Not reproducible for free — see tools/by-light/README.md. */
const BYLIGHT = ['byl-morning.png', 'byl-noon.png', 'byl-evening.png', 'byl-night.png'];

const problems = [];
const note = (s) => console.log(`  ${s}`);

async function exists(p) {
	try {
		await stat(p);
		return true;
	} catch {
		return false;
	}
}

async function count(dir) {
	try {
		return (await readdir(join(SEED, dir))).length;
	} catch {
		return -1;
	}
}

if (!(await exists(SEED))) {
	console.error('\nNo seed/ at all.\n');
	console.error('  Either unzip the bundle into the repo root, or run:');
	console.error('    node tools/seed/fetch.mjs\n');
	process.exit(1);
}

console.log('\nseed/ contents\n');

/*
 * products.json is the manifest the importer walks, so it decides how many images should be
 * present. Checking images against a hardcoded 409 would go stale the first time the sample
 * size changes; checking against the manifest cannot.
 */
let expectedImages = null;
if (await exists(join(SEED, 'image-map.json'))) {
	const map = JSON.parse(await readFile(join(SEED, 'image-map.json'), 'utf8'));
	expectedImages = Object.keys(map).length;
}

const images = await count('images');
note(`images/      ${images < 0 ? 'MISSING' : images} file(s)${expectedImages ? ` — image-map.json expects ${expectedImages}` : ''}`);
if (images < 0) problems.push('seed/images/ is missing — run node tools/seed/fetch.mjs');
else if (expectedImages && images < expectedImages) problems.push(`seed/images/ has ${images} of ${expectedImages} — the import will silently skip products`);

for (const f of ['products.json', 'categories.json', 'image-map.json', 'id-map.json']) {
	const ok = await exists(join(SEED, f));
	note(`${f.padEnd(20)} ${ok ? 'ok' : 'MISSING'}`);
	if (!ok) problems.push(`seed/${f} is missing — run node tools/seed/fetch.mjs`);
}

const hero = await exists(join(SEED, 'hero', 'hero-6623.jpg'));
note(`hero/hero-6623.jpg   ${hero ? 'ok' : 'MISSING'}`);
if (!hero) problems.push('seed/hero/hero-6623.jpg is missing — run node tools/scene/fetch-hero.mjs');

console.log('');
for (const f of BYLIGHT) {
	const ok = await exists(join(SEED, 'bylight', f));
	note(`bylight/${f.padEnd(21)} ${ok ? 'ok' : 'MISSING'}`);
	/*
	 * Louder than the rest on purpose. Everything above re-downloads; these do not. They are
	 * Higgsfield renders, regenerating them costs credits AND produces different images — the
	 * room drifts and the cross-fade reads as furniture morphing instead of light changing.
	 * hrd_byl_payload() fails closed on a partial set, so a missing frame does not break the
	 * page, it deletes the section.
	 */
	if (!ok) problems.push(`seed/bylight/${f} is missing — NOT re-downloadable. Without all four, לפי אור renders nothing at all (it fails closed by design). See tools/by-light/README.md.`);
}

console.log('');
if (problems.length) {
	console.error(`${problems.length} problem(s):\n`);
	for (const p of problems) console.error(`  - ${p}`);
	console.error('');
	process.exit(1);
}

console.log('seed/ is complete. Next: the import block in RESTORE.md.\n');
process.exit(0);
