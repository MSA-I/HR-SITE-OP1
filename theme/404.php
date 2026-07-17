<?php
/**
 * 404.
 *
 * Worth building properly rather than apologising in one line: this is where every
 * mistyped URL lands, and right now also every click on the header's wishlist heart,
 * which points at /wishlist/ — a route that does not exist. Until that is settled, this
 * page is what those customers see, so it gets a way onwards rather than a dead end.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

// Guarded: a 404 is the last page that should be able to fatal.
$shop_url = function_exists( 'wc_get_page_id' ) ? get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/' );

get_header();
?>

<main id="main" class="site-main">
	<section class="section ground--cream">
		<div class="grid">
			<div class="section__rail" aria-hidden="true"></div>

			<div class="section__body">
				<h1 class="t-display t-display--m"><?php esc_html_e( 'הדף לא נמצא', 'hrdesign' ); ?></h1>

				<p class="entry__lede">
					<?php esc_html_e( 'ייתכן שהכתובת השתנתה או שהדף הוסר. אפשר להמשיך מהחנות או לחזור לדף הבית.', 'hrdesign' ); ?>
				</p>

				<p class="entry__back">
					<a class="btn" href="<?php echo esc_url( $shop_url ); ?>">
						<?php esc_html_e( 'לכל המוצרים', 'hrdesign' ); ?>
					</a>
					<a class="btn" href="<?php echo esc_url( home_url( '/' ) ); ?>">
						<?php esc_html_e( 'לדף הבית', 'hrdesign' ); ?>
					</a>
				</p>
			</div>
		</div>
	</section>
</main>

<?php
get_footer();
