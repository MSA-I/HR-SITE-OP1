<?php
/**
 * Header actions: search, wishlist, cart.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

$cart_count = function_exists( 'hrd_cart_count' ) ? hrd_cart_count() : 0;
?>

<div class="header-actions">
	<a class="header-action" href="<?php echo esc_url( home_url( '/?s=&post_type=product' ) ); ?>" aria-label="<?php esc_attr_e( 'חיפוש', 'hrdesign' ); ?>">
		<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
			<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
		</svg>
	</a>

	<a class="header-action" href="<?php echo esc_url( home_url( '/wishlist/' ) ); ?>" aria-label="<?php esc_attr_e( 'מועדפים', 'hrdesign' ); ?>">
		<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
			<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1L12 21l7.7-7.6 1.1-1a5.5 5.5 0 0 0 0-7.8z"/>
		</svg>
		<span class="header-action__count" data-wishlist-count hidden>0</span>
	</a>

	<a class="header-action" href="<?php echo esc_url( wc_get_cart_url() ); ?>" aria-label="<?php esc_attr_e( 'עגלת הקניות', 'hrdesign' ); ?>">
		<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
			<path d="M6 6h15l-1.5 9h-12z"/><path d="M6 6 5 2H2"/>
			<circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/>
		</svg>
		<span class="header-action__count" data-cart-count <?php echo $cart_count ? '' : 'hidden'; ?>><?php echo (int) $cart_count; ?></span>
	</a>
</div>
