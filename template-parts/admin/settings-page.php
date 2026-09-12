<?php
/**
 * Settings page template.
 *
 * @var string $option_group  Option group for settings_fields().
 * @var string $page_slug     Page slug for do_settings_sections().
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<form action="options.php" method="post">
		<?php
		settings_fields( $option_group );
		do_settings_sections( $page_slug );
		submit_button( __( 'Save', 'all-in-one-yandex-smart-captcha' ) );
		?>
	</form>

	<?php include AIOYSC_PATH . 'template-parts/admin/usage-instructions.php'; ?>
</div>
