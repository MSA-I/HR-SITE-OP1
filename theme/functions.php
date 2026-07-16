<?php
/**
 * Theme bootstrap. Kept thin on purpose — everything real lives in inc/.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

define( 'HRD_VERSION', '0.1.0' );
define( 'HRD_DIR', get_template_directory() );
define( 'HRD_URI', get_template_directory_uri() );

require_once HRD_DIR . '/inc/setup.php';
require_once HRD_DIR . '/inc/assets.php';
require_once HRD_DIR . '/inc/nav.php';
require_once HRD_DIR . '/inc/bidi.php';

/*
 * The audit probes used to be required from here behind a WP_DEBUG check. They now live
 * in tools/dev-probes/ and load as a must-use plugin instead: keeping them out of the
 * theme directory entirely means the folder that gets handed over cannot carry a debug
 * panel by accident. See tools/audit/README.md.
 */

if ( class_exists( 'WooCommerce' ) ) {
	require_once HRD_DIR . '/inc/woocommerce/setup.php';
	require_once HRD_DIR . '/inc/woocommerce/badges.php';
	require_once HRD_DIR . '/inc/woocommerce/cart.php';
	require_once HRD_DIR . '/inc/woocommerce/filters.php';
	require_once HRD_DIR . '/inc/woocommerce/single.php';
	require_once HRD_DIR . '/inc/woocommerce/summary.php';

	require_once HRD_DIR . '/inc/room-scene/cpt.php';
	require_once HRD_DIR . '/inc/room-scene/metabox.php';
}
