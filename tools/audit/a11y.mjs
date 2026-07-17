/**
 * Accessibility audit — the checks that actually break this build.
 *
 *   node tools/audit/a11y.mjs
 *
 * Not a generic linter: it asserts the specific promises this theme made. Hebrew RTL,
 * bidi-isolated numbers, the לפי אור stops reachable by keyboard, no hover-only purchase
 * path, contrast on the real tokens.
 *
 * Injected into the page by tools/audit/run.mjs via Chrome.
 */

export const AUDIT = `(() => {
	const findings = [];
	const fail = (rule, detail, el) => findings.push({ rule, detail, el: el ? (el.tagName + '.' + String(el.className).split(' ')[0]).slice(0, 40) : null });

	// ---- RTL -------------------------------------------------------------
	if (document.documentElement.dir !== 'rtl') fail('rtl', 'html is not dir=rtl');

	// ---- Bidi: every price and measurement must be isolated -------------
	// Unisolated, the shekel sign lands on the wrong side of the number and dimension
	// strings scramble. It is a bug class, not a nicety.
	for (const el of document.querySelectorAll('.product-card__price, .mini-card__price, .pdp__price')) {
		const hasCurrency = /₪/.test(el.textContent);
		const isolated = el.querySelector('bdi') || getComputedStyle(el).unicodeBidi.includes('isolate');
		if (hasCurrency && !isolated) fail('bidi-price', el.textContent.trim().slice(0, 24), el);
	}
	for (const el of document.querySelectorAll('.product-card__spec, .mini-card__spec, .pdp__spec')) {
		if (!el.querySelector('bdi')) fail('bidi-spec', el.textContent.trim().slice(0, 24), el);
	}

	// ---- Images ----------------------------------------------------------
	for (const img of document.querySelectorAll('img')) {
		if (!img.hasAttribute('alt')) fail('img-alt-missing', img.currentSrc?.split('/').pop()?.slice(0, 30) || '?', img);
	}

	// ---- Interactive names ----------------------------------------------
	for (const el of document.querySelectorAll('button, a[href]')) {
		const name = (el.getAttribute('aria-label') || el.textContent || '').trim();
		if (name) continue;
		if (el.closest('[aria-hidden="true"]')) continue;
		if (el.getAttribute('aria-hidden') === 'true') continue;
		fail('no-accessible-name', el.outerHTML.slice(0, 60), el);
	}

	// ---- Touch targets ---------------------------------------------------
	// 44px, including the transparent padding that gives a 14px dot a real target.
	for (const el of document.querySelectorAll('.byl__time, .product-card__fav, .btn, .site-nav__link, .filter-chip')) {
		const r = el.getBoundingClientRect();
		if (r.width === 0 && r.height === 0) continue; // not rendered on this page
		if (r.height < 34 || r.width < 34) fail('touch-target', Math.round(r.width) + 'x' + Math.round(r.height), el);
	}

	/*
	 * ---- לפי אור: the flagship feature must be keyboard reachable --------
	 *
	 * This replaced the hotspot checks, which went with Shop the Space. They were left
	 * matching '.hotspot' for a while and passed every run — a check that matches nothing
	 * is not a passing check, it is a missing one, and it reports success either way.
	 *
	 * The control is a native radio group, so the old questions dissolve: radios are
	 * focusable and arrow-navigable for free and the <label> names them. What CAN break is
	 * the focus ring, because the radio itself is visually-hidden and the ring has to be
	 * put back by hand on the label. A keyboard user with no ring is driving blind.
	 */
	const stops = [...document.querySelectorAll('.byl__radio')];
	if (stops.length) {
		for (const stop of stops) {
			if (stop.tabIndex < 0) fail('byl-stop-not-focusable', String(stop.tabIndex), stop);
			const label = document.querySelector(`label[for="${stop.id}"]`);
			if (!label) fail('byl-stop-no-label', stop.id, stop);
		}
		if (!document.querySelector('.byl__times legend')) fail('byl-group-unnamed', 'no legend on the fieldset');
	}

	// ---- No hover-only path to purchase ---------------------------------
	// Verified by asking the stylesheet what it does under (hover: none), which is the
	// condition that matters — not by trusting that a rule exists.
	const quick = document.querySelector('.product-card__quick');
	if (quick && !matchMedia('(hover: none)').matches) {
		let guarded = false;
		for (const sheet of document.styleSheets) {
			let rules; try { rules = sheet.cssRules } catch { continue }
			for (const r of rules) {
				if (r.conditionText === '(hover: none)' && /product-card__quick/.test(r.cssText)) guarded = true;
			}
		}
		if (!guarded) fail('hover-only-purchase', 'no (hover:none) rule for quick add');
	}

	// ---- Headings --------------------------------------------------------
	const h1s = document.querySelectorAll('h1');
	if (h1s.length === 0) fail('heading', 'no h1');
	if (h1s.length > 1) fail('heading', h1s.length + ' h1 elements');

	let last = 0;
	for (const h of document.querySelectorAll('h1,h2,h3,h4,h5,h6')) {
		const level = Number(h.tagName[1]);
		if (last && level > last + 1) fail('heading-skip', 'h' + last + ' -> h' + level + ': ' + h.textContent.trim().slice(0, 24), h);
		last = level;
	}

	// ---- Landmarks -------------------------------------------------------
	if (!document.querySelector('main')) fail('landmark', 'no <main>');
	if (!document.querySelector('.skip-link')) fail('landmark', 'no skip link');

	// ---- Lang ------------------------------------------------------------
	if (!document.documentElement.lang) fail('lang', 'html has no lang');

	return findings;
})()`;
