/**
 * Captures the pitch shots from the running local site.
 *
 *   node tools/shots/capture.mjs
 *
 * Drives Chrome over the DevTools protocol directly — no Puppeteer, no dependency. The
 * browser is already installed; this only needs a debugging port.
 *
 * Chrome must be started with:
 *   chrome --remote-debugging-port=9222 --user-data-dir=<temp>
 *
 * A note earned the hard way: a backgrounded tab does not paint. Metrics come back null
 * and transitions freeze part-way. CDP's Page.captureScreenshot rasterises regardless of
 * tab visibility, which is exactly why this exists instead of a screenshot tool driving a
 * real window.
 */

import { mkdir, writeFile } from 'node:fs/promises';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');

// docs/, not seed/ — these are the deliverable, and seed/ is excluded from the repo
// because it holds HR Design's own photography and catalogue. Writing here keeps the
// committed shots and the capture script from drifting apart.
const OUT = join(ROOT, 'docs', 'shots');
const BASE = 'http://localhost:8080';
const CDP = 'http://127.0.0.1:9222';

const DESKTOP = { width: 1440, height: 900, dsf: 2, mobile: false };
const MOBILE = { width: 390, height: 844, dsf: 3, mobile: true };

/**
 * @type {Array<{name: string, url: string, vp: object, scroll?: number, fullPage?: boolean, act?: string, wait?: number}>}
 */
const PRODUCT = '/product/%d7%9e%d7%95%d7%a9%d7%91-%d7%9e%d7%aa%d7%a7%d7%a4%d7%9c-%d7%9c%d7%9e%d7%a7%d7%9c%d7%97%d7%95%d7%9f-hpl-%d7%90%d7%9c%d7%95%d7%9f-%d7%91%d7%94%d7%99%d7%a8/';

/*
 * Scroll offsets come from tools/shots/offsets.mjs, not from guessing. The first pass
 * used round numbers and cut every section heading off the top of the frame — a section
 * without its title is just a picture.
 *
 * Desktop section tops: hero 77 · categories 977 · STS 2119 · collection 3298 ·
 * new 4044 · rooms 4952 · inspiration 5718 · trust 6693.
 */
const SHOTS = [
	{ name: '01-hero', url: '/', vp: DESKTOP },
	{ name: '02-categories', url: '/', vp: DESKTOP, scroll: 1030 },
	{ name: '03-shop-the-space', url: '/', vp: DESKTOP, scroll: 2180, wait: 2000 },
	{
		name: '04-shop-the-space-open',
		url: '/',
		vp: DESKTOP,
		scroll: 2180,
		wait: 2000,
		// The console's card: the mechanism wired to the sale, not just the room.
		act: `document.querySelectorAll('.hotspot')[2]?.click()`,
	},
	{ name: '05-collection', url: '/', vp: DESKTOP, scroll: 3360 },
	{ name: '06-rooms', url: '/', vp: DESKTOP, scroll: 5010 },
	{ name: '07-catalogue', url: '/shop/', vp: DESKTOP, scroll: 260 },
	{ name: '08-filters', url: '/shop/?filter_color=%D7%98%D7%91%D7%A2%D7%99&query_type_color=or', vp: DESKTOP, scroll: 260 },
	{ name: '09-product', url: PRODUCT, vp: DESKTOP, scroll: 90 },
	{ name: '10-product-diagram', url: PRODUCT, vp: DESKTOP, scroll: 1180 },
	{
		name: '11-mega-menu',
		url: '/shop/',
		vp: DESKTOP,
		act: `(() => { const p = document.querySelector('[data-mega-panel]'); if (p) { p.removeAttribute('hidden'); p.dataset.open = ''; } })()`,
		wait: 700,
	},
	{ name: '12-catalogue-mobile', url: '/shop/', vp: MOBILE, scroll: 380 },
	{ name: '13-shop-the-space-mobile', url: '/', vp: MOBILE, scroll: 1740, wait: 2000 },
	{ name: '14-product-mobile', url: PRODUCT, vp: MOBILE, scroll: 60 },
];

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function connect() {
	let targets;
	try {
		targets = await (await fetch(`${CDP}/json/list`)).json();
	} catch {
		console.error(`No Chrome on ${CDP}.\n`);
		console.error('Start one with:');
		console.error('  chrome.exe --remote-debugging-port=9222 --user-data-dir=%TEMP%\\hrd-shots\n');
		process.exit(1);
	}

	const page = targets.find((t) => t.type === 'page');
	if (!page) {
		console.error('No page target.');
		process.exit(1);
	}

	const { default: WS } = await import('node:worker_threads').then(() => ({ default: null })).catch(() => ({ default: null }));
	return page.webSocketDebuggerUrl;
}

/** Minimal CDP client over the built-in WebSocket. */
class Cdp {
	constructor(url) {
		this.ws = new WebSocket(url);
		this.id = 0;
		this.pending = new Map();
		this.ready = new Promise((res, rej) => {
			this.ws.addEventListener('open', res);
			this.ws.addEventListener('error', rej);
		});
		this.ws.addEventListener('message', (e) => {
			const msg = JSON.parse(e.data);
			const p = this.pending.get(msg.id);
			if (!p) return;
			this.pending.delete(msg.id);
			msg.error ? p.reject(new Error(msg.error.message)) : p.resolve(msg.result);
		});
	}

	send(method, params = {}) {
		const id = ++this.id;
		return new Promise((resolve, reject) => {
			this.pending.set(id, { resolve, reject });
			this.ws.send(JSON.stringify({ id, method, params }));
		});
	}
}

const wsUrl = await connect();
const cdp = new Cdp(wsUrl);
await cdp.ready;

await cdp.send('Page.enable');
await cdp.send('Runtime.enable');
await mkdir(OUT, { recursive: true });

for (const shot of SHOTS) {
	await cdp.send('Emulation.setDeviceMetricsOverride', {
		width: shot.vp.width,
		height: shot.vp.height,
		deviceScaleFactor: shot.vp.dsf,
		mobile: shot.vp.mobile,
	});

	await cdp.send('Page.navigate', { url: BASE + shot.url });
	await sleep(2200);

	if (shot.scroll) {
		await cdp.send('Runtime.evaluate', { expression: `scrollTo({top: ${shot.scroll}, behavior: 'instant'})` });
		// The reveal observers need a frame or two after the scroll settles.
		await sleep(shot.wait ?? 900);
	}

	if (shot.act) {
		await cdp.send('Runtime.evaluate', { expression: shot.act, awaitPromise: false });
		await sleep(700);
	}

	const { data } = await cdp.send('Page.captureScreenshot', { format: 'jpeg', quality: 88, captureBeyondViewport: false });
	const file = join(OUT, `${shot.name}.jpg`);
	await writeFile(file, Buffer.from(data, 'base64'));
	console.log(`${shot.name.padEnd(28)} ${shot.vp.width}x${shot.vp.height}@${shot.vp.dsf}x  ${(Buffer.from(data, 'base64').length / 1024).toFixed(0)}KB`);
}

await cdp.send('Emulation.clearDeviceMetricsOverride');
console.log(`\n${SHOTS.length} shots -> seed/shots/`);
process.exit(0);
