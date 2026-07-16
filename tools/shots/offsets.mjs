/**
 * Reports the real scroll offset of each section at a given viewport.
 *
 *   node tools/shots/offsets.mjs
 *
 * The shot list had hand-guessed scroll numbers and they cut the section headings off.
 * Section offsets are a fact the page already knows — ask it rather than guess.
 */

const CDP = 'http://127.0.0.1:9222';
const BASE = 'http://localhost:8080';

const targets = await (await fetch(`${CDP}/json/list`)).json();
const page = targets.find((t) => t.type === 'page');
if (!page) {
	console.error('no page target — start Chrome with --remote-debugging-port=9222');
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

for (const vp of [
	{ label: 'desktop', width: 1440, height: 900, dsf: 2, mobile: false },
	{ label: 'mobile', width: 390, height: 844, dsf: 3, mobile: true },
]) {
	await cdp.send('Emulation.setDeviceMetricsOverride', {
		width: vp.width,
		height: vp.height,
		deviceScaleFactor: vp.dsf,
		mobile: vp.mobile,
	});
	await cdp.send('Page.navigate', { url: BASE + '/' });
	await new Promise((r) => setTimeout(r, 2500));

	const { result } = await cdp.send('Runtime.evaluate', {
		expression: `JSON.stringify([...document.querySelectorAll('main > section, main > .hero')].map(s => {
			const r = s.getBoundingClientRect();
			const head = s.querySelector('h1, h2');
			return {
				cls: s.className.replace(/\\s+/g, ' ').split(' ').filter(c => /hero|sts|collection|trust|ground/.test(c)).join(' ').slice(0, 30),
				top: Math.round(r.top + scrollY),
				height: Math.round(r.height),
				heading: head ? head.textContent.trim().slice(0, 26) : null,
				headingTop: head ? Math.round(head.getBoundingClientRect().top + scrollY) : null,
			};
		}))`,
		returnByValue: true,
	});

	console.log(`\n=== ${vp.label} ${vp.width}x${vp.height} ===\n`);
	for (const s of JSON.parse(result.value)) {
		console.log(
			`  top ${String(s.top).padStart(5)}  h ${String(s.height).padStart(4)}  ${String(s.cls).padEnd(30)} ${s.heading ?? ''}${s.headingTop !== null ? `  (heading at ${s.headingTop})` : ''}`
		);
	}
}

await cdp.send('Emulation.clearDeviceMetricsOverride');
process.exit(0);
