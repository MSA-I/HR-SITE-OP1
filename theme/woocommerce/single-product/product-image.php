<?php
/**
 * The media column.
 *
 * 35% of this catalogue has exactly one image, so a "gallery" would be a lie. Instead:
 * three legitimate frames built from one photo and a dimensions string.
 *
 *   1. the plate      — the photo, multiplied onto the accent tint
 *   2. the diagram    — generated SVG from the parsed dimensions
 *   3. the scale ref  — a 175cm silhouette beside the product's bounding box
 *
 * Frames 2 and 3 simply do not render when there are no dimensions. When real lifestyle
 * photography arrives it inserts as frame 2 and pushes the rest down; nothing needs
 * redesigning.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

global $product;

$gallery_ids = $product->get_gallery_image_ids();
// Axes, not the compacted display list: a drawing needs to know which number is which.
$axes = hrd_product_axes( $product );
?>

<div class="pdp-media">
	<figure class="pdp-frame pdp-frame--plate reveal" data-plate>
		<?php
		echo $product->get_image(
			'woocommerce_single',
			array( 'class' => 'pdp-plate__img', 'fetchpriority' => 'high' )
		);
		?>
	</figure>

	<?php foreach ( $gallery_ids as $id ) : ?>
		<figure class="pdp-frame pdp-frame--plate reveal">
			<?php
			echo wp_get_attachment_image(
				$id,
				'woocommerce_single',
				false,
				array( 'class' => 'pdp-plate__img', 'loading' => 'lazy' )
			);
			?>
		</figure>
	<?php endforeach; ?>

	<?php if ( $axes ) : ?>
		<figure class="pdp-frame pdp-frame--diagram reveal">
			<?php
			get_template_part(
				'templates/product/dimension-diagram',
				null,
				array(
					'l'         => $axes['l'],
					'w'         => $axes['w'],
					'h'         => $axes['h'],
					'estimated' => $axes['estimated'],
				)
			);
			?>
			<figcaption class="pdp-frame__cap t-mono">
				<?php echo $axes['estimated'] ? esc_html__( 'מידות משוערות', 'hrdesign' ) : esc_html__( 'מידות', 'hrdesign' ); ?>
			</figcaption>
		</figure>

		<?php // A scale comparison needs a height to compare. Wall mirrors and rugs have none. ?>
		<?php if ( $axes['h'] && $axes['h'] >= 20 ) : ?>
			<figure class="pdp-frame pdp-frame--scale reveal">
				<?php get_template_part( 'templates/product/scale-reference', null, array( 'l' => $axes['l'], 'h' => $axes['h'] ) ); ?>
				<figcaption class="pdp-frame__cap t-mono">
					<?php echo $axes['estimated'] ? esc_html__( 'קנה מידה משוער: אדם בגובה 175 ס"מ', 'hrdesign' ) : esc_html__( 'קנה מידה: אדם בגובה 175 ס"מ', 'hrdesign' ); ?>
				</figcaption>
			</figure>
		<?php endif; ?>
	<?php endif; ?>
</div>
