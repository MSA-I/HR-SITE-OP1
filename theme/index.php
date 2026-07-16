<?php
/**
 * Fallback template. Every template hierarchy path lands here if nothing more specific matches.
 *
 * @package hrdesign
 */

get_header();
?>

<main id="main" class="site-main">
	<?php if ( have_posts() ) : ?>
		<?php while ( have_posts() ) : ?>
			<?php the_post(); ?>
			<article <?php post_class(); ?>>
				<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
				<?php the_excerpt(); ?>
			</article>
		<?php endwhile; ?>
		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<p><?php esc_html_e( 'לא נמצא תוכן.', 'hrdesign' ); ?></p>
	<?php endif; ?>
</main>

<?php
get_footer();
