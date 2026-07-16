/**
 * Composes the Shop the Space scene from HR Design's own product cut-outs.
 *
 *   node tools/scene/compose.mjs
 *
 * WHY THIS SHAPE — the reasoning matters more than the code:
 *
 * 1. HR Design has no lifestyle photography of a living room, so there is nothing to
 *    hotspot directly. Their room photos each feature ONE product surrounded by
 *    stylist's props; linking the props to "similar" catalogue items would be inventing
 *    facts about their own store, to their face.
 *
 * 2. The plan was to composite a living room from cut-outs. The cut-outs do not exist:
 *    of 67 studio shots, the living-room furniture is all photographed in rooms. The one
 *    studio sofa is a pale sofa on white, which multiply erases to an empty plate.
 *
 * 3. So the scene does not pretend to be a photograph at all. It is an obviously
 *    ILLUSTRATED plane — flat tinted grounds, hard edges, 1px rules — carrying real
 *    product cut-outs. That is precisely the Gallery Apartment language the rest of the
 *    site already speaks, so it reads as art direction rather than as a fake room. Every
 *    hotspot points at a product HR Design actually sells.
 *
 * The room is an entryway, because that is the room the catalogue can furnish: a
 * pendant, a mirror-clock, a console, a stool, a runner.
 */

import { readFile, writeFile, mkdir } from 'node:fs/promises';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const OUT = join(ROOT, 'seed', 'scene');

const W = 1600;
const H = 900;

// The mobile crop is a re-composition, not a crop.
//
// The scene is 16:9 and the mobile stage is 4:5. Letting object-fit: cover do the work
// sliced the room into an unreadable strip — you saw a doormat and half a console. And a
// 16:9 room shrunk to fit a 390px screen puts five 44px hotspots inside 390px, which is
// the problem the portrait layout exists to solve.
const MW = 1000;
const MH = 1250;

/*
 * Picked by eye from tools/seed/export-studio-ids.php, not matched by regex — there is
 * exactly one scene, and the composer guessing wrong is how a laundry room ended up in
 * the living room on the first attempt.
 *
 * x/y are the object's BASE in stage percent; w is its width as a share of the stage.
 */
const PLAN = [
	{ src: 6659, layer: 'bg', x: 26, y: 40, w: 16, label: 'pendant', m: { x: 30, y: 26, w: 30 } },
	// Sits well clear of the console below it. At y:44 its pin landed on the console's
	// tabletop instead of on the clock — hotspots live inside their layer, so a bg pin
	// renders UNDER the mid layer's furniture and simply disappears.
	{ src: 5961, layer: 'bg', x: 70, y: 36, w: 14, label: 'wall clock', m: { x: 72, y: 46, w: 24 } },
	{ src: 6192, layer: 'mid', x: 68, y: 78, w: 34, label: 'console', m: { x: 62, y: 74, w: 56 } },
	{ src: 5372, layer: 'mid', x: 26, y: 80, w: 13, label: 'stool', m: { x: 24, y: 82, w: 24 } },
	// A threshold mat, not a runner — which is what an entryway wants anyway.
	{ src: 5828, layer: 'fore', x: 44, y: 97, w: 26, label: 'door mat', m: { x: 46, y: 96, w: 46 } },
];

const products = JSON.parse(await readFile(join(ROOT, 'seed', 'products.json'), 'utf8'));
const imageMap = JSON.parse(await readFile(join(ROOT, 'seed', 'image-map.json'), 'utf8'));

const placed = [];
for (const slot of PLAN) {
	const product = products.find((p) => p.id === slot.src);
	if (!product) {
		console.warn(`src ${slot.src} (${slot.label}) not in the seed`);
		continue;
	}
	const file = imageMap[`${product.id}:${product.images?.[0]?.id}`];
	if (!file) {
		console.warn(`src ${slot.src} (${slot.label}) has no cached image`);
		continue;
	}
	placed.push({ ...slot, product, file });
}

if (placed.length < 3) {
	console.error('too few products placed to make a room');
	process.exit(1);
}

await mkdir(OUT, { recursive: true });

/**
 * The room, drawn. Flat planes and hard edges — the plate language, at room scale.
 * No gradients, no blur, no perspective: it must not read as a photograph.
 */
function ground( w, h ) {
	const floorY = h * 0.66;
	return `
	<rect width="${w}" height="${h}" fill="#F2ECE1"/>
	<rect y="${floorY}" width="${w}" height="${h - floorY}" fill="#E6DDCD"/>
	<line x1="0" y1="${floorY}" x2="${w}" y2="${floorY}" stroke="#7A5C42" stroke-width="1" opacity=".45"/>

	<!-- One accent plane, like a painted wall. The section's colour event. -->
	<rect x="${w * 0.5}" width="${w * 0.5}" height="${floorY}" fill="#DDE0CC"/>
	<line x1="${w * 0.5}" y1="0" x2="${w * 0.5}" y2="${floorY}" stroke="#7A5C42" stroke-width="1" opacity=".45"/>

	<!-- A doorway: architecture, drawn with the same hairline as the rest of the site. -->
	<rect x="${w * 0.06}" y="${h * 0.12}" width="${w * 0.16}" height="${floorY - h * 0.12}"
		fill="none" stroke="#7A5C42" stroke-width="1" opacity=".45"/>`;
}

/**
 * Keys the white studio backdrop out to real transparency.
 *
 * The obvious move is mix-blend-mode: multiply, like the product plate uses. It does not
 * survive here: the parallax puts a transform on each layer, a transform creates a
 * stacking context, and a blend mode only reaches its own stacking context — so each
 * product multiplied against its own transparent layer and kept the white box it was
 * shot on. The effect worked in a static preview and broke the moment it moved, which is
 * the worst way for it to break.
 *
 * A colour key sidesteps blending entirely: the layers get genuine alpha and stack like
 * ordinary images, transform or not.
 *
 * alpha = clamp(3 - (R+G+B))  →  white 0, 90% grey 0.3, 70% grey 0.9, black 1.
 * Visually the same as multiplying onto a light ground, which is what the whole plate
 * language already looks like.
 */
const KEY_FILTER = `<filter id="key" x="0" y="0" width="100%" height="100%" color-interpolation-filters="sRGB">
	<feColorMatrix type="matrix" values="1 0 0 0 0
	                                     0 1 0 0 0
	                                     0 0 1 0 0
	                                     -1 -1 -1 3 0"/>
</filter>`;

/**
 * One layer's SVG, at one aspect.
 *
 * @param {string} layer bg | mid | fore
 * @param {boolean} withGround Draw the room behind it.
 * @param {boolean} portrait Use the mobile placement and canvas.
 */
async function layerSvg(layer, withGround, portrait = false) {
	const items = placed.filter((p) => p.layer === layer);
	const cw = portrait ? MW : W;
	const ch = portrait ? MH : H;

	const images = await Promise.all(
		items.map(async (item) => {
			const buf = await readFile(join(ROOT, 'seed', 'images', item.file));
			const b64 = buf.toString('base64');
			const mime = item.file.endsWith('.png') ? 'image/png' : 'image/jpeg';

			const place = portrait ? item.m : item;
			const w = (place.w / 100) * cw;
			const h = w; // the cut-outs are square-ish
			const x = (place.x / 100) * cw - w / 2;
			const y = (place.y / 100) * ch - h;

			return `<image href="data:${mime};base64,${b64}" x="${x.toFixed(1)}" y="${y.toFixed(1)}" width="${w.toFixed(1)}" height="${h.toFixed(1)}" preserveAspectRatio="xMidYMax meet" filter="url(#key)"/>`;
		})
	);

	return `<svg xmlns="http://www.w3.org/2000/svg" width="${cw}" height="${ch}" viewBox="0 0 ${cw} ${ch}">
<defs>${KEY_FILTER}</defs>
${withGround ? ground(cw, ch) : ''}
${images.join('\n')}
</svg>`;
}

for (const [layer, withGround] of Object.entries({ bg: true, mid: false, fore: false })) {
	const count = placed.filter((p) => p.layer === layer).length;

	await writeFile(join(OUT, `layer-${layer}.svg`), await layerSvg(layer, withGround, false));
	await writeFile(join(OUT, `layer-${layer}-mobile.svg`), await layerSvg(layer, withGround, true));

	console.log(`layer-${layer}.svg + -mobile.svg  (${count} products)`);
}

/*
 * Hotspot coordinates fall out of the placement — nothing hand-typed, so a pin cannot
 * drift from the object it names. Both crops get their own set, because the portrait
 * layout moves everything.
 *
 * The 0.42 factor puts the pin above the object's base, at roughly its centre of mass.
 */
const pin = (place) => ({
	x: Number(place.x.toFixed(1)),
	y: Number((place.y - place.w * 0.42).toFixed(1)),
});

await writeFile(
	join(OUT, 'hotspots.json'),
	JSON.stringify(
		placed.map((item) => {
			const d = pin(item);
			const m = pin(item.m);
			return {
				product_src_id: item.product.id,
				name: item.product.name,
				layer: item.layer,
				x_d: d.x,
				y_d: d.y,
				x_m: m.x,
				y_m: m.y,
			};
		}),
		null,
		1
	)
);

console.log(`\n${placed.length} real products placed:`);
for (const item of placed) console.log(`  ${item.layer.padEnd(5)} ${item.product.name}`);
