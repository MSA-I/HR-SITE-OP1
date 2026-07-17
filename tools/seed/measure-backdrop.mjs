/**
 * Measures the real backdrop luminance of the seeded product photography.
 *
 * The entire "Gallery Apartment" plate rests on the premise that the products sit on
 * WHITE, so mix-blend-mode: multiply drops the backdrop and leaves the product on a
 * coloured plane. The first product measured came back at rgb(236,237,239) — a light
 * blue-grey, not white — which multiply renders as a visible darker rectangle.
 *
 * This decodes the actual JPEGs to find the distribution before choosing a fix.
 * No image library: JPEG corner pixels are read via a minimal baseline decoder path in
 * sharp-free land, so instead we shell out to the browserless route — see below.
 *
 *   node tools/seed/measure-backdrop.mjs
 */

import { readdir, readFile } from 'node:fs/promises';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const IMAGES = join(ROOT, 'seed', 'images');

/**
 * Pull the JPEG's average DC level per MCU is overkill. Instead: most of these files
 * are JPEG, and the cheapest honest read of "what colour is the corner" without a
 * decoder is to let the OS do it. Node has no built-in image decode, so we approximate
 * using the fact that a flat backdrop dominates the file's overall average — which we
 * get from the thumbnail-sized re-encode the Store API already gave us.
 *
 * Simpler and exact: use ImageDecoder if available (Node 24 ships it behind no flag in
 * undici? no). So: fall back to counting the most common byte-run signature is unsound.
 *
 * Decision: decode with the platform's own tool.
 */

const files = (await readdir(IMAGES)).filter((f) => /\.(jpe?g|png|webp)$/i.test(f));
console.log(`${files.length} images on disk`);

// ImageDecoder is available in Node 24 via the Web Platform APIs surface.
if (typeof ImageDecoder === 'undefined') {
	console.log('\nImageDecoder unavailable in this Node build.');
	console.log('Measure in the browser instead: tools/seed/measure-backdrop-page.html');
	process.exit(2);
}

const sample = files.slice(0, 60);
const results = [];

for (const file of sample) {
	const buf = await readFile(join(IMAGES, file));
	const type = file.endsWith('.png') ? 'image/png' : file.endsWith('.webp') ? 'image/webp' : 'image/jpeg';

	try {
		const decoder = new ImageDecoder({ data: buf, type });
		const { image } = await decoder.decode();
		const w = image.displayWidth;
		const h = image.displayHeight;

		// Read the four corners out of the raw frame.
		const size = image.allocationSize();
		const bytes = new Uint8Array(size);
		await image.copyTo(bytes);

		const stride = size / h;
		const at = (x, y) => {
			const o = y * stride + x * 4;
			return (bytes[o] + bytes[o + 1] + bytes[o + 2]) / 3;
		};

		const corners = [at(2, 2), at(w - 3, 2), at(2, h - 3), at(w - 3, h - 3)];
		results.push({ file, luma: Math.round(Math.min(...corners)) });
		image.close();
	} catch {
		// A handful of files are webp/animated; skipping them does not change the picture.
	}
}

const lumas = results.map((r) => r.luma).sort((a, b) => a - b);
const pct = (p) => lumas[Math.floor(lumas.length * p)];

console.log(`\nmeasured: ${lumas.length}`);
console.log(`min    : ${lumas[0]}`);
console.log(`p10    : ${pct(0.1)}`);
console.log(`median : ${pct(0.5)}`);
console.log(`p90    : ${pct(0.9)}`);
console.log(`max    : ${lumas[lumas.length - 1]}`);

const pureWhite = lumas.filter((l) => l >= 250).length;
console.log(`\ncorners at >=250 (true white) : ${pureWhite} of ${lumas.length}`);
console.log(`corners at 230-249 (off-white): ${lumas.filter((l) => l >= 230 && l < 250).length}`);
console.log(`corners below 230 (not a backdrop / dark product): ${lumas.filter((l) => l < 230).length}`);

// The brightness factor that snaps the off-white backdrops to 255. Pixels already at
// 255 clip harmlessly; dark product pixels move imperceptibly.
const target = pct(0.1);
console.log(`\nbrightness() needed to lift p10 (${target}) to 255: ${(255 / target).toFixed(3)}`);
