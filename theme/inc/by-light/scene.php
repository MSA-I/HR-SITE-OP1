<?php
/**
 * By Light — the scene post type.
 *
 * Four photographs of one room at four hours, one product, four lines of copy. Everything
 * the section needs is resolved here and handed to the template already hydrated: the
 * product is a crawlable link on the homepage and nothing is fetched on interaction,
 * because the whole control is CSS and there is no interaction to fetch on.
 *
 * ON THE FOUR IMAGES — state plainly, because it is easy to imply otherwise:
 * they are GENERATED DEPICTIONS of HR Design's room, not their photograph relit
 * pixel-for-pixel. The source is their real living room (product 5932) and their real
 * pendant (6659 / AM933), fed to an image-to-image model; the furniture is recognisable
 * and the lamp is genuinely theirs, but the model RE-RENDERED the scene rather than
 * preserving it. Verified: geometry holds across all four frames to 0px, so nothing morphs
 * when they cross-fade. See tools/seed/install-bylight.php and seed/bylight/p-*.txt for
 * the exact prompts.
 *
 * What deliberately does NOT live here: anything about how the light looks. That is baked
 * into the images now. The client owns the images, the product and the copy.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	function () {
		register_post_type(
			'hrd_byl_scene',
			array(
				'labels'       => array(
					'name'          => __( 'סצנות לפי אור', 'hrdesign' ),
					'singular_name' => __( 'סצנת לפי אור', 'hrdesign' ),
					'add_new_item'  => __( 'סצנה חדשה', 'hrdesign' ),
					'edit_item'     => __( 'עריכת סצנה', 'hrdesign' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_rest' => false,
				'menu_icon'    => 'dashicons-lightbulb',
				'supports'     => array( 'title', 'thumbnail' ),
			)
		);
	}
);

/**
 * The four stops, in DOM order.
 *
 * Order is load-bearing twice over. In an RTL flex row this lays out right to left on its
 * own — 07:00 at the right, 23:00 at the left — which is a timeline running in Hebrew
 * reading order, for free and with no direction branch anywhere. And it is the order the
 * demo plays.
 *
 * Four curated stops rather than a continuous slider, for a reason that is about quality
 * and not effort: a slider demands that every intermediate frame look like a photograph.
 * Four hand-authored grades that each look photographic is achievable. A hundred
 * interpolated ones is mush in the middle, and the mush is the cheap failure.
 *
 * @return array<string, string> Key => visible label.
 */
function hrd_byl_stops() {
	return array(
		'07' => '07:00',
		'12' => '12:00',
		'18' => '18:00',
		'23' => '23:00',
	);
}

/**
 * One frame of the stage.
 *
 * Exists to own the srcset cap, so the template does not have to reach for a global filter.
 *
 * THE CAP IS THE POINT, and it was measured. The stage is 62% of the grid, so at a 1440
 * viewport the real slot is ~806 CSS px. On a retina screen that asks for 1613 device px,
 * and the size ladder offers 1536w and then 2048w — so the browser skipped over 1536 and
 * pulled the 2048w file, four times, for 2.69MB. Capping the srcset at 1536 hands it the
 * 1536w file instead: 1.2MB, less than half, for a 5% under-sample on a photograph that no
 * eye can resolve. The full-size stays on disk for the admin and for whatever comes later;
 * the section simply stops offering it.
 *
 * TWO things are needed and the filter alone is not enough: wp_calculate_image_srcset()
 * always keeps the image being used as `src`, cap or no cap. Requesting 'full' therefore
 * re-admitted the 2048w file through the back door and the cap did nothing. Asking for the
 * 1536 size makes THAT the src, and then the cap can drop the one above it.
 *
 * max_srcset_image_width is site-wide, so it is added and removed around this one call —
 * three other people are working in this theme today and a leaked filter would quietly
 * shrink everyone else's images.
 *
 * @param int    $id    Attachment id.
 * @param string $alt   Alt text. Empty for every frame after the first: all four are the
 *                      same room, and describing each would make a screen reader read the
 *                      living room out four times to announce a lighting change.
 * @param string $stop  Stop key, for the CSS state machine.
 * @return string
 */
function hrd_byl_frame( $id, $alt, $stop ) {
	$cap = fn() => 1536;
	add_filter( 'max_srcset_image_width', $cap );

	$html = wp_get_attachment_image(
		$id,
		'1536x1536',
		false,
		array(
			'class'     => 'byl__frame',
			'data-stop' => $stop,
			'alt'       => $alt,
			'loading'   => 'lazy',
			'decoding'  => 'async',
			/*
			 * Measured, not guessed at 62vw. The stage is 62% of the GRID, and the grid is
			 * the viewport minus two page margins minus the column gap — so at 1440 the
			 * real slot is ~806px, which is 56vw and not 62vw. The grid also stops growing
			 * at --max-width, so past ~1600 the slot is a fixed 890px rather than a share
			 * of the viewport. Overstating this is how a browser talks itself into a
			 * bigger file than it can display.
			 */
			'sizes'     => '(max-width: 860px) 90vw, (min-width: 1600px) 890px, 56vw',
		)
	);

	remove_filter( 'max_srcset_image_width', $cap );
	return $html;
}

/**
 * The scene to feature on the homepage.
 *
 * @return int|null
 */
function hrd_byl_active_scene_id() {
	$scenes = get_posts(
		array(
			'post_type'      => 'hrd_byl_scene',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'post_status'    => 'publish',
		)
	);

	return $scenes ? (int) $scenes[0] : null;
}

/**
 * Default copy.
 *
 * Still the client's call in the end — these four lines are the highest-leverage twenty
 * words in the section: it spends three of its four states telling you that you do not
 * need the product yet, and only asks for money at 23:00. That is the whole argument.
 *
 * Two things changed from the first draft, and neither is "better writing" — the earlier
 * lines were fine. Each line now names its own hour, so the sentence and the stop the
 * reader just pressed say the same thing instead of running alongside each other. And the
 * four lengths vary (24/35/43/25 characters) rather than landing as four sentences of the
 * same weight, which is the cadence that made the whole page read as generated.
 *
 * @return array<string, string>
 */
function hrd_byl_default_copy() {
	return array(
		'07' => __( 'שבע. החלון עושה הכל לבד.', 'hrdesign' ),
		'12' => __( 'בצהריים אין צללים, ואין מה להדליק.', 'hrdesign' ),
		'18' => __( 'שש, והאור מתחיל להצהיב. כאן המנורה נכנסת.', 'hrdesign' ),
		'23' => __( 'עכשיו דולק רק מה שבחרתם.', 'hrdesign' ),
	);
}

/**
 * Everything the front end needs for one scene.
 *
 * @param int $scene_id Scene post id.
 * @return array|null
 */
function hrd_byl_payload( $scene_id ) {
	$product = wc_get_product( (int) get_post_meta( $scene_id, '_hrd_byl_product', true ) );
	if ( ! $product || ! $product->is_visible() ) {
		return null;
	}

	/*
	 * All four stops or nothing.
	 *
	 * A partial set is the one failure this section must not ship: three hours and a hole
	 * still renders, still looks deliberate, and quietly breaks the argument — the whole
	 * point is the walk from "you do not need this" to "now you do". Fail closed and
	 * render nothing rather than render a lie.
	 */
	$images = array();
	foreach ( array_keys( hrd_byl_stops() ) as $stop ) {
		$id = (int) get_post_meta( $scene_id, '_hrd_byl_img_' . $stop, true );
		if ( ! $id || ! wp_get_attachment_image_url( $id, 'full' ) ) {
			return null;
		}
		$images[ $stop ] = $id;
	}

	$copy    = hrd_byl_default_copy();
	$authored = array();
	foreach ( array_keys( hrd_byl_stops() ) as $stop ) {
		$value = get_post_meta( $scene_id, '_hrd_byl_copy_' . $stop, true );
		$authored[ $stop ] = $value ? $value : $copy[ $stop ];
	}

	return array(
		'images' => $images,
		'copy'   => $authored,

		'product' => array(
			'id'         => $product->get_id(),
			'name'       => $product->get_name(),
			'price_html' => $product->get_price_html(),
			'permalink'  => get_permalink( $product->get_id() ),
			'thumb'      => get_the_post_thumbnail_url( $product->get_id(), 'woocommerce_thumbnail' ) ?: '',
			'photo_type' => hrd_photo_type( $product ),
			'sku'        => $product->get_sku(),
			'dims'       => hrd_product_dims( $product ),
			'in_stock'   => $product->is_in_stock(),
			/*
			 * Both keys are load-bearing and mini-card.php is fail-closed on them: a
			 * payload that omits either one degrades to a plain link everywhere instead of
			 * a quick-add. A variable product must link out rather than silently add the
			 * wrong variant, and a price-on-request product is in stock yet cannot be
			 * bought at all.
			 */
			'purchasable' => $product->is_purchasable(),
			'variable'    => hrd_is_variable( $product ),
		),
	);
}
