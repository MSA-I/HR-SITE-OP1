<?php
/**
 * Dev-only: view one product's photo full size, to place hotspots against it.
 *
 * /?hrd_probe=pick&src=5604
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'template_redirect',
	function () {
		if ( empty( $_GET['hrd_probe'] ) || 'pick' !== $_GET['hrd_probe'] || ! WP_DEBUG ) {
			return;
		}

		$src = absint( $_GET['src'] ?? 0 );
		$ids = get_posts(
			array(
				'post_type'      => 'product',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( array( 'key' => '_hrd_src_id', 'value' => $src ) ),
			)
		);

		if ( ! $ids ) {
			wp_die( 'not found' );
		}

		$product = wc_get_product( $ids[0] );
		$images = array_merge( array( get_post_thumbnail_id( $ids[0] ) ), $product->get_gallery_image_ids() );
		?>
		<!doctype html>
		<html dir="rtl" lang="he">
		<head>
			<meta charset="utf-8">
			<title><?php echo esc_html( $product->get_name() ); ?></title>
			<style>
				body { font: 13px system-ui; background: #222; color: #eee; padding: 16px; margin: 0; }
				.wrap { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; max-width: 1400px; }
				figure { margin: 0; position: relative; }
				img { width: 100%; display: block; }
				figcaption { padding: 4px; font-size: 11px; }
				/* A percentage grid, so hotspot coordinates can be read straight off. */
				.grid-overlay {
					position: absolute; inset: 0; pointer-events: none;
					background-image:
						repeating-linear-gradient(to right, rgb(255 0 0 / .35) 0 1px, transparent 1px 10%),
						repeating-linear-gradient(to bottom, rgb(255 0 0 / .35) 0 1px, transparent 1px 10%);
				}
			</style>
		</head>
		<body>
			<h1><?php echo esc_html( $product->get_name() ); ?> — src <?php echo (int) $src; ?></h1>
			<p>Red grid = 10% steps. Read hotspot x/y straight off it.</p>
			<div class="wrap">
				<?php foreach ( $images as $i => $image_id ) : ?>
					<?php if ( ! $image_id ) { continue; } ?>
					<figure>
						<?php echo wp_get_attachment_image( $image_id, 'full' ); ?>
						<span class="grid-overlay"></span>
						<figcaption>image <?php echo (int) $i; ?> — attachment <?php echo (int) $image_id; ?></figcaption>
					</figure>
				<?php endforeach; ?>
			</div>
		</body>
		</html>
		<?php
		exit;
	}
);
