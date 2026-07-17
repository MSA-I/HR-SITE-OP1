<?php
/**
 * The archive fallback: blog home, category, tag, date and search results.
 *
 * It is no longer a singular template, and that is what makes the loop below correct
 * rather than broken. A linked <h2> per item and the_excerpt() are archive markup: they
 * only ever read as bugs because the hierarchy was also handing this file /about/,
 * /cart/ and every post, with no page.php or single.php to catch them. One file was
 * doing two jobs and could only be right about one. It now does the archive job.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

// Guarded exactly as templates/header/nav.php:14 does.
$shop_url = function_exists( 'wc_get_page_id' ) ? get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/' );

get_header();
?>

<main id="main" class="site-main">
	<section class="section ground--cream">
		<div class="grid">
			<div class="section__rail" aria-hidden="true"></div>

			<div class="section__body">
				<h1 class="t-display t-display--s entry__title">
					<?php
					if ( is_search() ) {
						printf(
							/* translators: %s: the search term */
							esc_html__( 'תוצאות חיפוש: %s', 'hrdesign' ),
							esc_html( get_search_query() )
						);
					} elseif ( is_archive() ) {
						/*
						 * No <bdi> around the title or the query. typography.css isolates
						 * bdi to LTR for numbers and units; a Hebrew string forced LTR is
						 * the exact scrambling that rule exists to prevent. A mixed run is
						 * the bidi algorithm's job, and it gets them right unaided.
						 */
						the_archive_title();
					} else {
						esc_html_e( 'מאמרים', 'hrdesign' );
					}
					?>
				</h1>

				<?php if ( have_posts() ) : ?>
					<div class="entry-list">
						<?php while ( have_posts() ) : ?>
							<?php the_post(); ?>
							<article <?php post_class( 'entry-list__item' ); ?>>
								<h2 class="t-editorial entry-list__title">
									<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
								</h2>
								<?php the_excerpt(); ?>
							</article>
						<?php endwhile; ?>
					</div>

					<?php the_posts_pagination(); ?>

				<?php else : ?>
					<p class="entry__lede"><?php esc_html_e( 'לא נמצא תוכן שתואם את החיפוש.', 'hrdesign' ); ?></p>

					<p class="entry__back">
						<a class="btn" href="<?php echo esc_url( $shop_url ); ?>">
							<?php esc_html_e( 'לכל המוצרים', 'hrdesign' ); ?>
						</a>
					</p>
				<?php endif; ?>
			</div>
		</div>
	</section>
</main>

<?php
get_footer();
