<?php
/**
 * Product page layout.
 *
 * Media column takes the inline-start (= the right, in RTL): the eye starts there in
 * Hebrew. The buy box is position:sticky inside its own grid column, so it travels with
 * the media column and releases naturally at the column's end. Zero JS.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

global $product;
?>

<div id="product-<?php the_ID(); ?>" <?php wc_product_class( 'pdp is-' . hrd_photo_type( $product ), $product ); ?>>
	<?php do_action( 'woocommerce_before_single_product_summary' ); ?>

	<div class="pdp__summary">
		<?php do_action( 'woocommerce_single_product_summary' ); ?>
	</div>
</div>

<?php do_action( 'woocommerce_after_single_product_summary' ); ?>
