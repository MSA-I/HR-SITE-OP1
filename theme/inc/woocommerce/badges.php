<?php
/**
 * Card badges and the product facts the card renders.
 *
 * Every getter here returns null/empty rather than a placeholder when data is absent.
 * That is a hard requirement, not politeness: this catalogue is thin and uneven, and a
 * card that renders an empty rail where richer data would go reflows and looks broken.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

/** Share of the catalogue that may carry the "new" badge. */
const HRD_NEW_SHARE = 0.1;

/**
 * The _hrd_src_id threshold above which a product counts as new.
 *
 * post_date is useless here: everything was imported on the same day, so a date-based
 * rule badges 100% of the catalogue, which says nothing. The live store's post IDs are
 * sequential by creation, so a high source id IS a real recency signal — the top decile
 * really are the newest products on the live store. No dates are invented.
 *
 * @return int
 */
function hrd_new_threshold() {
	$cached = get_transient( 'hrd_new_threshold' );
	if ( false !== $cached ) {
		return (int) $cached;
	}

	global $wpdb;
	$ids = $wpdb->get_col(
		"SELECT meta_value + 0 FROM {$wpdb->postmeta} WHERE meta_key = '_hrd_src_id' ORDER BY meta_value + 0 DESC"
	);

	if ( ! $ids ) {
		return PHP_INT_MAX;
	}

	$index = (int) floor( count( $ids ) * HRD_NEW_SHARE );
	$threshold = (int) $ids[ min( $index, count( $ids ) - 1 ) ];

	set_transient( 'hrd_new_threshold', $threshold, DAY_IN_SECONDS );
	return $threshold;
}

/**
 * Badges for a product, most important first. Max one is rendered.
 *
 * @param WC_Product $product Product.
 * @return array<array{label:string,variant:string}>
 */
function hrd_product_badges( $product ) {
	$badges = array();

	if ( $product->is_on_sale() ) {
		$badges[] = array( 'label' => __( 'מבצע', 'hrdesign' ), 'variant' => 'sale' );
	}

	$src_id = (int) get_post_meta( $product->get_id(), '_hrd_src_id', true );
	if ( $src_id && $src_id >= hrd_new_threshold() ) {
		$badges[] = array( 'label' => __( 'חדש', 'hrdesign' ), 'variant' => 'new' );
	}

	if ( has_term( 'limited-edition', 'product_tag', $product->get_id() ) ) {
		$badges[] = array( 'label' => __( 'מהדורה מוגבלת', 'hrdesign' ), 'variant' => 'limited' );
	}

	if ( ! $product->is_in_stock() ) {
		$badges[] = array( 'label' => __( 'אזל', 'hrdesign' ), 'variant' => 'oos' );
	}

	return $badges;
}

/**
 * The hover image, or null.
 *
 * 35% of this catalogue has no second image. Guarding here is what stops those cards
 * from flickering: with no return value the card renders static and grows no hover
 * affordance at all.
 *
 * @param WC_Product $product Product.
 * @return int|null Attachment id.
 */
function hrd_hover_image_id( $product ) {
	$gallery = $product->get_gallery_image_ids();
	return $gallery ? (int) $gallery[0] : null;
}

/**
 * Colour terms with swatch hex, or an empty array.
 *
 * @param WC_Product $product Product.
 * @return array<array{name:string,hex:?string}>
 */
function hrd_product_colours( $product ) {
	$terms = wp_get_post_terms( $product->get_id(), 'pa_color' );
	if ( is_wp_error( $terms ) || ! $terms ) {
		return array();
	}

	$out = array();
	foreach ( $terms as $term ) {
		$hex = get_term_meta( $term->term_id, 'hrd_hex', true );
		$out[] = array(
			'name' => $term->name,
			// No hex mapped: the card renders a text chip rather than a wrong-coloured
			// dot. A swatch that lies about the colour causes returns.
			'hex'  => $hex ?: null,
		);
	}
	return $out;
}

/**
 * Dimensions as [l, w, h], plus whether they are measured or estimated.
 *
 * Measured data comes from WooCommerce's native fields and always wins. Estimates live
 * in a separate meta key and are never merged in — a card must be able to say which it
 * is showing, because these are a real store's real products and an inferred number
 * must never read as a measured one.
 *
 * @param WC_Product $product Product.
 * @return array{dims:array<float>,estimated:bool}|null
 */
function hrd_product_dims( $product ) {
	$measured = array_filter(
		array(
			(float) $product->get_length(),
			(float) $product->get_width(),
			(float) $product->get_height(),
		)
	);

	if ( $measured ) {
		return array(
			'dims'      => array_values( $measured ),
			'estimated' => false,
		);
	}

	$estimated = get_post_meta( $product->get_id(), '_hrd_dims_estimated', true );
	if ( ! is_array( $estimated ) || ! $estimated ) {
		return null;
	}

	// Keep l, w, h in order and drop any axis the estimator honestly left out.
	$ordered = array_values(
		array_filter(
			array(
				isset( $estimated['l'] ) ? (float) $estimated['l'] : 0,
				isset( $estimated['w'] ) ? (float) $estimated['w'] : 0,
				isset( $estimated['h'] ) ? (float) $estimated['h'] : 0,
			)
		)
	);

	return $ordered ? array( 'dims' => $ordered, 'estimated' => true ) : null;
}

/**
 * Dimensions keyed by axis, for the diagram and the scale reference.
 *
 * hrd_product_dims() compacts to a display list, which loses which number is which —
 * fine for a spec line, wrong for a drawing. Any axis may legitimately be null: a flat
 * mirror has no depth, and inventing one to fill the slot is exactly what the estimator
 * was told not to do.
 *
 * @param WC_Product $product Product.
 * @return array{l:?float,w:?float,h:?float,estimated:bool}|null
 */
function hrd_product_axes( $product ) {
	$measured = array(
		'l' => (float) $product->get_length() ?: null,
		'w' => (float) $product->get_width() ?: null,
		'h' => (float) $product->get_height() ?: null,
	);

	if ( array_filter( $measured ) ) {
		return $measured + array( 'estimated' => false );
	}

	$estimated = get_post_meta( $product->get_id(), '_hrd_dims_estimated', true );
	if ( ! is_array( $estimated ) || ! $estimated ) {
		return null;
	}

	return array(
		'l'         => isset( $estimated['l'] ) ? (float) $estimated['l'] : null,
		'w'         => isset( $estimated['w'] ) ? (float) $estimated['w'] : null,
		'h'         => isset( $estimated['h'] ) ? (float) $estimated['h'] : null,
		'estimated' => true,
	);
}

/**
 * Is this product variable on the live store?
 *
 * The seed imports everything as simple, but the card must not offer quick-add for
 * something that really has options — a quick-add that silently adds the wrong variant
 * is the classic bug in this pattern.
 *
 * @param WC_Product $product Product.
 * @return bool
 */
function hrd_is_variable( $product ) {
	return 'variable' === get_post_meta( $product->get_id(), '_hrd_src_type', true )
		|| $product->is_type( 'variable' );
}

/**
 * How this product's photography was shot, as measured at import.
 *
 * 'studio' — flat white backdrop. It multiplies onto the accent tint cleanly and reads
 *            as a catalogue plate. Only ~28% of this catalogue.
 * 'scene'  — a room photograph. Multiplying it would darken the whole frame and dissolve
 *            it into the tint, so it fills the frame as a photograph instead.
 *
 * Measured by tools/seed/classify-photos.php. Falls back to 'scene', which is the safe
 * rendering — a scene shown as a plate is a bug, a plate shown as a scene is merely less
 * pretty.
 *
 * @param WC_Product $product Product.
 * @return string 'studio'|'scene'
 */
function hrd_photo_type( $product ) {
	return 'studio' === get_post_meta( $product->get_id(), '_hrd_photo_type', true ) ? 'studio' : 'scene';
}

/**
 * Estimated delivery copy. One global default; per-product meta wins when set.
 *
 * @param WC_Product $product Product.
 * @return string
 */
function hrd_delivery_estimate( $product ) {
	$override = get_post_meta( $product->get_id(), '_hrd_delivery', true );
	if ( $override ) {
		return $override;
	}

	return $product->is_in_stock()
		? get_option( 'hrd_delivery_default', __( 'אספקה תוך 7–14 ימי עסקים', 'hrdesign' ) )
		: get_option( 'hrd_delivery_oos', __( 'בהזמנה מיוחדת — צרו קשר לזמן אספקה', 'hrdesign' ) );
}
