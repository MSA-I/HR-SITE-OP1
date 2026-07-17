<?php
/**
 * Why is the "new" badge over-firing on page 1?
 *
 *   docker compose exec wpcli wp eval-file /tools/seed/debug-new.php
 */

defined( 'ABSPATH' ) || die( 'wp eval-file only' );

delete_transient( 'hrd_new_threshold' );

$threshold = hrd_new_threshold();
WP_CLI::log( 'threshold from hrd_new_threshold(): ' . $threshold );

// Reproduce page 1 of the shop exactly as the archive queries it.
$q = new WP_Query(
	array(
		'post_type'      => 'product',
		'posts_per_page' => 24,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'fields'         => 'ids',
	)
);

WP_CLI::log( '' );
WP_CLI::log( sprintf( '%-46s %8s %6s %s', 'product', 'src_id', 'new?', 'badge' ) );

$new_count = 0;
foreach ( $q->posts as $id ) {
	$product = wc_get_product( $id );
	$src = (int) get_post_meta( $id, '_hrd_src_id', true );
	$badges = hrd_product_badges( $product );
	$is_new = $src >= $threshold;
	if ( $is_new ) {
		$new_count++;
	}

	WP_CLI::log(
		sprintf(
			'%-46s %8d %6s %s',
			mb_substr( $product->get_name(), 0, 44 ),
			$src,
			$is_new ? 'YES' : '-',
			$badges ? $badges[0]['label'] : '-'
		)
	);
}

WP_CLI::log( '' );
WP_CLI::log( sprintf( 'page 1: %d of %d flagged new', $new_count, count( $q->posts ) ) );
