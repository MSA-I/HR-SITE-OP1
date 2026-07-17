<?php
/**
 * Phase 2 — data normalization. The gate the whole build waits on.
 *
 *   docker compose exec wpcli wp eval-file /tools/seed/normalize.php
 *
 * Two jobs:
 *
 * 1. Dimensions. Promote them out of prose into native WC fields. Measured coverage:
 *    30% already native, ~28% more recoverable from text, union ~51%. (The original
 *    plan assumed 90% from a single `מדריך מידות:` pattern — that pattern covers 3%.
 *    Real dimensions are written five different ways; all five are handled below.)
 *
 * 2. Attributes. Every attribute on the live store is LOCAL, and WooCommerce's layered
 *    nav can only filter GLOBAL (pa_*) taxonomies. Until this runs, the filters in the
 *    brief are unbuildable. The live data also splits colour across three attribute
 *    names — צבע / צבע: / צבע לבחירה — which all collapse into one pa_color here.
 *
 * Idempotent: safe to re-run while tuning the patterns.
 */

defined( 'ABSPATH' ) || die( 'wp eval-file only' );

define( 'SEED_DIR', '/seed' );

$products_json = json_decode( file_get_contents( SEED_DIR . '/products.json' ), true );
$id_map = json_decode( file_get_contents( SEED_DIR . '/id-map.json' ), true );

/* -------------------------------------------------------------------------
 * Dimension parsing.
 * ---------------------------------------------------------------------- */

const NUM = '(\d+(?:[.,]\d+)?)';
const SEP = '\s*[\/xX×*]\s*';

/**
 * Pull length/width/height out of free text.
 *
 * Ordered most-specific first. Two-number matches return height as null rather than
 * guessing — a fabricated third axis is worse than a missing one.
 *
 * @param string $text Stripped product copy.
 * @return array{0:?float,1:?float,2:?float}|null
 */
function hrd_parse_dims( $text ) {
	$patterns = array(
		'(?:מדריך\s*)?מיד(?:ות|ה)\s*:?\s*' . NUM . SEP . NUM . SEP . NUM,
		'אורך\s*:?\s*' . NUM . '[^\d]{0,12}רוחב\s*:?\s*' . NUM . '[^\d]{0,12}גובה\s*:?\s*' . NUM,
		'(?:^|\s)' . NUM . SEP . NUM . SEP . NUM . '\s*(?:ס["״]?מ|cm)',
	);

	foreach ( $patterns as $pattern ) {
		if ( preg_match( '/' . $pattern . '/u', $text, $m ) ) {
			return array( hrd_num( $m[1] ), hrd_num( $m[2] ), hrd_num( $m[3] ) );
		}
	}

	// Two-axis forms. Height stays null.
	if ( preg_match( '/קוטר\s*:?\s*' . NUM . '[^\d]{0,14}גובה\s*:?\s*' . NUM . '/u', $text, $m ) ) {
		return array( hrd_num( $m[1] ), hrd_num( $m[1] ), hrd_num( $m[2] ) ); // diameter = L = W
	}
	if ( preg_match( '/(?:מדריך\s*)?מיד(?:ות|ה)\s*:?\s*' . NUM . SEP . NUM . '/u', $text, $m ) ) {
		return array( hrd_num( $m[1] ), hrd_num( $m[2] ), null );
	}

	return null;
}

/**
 * Normalize a matched number, rejecting implausible furniture measurements.
 *
 * @param string $raw Matched digits.
 * @return float|null
 */
function hrd_num( $raw ) {
	$n = (float) str_replace( ',', '.', $raw );
	// Sanity band in cm. Catches SKU fragments and millimetre copy masquerading as cm.
	return ( $n >= 1 && $n <= 400 ) ? $n : null;
}

/**
 * Strip tags and normalize the entity soup the live editor produces.
 *
 * @param string $html Raw copy.
 * @return string
 */
function hrd_strip( $html ) {
	$text = wp_strip_all_tags( (string) $html );
	$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
	return trim( preg_replace( '/\s+/u', ' ', $text ) );
}

$dims_parsed = 0;
$dims_already = 0;

foreach ( $products_json as $p ) {
	if ( ! isset( $id_map[ $p['id'] ] ) ) {
		continue;
	}

	$product = wc_get_product( $id_map[ $p['id'] ] );
	if ( ! $product ) {
		continue;
	}

	if ( $product->get_length() || $product->get_width() || $product->get_height() ) {
		$dims_already++;
		update_post_meta( $product->get_id(), '_hrd_dims_source', 'native' );
		continue;
	}

	$dims = hrd_parse_dims( hrd_strip( $p['short_description'] ) . ' ' . hrd_strip( $p['description'] ) );
	if ( ! $dims ) {
		continue;
	}

	list( $l, $w, $h ) = $dims;
	if ( $l ) {
		$product->set_length( (string) $l );
	}
	if ( $w ) {
		$product->set_width( (string) $w );
	}
	if ( $h ) {
		$product->set_height( (string) $h );
	}

	if ( $l || $w || $h ) {
		$product->save();
		update_post_meta( $product->get_id(), '_hrd_dims_source', 'parsed' );
		$dims_parsed++;
	}
}

WP_CLI::log( sprintf( 'Dimensions: %d already native, %d parsed from prose, %d total', $dims_already, $dims_parsed, $dims_already + $dims_parsed ) );

/* -------------------------------------------------------------------------
 * Attributes: local -> global.
 * ---------------------------------------------------------------------- */

/**
 * Live attribute names collapse into our global taxonomies. The live store spells
 * colour three ways and size four; the trailing-colon variants are data entry drift,
 * not distinct attributes.
 */
$attr_map = array(
	'צבע'         => 'color',
	'צבע:'        => 'color',
	'צבע לבחירה'  => 'color',
	'גודל'        => 'size',
	'גודל:'       => 'size',
	'מידה'        => 'size',
	'מידה:'       => 'size',
);

$attr_labels = array(
	'color' => 'צבע',
	'size'  => 'גודל',
);

// Hebrew colour names -> swatch hex. Only names that actually appear get a swatch;
// anything unmapped renders as a text chip rather than a wrong-coloured dot.
$colour_hex = array(
	'שחור'      => '#191512',
	'לבן'       => '#F4F1EA',
	'אפור'      => '#8C8985',
	'בז'        => '#D9C9AE',
	"בז'"       => '#D9C9AE',
	'בז׳'       => '#D9C9AE',
	'שמנת'      => '#EFE7D6',
	'חום'       => '#6B4A31',
	'מוקה'      => '#5A4234',
	'אגוז'      => '#6E4B2A',
	'אלון'      => '#C4A57B',
	'טבעי'      => '#C9AE85',
	'זהב'       => '#B8934E',
	'כסף'       => '#AFB2B4',
	'נחושת'     => '#A56A3E',
	'ירוק'      => '#5E6B3E',
	'כחול'      => '#1E3A54',
	'חלודה'     => '#A9482A',
	'ורוד'      => '#D9A9A0',
	'צהוב'      => '#D3A83B',
	'אדום'      => '#8C2F2F',
	'שנהב'      => '#EDE4D0',
	'טרקוטה'    => '#B25B38',
);

foreach ( $attr_labels as $slug => $label ) {
	$taxonomy = 'pa_' . $slug;

	if ( ! taxonomy_exists( $taxonomy ) ) {
		$id = wc_create_attribute(
			array(
				'name'         => $label,
				'slug'         => $slug,
				'type'         => 'select',
				'order_by'     => 'menu_order',
				'has_archives' => false,
			)
		);
		if ( is_wp_error( $id ) ) {
			WP_CLI::warning( "attribute $slug: " . $id->get_error_message() );
			continue;
		}
		// The taxonomy is registered on init, which already fired — register it now so
		// terms can be inserted in this same run.
		register_taxonomy( $taxonomy, 'product', array( 'hierarchical' => false, 'show_in_rest' => true ) );
	}
}

$assigned = array( 'color' => 0, 'size' => 0 );
$terms_made = array();

foreach ( $products_json as $p ) {
	if ( ! isset( $id_map[ $p['id'] ] ) ) {
		continue;
	}
	$product_id = $id_map[ $p['id'] ];

	$wc_attributes = array();

	foreach ( $p['attributes'] ?? array() as $attr ) {
		$name = trim( $attr['name'] ?? '' );
		if ( ! isset( $attr_map[ $name ] ) ) {
			continue; // בחירת מזלף / לבחירה — one-off local options, not filterable facets
		}

		$slug = $attr_map[ $name ];
		$taxonomy = 'pa_' . $slug;
		$term_names = array();

		foreach ( $attr['terms'] ?? array() as $term ) {
			$term_name = trim( $term['name'] ?? '' );
			if ( '' === $term_name ) {
				continue;
			}

			$existing = get_term_by( 'name', $term_name, $taxonomy );
			if ( ! $existing ) {
				$inserted = wp_insert_term( $term_name, $taxonomy );
				if ( is_wp_error( $inserted ) ) {
					continue;
				}
				$term_id = $inserted['term_id'];
				$terms_made[ $taxonomy ][] = $term_name;

				if ( 'color' === $slug ) {
					foreach ( $colour_hex as $needle => $hex ) {
						if ( false !== mb_strpos( $term_name, $needle ) ) {
							update_term_meta( $term_id, 'hrd_hex', $hex );
							break;
						}
					}
				}
			}
			$term_names[] = $term_name;
		}

		if ( ! $term_names ) {
			continue;
		}

		wp_set_object_terms( $product_id, $term_names, $taxonomy, true );

		$wc_attribute = new WC_Product_Attribute();
		$wc_attribute->set_id( wc_attribute_taxonomy_id_by_name( $taxonomy ) );
		$wc_attribute->set_name( $taxonomy );
		$wc_attribute->set_options( wp_list_pluck( get_terms( array( 'taxonomy' => $taxonomy, 'name' => $term_names, 'hide_empty' => false ) ), 'term_id' ) );
		$wc_attribute->set_visible( true );
		$wc_attribute->set_variation( false );
		$wc_attributes[] = $wc_attribute;

		$assigned[ $slug ]++;
	}

	if ( $wc_attributes ) {
		$product = wc_get_product( $product_id );
		$product->set_attributes( $wc_attributes );
		$product->save();
	}
}

foreach ( $terms_made as $taxonomy => $names ) {
	WP_CLI::log( sprintf( '%s: %d terms — %s', $taxonomy, count( $names ), implode( ', ', array_slice( $names, 0, 12 ) ) ) );
}

WP_CLI::success( sprintf( 'Attributes assigned: %d products with colour, %d with size', $assigned['color'], $assigned['size'] ) );
