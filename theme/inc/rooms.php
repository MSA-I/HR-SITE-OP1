<?php
/**
 * The rooms behind section 06, and the archive they land on.
 *
 * A room is not a term. "סלון" is five categories (ספות, כורסאות, שולחנות סלון, שטחי
 * סלון, מזנונים ובופה) and there is no term to hang thumbnail meta on, which is why the
 * portal images live in an `hrd_rooms` option written by tools/seed/install-rooms.php
 * rather than in term meta. Attachment IDs are install-specific: hardcoding them in the
 * theme would break on every fresh import.
 *
 * The slug list, by contrast, IS theme knowledge and stays here — it is the same on every
 * install, and it is the fallback that keeps the section rendering before the CLI ever
 * runs.
 *
 * A warning written in blood: the category slugs are percent-encoded Hebrew in the
 * database (`%d7%a1...`), and the NAME is not the SLUG. Term 24 is named "ספות" but its
 * slug is "ספות-ייצור-אישי"; term 22 is named "מיטות" but its slug is "מיטות-ייצור-אישי".
 * Both bare names match no term at all, so get_term_by() silently returns false and the
 * room quietly loses a category. Verify against `wp term list product_cat` before editing
 * this list, never against the label you see in the admin.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

/**
 * The five rooms, in reading order.
 *
 * Keys are stable identifiers: they travel in the `hrd_room` query var so the archive can
 * title itself, and they key the `hrd_rooms` option that install-rooms.php writes.
 *
 * מרפסת is deliberately absent. `ריהוט גן` has zero products, and a link that lands on an
 * empty archive is worse than no link.
 *
 * @return array<string, array{label:string, slugs:string[]}>
 */
function hrd_rooms() {
	return apply_filters(
		'hrd_rooms',
		array(
			'living'  => array(
				'label' => __( 'סלון', 'hrdesign' ),
				'slugs' => array( 'ספות-ייצור-אישי', 'כורסאות', 'שולחנות-סלון', 'שטחי-סלון', 'מזנונים-ובופה' ),
			),
			'dining'  => array(
				'label' => __( 'פינת אוכל', 'hrdesign' ),
				'slugs' => array( 'פינות-אוכל', 'כיסאות-וכיסאות-בר' ),
			),
			'entry'   => array(
				'label' => __( 'פינת כניסה', 'hrdesign' ),
				'slugs' => array( 'קונסולות-ושידות', 'מראות-גוף' ),
			),
			'bedroom' => array(
				'label' => __( 'חדר שינה', 'hrdesign' ),
				'slugs' => array( 'מיטות-ושידות-צד', 'מיטות-מרופדות', 'מיטות-ייצור-אישי', 'שטחים-חדרי-שינה' ),
			),
			'bath'    => array(
				'label' => __( 'חדרי רחצה', 'hrdesign' ),
				'slugs' => array( 'חדרי-רחצה' ),
			),
		)
	);
}

/**
 * The portal photographs, keyed by room, as written by tools/seed/install-rooms.php.
 *
 * @return array<string, int>
 */
function hrd_room_images() {
	$stored = get_option( 'hrd_rooms', array() );

	return is_array( $stored ) ? array_map( 'absint', $stored ) : array();
}

/**
 * The shop archive filtered to one room's categories.
 *
 * A comma-separated `product_cat` is an OR natively — WordPress turns it into a tax_query
 * with an IN operator — so the whole room resolves with no custom query code.
 *
 * No rawurlencode here: add_query_arg() encodes the values itself, and pre-encoding
 * double-encodes the Hebrew slugs into %25d7%2598… which matches no term and silently
 * returns zero products. inc/woocommerce/filters.php carries the same warning.
 *
 * @param string $key One of hrd_rooms()'s keys.
 * @return string
 */
function hrd_room_link( $key ) {
	$rooms = hrd_rooms();
	$shop  = get_permalink( wc_get_page_id( 'shop' ) );

	if ( ! isset( $rooms[ $key ] ) ) {
		return $shop;
	}

	return add_query_arg(
		array(
			'product_cat' => implode( ',', $rooms[ $key ]['slugs'] ),
			// Carries the room's identity to the archive, which otherwise has no way to
			// know it is a room and titles itself "חנות".
			'hrd_room'    => $key,
		),
		$shop
	);
}

/**
 * The portal crops.
 *
 * The markup asks for 400x533 and the old code fed it `woocommerce_thumbnail` — a 300x300
 * hard crop. That is the blur the client saw: a 300px square stretched into a 3:4 slot.
 *
 * There are two sizes because one would produce no srcset at all. wp_calculate_image_srcset()
 * only offers candidates that share the reference size's aspect ratio, and every other size
 * WordPress generates here is either square or uncropped — none of them are 3:4. Registering
 * a second 3:4 crop is what gives the browser an actual choice. The 2x covers a ~290px slot
 * on a retina screen; sources smaller than 800w (the beds are all 630w) simply skip it and
 * ship the 400 alone, which is honest rather than upscaled.
 */
add_action(
	'after_setup_theme',
	function () {
		add_image_size( 'hrd_portal', 400, 533, true );
		add_image_size( 'hrd_portal_2x', 800, 1066, true );
	}
);

/**
 * The room the current request is for, or '' if this is not a room archive.
 *
 * @return string
 */
function hrd_current_room() {
	// phpcs:ignore WordPress.Security.NonceVerification -- reading a public link's own query var.
	$key = isset( $_GET['hrd_room'] ) ? sanitize_key( wp_unslash( $_GET['hrd_room'] ) ) : '';

	return isset( hrd_rooms()[ $key ] ) ? $key : '';
}

/**
 * Title the archive with the room the visitor clicked.
 *
 * Measured, because the guess was wrong: a comma-separated product_cat does NOT land on
 * the generic "חנות". WordPress resolves the queried object to the LAST term in the list,
 * so clicking "סלון" lands on a page headed "כורסאות" — the name of one of the five
 * categories the room happens to end with. That is worse than generic: it reads as a
 * broken link. The room knows its own name, so it says it.
 *
 * @param string $title Default archive title.
 * @return string
 */
add_filter(
	'woocommerce_page_title',
	function ( $title ) {
		$key = hrd_current_room();

		return $key ? hrd_rooms()[ $key ]['label'] : $title;
	}
);

/**
 * The browser tab carries the same lie as the heading, so it gets the same correction.
 *
 * @param array $parts Document title parts.
 * @return array
 */
add_filter(
	'document_title_parts',
	function ( $parts ) {
		$key = hrd_current_room();

		if ( $key ) {
			$parts['title'] = hrd_rooms()[ $key ]['label'];
		}

		return $parts;
	}
);
