/**
 * Removes files created by broken shell quoting.
 *
 *   node tools/clean-junk.mjs          # report
 *   node tools/clean-junk.mjs --delete # remove
 *
 * PowerShell one-liners carrying code strings interpret `>` and `|` inside them as
 * redirections, so fragments like `get_id()`, `$label` and `({` land in the repo as
 * empty files. They are always zero bytes — anything with content is left alone and
 * reported, because that would mean something real got clobbered.
 */

import { readdir, stat, unlink } from 'node:fs/promises';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const DELETE = process.argv.includes('--delete');

/** Real project files, by name. Everything else at the root is suspect. */
const KEEP = new Set([
	'.gitignore',
	'docker-compose.yml',
	'package.json',
	'package-lock.json',
	'vite.config.js',
	'cad_mcp.log',
	"הערת ה צ'אט.txt",
	'node_modules',
	'seed',
	'theme',
	'tools',
	'docs',
	'README.md',
	'.git',
]);

const entries = await readdir(ROOT, { withFileTypes: true });
const junk = [];
const suspicious = [];

for (const entry of entries) {
	if (KEEP.has(entry.name)) continue;
	if (entry.isDirectory()) {
		suspicious.push({ name: entry.name, note: 'unexpected directory' });
		continue;
	}

	const info = await stat(join(ROOT, entry.name));
	if (info.size === 0) {
		junk.push(entry.name);
	} else {
		// Non-empty means it might not be junk. Never delete on a guess.
		suspicious.push({ name: entry.name, note: `${info.size} bytes — NOT empty, left alone` });
	}
}

console.log(`\n${junk.length} zero-byte junk file(s)${DELETE ? ' — deleting' : ''}:\n`);
for (const name of junk) console.log(`  ${JSON.stringify(name)}`);

if (suspicious.length) {
	console.log(`\n${suspicious.length} unexpected but NOT empty — check these by hand:\n`);
	for (const s of suspicious) console.log(`  ${JSON.stringify(s.name)} — ${s.note}`);
}

if (DELETE) {
	for (const name of junk) await unlink(join(ROOT, name));
	console.log(`\ndeleted ${junk.length}.`);
} else if (junk.length) {
	console.log('\nre-run with --delete to remove.');
}
