<?php
/**
 * Theme supports, menus, image sizes.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	function () {
		load_theme_textdomain( 'hrdesign', HRD_DIR . '/languages' );

		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );

		// The header logo was bloginfo('name') as plain text, which is why the client's
		// first note was that his logos are missing. Height is the real constraint: the
		// header is a 72px min-block-size row, so a 40px mark is what fits with the
		// padding the row already has. The width is generous because the asset is a
		// 720x520 landscape wordmark and cropping a client's logo to a square is not a
		// decision a theme gets to make.
		add_theme_support(
			'custom-logo',
			array(
				'height'               => 40,
				'width'                => 160,
				'flex-width'           => true,
				'flex-height'          => true,
				'unlink-homepage-logo' => false,
			)
		);
		add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'editor-styles' );

		// WooCommerce. Note the gallery features we deliberately do NOT declare:
		// wc-product-gallery-slider / -zoom / -lightbox. Half the catalogue has one
		// image, so flexslider+photoswipe would be ~60KB to render a single static <img>.
		add_theme_support( 'woocommerce' );

		// Only 'footer'. 'primary' was declared here and never read: inc/nav.php builds
		// the header from the product_cat tree on purpose, so the theme has nowhere to
		// render a primary menu. Offering the location anyway meant the admin showed a
		// menu slot that silently did nothing — the site owner assigns a menu to it and
		// waits for a change that is never coming. Declare what we actually render.
		register_nav_menus(
			array(
				'footer' => __( 'תפריט תחתון', 'hrdesign' ),
			)
		);
	}
);
