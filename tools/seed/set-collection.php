<?php
/**
 * Sets the featured collection for the horizontal section.
 *
 *   docker compose exec wpcli wp eval-file /tools/seed/set-collection.php
 *
 * Without this option the section falls back to the first node of hrd_category_tree(),
 * which is now ריהוט — a 12-child parent covering half the catalogue. That is a
 * department, not a collection. תאורה is the editorial pick: 9 lamps shot at 2560w,
 * the best-photographed group in the catalogue.
 */

defined( 'ABSPATH' ) || die( 'wp eval-file only' );

const HRD_COLLECTION_SLUG   = 'תאורה';
const HRD_COLLECTION_ACCENT = 'natural-forms';

$term = get_term_by( 'slug', HRD_COLLECTION_SLUG, 'product_cat' );

if ( ! $term || is_wp_error( $term ) ) {
	WP_CLI::error( sprintf( 'collection term "%s" not found', HRD_COLLECTION_SLUG ) );
}

update_option( 'hrd_featured_collection', $term->term_id );
update_term_meta( $term->term_id, 'hrd_accent', HRD_COLLECTION_ACCENT );

WP_CLI::success( sprintf( 'featured collection set to %s (term %d, accent %s)', $term->name, $term->term_id, HRD_COLLECTION_ACCENT ) );
