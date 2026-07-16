<?php
/**
 * 06 — Shop by room.
 *
 * Four arch-topped portals. This is the only radius in the entire system.
 *
 * The rooms map onto real categories rather than a pa_room attribute nobody has filled
 * in: a link that lands on an empty archive is worse than no link.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

$rooms = array(
	__( 'סלון', 'hrdesign' )      => array( 'ספות', 'כורסאות', 'שולחנות-סלון' ),
	__( 'חדר שינה', 'hrdesign' )  => array( 'מיטות-ושידות-צד', 'מיטות' ),
	__( 'פינת אוכל', 'hrdesign' ) => array( 'פינות-אוכל', 'כיסאות-וכיסאות-בר' ),
	__( 'מרפסת', 'hrdesign' )     => array( 'ריהוט-גן' ),
);

$portals = array();

foreach ( $rooms as $label => $slugs ) {
	foreach ( $slugs as $slug ) {
		$term = get_term_by( 'slug', $slug, 'product_cat' );
		if ( ! $term || is_wp_error( $term ) || ! $term->count ) {
			continue;
		}

		$thumbnail_id = get_term_meta( $term->term_id, 'thumbnail_id', true );
		$image = $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, 'woocommerce_thumbnail' ) : '';

		if ( ! $image ) {
			$found = get_posts(
				array(
					'post_type'      => 'product',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'tax_query'      => array( array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $term->term_id ) ),
				)
			);
			$image = $found ? get_the_post_thumbnail_url( $found[0], 'woocommerce_thumbnail' ) : '';
		}

		$portals[ $label ] = array( 'term' => $term, 'image' => $image );
		break;
	}
}

if ( count( $portals ) < 2 ) {
	return;
}
?>

<section class="section ground--cream-alt">
	<div class="grid">
		<div class="section__rail">
			<span class="section__index">04 / <?php esc_html_e( 'חללים', 'hrdesign' ); ?></span>
		</div>

		<div class="section__body">
			<header class="section__head stagger">
				<h2 class="t-display t-display--s" style="--i: 0"><?php esc_html_e( 'לפי חלל', 'hrdesign' ); ?></h2>
			</header>

			<ul class="portals" role="list">
				<?php foreach ( $portals as $label => $portal ) : ?>
					<li>
						<a class="portal reveal" href="<?php echo esc_url( get_term_link( $portal['term'] ) ); ?>">
							<span class="portal__frame">
								<span class="reveal__curtain" aria-hidden="true"></span>
								<?php if ( $portal['image'] ) : ?>
									<img src="<?php echo esc_url( $portal['image'] ); ?>" alt="" loading="lazy" width="400" height="533">
								<?php endif; ?>
							</span>
							<span class="portal__label"><?php echo esc_html( $label ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
</section>
