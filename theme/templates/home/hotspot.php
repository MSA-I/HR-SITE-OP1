<?php
/**
 * One hotspot.
 *
 * A real <button> in DOM order (= RTL reading order), so Tab traverses the room the way
 * a Hebrew reader would. Without this the site's flagship feature is invisible to
 * assistive tech and to Google.
 *
 * popovertarget does the opening: top layer, light dismiss, Escape and focus return,
 * all with zero JS. JS only positions the card.
 *
 * @package hrdesign
 *
 * @var array $args spot
 */

defined( 'ABSPATH' ) || exit;

$spot = $args['spot'];
?>

<button
	type="button"
	class="hotspot"
	data-hotspot="<?php echo esc_attr( $spot['id'] ); ?>"
	popovertarget="card-<?php echo esc_attr( $spot['id'] ); ?>"
	style="--x: <?php echo esc_attr( $spot['x_d'] ); ?>%; --y: <?php echo esc_attr( $spot['y_d'] ); ?>%; --x-m: <?php echo esc_attr( $spot['x_m'] ); ?>%; --y-m: <?php echo esc_attr( $spot['y_m'] ); ?>%;"
	aria-label="<?php echo esc_attr( sprintf( __( '%1$s — %2$s', 'hrdesign' ), $spot['product']['name'], wp_strip_all_tags( $spot['product']['price_html'] ) ) ); ?>"
>
	<span class="hotspot__dot" aria-hidden="true"></span>
	<span class="hotspot__chip" aria-hidden="true"><?php echo esc_html( $spot['product']['name'] ); ?></span>
</button>
