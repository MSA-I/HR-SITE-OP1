<?php
/**
 * 08 — Benefits. Terminates the page with a hard colour change into the footer.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

/*
 * Every number here is a promise the client has to honour, so each one tracks the real
 * תקנון at https://hr-design.co.il/privacy-policy/ rather than the theme's defaults:
 *
 *   - 30 days, not 14, and the return is conditional — original packaging, original
 *     invoice, customer pays return shipping, 5%/₪100 cancellation fee, and "הזמנה
 *     מיוחדת" cannot be cancelled at all. "ללא שאלות" promised the opposite of the
 *     terms, so the label points at them instead of overriding them.
 *   - One year, and the תקנון gives it in the company's own name ("פגמים... אשר נגרמו
 *     על ידי החברה"). Nothing there sources a manufacturer's warranty.
 *   - Delivery days are the one figure the תקנון does not state. The number stays as the
 *     theme default; do not sharpen it into a promise without a source.
 *   - There is a showroom address, but no published hours and no "בתיאום מראש" anywhere,
 *     so the claim is just the room.
 */
/*
 * 'count' opts the number into the count-up, and two of the four decline it: a counter
 * that runs from 0 to 1 renders one frame of "0" and then the answer, which is a flicker
 * rather than an animation. Counting the two numbers that have somewhere to travel and
 * leaving the ones is the honest version — all four still arrive together on the shared
 * .stagger, so the section reads as one movement either way.
 */
$items = array(
	array( 'num' => '30', 'unit' => __( 'יום', 'hrdesign' ), 'label' => __( 'החזרה בתנאי התקנון', 'hrdesign' ), 'count' => true ),
	array( 'num' => '7-14', 'unit' => __( 'ימים', 'hrdesign' ), 'label' => __( 'אספקה לכל הארץ', 'hrdesign' ), 'count' => true ),
	array( 'num' => '1', 'unit' => __( 'שנה', 'hrdesign' ), 'label' => __( 'אחריות החברה', 'hrdesign' ), 'count' => false ),
	array( 'num' => '1', 'unit' => __( 'אולם', 'hrdesign' ), 'label' => __( 'תצוגה', 'hrdesign' ), 'count' => false ),
);
?>

<section class="section trust ground--brown">
	<div class="grid">
		<div class="section__body section__body--full">
			<ul class="trust__list stagger" role="list">
				<?php foreach ( $items as $i => $item ) : ?>
					<li class="trust__item" style="--i: <?php echo (int) $i; ?>">
						<p class="trust__num">
							<bdi<?php echo $item['count'] ? ' data-count' : ''; ?>><?php echo esc_html( $item['num'] ); ?></bdi>
							<span class="trust__unit"><?php echo esc_html( $item['unit'] ); ?></span>
						</p>
						<p class="trust__label"><?php echo esc_html( $item['label'] ); ?></p>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
</section>
