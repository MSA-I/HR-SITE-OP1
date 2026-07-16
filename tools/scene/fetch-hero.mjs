/**
 * Re-fetches the hero photograph at full resolution.
 *
 *   node tools/scene/fetch-hero.mjs
 *
 * The seeder caps images at 1024w on purpose — 250 products x 2 images at full size is
 * ~900MB against someone else's live store, for cards that render at 400px. But the hero
 * is different: it is one image, it fills a 2560px viewport, and it is the LCP element.
 * At the seeded 473px it was being upscaled 5x — soft, and the first thing the client
 * sees.
 *
 * One extra request. Politeness rules from the seeder still apply.
 */

import { writeFile, readFile, mkdir } from 'node:fs/promises';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const UA = 'HR-Design-Dev-Seeder/1.0 (+local dev seed; contact: studentmoshe@gmail.com)';

/*
 * src 6623 "ספה מגאן" — a styled living room, and one of only 44 room photographs in the
 * catalogue that exist above 1400w (this one is 2560w).
 *
 * The first choice, 5604, was a better composition but its original upload is 473px:
 * stretched across a 2560px viewport it was upscaled 5x. The catalogue's resolution runs
 * backwards for design — product cut-outs reach 3195px while the styled interiors are
 * mostly 473-750px — so the hero has to be picked from what can actually carry it.
 */
const HERO_SRC = 6623;

const products = JSON.parse(await readFile(join(ROOT, 'seed', 'products.json'), 'utf8'));
const product = products.find((p) => p.id === HERO_SRC);
if (!product) {
	console.error(`src ${HERO_SRC} not in the seed`);
	process.exit(1);
}

const image = product.images[0];

/** The largest candidate the srcset offers — the opposite of the seeder's rule. */
function largest(img) {
	if (!img.srcset) return img.src;
	const best = img.srcset
		.split(',')
		.map((part) => {
			const [url, w] = part.trim().split(/\s+/);
			return { url, w: parseInt(w, 10) || 0 };
		})
		.filter((c) => c.url)
		.sort((a, b) => b.w - a.w)[0];
	return best?.url ?? img.src;
}

const url = largest(image);
console.log(`fetching ${url}`);

const res = await fetch(url, { headers: { 'User-Agent': UA } });
if (!res.ok) {
	console.error(`HTTP ${res.status}`);
	process.exit(1);
}

const buf = Buffer.from(await res.arrayBuffer());
await mkdir(join(ROOT, 'seed', 'hero'), { recursive: true });
const out = join(ROOT, 'seed', 'hero', `hero-${HERO_SRC}.jpg`);
await writeFile(out, buf);

console.log(`wrote ${out}  ${(buf.length / 1024).toFixed(0)}KB`);
console.log(`\nsrcset candidates offered:`);
for (const part of (image.srcset || '').split(',')) console.log(`  ${part.trim()}`);
