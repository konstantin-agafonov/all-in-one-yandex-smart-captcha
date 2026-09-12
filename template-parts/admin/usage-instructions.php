<?php
/**
 * Usage instructions block on the settings page.
 */

defined( 'ABSPATH' ) || exit;
?>
<hr>
<h3><?php esc_html_e( 'Theme Usage', 'all-in-one-yandex-smart-captcha' ); ?></h3>
<p><?php esc_html_e( 'Add the following to your AJAX form handler:', 'all-in-one-yandex-smart-captcha' ); ?></p>
<pre><code><?php echo esc_html(
'if (class_exists(\'AIOYSC\\Core\') && !AIOYSC\Core::verify_token()) {
    wp_send_json_error([\'message\' => \'Captcha verification failed.\']);
    wp_die();
}'
); ?></code></pre>
<p class="description">
	<?php esc_html_e( 'Contact Form 7 integration is automatic — the wpcf7_spam filter is loaded when the plugin is active.', 'all-in-one-yandex-smart-captcha' ); ?>
</p>
