<?php
/**
 * Hotspot authoring: click the image to place a pin, search a product, drag to adjust.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'add_meta_boxes',
	function () {
		add_meta_box(
			'hrd-hotspots',
			__( 'נקודות חמות', 'hrdesign' ),
			'hrd_render_hotspot_metabox',
			'hrd_room_scene',
			'normal',
			'high'
		);

		add_meta_box(
			'hrd-scene-layers',
			__( 'שכבות פרלקסה', 'hrdesign' ),
			'hrd_render_layers_metabox',
			'hrd_room_scene',
			'side'
		);
	}
);

/**
 * The picker.
 *
 * @param WP_Post $post Scene.
 */
function hrd_render_hotspot_metabox( $post ) {
	wp_nonce_field( 'hrd_hotspots', 'hrd_hotspots_nonce' );

	$hotspots = hrd_scene_hotspots( $post->ID );
	$image = get_the_post_thumbnail_url( $post->ID, 'large' );
	?>

	<?php if ( ! $image ) : ?>
		<p class="hrd-notice"><?php esc_html_e( 'הגדירו תמונה ראשית לסצנה כדי למקם נקודות.', 'hrdesign' ); ?></p>
	<?php else : ?>
		<p class="description">
			<?php esc_html_e( 'לחצו על התמונה כדי להוסיף נקודה. גררו נקודה כדי לדייק. הקואורדינטות נשמרות באחוזים, כך שהן נכונות בכל רוחב.', 'hrdesign' ); ?>
		</p>

		<div class="hrd-picker" data-hrd-picker>
			<div class="hrd-picker__stage" data-hrd-stage>
				<img src="<?php echo esc_url( $image ); ?>" alt="" draggable="false">
			</div>
			<div class="hrd-picker__list" data-hrd-list></div>
		</div>

		<textarea name="hrd_hotspots" data-hrd-data hidden><?php echo esc_textarea( wp_json_encode( $hotspots ) ); ?></textarea>
	<?php endif; ?>
	<?php
}

/**
 * Optional cut-out layers for the parallax.
 *
 * @param WP_Post $post Scene.
 */
function hrd_render_layers_metabox( $post ) {
	$fields = array(
		'_hrd_layer_bg'   => __( 'רקע (קיר, חלון)', 'hrdesign' ),
		'_hrd_layer_mid'  => __( 'אמצע (ספה, שולחן)', 'hrdesign' ),
		'_hrd_layer_fore' => __( 'חזית (שטיח, צמח)', 'hrdesign' ),
	);
	?>
	<p class="description">
		<?php esc_html_e( 'אופציונלי. בלי שכבות, הסצנה עדיין עובדת — התמונה הראשית משמשת כשכבה אחת והנקודות נעות מעליה.', 'hrdesign' ); ?>
	</p>
	<?php foreach ( $fields as $key => $label ) : ?>
		<p>
			<label for="<?php echo esc_attr( $key ); ?>"><strong><?php echo esc_html( $label ); ?></strong></label><br>
			<input type="number" class="widefat" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>"
				value="<?php echo esc_attr( get_post_meta( $post->ID, $key, true ) ); ?>"
				placeholder="<?php esc_attr_e( 'מזהה קובץ מדיה', 'hrdesign' ); ?>">
		</p>
	<?php endforeach; ?>

	<p>
		<label for="_hrd_scene_mobile"><strong><?php esc_html_e( 'חיתוך למובייל (4:5)', 'hrdesign' ); ?></strong></label><br>
		<input type="number" class="widefat" id="_hrd_scene_mobile" name="_hrd_scene_mobile"
			value="<?php echo esc_attr( get_post_meta( $post->ID, '_hrd_scene_mobile', true ) ); ?>"
			placeholder="<?php esc_attr_e( 'מזהה קובץ מדיה', 'hrdesign' ); ?>">
	</p>
	<?php
}

add_action(
	'save_post_hrd_room_scene',
	function ( $post_id ) {
		if ( ! isset( $_POST['hrd_hotspots_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['hrd_hotspots_nonce'] ), 'hrd_hotspots' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['hrd_hotspots'] ) ) {
			$raw = json_decode( wp_unslash( $_POST['hrd_hotspots'] ), true );
			$clean = array();

			foreach ( is_array( $raw ) ? $raw : array() as $spot ) {
				$product_id = absint( $spot['product_id'] ?? 0 );
				if ( ! $product_id ) {
					continue;
				}

				$clean[] = array(
					'id'         => sanitize_key( $spot['id'] ?? uniqid( 'h' ) ),
					// Clamp rather than trust: a dragged pin can overshoot the stage.
					'x_d'        => max( 0, min( 100, (float) ( $spot['x_d'] ?? 50 ) ) ),
					'y_d'        => max( 0, min( 100, (float) ( $spot['y_d'] ?? 50 ) ) ),
					'x_m'        => max( 0, min( 100, (float) ( $spot['x_m'] ?? $spot['x_d'] ?? 50 ) ) ),
					'y_m'        => max( 0, min( 100, (float) ( $spot['y_m'] ?? $spot['y_d'] ?? 50 ) ) ),
					'layer'      => in_array( $spot['layer'] ?? 'mid', array( 'bg', 'mid', 'fore' ), true ) ? $spot['layer'] : 'mid',
					'product_id' => $product_id,
				);
			}

			update_post_meta( $post_id, '_hrd_hotspots', wp_json_encode( $clean ) );
		}

		foreach ( array( '_hrd_layer_bg', '_hrd_layer_mid', '_hrd_layer_fore', '_hrd_scene_mobile' ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$value = absint( $_POST[ $key ] );
				$value ? update_post_meta( $post_id, $key, $value ) : delete_post_meta( $post_id, $key );
			}
		}
	}
);

/** Product search for the picker. */
add_action(
	'wp_ajax_hrd_search_products',
	function () {
		check_ajax_referer( 'hrd_hotspots', 'nonce' );

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
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || 'hrd_room_scene' !== get_post_type() ) {
			return;
		}

		wp_enqueue_style( 'hrd-picker', HRD_URI . '/assets/admin/picker.css', array(), HRD_VERSION );
		wp_enqueue_script( 'hrd-picker', HRD_URI . '/assets/admin/picker.js', array(), HRD_VERSION, true );
		wp_localize_script(
			'hrd-picker',
			'hrdPicker',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'hrd_hotspots' ),
				'i18n'  => array(
					'search'  => __( 'חיפוש מוצר…', 'hrdesign' ),
					'remove'  => __( 'הסרה', 'hrdesign' ),
					'noLink'  => __( 'לא נבחר מוצר', 'hrdesign' ),
					'layer'   => __( 'שכבה', 'hrdesign' ),
				),
			)
		);
	}
);
