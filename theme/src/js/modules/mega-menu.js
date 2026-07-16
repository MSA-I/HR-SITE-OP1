/**
 * M7/M9 — mega menu.
 *
 * The panel already opens on CSS :hover, so this is enhancement only: hover-intent so
 * it does not flash open when the pointer crosses the trigger, keyboard and Escape
 * handling, and the sliding rule.
 */

const OPEN_DELAY = 120;
const CLOSE_GRACE = 200;

export function initMegaMenu() {
	const root = document.querySelector('[data-mega-root]');
	const panel = document.querySelector('[data-mega-panel]');
	const trigger = document.querySelector('[data-mega-trigger]');
	if (!root || !panel || !trigger) return;

	let openTimer;
	let closeTimer;

	const open = () => {
		clearTimeout(closeTimer);
		panel.removeAttribute('hidden');
		panel.dataset.open = '';
		trigger.setAttribute('aria-expanded', 'true');
	};

	const close = () => {
		clearTimeout(openTimer);
		delete panel.dataset.open;
		panel.setAttribute('hidden', '');
		trigger.setAttribute('aria-expanded', 'false');
	};

	// Hover-intent: crossing the trigger on the way somewhere else should not open it.
	root.addEventListener('pointerenter', () => {
		clearTimeout(closeTimer);
		openTimer = setTimeout(open, OPEN_DELAY);
	});

	root.addEventListener('pointerleave', () => {
		clearTimeout(openTimer);
		closeTimer = setTimeout(close, CLOSE_GRACE);
	});

	// Touch and keyboard: click toggles. Touch has no hover to intend with.
	trigger.addEventListener('click', (event) => {
		event.preventDefault();
		'open' in panel.dataset ? close() : open();
	});

	document.addEventListener('keydown', (event) => {
		if (event.key !== 'Escape' || !('open' in panel.dataset)) return;
		close();
		trigger.focus(); // Escape must return focus, or the user is stranded.
	});

	// Focus leaving the panel closes it — the keyboard equivalent of pointerleave.
	root.addEventListener('focusout', (event) => {
		if (!root.contains(event.relatedTarget)) close();
	});

	// ---- Category switching ------------------------------------------------

	const setActive = (index) => {
		for (const el of panel.querySelectorAll('[data-mega-cat]')) {
			el.classList.toggle('is-active', el.dataset.megaCat === index);
		}
		for (const el of panel.querySelectorAll('[data-mega-subs]')) {
			el.classList.toggle('is-active', el.dataset.megaSubs === index);
		}
		for (const el of panel.querySelectorAll('[data-mega-plate]')) {
			const active = el.dataset.megaPlate === index;
			el.classList.toggle('is-active', active);
			// The inactive plates are decorative duplicates of a link that already
			// exists in column A — keep them out of the tab order and the AT tree.
			el.setAttribute('aria-hidden', String(!active));
		}
	};

	for (const cat of panel.querySelectorAll('[data-mega-cat]')) {
		cat.addEventListener('pointerenter', () => setActive(cat.dataset.megaCat));
		cat.addEventListener('focus', () => setActive(cat.dataset.megaCat));
	}

	// ---- The sliding rule --------------------------------------------------

	const list = document.querySelector('.site-nav__list');
	if (!list) return;

	const moveRule = (item) => {
		const itemBox = item.getBoundingClientRect();
		const listBox = list.getBoundingClientRect();
		// Measured against the container, so this is sign-agnostic and correct in RTL
		// without special-casing.
		list.style.setProperty('--rule-x', `${itemBox.left - listBox.left}px`);
		list.style.setProperty('--rule-w', `${itemBox.width}px`);
		list.classList.add('has-rule');
	};

	for (const link of list.querySelectorAll('.site-nav__link')) {
		link.addEventListener('pointerenter', () => moveRule(link));
		link.addEventListener('focus', () => moveRule(link));
	}

	list.addEventListener('pointerleave', () => list.classList.remove('has-rule'));
	list.addEventListener('focusout', (event) => {
		if (!list.contains(event.relatedTarget)) list.classList.remove('has-rule');
	});
}
