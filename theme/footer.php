<?php
/**
 * @package hrdesign
 */

defined( 'ABSPATH' ) || exit;
?>

<footer class="site-footer">
	<div class="site-footer__inner">
		<p class="site-footer__copy">
			&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>
		</p>

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
</footer>

<?php wp_footer(); ?>
</body>
</html>
