<?php
/**
 * By Light — one room, from morning to night.
 *
 * The section sells atmosphere. One living room at four hours, a control that walks its
 * light from 07:00 to 23:00, and a real pendant that is dark at dawn and is the only light
 * left at midnight. It spends three of its four states telling you that you do not need the
 * lamp yet, and asks for money once.
 *
 * THE IMAGES ARE GENERATED DEPICTIONS, AND THAT MUST NOT BE SOFT-PEDALLED. The source is
 * HR Design's real living room (product 5932) and their real pendant (6659 / AM933), but an
 * image-to-image model RE-RENDERED the scene at each hour rather than relighting their
 * photograph pixel-for-pixel. The furniture is recognisable and the lamp is genuinely
 * theirs; the pixels are not their photograph. Verified before shipping: geometry holds
 * across all four frames to 0px and no object is substituted, so nothing morphs when they
 * cross-fade. Prompts are kept in seed/bylight/p-*.txt for reproducibility.
 *
 * THE CONTROL IS FOUR NATIVE RADIOS AND :has(). There is no JavaScript in the feature at
 * all; src/js/by-light.js only plays a one-shot demo and then gets out of the way. With JS
 * disabled every stop is still drivable and the section is complete, not degraded.
 *
 * A consequence worth stating because a later change would undo it: there is no scroll
 * container here, so there is no scrollLeft, so the RTL trap documented at collection.js:13
 * cannot occur in this file. It was designed out of existence rather than handled.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

$scene_id = hrd_byl_active_scene_id();
if ( ! $scene_id ) {
	return;
}

$scene = hrd_byl_payload( $scene_id );
if ( ! $scene ) {
	return;
}

$stops = hrd_byl_stops();
?>

<section class="section byl ground--ink bleed" data-byl aria-labelledby="byl-title">
	<div class="byl__head stagger">
		<?php
		/*
		 * NOT .t-mono, and this is a real bug that was here.
		 *
		 * .t-mono is --ff-mono (IBM Plex Mono) plus letter-spacing: 0.08em. The theme ships
		 * exactly one Plex file, plex-mono-400-latin.woff2, whose unicode-range is Latin —
		 * there is no Hebrew in it. So this Hebrew eyebrow was not in Plex at all: it fell
		 * through to ui-monospace, whatever mono the OS happened to have, which is the one
		 * thing a self-hosted type system exists to prevent. On top of that it carried
		 * 0.88px of tracking, and Hebrew has no letter case to justify tracking — it only
		 * damages word-shape.
		 *
		 * The time labels below KEEP .t-mono, because "07:00" is Latin digits and Plex
		 * genuinely renders them. The distinction is the font's coverage, not the class.
		 */
		?>
		<p class="byl__eyebrow" style="--i: 0"><?php esc_html_e( 'לפי אור', 'hrdesign' ); ?></p>
		<h2 class="byl__title t-display t-display--s" id="byl-title" style="--i: 1">
			<?php esc_html_e( 'הבית מבוקר עד לילה', 'hrdesign' ); ?>
		</h2>
	</div>

	<div class="byl__grid">
		<?php
		/*
		 * The stage is deliberately NOT a child of .stagger.
		 *
		 * motion.css:68 sets `.stagger > * { opacity: 0 }`, and an element at opacity 0 is
		 * its own stacking context. Wrapping the stage in one would isolate the stops from
		 * each other for the 480ms of the entrance and the cross-fade would flicker through
		 * the ink ground. Only the head and the column stagger. Keep it that way.
		 */
		?>
		<div class="byl__figure">
			<div class="byl__stage">
				<?php
				/*
				 * Four frames stacked, one visible. This is the whole mechanism: the light
				 * is IN the photographs, so the section cross-fades opacity and does nothing
				 * else.
				 *
				 * Every one is lazy. The section is third on the page and nobody has
				 * scrolled to it; putting four interiors on the LCP path to light a room
				 * that is still below the fold would be the worst trade on the site.
				 *
				 * Only 07:00 carries alt text. All four are the same room and the same
				 * furniture — describing each would make a screen reader read the living
				 * room out four times to announce a lighting change. The stop's label and
				 * its line of copy carry the state; the photograph carries the room.
				 */
				?>
				<?php $first = true; ?>
				<?php foreach ( $stops as $key => $label ) : ?>
					<?php
					// hrd_byl_frame() owns the srcset cap and the measured `sizes`; see the
					// note on it for why both are worth a helper rather than inline markup.
					echo hrd_byl_frame(
						$scene['images'][ $key ],
						$first ? (string) get_post_meta( $scene['images'][ $key ], '_wp_attachment_image_alt', true ) : '',
						$key
					); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
					<?php $first = false; ?>
				<?php endforeach; ?>

				<?php
				/*
				 * The price chip that ignites at 23:00. It is a DECORATIVE DUPLICATE of the
				 * real, permanent CTA in the column — which is exactly what earns it the
				 * right to appear and disappear. Hiding a real control until nightfall would
				 * be a textbook hidden-but-focusable bug; a duplicate that is aria-hidden
				 * and pointer-events:none costs nothing to animate.
				 *
				 * price_html, not a hand-formatted number: it routes through inc/bidi.php,
				 * which is the only reason "480 ₪" does not render as "₪ 480" in an RTL
				 * document.
				 */
				?>
				<span class="byl__tag" aria-hidden="true"><?php echo wp_kses_post( $scene['product']['price_html'] ); ?></span>
			</div>
		</div>

		<div class="byl__col stagger">
			<?php
			/*
			 * A native radio group. Arrow keys move between stops for free and correctly,
			 * because that is what a radio group has always done — and it is the ARIA
			 * pattern a screen reader already expects, with no roving tabindex to get
			 * wrong. Buttons plus JS here would be strictly worse in every direction.
			 *
			 * DOM order 07 -> 23 in an RTL flex row lays out right to left on its own:
			 * 07:00 at the right, 23:00 at the left. A timeline in Hebrew reading order,
			 * with no direction branch and no coordinate maths anywhere.
			 */
			?>
			<fieldset class="byl__times" style="--i: 0">
				<legend class="visually-hidden"><?php esc_html_e( 'שעה ביום', 'hrdesign' ); ?></legend>
				<?php $first = true; ?>
				<?php foreach ( $stops as $key => $label ) : ?>
					<input
						type="radio"
						class="byl__radio visually-hidden"
						name="byl-time"
						id="byl-t-<?php echo esc_attr( $key ); ?>"
						value="<?php echo esc_attr( $key ); ?>"
						<?php checked( $first ); ?>>
					<label class="byl__time t-mono" for="byl-t-<?php echo esc_attr( $key ); ?>">
						<?php echo esc_html( $label ); ?>
					</label>
					<?php $first = false; ?>
				<?php endforeach; ?>
			</fieldset>

			<?php
			/*
			 * All four lines ship; CSS shows one. The inactive lines are visibility:hidden,
			 * not opacity:0 — opacity alone would leave three stale sentences in the
			 * accessibility tree for a screen reader to walk straight through.
			 */
			?>
			<div class="byl__copy" style="--i: 1">
				<?php foreach ( $stops as $key => $label ) : ?>
					<p class="byl__line" data-stop="<?php echo esc_attr( $key ); ?>">
						<?php echo esc_html( $scene['copy'][ $key ] ); ?>
					</p>
				<?php endforeach; ?>
			</div>

			<?php
			/*
			 * mini-card, and the reason is concrete rather than tidiness: cart.js:90 finds
			 * the product name for its toast via
			 * closest('.product-card, .mini-card, .pdp, .buy-bar'). A hand-rolled block here
			 * would ship a toast reading "נוסף לסל" with no product name — and that toast is
			 * the only confirmation a screen-reader user gets. It is a real accessibility
			 * regression that no manual test catches, because the cart still works. Reusing
			 * the card means cart.js is not touched at all.
			 */
			?>
			<div class="byl__buy" style="--i: 2">
				<?php get_template_part( 'templates/home/mini-card', null, array( 'product' => $scene['product'], 'variant' => 'inline' ) ); ?>
			</div>
		</div>
	</div>
</section>
