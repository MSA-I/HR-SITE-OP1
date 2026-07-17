<?php
/**
 * The mini card — the product card, compressed.
 *
 * Same multiply plate, same mono spec line, same rules. That consistency is what makes
 * the feature feel native rather than bolted on.
 *
 * It is also why by-light.php renders through this file instead of hand-rolling a block.
 * cart.js:90 finds the product name for its "added to cart" toast via
 * closest('.product-card, .mini-card, .pdp, .buy-bar'). A bespoke block would quietly ship
 * a toast with no product name in it — and that toast is the only confirmation of the add
 * that a screen-reader user gets. It is a real accessibility regression that no manual
 * test catches, because the cart still works. Rendering through this file means cart.js
 * needs no knowledge of the section at all.
 *
 * The popover branch went with Shop the Space, and nothing else consumed it. The card is
 * now inline and permanent — which is also what lets the CTA stay a real, operable control
 * at every hour of the day instead of materialising at nightfall, where it would have been
 * a textbook hidden-but-focusable bug.
 *
 * @package hrdesign
 *
 * @var array $args product, variant
 */

defined( 'ABSPATH' ) || exit;

$product = $args['product'];
$variant = $args['variant'] ?? 'inline';
?>

<div class="mini-card mini-card--<?php echo esc_attr( $variant ); ?>">
	<div class="mini-card__body">
		<?php
		/*
		 * The plate is a decorative duplicate of the name link directly beside it, so it
		 * is hidden from assistive tech entirely rather than announced as a nameless
		 * link. tabindex="-1" alone only removes it from the tab order — a screen reader
		 * would still meet it in browse mode.
		 */
		?>
		<?php if ( $product['thumb'] ) : ?>
			<a class="mini-card__plate is-<?php echo esc_attr( $product['photo_type'] ); ?>" href="<?php echo esc_url( $product['permalink'] ); ?>" tabindex="-1" aria-hidden="true">
				<img src="<?php echo esc_url( $product['thumb'] ); ?>" alt="" loading="lazy" width="88" height="88">
			</a>
		<?php endif; ?>

		<div class="mini-card__text">
			<h3 class="mini-card__name t-editorial">
				<a href="<?php echo esc_url( $product['permalink'] ); ?>"><?php echo esc_html( $product['name'] ); ?></a>
			</h3>
			<p class="mini-card__price"><?php echo wp_kses_post( $product['price_html'] ); ?></p>

			<?php if ( $product['sku'] || $product['dims'] ) : ?>
				<p class="mini-card__spec t-mono">
					<?php if ( $product['sku'] ) : ?>
						<bdi><?php echo esc_html( $product['sku'] ); ?></bdi>
					<?php endif; ?>
					<?php if ( $product['sku'] && $product['dims'] ) : ?>
						<span aria-hidden="true"> · </span>
					<?php endif; ?>
					<?php if ( $product['dims'] ) : ?>
						<bdi><?php echo $product['dims']['estimated'] ? '~' : ''; ?><?php echo esc_html( implode( '×', array_map( fn( $d ) => rtrim( rtrim( number_format( $d, 1 ), '0' ), '.' ), $product['dims']['dims'] ) ) ); ?></bdi>
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( $product['variable'] ) : ?>
		<?php // Load-bearing: a quick-add here would silently add the wrong variant. ?>
		<a class="btn btn--primary mini-card__cta" href="<?php echo esc_url( $product['permalink'] ); ?>">
			<?php esc_html_e( 'בחר אפשרויות', 'hrdesign' ); ?>
		</a>
	<?php elseif ( ! empty( $product['purchasable'] ) && ! empty( $product['in_stock'] ) ) : ?>
		<?php
		/*
		 * Both keys are read through empty() rather than directly, and that is the whole
		 * point: a payload that omits either one degrades to the "לפרטים" link below.
		 * Fail-closed is the only safe default — a missing key means we do not know the
		 * product can be bought, and a link that should have been a quick-add is a much
		 * lesser bug than a quick-add on something with no agreed price. Three products
		 * in this catalogue are in stock and still not purchasable; see
		 * hrd_is_price_on_request().
		 */
		?>
		<button type="button" class="btn btn--primary mini-card__cta" data-add-to-cart="<?php echo esc_attr( $product['id'] ); ?>">
			<span class="btn__label"><?php esc_html_e( 'הוספה לסל', 'hrdesign' ); ?></span>
			<span class="btn__done" aria-hidden="true"><?php esc_html_e( 'נוסף לסל', 'hrdesign' ); ?></span>
		</button>
	<?php else : ?>
		<a class="btn mini-card__cta" href="<?php echo esc_url( $product['permalink'] ); ?>">
			<?php esc_html_e( 'לפרטים', 'hrdesign' ); ?>
		</a>
	<?php endif; ?>

	<?php
	/*
	 * Was a literal <span class="icon--directional">←</span>, which was a live bug: the
	 * glyph already points left, and [dir='rtl'] .icon--directional applies scaleX(-1) on
	 * top, so it rendered as → and pointed away from the link's own target. hrd_icon()
	 * authors the arrow pointing right and lets the mirror make it correct.
	 */
	?>
	<a class="mini-card__link" href="<?php echo esc_url( $product['permalink'] ); ?>">
		<?php esc_html_e( 'לעמוד המוצר', 'hrdesign' ); ?>
		<?php hrd_icon( 'arrow', array( 'size' => 16 ) ); ?>
	</a>
</div>
