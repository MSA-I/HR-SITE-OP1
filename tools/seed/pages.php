<?php
/**
 * Creates the company pages the footer links to, and publishes the privacy policy.
 *
 *   docker compose exec wpcli wp eval-file /tools/seed/pages.php
 *
 * The footer fallback only renders links whose target resolves, so without this script
 * the אודות and צור קשר entries simply do not appear — the site is correct, just
 * thinner. This makes them exist rather than making the footer lie about them, which is
 * the order those two things have to happen in.
 *
 * Idempotent: re-running updates the pages it already made instead of making more.
 *
 * On copy: everything written here is a fact we verified against the live site — the
 * address, the phone, the email, the registered company name, the two social accounts.
 * The אודות page is deliberately four lines of verifiable fact and no story. We do not
 * know how long this business has been running, who founded it or what it is proud of,
 * and inventing any of that for a client's own about page is not a thing to do. The page
 * exists so the link works and so there is a real place to paste the client's copy into.
 */

defined( 'ABSPATH' ) || die( 'wp eval-file only' );

if ( ! function_exists( 'hrd_brand' ) ) {
	WP_CLI::error( 'hrd_brand() missing — is the hr-design theme active?' );
}

$brand = hrd_brand();

/**
 * Create or update a page, keyed by slug.
 *
 * @param string $slug    Page slug.
 * @param string $title   Page title.
 * @param string $content Block markup.
 * @return int Page id.
 */
function hrd_seed_page( $slug, $title, $content ) {
	$existing = get_page_by_path( $slug );

	$data = array(
		'post_name'    => $slug,
		'post_title'   => $title,
		'post_content' => $content,
		'post_type'    => 'page',
		'post_status'  => 'publish',
	);

	if ( $existing ) {
		$data['ID'] = $existing->ID;
		$id         = wp_update_post( $data, true );
		$verb       = 'updated';
	} else {
		$id   = wp_insert_post( $data, true );
		$verb = 'created';
	}

	if ( is_wp_error( $id ) ) {
		WP_CLI::error( $slug . ': ' . $id->get_error_message() );
	}

	WP_CLI::log( sprintf( '%s  %s (#%d) — %s', $verb, $slug, $id, get_permalink( $id ) ) );

	return $id;
}

/** Escape and wrap a paragraph as core block markup. */
function hrd_seed_p( $text ) {
	return "<!-- wp:paragraph -->\n<p>" . $text . "</p>\n<!-- /wp:paragraph -->\n\n";
}

// ---- אודות ----------------------------------------------------------------
// Facts only. See the note at the top of this file.

$about  = hrd_seed_p( esc_html( get_bloginfo( 'name' ) ) . ' מעצבת ומייצרת רהיטים ופריטי עיצוב לבית.' );
$about .= hrd_seed_p( 'הסטודיו והייצור נמצאים ב' . esc_html( $brand['address'] ) . '.' );
$about .= hrd_seed_p(
	'לשאלות, הזמנות מיוחדות והתאמות אישיות אפשר לפנות אלינו בטלפון ' .
	esc_html( $brand['phone_display'] ) . ' או בדואר האלקטרוני ' . esc_html( $brand['email'] ) . '.'
);
$about .= hrd_seed_p( esc_html( $brand['legal_name'] ) . ' ח.פ ' . esc_html( $brand['company_id'] ) . '.' );

hrd_seed_page( 'about', 'אודות', $about );

// ---- צור קשר --------------------------------------------------------------
// This one is genuinely complete: a contact page is contact details, and we have them.

$contact  = hrd_seed_p( 'נשמח לשמוע מכם. אפשר להתקשר, לכתוב, או לשלוח הודעה בוואטסאפ.' );
$contact .= hrd_seed_p(
	'<strong>טלפון:</strong> <a href="tel:' . esc_attr( $brand['phone'] ) . '">' . esc_html( $brand['phone_display'] ) . '</a>'
);
$contact .= hrd_seed_p(
	'<strong>וואטסאפ:</strong> <a href="https://wa.me/' . esc_attr( $brand['whatsapp'] ) . '" target="_blank" rel="noopener">שליחת הודעה</a>'
);
$contact .= hrd_seed_p(
	'<strong>דואר אלקטרוני:</strong> <a href="mailto:' . esc_attr( $brand['email'] ) . '">' . esc_html( $brand['email'] ) . '</a>'
);
$contact .= hrd_seed_p( '<strong>כתובת:</strong> ' . esc_html( $brand['address'] ) );
$contact .= hrd_seed_p(
	'<strong>עקבו אחרינו:</strong> ' .
	'<a href="' . esc_url( $brand['facebook'] ) . '" target="_blank" rel="noopener">פייסבוק</a> · ' .
	'<a href="' . esc_url( $brand['instagram'] ) . '" target="_blank" rel="noopener">אינסטגרם</a>'
);

hrd_seed_page( 'contact', 'צור קשר', $contact );

// ---- Privacy policy -------------------------------------------------------
/*
 * WordPress creates this page on install as an English-titled draft full of its own
 * boilerplate, and there it sat. get_privacy_policy_url() returns '' while it is a draft,
 * which is why the footer was correct to omit it — publishing is what makes the link
 * real, so publish it rather than teaching the footer to link to a draft.
 *
 * The body is left exactly as WordPress wrote it. It is a template with instructions to
 * the site owner inside it, and it needs a human — ideally the client's lawyer — before
 * it means anything. Replacing that template with prose I invented would look finished
 * and be worse, because a privacy policy that is confidently wrong is a liability and an
 * obviously-unfinished one at least reads as a task.
 */
$privacy_id = (int) get_option( 'wp_page_for_privacy_policy' );

if ( ! $privacy_id ) {
	$existing = get_page_by_path( 'privacy-policy' );
	if ( $existing ) {
		$privacy_id = $existing->ID;
	}
}

if ( $privacy_id && get_post( $privacy_id ) ) {
	wp_update_post(
		array(
			'ID'          => $privacy_id,
			'post_title'  => 'מדיניות הפרטיות ותנאי שימוש',
			'post_status' => 'publish',
		)
	);
	update_option( 'wp_page_for_privacy_policy', $privacy_id );

	WP_CLI::log( sprintf( 'published  privacy-policy (#%d) — %s', $privacy_id, get_permalink( $privacy_id ) ) );
	WP_CLI::warning( 'privacy policy body is still the WordPress template. It needs the client\'s own text.' );
} else {
	WP_CLI::warning( 'no privacy policy page found — skipped.' );
}

WP_CLI::success( 'pages done.' );
