<?php
/**
 * Installs the four By Light renders as attachments.
 *
 *   docker compose exec -T wpcli wp eval-file /tools/seed/install-bylight.php
 *
 * Modelled on tools/scene/install-hero.php, for the same reason it exists: the seeder caps
 * downloads at 1024w, which is right for 500 card thumbnails and wrong for the images that
 * ARE the section.
 *
 * WHY THIS SCRIPT RATHER THAN COMMITTING FOUR FILES:
 *
 * The renders are 2048x2048 PNGs at ~8.7MB each — 34MB that cannot ship, and that no one
 * could review in a diff. Converting them here means WordPress owns the derivatives: it
 * generates the intermediate sizes and a real srcset, so a phone gets a phone-sized WebP
 * instead of a 2048px PNG. That is exactly how the hero already works.
 *
 * PNG -> WebP is done through wp_get_image_editor(), not GD directly: it picks whatever
 * backend the container actually has (Imagick or GD) instead of assuming, and it is the
 * same code path WordPress uses for every other resize on the site.
 *
 * Idempotent: re-running replaces the four attachments rather than accumulating copies.
 */

defined( 'ABSPATH' ) || die( 'wp eval-file only' );

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';

/**
 * stop key => [source file, admin title, alt text]
 *
 * The alt text is real and describes the ROOM, because that is what the image is. It is
 * not "lamp" — the product beside it is named in real text, and repeating it here would
 * make a screen reader say it twice.
 */
$stops = array(
	'07' => array( 'byl-morning.png', 'לפי אור — 07:00', 'סלון של HR Design באור בוקר, המנורה כבויה' ),
	'12' => array( 'byl-noon.png', 'לפי אור — 12:00', 'אותו סלון באור צהריים מלא' ),
	'18' => array( 'byl-evening.png', 'לפי אור — 18:00', 'אותו סלון באור ערב חם, מנורת התלייה דולקת' ),
	'23' => array( 'byl-night.png', 'לפי אור — 23:00', 'אותו סלון בלילה, מנורת התלייה היא מקור האור היחיד' ),
);

$dir = '/seed/bylight';
$scene_id = hrd_byl_active_scene_id();

if ( ! $scene_id ) {
	WP_CLI::error( 'no hrd_byl_scene — run /tools/seed/by-light.php first' );
}

$installed = array();

foreach ( $stops as $stop => list( $file, $title, $alt ) ) {
	$path = "{$dir}/{$file}";
	if ( ! file_exists( $path ) ) {
		WP_CLI::error( "missing {$path}" );
	}

	// Replace rather than accumulate on re-run.
	foreach ( get_posts(
		array(
			'post_type'   => 'attachment',
			'meta_key'    => '_hrd_byl_stop',
			'meta_value'  => $stop,
			'numberposts' => -1,
			'fields'      => 'ids',
		)
	) as $old ) {
		wp_delete_attachment( $old, true );
	}

	/*
	 * PNG -> WebP, in ONE encode.
	 *
	 * The first version of this went PNG -> JPEG(82) -> WebP(82), letting a filter convert
	 * the derivatives. That is a double encode: the JPEG step throws detail away and WebP
	 * then re-compresses the damage, so it cost quality AND only bought ~7% — nowhere near
	 * the ~40% WebP is worth. Encoding WebP straight off the lossless source gets the real
	 * saving and a better image at the same time.
	 *
	 * Encoding the FULL image as WebP also means the derivatives inherit the format with no
	 * image_editor_output_format filter at all — which matters beyond tidiness: that filter
	 * is site-wide, and leaving it registered would silently re-encode every image anyone
	 * else uploads. The best way not to leak a global filter is not to need one.
	 *
	 * Quality 82: these are graded interiors with large smooth plaster fields, where the
	 * difference against 90 is invisible and the bytes are not.
	 */
	$editor = wp_get_image_editor( $path );
	if ( is_wp_error( $editor ) ) {
		WP_CLI::error( $editor->get_error_message() );
	}
	$editor->set_quality( 82 );

	$tmp = wp_tempnam( "byl-{$stop}.webp" );
	$saved = $editor->save( $tmp, 'image/webp' );
	if ( is_wp_error( $saved ) ) {
		WP_CLI::error( $saved->get_error_message() );
	}

	$upload = wp_upload_bits( "by-light-{$stop}.webp", null, file_get_contents( $saved['path'] ) );
	@unlink( $saved['path'] );
	@unlink( $tmp );

	if ( ! empty( $upload['error'] ) ) {
		WP_CLI::error( $upload['error'] );
	}

	// image/webp, and it must match the bytes on disk. The earlier version declared
	// image/jpeg while writing a .webp, which is a lie in the database: the browser was
	// fine (Apache types by extension) but every get_post_mime_type() caller was not.
	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/webp',
			'post_title'     => $title,
			'post_status'    => 'inherit',
		),
		$upload['file']
	);

	if ( is_wp_error( $attachment_id ) ) {
		WP_CLI::error( $attachment_id->get_error_message() );
	}

	/*
	 * This is what builds the srcset. Without it every viewport gets the 2048px file, four
	 * times over, and the section becomes the heaviest thing on the page by a wide margin.
	 *
	 * The derivatives come out WebP on their own because the source is: no filter, nothing
	 * global to leak. WebP is uncontroversial by this codebase's own standard — the theme
	 * already ships :has(), popover and @starting-style, all younger than WebP support.
	 */
	$meta = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
	wp_update_attachment_metadata( $attachment_id, $meta );

	update_post_meta( $attachment_id, '_hrd_byl_stop', $stop );
	update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
	update_post_meta( $scene_id, '_hrd_byl_img_' . $stop, $attachment_id );

	$installed[ $stop ] = array(
		'id'    => $attachment_id,
		'meta'  => $meta,
		'bytes' => filesize( $upload['file'] ),
		'png'   => filesize( $path ),
	);
}

WP_CLI::success( sprintf( 'scene %d — four stops installed', $scene_id ) );

$png_total = 0;
$jpg_total = 0;
foreach ( $installed as $stop => $i ) {
	$png_total += $i['png'];
	$jpg_total += $i['bytes'];
	WP_CLI::log(
		sprintf(
			'  %s  attachment %d  %dx%d  PNG %s -> WebP %s   sizes: %s',
			$stop,
			$i['id'],
			$i['meta']['width'] ?? 0,
			$i['meta']['height'] ?? 0,
			size_format( $i['png'] ),
			size_format( $i['bytes'] ),
			implode( ', ', array_map( fn( $s ) => $s['width'] . 'w', $i['meta']['sizes'] ?? array() ) )
		)
	);
}

WP_CLI::log( sprintf( "\n  full-size total: %s PNG -> %s WebP", size_format( $png_total ), size_format( $jpg_total ) ) );
WP_CLI::log( '  (what a browser actually transfers is smaller again — srcset picks a sized derivative)' );
