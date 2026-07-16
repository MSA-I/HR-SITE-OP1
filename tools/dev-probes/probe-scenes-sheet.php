<?php
/**
 * Dev-only contact sheet of SCENE-classified products — their own room photography.
 *
 * The composed-room plan assumed the living-room furniture existed as studio cut-outs.
 * It does not: the cut-outs are bathroom fittings and lighting, and the one sofa is a
 * pale sofa on white, which multiply erases entirely. The living-room furniture is all
 * photographed IN rooms.
 *
 * Which reframes the feature: their room photographs ARE the space. This sheet finds the
 * richest one — a styled room with several of their products in it.
 *
 * /?hrd_probe=scenes while WP_DEBUG is on.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'template_redirect',
	function () {
		if ( empty( $_GET['hrd_probe'] ) || 'scenes' !== $_GET['hrd_probe'] || ! WP_DEBUG ) {
			return;
		}

		$cat = sanitize_title( wp_unslash( $_GET['cat'] ?? '' ) );

		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => 60,
			'fields'         => 'ids',
			'meta_query'     => array(
				array( 'key' => '_hrd_photo_type', 'value' => 'scene' ),
			),
		);

		if ( $cat ) {
			$args['tax_query'] = array( array( 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $cat ) );
		}

		$ids = get_posts( $args );

		$cats = get_terms(
			array( 'taxonomy' => 'product_cat', 'parent' => 0, 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC' )
		);
		?>
		<!doctype html>
		<html dir="rtl" lang="he">
		<head>
			<meta charset="utf-8">
			<title>scene contact sheet</title>
			<style>
				body { font: 13px system-ui; background: #333; color: #eee; padding: 16px; margin: 0; }
				nav { margin-block-end: 12px; display: flex; gap: 8px; flex-wrap: wrap; }
				nav a { color: #9fb37a; }
				.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px; }
				figure { margin: 0; background: #111; }
				img { width: 100%; aspect-ratio: 4/3; object-fit: cover; display: block; }
				figcaption { padding: 4px 6px; font-size: 11px; line-height: 1.3; }
				b { color: #9fb37a; }
			</style>
		</head>
		<body>
			<nav>
				<a href="?hrd_probe=scenes">הכל</a>
				<?php foreach ( $cats as $term ) : ?>
					<a href="?hrd_probe=scenes&cat=<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?> (<?php echo (int) $term->count; ?>)</a>
				<?php endforeach; ?>
			</nav>
			<p><?php echo count( $ids ); ?> scene photos. Looking for one styled room holding several products.</p>
			<div class="grid">
				<?php foreach ( $ids as $id ) : ?>
					<?php $product = wc_get_product( $id ); ?>
					<figure>
						<?php echo $product->get_image( 'woocommerce_single' ); ?>
						<figcaption>
							<b><?php echo (int) get_post_meta( $id, '_hrd_src_id', true ); ?></b>
							<?php echo esc_html( mb_substr( $product->get_name(), 0, 46 ) ); ?>
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
