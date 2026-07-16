<?php
/**
 * The mini card — the product card, compressed.
 *
 * Same multiply plate, same mono spec line, same rules. That consistency is what makes
 * the feature feel native rather than bolted on.
 *
 * No colour swatches: there are no variants, and lying inside the flagship feature of
 * the site is how you lose the argument.
 *
 * @package hrdesign
 *
 * @var array $args spot
 */

defined( 'ABSPATH' ) || exit;

$spot = $args['spot'];
$product = $spot['product'];
?>

<div class="mini-card" id="card-<?php echo esc_attr( $spot['id'] ); ?>" popover data-mini-card="<?php echo esc_attr( $spot['id'] ); ?>">
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
	<?php elseif ( $product['in_stock'] ) : ?>
		<button type="button" class="btn btn--primary mini-card__cta" data-add-to-cart="<?php echo esc_attr( $product['id'] ); ?>">
			<span class="btn__label"><?php esc_html_e( 'הוספה לסל', 'hrdesign' ); ?></span>
			<span class="btn__done" aria-hidden="true"><?php esc_html_e( 'נוסף לסל', 'hrdesign' ); ?></span>
		</button>
	<?php else : ?>
		<a class="btn mini-card__cta" href="<?php echo esc_url( $product['permalink'] ); ?>">
			<?php esc_html_e( 'לפרטים', 'hrdesign' ); ?>
		</a>
	<?php endif; ?>

	<a class="mini-card__link" href="<?php echo esc_url( $product['permalink'] ); ?>">
		<?php esc_html_e( 'לעמוד המוצר', 'hrdesign' ); ?>
		<span class="icon--directional" aria-hidden="true">←</span>
	</a>
</div>
