<?php
/**
 * Installs the two brand marks: the transparent wordmark on the footer, the live site's
 * own header tile on the header.
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
 * there is no fetch step to run first — two small files.
 *
 * These are two different assets, not two sizes of one, and that was the thing this
 * script originally got wrong. See the header block at the bottom.
 */

defined( 'ABSPATH' ) || die( 'wp eval-file only' );

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';

const HRD_LOGO_URL = 'https://hr-design.co.il/wp-content/uploads/2021/12/156828179_245748037204194_969586242701827269_n-big.png';

// The live site's header logo. A different file from the one above, not a crop of it.
const HRD_HEADER_LOGO_URL = 'https://hr-design.co.il/wp-content/uploads/2021/12/109838972_128419198937079_6705625843572566784_n.jpg';

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
 * So this goes in its own option rather than custom_logo. The header is fed separately,
 * below, from the asset the live site actually uses there.
 */
update_option( 'hrd_footer_logo', $attachment_id );

WP_CLI::success(
	sprintf(
		'footer logo attachment %d — %dx%d, set as hrd_footer_logo',
		$attachment_id,
		$meta['width'] ?? 0,
		$meta['height'] ?? 0
	)
);

/*
 * The header mark.
 *
 * This script used to end by asking the client for a dark version of the mark for the
 * cream header. That request was unnecessary: the live site has been solving this since
 * 2021, and not the way we assumed. It does not own a dark mark. It takes the same white
 * and gold artwork and bakes it onto a dark grey plate — an opaque 148x146 JPG, ground
 * measured at #2E302F, mean luma 64.4. The mark never had to survive cream. It was given
 * something else to sit on.
 *
 * So the header gets their tile, unmodified: same file, same square, same ground. No
 * recolour and no re-cut of a client's brand asset to suit our palette, and no invented
 * dark variant of a mark that does not have one.
 *
 * The known cost, accepted deliberately: header.css gives the logo 40px of height, and
 * the live site renders this tile at 148px. At 40 the Hebrew tagline inside it lands at
 * roughly 3px and is decoration rather than text — which is why the alt text below
 * carries the name and the tagline is not transcribed into it. If the tagline needs to
 * be legible, the header row is what has to change, not the file.
 */
foreach ( get_posts(
	array(
		'post_type'   => 'attachment',
		'meta_key'    => HRD_LOGO_META,
		'meta_value'  => HRD_HEADER_LOGO_URL,
		'numberposts' => -1,
		'fields'      => 'ids',
	)
) as $old ) {
	wp_delete_attachment( $old, true );
}

$header_tmp = download_url( HRD_HEADER_LOGO_URL );
if ( is_wp_error( $header_tmp ) ) {
	WP_CLI::error( 'header logo download failed: ' . $header_tmp->get_error_message() );
}

// Opaque JPG: nothing to trim. hrd_trim_png() reads GD's alpha channel and there is none.
$header_upload = wp_upload_bits( 'hr-design-logo-header.jpg', null, file_get_contents( $header_tmp ) );
unlink( $header_tmp );

if ( ! empty( $header_upload['error'] ) ) {
	WP_CLI::error( $header_upload['error'] );
}

$header_id = wp_insert_attachment(
	array(
		'post_mime_type' => 'image/jpeg',
		'post_title'     => 'HR Design — header logo',
		'post_status'    => 'inherit',
	),
	$header_upload['file']
);

if ( is_wp_error( $header_id ) ) {
	WP_CLI::error( $header_id->get_error_message() );
}

$header_meta = wp_generate_attachment_metadata( $header_id, $header_upload['file'] );
wp_update_attachment_metadata( $header_id, $header_meta );

update_post_meta( $header_id, HRD_LOGO_META, HRD_HEADER_LOGO_URL );
update_post_meta( $header_id, '_wp_attachment_image_alt', 'HR Design' );

set_theme_mod( 'custom_logo', $header_id );

WP_CLI::success(
	sprintf(
		'header logo attachment %d — %dx%d, set as custom_logo',
		$header_id,
		$header_meta['width'] ?? 0,
		$header_meta['height'] ?? 0
	)
);

WP_CLI::log( 'note: header tile carries its own #2E302F ground (luma 64.4) — it does not rely on the header colour.' );
