/**
 * Finds what is making the document wider than the mobile viewport.
 *
 *   node tools/shots/overflow.mjs
 *
 * The body must never scroll sideways; wide content is supposed to scroll inside its own
 * container. Listing "elements past the right edge" was useless here — in RTL the whole
 * page shifts, so every element reports as an offender and the actual culprit hides in
 * 225 rows.
 *
 * This asks the right question instead: which element is WIDER than its container, and
 * does that container scroll?
 */

const CDP = 'http://127.0.0.1:9222';
const BASE = 'http://localhost:8080';

const targets = await (await fetch(`${CDP}/json/list`)).json();
const page = targets.find((t) => t.type === 'page');
if (!page) {
	console.error('no page target');
	process.exit(1);
}

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

for (const url of ['/', '/shop/']) {
	await cdp.send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 3, mobile: true });
	await cdp.send('Page.navigate', { url: BASE + url });
	await new Promise((r) => setTimeout(r, 2500));

	const { result } = await cdp.send('Runtime.evaluate', { expression: EXPR, returnByValue: true });
	const d = JSON.parse(result.value);

	console.log(`\n=== ${url} @390 ===`);
	console.log(`viewport ${d.viewport} · doc scrollWidth ${d.docScrollWidth} · overflow ${d.overflowPx}px\n`);

	console.log('boxes wider than the viewport:');
	for (const w of d.widerThanViewport) console.log(`  ${String(w.w).padStart(5)}px  ${w.tag}.${w.cls}`);
	if (!d.widerThanViewport.length) console.log('  none');

	console.log('\noverflowing with nothing scrolling them:');
	for (const r of d.unscrolledOverflow) {
		console.log(`  +${String(r.over).padStart(4)}px  ${r.tag}.${r.cls}  (client ${r.client}, scroll ${r.scroll}, overflow-x: ${r.overflowX})`);
	}
	if (!d.unscrolledOverflow.length) console.log('  none');
}

await cdp.send('Emulation.clearDeviceMetricsOverride');
process.exit(0);
