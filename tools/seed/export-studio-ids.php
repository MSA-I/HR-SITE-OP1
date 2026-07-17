<?php
/**
 * Exports studio-shot product ids and reports what a room could be composed from.
 *
 *   docker compose exec wpcli wp eval-file /tools/seed/export-studio-ids.php
 *
 * Only studio cut-outs can be composed: a room photo pasted into a room is a room inside
 * a room, which is exactly what the first attempt produced.
 */

defined( 'ABSPATH' ) || die( 'wp eval-file only' );

$ids = get_posts(
	array(
		'post_type'      => 'product',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => array( array( 'key' => '_hrd_photo_type', 'value' => 'studio' ) ),
	)
);

$src_ids = array();
$catalogue = array();

foreach ( $ids as $id ) {
	$src = (int) get_post_meta( $id, '_hrd_src_id', true );
	if ( ! $src ) {
		continue;
	}
	$src_ids[] = $src;
	$catalogue[] = array(
		'src'   => $src,
		'name'  => html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ),
		// A cut-out whose product is itself near-white vanishes under multiply. The one
		// sofa in the studio set is a pale sofa on white — an empty plate.
		'white' => (float) get_post_meta( $id, '_hrd_photo_white_share', true ),
	);
}

file_put_contents( '/seed/studio-ids.json', wp_json_encode( $src_ids ) );

// Anything above this is more backdrop than product: it will multiply away to nothing.
$usable = array_values( array_filter( $catalogue, fn( $p ) => $p['white'] < 0.82 ) );
file_put_contents( '/seed/studio-catalogue.json', wp_json_encode( $usable, JSON_UNESCAPED_UNICODE ) );

WP_CLI::success( sprintf( '%d studio ids, %d usable as cut-outs (rest are too white to survive multiply).', count( $src_ids ), count( $usable ) ) );

WP_CLI::log( '' );
WP_CLI::log( 'usable cut-outs by type:' );

$slots = array(
	'תאורה תלויה' => '/שנדליר|מנורה תלויה|אהיל|תאורה תלויה|מנורת תקרה/u',
	'מראה/שעון'   => '/מראה|שעון/u',
	'שרפרף/סטול'  => '/סטול|שרפרף|ספסל/u',
	'שטיח'         => '/שטיח/u',
	'מדף/קונסולה' => '/מדף|קונסולה|מזנון|שידה/u',
	'עציץ/אגרטל'  => '/עציץ|אגרטל|כלי/u',
);

foreach ( $slots as $label => $pattern ) {
	$found = array_values( array_filter( $usable, fn( $p ) => preg_match( $pattern, $p['name'] ) ) );
	WP_CLI::log( sprintf( '  %-14s %2d', $label, count( $found ) ) );
	foreach ( array_slice( $found, 0, 3 ) as $item ) {
		WP_CLI::log( sprintf( '      %d  %-46s white %.2f', $item['src'], mb_substr( $item['name'], 0, 44 ), $item['white'] ) );
	}
}
