/**
 * CDP helper for the by-light work.
 *
 * Opens its OWN tab rather than attaching to the first page target: several agents share
 * this browser today, and navigating the shared target would yank the page out from under
 * whoever else is mid-measurement. Closes it again on exit.
 */
const CDP = 'http://127.0.0.1:9222';
export const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

class Client {
	constructor(u) {
		this.ws = new WebSocket(u); this.id = 0; this.pending = new Map();
		this.ready = new Promise((res, rej) => { this.ws.addEventListener('open', res); this.ws.addEventListener('error', rej); });
		this.ws.addEventListener('message', (e) => {
			const m = JSON.parse(e.data); const p = this.pending.get(m.id);
			if (!p) return; this.pending.delete(m.id);
			m.error ? p.reject(new Error(m.error.message)) : p.resolve(m.result);
		});
	}
	send(method, params = {}) {
		const id = ++this.id;
		return new Promise((res, rej) => { this.pending.set(id, { resolve: res, reject: rej }); this.ws.send(JSON.stringify({ id, method, params })); });
	}
}

export async function openTab() {
	const tab = await (await fetch(`${CDP}/json/new?about:blank`, { method: 'PUT' })).json();
	const cdp = new Client(tab.webSocketDebuggerUrl);
	await cdp.ready;
	await cdp.send('Page.enable');
	await cdp.send('Runtime.enable');
	cdp.close = () => fetch(`${CDP}/json/close/${tab.id}`);
	return cdp;
}

export async function evaluate(cdp, expression) {
	const r = await cdp.send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
	if (r.exceptionDetails) throw new Error(r.exceptionDetails.exception?.description ?? JSON.stringify(r.exceptionDetails));
	return r.result.value;
}

/**
 * Navigate, then PROVE we landed before anyone reads a pixel.
 *
 * The other agents' scripts all attach to `targets.find(t => t.type === 'page')`, and a
 * freshly opened tab can be that target — so a sibling agent will happily navigate this
 * tab to localhost:8080 mid-wait. That is not hypothetical: it silently produced a
 * screenshot of the homepage captioned as this section's prototype. Assert the URL and
 * re-take the tab if it drifted.
 */
export async function goto(cdp, url, settle = 1500) {
	for (let attempt = 1; attempt <= 6; attempt++) {
		await cdp.send('Page.navigate', { url });
		await sleep(settle);
		const here = await evaluate(cdp, 'location.href');
		if (here && decodeURI(here).includes(decodeURI(url.split('/').pop()))) return;
		console.warn(`  tab drifted to ${here} — retaking (${attempt}/6)`);
	}
	throw new Error(`could not hold the tab on ${url}`);
}
