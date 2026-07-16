<?php
/**
 * 02 — Main categories.
 *
 * Five tiles, deliberately not equal: a 3-2 split where the first is two columns taller.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

$tree = hrd_category_tree();
if ( ! $tree ) {
	return;
}

$tiles = array_slice( $tree, 0, 5 );
?>

<section class="section ground--cream">
	<div class="grid">
		<div class="section__rail">
			<span class="section__index">01 / <?php esc_html_e( 'קטגוריות', 'hrdesign' ); ?></span>
		</div>

		<div class="section__body">
			<header class="section__head stagger">
				<h2 class="t-display t-display--s" style="--i: 0"><?php esc_html_e( 'לאן הולכים', 'hrdesign' ); ?></h2>
			</header>

			<ul class="cat-tiles" role="list">
				<?php foreach ( $tiles as $i => $node ) : ?>
					<li class="cat-tile<?php echo 0 === $i ? ' cat-tile--lead' : ''; ?>">
						<a class="cat-tile__link reveal" href="<?php echo esc_url( get_term_link( $node['term'] ) ); ?>">
							<span class="reveal__curtain" aria-hidden="true"></span>
							<?php if ( $node['image'] ) : ?>
								<img src="<?php echo esc_url( $node['image'] ); ?>" alt="" loading="lazy" width="600" height="600">
							<?php endif; ?>
							<span class="cat-tile__label">
								<?php echo esc_html( $node['term']->name ); ?>
								<span class="cat-tile__count t-mono"><bdi><?php echo (int) $node['term']->count; ?></bdi></span>
							</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
</section>
