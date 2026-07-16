/**
 * Finds a room photograph large enough to carry a full-bleed hero.
 *
 *   node tools/scene/find-hero.mjs
 *
 * The catalogue's resolution is backwards for design: the product cut-outs go up to
 * 3195px, while the styled room photographs — the ones worth putting in a hero — are
 * mostly 473-750px. This lists the scene-type photos that are actually big enough,
 * so the hero is chosen from what exists rather than upscaled 5x.
 */

import { readFile } from 'node:fs/promises';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const products = JSON.parse(await readFile(join(ROOT, 'seed', 'products.json'), 'utf8'));

// Rooms, not objects: these categories are where the styled interiors live.
const ROOMY = /ספות|כורסאות|מיטות|פינות אוכל|שולחנות|שטיחים|קונסולות|ריהוט/;

const rows = [];

for (const p of products) {
	const inRoom = (p.categories ?? []).some((c) => ROOMY.test(c.name));
	if (!inRoom) continue;

	for (const [i, img] of (p.images ?? []).entries()) {
		if (!img.srcset) continue;
		const candidates = img.srcset.split(',').map((part) => {
			const [url, w] = part.trim().split(/\s+/);
			return { url, w: parseInt(w, 10) || 0 };
		});
		const max = candidates.sort((a, b) => b.w - a.w)[0];
		if (!max || max.w < 1400) continue;

		rows.push({
			src: p.id,
			imageIndex: i,
			w: max.w,
			name: p.name.slice(0, 44),
			cats: (p.categories ?? []).map((c) => c.name).join(', ').slice(0, 34),
			url: max.url,
		});
	}
}

rows.sort((a, b) => b.w - a.w);

console.log(`\n${rows.length} room-category images at >=1400w\n`);
for (const r of rows.slice(0, 18)) {
	console.log(`  ${String(r.w).padStart(5)}w  src ${String(r.src).padEnd(5)} img${r.imageIndex}  ${r.name.padEnd(46)} ${r.cats}`);
}

if (!rows.length) {
	console.log('  none — the hero cannot be a room photograph from this catalogue.');
}
