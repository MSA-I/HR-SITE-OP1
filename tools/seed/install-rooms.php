<?php
/**
 * Installs the five room portal photographs and writes the `hrd_rooms` option.
 *
 *   node tools/seed/fetch-rooms.mjs
 *   docker compose exec wpcli wp eval-file /tools/seed/install-rooms.php
 *
 * Same shape as tools/scene/install-hero.php, and for the same reason: WordPress has to
 * generate the intermediate sizes itself, otherwise the portal ships one full-size file
 * with no srcset and the browser downloads 2560px to paint 300.
 *
 * The option, not term meta, is the store: a room is five categories, so there is no term
 * to hang a thumbnail on. And the theme cannot hardcode these IDs — they differ on every
 * import.
 *
 * Idempotent: re-running replaces rather than accumulates.
 */

defined( 'ABSPATH' ) || die( 'wp eval-file only' );

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';

/** Alt text is content, not decoration: these describe the photograph, in Hebrew. */
const HRD_ROOM_ALT = array(
	'living'  => 'סלון מעוצב עם ספה בהירה ושולחן עץ',
	'dining'  => 'פינת אוכל עם שולחן עץ וכיסאות',
	'entry'   => 'פינת כניסה עם קונסולה מעץ ותמונה ממוסגרת',
	'bedroom' => 'חדר שינה עם מיטת עץ וראש מיטה מקש',
	'bath'    => 'חדר רחצה עם מתלה מגבות מפליז',
);

$dir = '/seed/rooms';
if ( ! is_dir( $dir ) ) {
	WP_CLI::error( 'run node tools/seed/fetch-rooms.mjs first' );
}

$installed = array();

foreach ( array_keys( HRD_ROOM_ALT ) as $key ) {
	// No GLOB_BRACE: this container's PHP is built against musl, which does not ship it.
	$matches = array_values(
		array_filter(
			glob( "{$dir}/{$key}-*" ) ?: array(),
			fn( $f ) => in_array( strtolower( pathinfo( $f, PATHINFO_EXTENSION ) ), array( 'jpg', 'jpeg', 'png', 'webp' ), true )
		)
	);
	if ( ! $matches ) {
		WP_CLI::warning( "{$key}: no file in {$dir} — skipped" );
		continue;
	}

	$file = $matches[0];
	$ext  = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );

	// Replace rather than accumulate on re-run.
	foreach ( get_posts(
		array(
			'post_type'   => 'attachment',
			'meta_key'    => '_hrd_room',
			'meta_value'  => $key,
			'numberposts' => -1,
			'fields'      => 'ids',
		)
	) as $old ) {
		wp_delete_attachment( $old, true );
	}

	$upload = wp_upload_bits( "room-{$key}.{$ext}", null, file_get_contents( $file ) );
	if ( ! empty( $upload['error'] ) ) {
		WP_CLI::error( "{$key}: {$upload['error']}" );
	}

	$attachment_id = wp_insert_attachment(
		array(
			// Let WordPress name the type from the file rather than asserting image/jpeg:
			// the catalogue mixes JPEG and WebP and the entry portal is WebP.
			'post_mime_type' => wp_check_filetype( $upload['file'] )['type'],
			'post_title'     => "HR Design — portal {$key}",
			'post_status'    => 'inherit',
		),
		$upload['file']
	);

	if ( is_wp_error( $attachment_id ) ) {
		WP_CLI::error( "{$key}: " . $attachment_id->get_error_message() );
	}

	// This is what builds the srcset.
	$meta = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
	wp_update_attachment_metadata( $attachment_id, $meta );

	update_post_meta( $attachment_id, '_hrd_room', $key );
	update_post_meta( $attachment_id, '_wp_attachment_image_alt', HRD_ROOM_ALT[ $key ] );

	$installed[ $key ] = $attachment_id;

	WP_CLI::log(
		sprintf(
			'%-8s attachment %d — %dx%d, %d sizes: %s',
			$key,
			$attachment_id,
			$meta['width'] ?? 0,
			$meta['height'] ?? 0,
			count( $meta['sizes'] ?? array() ),
			implode( ', ', array_map( fn( $s ) => $s['width'] . 'w', $meta['sizes'] ?? array() ) )
		)
	);
}

if ( count( $installed ) < 2 ) {
	WP_CLI::error( 'fewer than 2 portals installed — the section will not render' );
}

update_option( 'hrd_rooms', $installed );

WP_CLI::success( sprintf( 'hrd_rooms = %s', wp_json_encode( $installed ) ) );
