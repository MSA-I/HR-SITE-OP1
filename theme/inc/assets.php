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
 * Preload the Hebrew display faces that the page actually uses.
 *
 * font-display: swap means the browser discovers a face only after the stylesheet parses,
 * and reflows the text it touches when it lands. Karantina carries every heading, the
 * logo and the related-products title, so it is worth getting ahead of.
 *
 * Hebrew only. The Latin faces sit behind a unicode-range and a Hebrew page needs them for
 * prices and SKUs at most, which is not worth 30KB on the critical path.
 *
 * ONLY 300 TODAY. karantina-400-hebrew.woff2 is built and shipped, but nothing requests
 * weight 400 yet: every --ff-display consumer pins 300, so document.fonts reports the 400
 * face "unloaded" on every page type. Preloading it now would fetch 6100 B at high
 * priority, on every uncached load, for a face the renderer never asks for. It joins this
 * list in the same change that repoints the tokens onto it, for the same reason the font
 * fetch and the token repoint have to be atomic in the other direction.
 *
 * crossorigin is not optional on a font preload even though these are same-origin: fonts
 * are fetched in anonymous CORS mode, and a preload without it downloads the file a second
 * time instead of matching the one already in flight.
 */
add_action(
	'wp_head',
	function () {
		foreach ( array( 'karantina-300-hebrew' ) as $face ) {
			$url = hrd_asset( 'src/fonts/' . $face . '.woff2' );

			// Same contract as every other asset here: unbuilt means no tag, not a broken
			// one. A preload pointing at a 404 costs a request and warns in the console.
			if ( ! $url ) {
				continue;
			}

			printf(
				'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
				esc_url( $url )
			);
		}
	},
	2
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
