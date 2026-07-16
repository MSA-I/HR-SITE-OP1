<?php
/**
 * Dimension diagram — generated, not photographed.
 *
 * Built entirely from the parsed WC dimension fields, so it costs nothing per product
 * and appears only where the data exists.
 *
 * The stage is forced LTR: this is a technical drawing, and its leader lines and axes
 * are physical positions, not text flow. The labels inside are restored to RTL.
 *
 * @package hrdesign
 *
 * @var array $args l, w, h (cm, any may be null), estimated (bool)
 */

defined( 'ABSPATH' ) || exit;

$l = $args['l'] ?? null;
$w = $args['w'] ?? null;
$h = $args['h'] ?? null;
$estimated = ! empty( $args['estimated'] );
$tilde = $estimated ? '~' : '';

// Normalise to a 260x200 stage while keeping the real proportions.
$max = max( array_filter( array( $l, $w, $h ) ) ?: array( 1 ) );
$scale = 150 / $max;

$box_w = $l ? max( 24, $l * $scale ) : 120;
$box_h = $h ? max( 24, $h * $scale ) : 90;
$depth = $w ? max( 8, min( 40, $w * $scale * 0.5 ) ) : 0;

$x = 40;
$y = 190 - $box_h;
?>

<svg class="dim-diagram" viewBox="0 0 300 200" role="img"
	aria-label="<?php echo esc_attr( sprintf( __( 'תרשים מידות: רוחב %1$s, עומק %2$s, גובה %3$s סנטימטר', 'hrdesign' ), $l ?? '?', $w ?? '?', $h ?? '?' ) ); ?>">

	<g fill="none" stroke="var(--acc)" stroke-width="1" vector-effect="non-scaling-stroke">
		<?php if ( $depth ) : ?>
			<path d="M<?php echo esc_attr( $x + $depth ); ?> <?php echo esc_attr( $y - $depth ); ?>
				h<?php echo esc_attr( $box_w ); ?>
				v<?php echo esc_attr( $box_h ); ?>
				l<?php echo esc_attr( -$depth ); ?> <?php echo esc_attr( $depth ); ?>" opacity=".45"/>
			<path d="M<?php echo esc_attr( $x ); ?> <?php echo esc_attr( $y ); ?>
				l<?php echo esc_attr( $depth ); ?> <?php echo esc_attr( -$depth ); ?>" opacity=".45"/>
			<path d="M<?php echo esc_attr( $x + $box_w ); ?> <?php echo esc_attr( $y ); ?>
				l<?php echo esc_attr( $depth ); ?> <?php echo esc_attr( -$depth ); ?>" opacity=".45"/>
		<?php endif; ?>

		<rect x="<?php echo esc_attr( $x ); ?>" y="<?php echo esc_attr( $y ); ?>"
			width="<?php echo esc_attr( $box_w ); ?>" height="<?php echo esc_attr( $box_h ); ?>"/>
	</g>

	<g stroke="var(--brown-500)" stroke-width="1" opacity=".5" vector-effect="non-scaling-stroke">
		<?php if ( $l ) : ?>
			<line x1="<?php echo esc_attr( $x ); ?>" y1="196" x2="<?php echo esc_attr( $x + $box_w ); ?>" y2="196"/>
			<line x1="<?php echo esc_attr( $x ); ?>" y1="192" x2="<?php echo esc_attr( $x ); ?>" y2="200"/>
			<line x1="<?php echo esc_attr( $x + $box_w ); ?>" y1="192" x2="<?php echo esc_attr( $x + $box_w ); ?>" y2="200"/>
		<?php endif; ?>
		<?php if ( $h ) : ?>
			<line x1="<?php echo esc_attr( $x - 8 ); ?>" y1="<?php echo esc_attr( $y ); ?>" x2="<?php echo esc_attr( $x - 8 ); ?>" y2="190"/>
			<line x1="<?php echo esc_attr( $x - 12 ); ?>" y1="<?php echo esc_attr( $y ); ?>" x2="<?php echo esc_attr( $x - 4 ); ?>" y2="<?php echo esc_attr( $y ); ?>"/>
			<line x1="<?php echo esc_attr( $x - 12 ); ?>" y1="190" x2="<?php echo esc_attr( $x - 4 ); ?>" y2="190"/>
		<?php endif; ?>
	</g>

	<?php // Latin digits in an LTR run — never rely on the parent's bidi context. ?>
	<g class="dim-diagram__labels" fill="var(--brown-500)" font-size="10" direction="ltr">
		<?php if ( $l ) : ?>
			<text x="<?php echo esc_attr( $x + $box_w / 2 ); ?>" y="188" text-anchor="middle"><?php echo esc_html( $tilde . $l ); ?></text>
		<?php endif; ?>
		<?php if ( $h ) : ?>
			<text x="<?php echo esc_attr( $x - 16 ); ?>" y="<?php echo esc_attr( $y + $box_h / 2 ); ?>" text-anchor="end"><?php echo esc_html( $tilde . $h ); ?></text>
		<?php endif; ?>
		<?php if ( $w && $depth ) : ?>
			<text x="<?php echo esc_attr( $x + $box_w + $depth + 6 ); ?>" y="<?php echo esc_attr( $y - $depth / 2 ); ?>"><?php echo esc_html( $tilde . $w ); ?></text>
		<?php endif; ?>
	</g>
</svg>
