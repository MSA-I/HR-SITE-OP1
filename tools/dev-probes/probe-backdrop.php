<?php
/**
 * Dev-only probe: measures the real backdrop luminance of the seeded product photos.
 *
 * The whole plate design assumes the products sit on WHITE so that multiply drops the
 * backdrop. The first sample came back at rgb(236,237,239) — a light blue-grey — which
 * multiply renders as a visible rectangle. This measures the distribution using GD,
 * server-side, so no browser canvas is involved.
 *
 * Reachable at /?hrd_probe=backdrop while WP_DEBUG is on. Delete before any handoff.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'template_redirect',
	function () {
		if ( empty( $_GET['hrd_probe'] ) || 'backdrop' !== $_GET['hrd_probe'] || ! WP_DEBUG ) {
			return;
		}

		header( 'Content-Type: text/plain; charset=utf-8' );

		$ids = get_posts(
			array(
				'post_type'      => 'product',
				'posts_per_page' => 80,
				'fields'         => 'ids',
				'orderby'        => 'rand',
			)
		);

		$lumas = array();
		$light_products = array();

		foreach ( $ids as $product_id ) {
			$attachment_id = get_post_thumbnail_id( $product_id );
			if ( ! $attachment_id ) {
				continue;
			}

			$path = get_attached_file( $attachment_id );
			if ( ! $path || ! file_exists( $path ) ) {
				continue;
			}

			$image = @imagecreatefromstring( file_get_contents( $path ) );
			if ( ! $image ) {
				continue;
			}

			$w = imagesx( $image );
			$h = imagesy( $image );

			$luma = function ( $x, $y ) use ( $image ) {
				$rgb = imagecolorat( $image, $x, $y );
				return ( ( ( $rgb >> 16 ) & 0xFF ) + ( ( $rgb >> 8 ) & 0xFF ) + ( $rgb & 0xFF ) ) / 3;
			};

			/*
			 * Take the MEDIAN of the four corners, not the min: a single corner holding
			 * product or a drop shadow drags the min to 0 and reports "no white
			 * backdrop" for a photo that plainly has one. The median asks the honest
			 * question — what is this photo mostly sitting on?
			 *
			 * The spread across corners is the second signal: a flat studio backdrop is
			 * uniform, a gradient or a lifestyle shot is not.
			 */
			$corners = array( $luma( 2, 2 ), $luma( $w - 3, 2 ), $luma( 2, $h - 3 ), $luma( $w - 3, $h - 3 ) );
			sort( $corners );
			$backdrop = (int) round( ( $corners[1] + $corners[2] ) / 2 );
			$spread = (int) round( $corners[3] - $corners[0] );

			$lumas[] = $backdrop;
			if ( $spread > 12 ) {
				$light_products[] = sprintf(
					'%s — corners %d/%d/%d/%d (spread %d)',
					get_the_title( $product_id ),
					$corners[0],
					$corners[1],
					$corners[2],
					$corners[3],
					$spread
				);
			}

			imagedestroy( $image );
		}

		sort( $lumas );
		$n = count( $lumas );
		$pct = fn( $p ) => $lumas[ (int) floor( $n * $p ) ] ?? 0;

		echo "measured: {$n} product photos\n\n";
		echo "backdrop luminance (min of 4 corners)\n";
		echo '  min    : ' . $lumas[0] . "\n";
		echo '  p10    : ' . $pct( 0.1 ) . "\n";
		echo '  median : ' . $pct( 0.5 ) . "\n";
		echo '  p90    : ' . $pct( 0.9 ) . "\n";
		echo '  max    : ' . $lumas[ $n - 1 ] . "\n\n";

		$buckets = array(
			'>= 250 (true white)'  => count( array_filter( $lumas, fn( $l ) => $l >= 250 ) ),
			'240-249 (off-white)'  => count( array_filter( $lumas, fn( $l ) => $l >= 240 && $l < 250 ) ),
			'230-239 (grey cast)'  => count( array_filter( $lumas, fn( $l ) => $l >= 230 && $l < 240 ) ),
			'< 230 (not white)'    => count( array_filter( $lumas, fn( $l ) => $l < 230 ) ),
		);
		foreach ( $buckets as $label => $count ) {
			printf( "  %-22s %3d  (%d%%)\n", $label, $count, $n ? round( $count / $n * 100 ) : 0 );
		}

		printf( "\nbrightness() to lift p10 (%d) to 255: %.3f\n", $pct( 0.1 ), $pct( 0.1 ) ? 255 / $pct( 0.1 ) : 0 );
		printf( "brightness() to lift median (%d) to 255: %.3f\n", $pct( 0.5 ), $pct( 0.5 ) ? 255 / $pct( 0.5 ) : 0 );

		printf( "\nphotos with a NON-uniform backdrop (corner spread > 12): %d of %d\n", count( $light_products ), $n );
		foreach ( array_slice( $light_products, 0, 12 ) as $item ) {
			echo "  - {$item}\n";
		}

		// Aspect ratios decide the plate's frame: contain letterboxes badly when the
		// frame and the photo disagree, and cover crops the product out of view.
		$ratios = array();
		foreach ( $ids as $product_id ) {
			$attachment_id = get_post_thumbnail_id( $product_id );
			$meta = $attachment_id ? wp_get_attachment_metadata( $attachment_id ) : null;
			if ( empty( $meta['width'] ) || empty( $meta['height'] ) ) {
				continue;
			}
			$ratios[] = round( $meta['width'] / $meta['height'], 3 );
		}

		sort( $ratios );
		$rn = count( $ratios );
		echo "\naspect ratios (w/h) of {$rn} featured images\n";
		printf( "  square (0.98-1.02) : %d (%d%%)\n", count( array_filter( $ratios, fn( $r ) => $r >= 0.98 && $r <= 1.02 ) ), round( count( array_filter( $ratios, fn( $r ) => $r >= 0.98 && $r <= 1.02 ) ) / max( $rn, 1 ) * 100 ) );
		printf( "  portrait (< 0.98)  : %d (%d%%)\n", count( array_filter( $ratios, fn( $r ) => $r < 0.98 ) ), round( count( array_filter( $ratios, fn( $r ) => $r < 0.98 ) ) / max( $rn, 1 ) * 100 ) );
		printf( "  landscape (> 1.02) : %d (%d%%)\n", count( array_filter( $ratios, fn( $r ) => $r > 1.02 ) ), round( count( array_filter( $ratios, fn( $r ) => $r > 1.02 ) ) / max( $rn, 1 ) * 100 ) );
		printf( "  min %.2f  median %.2f  max %.2f\n", $ratios[0] ?? 0, $ratios[ (int) ( $rn / 2 ) ] ?? 0, $ratios[ $rn - 1 ] ?? 0 );

		exit;
	}
);
