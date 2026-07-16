<?php
/**
 * 05 — New / bestsellers.
 *
 * Card #2 is offset 96px down. One card. Not a masonry, not randomised.
 *
 * Both tabs sort on real signals recovered from the seed: recency from the live store's
 * sequential post ids, popularity from the order the Store API returned. No invented
 * dates, no invented sales figures.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

$new = wc_get_products(
	array(
		'limit'    => 4,
		'status'   => 'publish',
		'meta_key' => '_hrd_src_id',
		'orderby'  => 'meta_value_num',
		'order'    => 'DESC',
	)
);

$best = wc_get_products(
	array(
		'limit'   => 4,
		'status'  => 'publish',
		'orderby' => 'popularity',
	)
);

if ( ! $new && ! $best ) {
	return;
}
?>

<section class="section ground--cream" data-tabs>
	<div class="grid">
		<div class="section__rail">
			<span class="section__index">03 / <?php esc_html_e( 'חדשים', 'hrdesign' ); ?></span>
		</div>

		<div class="section__body">
			<header class="section__head section__head--tabs stagger">
				<h2 class="t-display t-display--s" style="--i: 0"><?php esc_html_e( 'מה שווה לראות', 'hrdesign' ); ?></h2>

				<div class="tabs" role="tablist" style="--i: 1">
					<button type="button" class="tab is-active" role="tab" aria-selected="true" aria-controls="panel-new" id="tab-new">
						<?php esc_html_e( 'חדש באתר', 'hrdesign' ); ?>
					</button>
					<button type="button" class="tab" role="tab" aria-selected="false" aria-controls="panel-best" id="tab-best">
						<?php esc_html_e( 'הנמכרים ביותר', 'hrdesign' ); ?>
					</button>
				</div>
			</header>

			<?php
			foreach ( array( 'new' => $new, 'best' => $best ) as $key => $set ) :
				if ( ! $set ) {
					continue;
				}
				?>
				<div class="tab-panel<?php echo 'new' === $key ? ' is-active' : ''; ?>" id="panel-<?php echo esc_attr( $key ); ?>"
					role="tabpanel" aria-labelledby="tab-<?php echo esc_attr( $key ); ?>" <?php echo 'new' === $key ? '' : 'hidden'; ?>>
					<ul class="products products--offset" role="list">
						<?php
						global $product, $post;
						foreach ( $set as $item ) {
							$post = get_post( $item->get_id() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
							setup_postdata( $post );
							$product = $item; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
							wc_get_template_part( 'content', 'product' );
						}
						wp_reset_postdata();
						?>
					</ul>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
