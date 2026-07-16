<?php
/**
 * The filter rail.
 *
 * Plain links and a plain form: this works with JS disabled, which is the requirement.
 * JS only enhances it (phase: instant counts via the Store API).
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

$bounds = hrd_price_bounds();
// phpcs:disable WordPress.Security.NonceVerification -- read-only public filtering
$min_price = isset( $_GET['min_price'] ) ? (int) $_GET['min_price'] : $bounds['min'];
$max_price = isset( $_GET['max_price'] ) ? (int) $_GET['max_price'] : $bounds['max'];
$in_stock  = ! empty( $_GET['stock'] );
// phpcs:enable

$facets = array();
foreach ( hrd_filter_taxonomies() as $taxonomy => $label ) {
	$terms = hrd_facet_terms( $taxonomy );
	if ( $terms ) {
		$facets[ $taxonomy ] = array( 'label' => $label, 'terms' => $terms );
	}
}
?>

<aside class="filters" aria-label="<?php esc_attr_e( 'סינון מוצרים', 'hrdesign' ); ?>">
	<div class="filters__head">
		<h2 class="filters__title"><?php esc_html_e( 'סינון', 'hrdesign' ); ?></h2>
		<?php if ( hrd_has_active_filters() ) : ?>
			<a class="filters__clear" href="<?php echo esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ); ?>">
				<?php esc_html_e( 'ניקוי', 'hrdesign' ); ?>
			</a>
		<?php endif; ?>
	</div>

	<?php foreach ( $facets as $taxonomy => $facet ) : ?>
		<?php $active = hrd_active_filter( $taxonomy ); ?>
		<section class="filters__group">
			<h3 class="filters__label"><?php echo esc_html( $facet['label'] ); ?></h3>
			<ul class="filters__list">
				<?php foreach ( $facet['terms'] as $term ) : ?>
					<?php
					$is_active = in_array( $term->slug, $active, true );
					$hex       = get_term_meta( $term->term_id, 'hrd_hex', true );
					?>
					<li>
						<a
							class="filter-chip<?php echo $is_active ? ' is-active' : ''; ?>"
							href="<?php echo esc_url( hrd_filter_toggle_url( $taxonomy, $term->slug ) ); ?>"
							<?php echo $is_active ? 'aria-current="true"' : ''; ?>
							rel="nofollow"
						>
							<?php if ( $hex ) : ?>
								<span class="swatch" style="--swatch: <?php echo esc_attr( $hex ); ?>" aria-hidden="true"></span>
							<?php endif; ?>
							<span class="filter-chip__name"><?php echo esc_html( $term->name ); ?></span>
							<span class="filter-chip__count t-mono"><bdi><?php echo (int) $term->count; ?></bdi></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endforeach; ?>

	<form class="filters__group" method="get" action="<?php echo esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ); ?>">
		<?php
		// Carry the other active filters through this form, or submitting price would
		// silently drop them.
		foreach ( $_GET as $key => $value ) : // phpcs:ignore WordPress.Security.NonceVerification
			if ( in_array( $key, array( 'min_price', 'max_price', 'stock', 'paged' ), true ) ) {
				continue;
			}
			?>
			<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( is_array( $value ) ? '' : $value ); ?>">
		<?php endforeach; ?>

		<h3 class="filters__label"><?php esc_html_e( 'מחיר', 'hrdesign' ); ?></h3>
		<div class="filters__price">
			<label>
				<span class="visually-hidden"><?php esc_html_e( 'מחיר מינימלי', 'hrdesign' ); ?></span>
				<input type="number" name="min_price" inputmode="numeric" value="<?php echo esc_attr( $min_price ); ?>" min="<?php echo esc_attr( $bounds['min'] ); ?>" max="<?php echo esc_attr( $bounds['max'] ); ?>">
			</label>
			<span aria-hidden="true">—</span>
			<label>
				<span class="visually-hidden"><?php esc_html_e( 'מחיר מרבי', 'hrdesign' ); ?></span>
				<input type="number" name="max_price" inputmode="numeric" value="<?php echo esc_attr( $max_price ); ?>" min="<?php echo esc_attr( $bounds['min'] ); ?>" max="<?php echo esc_attr( $bounds['max'] ); ?>">
			</label>
		</div>

		<label class="filters__check">
			<input type="checkbox" name="stock" value="1" <?php checked( $in_stock ); ?>>
			<span><?php esc_html_e( 'במלאי בלבד', 'hrdesign' ); ?></span>
		</label>

		<button type="submit" class="btn filters__submit"><?php esc_html_e( 'החל', 'hrdesign' ); ?></button>
	</form>
</aside>
