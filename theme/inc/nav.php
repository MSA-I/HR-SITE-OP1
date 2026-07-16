<?php
/**
 * Navigation data.
 *
 * The mega menu is built from the existing product_cat tree, not from a WP nav menu:
 * the live store already has a term image on nearly every category, so the whole panel
 * costs zero authoring and stays correct as the catalogue changes.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

/**
 * Terms that are merchandising flags, not categories.
 *
 * The live store models "on sale" and "out of stock" as product categories. They are
 * real terms with real products, but rendering them as navigation is wrong — and the
 * sale term in particular is hand-maintained and already drifting out of sync with the
 * actual sale prices. מבצעים in the header queries on_sale instead.
 *
 * @return string[] Slugs to hide.
 */
function hrd_nav_excluded_slugs() {
	return apply_filters(
		'hrd_nav_excluded_slugs',
		array(
			'december-sales',
			'sales',
			'uncategorized',
			'ללא-קטגוריה',
			'לא-במלאי-בהזמנה-משלימה',
		)
	);
}

/**
 * The category tree for the mega menu: top-level terms, each with children.
 *
 * Cached in a transient — this is 63 terms plus their images on every page load
 * otherwise, and the tree changes about never.
 *
 * @return array<int,array{term:WP_Term,image:string,children:WP_Term[]}>
 */
function hrd_category_tree() {
	$cached = get_transient( 'hrd_category_tree' );
	if ( false !== $cached ) {
		return $cached;
	}

	$excluded = hrd_nav_excluded_slugs();

	$tops = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'parent'     => 0,
			'hide_empty' => true,
			'orderby'    => 'count',
			'order'      => 'DESC',
		)
	);

	if ( is_wp_error( $tops ) ) {
		return array();
	}

	$tree = array();

	foreach ( $tops as $top ) {
		if ( in_array( $top->slug, $excluded, true ) || str_contains( $top->name, 'מבצע' ) ) {
			continue;
		}

		$children = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'parent'     => $top->term_id,
				'hide_empty' => true,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);

		if ( is_wp_error( $children ) ) {
			$children = array();
		}

		$children = array_values(
			array_filter(
				$children,
				fn( $child ) => ! in_array( $child->slug, $excluded, true ) && ! str_contains( $child->name, 'מבצע' )
			)
		);

		$thumbnail_id = get_term_meta( $top->term_id, 'thumbnail_id', true );

		// Fall back to a child's image, then to a product's, so the preview plate is
		// never empty — an empty slot in a fixed-height panel is the ugliest failure.
		if ( ! $thumbnail_id ) {
			foreach ( $children as $child ) {
				$thumbnail_id = get_term_meta( $child->term_id, 'thumbnail_id', true );
				if ( $thumbnail_id ) {
					break;
				}
			}
		}

		$image = $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, 'woocommerce_thumbnail' ) : '';

		if ( ! $image ) {
			$product = get_posts(
				array(
					'post_type'      => 'product',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'tax_query'      => array(
						array(
							'taxonomy' => 'product_cat',
							'field'    => 'term_id',
							'terms'    => $top->term_id,
						),
					),
				)
			);
			if ( $product ) {
				$image = get_the_post_thumbnail_url( $product[0], 'woocommerce_thumbnail' ) ?: '';
			}
		}

		$tree[] = array(
			'term'     => $top,
			'image'    => $image,
			'children' => array_slice( $children, 0, 10 ),
		);
	}

	$tree = array_slice( $tree, 0, 8 );
	set_transient( 'hrd_category_tree', $tree, DAY_IN_SECONDS );

	return $tree;
}

/** The tree is cached; any category edit must invalidate it. */
foreach ( array( 'edited_product_cat', 'created_product_cat', 'delete_product_cat' ) as $hook ) {
	add_action( $hook, fn() => delete_transient( 'hrd_category_tree' ) );
}
