<?php
/**
 * Seeds the three posts the Inspiration section renders.
 *
 *   docker compose exec wpcli wp eval-file /tools/seed/inspiration.php
 *
 * The section was showing WordPress's default "Hello world!" against an empty tinted
 * box, because it queried whatever was in the blog and the blog held exactly one post
 * with no thumbnail. The template now requires three published posts WITH thumbnails,
 * so it needs three to exist before it renders at all. This makes them.
 *
 * THE COPY IS DEMO COPY, NOT THE CLIENT'S. HR Design writes their own editorial; this
 * exists to show that the layout works with real content in it, and every post says so
 * in its own first paragraph. The storage is deliberately defensive about that, the same
 * way import-estimates.php is about inferred dimensions:
 *
 *   - every post carries _hrd_demo_content = 1
 *   - every post opens with a visible notice naming itself as placeholder text
 *   - so one query finds them all, and one removes them:
 *
 *       wp post list --post_type=post --meta_key=_hrd_demo_content --format=ids
 *       wp post delete $(wp post list --post_type=post --meta_key=_hrd_demo_content --format=ids) --force
 *
 * The photographs ARE real: each post borrows the featured image of a real catalogue
 * product, looked up through _hrd_src_id. Nothing here invents an image.
 *
 * Idempotent: posts are matched on their ASCII slug, so a re-run updates in place.
 */

defined( 'ABSPATH' ) || die( 'wp eval-file only' );

/** Marks a post as placeholder copy. One query finds every one of them. */
const HRD_DEMO_META = '_hrd_demo_content';

/**
 * The full notice, carried in post_content.
 *
 * Worth knowing that nothing renders this today: theme/index.php is the only template a
 * post can land on, and it calls the_excerpt(), never the_content(). It is kept anyway so
 * the post is self-describing in the editor and in the database, and so the notice is
 * already there the day a single.php exists.
 */
const HRD_DEMO_NOTICE = 'טקסט הדגמה. הפסקה הזו קיימת כדי להראות איך המדור נראה עם תוכן אמיתי בתוכו, והיא אינה תוכן שיווקי של HR Design. את הטקסט הסופי כותב הלקוח.';

/**
 * The visible marker, prefixed onto the excerpt.
 *
 * The excerpt is the only part of a post this theme ever prints, on the home page and on
 * the post itself, so this is the only marker a human actually sees. It goes at the FRONT
 * because the template trims the excerpt to 18 words: a marker at the end is a marker
 * that can be cut off, which is the one thing it must never be.
 */
const HRD_DEMO_LABEL = 'טקסט הדגמה:';

/*
 * The photographs, in render order: lead first, then the two stacked side items.
 *
 * src 6623 ("ספה מגאן") is the obvious sofa scene and is deliberately NOT here: it is
 * already the hero, and the hero and the first editorial image sitting on one page would
 * read as a shortage of photographs. src 5932 is a different real living room.
 *
 * The third is a mirror rather than a lamp. The brief asked for a lighting scene, but the
 * catalogue does not contain one: every product in תאורה is a cutout on white. The three
 * that _hrd_photo_type calls "scene" are transparent PNGs, which the classifier misreads
 * because alpha samples as luma 0 and so fails its white test. Dropping a knockout lamp
 * between two room photographs would rebuild the exact "half finished" look this section
 * is being fixed for, so the light topic is carried by a room that is actually about
 * light: an arched mirror bouncing a window across a living room.
 */
$seed = array(
	array(
		'slug'    => 'hrd-demo-choosing-a-sofa',
		'src'     => 5932,
		'title'   => 'איך בוחרים ספה שנשארים בה',
		'excerpt' => 'עומק הישיבה, המידה ביחס לחדר והבד. שלושה דברים שכדאי למדוד לפני שמזמינים, ולא אחרי.',
	),
	array(
		'slug'    => 'hrd-demo-dining-corner',
		'src'     => 6458,
		'title'   => 'פינת אוכל שמזמינה להישאר',
		'excerpt' => 'שולחן הוא רק ההתחלה. מה שקובע כמה זמן יושבים סביבו הוא המרווח, הכיסא והאור שנופל עליו.',
	),
	array(
		'slug'    => 'hrd-demo-mirror-and-light',
		'src'     => 6113,
		'title'   => 'מראה במקום הנכון מכפילה את האור',
		'excerpt' => 'מראה מול חלון מחזירה את אור היום עמוק לתוך החדר, ומרחיבה חלל קטן בלי להזיז קיר.',
	),
);

/**
 * Resolves a live-store product id to the attachment behind its featured image.
 *
 * @param int $src The live store's post id, as stored in _hrd_src_id.
 * @return int Attachment id, or 0 when the product or its thumbnail is missing.
 */
function hrd_demo_thumbnail_for_src( $src ) {
	$ids = get_posts(
		array(
			'post_type'      => 'product',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array( array( 'key' => '_hrd_src_id', 'value' => $src ) ),
		)
	);

	return $ids ? (int) get_post_thumbnail_id( $ids[0] ) : 0;
}

// The post this whole exercise is about. Matched on slug rather than trusting id 1 to be
// what it looks like, because deleting the wrong post permanently is not recoverable.
$hello = get_page_by_path( 'hello-world', OBJECT, 'post' );
if ( $hello ) {
	wp_delete_post( $hello->ID, true );
	WP_CLI::log( sprintf( 'deleted default post %d ("%s")', $hello->ID, $hello->post_title ) );
}

$created = 0;
$updated = 0;

foreach ( $seed as $item ) {
	$thumbnail_id = hrd_demo_thumbnail_for_src( $item['src'] );

	// No image, no post. A post without a thumbnail is invisible to the template's query
	// anyway, so seeding one would only produce a section that silently refuses to render.
	if ( ! $thumbnail_id ) {
		WP_CLI::warning( sprintf( 'src %d has no featured image, skipping "%s"', $item['src'], $item['title'] ) );
		continue;
	}

	$content = sprintf(
		"<!-- wp:paragraph -->\n<p><strong>%s</strong></p>\n<!-- /wp:paragraph -->",
		esc_html( HRD_DEMO_NOTICE )
	);

	$postarr = array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_name'    => $item['slug'],
		'post_title'   => $item['title'],
		'post_excerpt' => HRD_DEMO_LABEL . ' ' . $item['excerpt'],
		'post_content' => $content,
	);

	$existing = get_page_by_path( $item['slug'], OBJECT, 'post' );
	if ( $existing ) {
		$postarr['ID'] = $existing->ID;
		$updated++;
	} else {
		$created++;
	}

	$post_id = wp_insert_post( $postarr, true );
	if ( is_wp_error( $post_id ) ) {
		WP_CLI::error( $post_id->get_error_message() );
	}

	// Shares the product's attachment rather than copying the file: same photograph, one
	// copy on disk, and the srcset WordPress already generated for it.
	set_post_thumbnail( $post_id, $thumbnail_id );
	update_post_meta( $post_id, HRD_DEMO_META, 1 );

	$meta = wp_get_attachment_metadata( $thumbnail_id );
	WP_CLI::log(
		sprintf(
			'post %-3d %-28s src %-5d attachment %-4d %sx%s',
			$post_id,
			$item['slug'],
			$item['src'],
			$thumbnail_id,
			$meta['width'] ?? '?',
			$meta['height'] ?? '?'
		)
	);
}

$total = (int) wp_count_posts( 'post' )->publish;

WP_CLI::success(
	sprintf(
		'%d created, %d updated, %d published posts total. All marked %s — demo copy, not the client\'s.',
		$created,
		$updated,
		$total,
		HRD_DEMO_META
	)
);
