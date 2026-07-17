/**
 * Frame-consistency gate for the AI-relit stops.
 *
 *   node byl-frames.mjs <07> <12> <18> <23>
 *
 * THE QUESTION, and why it is the one that matters:
 *
 * Four independently generated frames of the same room will drift — a ginger jar shifts,
 * the plant regrows, a sofa seam moves. Judged one at a time every frame can look superb
 * and the set still be unusable, because cross-fading between drifting frames does not read
 * as "the light changed", it reads as the FURNITURE MORPHING. Motion is what the eye is
 * best at, so this failure is louder than the composite it replaced.
 *
 * The physical claim: relighting changes LUMA. It must not move EDGES.
 *
 * METHOD — block-wise normalised cross-correlation, and the first version was wrong.
 *
 * v1 asked "does each reference edge have an edge within +/-2px in the other frame". It
 * passed a deliberate 6px shift of the whole sofa. In a densely textured photograph every
 * edge finds SOME neighbour, so the metric saturates near 100% and measures edge DENSITY,
 * not edge POSITION. It was decoration, and only a fixture with known drift exposed it.
 *
 * v2 measures the thing directly: for each grid cell, find the (dx,dy) that best aligns
 * the cell between frames. If the room is the same room, the answer is (0,0). Anything
 * else is content that moved, and the magnitude IS the drift in pixels.
 *
 *   - Correlate GRADIENT MAGNITUDE, not luma: gradients survive a brightness change.
 *   - Contrast-normalise per cell, or the test just rediscovers that night is darker.
 *   - Report the correlation peak too. A cell crushed to black at 23:00 carries no
 *     structure to align, and the honest answer there is "indeterminate", not "pass".
 */
import { readFile } from 'node:fs/promises';
import { writeFile } from 'node:fs/promises';
import { basename } from 'node:path';
import { openTab, goto, evaluate } from './byl-cdp.mjs';

const OUT = 'C:/Users/art1/AppData/Local/Temp/claude/D----------------HR-DESIGN-SITE/f9881659-8f57-4fe5-be81-fba73bb4d9c6/scratchpad';

const files = process.argv.slice(2);
if (files.length !== 4) {
	console.error('need exactly 4 frames, in order: 07 12 18 23');
	process.exit(1);
}

const mime = (f) => (f.toLowerCase().endsWith('.png') ? 'image/png' : 'image/jpeg');
const imgs = await Promise.all(files.map(async (f) => `data:${mime(f)};base64,${(await readFile(f)).toString('base64')}`));

await writeFile(
	`${OUT}/byl-frames.html`,
	`<!doctype html><meta charset="utf-8">
${imgs.map((src, i) => `<img id="f${i}" src="${src}">`).join('\n')}
<script>
window.ready = Promise.all([...document.images].map(i => i.decode()));

/** Gradient magnitude at NxN. Gradients, because they survive a brightness change. */
window.grad = (id, N) => {
  const img = document.getElementById(id);
  const c = document.createElement('canvas'); c.width = N; c.height = N;
  const x = c.getContext('2d', { willReadFrequently: true });
  x.drawImage(img, 0, 0, N, N);
  const d = x.getImageData(0, 0, N, N).data;
  const L = new Float32Array(N * N);
  for (let i = 0; i < N * N; i++) L[i] = (0.2126*d[i*4] + 0.7152*d[i*4+1] + 0.0722*d[i*4+2]) / 255;
  const g = new Float32Array(N * N);
  for (let y = 1; y < N-1; y++) for (let xx = 1; xx < N-1; xx++) {
    const i = y*N + xx;
    const gx = -L[i-N-1] - 2*L[i-1] - L[i+N-1] + L[i-N+1] + 2*L[i+1] + L[i+N+1];
    const gy = -L[i-N-1] - 2*L[i-N] - L[i-N+1] + L[i+N-1] + 2*L[i+N] + L[i+N+1];
    g[i] = Math.hypot(gx, gy);
  }
  return g;
};

/**
 * Best alignment offset for one cell, by normalised cross-correlation over a search box.
 * Returns { dx, dy, peak }. peak is how confident the alignment is: low peak = the cell
 * has no structure left to align (crushed to black), so the verdict is indeterminate.
 */
window.align = (A, B, N, x0, x1, y0, y1, R) => {
  const patch = (S, ox, oy) => {
    const v = [];
    for (let y = y0; y < y1; y++) for (let x = x0; x < x1; x++) {
      const yy = y + oy, xx = x + ox;
      v.push(yy < 0 || yy >= N || xx < 0 || xx >= N ? 0 : S[yy*N + xx]);
    }
    return v;
  };
  const norm = (v) => {
    let m = 0; for (const q of v) m += q; m /= v.length;
    let s = 0; for (const q of v) s += (q-m)**2; s = Math.sqrt(s) || 1e-9;
    return v.map(q => (q-m)/s);
  };
  const a = norm(patch(A, 0, 0));
  let best = { dx: 0, dy: 0, peak: -2 };
  for (let dy = -R; dy <= R; dy++) for (let dx = -R; dx <= R; dx++) {
    const b = norm(patch(B, dx, dy));
    let s = 0; for (let i = 0; i < a.length; i++) s += a[i]*b[i];
    if (s > best.peak) best = { dx, dy, peak: s };
  }
  return best;
};
</script>`
);

const cdp = await openTab();
await goto(cdp, `file:///${OUT}/byl-frames.html`, 2000);

const report = await evaluate(
	cdp,
	`(async () => {
  await window.ready;
  const N = 512, R = 8, G = 4;
  const dims = [...document.images].map(i => i.naturalWidth + 'x' + i.naturalHeight);
  const F = [0,1,2,3].map(i => window.grad('f' + i, N));
  const cells = [];
  for (let gy = 0; gy < G; gy++) for (let gx = 0; gx < G; gx++) {
    const x0 = Math.floor(gx*N/G), x1 = Math.floor((gx+1)*N/G);
    const y0 = Math.floor(gy*N/G), y1 = Math.floor((gy+1)*N/G);
    const stops = [];
    for (let i = 1; i < 4; i++) {
      const r = window.align(F[0], F[i], N, x0, x1, y0, y1, R);
      // scale offsets from the 512 analysis grid back to source pixels
      stops.push({ stop: ['12','18','23'][i-1], dx: r.dx, dy: r.dy,
                   shift: Math.hypot(r.dx, r.dy), peak: r.peak });
    }
    cells.push({ gx, gy, stops });
  }
  return { dims, cells, N };
})()`
);

console.log(`frames: ${files.map((f) => basename(f)).join('  ')}`);
console.log(`sizes : ${report.dims.join('  ')}`);
const square = report.dims.every((d) => d.split('x')[0] === d.split('x')[1]);
const same = new Set(report.dims).size === 1;
console.log(`square: ${square ? 'yes' : 'NO — the layout assumes square'}   identical dims: ${same ? 'yes' : 'NO'}`);

const scale = Number(report.dims[0].split('x')[0]) / report.N;

console.log(`\nworst drift per region, in SOURCE px (analysis grid ${report.N}, x${scale.toFixed(1)}):`);
console.log('rows top->bottom, cols LEFT->RIGHT of the photograph');
const grid = [];
for (let gy = 0; gy < 4; gy++) {
	const row = [];
	for (let gx = 0; gx < 4; gx++) {
		const c = report.cells.find((c) => c.gx === gx && c.gy === gy);
		const worst = c.stops.reduce((a, b) => (b.shift > a.shift ? b : a));
		const weak = c.stops.every((s) => s.peak < 0.35);
		row.push({ shift: worst.shift * scale, weak, stop: worst.stop, peak: Math.max(...c.stops.map((s) => s.peak)) });
	}
	grid.push(row);
	console.log('  ' + row.map((v) => (v.weak ? '   ?' : (v.shift).toFixed(0).padStart(4)) + 'px').join(''));
}

/*
 * The lamp sits at x=72% y=36% -> grid col 2, rows 0-1. That is where the light appears,
 * so structure there legitimately changes. Everything else is furniture and must hold.
 */
const fails = [];
const unknown = [];
grid.forEach((row, gy) =>
	row.forEach((v, gx) => {
		const lampCell = gx === 2 && gy <= 1;
		if (lampCell) return;
		if (v.weak) unknown.push(`row ${gy} col ${gx} (peak ${v.peak.toFixed(2)})`);
		else if (v.shift > 2) fails.push(`row ${gy} col ${gx} — ${v.shift.toFixed(0)}px at ${v.stop}:00`);
	})
);

console.log('');
if (unknown.length) {
	console.log('INDETERMINATE — too little structure left to align (crushed to black?):');
	for (const u of unknown) console.log('  ' + u);
	console.log('');
}
if (fails.length) {
	console.log('FAIL — geometry moved where the lamp cannot explain it:');
	for (const f of fails) console.log('  ' + f);
	console.log('\nThese will morph furniture when cross-faded. Regenerate as a LOCKED set:');
	console.log('same seed, img2img at low denoise off the SAME source — not four independent runs.');
	process.exitCode = 1;
} else {
	console.log("PASS — the room holds still outside the lamp's region.");
	console.log('The set cross-fades as light, not as motion.');
}

await cdp.close();
