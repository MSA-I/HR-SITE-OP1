<?php
/**
 * Phase gate check. Prints what actually landed in the database.
 *
 *   docker compose exec wpcli wp eval-file /tools/seed/verify.php
 */

defined( 'ABSPATH' ) || die( 'wp eval-file only' );

$ids = get_posts(
	array(
		'post_type'      => 'product',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'post_status'    => 'any',
	)
);

$total = count( $ids );
$featured = 0;
$gallery = 0;
$dims = 0;
$dims_est = 0;
$sku = 0;
$sale = 0;
$oos = 0;
$src_variable = 0;
$colour = 0;

foreach ( $ids as $id ) {
	$product = wc_get_product( $id );
	if ( ! $product ) {
		continue;
	}

	if ( get_post_thumbnail_id( $id ) ) {
		$featured++;
	}
	if ( get_post_meta( $id, '_product_image_gallery', true ) ) {
		$gallery++;
	}
	if ( $product->get_length() || $product->get_width() || $product->get_height() ) {
		$dims++;
	}
	if ( get_post_meta( $id, '_hrd_dims_estimated', true ) ) {
		$dims_est++;
	}
	if ( $product->get_sku() ) {
		$sku++;
	}
	if ( $product->is_on_sale() ) {
		$sale++;
	}
	if ( ! $product->is_in_stock() ) {
		$oos++;
	}
	if ( 'variable' === get_post_meta( $id, '_hrd_src_type', true ) ) {
		$src_variable++;
	}
	if ( has_term( '', 'pa_color', $id ) ) {
		$colour++;
	}
}

$pct = function ( $n ) use ( $total ) {
	return $total ? str_pad( round( $n / $total * 100 ) . '%', 5, ' ', STR_PAD_LEFT ) : '  n/a';
};

$rows = array(
	'products'                  => $total,
	'with featured image'       => $featured,
	'with gallery (hover img)'  => $gallery,
	'with native dimensions'    => $dims,
	'with ESTIMATED dimensions' => $dims_est,
	'with sku'                  => $sku,
	'on sale'                   => $sale,
	'out of stock'              => $oos,
	'variable at source'        => $src_variable,
	'with pa_color term'        => $colour,
);

WP_CLI::log( '' );
foreach ( $rows as $label => $count ) {
	WP_CLI::log( sprintf( '  %-28s %4d  %s', $label, $count, $pct( $count ) ) );
}

$cats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
WP_CLI::log( sprintf( '  %-28s %4d', 'product_cat terms', is_wp_error( $cats ) ? 0 : count( $cats ) ) );

$attrs = wc_get_attribute_taxonomies();
WP_CLI::log( sprintf( '  %-28s %4d  %s', 'global attributes', count( $attrs ), implode( ', ', wp_list_pluck( $attrs, 'attribute_name' ) ) ) );
WP_CLI::log( '' );
