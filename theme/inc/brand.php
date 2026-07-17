<?php
/**
 * The business behind the shop: contact details, socials, legal identity.
 *
 * These live in one array because they are the same facts repeated in five places —
 * footer, product page WhatsApp link, JSON-LD, contact page, order emails. Every one of
 * them was previously either absent or hardcoded, and the WhatsApp number in particular
 * existed only as a literal inside the product template. When the client changes a phone
 * number, they change it here (or in the options table) and the whole site follows.
 *
 * Each field reads an option so the site owner can edit it without a deploy; the
 * constant is the fallback, not the source of truth.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

/**
 * The brand's real-world identity.
 *
 * @return array{
 *     phone:string, phone_display:string, whatsapp:string, email:string,
 *     address:string, legal_name:string, company_id:string,
 *     facebook:string, instagram:string
 * }
 */
function hrd_brand() {
	return apply_filters(
		'hrd_brand',
		array(
			// Digits only, in E.164 without the plus: wa.me and tel: want different
			// shapes and both are derived from this, so storing either formatted form
			// would mean parsing it back out.
			'whatsapp'      => get_option( 'hrd_whatsapp', '972587960660' ),
			'phone'         => get_option( 'hrd_phone', '+972587960660' ),
			'phone_display' => get_option( 'hrd_phone_display', '058-796-0660' ),
			'email'         => get_option( 'hrd_email', 'hr.israel.hr@gmail.com' ),
			'address'       => get_option( 'hrd_address', 'שלמה זלמן 32 ירושלים' ),

			// The registered company, which is not the trading name. Invoices and the
			// footer's legal line need the former; everything else uses the latter.
			'legal_name'    => get_option( 'hrd_legal_name', "חב' אייצ' אר עיצוב וייצור" ),
			'company_id'    => get_option( 'hrd_company_id', '211355201' ),

			// Three. There is no TikTok, YouTube or LinkedIn account to link to, and a
			// dead social icon is worse than a missing one.
			'facebook'      => get_option( 'hrd_facebook', 'https://www.facebook.com/reuve99/' ),
			'instagram'     => get_option( 'hrd_instagram', 'https://www.instagram.com/hr_design_and_manufacturing/' ),
		)
	);
}

/**
 * The socials that are actually configured, in render order.
 *
 * Returns only the ones with a URL, so emptying an option in the admin removes the icon
 * rather than shipping a link to nowhere. WhatsApp is a social here because that is how
 * this business is actually contacted.
 *
 * @return array<int,array{key:string,label:string,url:string}>
 */
function hrd_brand_socials() {
	$brand = hrd_brand();

	$socials = array(
		array(
			'key'   => 'facebook',
			'label' => __( 'פייסבוק', 'hrdesign' ),
			'url'   => $brand['facebook'],
		),
		array(
			'key'   => 'instagram',
			'label' => __( 'אינסטגרם', 'hrdesign' ),
			'url'   => $brand['instagram'],
		),
		array(
			'key'   => 'whatsapp',
			'label' => __( 'וואטסאפ', 'hrdesign' ),
			'url'   => $brand['whatsapp'] ? 'https://wa.me/' . rawurlencode( $brand['whatsapp'] ) : '',
		),
	);

	return array_values( array_filter( $socials, fn( $social ) => ! empty( $social['url'] ) ) );
}

/**
 * The footer menu when the site owner has not built one.
 *
 * Lives here rather than in inc/nav.php because that file is about the category tree the
 * header mega menu is generated from; this is the company's own pages.
 *
 * Every entry is resolved before it is rendered. This is the theme's existing doctrine
 * applied to links instead of facets: badges.php returns nothing rather than a
 * placeholder, filters.php hides a facet with too few products behind it, and the same
 * reasoning says a footer must not link to a page that does not exist. It matters
 * concretely here — this install has no about or contact page and its privacy policy is
 * still a draft, so porting the live site's footer verbatim would have shipped three
 * dead links. get_privacy_policy_url() already returns '' unless the page is published,
 * which is the guard we want and not one we have to write.
 *
 * @param array $args wp_nav_menu args.
 * @return string|void Markup when $args['echo'] is false.
 */
function hrd_footer_menu_fallback( $args = array() ) {
	$links = array();

	$pages = array(
		array( 'id' => wc_get_page_id( 'shop' ), 'label' => __( 'החנות', 'hrdesign' ) ),
		array( 'id' => wc_get_page_id( 'myaccount' ), 'label' => __( 'החשבון שלי', 'hrdesign' ) ),
		array( 'id' => wc_get_page_id( 'cart' ), 'label' => __( 'עגלת הקניות', 'hrdesign' ) ),
	);

	foreach ( array( 'about' => __( 'אודות', 'hrdesign' ), 'contact' => __( 'צור קשר', 'hrdesign' ) ) as $slug => $label ) {
		$page = get_page_by_path( $slug );
		if ( $page ) {
			$pages[] = array( 'id' => $page->ID, 'label' => $label );
		}
	}

	foreach ( $pages as $page ) {
		// wc_get_page_id() returns -1 when the page is unset, and a page can also be in
		// the trash while its id survives in options.
		if ( $page['id'] > 0 && 'publish' === get_post_status( $page['id'] ) ) {
			$links[] = array(
				'url'   => get_permalink( $page['id'] ),
				'label' => $page['label'],
			);
		}
	}

	$privacy = get_privacy_policy_url();
	if ( $privacy ) {
		$links[] = array(
			'url'   => $privacy,
			'label' => get_the_title( (int) get_option( 'wp_page_for_privacy_policy' ) ),
		);
	}

	if ( ! $links ) {
		return '';
	}

	$items = '';
	foreach ( $links as $link ) {
		$items .= sprintf(
			'<li class="menu-item"><a href="%s">%s</a></li>',
			esc_url( $link['url'] ),
			esc_html( $link['label'] )
		);
	}

	$markup = sprintf(
		'<ul class="%s">%s</ul>',
		esc_attr( $args['menu_class'] ?? 'footer-menu' ),
		$items
	);

	if ( isset( $args['echo'] ) && ! $args['echo'] ) {
		return $markup;
	}

	echo $markup; // phpcs:ignore WordPress.Security.EscapingOutput -- escaped per field above.
}

/**
 * Organization JSON-LD.
 *
 * The point of this is sameAs: it is what tells Google that this shop, the Facebook page
 * and the Instagram account are one business. Woo emits Product schema per product and
 * nothing at all about the company.
 */
add_action(
	'wp_head',
	function () {
		if ( ! is_front_page() ) {
			return;
		}

		$brand = hrd_brand();

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Organization',
			'name'     => get_bloginfo( 'name' ),
			'url'      => home_url( '/' ),
			'email'    => $brand['email'],
			'telephone' => $brand['phone'],
			'address'  => array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => $brand['address'],
				'addressCountry'  => 'IL',
			),
			'sameAs'   => array_values(
				array_filter(
					array(
						$brand['facebook'],
						$brand['instagram'],
						$brand['whatsapp'] ? 'https://wa.me/' . $brand['whatsapp'] : '',
					)
				)
			),
		);

		$logo_id = get_theme_mod( 'custom_logo' );
		if ( $logo_id ) {
			$logo = wp_get_attachment_image_url( $logo_id, 'full' );
			if ( $logo ) {
				$schema['logo'] = $logo;
			}
		}

		printf(
			"<script type=\"application/ld+json\">%s</script>\n",
			wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		);
	}
);
