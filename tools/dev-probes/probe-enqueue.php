<?php
/**
 * Dev-only: what is actually enqueued on this page, and who asked for it?
 *
 * /?hrd_probe=enqueue
 *
 * The homepage of a classic theme that renders no blocks is pulling 1.3MB of
 * JavaScript. This finds the roots — dequeuing a dependency is pointless if something
 * still depends on it.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_print_footer_scripts',
	function () {
		if ( empty( $_GET['hrd_probe'] ) || 'enqueue' !== $_GET['hrd_probe'] || ! WP_DEBUG ) {
			return;
		}

		global $wp_scripts;

		$rows = array();
		foreach ( $wp_scripts->done as $handle ) {
			$item = $wp_scripts->registered[ $handle ] ?? null;
			if ( ! $item ) {
				continue;
			}

			$src = $item->src ?: '';
			$path = $src ? ABSPATH . ltrim( wp_make_link_relative( $src ), '/' ) : '';
			$size = $path && file_exists( $path ) ? filesize( $path ) : 0;

			// Who pulled this in?
			$dependents = array();
			foreach ( $wp_scripts->registered as $other_handle => $other ) {
				if ( in_array( $handle, (array) $other->deps, true ) && in_array( $other_handle, $wp_scripts->done, true ) ) {
					$dependents[] = $other_handle;
				}
			}

			$rows[] = array(
				'handle'     => $handle,
				'kb'         => round( $size / 1024 ),
				'dependents' => $dependents,
			);
		}

		usort( $rows, fn( $a, $b ) => $b['kb'] <=> $a['kb'] );

		echo "\n<!-- HRD ENQUEUE PROBE\n";
		printf( "%-42s %6s  %s\n", 'handle', 'KB', 'pulled in by' );
		$total = 0;
		foreach ( $rows as $row ) {
			$total += $row['kb'];
			if ( $row['kb'] < 4 ) {
				continue;
			}
			printf(
				"%-42s %6d  %s\n",
				$row['handle'],
				$row['kb'],
				$row['dependents'] ? implode( ', ', array_slice( $row['dependents'], 0, 3 ) ) : '(root — enqueued directly)'
			);
		}
		printf( "\n%-42s %6d across %d handles\n", 'TOTAL', $total, count( $rows ) );
		echo "-->\n";
	},
	1
);
