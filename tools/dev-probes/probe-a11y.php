<?php
/**
 * Dev-only: injects the accessibility audit into any page.
 *
 * Add ?hrd_a11y=1 to a URL and the findings render as a fixed panel. This runs against
 * the REAL page rather than a static analysis, because most of what matters here —
 * computed touch targets, whether <bdi> survived kses, whether a hotspot is focusable —
 * only exists at runtime.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_footer',
	function () {
		if ( empty( $_GET['hrd_a11y'] ) || ! WP_DEBUG ) {
			return;
		}
		?>
		<style>
			#hrd-a11y { position: fixed; inset-block-start: 0; inset-inline-start: 0; z-index: 99999;
				max-block-size: 100vh; overflow: auto; inline-size: 420px; background: #111; color: #eee;
				font: 12px/1.5 ui-monospace, monospace; padding: 12px; direction: ltr; text-align: left; }
			#hrd-a11y h2 { font-size: 13px; margin: 0 0 8px; color: #9fb37a; }
			#hrd-a11y .f { padding: 4px 0; border-bottom: 1px solid #333; }
			#hrd-a11y .r { color: #e06c5b; font-weight: 700; }
			#hrd-a11y .ok { color: #9fb37a; }
		</style>
		<script>
		(() => {
			const F = [];
			const fail = (rule, detail, el) => F.push({ rule, detail: String(detail ?? '').slice(0, 60),
				el: el ? (el.tagName.toLowerCase() + '.' + String(el.className).split(' ')[0]).slice(0, 30) : '' });

			if (document.documentElement.dir !== 'rtl') fail('rtl', 'html not dir=rtl');
			if (!document.documentElement.lang) fail('lang', 'no lang attribute');
			if (!document.querySelector('main')) fail('landmark', 'no <main>');
			if (!document.querySelector('.skip-link')) fail('landmark', 'no skip link');

			// Bidi: the shekel sign is neutral, so an unisolated price mangles inline.
			for (const el of document.querySelectorAll('.product-card__price, .mini-card__price, .pdp__price, .buy-bar__price')) {
				if (/₪/.test(el.textContent) && !el.querySelector('bdi')) fail('bidi-price', el.textContent.trim(), el);
			}
			for (const el of document.querySelectorAll('.product-card__spec, .mini-card__spec, .pdp__spec')) {
				if (!el.querySelector('bdi')) fail('bidi-spec', el.textContent.trim(), el);
			}

			for (const img of document.querySelectorAll('img')) {
				if (!img.hasAttribute('alt')) fail('img-alt', (img.getAttribute('src') || '').split('/').pop(), img);
			}

			/*
			 * Accessible name, computed the way the platform does it — an <a> wrapping an
			 * <img alt="Sofa"> IS named. Checking only textContent reports every image
			 * link as broken, which buries the real findings.
			 */
			const accName = (el) => {
				const aria = el.getAttribute('aria-label');
				if (aria && aria.trim()) return aria.trim();

				const labelledby = el.getAttribute('aria-labelledby');
				if (labelledby) {
					const text = labelledby.split(/\\s+/).map(id => document.getElementById(id)?.textContent || '').join(' ').trim();
					if (text) return text;
				}

				const text = el.textContent.trim();
				if (text) return text;

				// Contained images contribute their alt.
				for (const img of el.querySelectorAll('img')) {
					const alt = (img.getAttribute('alt') || '').trim();
					if (alt) return alt;
				}

				if (el.labels && el.labels.length) return 'labelled';
				const title = el.getAttribute('title');
				return title && title.trim() ? title.trim() : '';
			};

			for (const el of document.querySelectorAll('button, a[href], input, select')) {
				if (el.type === 'hidden') continue;
				if (el.getAttribute('aria-hidden') === 'true' || el.closest('[aria-hidden="true"]')) continue;
				if (!accName(el)) fail('no-accessible-name', el.outerHTML.slice(0, 50), el);
			}

			/*
			 * offsetWidth, not getBoundingClientRect: the rect includes transforms, so an
			 * element mid-reveal reports whatever scale it is caught at rather than its
			 * real hit area. Worse, a background tab freezes transitions part-way, so the
			 * rect reports whatever frame it stopped on. The layout box is the honest
			 * measure.
			 */
			for (const el of document.querySelectorAll('.byl__time, .product-card__fav, .btn, .site-nav__link, .filter-chip, .tab, .motion-toggle')) {
				if (!el.offsetWidth && !el.offsetHeight) continue; // not rendered here
				if (el.offsetHeight < 34 || el.offsetWidth < 34) fail('touch-target', el.offsetWidth + 'x' + el.offsetHeight, el);
			}

			/*
			 * לפי אור's control. This replaced the hotspot checks, which went with Shop the
			 * Space — they were still here, still matching nothing, and still reporting a
			 * clean pass on every run. A check that matches zero elements is not passing,
			 * it is absent, and it looks identical in the output.
			 *
			 * The control is a native radio group, so focusability and arrow navigation
			 * come free and the <label> supplies the name. What can actually break is the
			 * focus ring: the radio is visually-hidden, so the ring is hand-drawn on the
			 * label, and losing it leaves a keyboard user driving the room blind.
			 */
			for (const stop of document.querySelectorAll('.byl__radio')) {
				if (stop.tabIndex < 0) fail('byl-stop-not-focusable', stop.tabIndex, stop);
				if (!document.querySelector('label[for="' + stop.id + '"]')) fail('byl-stop-no-label', stop.id, stop);
				stop.focus();
				const label = document.querySelector('label[for="' + stop.id + '"]');
				const ring = label && getComputedStyle(label).outlineStyle;
				if (stop.matches(':focus-visible') && (!ring || ring === 'none')) fail('byl-stop-no-focus-ring', stop.id, stop);
			}
			if (document.querySelector('.byl') && !document.querySelector('.byl__times legend')) {
				fail('byl-group-unnamed', 'the fieldset has no legend');
			}

			const h1 = document.querySelectorAll('h1');
			if (h1.length !== 1) fail('heading', h1.length + ' h1 elements');
			let last = 0;
			for (const h of document.querySelectorAll('h1,h2,h3,h4,h5,h6')) {
				const lv = +h.tagName[1];
				if (last && lv > last + 1) fail('heading-skip', 'h' + last + ' to h' + lv + ': ' + h.textContent.trim().slice(0, 20), h);
				last = lv;
			}

			// Hebrew must never carry positive tracking: it dissolves word recognition.
			for (const el of document.querySelectorAll('h1, h2, h3, p, a, button, li')) {
				const cs = getComputedStyle(el);
				if (!/[֐-׿]/.test(el.textContent || '')) continue;
				if (cs.fontFamily.includes('Plex')) continue; // mono runs are Latin
				const ls = parseFloat(cs.letterSpacing);
				if (ls > 0.2) fail('hebrew-tracking', cs.letterSpacing, el);
				if (cs.fontStyle === 'italic') fail('hebrew-italic', 'synthesised oblique', el);
			}

			const panel = document.createElement('div');
			panel.id = 'hrd-a11y';
			panel.innerHTML = '<h2>a11y — ' + (F.length ? F.length + ' finding(s)' : '<span class=ok>clean</span>') + '</h2>' +
				F.map(f => '<div class=f><span class=r>' + f.rule + '</span> ' + f.detail + ' <em>' + f.el + '</em></div>').join('');
			document.body.append(panel);
			window.hrdA11y = F;
		})();
		</script>
		<?php
	},
	99
);
