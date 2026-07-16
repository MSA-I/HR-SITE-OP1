/**
 * Guards against the two encoding failures this Windows + Hebrew-path project invites:
 * a UTF-8 BOM (before `<?php` it emits whitespace and breaks headers) and cp1255
 * mojibake from PowerShell round-tripping a UTF-8 file.
 *
 *   node tools/check-encoding.mjs
 */

import { readdir, readFile } from 'node:fs/promises';
import { join, dirname, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const SCAN = [join(ROOT, 'theme'), join(ROOT, 'tools')];
const EXT = /\.(php|css|js|mjs|json)$/;
// This file necessarily contains the very byte patterns it hunts for.
const SKIP = /node_modules|assets[\\/]dist|assets[\\/]fonts|src[\\/]fonts|check-encoding\.mjs/;

// Signatures of UTF-8 bytes decoded as cp1255 (Hebrew) or cp1252.
const MOJIBAKE = /×[-¿]|ג€|Ã[-¿]|â€/;

const findings = [];

async function walk(dir) {
	let entries;
	try {
		entries = await readdir(dir, { withFileTypes: true });
	} catch {
		return;
	}

	for (const entry of entries) {
		const path = join(dir, entry.name);
		if (SKIP.test(path)) continue;
		if (entry.isDirectory()) {
			await walk(path);
			continue;
		}
		if (!EXT.test(entry.name)) continue;

		const buf = await readFile(path);
		const rel = relative(ROOT, path);

		if (buf[0] === 0xef && buf[1] === 0xbb && buf[2] === 0xbf) {
			findings.push(`BOM       ${rel}`);
		}

		const text = buf.toString('utf8');
		if (MOJIBAKE.test(text)) {
			const line = text.split('\n').findIndex((l) => MOJIBAKE.test(l)) + 1;
			findings.push(`MOJIBAKE  ${rel}:${line}`);
		}
		if (text.includes('�')) {
			findings.push(`REPLACEMENT CHAR  ${rel}`);
		}
	}
}

for (const dir of SCAN) await walk(dir);

if (findings.length) {
	console.log(findings.join('\n'));
	console.log(`\n${findings.length} problem(s).`);
	process.exit(1);
}
console.log('clean: no BOM, no mojibake, no replacement chars.');
