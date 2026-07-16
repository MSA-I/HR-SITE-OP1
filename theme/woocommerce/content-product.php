<?php
/**
 * The product card.
 *
 * One of the few templates worth overriding outright — the DOM structure itself has to
 * change. Every degradation case in this catalogue converges here:
 *   - 35% have no second image  -> no hover affordance, static plate
 *   - 80% have no colour terms  -> the swatch row is absent, not empty
 *   - 48% have no dimensions    -> the spec line drops that half
 *   - 28% are variable          -> "בחר אפשרויות", never a silent quick-add
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product || ! $product->is_visible() ) {
	return;
}

$badges    = hrd_product_badges( $product );
$badge     = $badges ? $badges[0] : null;
$hover_id  = hrd_hover_image_id( $product );
$colours   = hrd_product_colours( $product );
$dims      = hrd_product_dims( $product );
$variable  = hrd_is_variable( $product );
$sku       = $product->get_sku();
$permalink = get_permalink( $product->get_id() );
$photo     = hrd_photo_type( $product );
?>

<li <?php wc_product_class( 'product-card is-' . $photo, $product ); ?>>
	<div class="product-card__plate<?php echo $hover_id ? ' has-hover' : ''; ?>">
		<a class="product-card__link" href="<?php echo esc_url( $permalink ); ?>" data-plate>
			<span class="visually-hidden"><?php echo esc_html( $product->get_name() ); ?></span>

			<?php
			// The tint overlay. It carries the hover colour change as an opacity
			// transition — never background-color, which cannot be composited.
			?>
			<span class="product-card__tint" aria-hidden="true"></span>

			<?php echo $product->get_image( 'woocommerce_thumbnail', array( 'class' => 'product-card__img' ) ); ?>

			<?php if ( $hover_id ) : ?>
				<?php
				echo wp_get_attachment_image(
					$hover_id,
					'woocommerce_thumbnail',
					false,
					array(
						'class'   => 'product-card__img product-card__img--hover',
						'loading' => 'lazy',
						'alt'     => '',
					)
				);
				?>
			<?php endif; ?>
		</a>

		<?php if ( $badge ) : ?>
			<span class="badge badge--<?php echo esc_attr( $badge['variant'] ); ?>">
				<?php echo esc_html( $badge['label'] ); ?>
			</span>
		<?php endif; ?>

		<button
			type="button"
			class="product-card__fav"
			data-wishlist="<?php echo esc_attr( $product->get_id() ); ?>"
			aria-pressed="false"
			aria-label="<?php echo esc_attr( sprintf( __( 'הוספת %s למועדפים', 'hrdesign' ), $product->get_name() ) ); ?>"
		>
			<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
				<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1L12 21l7.7-7.6 1.1-1a5.5 5.5 0 0 0 0-7.8z"/>
			</svg>
		</button>

		<?php
		/*
		 * Quick action sits INSIDE the plate, so revealing it never changes the card's
		 * height and never moves the card. On coarse pointers CSS keeps it permanently
		 * visible — hover must never be the only route to a purchase action.
		 */
		?>
		<div class="product-card__quick">
			<?php if ( $variable ) : ?>
				<a class="btn btn--quick" href="<?php echo esc_url( $permalink ); ?>">
					<?php esc_html_e( 'בחר אפשרויות', 'hrdesign' ); ?>
				</a>
			<?php elseif ( $product->is_in_stock() ) : ?>
				<?php
				/*
				 * A real link, not a <button>.
				 *
				 * ?add-to-cart=ID is WooCommerce's own no-JS path: it adds the item and
				 * redirects back. JS intercepts the click and uses the Store API instead,
				 * so nothing reloads. As a bare <button> this was dead without JS — the
				 * one interaction on the card that does the actual selling.
				 */
				?>
				<a
					class="btn btn--quick"
					href="<?php echo esc_url( add_query_arg( 'add-to-cart', $product->get_id(), $permalink ) ); ?>"
					data-add-to-cart="<?php echo esc_attr( $product->get_id() ); ?>"
					rel="nofollow"
				>
					<span class="btn__label"><?php esc_html_e( 'הוספה מהירה לסל', 'hrdesign' ); ?></span>
					<span class="btn__done" aria-hidden="true"><?php esc_html_e( 'נוסף לסל', 'hrdesign' ); ?></span>
				</a>
			<?php else : ?>
				<a class="btn btn--quick btn--muted" href="<?php echo esc_url( $permalink ); ?>">
					<?php esc_html_e( 'לפרטים', 'hrdesign' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>

	<div class="product-card__body">
		<h3 class="product-card__name t-editorial">
			<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
		</h3>

		<p class="product-card__price">
			<?php
			// WooCommerce wraps the amount in <bdi>, which is what keeps ₪ on the correct
			// side of the number. wp_kses_post() would strip it — <bdi> is missing from
			// WordPress's allowed tags — so inc/bidi.php allows it back. Without that
			// filter this line silently mangles every price on the site.
			echo wp_kses_post( $product->get_price_html() );
			?>
		</p>

		<?php if ( $colours ) : ?>
			<ul class="swatches" aria-label="<?php esc_attr_e( 'צבעים זמינים', 'hrdesign' ); ?>">
				<?php foreach ( array_slice( $colours, 0, 5 ) as $colour ) : ?>
					<li>
						<?php if ( $colour['hex'] ) : ?>
							<span
								class="swatch"
								style="--swatch: <?php echo esc_attr( $colour['hex'] ); ?>"
								title="<?php echo esc_attr( $colour['name'] ); ?>"
							></span>
							<span class="visually-hidden"><?php echo esc_html( $colour['name'] ); ?></span>
						<?php else : ?>
							<span class="swatch swatch--text"><?php echo esc_html( $colour['name'] ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
				<?php if ( count( $colours ) > 5 ) : ?>
					<li class="swatch--more t-mono">+<?php echo (int) ( count( $colours ) - 5 ); ?></li>
				<?php endif; ?>
			</ul>
		<?php endif; ?>

		<?php if ( $sku || $dims ) : ?>
			<p class="product-card__spec t-mono">
				<?php if ( $sku ) : ?>
					<bdi><?php echo esc_html( $sku ); ?></bdi>
				<?php endif; ?>

				<?php if ( $sku && $dims ) : ?>
					<span aria-hidden="true"> · </span>
				<?php endif; ?>

				<?php if ( $dims ) : ?>
					<bdi<?php echo $dims['estimated'] ? ' class="is-estimated" title="' . esc_attr__( 'מידות משוערות', 'hrdesign' ) . '"' : ''; ?>>
						<?php echo $dims['estimated'] ? '~' : ''; ?><?php echo esc_html( implode( '×', array_map( fn( $d ) => rtrim( rtrim( number_format( $d, 1 ), '0' ), '.' ), $dims['dims'] ) ) ); ?>
					</bdi>
					<?php if ( $dims['estimated'] ) : ?>
						<span class="visually-hidden"><?php esc_html_e( 'מידות משוערות', 'hrdesign' ); ?></span>
					<?php endif; ?>
				<?php endif; ?>
			</p>
		<?php endif; ?>
	</div>
</li>
