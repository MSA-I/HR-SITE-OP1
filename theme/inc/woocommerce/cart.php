<?php
/**
 * Cart glue: the Store API handshake and the header count.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hand the front end what it needs to talk to the Store API.
 *
 * The nonce is the load-bearing part: cart mutations 401 without it. The JS replaces
 * this value with the fresh one every response returns.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! wp_script_is( 'hrd-main', 'enqueued' ) ) {
			return;
		}

		wp_localize_script(
			'hrd-main',
			'hrdStore',
			array(
				'root'    => esc_url_raw( rest_url() ),
				'nonce'   => wp_create_nonce( 'wc_store_api' ),
				'cartUrl' => wc_get_cart_url(),
				'i18n'    => array(
					'added'      => __( 'נוסף לסל', 'hrdesign' ),
					'addFailed'  => __( 'ההוספה לסל נכשלה. נסו שוב.', 'hrdesign' ),
					'viewCart'   => __( 'לצפייה בסל', 'hrdesign' ),
				),
			)
		);
	},
	20
);

/**
 * Cart item count for the header badge.
 *
 * @return int
 */
function hrd_cart_count() {
	return WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
}
