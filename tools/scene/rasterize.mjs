/**
 * Rasterises the composed SVG layers to PNG.
 *
 * No image library: the SVGs already carry their images as data URIs, so a browser can
 * open them directly. This writes a tiny HTML harness that draws each layer to a canvas
 * and hands back a PNG — the browser is already a dependency here.
 *
 *   node tools/scene/rasterize.mjs   → writes tools/scene/rasterize.html
 *   then open it and the page saves the PNGs itself
 */

import { readdir, writeFile } from 'node:fs/promises';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const SCENE = join(ROOT, 'seed', 'scene');

const files = (await readdir(SCENE)).filter((f) => f.endsWith('.svg'));

const html = `<!doctype html>
<meta charset="utf-8">
<title>scene rasterizer</title>
<style>body{font:14px system-ui;padding:24px;background:#222;color:#eee}
img{max-width:100%;border:1px solid #444;margin-block:8px;background:#fff}
.layer{margin-block-end:24px}</style>
<h1>Shop the Space — composed layers</h1>
<p>Each layer below is the SVG rendered. Right-click to save, or let the page do it.</p>
${files
	.map(
		(f) => `<div class="layer"><h2>${f}</h2><img src="./${f}" data-name="${f.replace('.svg', '.png')}"></div>`
	)
	.join('\n')}
<div id="out"></div>
<script>
window.rasterize = async () => {
  const results = {};
  for (const img of document.querySelectorAll('img[data-name]')) {
    await img.decode();
    const c = document.createElement('canvas');
    c.width = img.naturalWidth; c.height = img.naturalHeight;
    c.getContext('2d').drawImage(img, 0, 0);
    results[img.dataset.name] = c.toDataURL('image/png');
  }
  return results;
};
</script>`;

await writeFile(join(SCENE, 'rasterize.html'), html);
console.log(`wrote ${join(SCENE, 'rasterize.html')}`);
console.log(`layers: ${files.join(', ')}`);
