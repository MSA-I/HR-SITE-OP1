/**
 * Dimension coverage per top-level category.
 *
 * The card's dimension bar is a scale comparator: it only means anything if most of
 * the grid participates. 51% catalogue-wide is useless as a global feature — but if
 * coverage clusters by category, the bar becomes a per-category feature that is
 * consistent wherever it appears. This measures whether that is true.
 *
 *   node tools/seed/dims-by-category.mjs
 */

import { readFile } from 'node:fs/promises';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const products = JSON.parse(await readFile(join(ROOT, 'seed', 'products.json'), 'utf8'));
const categories = JSON.parse(await readFile(join(ROOT, 'seed', 'categories.json'), 'utf8'));

const byId = new Map(categories.map((c) => [c.id, c]));

/** Walk up to the top-level ancestor so subcategories roll up. */
function topLevel(catId) {
	let cat = byId.get(catId);
	let guard = 0;
	while (cat?.parent && byId.has(cat.parent) && guard++ < 10) cat = byId.get(cat.parent);
	return cat?.name ?? 'unknown';
}

const strip = (h) => (h || '').replace(/<[^>]+>/g, ' ').replace(/&quot;/g, '"').replace(/\s+/g, ' ');
const NUM = '(\\d+(?:[.,]\\d+)?)';
const SEP = '\\s*[/xX×*]\\s*';
const PATTERNS = [
	new RegExp(`(?:מדריך\\s*)?מיד(?:ות|ה)\\s*:?\\s*${NUM}${SEP}${NUM}${SEP}${NUM}`),
	new RegExp(`אורך\\s*:?\\s*${NUM}[^\\d]{0,12}רוחב\\s*:?\\s*${NUM}[^\\d]{0,12}גובה\\s*:?\\s*${NUM}`),
	new RegExp(`(?:מדריך\\s*)?מיד(?:ות|ה)\\s*:?\\s*${NUM}${SEP}${NUM}`),
	new RegExp(`קוטר\\s*:?\\s*${NUM}[^\\d]{0,14}גובה\\s*:?\\s*${NUM}`),
	new RegExp(`(?:^|\\s)${NUM}${SEP}${NUM}${SEP}${NUM}\\s*(?:ס["״]?מ|cm)`),
];

const stats = new Map();

for (const p of products) {
	const text = `${strip(p.short_description)} ${strip(p.description)}`;
	const hasText = PATTERNS.some((re) => re.test(text));
	const hasNative = !!(p.dimensions?.length || p.dimensions?.width || p.dimensions?.height);
	const has = hasText || hasNative;

	// A product can sit in several categories; count it under each top-level root.
	const roots = new Set((p.categories ?? []).map((c) => topLevel(c.id)));
	for (const root of roots) {
		if (!stats.has(root)) stats.set(root, { total: 0, with: 0 });
		const s = stats.get(root);
		s.total++;
		if (has) s.with++;
	}
}

console.log('\nDimension coverage by top-level category\n');
console.log('  cover   n     category');
console.log('  ─────  ────  ─────────────────────────');

for (const [name, s] of [...stats].sort((a, b) => b[1].with / b[1].total - a[1].with / a[1].total)) {
	if (s.total < 4) continue; // too few to conclude anything
	const p = Math.round((s.with / s.total) * 100);
	const bar = '█'.repeat(Math.round(p / 5)).padEnd(20, '·');
	console.log(`  ${String(p).padStart(3)}%  ${String(s.total).padStart(4)}  ${bar}  ${name}`);
}
