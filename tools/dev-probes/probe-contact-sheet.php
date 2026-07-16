<?php
/**
 * Dev-only contact sheet of every studio-classified product, for picking the scene by eye.
 *
 * The classifier is good enough for the card (a plate where a photo would do is a
 * cosmetic miss). It is NOT good enough to compose a room: a room photo with white
 * margins passes both the corner and the white-share tests, and the composer duly pasted
 * a laundry room into the living room. There is exactly ONE scene, so it gets picked by
 * eye rather than by chasing the classifier further.
 *
 * /?hrd_probe=sheet while WP_DEBUG is on.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'template_redirect',
	function () {
		if ( empty( $_GET['hrd_probe'] ) || 'sheet' !== $_GET['hrd_probe'] || ! WP_DEBUG ) {
			return;
		}

		$ids = get_posts(
			array(
				'post_type'      => 'product',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array( 'key' => '_hrd_photo_type', 'value' => 'studio' ),
				),
			)
		);
		?>
		<!doctype html>
		<html dir="rtl" lang="he">
		<head>
			<meta charset="utf-8">
			<title>studio contact sheet</title>
			<style>
				body { font: 13px system-ui; background: #333; color: #eee; padding: 16px; margin: 0; }
				.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; }
				figure { margin: 0; background: #ddd8cc; }
				img { width: 100%; aspect-ratio: 1; object-fit: contain; mix-blend-mode: multiply; display: block; }
				figcaption { padding: 4px 6px; background: #222; font-size: 11px; line-height: 1.3; }
				b { color: #9fb37a; }
			</style>
		</head>
		<body>
			<p><?php echo count( $ids ); ?> studio-classified. Shown exactly as the plate renders them: multiply on the tint. Anything that still shows a room is a misclassification.</p>
			<div class="grid">
				<?php foreach ( $ids as $id ) : ?>
					<?php $product = wc_get_product( $id ); ?>
					<figure>
						<?php echo $product->get_image( 'woocommerce_thumbnail' ); ?>
						<figcaption>
							<b><?php echo (int) get_post_meta( $id, '_hrd_src_id', true ); ?></b>
							<?php echo esc_html( mb_substr( $product->get_name(), 0, 40 ) ); ?><br>
							white <?php echo esc_html( get_post_meta( $id, '_hrd_photo_white_share', true ) ); ?>
						</figcaption>
					</figure>
				<?php endforeach; ?>
			</div>
		</body>
		</html>
		<?php
		exit;
	}
);
