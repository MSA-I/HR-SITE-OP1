<?php
/**
 * Restores the live store's popularity ranking.
 *
 *   docker compose exec wpcli wp eval-file /tools/seed/rank.php
 *
 * The seeder fetched with orderby=popularity, so the position of a product in
 * products.json IS its rank on the live store. Without this, total_sales is 0 on every
 * product, "הנמכרים ביותר" sorts by nothing, and the homepage bestsellers section the
 * brief asks for would be arbitrary.
 *
 * The absolute numbers are synthetic; the ORDER is real. Same reasoning as using
 * _hrd_src_id for recency instead of inventing post dates.
 */

defined( 'ABSPATH' ) || die( 'wp eval-file only' );

$products = json_decode( file_get_contents( '/seed/products.json' ), true );
$id_map = json_decode( file_get_contents( '/seed/id-map.json' ), true );

$total = count( $products );
$done = 0;

foreach ( $products as $rank => $p ) {
	if ( ! isset( $id_map[ $p['id'] ] ) ) {
		continue;
	}

	// Rank 0 is the most popular, so invert. Stored as a rank proxy, not a sales claim.
	update_post_meta( $id_map[ $p['id'] ], 'total_sales', $total - $rank );
	update_post_meta( $id_map[ $p['id'] ], '_hrd_popularity_rank', $rank + 1 );
	$done++;
}

WP_CLI::success( sprintf( '%d products ranked (1 = most popular on the live store).', $done ) );
