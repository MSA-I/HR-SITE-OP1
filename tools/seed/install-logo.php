<?php
/**
 * Installs the brand logo and sets it as the custom logo.
 *
 *   docker compose exec wpcli wp eval-file /tools/seed/install-logo.php
 *
 * Separate from the catalogue seed for the same reason install-hero.php is: the seeder
 * caps images at 1024w, which is right for 500 product cards and wrong for a mark that
 * has to stay crisp on a 2x display at 40px tall. This one is small enough that the cap
 * would never bite, but it also is not a product, and putting it through the product
 * importer would file it against a _hrd_src_id it does not have.
 *
 * Pulls straight from the live site rather than from /seed, because unlike the hero
 * there is no fetch step to run first — it is one 40KB PNG.
 */

defined( 'ABSPATH' ) || die( 'wp eval-file only' );

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';

const HRD_LOGO_URL = 'https://hr-design.co.il/wp-content/uploads/2021/12/156828179_245748037204194_969586242701827269_n-big.png';

// Marks the attachment as ours so a re-run replaces instead of accumulating, the same
// way the hero uses _hrd_hero_src.
const HRD_LOGO_META = '_hrd_logo_src';

foreach ( get_posts(
	array(
		'post_type'   => 'attachment',
		'meta_key'    => HRD_LOGO_META,
		'meta_value'  => HRD_LOGO_URL,
		'numberposts' => -1,
		'fields'      => 'ids',
	)
) as $old ) {
	wp_delete_attachment( $old, true );
}

$tmp = download_url( HRD_LOGO_URL );
if ( is_wp_error( $tmp ) ) {
	WP_CLI::error( 'download failed: ' . $tmp->get_error_message() );
}

/**
 * Trim fully transparent margin.
 *
 * The source is 720x520 but the artwork's bounding box is only 394x287 at (163,113) —
 * 69.8% of the canvas is empty. That padding is not free: CSS sizes the *file*, so a
 * 96px-tall box rendered the actual mark at 53px and the Hebrew tagline inside it at
 * roughly 6px, which is the "illegible at any DPR" problem. Trimming makes the box and
 * the artwork the same thing, so 96px means 96px.
 *
 * This removes empty space and nothing else: no scaling, no recolour, no re-encode of
 * the ink, and no crop into the artwork — the bounding box is computed from alpha, so a
 * pixel the designer drew cannot be cut. It is the one edit to a client's logo that
 * changes nothing about the logo.
 *
 * @param string $file PNG path.
 * @return array{0:string,1:array,2:array} Trimmed bits, source size, trimmed size.
 */
function hrd_trim_png( $file ) {
	$src = imagecreatefrompng( $file );
	if ( ! $src ) {
		WP_CLI::error( 'not a readable PNG' );
	}

	$w = imagesx( $src );
	$h = imagesy( $src );

	$min_x = $w;
	$min_y = $h;
	$max_x = -1;
	$max_y = -1;

	// 127 is fully transparent in GD's 7-bit alpha; anything less is ink worth keeping.
	for ( $y = 0; $y < $h; $y++ ) {
		for ( $x = 0; $x < $w; $x++ ) {
			$alpha = ( imagecolorat( $src, $x, $y ) >> 24 ) & 0x7F;
			if ( $alpha >= 127 ) {
				continue;
			}
			$min_x = min( $min_x, $x );
			$max_x = max( $max_x, $x );
			$min_y = min( $min_y, $y );
			$max_y = max( $max_y, $y );
		}
	}

	if ( $max_x < 0 ) {
		WP_CLI::error( 'image is fully transparent' );
	}

	$tw = $max_x - $min_x + 1;
	$th = $max_y - $min_y + 1;

	$out = imagecreatetruecolor( $tw, $th );
	imagealphablending( $out, false );
	imagesavealpha( $out, true );
	imagefill( $out, 0, 0, imagecolorallocatealpha( $out, 0, 0, 0, 127 ) );
	imagecopy( $out, $src, 0, 0, $min_x, $min_y, $tw, $th );

	ob_start();
	imagepng( $out, null, 9 );
	$bits = ob_get_clean();

	imagedestroy( $src );
	imagedestroy( $out );

	return array( $bits, array( $w, $h ), array( $tw, $th ) );
}

list( $bits, $src_size, $trim_size ) = hrd_trim_png( $tmp );
unlink( $tmp );

WP_CLI::log(
	sprintf(
		'trimmed transparent margin: %dx%d -> %dx%d',
		$src_size[0],
		$src_size[1],
		$trim_size[0],
		$trim_size[1]
	)
);

$upload = wp_upload_bits( 'hr-design-logo.png', null, $bits );
if ( ! empty( $upload['error'] ) ) {
	WP_CLI::error( $upload['error'] );
}

$attachment_id = wp_insert_attachment(
	array(
		'post_mime_type' => 'image/png',
		'post_title'     => 'HR Design — logo',
		'post_status'    => 'inherit',
	),
	$upload['file']
);

if ( is_wp_error( $attachment_id ) ) {
	WP_CLI::error( $attachment_id->get_error_message() );
}

$meta = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
wp_update_attachment_metadata( $attachment_id, $meta );

update_post_meta( $attachment_id, HRD_LOGO_META, HRD_LOGO_URL );

// The logo is a wordmark: it says "HR Design" in the image, so alt text that also says
// "HR Design" is the name read twice. The link it lives in is the home link, and that is
// what the alt describes.
update_post_meta( $attachment_id, '_wp_attachment_image_alt', 'HR Design' );

/*
 * Footer, not header, and this is the opposite of what it looks like it should be.
 *
 * The asset reads as dark at a glance because the gold "R" is the loudest thing in it.
 * It is not. Measured over the 30,722 opaque pixels (the other 91.8% of the file is
 * transparent padding): 51.4% of the ink is light, 48.3% is the mid-gold, 0.4% is dark.
 * Mean luma 185. The outlined "H" and the whole Hebrew tagline are white.
 *
 * Against the theme's grounds that means white ink lands at 1.18:1 on --cream-100 and
 * 11.48:1 on --brown-700. On the header, half the logo is invisible and it renders as a
 * gold sliver next to a ghost. On the footer it is clean.
 *
 * So this goes in its own option rather than custom_logo. custom_logo is the header slot
 * and stays empty until there is an asset that survives cream — at which point the client
 * uploads it in the Customizer and header.php lights up with no code change, because it
 * already branches on has_custom_logo().
 */
update_option( 'hrd_footer_logo', $attachment_id );

// Undo the earlier assignment if this script already ran when it still set custom_logo.
if ( (int) get_theme_mod( 'custom_logo' ) === (int) $attachment_id ) {
	remove_theme_mod( 'custom_logo' );
}

WP_CLI::success(
	sprintf(
		'logo attachment %d — %dx%d, set as hrd_footer_logo',
		$attachment_id,
		$meta['width'] ?? 0,
		$meta['height'] ?? 0
	)
);

WP_CLI::log( 'note: light asset (mean luma 185) — footer only, 11.48:1 on brown.' );
WP_CLI::warning( 'header still has no logo. Ask the client for a DARK version for the cream header.' );
