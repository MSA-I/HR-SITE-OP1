<?php
/**
 * Product page.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	function () {
		// Rebuild the summary column deliberately rather than inheriting Woo's order.
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_title', 5 );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10 );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_sharing', 50 );

		// No reviews tab: there are zero reviews across the entire catalogue. An empty
		// reviews module is worse than none — it is the loudest "this is a template"
		// signal there is. It slots back in the moment review data exists.
		remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );
	}
);

/**
 * Product tabs: materials/care and delivery. Content is global, from theme options —
 * not per-product authoring for 250 products that have no such copy.
 */
add_filter(
	'woocommerce_product_tabs',
	function ( $tabs ) {
		unset( $tabs['reviews'], $tabs['additional_information'] );
		return $tabs;
	},
	98
);

/**
 * The WhatsApp question link.
 *
 * A text link in the meta rail, at priority 35 — below the add-to-cart button, never a
 * floating green blob. The brief is explicit that it must not replace the purchase.
 */
add_action(
	'woocommerce_single_product_summary',
	function () {
		global $product;

		$number = hrd_brand()['whatsapp'];
		if ( ! $number ) {
			return;
		}

		$message = sprintf(
			/* translators: 1: product name, 2: product URL */
			__( "שלום, אשמח לפרטים על %1\$s\n%2\$s", 'hrdesign' ),
			$product->get_name(),
			get_permalink( $product->get_id() )
		);

		printf(
			'<a class="whatsapp-link" href="https://wa.me/%s?text=%s" target="_blank" rel="noopener">%s</a>',
			esc_attr( $number ),
			rawurlencode( $message ),
			esc_html__( 'שאלה על המוצר בוואטסאפ', 'hrdesign' )
		);
	},
	35
);

/**
 * Related products: "משתלב במיוחד עם".
 *
 * Native cross-sells when authored, else same-category neighbours in an adjacent price
 * band — a product 10x the price is not a pairing suggestion. No plugin.
 *
 * @param int[] $related_ids Default related ids.
 * @param int   $product_id  Current product.
 * @return int[]
 */
add_filter(
	'woocommerce_related_products',
	function ( $related_ids, $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return $related_ids;
		}

		$cross = $product->get_cross_sell_ids();
		if ( $cross ) {
			return $cross;
		}

		$price = (float) $product->get_price();
		if ( ! $price || ! $related_ids ) {
			return $related_ids;
		}

		// Keep the price band wide enough that thin categories still return four.
		$scored = array();
		foreach ( $related_ids as $id ) {
			$candidate = wc_get_product( $id );
			if ( ! $candidate ) {
				continue;
			}
			$other = (float) $candidate->get_price();
			if ( ! $other ) {
				continue;
			}
			$scored[ $id ] = abs( log( $other / $price ) ); // ratio distance, not absolute
		}

		if ( ! $scored ) {
			return $related_ids;
		}

		asort( $scored );
		return array_keys( $scored );
	},
	10,
	2
);
