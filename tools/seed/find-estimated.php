<?php
/**
 * Prints sample products carrying estimated dimensions, for visual verification.
 *
 *   docker compose exec wpcli wp eval-file /tools/seed/find-estimated.php
 */

defined( 'ABSPATH' ) || die( 'wp eval-file only' );

foreach ( array( 'high', 'medium', 'low' ) as $confidence ) {
	$ids = get_posts(
		array(
			'post_type'      => 'product',
			'posts_per_page' => 2,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'   => '_hrd_dims_confidence',
					'value' => $confidence,
				),
			),
		)
	);

	foreach ( $ids as $id ) {
		$dims = get_post_meta( $id, '_hrd_dims_estimated', true );
		WP_CLI::log( strtoupper( $confidence ) . ': ' . get_the_title( $id ) );
		WP_CLI::log( '  dims  : ' . wp_json_encode( $dims ) );
		WP_CLI::log( '  basis : ' . get_post_meta( $id, '_hrd_dims_basis', true ) );
		WP_CLI::log( '  url   : ' . get_permalink( $id ) );
		WP_CLI::log( '' );
	}
}
