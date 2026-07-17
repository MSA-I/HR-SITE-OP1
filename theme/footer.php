<?php
/**
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

$brand   = hrd_brand();
$socials = hrd_brand_socials();
?>

<footer class="site-footer">
	<div class="site-footer__inner">

		<div class="site-footer__cols">

			<?php
			/*
			 * The real logo lives here rather than in the header, which is the reverse of
			 * where you would expect it. The client's only asset is a light mark: the
			 * outlined "H" and the Hebrew tagline are white, so it measures 11.48:1 on
			 * --brown-700 and 1.18:1 on the cream header, where half of it simply is not
			 * there. It is in the right place; the header is the one waiting on an asset.
			 * See tools/seed/install-logo.php for the measurement.
			 *
			 * The Karantina wordmark is the fallback and is not a lesser one — it is the
			 * display voice, in cream on brown. Nothing here inverts or recolours the
			 * client's logo to make a layout work.
			 */
			$footer_logo = (int) get_option( 'hrd_footer_logo' );
			?>
			<div class="site-footer__col site-footer__col--brand">
				<a
					class="site-footer__wordmark <?php echo $footer_logo ? 'site-footer__wordmark--image' : 't-display'; ?>"
					href="<?php echo esc_url( home_url( '/' ) ); ?>"
					rel="home"
					<?php echo $footer_logo ? 'aria-label="' . esc_attr( get_bloginfo( 'name' ) ) . '"' : ''; ?>
				>
					<?php
					if ( $footer_logo ) {
						echo wp_get_attachment_image(
							$footer_logo,
							'medium',
							false,
							array(
								// Empty alt with the name on the <a>. The image is the only
								// thing in the link, so alt text here and a label there
								// would be the same words twice; an empty alt with no label
								// would leave the link with no name at all.
								'alt'     => '',
								'loading' => 'lazy',
							)
						);
					} else {
						bloginfo( 'name' );
					}
					?>
				</a>
				<p class="site-footer__legal"><?php echo esc_html( $brand['legal_name'] ); ?></p>
				<p class="site-footer__address"><?php echo esc_html( $brand['address'] ); ?></p>
			</div>

			<nav class="site-footer__col site-footer__col--menu" aria-label="<?php esc_attr_e( 'תפריט תחתון', 'hrdesign' ); ?>">
				<?php // Not "החנות": the column also carries אודות, צור קשר and the policy, and the first link in it is already called החנות. ?>
				<h2 class="site-footer__heading"><?php esc_html_e( 'קישורים', 'hrdesign' ); ?></h2>
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'footer',
						'container'      => false,
						'menu_class'     => 'footer-menu',
						'depth'          => 1,
						'fallback_cb'    => 'hrd_footer_menu_fallback',
					)
				);
				?>
			</nav>

			<div class="site-footer__col site-footer__col--contact">
				<h2 class="site-footer__heading"><?php esc_html_e( 'יצירת קשר', 'hrdesign' ); ?></h2>
				<ul class="footer-menu">
					<?php if ( $brand['phone'] ) : ?>
						<li>
							<a href="tel:<?php echo esc_attr( $brand['phone'] ); ?>">
								<?php echo esc_html( $brand['phone_display'] ); ?>
							</a>
						</li>
					<?php endif; ?>

					<?php if ( $brand['email'] ) : ?>
						<li>
							<a href="mailto:<?php echo esc_attr( $brand['email'] ); ?>">
								<?php echo esc_html( $brand['email'] ); ?>
							</a>
						</li>
					<?php endif; ?>

					<?php if ( $brand['whatsapp'] ) : ?>
						<li>
							<a href="https://wa.me/<?php echo esc_attr( $brand['whatsapp'] ); ?>" target="_blank" rel="noopener">
								<?php esc_html_e( 'שליחת הודעה בוואטסאפ', 'hrdesign' ); ?>
							</a>
						</li>
					<?php endif; ?>
				</ul>
			</div>

			<?php
			/*
			 * The offer is real, so the link has to go somewhere real. There is no mailing
			 * list integration on this install, and a subscribe field that accepts an
			 * address and drops it is the one version of this block that costs the client
			 * a customer. Until an ESP is connected this is a mail link that reaches a
			 * person. It becomes a <form> the day there is something to POST to.
			 */
			?>
			<div class="site-footer__col site-footer__col--news">
				<h2 class="site-footer__heading"><?php esc_html_e( 'קבלו 10% הנחה', 'hrdesign' ); ?></h2>
				<p class="site-footer__news-copy">
					<?php esc_html_e( 'הצטרפו לרשימת התפוצה ונשלח לכם קוד הנחה של 10% לרכישה הראשונה.', 'hrdesign' ); ?>
				</p>
				<?php if ( $brand['email'] ) : ?>
					<a
						class="site-footer__news-cta"
						href="mailto:<?php echo esc_attr( $brand['email'] ); ?>?subject=<?php echo rawurlencode( __( 'הצטרפות לרשימת התפוצה', 'hrdesign' ) ); ?>"
					>
						<?php esc_html_e( 'הצטרפו לרשימה', 'hrdesign' ); ?>
						<?php hrd_icon( 'arrow', array( 'size' => 16 ) ); ?>
					</a>
				<?php endif; ?>
			</div>

		</div>

		<div class="site-footer__bar">

			<p class="site-footer__copy">
				&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>
				<span class="site-footer__company-id">
					<?php
					printf(
						/* translators: 1: legal company name, 2: company registration number */
						esc_html__( '%1$s ח.פ %2$s', 'hrdesign' ),
						esc_html( $brand['legal_name'] ),
						esc_html( $brand['company_id'] )
					);
					?>
				</span>
			</p>

			<?php
			/*
			 * Payment marks as text, not logos. Visa, MasterCard and American Express are
			 * trademarks with published brand assets, and we do not have them — drawing an
			 * approximation from memory produces a wrong logo, which is worse than no
			 * logo and is the client's legal exposure rather than ours. ישראכרט has no
			 * canonical public SVG at all. Naming the schemes in text is accurate, is
			 * ordinary nominative use, and is not a fabricated mark. Replace with the
			 * official SVGs from each scheme's brand centre when we have them.
			 */
			$payment_marks = array( 'Visa', 'MasterCard', 'American Express', 'ישראכרט' );
			?>
			<ul class="site-footer__payments" aria-label="<?php esc_attr_e( 'אמצעי תשלום', 'hrdesign' ); ?>">
				<?php foreach ( $payment_marks as $mark ) : ?>
					<li class="payment-chip"><?php echo esc_html( $mark ); ?></li>
				<?php endforeach; ?>
			</ul>

			<?php if ( $socials ) : ?>
				<ul class="site-footer__socials">
					<?php foreach ( $socials as $social ) : ?>
						<li>
							<a
								class="site-footer__social"
								href="<?php echo esc_url( $social['url'] ); ?>"
								target="_blank"
								rel="noopener"
								aria-label="<?php echo esc_attr( $social['label'] ); ?>"
							>
								<?php hrd_icon( $social['key'], array( 'size' => 18 ) ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php
			/*
			 * The motion opt-out. It exists because the OS-level reduced-motion setting is
			 * the only other way to turn this off, and most Windows users in Israel have
			 * never seen that setting. Writes the same key the <head> script reads.
			 */
			?>
			<button type="button" class="motion-toggle" data-motion-toggle aria-pressed="false">
				<?php esc_html_e( 'הפחתת תנועה', 'hrdesign' ); ?>
			</button>

		</div>

	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
