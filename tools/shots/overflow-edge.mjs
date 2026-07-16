/**
 * Walks down the DOM to the first container that overflows the mobile viewport.
 *
 *   node tools/shots/overflow-edge.mjs
 *
 * "Which elements are wide" and "which elements hang off the edge" both came back empty
 * while the document still measured 35px over. So ask the containment chain directly:
 * start at body and follow whichever child is itself over-wide. The last row is the
 * culprit's parent; the child list under it names the culprit.
 */

const CDP = 'http://127.0.0.1:9222';
const BASE = 'http://localhost:8080';

const targets = await (await fetch(`${CDP}/json/list`)).json();
const page = targets.find((t) => t.type === 'page');

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
	const chain = [];
	const describe = (el) => ({
		tag: el.tagName.toLowerCase(),
		cls: String(el.className).split(' ').slice(0, 2).join(' ').slice(0, 30),
		client: el.clientWidth,
		scroll: el.scrollWidth,
		over: el.scrollWidth - el.clientWidth,
		overflowX: getComputedStyle(el).overflowX,
	});

	let el = document.body;
	while (el) {
		chain.push(describe(el));
		const next = [...el.children].find((c) => {
			const cs = getComputedStyle(c);
			if (cs.position === 'fixed' || cs.display === 'none') return false;
			// A container that scrolls itself is containing its own overflow correctly.
			if (/auto|scroll|hidden|clip/.test(cs.overflowX)) return false;
			return c.scrollWidth > vw + 1;
		});
		if (!next) {
			// Nothing further overflows: list what this node actually holds.
			chain.push({ note: 'children of the last row', kids: [...el.children].map(describe).filter((k) => k.scroll > vw + 1 || k.client > vw + 1) });
			break;
		}
		el = next;
	}

	return JSON.stringify({ viewport: vw, docScroll: document.documentElement.scrollWidth, chain });
})()`;

for (const url of ['/shop/', '/']) {
	await cdp.send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 3, mobile: true });
	await cdp.send('Page.navigate', { url: BASE + url });
	await new Promise((r) => setTimeout(r, 2500));

	const { result } = await cdp.send('Runtime.evaluate', { expression: EXPR, returnByValue: true });
	const d = JSON.parse(result.value);

	console.log(`\n=== ${url} @${d.viewport} — document ${d.docScroll} (over ${d.docScroll - d.viewport}px) ===\n`);
	for (const row of d.chain) {
		if (row.note) {
			console.log(`  ${row.note}:`);
			for (const k of row.kids) console.log(`      ${k.tag}.${k.cls}  client ${k.client} scroll ${k.scroll} (+${k.over}) overflow-x: ${k.overflowX}`);
			if (!row.kids.length) console.log('      none over-wide');
			continue;
		}
		console.log(`  ${row.tag}.${row.cls}`.padEnd(40) + `client ${String(row.client).padStart(4)} scroll ${String(row.scroll).padStart(4)} (+${row.over})  overflow-x: ${row.overflowX}`);
	}
}

await cdp.send('Emulation.clearDeviceMetricsOverride');
process.exit(0);
