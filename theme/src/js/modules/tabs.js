/**
 * The new/bestsellers tabs. Real ARIA tabs, real keyboard support.
 */

export function initTabs() {
	const root = document.querySelector('[data-tabs]');
	if (!root) return;

	const tabs = [...root.querySelectorAll('[role="tab"]')];
	if (tabs.length < 2) return;

	const select = (tab) => {
		for (const other of tabs) {
			const active = other === tab;
			other.classList.toggle('is-active', active);
			other.setAttribute('aria-selected', String(active));
			// Roving tabindex: the tablist is one stop, arrows move within it.
			other.tabIndex = active ? 0 : -1;

			const panel = document.getElementById(other.getAttribute('aria-controls'));
			if (panel) {
				panel.classList.toggle('is-active', active);
				panel.hidden = !active;
			}
		}
	};

	for (const tab of tabs) {
		tab.addEventListener('click', () => select(tab));
	}

	root.querySelector('[role="tablist"]')?.addEventListener('keydown', (event) => {
		const index = tabs.indexOf(document.activeElement);
		if (index === -1) return;

		// RTL: ArrowLeft advances, ArrowRight goes back — the visual direction, which is
		// what the user's hand expects.
		const delta = { ArrowLeft: 1, ArrowRight: -1, Home: -index, End: tabs.length - 1 - index }[event.key];
		if (delta === undefined) return;

		event.preventDefault();
		const next = tabs[(index + delta + tabs.length) % tabs.length];
		next.focus();
		select(next);
	});

	select(tabs[0]);
}
