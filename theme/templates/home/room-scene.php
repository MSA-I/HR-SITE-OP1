<?php
/**
 * Shop the Space.
 *
 * Everything is server-rendered: the layers, the hotspot buttons, and all the mini
 * cards. Zero fetch on click, so a card opens in one frame — and the products are
 * crawlable links on the homepage. With JS off you get the photo plus a working product
 * list, which is a real degradation rather than a broken section.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

$scene_id = hrd_active_scene_id();
if ( ! $scene_id ) {
	return;
}

$hotspots = hrd_scene_payload( $scene_id );
if ( ! $hotspots ) {
	return;
}

$desktop_id = get_post_thumbnail_id( $scene_id );
$mobile_id  = get_post_meta( $scene_id, '_hrd_scene_mobile', true );

$layers = array(
	'bg'   => get_post_meta( $scene_id, '_hrd_layer_bg', true ),
	'mid'  => get_post_meta( $scene_id, '_hrd_layer_mid', true ),
	'fore' => get_post_meta( $scene_id, '_hrd_layer_fore', true ),
);

// The portrait re-composition, per layer. The stage is 16:9 on desktop and 4:5 on
// mobile: cover-cropping the wide room into the tall stage sliced it into an
// unreadable strip, so the composer emits a second, re-laid-out set.
$layers_mobile = array(
	'bg'   => get_post_meta( $scene_id, '_hrd_layer_bg_mobile', true ),
	'mid'  => get_post_meta( $scene_id, '_hrd_layer_mid_mobile', true ),
	'fore' => get_post_meta( $scene_id, '_hrd_layer_fore_mobile', true ),
);

// No cut-out layers authored: fall back to one flat plate. It reads as a window rather
// than a room, which is an honest degradation — not the pitch, but not broken either.
$layered = (bool) array_filter( $layers );

// Group hotspots by layer so each one renders INSIDE its layer div and inherits that
// layer's transform for free. This is the whole architectural trick: get it wrong and
// you spend two days chasing pins drifting off their objects.
$by_layer = array( 'bg' => array(), 'mid' => array(), 'fore' => array() );
foreach ( $hotspots as $spot ) {
	$by_layer[ $layered ? $spot['layer'] : 'mid' ][] = $spot;
}
?>

<section class="section sts ground--ink bleed" data-sts aria-labelledby="sts-title">
	<div class="sts__head stagger">
		<p class="sts__eyebrow t-mono" style="--i: 0"><?php esc_html_e( 'Shop the Space', 'hrdesign' ); ?></p>
		<h2 class="sts__title t-display t-display--s" id="sts-title" style="--i: 1">
			<?php esc_html_e( 'הסלון הזה נמכר, פריט אחר פריט', 'hrdesign' ); ?>
		</h2>
	</div>

	<?php
	/*
	 * The stage is forced LTR in CSS. Hotspot coordinates are PHYSICAL positions in an
	 * image: inherit RTL and inset-inline-start: 61% measures from the right edge, so
	 * every pin mirrors onto the wrong furniture. Casual QA never catches it — the dots
	 * are still on objects, just the wrong ones.
	 */
	?>
	<div class="sts__stage" data-sts-stage>
		<?php if ( $layered ) : ?>
			<?php foreach ( array( 'bg', 'mid', 'fore' ) as $layer ) : ?>
				<div class="sts__layer sts__layer--<?php echo esc_attr( $layer ); ?>" data-sts-layer="<?php echo esc_attr( $layer ); ?>">
					<?php
					/*
					 * Lazy, and the three layers are ~750KB together. This section is
					 * always below the fold — it is the third on the page — so eager
					 * loading them puts three quarters of a megabyte on the LCP path for
					 * a room nobody has scrolled to yet.
					 */
					?>
					<?php if ( $layers[ $layer ] ) : ?>
						<picture>
							<?php if ( $layers_mobile[ $layer ] ) : ?>
								<source media="(max-width: 720px)" srcset="<?php echo esc_url( wp_get_attachment_image_url( (int) $layers_mobile[ $layer ], 'full' ) ); ?>">
							<?php endif; ?>
							<?php echo wp_get_attachment_image( (int) $layers[ $layer ], 'full', false, array( 'class' => 'sts__img', 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async' ) ); ?>
						</picture>
					<?php endif; ?>
					<?php
					foreach ( $by_layer[ $layer ] as $spot ) {
						get_template_part( 'templates/home/hotspot', null, array( 'spot' => $spot ) );
					}
					?>
				</div>
			<?php endforeach; ?>
		<?php else : ?>
			<div class="sts__layer sts__layer--mid" data-sts-layer="mid">
				<picture>
					<?php if ( $mobile_id ) : ?>
						<source media="(max-width: 720px)" srcset="<?php echo esc_url( wp_get_attachment_image_url( (int) $mobile_id, 'full' ) ); ?>">
					<?php endif; ?>
					<?php echo wp_get_attachment_image( $desktop_id, 'full', false, array( 'class' => 'sts__img', 'alt' => get_the_title( $scene_id ), 'loading' => 'lazy', 'decoding' => 'async' ) ); ?>
				</picture>
				<?php
				foreach ( $by_layer['mid'] as $spot ) {
					get_template_part( 'templates/home/hotspot', null, array( 'spot' => $spot ) );
				}
				?>
			</div>
		<?php endif; ?>
	</div>

	<?php
	/*
	 * Mobile: the strip is the real control, not a fallback. Tapping a 20px dot your own
	 * thumb is covering is not an interaction. Two-way bound to the pins in JS.
	 */
	?>
	<ul class="sts__strip" data-sts-strip role="list">
		<?php foreach ( $hotspots as $spot ) : ?>
			<li>
				<button type="button" class="sts__strip-item" data-sts-strip-item="<?php echo esc_attr( $spot['id'] ); ?>">
					<?php if ( $spot['product']['thumb'] ) : ?>
						<span class="sts__strip-plate is-<?php echo esc_attr( $spot['product']['photo_type'] ); ?>">
							<img src="<?php echo esc_url( $spot['product']['thumb'] ); ?>" alt="" loading="lazy" width="80" height="80">
						</span>
					<?php endif; ?>
					<span class="sts__strip-name"><?php echo esc_html( $spot['product']['name'] ); ?></span>
					<span class="sts__strip-price"><?php echo wp_kses_post( $spot['product']['price_html'] ); ?></span>
				</button>
			</li>
		<?php endforeach; ?>
	</ul>

	<?php // The mini cards, rendered up front and hidden. Popover API gives top-layer,
	// light-dismiss, Escape and focus management for free — no JS needed for any of it. ?>
	<?php foreach ( $hotspots as $spot ) : ?>
		<?php get_template_part( 'templates/home/mini-card', null, array( 'spot' => $spot ) ); ?>
	<?php endforeach; ?>
</section>
