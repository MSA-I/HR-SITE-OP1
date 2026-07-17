/**
 * Finds what is making the document wider than the viewport, and FAILS if it does.
 *
 *   node tools/shots/overflow.mjs        # exits 1 on any sideways scroll
 *
 * The body must never scroll sideways; wide content is supposed to scroll inside its own
 * container. Listing "elements past the right edge" was useless here — in RTL the whole
 * page shifts, so every element reports as an offender and the actual culprit hides in
 * 225 rows.
 *
 * This asks the right question instead: which element is WIDER than its container, and
 * does that container scroll?
 *
 * Two things this got wrong for a long time, both of which let a real bug ship:
 *
 * 1. It only ever ran at 390. Every desktop-only overflow was invisible to it — including
 *    the collection track's, which is a 1440px bug.
 * 2. It exited 0 unconditionally. It printed a report nobody read and called it a gate.
 *    A check that cannot fail is documentation, not a check.
 */

const CDP = 'http://127.0.0.1:9222';
const BASE = 'http://localhost:8080';

/*
 * Desktop is not optional. 1440 is what tools/shots/capture.mjs shoots the pitch at, so
 * it is the width the client actually judges.
 */
const VIEWPORTS = [
	{ label: 'mobile', width: 390, height: 844, deviceScaleFactor: 3, mobile: true },
	{ label: 'desktop', width: 1440, height: 900, deviceScaleFactor: 2, mobile: false },
];

/* A sub-pixel of slop. Fractional layout maths should not turn the build red. */
const TOLERANCE = 1;

/*
 * Open a tab of our own rather than attaching to whatever tab happens to be first.
 *
 * This used to do `targets.find(t => t.type === 'page')`, which is correct against a
 * dedicated browser and quietly wrong against a shared one: if anything else drives that
 * tab, it navigates away mid-run and this gate dies on a promise that never settles —
 * exit 13, no failures listed, nothing to debug. A gate that flakes red is a gate people
 * re-run until it passes, which is the same as not having one.
 */
const tab = await (await fetch(`${CDP}/json/new?about:blank`, { method: 'PUT' })).json().catch(() => null);
if (!tab) {
	console.error(`no Chrome on ${CDP}`);
	process.exit(1);
}
const page = tab;
const closeTab = () => fetch(`${CDP}/json/close/${tab.id}`);

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
			const m = JSON.parse(e.data);
			const p = this.pending.get(m.id);
			if (!p) return;
			this.pending.delete(m.id);
			m.error ? p.reject(new Error(m.error.message)) : p.resolve(m.result);
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

const cdp = new Cdp(page.webSocketDebuggerUrl);
await cdp.ready;
await cdp.send('Page.enable');
await cdp.send('Runtime.enable');

const EXPR = `(() => {
	const vw = document.documentElement.clientWidth;
	const rows = [];

	for (const el of document.querySelectorAll('body *')) {
		const cs = getComputedStyle(el);
		if (cs.position === 'fixed' || cs.display === 'none') continue;

		// The real question: is this box wider than the space it was given, and is
		// nobody scrolling it?
		const overflowsSelf = el.scrollWidth > el.clientWidth + 1;
		const scrolls = /auto|scroll/.test(cs.overflowX);
		if (overflowsSelf && !scrolls) {
			rows.push({
				tag: el.tagName.toLowerCase(),
				cls: String(el.className).split(' ').slice(0, 2).join(' ').slice(0, 30),
				client: el.clientWidth,
				scroll: el.scrollWidth,
				over: el.scrollWidth - el.clientWidth,
				overflowX: cs.overflowX,
			});
		}
	}

	// Widest boxes on the page, whatever they are.
	const widest = [...document.querySelectorAll('body *')]
		.filter(el => getComputedStyle(el).position !== 'fixed')
		.map(el => ({ tag: el.tagName.toLowerCase(), cls: String(el.className).split(' ')[0].slice(0, 28), w: Math.round(el.getBoundingClientRect().width) }))
		.filter(x => x.w > vw)
		.sort((a, b) => b.w - a.w)
		.slice(0, 8);

	return JSON.stringify({
		viewport: vw,
		docScrollWidth: document.documentElement.scrollWidth,
		bodyScrollWidth: document.body.scrollWidth,
		overflowPx: document.documentElement.scrollWidth - vw,
		unscrolledOverflow: rows.sort((a, b) => b.over - a.over).slice(0, 10),
		widerThanViewport: widest,
	});
})()`;

const failures = [];

for (const vp of VIEWPORTS) {
	for (const url of ['/', '/shop/']) {
		await cdp.send('Emulation.setDeviceMetricsOverride', vp);
		await cdp.send('Page.navigate', { url: BASE + url });
		await new Promise((r) => setTimeout(r, 2500));

		const { result } = await cdp.send('Runtime.evaluate', { expression: EXPR, returnByValue: true });
		const d = JSON.parse(result.value);

		console.log(`\n=== ${url} @${vp.width} (${vp.label}) ===`);
		console.log(`viewport ${d.viewport} · doc scrollWidth ${d.docScrollWidth} · overflow ${d.overflowPx}px\n`);

		console.log('boxes wider than the viewport:');
		for (const w of d.widerThanViewport) console.log(`  ${String(w.w).padStart(5)}px  ${w.tag}.${w.cls}`);
		if (!d.widerThanViewport.length) console.log('  none');

		console.log('\noverflowing with nothing scrolling them:');
		for (const r of d.unscrolledOverflow) {
			console.log(`  +${String(r.over).padStart(4)}px  ${r.tag}.${r.cls}  (client ${r.client}, scroll ${r.scroll}, overflow-x: ${r.overflowX})`);
		}
		if (!d.unscrolledOverflow.length) console.log('  none');

		if (d.overflowPx > TOLERANCE) {
			failures.push(`${url} @${vp.width} — document scrolls sideways by ${d.overflowPx}px`);
		}
	}
}

await cdp.send('Emulation.clearDeviceMetricsOverride');
await closeTab();
cdp.ws.close();

if (failures.length) {
	console.error(`\nFAIL — the body must never scroll sideways:\n`);
	for (const f of failures) console.error(`  ${f}`);
	process.exitCode = 1;
} else {
	console.log('\nPASS — no sideways scroll at any viewport.');
}

/*
 * process.exitCode, not process.exit(): exiting outright while the WebSocket and the
 * close() fetch are still unwinding makes libuv abort on Windows with
 * "Assertion failed: !(handle->flags & UV_HANDLE_CLOSING)" — a 127 that overwrites the
 * real verdict. Setting the code lets the loop drain and node exits with it.
 */
