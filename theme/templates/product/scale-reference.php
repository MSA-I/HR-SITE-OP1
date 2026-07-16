<?php
/**
 * Scale reference — the product's bounding box beside a 175cm silhouette.
 *
 * This answers the single biggest furniture-purchase anxiety (will it fit, how big is
 * it really) and costs one shared SVG plus arithmetic. Like the diagram, it is forced
 * LTR: these are physical positions, not text.
 *
 * @package hrdesign
 *
 * @var array $args l (width, cm), h (height, cm)
 */

defined( 'ABSPATH' ) || exit;

$l = (float) ( $args['l'] ?? 0 );
$h = (float) ( $args['h'] ?? 0 );

if ( ! $h ) {
	return;
}

const HRD_PERSON_CM = 175;

// Both the person and the product share one scale, or the comparison means nothing.
$tallest = max( $h, HRD_PERSON_CM );
$scale   = 150 / $tallest;

$person_h  = HRD_PERSON_CM * $scale;
$product_h = $h * $scale;
$product_w = $l ? max( 10, min( 150, $l * $scale ) ) : 40;

$baseline = 180;
$person_x = 40;
$product_x = 100;
?>

<svg class="scale-ref" viewBox="0 0 300 200" role="img"
	aria-label="<?php echo esc_attr( sprintf( __( 'השוואת קנה מידה: המוצר בגובה %s ס"מ לצד אדם בגובה 175 ס"מ', 'hrdesign' ), $h ) ); ?>">

	<line x1="20" y1="<?php echo esc_attr( $baseline ); ?>" x2="280" y2="<?php echo esc_attr( $baseline ); ?>"
		stroke="var(--cream-200)" stroke-width="1" vector-effect="non-scaling-stroke"/>

	<?php // 175cm silhouette, drawn once, scaled by the shared factor. ?>
	<g transform="translate(<?php echo esc_attr( $person_x ); ?>, <?php echo esc_attr( $baseline - $person_h ); ?>) scale(<?php echo esc_attr( $person_h / 175 ); ?>)"
		fill="var(--brown-500)" opacity=".35">
		<circle cx="18" cy="16" r="14"/>
		<path d="M18 32c-11 0-19 7-19 17v46c0 4 3 6 6 6l2 52c0 5 3 7 6 7s6-2 6-7l2-40h4l2 40c0 5 3 7 6 7s6-2 6-7l2-52c3 0 6-2 6-6V49c0-10-8-17-19-17z"/>
	</g>

	<g fill="var(--acc)" opacity=".16">
		<rect x="<?php echo esc_attr( $product_x ); ?>" y="<?php echo esc_attr( $baseline - $product_h ); ?>"
			width="<?php echo esc_attr( $product_w ); ?>" height="<?php echo esc_attr( $product_h ); ?>"/>
	</g>
	<g fill="none" stroke="var(--acc)" stroke-width="1" vector-effect="non-scaling-stroke">
		<rect x="<?php echo esc_attr( $product_x ); ?>" y="<?php echo esc_attr( $baseline - $product_h ); ?>"
			width="<?php echo esc_attr( $product_w ); ?>" height="<?php echo esc_attr( $product_h ); ?>"/>
	</g>

	<g fill="var(--brown-500)" font-size="9" direction="ltr">
		<text x="<?php echo esc_attr( $person_x + 8 ); ?>" y="194" text-anchor="middle">175</text>
		<text x="<?php echo esc_attr( $product_x + $product_w / 2 ); ?>" y="194" text-anchor="middle"><?php echo esc_html( (string) $h ); ?></text>
	</g>
</svg>
