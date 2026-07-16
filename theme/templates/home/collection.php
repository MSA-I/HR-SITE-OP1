<?php
/**
 * 04 — the horizontal section. One collection, presented as an editorial spread.
 *
 * Native `overflow-inline: auto`, NOT a pinned vertical-to-horizontal translate. This is
 * the most important call in the layout: GSAP-pinned horizontal sections ARE scroll
 * hijacking — banned by the brief — and they break trackpads, keyboards and
 * find-in-page. Native overflow gets shift+wheel, trackpad, swipe, keyboard and screen
 * reader linearity for free.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

$term_id = (int) get_option( 'hrd_featured_collection' );
$term = $term_id ? get_term( $term_id, 'product_cat' ) : null;

if ( ! $term || is_wp_error( $term ) ) {
	$tree = hrd_category_tree();
	$term = $tree ? $tree[0]['term'] : null;
}

if ( ! $term ) {
	return;
}

$products = wc_get_products(
	array(
		'limit'    => 5,
		'category' => array( $term->slug ),
		'orderby'  => 'popularity',
		'status'   => 'publish',
	)
);

if ( ! $products ) {
	return;
}

$accent = get_term_meta( $term->term_id, 'hrd_accent', true ) ?: 'natural-forms';
?>

<section class="section collection ground--accent collection--<?php echo esc_attr( $accent ); ?> bleed">
	<div class="collection__rail" aria-hidden="true">
		<span class="section__index">02 / <?php esc_html_e( 'קולקציה', 'hrdesign' ); ?></span>
	</div>

	<?php // scroll-snap is PROXIMITY, not mandatory: mandatory fights the user's finger. ?>
	<div class="collection__track" data-collection-track tabindex="0" role="region"
		aria-label="<?php echo esc_attr( sprintf( __( 'קולקציית %s', 'hrdesign' ), $term->name ) ); ?>">

		<div class="collection__frame collection__frame--title">
			<p class="t-mono collection__eyebrow"><?php esc_html_e( 'קולקציה במוקד', 'hrdesign' ); ?></p>
			<h2 class="t-display t-display--l"><?php echo esc_html( $term->name ); ?></h2>
			<?php if ( $term->description ) : ?>
				<p class="collection__story"><?php echo esc_html( wp_trim_words( $term->description, 24 ) ); ?></p>
			<?php endif; ?>
			<a class="btn" href="<?php echo esc_url( get_term_link( $term ) ); ?>">
				<?php esc_html_e( 'לכל הקולקציה', 'hrdesign' ); ?>
			</a>
		</div>

		<?php foreach ( $products as $product ) : ?>
			<?php $photo = hrd_photo_type( $product ); ?>
			<article class="collection__frame collection__frame--product">
				<a class="collection__plate is-<?php echo esc_attr( $photo ); ?>" href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>">
					<?php echo $product->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy' ) ); ?>
				</a>
				<h3 class="collection__name t-editorial"><?php echo esc_html( $product->get_name() ); ?></h3>
				<p class="collection__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></p>
			</article>
		<?php endforeach; ?>

		<div class="collection__frame collection__frame--end">
			<a class="collection__end-link t-display t-display--s" href="<?php echo esc_url( get_term_link( $term ) ); ?>">
				<?php esc_html_e( 'לכל הקולקציה', 'hrdesign' ); ?>
				<span class="icon--directional" aria-hidden="true">←</span>
			</a>
		</div>
	</div>

	<div class="collection__progress" aria-hidden="true">
		<span class="collection__progress-fill" data-collection-progress></span>
	</div>
</section>
