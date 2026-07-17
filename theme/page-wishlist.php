<?php
/**
 * The wishlist page.
 *
 * The list lives in localStorage (src/js/modules/wishlist.js) — no account, no plugin, no
 * server-side list. So the ids arrive from the browser and this renders them.
 *
 * Why the ids come here rather than the browser rendering Store API JSON: the card IS
 * content-product.php, and that template is 180 lines of degradation handling — the
 * studio/scene plate treatment, the badge, the hover image, the colour swatches, the
 * dimensions, and four different quick actions depending on whether the product is
 * variable, purchasable, in stock or price-on-request. The Store API returns none of it.
 * A JS lookalike would drift from the catalogue's cards the first time either changed,
 * and a wishlist whose cards do not match the shop's IS the "half the work was done"
 * complaint in miniature. Rendering the real template cannot drift.
 *
 * ?partial=1 returns just the list for wishlist.js to fetch and inject. Nothing here is
 * privileged: the ids are public product ids and the markup is what the shop already
 * serves to anyone. Ids are cast with absint() and capped, so the query is bounded no
 * matter what arrives.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

/*
 * No nonce: this reads public catalogue data with no side effects, exactly as ?s= and the
 * filter params already do. A nonce here would only break the page for a shared link.
 */
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$hrd_raw     = isset( $_GET['ids'] ) ? sanitize_text_field( wp_unslash( $_GET['ids'] ) ) : '';
$hrd_partial = isset( $_GET['partial'] );
// phpcs:enable WordPress.Security.NonceVerification.Recommended

// Cap at 60: a wishlist is a shortlist, and this bounds the query against a hand-typed URL.
$hrd_ids = $hrd_raw ? array_slice( array_unique( array_filter( array_map( 'absint', explode( ',', $hrd_raw ) ) ) ), 0, 60 ) : array();

$hrd_query = $hrd_ids ? new WP_Query(
	array(
		'post_type'           => 'product',
		'post__in'            => $hrd_ids,
		// The order the customer saved them in, not the order WordPress feels like.
		'orderby'             => 'post__in',
		'posts_per_page'      => count( $hrd_ids ),
		'post_status'         => 'publish',
		'ignore_sticky_posts' => true,
	)
) : null;

if ( $hrd_partial ) {
	if ( $hrd_query && $hrd_query->have_posts() ) {
		echo '<ul class="products">';
		while ( $hrd_query->have_posts() ) {
			$hrd_query->the_post();
			// wc_setup_product_data is hooked to `the_post`, so global $product is live here.
			wc_get_template_part( 'content', 'product' );
		}
		echo '</ul>';
	}
	wp_reset_postdata();
	exit;
}

// Guarded as templates/header/nav.php:14 does.
$hrd_shop_url = function_exists( 'wc_get_page_id' ) ? get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/' );

get_header();
?>

<main id="main" class="site-main">
	<section class="section ground--cream">
		<div class="grid">
			<div class="section__rail" aria-hidden="true"></div>

			<div class="section__body">
				<?php while ( have_posts() ) : ?>
					<?php the_post(); ?>
					<h1 class="t-display t-display--s entry__title"><?php the_title(); ?></h1>

					<?php // Empty by default. It exists so intro copy can be added without a code change. ?>
					<div class="entry__content"><?php the_content(); ?></div>
				<?php endwhile; ?>

				<?php
				/*
				 * Server-rendered empty state, replaced by JS when there is something to
				 * show. This way the page always says something: an empty wishlist that
				 * renders a void is the exact failure this whole pass is about.
				 */
				?>
				<div data-wishlist-list>
					<p class="entry__lede">
						<?php esc_html_e( 'עדיין לא שמרתם פריטים. הלב שבפינת כל כרטיס מוסיף אותו לכאן.', 'hrdesign' ); ?>
					</p>
					<p class="entry__back">
						<a class="btn" href="<?php echo esc_url( $hrd_shop_url ); ?>">
							<?php esc_html_e( 'לכל המוצרים', 'hrdesign' ); ?>
						</a>
					</p>
				</div>

				<noscript>
					<p class="entry__lede">
						<?php esc_html_e( 'רשימת המועדפים נשמרת בדפדפן שלכם, ולכן היא דורשת JavaScript.', 'hrdesign' ); ?>
					</p>
				</noscript>
			</div>
		</div>
	</section>
</main>

<?php
get_footer();
