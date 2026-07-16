/**
 * What is actually in the theme, and what is only tooling.
 *
 *   node tools/inventory.mjs
 *
 * The deliverable is theme/ alone. Everything under tools/ and seed/ is how it was
 * built, not what gets handed over.
 */

import { readdir, stat, readFile } from 'node:fs/promises';
import { join, dirname, extname, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');

async function walk(dir, skip = /node_modules|\.git/) {
	const out = [];
	let entries;
	try {
		entries = await readdir(dir, { withFileTypes: true });
	} catch {
		return out;
	}
	for (const e of entries) {
		const p = join(dir, e.name);
		if (skip.test(p)) continue;
		if (e.isDirectory()) out.push(...(await walk(p, skip)));
		else out.push(p);
	}
	return out;
}

async function report(label, dir, skip) {
	const files = await walk(dir, skip);
	const byExt = new Map();
	let bytes = 0;
	let lines = 0;

	for (const f of files) {
		const ext = extname(f) || '(none)';
		const info = await stat(f);
		bytes += info.size;
		const entry = byExt.get(ext) ?? { n: 0, bytes: 0, lines: 0 };
		entry.n++;
		entry.bytes += info.size;

		if (/\.(php|css|js|mjs|json)$/.test(f) && info.size < 400_000) {
			const text = await readFile(f, 'utf8');
			const n = text.split('\n').length;
			entry.lines += n;
			lines += n;
		}
		byExt.set(ext, entry);
	}

	console.log(`\n${label} — ${files.length} files, ${(bytes / 1024).toFixed(0)}KB, ${lines} lines\n`);
	for (const [ext, e] of [...byExt].sort((a, b) => b[1].n - a[1].n)) {
		console.log(`  ${ext.padEnd(8)} ${String(e.n).padStart(3)} files  ${String(Math.round(e.bytes / 1024)).padStart(5)}KB${e.lines ? `  ${String(e.lines).padStart(5)} lines` : ''}`);
	}
}

// The theme as shipped: source and templates, not the build output or the webfonts.
await report('THEME (deliverable)', join(ROOT, 'theme'), /node_modules|\.git|assets[\\/]dist|src[\\/]fonts|assets[\\/]fonts/);
await report('TOOLS (how it was built)', join(ROOT, 'tools'), /node_modules|\.git/);
