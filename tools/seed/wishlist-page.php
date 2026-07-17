<?php
/**
 * Creates the מועדפים page, so /wishlist/ stops being a 404.
 *
 *   docker compose exec wpcli wp eval-file /tools/seed/wishlist-page.php
 *
 * The header's heart icon has linked to /wishlist/ on every page of the site since the
 * first commit, and nothing has ever answered it. The feature itself was never broken —
 * wishlist.js has been storing ids in localStorage all along — only the page to show them
 * on was missing, which is why this creates one rather than the header dropping the link.
 *
 * The slug is what matters: page-wishlist.php is selected by the template hierarchy from
 * `wishlist`, and it does the work. Note that hierarchy could not have picked that file up
 * before today, because page.php did not exist either.
 *
 * Deliberately kept separate from tools/seed/pages.php, which is another agent's file.
 * Idempotent: re-running updates the page rather than making a second one.
 */

defined( 'ABSPATH' ) || die( 'wp eval-file only' );

if ( ! function_exists( 'wc_get_page_id' ) ) {
	WP_CLI::error( 'WooCommerce missing — the wishlist page is a catalogue page.' );
}

$slug  = 'wishlist';
$title = 'מועדפים'; // The same word the header's heart already uses as its aria-label.

/*
 * Body is empty on purpose. page-wishlist.php renders the list and its own empty state;
 * the_content() is echoed above that purely so the client can add intro copy later
 * without needing a developer. Seeding filler here would be inventing the client's voice.
 */
$existing = get_page_by_path( $slug );

$data = array(
	'post_name'    => $slug,
	'post_title'   => $title,
	'post_content' => '',
	'post_type'    => 'page',
	'post_status'  => 'publish',
);

if ( $existing ) {
	$data['ID'] = $existing->ID;
	$id         = wp_update_post( $data, true );
	$verb       = 'updated';
} else {
	$id   = wp_insert_post( $data, true );
	$verb = 'created';
}

if ( is_wp_error( $id ) ) {
	WP_CLI::error( $slug . ': ' . $id->get_error_message() );
}

WP_CLI::log( sprintf( '%s  %s (#%d) — %s', $verb, $slug, $id, get_permalink( $id ) ) );

// The whole point is that the header's existing link resolves. Say so, or say why not.
$template = get_page_template_slug( $id );
if ( $template ) {
	WP_CLI::warning( sprintf( 'page has an explicit template set (%s) — page-wishlist.php will be ignored.', $template ) );
}

WP_CLI::success( 'wishlist page done. The header heart now resolves.' );
