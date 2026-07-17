/**
 * Reports what is actually parseable out of the seed, before the normalizer is written.
 *
 * Phase 2 is the gate the whole build waits on, so its regexes get designed against
 * measured reality rather than against the one product I happened to look at.
 *
 * The first pass of this script killed the plan's headline assumption: `מדריך מידות`
 * covers 3% of products, not 90%. Dimensions are real but written five different ways.
 *
 *   node tools/seed/analyze.mjs
 */

import { readFile } from 'node:fs/promises';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const products = JSON.parse(await readFile(join(ROOT, 'seed', 'products.json'), 'utf8'));

const strip = (html) =>
	(html || '')
		.replace(/<[^>]+>/g, ' ')
		.replace(/&nbsp;/g, ' ')
		.replace(/&quot;/g, '"')
		.replace(/&#8221;|&#8220;|&#8243;/g, '"')
		.replace(/\s+/g, ' ')
		.trim();

const pct = (n) => `${String(Math.round((n / products.length) * 100)).padStart(3)}%`;

/**
 * Dimension patterns, tried in order. Real formats found in the catalogue:
 *   מדריך מידות: 48/48/45
 *   מידות: 75*45*16
 *   מידות: 60/1/150ס"מ
 *   מידות: אורך: 20 מ"מ רוחב: 40 מ"מ גובה: 60 מ"מ
 *   קוטר 40 גובה 25
 */
const NUM = '(\\d+(?:[.,]\\d+)?)';
const SEP = '\\s*[/xX×*]\\s*';
const PATTERNS = [
	{ id: 'triple-sep', re: new RegExp(`(?:מדריך\\s*)?מיד(?:ות|ה)\\s*:?\\s*${NUM}${SEP}${NUM}${SEP}${NUM}`) },
	{ id: 'labeled', re: new RegExp(`אורך\\s*:?\\s*${NUM}[^\\d]{0,12}רוחב\\s*:?\\s*${NUM}[^\\d]{0,12}גובה\\s*:?\\s*${NUM}`) },
	{ id: 'double-sep', re: new RegExp(`(?:מדריך\\s*)?מיד(?:ות|ה)\\s*:?\\s*${NUM}${SEP}${NUM}`) },
	{ id: 'diameter-height', re: new RegExp(`קוטר\\s*:?\\s*${NUM}[^\\d]{0,14}גובה\\s*:?\\s*${NUM}`) },
	{ id: 'bare-triple', re: new RegExp(`(?:^|\\s)${NUM}${SEP}${NUM}${SEP}${NUM}\\s*(?:ס["״]?מ|cm)`) },
];

const RE_SKU = /מק["״'׳]?\s*ט\s*:?\s*([A-Za-z0-9\-_]{3,})/;

const hits = Object.fromEntries(PATTERNS.map((p) => [p.id, 0]));
let anyText = 0, nativeDims = 0, unionDims = 0;
let skuText = 0, nativeSku = 0, unionSku = 0;
let variable = 0, gallery = 0, onSale = 0, oos = 0, noDimsAtAll = 0;
const attrNames = new Map();
const stillUnparsed = [];

for (const p of products) {
	const text = `${strip(p.short_description)} ${strip(p.description)}`;

	let matched = null;
	for (const pat of PATTERNS) {
		if (pat.re.test(text)) {
			matched = pat.id;
			hits[pat.id]++;
			break;
		}
	}
	if (matched) anyText++;

	const hasNative = !!(p.dimensions?.length || p.dimensions?.width || p.dimensions?.height);
	if (hasNative) nativeDims++;
	if (matched || hasNative) unionDims++;
	else {
		noDimsAtAll++;
		if (/מיד|קוטר|גובה|אורך/.test(text) && stillUnparsed.length < 8) stillUnparsed.push(text.slice(0, 120));
	}

	const skuMatch = RE_SKU.test(text);
	if (skuMatch) skuText++;
	if (p.sku) nativeSku++;
	if (skuMatch || p.sku) unionSku++;

	if (p.type === 'variable') variable++;
	if ((p.images ?? []).length > 1) gallery++;
	if (p.on_sale) onSale++;
	if (!p.is_in_stock) oos++;

	for (const a of p.attributes ?? []) {
		const key = `${a.name}  [${a.taxonomy ?? 'LOCAL'}]`;
		attrNames.set(key, (attrNames.get(key) ?? 0) + 1);
	}
}

console.log(`\n=== SEED: ${products.length} products ===\n`);
console.log('DIMENSIONS — by source');
for (const p of PATTERNS) console.log(`  text: ${p.id.padEnd(16)} ${String(hits[p.id]).padStart(4)}  ${pct(hits[p.id])}`);
console.log(`  ${'text: ANY pattern'.padEnd(22)} ${String(anyText).padStart(4)}  ${pct(anyText)}`);
console.log(`  ${'native WC fields'.padEnd(22)} ${String(nativeDims).padStart(4)}  ${pct(nativeDims)}`);
console.log(`  ${'UNION (what we ship)'.padEnd(22)} ${String(unionDims).padStart(4)}  ${pct(unionDims)}   <-- the dimension bar depends on this`);
console.log(`  ${'no dimensions at all'.padEnd(22)} ${String(noDimsAtAll).padStart(4)}  ${pct(noDimsAtAll)}`);

console.log('\nSKU — by source');
console.log(`  ${'text pattern'.padEnd(22)} ${String(skuText).padStart(4)}  ${pct(skuText)}`);
console.log(`  ${'native WC field'.padEnd(22)} ${String(nativeSku).padStart(4)}  ${pct(nativeSku)}`);
console.log(`  ${'UNION'.padEnd(22)} ${String(unionSku).padStart(4)}  ${pct(unionSku)}`);

console.log('\nOTHER');
console.log(`  ${'variable'.padEnd(22)} ${String(variable).padStart(4)}  ${pct(variable)}`);
console.log(`  ${'second image'.padEnd(22)} ${String(gallery).padStart(4)}  ${pct(gallery)}`);
console.log(`  ${'on sale'.padEnd(22)} ${String(onSale).padStart(4)}  ${pct(onSale)}`);
console.log(`  ${'out of stock'.padEnd(22)} ${String(oos).padStart(4)}  ${pct(oos)}`);

console.log(`\n=== ATTRIBUTES (all LOCAL — layered nav cannot filter these) ===`);
for (const [name, count] of [...attrNames].sort((a, b) => b[1] - a[1])) {
	console.log(`  ${String(count).padStart(4)}x  ${name}`);
}

if (stillUnparsed.length) {
	console.log(`\n=== MENTIONS DIMENSIONS BUT STILL UNPARSED ===`);
	for (const u of stillUnparsed) console.log(`  · ${u}`);
}
