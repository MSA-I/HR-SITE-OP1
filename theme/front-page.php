<?php
/**
 * Homepage.
 *
 * Ground sequence: photo -> cream -> near-black -> accent -> cream -> cream-200 ->
 * cream -> brown. The two hard colour events (03 Shop the Space and 04 the featured
 * collection) sit ADJACENT, so the page's strongest moment lands exactly where the
 * differentiating feature lives. That is the reason for the order.
 *
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="main" class="site-main">
	<?php
	get_template_part( 'templates/home/hero' );
	get_template_part( 'templates/home/categories' );
	get_template_part( 'templates/home/room-scene' );
	get_template_part( 'templates/home/collection' );
	get_template_part( 'templates/home/new' );
	get_template_part( 'templates/home/by-room' );
	get_template_part( 'templates/home/inspiration' );
	get_template_part( 'templates/home/trust' );
	?>
</main>

<?php
get_footer();
