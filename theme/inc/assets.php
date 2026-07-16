<?php
/**
 * Asset enqueueing via the Vite manifest.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

/**
 * Read a built entry's URL out of the Vite manifest.
 *
 * @param string $entry Source-relative entry path, e.g. 'src/css/main.css'.
 * @return string|null Public URL, or null when the entry has not been built yet.
 */
function hrd_asset( $entry ) {
	static $manifest = null;

	if ( null === $manifest ) {
		$path     = HRD_DIR . '/assets/dist/.vite/manifest.json';
		$manifest = is_readable( $path )
			? json_decode( file_get_contents( $path ), true ) // phpcs:ignore WordPress.WP.AlternativeFunctions
			: array();
	}

	// ponytail: no dev-server HMR proxy. `vite build --watch` writes the manifest on
	// every save, which is one moving part instead of three, and the PHP path stays
	// identical in dev and in the demo. Add HMR only if the rebuild latency bites.
	return isset( $manifest[ $entry ]['file'] )
		? HRD_URI . '/assets/dist/' . $manifest[ $entry ]['file']
		: null;
}

add_action(
	'wp_enqueue_scripts',
	function () {
		// Drop the block-library CSS: this is a classic theme and nothing on the
		// storefront renders core blocks.
		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'wc-blocks-style' );

		$css = hrd_asset( 'src/css/main.css' );
		if ( $css ) {
			wp_enqueue_style( 'hrd-main', $css, array(), HRD_VERSION );
		}

		$js = hrd_asset( 'src/js/main.js' );
		if ( $js ) {
			wp_enqueue_script( 'hrd-main', $js, array(), HRD_VERSION, true );
			wp_script_add_data( 'hrd-main', 'type', 'module' );
		}
	}
);

/**
 * Set the motion mode before first paint.
 *
 * This is the gate every animation consults. It runs inline in <head> rather than in
 * the bundle for two reasons: no FOUC of animated elements, and with JS disabled the
 * attribute is simply absent, so the whole site renders static. That is the intended
 * no-JS degradation, not an accident.
 */
add_action(
	'wp_head',
	function () {
		?>
		<script>
		document.documentElement.dataset.motion =
			localStorage.getItem('hr-motion') ??
			(matchMedia('(prefers-reduced-motion: reduce)').matches ? 'reduced' : 'full');
		</script>
		<?php
	},
	1
);
