<?php
/**
 * 08 — Benefits. Terminates the page with a hard colour change into the footer.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

$items = array(
	array( 'num' => '14', 'unit' => __( 'יום', 'hrdesign' ), 'label' => __( 'החזרה ללא שאלות', 'hrdesign' ) ),
	array( 'num' => '7—14', 'unit' => __( 'ימים', 'hrdesign' ), 'label' => __( 'אספקה לכל הארץ', 'hrdesign' ) ),
	array( 'num' => '2', 'unit' => __( 'שנים', 'hrdesign' ), 'label' => __( 'אחריות יצרן', 'hrdesign' ) ),
	array( 'num' => '1', 'unit' => __( 'אולם', 'hrdesign' ), 'label' => __( 'תצוגה — בתיאום מראש', 'hrdesign' ) ),
);
?>

<section class="section trust ground--brown">
	<div class="grid">
		<div class="section__body section__body--full">
			<ul class="trust__list stagger" role="list">
				<?php foreach ( $items as $i => $item ) : ?>
					<li class="trust__item" style="--i: <?php echo (int) $i; ?>">
						<p class="trust__num">
							<bdi><?php echo esc_html( $item['num'] ); ?></bdi>
							<span class="trust__unit"><?php echo esc_html( $item['unit'] ); ?></span>
						</p>
						<p class="trust__label"><?php echo esc_html( $item['label'] ); ?></p>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
</section>
