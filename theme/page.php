<?php
/**
 * Every WordPress page: אודות, צור קשר, the policy — and WooCommerce's cart, checkout
 * and account, which are ordinary pages whose whole body is a block or a shortcode.
 *
 * This file existing at all is the fix. Without it the hierarchy fell through to
 * index.php, which is an ARCHIVE loop: it printed a linked <h2> and called
 * the_excerpt(). Excerpts strip shortcodes and blocks, so /cart/ rendered its own title
 * and silently dropped the cart — the store accepted add-to-cart and had nothing after
 * it. /checkout/ and /my-account/ went the same way. the_content() below is the whole
 * repair; the container around it is why the text no longer runs off both edges.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="main" class="site-main">
	<section class="section ground--cream">
		<div class="grid">
			<?php
			/*
			 * The rail carries the hairline but no index. Every index on this site names a
			 * section of the homepage's argument ("02 / קולקציה") or a body of content
			 * ("קטלוג"); a standalone page is neither, and repeating its own title beside
			 * its own <h1> would be the same words twice, once rotated. The hairline keeps
			 * the column rhythm the shop and the homepage set.
			 */
			?>
			<div class="section__rail" aria-hidden="true"></div>

			<div class="section__body">
				<?php while ( have_posts() ) : ?>
					<?php the_post(); ?>

					<article <?php post_class( 'entry' ); ?>>
						<h1 class="t-display t-display--s entry__title"><?php the_title(); ?></h1>

						<div class="entry__content">
							<?php the_content(); ?>
						</div>
					</article>
				<?php endwhile; ?>
			</div>
		</div>
	</section>
</main>

<?php
get_footer();
