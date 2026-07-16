<?php
/**
 * 01 — Hero.
 *
 * One image, one headline, two buttons. No carousel: if there are three messages there
 * is no message. The headline is the only element permitted to hang into the rail and
 * out over the image edge.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

$image_id = (int) get_option( 'hrd_hero_image' );
$shop_url = get_permalink( wc_get_page_id( 'shop' ) );

// Fall back to the room scene's photograph — the site should never ship a grey box.
if ( ! $image_id ) {
	$scene_id = function_exists( 'hrd_active_scene_id' ) ? hrd_active_scene_id() : null;
	$image_id = $scene_id ? (int) get_post_thumbnail_id( $scene_id ) : 0;
}
?>

<section class="hero">
	<?php if ( $image_id ) : ?>
		<div class="hero__media">
			<?php
			/*
			 * 'full' would hand a phone the same 2560px file as a desktop. WordPress
			 * generated 9 sizes for this image — `sizes="100vw"` is what lets the browser
			 * pick one, and this is the LCP element, so it is the one place on the site
			 * where that choice actually costs something.
			 */
			echo wp_get_attachment_image(
				$image_id,
				'full',
				false,
				array(
					'class'         => 'hero__img',
					'fetchpriority' => 'high',
					'decoding'      => 'sync',
					'loading'       => 'eager',
					'sizes'         => '100vw',
					'alt'           => '',
				)
			);
			?>
		</div>
	<?php endif; ?>

	<div class="hero__inner stagger">
		<h1 class="hero__title t-display t-display--m" style="--i: 0">
			<?php echo esc_html( get_option( 'hrd_hero_title', __( 'בית עם אופי, לא קטלוג', 'hrdesign' ) ) ); ?>
		</h1>

		<div class="hero__actions" style="--i: 1">
			<a class="btn btn--primary" href="<?php echo esc_url( add_query_arg( 'orderby', 'date', $shop_url ) ); ?>">
				<?php esc_html_e( 'לקולקציה החדשה', 'hrdesign' ); ?>
			</a>
			<a class="btn" href="<?php echo esc_url( $shop_url ); ?>">
				<?php esc_html_e( 'לכל המוצרים', 'hrdesign' ); ?>
			</a>
		</div>
	</div>
</section>
