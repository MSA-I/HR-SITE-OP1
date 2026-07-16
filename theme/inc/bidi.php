<?php
/**
 * Keeps <bdi> alive through sanitisation.
 *
 * WordPress's allowed-post-tags list includes <bdo> but not <bdi>, so wp_kses_post()
 * silently deletes every <bdi> WooCommerce emits around a price. On an LTR site nobody
 * notices. On this one it is the difference between "599 ₪" and a mangled run: the
 * shekel sign is bidi-neutral, so unisolated it attaches to whatever text sits next to
 * it, and a price inline with Hebrew — in a toast, an aria-label, a cart line — renders
 * with the sign on the wrong side.
 *
 * WooCommerce is right, kses is stale, and the theme's own typography rules make bidi
 * isolation a hard requirement. So the tag gets allowed rather than the price left
 * unsanitised.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'wp_kses_allowed_html',
	function ( $tags, $context ) {
		if ( ! in_array( $context, array( 'post', 'pre_user_description' ), true ) ) {
			return $tags;
		}

		// bdi carries no scripting surface: it is dir + the global attributes.
		$tags['bdi'] = array(
			'class' => true,
			'dir'   => true,
			'id'    => true,
			'title' => true,
			'style' => true,
		);

		return $tags;
	},
	10,
	2
);
