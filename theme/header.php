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
		<a class="site-header__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<?php bloginfo( 'name' ); ?>
		</a>
		<?php get_template_part( 'templates/header/nav' ); ?>
		<?php get_template_part( 'templates/header/actions' ); ?>
	</div>
</header>
