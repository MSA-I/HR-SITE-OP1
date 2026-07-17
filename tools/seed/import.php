<?php
/**
 * Imports the seed catalogue into the local WordPress.
 *
 *   docker compose exec wpcli wp eval-file /seed/../tools/seed/import.php
 *
 * Uses WC_Product objects directly rather than 250 REST round-trips through the whole
 * WooCommerce stack. Images are sideloaded from the local /seed mount — NOT via
 * media_sideload_image(), which would re-download from the live site and defeat the
 * entire point of having cached them.
 *
 * Idempotent: products are matched on _hrd_src_id, so a re-run updates in place.
 */

defined( 'ABSPATH' ) || die( 'wp eval-file only' );

if ( ! class_exists( 'WooCommerce' ) ) {
	WP_CLI::error( 'WooCommerce is not active.' );
}

define( 'SEED_DIR', '/seed' );

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

$products_json = json_decode( file_get_contents( SEED_DIR . '/products.json' ), true );
$categories_json = json_decode( file_get_contents( SEED_DIR . '/categories.json' ), true );

// $GLOBALS, not a plain top-level variable: `wp eval-file` executes this inside a
// function scope, so top-level assignments are locals and `global $x` in a helper
// would silently see nothing. That exact mistake imported 250 products with 0 images.
$GLOBALS['hrd_image_map'] = json_decode( file_get_contents( SEED_DIR . '/image-map.json' ), true );
$GLOBALS['hrd_attachment_cache'] = array();

WP_CLI::log( sprintf( 'Seed: %d products, %d categories', count( $products_json ), count( $categories_json ) ) );

/* -------------------------------------------------------------------------
 * 1. Categories — parents first, so children can attach.
 * ---------------------------------------------------------------------- */

$cat_map = array(); // live term id => local term id

// Sort so that a parent is always created before its children.
$sorted = $categories_json;
usort(
	$sorted,
	function ( $a, $b ) {
		return ( $a['parent'] ?? 0 ) <=> ( $b['parent'] ?? 0 );
	}
);

foreach ( $sorted as $cat ) {
	$existing = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'meta_key'   => '_hrd_src_id',
			'meta_value' => $cat['id'],
			'hide_empty' => false,
		)
	);

	if ( ! empty( $existing ) && ! is_wp_error( $existing ) ) {
		$cat_map[ $cat['id'] ] = $existing[0]->term_id;
		continue;
	}

	$parent = isset( $cat_map[ $cat['parent'] ] ) ? $cat_map[ $cat['parent'] ] : 0;

	$term = wp_insert_term(
		$cat['name'],
		'product_cat',
		array(
			'slug'        => $cat['slug'],
			'parent'      => $parent,
			'description' => $cat['description'] ?? '',
		)
	);

	if ( is_wp_error( $term ) ) {
		// Slug collision: reuse the existing term rather than skipping the branch.
		$existing_term = get_term_by( 'slug', $cat['slug'], 'product_cat' );
		if ( ! $existing_term ) {
			WP_CLI::warning( "category '{$cat['name']}': " . $term->get_error_message() );
			continue;
		}
		$term = array( 'term_id' => $existing_term->term_id );
	}

	update_term_meta( $term['term_id'], '_hrd_src_id', $cat['id'] );
	$cat_map[ $cat['id'] ] = $term['term_id'];
}

WP_CLI::log( sprintf( 'Categories: %d mapped', count( $cat_map ) ) );

/* -------------------------------------------------------------------------
 * 2. Images — sideload from disk, keyed by "productId:imageId".
 * ---------------------------------------------------------------------- */

/**
 * Sideload one seed image into the media library, or return the existing attachment.
 *
 * @param int    $product_src_id Live product id.
 * @param array  $image          Store API image object.
 * @param string $title          Alt text / title.
 * @return int|null Attachment id.
 */
function hrd_seed_image( $product_src_id, $image, $title ) {
	$image_map = $GLOBALS['hrd_image_map'];

	$key = $product_src_id . ':' . $image['id'];
	if ( isset( $GLOBALS['hrd_attachment_cache'][ $key ] ) ) {
		return $GLOBALS['hrd_attachment_cache'][ $key ];
	}

	if ( ! isset( $image_map[ $key ] ) ) {
		return null;
	}

	// Already imported in a previous run?
	$found = get_posts(
		array(
			'post_type'   => 'attachment',
			'meta_key'    => '_hrd_src_img',
			'meta_value'  => $key,
			'numberposts' => 1,
			'fields'      => 'ids',
		)
	);
	if ( ! empty( $found ) ) {
		$GLOBALS['hrd_attachment_cache'][ $key ] = $found[0];
		return $found[0];
	}

	$src = SEED_DIR . '/images/' . $image_map[ $key ];
	if ( ! file_exists( $src ) ) {
		return null;
	}

	$upload = wp_upload_bits( basename( $src ), null, file_get_contents( $src ) );
	if ( ! empty( $upload['error'] ) ) {
		return null;
	}

	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => $upload['type'],
			'post_title'     => $title,
			'post_status'    => 'inherit',
		),
		$upload['file']
	);

	if ( is_wp_error( $attachment_id ) ) {
		return null;
	}

	wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
	update_post_meta( $attachment_id, '_hrd_src_img', $key );
	update_post_meta( $attachment_id, '_wp_attachment_image_alt', $title );

	$GLOBALS['hrd_attachment_cache'][ $key ] = $attachment_id;
	return $attachment_id;
}

/* -------------------------------------------------------------------------
 * 3. Products.
 * ---------------------------------------------------------------------- */

$id_map = array(); // live product id => local product id
$created = 0;
$updated = 0;
$img_count = 0;

foreach ( $products_json as $i => $p ) {
	$existing = get_posts(
		array(
			'post_type'   => 'product',
			'meta_key'    => '_hrd_src_id',
			'meta_value'  => $p['id'],
			'numberposts' => 1,
			'fields'      => 'ids',
			'post_status' => 'any',
		)
	);

	// Variable products are imported as simple: the demo needs the catalogue to look
	// and filter correctly, and variation-level stock/pricing adds nothing to that.
	// The card still reads type from _hrd_src_type and renders "בחר אפשרויות".
	$product = ! empty( $existing ) ? new WC_Product_Simple( $existing[0] ) : new WC_Product_Simple();

	$product->set_name( $p['name'] );
	$product->set_status( 'publish' );
	$product->set_description( $p['description'] ?? '' );
	$product->set_short_description( $p['short_description'] ?? '' );
	$product->set_catalog_visibility( 'visible' );

	// Store API gives prices as minor units in a string; prices.price is the current one.
	$minor = (int) ( $p['prices']['currency_minor_unit'] ?? 2 );
	$divisor = pow( 10, $minor );
	$regular = isset( $p['prices']['regular_price'] ) ? (float) $p['prices']['regular_price'] / $divisor : 0;
	$sale = isset( $p['prices']['sale_price'] ) ? (float) $p['prices']['sale_price'] / $divisor : 0;

	$product->set_regular_price( (string) $regular );
	if ( $sale && $sale < $regular ) {
		$product->set_sale_price( (string) $sale );
	} else {
		$product->set_sale_price( '' );
	}

	if ( ! empty( $p['sku'] ) ) {
		// SKUs must be unique; the live store has duplicates in a few places.
		$sku = $p['sku'];
		if ( ! wc_get_product_id_by_sku( $sku ) || ( ! empty( $existing ) && wc_get_product_id_by_sku( $sku ) === $existing[0] ) ) {
			$product->set_sku( $sku );
		}
	}

	$product->set_stock_status( ! empty( $p['is_in_stock'] ) ? 'instock' : 'outofstock' );

	// Native dimensions, where the live store actually has them.
	if ( ! empty( $p['dimensions']['length'] ) ) {
		$product->set_length( $p['dimensions']['length'] );
	}
	if ( ! empty( $p['dimensions']['width'] ) ) {
		$product->set_width( $p['dimensions']['width'] );
	}
	if ( ! empty( $p['dimensions']['height'] ) ) {
		$product->set_height( $p['dimensions']['height'] );
	}

	$cat_ids = array();
	foreach ( $p['categories'] ?? array() as $c ) {
		if ( isset( $cat_map[ $c['id'] ] ) ) {
			$cat_ids[] = $cat_map[ $c['id'] ];
		}
	}
	if ( $cat_ids ) {
		$product->set_category_ids( $cat_ids );
	}

	$product_id = $product->save();

	update_post_meta( $product_id, '_hrd_src_id', $p['id'] );
	update_post_meta( $product_id, '_hrd_src_type', $p['type'] ?? 'simple' );
	update_post_meta( $product_id, '_hrd_src_permalink', $p['permalink'] ?? '' );

	// Images: first is featured, rest are the gallery (the card only needs [0] for hover).
	$images = $p['images'] ?? array();
	$gallery = array();
	foreach ( $images as $idx => $image ) {
		$attachment_id = hrd_seed_image( $p['id'], $image, $p['name'] );
		if ( ! $attachment_id ) {
			continue;
		}
		$img_count++;
		if ( 0 === $idx ) {
			set_post_thumbnail( $product_id, $attachment_id );
		} else {
			$gallery[] = $attachment_id;
		}
	}
	if ( $gallery ) {
		update_post_meta( $product_id, '_product_image_gallery', implode( ',', $gallery ) );
	}

	$id_map[ $p['id'] ] = $product_id;
	empty( $existing ) ? $created++ : $updated++;

	if ( 0 === ( $i + 1 ) % 25 ) {
		WP_CLI::log( sprintf( '  %d/%d products', $i + 1, count( $products_json ) ) );
	}
}

file_put_contents( SEED_DIR . '/id-map.json', wp_json_encode( $id_map ) );

WP_CLI::success(
	sprintf(
		'%d created, %d updated, %d images attached. Live->local id map written to seed/id-map.json',
		$created,
		$updated,
		$img_count
	)
);
