<?php
/**
 * Development probes — loaded as a must-use plugin, never as theme code.
 *
 *   docker compose exec wpcli wp eval-file /tools/dev-probes/install.php
 *
 * These shaped the build and are worth keeping (see tools/audit/README.md), but they are
 * instruments, not product. Living outside theme/ means the theme directory that gets
 * handed over cannot contain them by accident — which is the failure mode when the only
 * thing standing between a debug panel and production is someone remembering to delete a
 * file.
 *
 * Doubly gated: mu-plugins are not shipped with the theme, and every probe checks
 * WP_DEBUG anyway.
 *
 * @package hrdesign-dev
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
	return;
}

foreach ( glob( __DIR__ . '/probe-*.php' ) as $probe ) {
	require_once $probe;
}
