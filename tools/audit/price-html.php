<?php
/**
 * What does wc_price() actually emit?
 *
 *   docker compose exec wpcli wp eval-file /tools/audit/price-html.php
 *
 * The theme assumed WooCommerce wraps prices in <bdi>. The DOM says otherwise, and the
 * typography rules make bidi isolation on every number a hard requirement — without it
 * the shekel sign lands on the wrong side of the number the moment a price sits inline
 * with Hebrew text.
 */

defined( 'ABSPATH' ) || die( 'wp eval-file only' );

WP_CLI::log( 'wc_price(599):' );
WP_CLI::log( '  ' . wc_price( 599 ) );

WP_CLI::log( '' );
WP_CLI::log( 'currency position option: ' . get_option( 'woocommerce_currency_pos' ) );
WP_CLI::log( 'WooCommerce version: ' . WC()->version );

$ids = get_posts( array( 'post_type' => 'product', 'posts_per_page' => 1, 'fields' => 'ids' ) );
$product = wc_get_product( $ids[0] );
WP_CLI::log( '' );
WP_CLI::log( 'get_price_html() plain:' );
WP_CLI::log( '  ' . $product->get_price_html() );

$sale_ids = wc_get_product_ids_on_sale();
if ( $sale_ids ) {
	$sale = wc_get_product( $sale_ids[0] );
	WP_CLI::log( '' );
	WP_CLI::log( 'get_price_html() on sale:' );
	WP_CLI::log( '  ' . $sale->get_price_html() );
}

WP_CLI::log( '' );
WP_CLI::log( 'contains <bdi>: ' . ( str_contains( wc_price( 599 ), '<bdi>' ) ? 'YES' : 'NO' ) );
