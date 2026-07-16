<?php
/**
 * Shop / category archive.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="main" class="site-main">
	<section class="section ground--cream">
		<div class="grid">
			<div class="section__rail">
				<span class="section__index"><?php esc_html_e( 'קטלוג', 'hrdesign' ); ?></span>
			</div>

			<div class="section__body">
				<header class="archive-head stagger">
					<?php woocommerce_breadcrumb(); ?>

					<h1 class="t-display t-display--s" style="--i: 1">
						<?php woocommerce_page_title(); ?>
					</h1>

					<div class="archive-head__meta" style="--i: 2">
						<?php woocommerce_result_count(); ?>
						<?php woocommerce_catalog_ordering(); ?>
					</div>
				</header>

				<div class="archive-layout">
					<?php get_template_part( 'templates/parts/filters' ); ?>

					<div class="archive-results">
						<?php if ( woocommerce_product_loop() ) : ?>
							<?php woocommerce_product_loop_start(); ?>

							<?php
							while ( have_posts() ) {
								the_post();
								wc_get_template_part( 'content', 'product' );
							}
							?>

							<?php woocommerce_product_loop_end(); ?>
							<?php woocommerce_pagination(); ?>
						<?php else : ?>
							<p class="archive-empty">
								<?php esc_html_e( 'לא נמצאו מוצרים התואמים לסינון.', 'hrdesign' ); ?>
								<a href="<?php echo esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ); ?>"><?php esc_html_e( 'ניקוי הסינון', 'hrdesign' ); ?></a>
							</p>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</section>
</main>

<?php
get_footer();
