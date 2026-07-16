<?php
/**
 * Does wp_kses_post() strip <bdi>?
 *
 *   docker compose exec wpcli wp eval-file /tools/audit/kses-bdi.php
 */

defined( 'ABSPATH' ) || die( 'wp eval-file only' );

$price = wc_price( 599 );

WP_CLI::log( 'raw wc_price:' );
WP_CLI::log( '  ' . $price );
WP_CLI::log( '  has bdi: ' . ( str_contains( $price, '<bdi>' ) ? 'YES' : 'NO' ) );

$kses = wp_kses_post( $price );
WP_CLI::log( '' );
WP_CLI::log( 'after wp_kses_post:' );
WP_CLI::log( '  ' . $kses );
WP_CLI::log( '  has bdi: ' . ( str_contains( $kses, '<bdi>' ) ? 'YES' : 'NO' ) );

global $allowedposttags;
WP_CLI::log( '' );
WP_CLI::log( 'bdi in $allowedposttags : ' . ( isset( $allowedposttags['bdi'] ) ? 'YES' : 'NO' ) );
WP_CLI::log( 'bdo in $allowedposttags : ' . ( isset( $allowedposttags['bdo'] ) ? 'YES' : 'NO' ) );
