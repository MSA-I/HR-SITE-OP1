<?php
/**
 * Inline SVG icons.
 *
 * Why a helper and not hand-written SVG per template: the count crossed the threshold.
 * Three icons in one file was fine; twelve across the header, footer, cards and product
 * page is the point where the same <svg> gets pasted with a drifting viewBox.
 *
 * Why not a <symbol>/<use> sprite: the theme currently makes zero extra requests for
 * chrome, and a sprite costs either a fetch or an inline blob in every document. It also
 * puts a shadow boundary between the icon and its colour, and currentColor inheritance
 * through <use> is precisely where stroke colour stops following the link it lives in.
 * Inlining per call keeps both properties; this helper only deduplicates the markup.
 *
 * The real reason it exists, though, is `directional`. base/typography.css documents the
 * rule — "Arrows and chevrons mirror. Cart, heart, search, WhatsApp never do" — and
 * implements [dir='rtl'] .icon--directional { transform: scaleX(-1) }, but nothing
 * enforced which icons got the class, so it was a comment and an honour system. Here
 * mirroring is a property of the icon itself and the caller cannot set it. Shipping a
 * back-to-front cart is now not a thing you can typo.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

/**
 * The icon table.
 *
 * Per-icon geometry rather than one house style: the UI icons are 24-grid strokes at
 * 1.5, the brand marks are filled logos on their own grids. Forcing a brand glyph
 * through stroke-width:1.5 does not produce a thinner logo, it produces a wrong one.
 *
 * @return array<string,array{viewBox:string,path:string,directional?:bool,fill?:string,stroke?:string,stroke_width?:string}>
 */
function hrd_icons() {
	return apply_filters(
		'hrd_icons',
		array(
			// ---- UI: 24 grid, stroke 1.5, never mirrored ----------------------
			// Ported verbatim from templates/header/actions.php so a later pass can
			// migrate that file without a visual diff.
			'search'    => array(
				'viewBox' => '0 0 24 24',
				'path'    => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
			),
			'heart'     => array(
				'viewBox' => '0 0 24 24',
				'path'    => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1L12 21l7.7-7.6 1.1-1a5.5 5.5 0 0 0 0-7.8z"/>',
			),
			'cart'      => array(
				'viewBox' => '0 0 24 24',
				'path'    => '<path d="M6 6h15l-1.5 9h-12z"/><path d="M6 6 5 2H2"/><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/>',
			),

			// ---- Directional: authored pointing right, mirrored in RTL ---------
			// Authored LTR-forward and flipped by CSS, rather than authored per
			// direction. "Forward" in Hebrew is leftward, so the RTL mirror is what
			// makes this correct, not a workaround.
			'arrow'     => array(
				'viewBox'     => '0 0 24 24',
				'path'        => '<path d="M4 12h15"/><path d="m13 6 6 6-6 6"/>',
				'directional' => true,
			),
			'chevron'   => array(
				'viewBox'     => '0 0 24 24',
				'path'        => '<path d="m9 5 7 7-7 7"/>',
				'directional' => true,
			),

			// ---- Brand marks: filled, own grids, monochrome ---------------------
			// Each scheme permits a single-colour rendition of its glyph, which is why
			// these inherit currentColor rather than carrying brand colour.
			'facebook'  => array(
				'viewBox'      => '0 0 24 24',
				'fill'         => 'currentColor',
				'stroke'       => 'none',
				'stroke_width' => '0',
				'path'         => '<path d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.68.24 2.68.24v2.97h-1.51c-1.49 0-1.96.93-1.96 1.89v2.25h3.33l-.53 3.49h-2.8V24C19.61 23.1 24 18.1 24 12.07z"/>',
			),
			'instagram' => array(
				'viewBox'      => '0 0 24 24',
				'fill'         => 'currentColor',
				'stroke'       => 'none',
				'stroke_width' => '0',
				'path'         => '<path d="M12 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.42.36 1.06.41 2.23.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23-.22.56-.48.96-.9 1.38-.42.42-.82.68-1.38.9-.42.16-1.06.36-2.23.41-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.25-2.23-.41-.56-.22-.96-.48-1.38-.9-.42-.42-.68-.82-.9-1.38-.16-.42-.36-1.06-.41-2.23-.06-1.27-.07-1.65-.07-4.85s.01-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.42-.16 1.06-.36 2.23-.41 1.27-.06 1.65-.07 4.85-.07zM12 0C8.74 0 8.33.01 7.05.07 5.78.13 4.9.33 4.14.63c-.79.3-1.46.72-2.13 1.38C1.35 2.68.93 3.35.63 4.14.33 4.9.13 5.78.07 7.05.01 8.33 0 8.74 0 12s.01 3.67.07 4.95c.06 1.27.26 2.15.56 2.91.3.79.72 1.46 1.38 2.13.67.66 1.34 1.08 2.13 1.38.76.3 1.64.5 2.91.56C8.33 23.99 8.74 24 12 24s3.67-.01 4.95-.07c1.27-.06 2.15-.26 2.91-.56.79-.3 1.46-.72 2.13-1.38.66-.67 1.08-1.34 1.38-2.13.3-.76.5-1.64.56-2.91.06-1.28.07-1.69.07-4.95s-.01-3.67-.07-4.95c-.06-1.27-.26-2.15-.56-2.91-.3-.79-.72-1.46-1.38-2.13C21.32 1.35 20.65.93 19.86.63c-.76-.3-1.64-.5-2.91-.56C15.67.01 15.26 0 12 0zm0 5.84a6.16 6.16 0 1 0 0 12.32A6.16 6.16 0 0 0 12 5.84zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm7.85-10.4a1.44 1.44 0 1 1-2.88 0 1.44 1.44 0 0 1 2.88 0z"/>',
			),
			'whatsapp'  => array(
				'viewBox'      => '0 0 24 24',
				'fill'         => 'currentColor',
				'stroke'       => 'none',
				'stroke_width' => '0',
				'path'         => '<path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.64.07-.3-.15-1.25-.46-2.39-1.47-.88-.79-1.48-1.76-1.65-2.06-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.61-.92-2.21-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48s1.06 2.88 1.21 3.08c.15.2 2.1 3.2 5.08 4.49.71.3 1.26.49 1.69.63.71.23 1.36.19 1.87.12.57-.09 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.41-.07-.12-.27-.2-.57-.35zM12.04 21.8h-.01a9.8 9.8 0 0 1-4.99-1.37l-.36-.21-3.71.97.99-3.62-.23-.37a9.78 9.78 0 0 1-1.5-5.23c0-5.4 4.4-9.8 9.81-9.8 2.62 0 5.08 1.02 6.93 2.88a9.74 9.74 0 0 1 2.87 6.93c0 5.4-4.4 9.8-9.8 9.8zM20.5 3.49A11.75 11.75 0 0 0 12.04 0C5.56 0 .29 5.27.28 11.75c0 2.07.54 4.09 1.57 5.87L.18 24l6.53-1.71a11.74 11.74 0 0 0 5.33 1.36h.01c6.48 0 11.75-5.27 11.76-11.75a11.68 11.68 0 0 0-3.31-8.41z"/>',
			),
		)
	);
}

/**
 * Build one icon's markup.
 *
 * @param string $name Key in hrd_icons().
 * @param array  $args {
 *     @type int    $size  Square px. Default 20.
 *     @type string $class Extra classes. `icon--directional` is added by the icon's own
 *                         definition and cannot be passed in — that is the whole point.
 *     @type string $label Accessible name. Omit for decorative icons, which is the
 *                         common case: nearly every icon here sits inside a link that
 *                         already has an aria-label, and naming both reads it twice.
 * }
 * @return string Empty string for an unknown name, so a typo drops the icon rather than
 *                emitting a broken <svg> or fataling on a live page.
 */
function hrd_get_icon( $name, $args = array() ) {
	$icons = hrd_icons();

	if ( ! isset( $icons[ $name ] ) ) {
		return '';
	}

	$icon = $icons[ $name ];

	$args = wp_parse_args(
		$args,
		array(
			'size'  => 20,
			'class' => '',
			'label' => '',
		)
	);

	$classes = array( 'icon', 'icon--' . $name );

	// Not negotiable by the caller. The definition decides.
	if ( ! empty( $icon['directional'] ) ) {
		$classes[] = 'icon--directional';
	}

	if ( $args['class'] ) {
		$classes[] = $args['class'];
	}

	// Labelled icons are img/label; unlabelled ones are hidden from the tree entirely.
	$a11y = $args['label']
		? sprintf( 'role="img" aria-label="%s"', esc_attr( $args['label'] ) )
		: 'aria-hidden="true" focusable="false"';

	return sprintf(
		'<svg class="%1$s" viewBox="%2$s" width="%3$d" height="%3$d" fill="%4$s" stroke="%5$s" stroke-width="%6$s" %7$s>%8$s</svg>',
		esc_attr( implode( ' ', $classes ) ),
		esc_attr( $icon['viewBox'] ),
		(int) $args['size'],
		esc_attr( $icon['fill'] ?? 'none' ),
		esc_attr( $icon['stroke'] ?? 'currentColor' ),
		esc_attr( $icon['stroke_width'] ?? '1.5' ),
		$a11y,
		$icon['path'] // Theme-authored markup, not user input.
	);
}

/**
 * Echo an icon. See hrd_get_icon().
 *
 * @param string $name Icon key.
 * @param array  $args Options.
 */
function hrd_icon( $name, $args = array() ) {
	echo hrd_get_icon( $name, $args ); // phpcs:ignore WordPress.Security.EscapingOutput -- built from the theme's own icon table.
}
