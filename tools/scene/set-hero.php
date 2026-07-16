<?php
/**
 * Sets the hero image to one of HR Design's own styled room photographs.
 *
 *   docker compose exec wpcli wp eval-file /tools/scene/set-hero.php
 *
 * The fallback (the room scene's featured image) grabbed the composed illustration,
 * which is a diagram, not a hero. src 5604 is a real photograph of a styled living room
 * — their own — and it is the strongest image in the catalogue.
 */

defined( 'ABSPATH' ) || die( 'wp eval-file only' );

const HRD_HERO_SRC = 5604;

$ids = get_posts(
	array(
		'post_type'      => 'product',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_query'     => array( array( 'key' => '_hrd_src_id', 'value' => HRD_HERO_SRC ) ),
	)
);

if ( ! $ids ) {
	WP_CLI::error( 'hero source product not found' );
}

$attachment_id = get_post_thumbnail_id( $ids[0] );
if ( ! $attachment_id ) {
	WP_CLI::error( 'hero source has no image' );
}

update_option( 'hrd_hero_image', $attachment_id );
update_option( 'hrd_hero_title', 'הבית מתחיל בפריט אחד' );

WP_CLI::success( sprintf( 'hero image set to attachment %d (%s)', $attachment_id, get_the_title( $ids[0] ) ) );
