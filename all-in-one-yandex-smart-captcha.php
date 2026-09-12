<?php
/**
 * Plugin Name:       All-in-One Yandex SmartCaptcha
 * Plugin URI:        https://example.com/all-in-one-yandex-smart-captcha
 * Description:       Защита всех форм на сайте Яндекс SmartCaptcha (invisible). Автоматически инжектит токен в формы, интеграция с Contact Form 7.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Developer
 * License:           GPL v2 or later
 * Text Domain:       all-in-one-yandex-smart-captcha
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'AIOYSC_VERSION', '1.0.0' );
define( 'AIOYSC_FILE', __FILE__ );
define( 'AIOYSC_PATH', plugin_dir_path( __FILE__ ) );
define( 'AIOYSC_URL', plugin_dir_url( __FILE__ ) );
define( 'AIOYSC_BASENAME', plugin_basename( __FILE__ ) );

require_once AIOYSC_PATH . 'includes/class-smartcaptcha-core.php';
require_once AIOYSC_PATH . 'includes/class-smartcaptcha-admin.php';

register_activation_hook( __FILE__, function () {
	if ( ! get_option( 'aioysc_settings' ) ) {
		add_option( 'aioysc_settings', [
			'enabled'  => true,
			'sitekey'  => '',
			'secret'   => '',
			'mode'     => 'invisible',
			'cf7_auto' => true,
		] );
	}
} );

register_deactivation_hook( __FILE__, function () {
	// Nothing to clean up — options persist for reactivation.
} );

add_action( 'plugins_loaded', function () {
	AIOYSC\Core::get_instance();
	if ( is_admin() ) {
		AIOYSC\Admin::get_instance();
	}
} );
