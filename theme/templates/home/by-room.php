<?php
/**
 * 06 — Shop by room.
 *
 * Five arch-topped portals. This is the only radius in the entire system.
 *
 * The rooms map onto real categories rather than a pa_room attribute nobody has filled
 * in: a link that lands on an empty archive is worse than no link. Each portal links to
 * the whole room — a comma-separated product_cat, which WordPress reads as an OR — not to
 * one narrow category. The definitions, the link and the archive's title all live in
 * inc/rooms.php; this file only renders them.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'hrd_rooms' ) ) {
	return;
}

$hrd_images  = hrd_room_images();
$hrd_portals = array();

foreach ( hrd_rooms() as $hrd_key => $hrd_room ) {
	// A room with no products is a dead link; drop it rather than show it.
	$hrd_count = 0;
	foreach ( $hrd_room['slugs'] as $hrd_slug ) {
		$hrd_term = get_term_by( 'slug', $hrd_slug, 'product_cat' );
		if ( $hrd_term && ! is_wp_error( $hrd_term ) ) {
			$hrd_count += (int) $hrd_term->count;
		}
	}

	if ( ! $hrd_count ) {
		continue;
	}

	$hrd_portals[ $hrd_key ] = array(
		'label' => $hrd_room['label'],
		'link'  => hrd_room_link( $hrd_key ),
		'image' => $hrd_images[ $hrd_key ] ?? 0,
	);
}

if ( count( $hrd_portals ) < 2 ) {
	return;
}
?>

<section class="section ground--cream-alt">
	<div class="grid">
		<div class="section__rail">
			<span class="section__index"><span class="section__num">04</span> / <span class="section__label"><?php esc_html_e( 'חללים', 'hrdesign' ); ?></span></span>
		</div>

		<div class="section__body">
			<header class="section__head stagger">
				<h2 class="t-display t-display--s" style="--i: 0"><?php esc_html_e( 'לפי חלל', 'hrdesign' ); ?></h2>
			</header>

			<ul class="portals" role="list">
				<?php foreach ( $hrd_portals as $hrd_portal ) : ?>
					<li>
						<a class="portal reveal" href="<?php echo esc_url( $hrd_portal['link'] ); ?>">
							<span class="portal__frame">
								<span class="reveal__curtain" aria-hidden="true"></span>
								<?php
								if ( $hrd_portal['image'] ) {
									// wp_get_attachment_image writes the real intrinsic width/height
									// and the srcset. The markup used to hardcode 400x533 around a
									// 300x300 file, which was both the blur and a lie about the size.
									echo wp_get_attachment_image(
										$hrd_portal['image'],
										'hrd_portal',
										false,
										array(
											// The label names the room, so the image repeating it
											// would be noise to a screen reader.
											'alt'     => '',
											'loading' => 'lazy',
											'sizes'   => '(max-width: 900px) 45vw, 20vw',
										)
									);
								}
								?>
							</span>
							<span class="portal__label"><?php echo esc_html( $hrd_portal['label'] ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
</section>
