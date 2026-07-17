<?php
/**
 * Authors the By Light scene.
 *
 *   docker compose exec -T wpcli wp eval-file /tools/seed/by-light.php
 *
 * Not a migration. The CPT changed name AND every meta key, and there is exactly one row
 * in existence — writing a migration to carry a single post across a schema that shares no
 * keys with the old one would be more code than the post itself, and code that runs once
 * and is never tested again.
 *
 * The old hrd_room_scene post is left where it is. Its post type is no longer registered,
 * so it is invisible to WordPress and to the front end; deleting the client's row on their
 * behalf is not this script's call to make.
 *
 * The numbers here are the defaults measured off the photographs — see by-light.css for
 * how they were derived. Idempotent: run it as often as you like.
 *
 * @package hrdesign
 */

$room_src = 5932; // ספת שזלונג בונו — a real living room, square, window at the far left.
$lamp_src = 6659; // מנורת תקרה ממתכת AM933 — shot on pure white with no cast shadow.

$map_file = '/seed/id-map.json';
if ( ! file_exists( $map_file ) ) {
	WP_CLI::error( 'seed/id-map.json is missing. Run the seeder first.' );
}

$map = json_decode( file_get_contents( $map_file ), true );

$room_product = isset( $map[ $room_src ] ) ? wc_get_product( (int) $map[ $room_src ] ) : null;
$lamp_product = isset( $map[ $lamp_src ] ) ? wc_get_product( (int) $map[ $lamp_src ] ) : null;

if ( ! $room_product || ! $lamp_product ) {
	WP_CLI::error( "Could not resolve $room_src / $lamp_src locally. Reseed." );
}

$room_image = (int) $room_product->get_image_id();
if ( ! $room_image ) {
	WP_CLI::error( 'The room product has no image.' );
}

$existing = get_posts(
	array(
		'post_type'      => 'hrd_byl_scene',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'post_status'    => 'any',
	)
);

$scene_id = $existing ? (int) $existing[0] : wp_insert_post(
	array(
		'post_type'   => 'hrd_byl_scene',
		'post_status' => 'publish',
		'post_title'  => 'הסלון',
	)
);

if ( is_wp_error( $scene_id ) ) {
	WP_CLI::error( $scene_id->get_error_message() );
}

/*
 * The scene's own thumbnail is the UNRETOUCHED source room, and nothing on the front end
 * reads it — hrd_byl_payload() takes the four rendered frames and never looks here.
 *
 * It stays for two reasons that are worth more than the row it costs: the admin post list
 * needs a face, and this is the provenance record. It is the one place in the database that
 * still holds the actual photograph the four renders were generated from, which is exactly
 * the question someone will ask when they wonder how real the section is.
 */
set_post_thumbnail( $scene_id, $room_image );

update_post_meta( $scene_id, '_hrd_byl_product', $lamp_product->get_id() );

/*
 * No anchor any more. The lamp used to be composited in at runtime, which needed three
 * measured numbers (--lx/--ly/--lw) and a colour key; it is inside the four renders now, so
 * there is no coordinate for anyone to get wrong. The four images are attached by
 * tools/seed/install-bylight.php — run that next.
 */

/*
 * Sweep the anchor meta from the design we abandoned.
 *
 * Scene 711 was authored against the composite version and still carried _hrd_byl_lx/ly/lw
 * long after nothing read them. Meta that no code reads is worse than clutter: the next
 * person to open that row sees three plausible-looking numbers and has no way to know they
 * are inert, so they trust them, or "fix" them, or file a bug when moving them does nothing.
 * Deleting keys is cheap; a stale number that looks authoritative is not.
 */
foreach ( array( '_hrd_byl_lx', '_hrd_byl_ly', '_hrd_byl_lw', '_hrd_byl_lamp_image' ) as $dead ) {
	if ( metadata_exists( 'post', $scene_id, $dead ) ) {
		delete_post_meta( $scene_id, $dead );
		WP_CLI::log( "  swept dead meta: {$dead}" );
	}
}

// The copy is left unset on purpose: hrd_byl_default_copy() supplies the placeholders, and
// storing them here would disguise a placeholder as an authored decision. The four lines
// are the client's to write.

WP_CLI::success(
	sprintf(
		'Scene %d — room %s (attachment %d) · lamp %s (%d)',
		$scene_id,
		$room_product->get_name(),
		$room_image,
		$lamp_product->get_name(),
		$lamp_product->get_id()
	)
);
