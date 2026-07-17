/**
 * Fetches the five room portal photographs at full resolution.
 *
 *   node tools/seed/fetch-rooms.mjs
 *
 * Same argument as tools/scene/fetch-hero.mjs: the seeder caps downloads at 1024w, which
 * is right for 500 cards against someone else's live store and wrong for the handful of
 * images that carry the page. These five are picked by eye, not by `_hrd_photo_type` —
 * that classifier samples luma only and labels a transparent cutout a "scene", so every
 * one of these was opened and looked at.
 *
 * Politeness rules from the seeder still apply: one request each, spaced, honest UA.
 */

import { writeFile, readFile, mkdir } from 'node:fs/promises';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const UA = 'HR-Design-Dev-Seeder/1.0 (+local dev seed; contact: studentmoshe@gmail.com)';

/*
 * Room -> source product, and WHY that product.
 *
 * `image` is an index into the product's images: the featured shot is often the studio
 * cutout while a later frame is the styled room, so the index matters as much as the id.
 *
 * Rejected, on the record, so nobody re-proposes them:
 *   6623 ספה מגאן  — 2560w and a beautiful living room, but it IS the hero (attachment
 *                    685, _hrd_hero_src 6623). The same photograph twice on one page.
 *   6449 קונסולה לואי — 800x544, and a studio cutout on white.
 *   5375 ספסל אמבטיה — a white stool on a white studio backdrop. Not a room.
 *   5562#2           — 780w, but it is a headboard detail crop, not a room.
 *   6289 שרפרף טיק   — 1800w cutout on white.
 */
const ROOMS = {
	// 2560x2560. Sofa, coffee table, sideboard, art — the fullest living room in the
	// catalogue that is not the hero.
	living: { src: 5932, image: 0 },
	// 2560x2560. A real dining room: table, chairs, plaster wall, rug.
	dining: { src: 6458, image: 0 },
	// 1800x1800 webp. Fluted console, framed art, vase — an entry vignette, and it sits
	// in קונסולות ושידות, which is one of the room's own categories.
	entry: { src: 6186, image: 0 },
	// 630x630 — the catalogue's ceiling for beds; every bed in the seed is 630w. It is a
	// real styled bedroom, which the higher-resolution alternatives are not.
	bedroom: { src: 5562, image: 0 },
	// 1398x2082, and portrait: the only styled bath scene in the catalogue. Everything
	// else under חדרי רחצה is a cutout or a warehouse floor.
	bath: { src: 5991, image: 0 },
};

const products = JSON.parse(await readFile(join(ROOT, 'seed', 'products.json'), 'utf8'));

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

/**
 * Keep the source's real format. The catalogue mixes JPEG and WebP (6186 is WebP), and
 * writing a WebP into a .jpg would hand WordPress a file whose extension lies about it.
 */
function extFor(url, contentType) {
	if (/webp/i.test(contentType) || /\.webp(\?|$)/i.test(url)) return 'webp';
	if (/png/i.test(contentType) || /\.png(\?|$)/i.test(url)) return 'png';
	return 'jpg';
}

await mkdir(join(ROOT, 'seed', 'rooms'), { recursive: true });

for (const [key, { src, image }] of Object.entries(ROOMS)) {
	const product = products.find((p) => p.id === src);
	if (!product) {
		console.error(`${key}: src ${src} not in the seed`);
		process.exitCode = 1;
		continue;
	}

	const img = product.images?.[image];
	if (!img) {
		console.error(`${key}: src ${src} has no image #${image}`);
		process.exitCode = 1;
		continue;
	}

	const url = largest(img);
	const res = await fetch(url, { headers: { 'User-Agent': UA } });
	if (!res.ok) {
		console.error(`${key}: HTTP ${res.status} for ${url}`);
		process.exitCode = 1;
		continue;
	}

	const buf = Buffer.from(await res.arrayBuffer());
	const ext = extFor(url, res.headers.get('content-type') ?? '');
	const out = join(ROOT, 'seed', 'rooms', `${key}-${src}.${ext}`);
	await writeFile(out, buf);

	console.log(`${key.padEnd(8)} src ${src}#${image}  ${(buf.length / 1024).toFixed(0)}KB  -> ${out}`);

	// Space the requests: this is someone's live store.
	await new Promise((r) => setTimeout(r, 500));
}

console.log('\nnow: docker compose exec wpcli wp eval-file /tools/seed/install-rooms.php');
