<?php
/**
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link" href="#main"><?php esc_html_e( 'דילוג לתוכן', 'hrdesign' ); ?></a>

<header class="site-header">
	<div class="site-header__inner">
		<?php
		/*
		 * The PNG when there is one, the Karantina wordmark when there is not. The
		 * fallback is not a degraded state — it is what the footer ships permanently,
		 * because the client's only mark is dark on transparency and dies on brown.
		 * the_custom_logo() emits its own home link, so the <a> belongs to the fallback
		 * branch only; wrapping both would nest a link inside a link.
		 */
		if ( has_custom_logo() ) :
			?>
			<div class="site-header__logo site-header__logo--image">
				<?php the_custom_logo(); ?>
			</div>
		<?php else : ?>
			<a class="site-header__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
				<?php bloginfo( 'name' ); ?>
			</a>
		<?php endif; ?>
		<?php get_template_part( 'templates/header/nav' ); ?>
		<?php get_template_part( 'templates/header/actions' ); ?>
	</div>
</header>
