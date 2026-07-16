/**
 * Checks that every relative link and image in the markdown resolves.
 *
 *   node tools/check-links.mjs
 *
 * A broken image on the front page of a pitch repo is worse than no image, and the paths
 * are easy to get wrong — the shots moved from seed/ to docs/ after the README was
 * written.
 */

import { readdir, readFile, access } from 'node:fs/promises';
import { join, dirname, resolve, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');

async function walk(dir) {
	const out = [];
	for (const e of await readdir(dir, { withFileTypes: true })) {
		const p = join(dir, e.name);
		if (/node_modules|\.git|seed/.test(p)) continue;
		if (e.isDirectory()) out.push(...(await walk(p)));
		else if (e.name.endsWith('.md')) out.push(p);
	}
	return out;
}

const files = await walk(ROOT);
let broken = 0;
let checked = 0;

for (const file of files) {
	const text = await readFile(file, 'utf8');
	// ![alt](path) and [text](path)
	const links = [...text.matchAll(/!?\[[^\]]*\]\(([^)]+)\)/g)].map((m) => m[1]);

	for (const raw of links) {
		const target = raw.split('#')[0].trim();
		if (!target || /^(https?:|mailto:|#)/.test(target)) continue;

		checked++;
		// Markdown links resolve relative to the file, except in a repo root README where
		// GitHub resolves them from the repo root too — both are the same here.
		const abs = resolve(dirname(file), target);
		try {
			await access(abs);
		} catch {
			broken++;
			console.log(`  BROKEN  ${relative(ROOT, file)}  ->  ${target}`);
		}
	}
}

console.log(`\n${checked} relative link(s) checked, ${broken} broken.`);
process.exit(broken ? 1 : 0);
