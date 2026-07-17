/**
 * How large is HR Design's product photography, actually?
 *
 *   node tools/seed/image-sizes.mjs
 *
 * The hero turned out to be a 473px file stretched across a 2560px viewport. That looked
 * like a seeder bug — the seeder deliberately caps downloads at 1024w — but the live
 * srcset for that product tops out at 473w. So the ceiling is theirs, not ours.
 *
 * This reads the largest candidate each product's srcset offers, which IS the original
 * upload, and reports the distribution. It decides whether a full-bleed hero is even
 * possible with this catalogue.
 */

import { readFile } from 'node:fs/promises';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const products = JSON.parse(await readFile(join(ROOT, 'seed', 'products.json'), 'utf8'));

const widths = [];
const biggest = [];

for (const p of products) {
	for (const img of p.images ?? []) {
		if (!img.srcset) continue;
		const max = Math.max(
			...img.srcset
				.split(',')
				.map((part) => parseInt(part.trim().split(/\s+/)[1], 10) || 0)
		);
		if (!max) continue;
		widths.push(max);
		biggest.push({ w: max, name: p.name.slice(0, 42) });
	}
}

widths.sort((a, b) => a - b);
const pct = (p) => widths[Math.floor(widths.length * p)];

console.log(`\n${widths.length} images across ${products.length} products\n`);
console.log('original upload width (largest srcset candidate)');
console.log(`  min    : ${widths[0]}`);
console.log(`  p10    : ${pct(0.1)}`);
console.log(`  median : ${pct(0.5)}`);
console.log(`  p90    : ${pct(0.9)}`);
console.log(`  max    : ${widths[widths.length - 1]}`);

const buckets = {
	'< 500px  (thumbnail-grade)': widths.filter((w) => w < 500).length,
	'500-799px': widths.filter((w) => w >= 500 && w < 800).length,
	'800-1199px': widths.filter((w) => w >= 800 && w < 1200).length,
	'1200-1919px': widths.filter((w) => w >= 1200 && w < 1920).length,
	'>= 1920px (hero-grade)': widths.filter((w) => w >= 1920).length,
};

console.log('');
for (const [label, n] of Object.entries(buckets)) {
	console.log(`  ${label.padEnd(28)} ${String(n).padStart(4)}  ${Math.round((n / widths.length) * 100)}%`);
}

console.log('\nlargest images in the catalogue:');
for (const item of biggest.sort((a, b) => b.w - a.w).slice(0, 8)) {
	console.log(`  ${String(item.w).padStart(5)}w  ${item.name}`);
}

// A full-bleed hero on a 2x display wants ~2560px. A framed one wants ~1200.
const heroGrade = widths.filter((w) => w >= 1600).length;
console.log(`\nimages that could carry a full-bleed hero (>=1600w): ${heroGrade}`);
