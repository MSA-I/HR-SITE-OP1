<?php
/**
 * WooCommerce integration: strip the defaults, then rebuild deliberately.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

/*
 * Remove the default loop furniture. We re-add what we want inside our own
 * content-product.php, which is one of the few templates worth overriding at all —
 * the card's DOM structure itself has to change.
 */
add_action(
	'init',
	function () {
		remove_action( 'woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open', 10 );
		remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10 );
		remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10 );
		remove_action( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title', 10 );
		remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 );
		remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
		remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close', 5 );
		remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );

		// Sidebar: we build our own filter rail in phase 5.
		remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );
	}
);

/**
 * Dequeue the gallery stack.
 *
 * We never declared wc-product-gallery-slider/-zoom/-lightbox, but WooCommerce still
 * enqueues the scripts. 35% of this catalogue has exactly one image — loading
 * flexslider and photoswipe to render a single static <img> is pure weight.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		wp_dequeue_script( 'wc-single-product' );
		wp_dequeue_script( 'flexslider' );
		wp_dequeue_script( 'photoswipe' );
		wp_dequeue_script( 'photoswipe-ui-default' );
		wp_dequeue_script( 'zoom' );
		wp_dequeue_style( 'photoswipe' );
		wp_dequeue_style( 'photoswipe-default-skin' );
		wp_dequeue_style( 'woocommerce-general' );
		wp_dequeue_style( 'woocommerce-layout' );
		wp_dequeue_style( 'woocommerce-smallscreen' );
	},
	99
);

/** Products per page — 4-up grid, so a multiple of 4 and 3 keeps rows whole. */
add_filter( 'loop_shop_per_page', fn() => 24, 20 );

/**
 * "New" and "on sale" archive views.
 *
 * Recency orders by _hrd_src_id, not post_date: every product was imported on the same
 * day, so post_date carries no signal. The live store's ids are sequential by creation,
 * which makes them a real — if relative — recency signal.
 */
add_action(
	'woocommerce_product_query',
	function ( $query ) {
		if ( is_admin() ) {
			return;
		}

		if ( isset( $_GET['on_sale'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$query->set( 'post__in', array_merge( array( 0 ), wc_get_product_ids_on_sale() ) );
		}

		if ( isset( $_GET['orderby'] ) && 'date' === $_GET['orderby'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			$query->set( 'meta_key', '_hrd_src_id' );
			$query->set( 'orderby', 'meta_value_num' );
			$query->set( 'order', 'DESC' );
		}
	}
);

add_filter( 'woocommerce_output_related_products_args', fn( $args ) => array_merge( $args, array( 'posts_per_page' => 4, 'columns' => 4 ) ) );

/** The archive grid column count is CSS-driven; tell Woo so its markup agrees. */
add_filter( 'loop_shop_columns', fn() => 4, 20 );

/**
 * Sort options.
 *
 * "Average rating" is removed: there are zero reviews across the entire catalogue, so
 * it is an option that sorts nothing. "Default sorting" is removed because menu_order
 * is unset on every product, so it silently means "by title" anyway — better to say so.
 */
add_filter(
	'woocommerce_catalog_orderby',
	function () {
		return array(
			'title'      => __( 'לפי שם', 'hrdesign' ),
			'date'       => __( 'חדשים ראשונים', 'hrdesign' ),
			'popularity' => __( 'הנמכרים ביותר', 'hrdesign' ),
			'price'      => __( 'מחיר: מהנמוך לגבוה', 'hrdesign' ),
			'price-desc' => __( 'מחיר: מהגבוה לנמוך', 'hrdesign' ),
		);
	}
);

/**
 * Breadcrumb: WooCommerce's default wrapper is a <nav> with no label.
 */
add_filter(
	'woocommerce_breadcrumb_defaults',
	fn( $args ) => array_merge(
		$args,
		array(
			'delimiter' => '<span class="breadcrumb__sep" aria-hidden="true"> / </span>',
			'wrap_before' => '<nav class="breadcrumb t-mono" aria-label="' . esc_attr__( 'מיקומך באתר', 'hrdesign' ) . '">',
			'wrap_after' => '</nav>',
		)
	)
);
