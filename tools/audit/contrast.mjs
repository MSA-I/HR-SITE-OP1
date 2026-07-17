/**
 * Contrast audit of the design tokens.
 *
 *   node tools/audit/contrast.mjs
 *
 * The design claims olive and rust are luminance-matched so swapping the collection
 * accent never changes hierarchy weight, and that deep blue is the exception that must
 * run in reverse mode. Those are numeric claims — this checks them instead of trusting
 * the spec.
 */

const TOKENS = {
	'cream-050': '#FAF6EF',
	'cream-100': '#F2ECE1',
	'cream-200': '#E6DDCD',
	'brown-500': '#7A5C42',
	'brown-700': '#4A3527',
	'ink-600': '#5A524A',
	'ink-900': '#191512',
	'ok-500': '#3F6B4A',
	'sale-500': '#A9482A',
	'err-500': '#8C2F2F',
	'olive': '#5E6B3E',
	'olive-tint': '#DDE0CC',
	'olive-deep': '#2F3720',
	'rust': '#A9482A',
	'rust-tint': '#F0DACD',
	'rust-deep': '#52220F',
	'blue': '#1E3A54',
	'blue-tint': '#D2DAE2',
	'blue-deep': '#101F2E',
};

const srgb = (hex) => {
	const n = parseInt(hex.slice(1), 16);
	return [(n >> 16) & 255, (n >> 8) & 255, n & 255].map((c) => {
		const s = c / 255;
		return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
	});
};

const luminance = (hex) => {
	const [r, g, b] = srgb(hex);
	return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};

const ratio = (a, b) => {
	const [l1, l2] = [luminance(a), luminance(b)].sort((x, y) => y - x);
	return (l1 + 0.05) / (l2 + 0.05);
};

const T = TOKENS;
let failures = 0;

/**
 * @param {string} label What this pairing is used for.
 * @param {string} fg Foreground token.
 * @param {string} bg Background token.
 * @param {number} min Required ratio. 4.5 = body text, 3 = large text and UI borders.
 */
const check = (label, fg, bg, min) => {
	const r = ratio(T[fg], T[bg]);
	const pass = r >= min;
	if (!pass) failures++;
	console.log(`  ${pass ? 'PASS' : 'FAIL'}  ${label.padEnd(42)} ${r.toFixed(2)}:1  (need ${min})`);
	return r;
};

console.log('\nBody and UI text\n');
check('body text on page', 'ink-900', 'cream-100', 4.5);
check('secondary text on page', 'ink-600', 'cream-100', 4.5);
check('mono meta on page', 'brown-500', 'cream-100', 4.5);
check('mono meta on card plate', 'brown-500', 'cream-050', 4.5);
check('mono meta on alt ground', 'brown-500', 'cream-200', 4.5);
check('cream text on brown band', 'cream-050', 'brown-700', 4.5);
check('cream text on ink section', 'cream-050', 'ink-900', 4.5);
check('sale price on page', 'sale-500', 'cream-100', 4.5);
check('in-stock chip', 'ok-500', 'cream-100', 4.5);
check('error text', 'err-500', 'cream-100', 4.5);

console.log('\nAccent — tint mode (dark type on a light tint)\n');
const olive = check('olive on cream (link, eyebrow)', 'olive', 'cream-100', 4.5);
const rust = check('rust on cream (link, eyebrow)', 'rust', 'cream-100', 4.5);
check('olive-deep on olive-tint', 'olive-deep', 'olive-tint', 4.5);
check('rust-deep on rust-tint', 'rust-deep', 'rust-tint', 4.5);
check('body text on olive tint plate', 'ink-900', 'olive-tint', 4.5);

console.log('\nAccent — the luminance contract\n');
console.log(`  olive on cream : ${olive.toFixed(2)}:1`);
console.log(`  rust  on cream : ${rust.toFixed(2)}:1`);
const drift = Math.abs(olive - rust);
const matched = drift < 0.5;
if (!matched) failures++;
console.log(`  ${matched ? 'PASS' : 'FAIL'}  olive/rust luminance-matched: drift ${drift.toFixed(2)} (need < 0.50)`);

const blue = ratio(T.blue, T['cream-100']);
console.log(`\n  blue on cream  : ${blue.toFixed(2)}:1  — reads as a DARK, not a colour`);
console.log(`  ${blue > 7 ? 'PASS' : 'FAIL'}  blue is the reverse-mode exception (needs > 7, hence --acc-mode: reverse)`);
if (blue <= 7) failures++;
check('cream text on full blue (reverse mode)', 'cream-050', 'blue', 4.5);

console.log('\nAccent on the dark section\n');
// Still exactly the right pair to check: it is now the active stop's underline and the
// focus ring on the לפי אור control, both olive on the ink ground.
check('olive accent on ink (active stop + focus ring)', 'olive', 'ink-900', 3);
check('cream on ink (the dark section\'s text)', 'cream-050', 'ink-900', 3);

console.log(failures ? `\n${failures} contrast failure(s).\n` : '\nAll contrast pairings pass.\n');
process.exit(failures ? 1 : 0);
