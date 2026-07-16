<?php
/**
 * 07 — Inspiration. Editorial: one large plus two stacked, text overlapping by a column.
 *
 * The image links are decorative duplicates of the heading links beside them, so they
 * are hidden from assistive tech. A nameless link announces as just "link", and there
 * are three of them per section.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

$posts = get_posts( array( 'posts_per_page' => 3 ) );
if ( ! $posts ) {
	return;
}
?>

<section class="section ground--cream">
	<div class="grid">
		<div class="section__rail">
			<span class="section__index">05 / <?php esc_html_e( 'השראה', 'hrdesign' ); ?></span>
		</div>

		<div class="section__body">
			<header class="section__head stagger">
				<h2 class="t-display t-display--s" style="--i: 0"><?php esc_html_e( 'השראה', 'hrdesign' ); ?></h2>
			</header>

			<div class="inspiration">
				<?php $lead = array_shift( $posts ); ?>
				<article class="inspiration__lead">
					<a class="inspiration__media reveal" href="<?php echo esc_url( get_permalink( $lead ) ); ?>" tabindex="-1" aria-hidden="true">
						<span class="reveal__curtain" aria-hidden="true"></span>
						<?php if ( has_post_thumbnail( $lead ) ) : ?>
							<?php echo get_the_post_thumbnail( $lead, 'large', array( 'loading' => 'lazy', 'alt' => '' ) ); ?>
						<?php endif; ?>
					</a>
					<div class="inspiration__text">
						<h3 class="t-editorial">
							<a href="<?php echo esc_url( get_permalink( $lead ) ); ?>"><?php echo esc_html( get_the_title( $lead ) ); ?></a>
						</h3>
						<p><?php echo esc_html( wp_trim_words( get_the_excerpt( $lead ), 18 ) ); ?></p>
					</div>
				</article>

				<div class="inspiration__side">
					<?php foreach ( $posts as $item ) : ?>
						<article class="inspiration__item">
							<a class="inspiration__media reveal" href="<?php echo esc_url( get_permalink( $item ) ); ?>" tabindex="-1" aria-hidden="true">
								<span class="reveal__curtain" aria-hidden="true"></span>
								<?php if ( has_post_thumbnail( $item ) ) : ?>
									<?php echo get_the_post_thumbnail( $item, 'medium', array( 'loading' => 'lazy', 'alt' => '' ) ); ?>
								<?php endif; ?>
							</a>
							<h3 class="t-editorial">
								<a href="<?php echo esc_url( get_permalink( $item ) ); ?>"><?php echo esc_html( get_the_title( $item ) ); ?></a>
							</h3>
						</article>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</div>
</section>
