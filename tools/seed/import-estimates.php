<?php
/**
 * Imports the AI dimension estimates.
 *
 *   docker compose exec wpcli wp eval-file /tools/seed/import-estimates.php
 *
 * These are INFERRED numbers on a real store's real products, so the storage is
 * deliberately defensive:
 *
 *   - they never touch WooCommerce's native _length/_width/_height, which stay reserved
 *     for measured data
 *   - they live in _hrd_dims_estimated with _hrd_dims_source = 'estimated'
 *   - the UI renders them with a ~ prefix and a "מידות משוערות" label
 *
 * So an estimate can never be mistaken for a measurement, and one query removes every
 * estimate if this ever goes near production:
 *
 *   wp post meta delete --all _hrd_dims_estimated
 *
 * Idempotent: skips any product that has real dimensions, even if the estimator
 * produced one for it.
 */

defined( 'ABSPATH' ) || die( 'wp eval-file only' );

$id_map = json_decode( file_get_contents( '/seed/id-map.json' ), true );

$estimates = array();
foreach ( array( '/seed/dims-estimated-a.json', '/seed/dims-estimated-b.json' ) as $file ) {
	if ( ! file_exists( $file ) ) {
		WP_CLI::warning( "missing: {$file}" );
		continue;
	}
	$batch = json_decode( file_get_contents( $file ), true );
	if ( ! is_array( $batch ) ) {
		WP_CLI::warning( "unparseable: {$file}" );
		continue;
	}
	$estimates = array_merge( $estimates, $batch );
}

WP_CLI::log( sprintf( '%d estimates loaded', count( $estimates ) ) );

$applied = 0;
$skipped_real = 0;
$skipped_missing = 0;
$rejected = 0;
$by_confidence = array();

foreach ( $estimates as $row ) {
	$src_id = $row['id'] ?? 0;
	if ( ! isset( $id_map[ $src_id ] ) ) {
		$skipped_missing++;
		continue;
	}

	$product_id = $id_map[ $src_id ];
	$product = wc_get_product( $product_id );
	if ( ! $product ) {
		$skipped_missing++;
		continue;
	}

	// Measured data always wins. An estimate must never overwrite a real number.
	if ( $product->get_length() || $product->get_width() || $product->get_height() ) {
		$skipped_real++;
		continue;
	}

	$dims = array();
	foreach ( array( 'l', 'w', 'h' ) as $axis ) {
		$value = $row[ $axis ] ?? null;
		if ( null === $value || '' === $value ) {
			continue;
		}
		$value = (float) $value;
		// Same sanity band the parser uses. A hallucinated 4000cm sofa gets dropped
		// rather than displayed with a tilde in front of it.
		if ( $value < 1 || $value > 400 ) {
			$rejected++;
			continue 2;
		}
		$dims[ $axis ] = $value;
	}

	if ( ! $dims ) {
		$rejected++;
		continue;
	}

	update_post_meta( $product_id, '_hrd_dims_estimated', $dims );
	update_post_meta( $product_id, '_hrd_dims_source', 'estimated' );
	update_post_meta( $product_id, '_hrd_dims_confidence', $row['confidence'] ?? 'medium' );
	update_post_meta( $product_id, '_hrd_dims_basis', $row['basis'] ?? '' );

	$key = $row['confidence'] ?? 'medium';
	$by_confidence[ $key ] = ( $by_confidence[ $key ] ?? 0 ) + 1;
	$applied++;
}

WP_CLI::log( '' );
foreach ( $by_confidence as $level => $count ) {
	WP_CLI::log( sprintf( '  %-8s %3d', $level, $count ) );
}

WP_CLI::success(
	sprintf(
		'%d estimates applied, %d skipped (real dims exist), %d skipped (unknown product), %d rejected (out of range).',
		$applied,
		$skipped_real,
		$skipped_missing,
		$rejected
	)
);
