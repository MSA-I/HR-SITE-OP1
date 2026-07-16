<?php
/**
 * Installs the full-resolution hero photograph.
 *
 *   docker compose exec wpcli wp eval-file /tools/scene/install-hero.php
 *
 * Imported separately from the seed because the seeder caps at 1024w — the right rule
 * for 500 card images against someone else's live store, the wrong one for the single
 * image that fills the viewport and is the LCP element.
 *
 * WordPress generates the intermediate sizes, so the hero ships a real srcset instead of
 * upscaling one file.
 */

defined( 'ABSPATH' ) || die( 'wp eval-file only' );

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';

const HRD_HERO_SRC = 6623;
const HRD_HERO_FILE = '/seed/hero/hero-6623.jpg';

if ( ! file_exists( HRD_HERO_FILE ) ) {
	WP_CLI::error( 'run tools/scene/fetch-hero.mjs first' );
}

// Replace rather than accumulate on re-run.
foreach ( get_posts(
	array(
		'post_type'   => 'attachment',
		'meta_key'    => '_hrd_hero_src',
		'meta_value'  => HRD_HERO_SRC,
		'numberposts' => -1,
		'fields'      => 'ids',
	)
) as $old ) {
	wp_delete_attachment( $old, true );
}

$upload = wp_upload_bits( 'hero-hr-design.jpg', null, file_get_contents( HRD_HERO_FILE ) );
if ( ! empty( $upload['error'] ) ) {
	WP_CLI::error( $upload['error'] );
}

$attachment_id = wp_insert_attachment(
	array(
		'post_mime_type' => 'image/jpeg',
		'post_title'     => 'HR Design — hero',
		'post_status'    => 'inherit',
	),
	$upload['file']
);

if ( is_wp_error( $attachment_id ) ) {
	WP_CLI::error( $attachment_id->get_error_message() );
}

// This is what builds the srcset — without it the browser gets one 2560px file for every
// viewport, including phones.
$meta = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
wp_update_attachment_metadata( $attachment_id, $meta );

update_post_meta( $attachment_id, '_hrd_hero_src', HRD_HERO_SRC );
update_post_meta( $attachment_id, '_wp_attachment_image_alt', 'סלון מעוצב של HR Design' );

update_option( 'hrd_hero_image', $attachment_id );

WP_CLI::success(
	sprintf(
		'hero attachment %d — %dx%d, %d generated sizes: %s',
		$attachment_id,
		$meta['width'] ?? 0,
		$meta['height'] ?? 0,
		count( $meta['sizes'] ?? array() ),
		implode( ', ', array_map( fn( $s ) => $s['width'] . 'w', $meta['sizes'] ?? array() ) )
	)
);
