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
 *
 * A second note, earned the same way: this opens its OWN tab and closes it afterwards. It
 * used to attach to `targets.find(t => t.type === 'page')` — whatever tab happened to be
 * first — which is fine against a dedicated browser and silently corrupt against a shared
 * one. If anything else is driving that tab, it navigates away mid-run and the capture
 * either hangs on a promise that never settles or, worse, writes a screenshot of somebody
 * else's page under this run's filename.
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
 * @type {Array<{name: string, url: string, vp: object, scroll?: number, fullPage?: boolean, act?: string, wait?: number, actWait?: number}>}
 */
const PRODUCT = '/product/%d7%9e%d7%95%d7%a9%d7%91-%d7%9e%d7%aa%d7%a7%d7%a4%d7%9c-%d7%9c%d7%9e%d7%a7%d7%9c%d7%97%d7%95%d7%9f-hpl-%d7%90%d7%9c%d7%95%d7%9f-%d7%91%d7%94%d7%99%d7%a8/';

/*
 * Two ways to frame a shot:
 *
 *   at:     a selector. Preferred. Cannot drift, because it measures at capture time.
 *   scroll: a number from tools/shots/offsets.mjs, never guessed. The first pass used
 *           round numbers and cut every section heading off the top of the frame — a
 *           section without its title is just a picture.
 *
 * Any `scroll` number below is only true for one layout. The measured tops were taken when
 * section 03 was Shop the Space; By Light is a different height, so EVERY offset after it
 * has moved and offsets.mjs must be re-run before these shots are trusted again. The `at`
 * entries are already immune.
 */
const SHOTS = [
	{ name: '01-hero', url: '/', vp: DESKTOP },
	{ name: '02-categories', url: '/', vp: DESKTOP, scroll: 1030 },
	/*
	 * By Light replaced Shop the Space. These three scroll to the section by SELECTOR
	 * rather than to a measured offset: the old numbers came from tools/shots/offsets.mjs
	 * and every one of them was invalidated the moment the section's height changed. An
	 * element-relative scroll cannot drift, so this section's shots no longer need
	 * offsets.mjs re-run after every edit above them on the page.
	 *
	 * Both stops are captured, because the section's whole argument is the difference
	 * between them: at 07:00 it tells you that you do not need the lamp.
	 */
	{
		name: '03-by-light-morning',
		url: '/',
		vp: DESKTOP,
		at: '[data-byl]',
		wait: 2200,
		// pointerdown cancels the one-shot demo, which would otherwise walk to night mid-shot.
		act: `(() => {
			const s = document.querySelector('[data-byl]');
			s.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }));
			document.getElementById('byl-t-07').checked = true;
		})()`,
	},
	{
		name: '04-by-light-night',
		url: '/',
		vp: DESKTOP,
		at: '[data-byl]',
		wait: 2200,
		// The pitch: the lamp is the only light left, and the only thing for sale.
		act: `(() => {
			const s = document.querySelector('[data-byl]');
			s.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }));
			document.getElementById('byl-t-23').checked = true;
		})()`,
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
	{
		name: '13-by-light-mobile',
		url: '/',
		vp: MOBILE,
		at: '[data-byl]',
		wait: 2200,
		act: `(() => {
			const s = document.querySelector('[data-byl]');
			s.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }));
			document.getElementById('byl-t-23').checked = true;
		})()`,
	},
	{ name: '14-product-mobile', url: PRODUCT, vp: MOBILE, scroll: 60 },
];

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

/** Open a tab of our own. Returns { wsUrl, close }. */
async function connect() {
	let tab;
	try {
		tab = await (await fetch(`${CDP}/json/new?about:blank`, { method: 'PUT' })).json();
	} catch {
		console.error(`No Chrome on ${CDP}.\n`);
		console.error('Start one with:');
		console.error('  chrome.exe --remote-debugging-port=9222 --user-data-dir=%TEMP%\\hrd-shots\n');
		process.exit(1);
	}

	return { wsUrl: tab.webSocketDebuggerUrl, close: () => fetch(`${CDP}/json/close/${tab.id}`) };
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

const { wsUrl, close } = await connect();
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

	if (shot.at) {
		// Scroll by element. A measured offset is only correct until something above it on
		// the page changes height, and then it silently frames the wrong thing.
		await cdp.send('Runtime.evaluate', {
			expression: `(() => {
				const el = document.querySelector('${shot.at}');
				if (!el) throw new Error('no ${shot.at} on the page');
				scrollTo({ top: el.getBoundingClientRect().top + scrollY - 40, behavior: 'instant' });
			})()`,
		});
		await sleep(shot.wait ?? 900);
	} else if (shot.scroll) {
		await cdp.send('Runtime.evaluate', { expression: `scrollTo({top: ${shot.scroll}, behavior: 'instant'})` });
		// The reveal observers need a frame or two after the scroll settles.
		await sleep(shot.wait ?? 900);
	}

	if (shot.act) {
		await cdp.send('Runtime.evaluate', { expression: shot.act, awaitPromise: false });
		/*
		 * Longer than the longest transition an `act` can start, which is by-light's
		 * --byl-dur: 900ms. This was 700, and 700 < 900, so every by-light pitch shot was
		 * captured mid-cross-fade: the outgoing copy line was still visible under the
		 * incoming one, which reads as ghosted text in the deliverable. The section was
		 * fine; the instrument was shooting too early.
		 *
		 * Per-shot `actWait` for anything slower. Do not lower this to "speed up" a run --
		 * the whole set takes seconds and a torn frame costs a re-shoot.
		 */
		await sleep(shot.actWait ?? 1400);
	}

	/*
	 * Wait for the images actually in frame to decode. Everything below the fold is lazy,
	 * so a sleep-and-hope shot of a freshly scrolled section catches it half-loaded — that
	 * is how a shot of an empty stage nearly shipped as this section's pitch image.
	 */
	await cdp.send('Runtime.evaluate', {
		expression: `(async () => {
			const inFrame = [...document.images].filter((img) => {
				const b = img.getBoundingClientRect();
				return b.bottom > 0 && b.top < innerHeight && b.width > 0;
			});
			for (let i = 0; i < 40; i++) {
				if (inFrame.every((img) => img.complete && img.naturalWidth > 0)) break;
				await new Promise((r) => setTimeout(r, 150));
			}
			await Promise.all(inFrame.map((img) => img.decode().catch(() => {})));
		})()`,
		awaitPromise: true,
	});

	const { data } = await cdp.send('Page.captureScreenshot', { format: 'jpeg', quality: 88, captureBeyondViewport: false });
	const file = join(OUT, `${shot.name}.jpg`);
	await writeFile(file, Buffer.from(data, 'base64'));
	console.log(`${shot.name.padEnd(28)} ${shot.vp.width}x${shot.vp.height}@${shot.vp.dsf}x  ${(Buffer.from(data, 'base64').length / 1024).toFixed(0)}KB`);
}

await cdp.send('Emulation.clearDeviceMetricsOverride');
await close();
cdp.ws.close();
console.log(`\n${SHOTS.length} shots -> docs/shots/`);

/*
 * No process.exit(0) here. It used to tear the process down while the WebSocket and the
 * close() fetch were still unwinding, and libuv aborts on Windows with
 * "Assertion failed: !(handle->flags & UV_HANDLE_CLOSING)" — exit code 127 AFTER printing
 * every shot successfully. A capture tool that reports failure on success trains you to
 * ignore its exit code, which is worse than not having one. Closing the socket lets the
 * loop drain and node exits 0 on its own.
 */
