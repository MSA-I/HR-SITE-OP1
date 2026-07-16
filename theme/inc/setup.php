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
		add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'editor-styles' );

		// WooCommerce. Note the gallery features we deliberately do NOT declare:
		// wc-product-gallery-slider / -zoom / -lightbox. Half the catalogue has one
		// image, so flexslider+photoswipe would be ~60KB to render a single static <img>.
		add_theme_support( 'woocommerce' );

		register_nav_menus(
			array(
				'primary' => __( 'תפריט ראשי', 'hrdesign' ),
				'footer'  => __( 'תפריט תחתון', 'hrdesign' ),
			)
		);
	}
);
