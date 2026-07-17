<?php
/**
 * By Light authoring: four images, one product, four lines.
 *
 * Far smaller than the hotspot picker it replaces, because the section is. There is no
 * anchor to place any more — the lamp is inside the photographs — so there is no picker, no
 * drag, and no coordinate maths. wp_ajax_hrd_search_products is carried over verbatim from
 * the old metabox; it was good and it is still exactly what the product field needs.
 *
 * ONE THING THIS SCREEN MUST SAY OUT LOUD, and does: the four images are a matched set.
 * They were generated from one another so the geometry holds, and swapping one for a frame
 * from a different run puts furniture in two places at once — which reads as the room
 * morphing, not as the light changing. tools/seed/install-bylight.php installs all four
 * together for exactly that reason.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'add_meta_boxes',
	function () {
		add_meta_box(
			'hrd-byl-scene',
			__( 'הסצנה', 'hrdesign' ),
			'hrd_byl_render_scene_metabox',
			'hrd_byl_scene',
			'normal',
			'high'
		);

		add_meta_box(
			'hrd-byl-copy',
			__( 'הטקסט לפי שעה', 'hrdesign' ),
			'hrd_byl_render_copy_metabox',
			'hrd_byl_scene',
			'normal'
		);
	}
);

/**
 * The product, and the four frames.
 *
 * @param WP_Post $post Scene.
 */
function hrd_byl_render_scene_metabox( $post ) {
	wp_nonce_field( 'hrd_byl', 'hrd_byl_nonce' );

	$product_id = (int) get_post_meta( $post->ID, '_hrd_byl_product', true );
	$product    = $product_id ? wc_get_product( $product_id ) : null;
	?>

	<p class="hrd-notice">
		<?php esc_html_e( 'ארבע התמונות הן סט אחד. הן נוצרו זו מזו כדי שהחדר יישאר זהה בין השעות — החלפה של תמונה אחת בפריים מריצה אחרת תזיז רהיטים באמצע המעבר, וזה נקרא כחדר שמתעוות ולא כאור שמשתנה.', 'hrdesign' ); ?>
	</p>

	<p>
		<label for="hrd-byl-search"><strong><?php esc_html_e( 'המוצר הנמכר', 'hrdesign' ); ?></strong></label>
		<span class="hrd-row__main">
			<input type="search" class="hrd-row__search" id="hrd-byl-search" data-hrd-search
				placeholder="<?php esc_attr_e( 'חיפוש מוצר…', 'hrdesign' ); ?>"
				value="<?php echo esc_attr( $product ? $product->get_name() : '' ); ?>">
			<span class="hrd-row__results" hidden data-hrd-results></span>
		</span>
		<?php if ( ! $product ) : ?>
			<em class="hrd-row__empty"><?php esc_html_e( 'לא נבחר מוצר — הסקשן לא יוצג.', 'hrdesign' ); ?></em>
		<?php endif; ?>
	</p>

	<input type="hidden" name="_hrd_byl_product" value="<?php echo esc_attr( $product_id ); ?>" data-hrd-product>

	<div class="hrd-stops">
		<?php foreach ( hrd_byl_stops() as $stop => $label ) : ?>
			<?php
			$key = '_hrd_byl_img_' . $stop;
			$id  = (int) get_post_meta( $post->ID, $key, true );
			$src = $id ? wp_get_attachment_image_url( $id, 'medium' ) : '';
			?>
			<div class="hrd-stop">
				<strong><?php echo esc_html( $label ); ?></strong>
				<div class="hrd-stop__thumb">
					<?php if ( $src ) : ?>
						<img src="<?php echo esc_url( $src ); ?>" alt="">
					<?php else : ?>
						<span class="hrd-stop__empty"><?php esc_html_e( 'חסר', 'hrdesign' ); ?></span>
					<?php endif; ?>
				</div>
				<label class="screen-reader-text" for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
				<input type="number" class="widefat" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>"
					value="<?php echo esc_attr( $id ? $id : '' ); ?>"
					placeholder="<?php esc_attr_e( 'מזהה מדיה', 'hrdesign' ); ?>">
			</div>
		<?php endforeach; ?>
	</div>

	<p class="description">
		<?php
		printf(
			/* translators: %s: the wp-cli command that installs the set. */
			esc_html__( 'הדרך המומלצת למלא את ארבעתן: %s — הסקריפט ממיר, מייבא ומחבר את הסט כולו בבת אחת.', 'hrdesign' ),
			'<code>wp eval-file /tools/seed/install-bylight.php</code>'
		);
		?>
	</p>
	<?php
}

/**
 * The four lines.
 *
 * @param WP_Post $post Scene.
 */
function hrd_byl_render_copy_metabox( $post ) {
	$defaults = hrd_byl_default_copy();
	?>
	<p class="description">
		<?php esc_html_e( 'שורה אחת לכל שעה. הסקשן מבלה שלוש משעותיו באמירה שהמוצר עוד לא נחוץ, ומבקש לקנות רק ב-23:00 — זה הטיעון, אז הניסוח חשוב.', 'hrdesign' ); ?>
	</p>
	<?php foreach ( hrd_byl_stops() as $key => $label ) : ?>
		<p>
			<label for="_hrd_byl_copy_<?php echo esc_attr( $key ); ?>"><strong><?php echo esc_html( $label ); ?></strong></label><br>
			<textarea class="widefat" rows="2" id="_hrd_byl_copy_<?php echo esc_attr( $key ); ?>" name="_hrd_byl_copy_<?php echo esc_attr( $key ); ?>"
				placeholder="<?php echo esc_attr( $defaults[ $key ] ); ?>"><?php echo esc_textarea( get_post_meta( $post->ID, '_hrd_byl_copy_' . $key, true ) ); ?></textarea>
		</p>
	<?php endforeach; ?>
	<?php
}

add_action(
	'save_post_hrd_byl_scene',
	function ( $post_id ) {
		if ( ! isset( $_POST['hrd_byl_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['hrd_byl_nonce'] ), 'hrd_byl' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$keys = array( '_hrd_byl_product' );
		foreach ( array_keys( hrd_byl_stops() ) as $stop ) {
			$keys[] = '_hrd_byl_img_' . $stop;
		}

		foreach ( $keys as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$value = absint( $_POST[ $key ] );
				$value ? update_post_meta( $post_id, $key, $value ) : delete_post_meta( $post_id, $key );
			}
		}

		foreach ( array_keys( hrd_byl_stops() ) as $stop ) {
			$key = '_hrd_byl_copy_' . $stop;
			if ( ! isset( $_POST[ $key ] ) ) {
				continue;
			}
			$value = sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) );
			// An emptied field falls back to the default rather than storing "", so clearing
			// a line restores the placeholder instead of blanking the stop.
			$value ? update_post_meta( $post_id, $key, $value ) : delete_post_meta( $post_id, $key );
		}
	}
);

/**
 * Product search for the picker.
 *
 * Carried over verbatim from the hotspot metabox — it was already right.
 */
add_action(
	'wp_ajax_hrd_search_products',
	function () {
		check_ajax_referer( 'hrd_byl', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error();
		}

		$term = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) );
		if ( mb_strlen( $term ) < 2 ) {
			wp_send_json_success( array() );
		}

		$products = wc_get_products(
			array(
				'limit'  => 12,
				's'      => $term,
				'status' => 'publish',
			)
		);

		wp_send_json_success(
			array_map(
				fn( $product ) => array(
					'id'    => $product->get_id(),
					'name'  => $product->get_name(),
					'thumb' => get_the_post_thumbnail_url( $product->get_id(), 'thumbnail' ) ?: '',
					'price' => wp_strip_all_tags( $product->get_price_html() ),
				),
				$products
			)
		);
	}
);

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || 'hrd_byl_scene' !== get_post_type() ) {
			return;
		}

		wp_enqueue_style( 'hrd-picker', HRD_URI . '/assets/admin/picker.css', array(), HRD_VERSION );
		wp_enqueue_script( 'hrd-picker', HRD_URI . '/assets/admin/picker.js', array(), HRD_VERSION, true );
		wp_localize_script(
			'hrd-picker',
			'hrdPicker',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'hrd_byl' ),
			)
		);
	}
);
