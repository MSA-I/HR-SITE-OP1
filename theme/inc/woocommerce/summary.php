<?php
/**
 * The buy box contents, in order.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

/** Collection eyebrow — the top-level category, which is what reads as a collection. */
add_action(
	'woocommerce_single_product_summary',
	function () {
		global $product;

		$terms = wp_get_post_terms( $product->get_id(), 'product_cat' );
		if ( is_wp_error( $terms ) || ! $terms ) {
			return;
		}

		// Deepest term is the most specific and the most informative.
		usort( $terms, fn( $a, $b ) => $b->parent <=> $a->parent );
		printf(
			'<a class="pdp__eyebrow" href="%s">%s</a>',
			esc_url( get_term_link( $terms[0] ) ),
			esc_html( $terms[0]->name )
		);
	},
	4
);

add_action(
	'woocommerce_single_product_summary',
	function () {
		the_title( '<h1 class="pdp__title">', '</h1>' );
	},
	5
);

add_action(
	'woocommerce_single_product_summary',
	function () {
		global $product;
		printf( '<p class="pdp__price">%s</p>', wp_kses_post( $product->get_price_html() ) );
	},
	10
);

/** Stock chip. Real data — the live store tracks this. */
add_action(
	'woocommerce_single_product_summary',
	function () {
		global $product;

		$in = $product->is_in_stock();
		printf(
			'<span class="stock-chip%s">%s</span>',
			$in ? '' : ' stock-chip--out',
			esc_html( $in ? __( 'במלאי', 'hrdesign' ) : __( 'אזל מהמלאי', 'hrdesign' ) )
		);
	},
	11
);

/** Delivery estimate, below the button. */
add_action(
	'woocommerce_single_product_summary',
	function () {
		global $product;
		printf( '<p class="pdp__delivery">%s</p>', esc_html( hrd_delivery_estimate( $product ) ) );
	},
	31
);

/** The mono spec line: sku and dimensions, each only when present. */
add_action(
	'woocommerce_single_product_summary',
	function () {
		global $product;

		$sku  = $product->get_sku();
		$dims = hrd_product_dims( $product );

		if ( ! $sku && ! $dims ) {
			return;
		}

		$parts = array();
		if ( $sku ) {
			$parts[] = sprintf( '<bdi>%s</bdi>', esc_html( $sku ) );
		}
		if ( $dims ) {
			$formatted = implode( '×', array_map( fn( $d ) => rtrim( rtrim( number_format( $d, 1 ), '0' ), '.' ), $dims['dims'] ) );
			$parts[] = sprintf(
				'<bdi%s>%s%s</bdi>%s',
				$dims['estimated'] ? ' class="is-estimated" title="' . esc_attr__( 'מידות משוערות', 'hrdesign' ) . '"' : '',
				$dims['estimated'] ? '~' : '',
				esc_html( $formatted ),
				$dims['estimated'] ? ' <span class="visually-hidden">' . esc_html__( 'מידות משוערות', 'hrdesign' ) . '</span>' : ''
			);
		}

		printf( '<p class="pdp__spec t-mono">%s</p>', implode( ' <span aria-hidden="true">·</span> ', $parts ) );
	},
	33
);

/**
 * Materials / delivery accordion + the mobile buy bar.
 *
 * Copy is global from theme options: these 250 products carry no such text, and
 * authoring it per-product is not a thing anyone will do.
 */
add_action(
	'woocommerce_after_single_product_summary',
	function () {
		global $product;

		$sections = array(
			__( 'חומרים ותחזוקה', 'hrdesign' ) => get_option( 'hrd_care_copy', __( 'כל פריט מיוצר מחומרים טבעיים, ולכן גוון ומרקם עשויים להשתנות מעט בין פריט לפריט. לניקוי — מטלית לחה ומעט סבון עדין. יש להימנע מחומרים ממיסים ומחשיפה ממושכת לשמש ישירה.', 'hrdesign' ) ),
			__( 'משלוח, הרכבה והחזרות', 'hrdesign' ) => get_option( 'hrd_shipping_copy', __( 'משלוח לכל הארץ. פריטים גדולים מגיעים בהובלה ייעודית בתיאום מראש, וניתן להוסיף שירות הרכבה. ניתן להחזיר פריט באריזתו המקורית תוך 14 יום מקבלתו.', 'hrdesign' ) ),
		);

		echo '<div class="pdp-details">';
		foreach ( $sections as $title => $body ) {
			printf(
				'<details><summary>%s</summary><div class="pdp-details__body"><p>%s</p></div></details>',
				esc_html( $title ),
				esc_html( $body )
			);
		}
		echo '</div>';

		// Mobile buy bar. Hidden by CSS above 900px; JS reveals it once the in-flow
		// button leaves the viewport.
		if ( $product->is_in_stock() && ! hrd_is_variable( $product ) ) {
			printf(
				'<div class="buy-bar" data-buy-bar><span class="buy-bar__price">%s</span>
					<button type="button" class="btn btn--primary" data-add-to-cart="%d">
						<span class="btn__label">%s</span><span class="btn__done" aria-hidden="true">%s</span>
					</button>
				</div>',
				wp_kses_post( $product->get_price_html() ),
				(int) $product->get_id(),
				esc_html__( 'הוספה לסל', 'hrdesign' ),
				esc_html__( 'נוסף לסל', 'hrdesign' )
			);
		}
	},
	15
);

/** Related heading in Hebrew, matching the brief's wording. */
add_filter( 'woocommerce_product_related_products_heading', fn() => __( 'משתלב במיוחד עם', 'hrdesign' ) );
