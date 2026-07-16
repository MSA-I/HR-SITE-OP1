<?php
/**
 * Shop the Space — the room scene post type.
 *
 * One post meta key holding a JSON array. One get_post_meta, no meta-table joins, and
 * no ACF: the free tier has no repeater, so hotspots would need ACF Pro — a paid
 * dependency for one feature — and even then authoring means typing x/y percentages by
 * hand. A click-to-place picker is ~150 lines of vanilla JS and is strictly better.
 * This is the one place in the project where building beats buying.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	function () {
		register_post_type(
			'hrd_room_scene',
			array(
				'labels'       => array(
					'name'          => __( 'סצנות חלל', 'hrdesign' ),
					'singular_name' => __( 'סצנת חלל', 'hrdesign' ),
					'add_new_item'  => __( 'סצנה חדשה', 'hrdesign' ),
					'edit_item'     => __( 'עריכת סצנה', 'hrdesign' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_rest' => false,
				'menu_icon'    => 'dashicons-location-alt',
				'supports'     => array( 'title', 'thumbnail' ),
			)
		);
	}
);

/**
 * Read a scene's hotspots.
 *
 * @param int $scene_id Scene post id.
 * @return array<array{id:string,x_d:float,y_d:float,x_m:float,y_m:float,layer:string,product_id:int}>
 */
function hrd_scene_hotspots( $scene_id ) {
	$raw = get_post_meta( $scene_id, '_hrd_hotspots', true );
	$data = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
	return is_array( $data ) ? $data : array();
}

/**
 * The scene to feature on the homepage.
 *
 * @return int|null
 */
function hrd_active_scene_id() {
	$scenes = get_posts(
		array(
			'post_type'      => 'hrd_room_scene',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'post_status'    => 'publish',
		)
	);

	return $scenes ? (int) $scenes[0] : null;
}

/**
 * Everything the front end needs for one scene, resolved server-side.
 *
 * The products are hydrated here, never fetched on click: the mini card must open in a
 * single frame. It also means the products are crawlable links inside the homepage, and
 * with JS off the section degrades to a photo plus a plain product list.
 *
 * @param int $scene_id Scene post id.
 * @return array|null
 */
function hrd_scene_payload( $scene_id ) {
	$hotspots = hrd_scene_hotspots( $scene_id );
	if ( ! $hotspots ) {
		return null;
	}

	$out = array();

	foreach ( $hotspots as $spot ) {
		$product = wc_get_product( $spot['product_id'] ?? 0 );
		if ( ! $product || ! $product->is_visible() ) {
			// A hotspot pointing at a deleted or hidden product renders nothing rather
			// than a dot that opens an empty card.
			continue;
		}

		$dims = hrd_product_dims( $product );

		$out[] = array(
			'id'      => $spot['id'] ?? uniqid( 'h' ),
			'x_d'     => (float) ( $spot['x_d'] ?? 50 ),
			'y_d'     => (float) ( $spot['y_d'] ?? 50 ),
			'x_m'     => (float) ( $spot['x_m'] ?? $spot['x_d'] ?? 50 ),
			'y_m'     => (float) ( $spot['y_m'] ?? $spot['y_d'] ?? 50 ),
			'layer'   => in_array( $spot['layer'] ?? 'mid', array( 'bg', 'mid', 'fore' ), true ) ? $spot['layer'] : 'mid',
			'product' => array(
				'id'         => $product->get_id(),
				'name'       => $product->get_name(),
				'price_html' => $product->get_price_html(),
				'permalink'  => get_permalink( $product->get_id() ),
				'thumb'      => get_the_post_thumbnail_url( $product->get_id(), 'woocommerce_thumbnail' ) ?: '',
				'photo_type' => hrd_photo_type( $product ),
				'sku'        => $product->get_sku(),
				'dims'       => $dims,
				'in_stock'   => $product->is_in_stock(),
				// Load-bearing: a variable product must link out, never quick-add.
				'variable'   => hrd_is_variable( $product ),
			),
		);
	}

	return $out ?: null;
}
