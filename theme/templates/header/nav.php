<?php
/**
 * Primary navigation and the mega menu.
 *
 * The panel is real markup with real links — crawlable, and it works with JS off (CSS
 * :hover opens it). JS only adds hover-intent, keyboard control and the sliding rule.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

$tree = function_exists( 'hrd_category_tree' ) ? hrd_category_tree() : array();
$shop_url = function_exists( 'wc_get_page_id' ) ? get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/' );
?>

<nav class="site-nav" aria-label="<?php esc_attr_e( 'ניווט ראשי', 'hrdesign' ); ?>">
	<ul class="site-nav__list">
		<?php if ( $tree ) : ?>
			<li class="site-nav__item has-mega" data-mega-root>
				<button type="button" class="site-nav__link" aria-expanded="false" aria-controls="mega-shop" data-mega-trigger>
					<?php esc_html_e( 'חנות', 'hrdesign' ); ?>
				</button>

				<div class="mega" id="mega-shop" data-mega-panel hidden>
					<div class="mega__inner">
						<?php // Col A — top-level categories. Hovering or focusing one swaps B and C. ?>
						<ul class="mega__cats" role="list">
							<?php foreach ( $tree as $i => $node ) : ?>
								<li>
									<a
										class="mega__cat<?php echo 0 === $i ? ' is-active' : ''; ?>"
										href="<?php echo esc_url( get_term_link( $node['term'] ) ); ?>"
										data-mega-cat="<?php echo esc_attr( $i ); ?>"
									>
										<span><?php echo esc_html( $node['term']->name ); ?></span>
										<span class="mega__count t-mono"><bdi><?php echo (int) $node['term']->count; ?></bdi></span>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>

						<?php // Col B — the active category's children. ?>
						<div class="mega__subs">
							<?php foreach ( $tree as $i => $node ) : ?>
								<ul class="mega__sub-list<?php echo 0 === $i ? ' is-active' : ''; ?>" data-mega-subs="<?php echo esc_attr( $i ); ?>" role="list">
									<?php foreach ( $node['children'] as $child ) : ?>
										<li>
											<a href="<?php echo esc_url( get_term_link( $child ) ); ?>">
												<?php echo esc_html( $child->name ); ?>
											</a>
										</li>
									<?php endforeach; ?>
									<?php if ( ! $node['children'] ) : ?>
										<li>
											<a href="<?php echo esc_url( get_term_link( $node['term'] ) ); ?>">
												<?php echo esc_html( sprintf( __( 'לכל ה%s', 'hrdesign' ), $node['term']->name ) ); ?>
											</a>
										</li>
									<?php endif; ?>
								</ul>
							<?php endforeach; ?>
						</div>

						<?php
						/*
						 * Col C — ONE preview plate that cross-fades, not an image per row.
						 * That is the whole idea: a dense text list beside a single large
						 * image reads as a contents page, which is exactly the brief's
						 * complaint about the current long, crowded list.
						 */
						?>
						<div class="mega__preview">
							<?php foreach ( $tree as $i => $node ) : ?>
								<a
									class="mega__plate<?php echo 0 === $i ? ' is-active' : ''; ?>"
									data-mega-plate="<?php echo esc_attr( $i ); ?>"
									href="<?php echo esc_url( get_term_link( $node['term'] ) ); ?>"
									tabindex="-1"
									aria-hidden="true"
								>
									<?php if ( $node['image'] ) : ?>
										<img src="<?php echo esc_url( $node['image'] ); ?>" alt="" loading="lazy" width="300" height="300">
									<?php endif; ?>
									<span class="mega__plate-label"><?php echo esc_html( $node['term']->name ); ?></span>
								</a>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</li>
		<?php endif; ?>

		<li class="site-nav__item">
			<a class="site-nav__link" href="<?php echo esc_url( add_query_arg( 'on_sale', '1', $shop_url ) ); ?>">
				<?php esc_html_e( 'מבצעים', 'hrdesign' ); ?>
			</a>
		</li>
		<li class="site-nav__item">
			<a class="site-nav__link" href="<?php echo esc_url( add_query_arg( 'orderby', 'date', $shop_url ) ); ?>">
				<?php esc_html_e( 'חדש', 'hrdesign' ); ?>
			</a>
		</li>
	</ul>
</nav>
