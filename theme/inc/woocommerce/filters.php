<?php
/**
 * Catalogue filters.
 *
 * Server-rendered first: the whole rail is a <form> of links and inputs that works with
 * JS disabled. No filter plugin — core layered nav is sufficient now that phase 2
 * promoted the attributes to global taxonomies. A plugin would only have papered over
 * that, which had to be fixed regardless.
 *
 * The governing rule: facets are computed from terms that actually have products, and a
 * facet with too few products does not render at all. This catalogue is thin and uneven
 * — a filter returning 3 of 250 is worse than no filter, and the UI improves by itself
 * as the client fills data in.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

/** A facet needs at least this many products behind it to be worth showing. */
const HRD_FACET_MIN_PRODUCTS = 10;

/** A single term needs at least this many products to appear as an option. */
const HRD_TERM_MIN_PRODUCTS = 2;

/**
 * Filterable attribute taxonomies, in display order.
 *
 * pa_size is deliberately absent: its live values are one-off dimension strings
 * ("176*125*83", "70.29"), so every term matches exactly one product. It is a
 * dimension field that was entered as an attribute, not a facet.
 *
 * @return array<string,string>
 */
function hrd_filter_taxonomies() {
	return array(
		'pa_color' => __( 'צבע', 'hrdesign' ),
	);
}

/**
 * Terms for a facet that clear the threshold.
 *
 * @param string $taxonomy Attribute taxonomy.
 * @return WP_Term[]
 */
function hrd_facet_terms( $taxonomy ) {
	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
			'orderby'    => 'count',
			'order'      => 'DESC',
		)
	);

	if ( is_wp_error( $terms ) || ! $terms ) {
		return array();
	}

	$terms = array_values( array_filter( $terms, fn( $t ) => $t->count >= HRD_TERM_MIN_PRODUCTS ) );

	// Not enough behind the whole facet: hide it rather than render a stub.
	$total = array_sum( wp_list_pluck( $terms, 'count' ) );
	return $total >= HRD_FACET_MIN_PRODUCTS ? $terms : array();
}

/**
 * Currently active filter values for a taxonomy.
 *
 * @param string $taxonomy Attribute taxonomy.
 * @return string[] Term slugs.
 */
function hrd_active_filter( $taxonomy ) {
	$key = 'filter_' . str_replace( 'pa_', '', $taxonomy );
	// phpcs:ignore WordPress.Security.NonceVerification -- read-only public filtering
	if ( empty( $_GET[ $key ] ) ) {
		return array();
	}
	// phpcs:ignore WordPress.Security.NonceVerification
	return array_map( 'sanitize_title', explode( ',', wp_unslash( $_GET[ $key ] ) ) );
}

/**
 * Build the URL that results from toggling one term on or off.
 *
 * @param string $taxonomy Attribute taxonomy.
 * @param string $slug     Term slug.
 * @return string
 */
function hrd_filter_toggle_url( $taxonomy, $slug ) {
	$key    = 'filter_' . str_replace( 'pa_', '', $taxonomy );
	$active = hrd_active_filter( $taxonomy );

	$next = in_array( $slug, $active, true )
		? array_diff( $active, array( $slug ) )
		: array_merge( $active, array( $slug ) );

	// Preserve every other query var (other facets, price, sort) — dropping them is
	// how filter rails end up resetting each other.
	$args = $_GET; // phpcs:ignore WordPress.Security.NonceVerification
	unset( $args['paged'] ); // a narrower result set invalidates the page number

	if ( $next ) {
		$args[ $key ] = implode( ',', $next );
		$args[ 'query_type_' . str_replace( 'pa_', '', $taxonomy ) ] = 'or';
	} else {
		unset( $args[ $key ], $args[ 'query_type_' . str_replace( 'pa_', '', $taxonomy ) ] );
	}

	$base = get_permalink( wc_get_page_id( 'shop' ) );
	if ( is_product_category() ) {
		$term_link = get_term_link( get_queried_object() );
		if ( ! is_wp_error( $term_link ) ) {
			$base = $term_link;
		}
	}

	// No rawurlencode here: add_query_arg() encodes values itself, and pre-encoding
	// double-encodes Hebrew slugs into %25d7%2598… which matches no term and silently
	// returns zero products.
	return $args ? add_query_arg( array_map( 'strval', $args ), $base ) : $base;
}

/**
 * Price bounds across the whole catalogue, for the range inputs.
 *
 * @return array{min:int,max:int}
 */
function hrd_price_bounds() {
	$cached = get_transient( 'hrd_price_bounds' );
	if ( false !== $cached ) {
		return $cached;
	}

	global $wpdb;
	$row = $wpdb->get_row(
		"SELECT MIN(min_price + 0) AS min, MAX(max_price + 0) AS max FROM {$wpdb->wc_product_meta_lookup}",
		ARRAY_A
	);

	$bounds = array(
		'min' => (int) floor( (float) ( $row['min'] ?? 0 ) ),
		'max' => (int) ceil( (float) ( $row['max'] ?? 0 ) ),
	);

	set_transient( 'hrd_price_bounds', $bounds, HOUR_IN_SECONDS );
	return $bounds;
}

/** Bust the caches when the catalogue changes. */
add_action( 'woocommerce_update_product', fn() => delete_transient( 'hrd_price_bounds' ) );
add_action( 'edited_product_cat', fn() => delete_transient( 'hrd_category_tree' ) );

/**
 * Is any filter currently applied?
 *
 * @return bool
 */
function hrd_has_active_filters() {
	// phpcs:ignore WordPress.Security.NonceVerification
	foreach ( array_keys( $_GET ) as $key ) {
		if ( str_starts_with( $key, 'filter_' ) || in_array( $key, array( 'min_price', 'max_price', 'stock' ), true ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Apply the in-stock filter. Price and attribute filtering are handled by core's
 * layered nav; this one core does not do on its own.
 */
add_action(
	'woocommerce_product_query',
	function ( $query ) {
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( is_admin() || empty( $_GET['stock'] ) ) {
			return;
		}

		$meta = (array) $query->get( 'meta_query' );
		$meta[] = array(
			'key'   => '_stock_status',
			'value' => 'instock',
		);
		$query->set( 'meta_query', $meta );
	}
);
