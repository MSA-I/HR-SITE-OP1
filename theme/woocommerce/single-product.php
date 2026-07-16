<?php
/**
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="main" class="site-main">
	<section class="section section--pdp ground--cream">
		<div class="grid">
			<div class="section__rail">
				<span class="section__index"><?php esc_html_e( 'מוצר', 'hrdesign' ); ?></span>
			</div>

			<div class="section__body">
				<?php woocommerce_breadcrumb(); ?>

				<?php while ( have_posts() ) : ?>
					<?php the_post(); ?>
					<?php wc_get_template_part( 'content', 'single-product' ); ?>
				<?php endwhile; ?>
			</div>
		</div>
	</section>
</main>

<?php
get_footer();
