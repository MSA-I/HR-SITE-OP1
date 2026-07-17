<?php
/**
 * Classifies each product photo as a studio cutout or a scene.
 *
 *   docker compose exec wpcli wp eval-file /tools/seed/classify-photos.php
 *   docker compose exec wpcli wp eval-file /tools/seed/classify-photos.php dry-run
 *
 * The plate design assumed the catalogue was shot on white, so mix-blend-mode: multiply
 * would drop the backdrop and leave the product on a coloured plane. Measured reality:
 * only ~26% are on true white; ~66% have a non-uniform backdrop because they are room
 * photographs, not studio shots. Multiplying a room photo darkens the whole frame and
 * dissolves it into the tint.
 *
 * So the card branches on measured data rather than on an assumption:
 *   studio  -> contained cutout, multiplied onto the accent tint (the signature plate)
 *   scene   -> the photograph fills the frame, no blending
 *
 * Both read as deliberate; the type, tint frame and hairlines unify them.
 *
 * A backdrop is white OR ABSENT. The first pass reasoned about luma alone, which is blind
 * to the second case: on a transparent pixel imagecolorat() returns RGB 0, so a knocked
 * out product scored as pitch black, failed the white test and was filed as a room
 * photograph. Every transparent PNG in the catalogue was mislabelled "scene" — and the
 * damage was not cosmetic, because that meta is the signal used to pick REAL room
 * photographs. It briefed a lamp cutout as a lighting scene once already.
 *
 * The dry-run argument prints the delta and writes nothing. This meta drives rendering in
 * six places, so a re-run is worth looking at before it lands. It is bare rather than
 * --dry-run because WP-CLI parses anything dash-prefixed as its own flag and rejects it
 * before the file is ever read.
 *
 * Idempotent. Re-run after a reseed.
 */

defined( 'ABSPATH' ) || die( 'wp eval-file only' );

/** A backdrop this bright reads as white once multiplied. */
const HRD_WHITE_MIN = 246;

/** Corner disagreement above this means a gradient, a floor, or a wall — not a backdrop. */
const HRD_SPREAD_MAX = 10;

/**
 * Alpha at which a pixel stops being evidence of anything.
 *
 * GD packs alpha into bits 24-30 of imagecolorat(): 0 opaque, 127 invisible. A pixel this
 * transparent has no colour worth reading, so it is counted as backdrop and never fed to
 * the luma test. JPEGs report 0 here for every pixel, so nothing shot on white is
 * affected by any of this.
 */
const HRD_ALPHA_CLEAR = 96;

/**
 * Minimum share of the whole frame that must be backdrop: near-white, or transparent.
 *
 * The corner test alone is not enough, and composing the scene proved it: a ROOM
 * photograph placed on a white canvas has four white corners and sails through, so the
 * composer pasted a laundry room into the living room. A real studio cut-out is mostly
 * backdrop — 35%+ of the frame. A room shot with white margins is far less.
 */
const HRD_WHITE_SHARE_MIN = 0.35;

/*
 * Why a transparent knockout is filed as "studio" and not as a third value.
 *
 * It is one on the merits: a product floating on nothing is the definition of a cutout,
 * and multiply onto the accent tint is the branch built for it. A fully transparent pixel
 * under multiply leaves the backdrop untouched, so the tint shows through exactly as it
 * does around a white-backdrop cutout.
 *
 * But the deciding reason is mechanical. hrd_photo_type() in theme/inc/woocommerce/badges.php
 * reads `'studio' === $meta ? 'studio' : 'scene'`, and six stylesheets branch on those two
 * classes. A third value would land in the else and render as a scene — silently
 * reintroducing this exact bug through the fix for it.
 */

$dry_run = in_array( 'dry-run', $args, true );

$ids = get_posts(
	array(
		'post_type'      => 'product',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);

$studio = 0;
$scene = 0;
$skipped = 0;
$changed = array();

foreach ( $ids as $product_id ) {
	$attachment_id = get_post_thumbnail_id( $product_id );
	if ( ! $attachment_id ) {
		$skipped++;
		continue;
	}

	$path = get_attached_file( $attachment_id );
	if ( ! $path || ! file_exists( $path ) ) {
		$skipped++;
		continue;
	}

	$image = @imagecreatefromstring( file_get_contents( $path ) );
	if ( ! $image ) {
		$skipped++;
		continue;
	}

	// On a palette image imagecolorat() returns an index, not a colour, so every shift
	// below would read a table offset as if it were red. Normalising once is cheaper than
	// branching on imageistruecolor() at every sample.
	if ( ! imageistruecolor( $image ) ) {
		imagepalettetotruecolor( $image );
	}

	$w = imagesx( $image );
	$h = imagesy( $image );

	/** One sample: its transparency, and the colour only if there is one worth reading. */
	$sample = function ( $x, $y ) use ( $image ) {
		$rgb   = imagecolorat( $image, $x, $y );
		$alpha = ( $rgb >> 24 ) & 0x7F;

		return array(
			'clear' => $alpha >= HRD_ALPHA_CLEAR,
			'luma'  => ( ( ( $rgb >> 16 ) & 0xFF ) + ( ( $rgb >> 8 ) & 0xFF ) + ( $rgb & 0xFF ) ) / 3,
		);
	};

	// Sample inside the corners rather than exactly on them: JPEG ringing at the very
	// edge is not representative of the backdrop.
	$inset = max( 4, (int) ( min( $w, $h ) * 0.02 ) );
	$corners = array(
		$sample( $inset, $inset ),
		$sample( $w - $inset, $inset ),
		$sample( $inset, $h - $inset ),
		$sample( $w - $inset, $h - $inset ),
	);

	// A corner is backdrop if it is transparent or near-white. Anything else is the
	// product, a wall or a floor, and disqualifies the frame however the rest scores.
	$opaque = array();
	$corner_content = false;
	foreach ( $corners as $corner ) {
		if ( $corner['clear'] ) {
			continue;
		}
		$opaque[] = $corner['luma'];
		if ( $corner['luma'] < HRD_WHITE_MIN ) {
			$corner_content = true;
		}
	}
	sort( $opaque );

	// Median and spread over the corners that actually have a colour. With every corner
	// transparent there is no backdrop luma to measure, and -1 says so — 0 would be the
	// original bug restated, a knockout claiming to be pitch black.
	//
	// A true median, because the count is no longer always four: transparent corners drop
	// out, so this has to stay honest at one, two and three as well.
	$count    = count( $opaque );
	$backdrop = -1;
	if ( $count ) {
		$mid      = (int) floor( $count / 2 );
		$backdrop = $count % 2 ? $opaque[ $mid ] : ( $opaque[ $mid - 1 ] + $opaque[ $mid ] ) / 2;
	}
	$spread = $opaque ? end( $opaque ) - $opaque[0] : 0;

	// How much of the frame is actually backdrop? Sample a grid rather than every
	// pixel — 32x32 is plenty to separate "mostly white" from "a photo with margins".
	$white = 0;
	$clear = 0;
	$samples = 0;
	for ( $gx = 0; $gx < 32; $gx++ ) {
		for ( $gy = 0; $gy < 32; $gy++ ) {
			$px = (int) ( $w * ( $gx + 0.5 ) / 32 );
			$py = (int) ( $h * ( $gy + 0.5 ) / 32 );
			$samples++;

			$point = $sample( $px, $py );
			if ( $point['clear'] ) {
				$clear++;
			} elseif ( $point['luma'] >= HRD_WHITE_MIN ) {
				$white++;
			}
		}
	}
	$white_share = $samples ? $white / $samples : 0;
	$alpha_share = $samples ? $clear / $samples : 0;

	$is_studio = ! $corner_content
		&& ( ! $opaque || $backdrop >= HRD_WHITE_MIN )
		&& $spread <= HRD_SPREAD_MAX
		&& ( $white_share + $alpha_share ) >= HRD_WHITE_SHARE_MIN;

	$type = $is_studio ? 'studio' : 'scene';
	$was  = get_post_meta( $product_id, '_hrd_photo_type', true );

	if ( $was && $was !== $type ) {
		$changed[] = sprintf(
			'  %-5s %-6s -> %-6s  white %.3f alpha %.3f  %s',
			get_post_meta( $product_id, '_hrd_src_id', true ),
			$was,
			$type,
			$white_share,
			$alpha_share,
			mb_substr( html_entity_decode( get_the_title( $product_id ) ), 0, 38 )
		);
	}

	if ( ! $dry_run ) {
		update_post_meta( $product_id, '_hrd_photo_type', $type );
		update_post_meta( $product_id, '_hrd_photo_backdrop', (int) round( $backdrop ) );
		update_post_meta( $product_id, '_hrd_photo_white_share', round( $white_share, 3 ) );
		update_post_meta( $product_id, '_hrd_photo_alpha_share', round( $alpha_share, 3 ) );
	}

	$is_studio ? $studio++ : $scene++;
	imagedestroy( $image );
}

$total = $studio + $scene;

if ( $changed ) {
	WP_CLI::log( sprintf( "\n%d products change classification:", count( $changed ) ) );
	WP_CLI::log( implode( "\n", $changed ) );
	WP_CLI::log( '' );
} else {
	WP_CLI::log( "\nno classification changes" );
}

WP_CLI::success(
	sprintf(
		'%d studio cutouts (%d%%), %d scenes (%d%%), %d skipped.%s',
		$studio,
		$total ? round( $studio / $total * 100 ) : 0,
		$scene,
		$total ? round( $scene / $total * 100 ) : 0,
		$skipped,
		$dry_run ? ' DRY RUN — nothing written.' : ''
	)
);
