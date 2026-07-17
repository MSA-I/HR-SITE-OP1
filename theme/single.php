<?php
/**
 * A single post. In practice these are the inspiration articles that section 05 of the
 * homepage links to, which is why the rail says השראה: arriving here IS that section
 * continued, and the index is the one place that can say so.
 *
 * Same repair as page.php — the_content(), and a container so the text stops running off
 * both edges. These three cards were live on the homepage and every one of them landed
 * on the archive stub.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

// Guarded exactly as templates/header/nav.php:14 does: WooCommerce is a plugin, and a
// content template has no business fataling when it is switched off.
$shop_url = function_exists( 'wc_get_page_id' ) ? get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/' );

get_header();
?>

<main id="main" class="site-main">
	<section class="section ground--cream">
		<div class="grid">
			<div class="section__rail">
				<span class="section__index"><?php esc_html_e( 'השראה', 'hrdesign' ); ?></span>
			</div>

			<div class="section__body">
				<?php while ( have_posts() ) : ?>
					<?php the_post(); ?>

					<article <?php post_class( 'entry' ); ?>>
						<h1 class="t-display t-display--s entry__title"><?php the_title(); ?></h1>

						<?php if ( has_post_thumbnail() ) : ?>
							<figure class="entry__media">
								<?php the_post_thumbnail( 'large', array( 'alt' => '' ) ); ?>
							</figure>
						<?php endif; ?>

						<div class="entry__content">
							<?php the_content(); ?>
						</div>
					</article>
				<?php endwhile; ?>

				<?php
				/*
				 * A post is a dead end otherwise: there is no blog index to go back to, and
				 * the reader arrived from the homepage to be sold furniture.
				 */
				?>
				<p class="entry__back">
					<a class="btn" href="<?php echo esc_url( $shop_url ); ?>">
						<?php esc_html_e( 'לכל המוצרים', 'hrdesign' ); ?>
					</a>
				</p>
			</div>
		</div>
	</section>
</main>

<?php
get_footer();
