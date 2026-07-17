/**
 * Lists the products with no dimensions, as a compact worklist for estimation.
 *
 *   node tools/seed/dims-todo.mjs > seed/dims-todo.json
 */

import { readFile } from 'node:fs/promises';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const products = JSON.parse(await readFile(join(ROOT, 'seed', 'products.json'), 'utf8'));

const strip = (h) =>
	(h || '')
		.replace(/<[^>]+>/g, ' ')
		.replace(/&quot;/g, '"')
		.replace(/&#8211;/g, '-')
		.replace(/&nbsp;/g, ' ')
		.replace(/\s+/g, ' ')
		.trim();

const NUM = '(\\d+(?:[.,]\\d+)?)';
const SEP = '\\s*[/xX×*]\\s*';
const PATTERNS = [
	new RegExp(`(?:מדריך\\s*)?מיד(?:ות|ה)\\s*:?\\s*${NUM}${SEP}${NUM}${SEP}${NUM}`),
	new RegExp(`אורך\\s*:?\\s*${NUM}[^\\d]{0,12}רוחב\\s*:?\\s*${NUM}[^\\d]{0,12}גובה\\s*:?\\s*${NUM}`),
	new RegExp(`(?:^|\\s)${NUM}${SEP}${NUM}${SEP}${NUM}\\s*(?:ס["״]?מ|cm)`),
	new RegExp(`קוטר\\s*:?\\s*${NUM}[^\\d]{0,14}גובה\\s*:?\\s*${NUM}`),
	new RegExp(`(?:מדריך\\s*)?מיד(?:ות|ה)\\s*:?\\s*${NUM}${SEP}${NUM}`),
];

const todo = products
	.filter((p) => {
		const hasNative = !!(p.dimensions?.length || p.dimensions?.width || p.dimensions?.height);
		const text = `${strip(p.short_description)} ${strip(p.description)}`;
		return !hasNative && !PATTERNS.some((re) => re.test(text));
	})
	.map((p) => ({
		id: p.id,
		name: p.name,
		categories: (p.categories ?? []).map((c) => c.name),
		price: Math.round(Number(p.prices?.price ?? 0) / 100),
		// Trimmed: the estimator needs the type and material cues, not the sales copy.
		desc: strip(p.short_description).slice(0, 180) || strip(p.description).slice(0, 180),
	}));

console.log(JSON.stringify(todo, null, 1));
