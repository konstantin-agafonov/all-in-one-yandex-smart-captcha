<?php
namespace AIOYSC;

defined( 'ABSPATH' ) || exit;

class Core {
	private static ?self $instance = null;

	public static function get_instance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Check if the site is running in development mode.
	 *
	 * @return bool
	 */
	public static function is_dev_mode(): bool {
		$env = defined( 'WP_ENV' ) ? WP_ENV : '';
		return in_array( strtolower( $env ), [ 'dev', 'develop', 'development' ], true );
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_head', [ $this, 'preconnect' ], 1 );

		// Auto-integration with CF7.
		add_filter( 'wpcf7_spam', [ $this, 'cf7_spam_check' ] );
	}

	/**
	 * Add preconnect hint for Yandex SmartCaptcha CDN.
	 */
	public function preconnect(): void {
		echo '<link rel="preconnect" href="https://smartcaptcha.cloud.yandex.ru" crossorigin>' . "\n";
	}

	/**
	 * Enqueue SmartCaptcha scripts on the frontend.
	 * Skipped when sitekey or secret is empty — form works without captcha.
	 */
	public function enqueue_assets(): void {
		$settings = get_option( 'aioysc_settings', [] );
		if ( empty( $settings['enabled'] ) || empty( $settings['sitekey'] ) || empty( $settings['secret'] ) ) {
			return;
		}

		wp_enqueue_script(
			'smartcaptcha-front',
			AIOYSC_URL . 'public/js/smartcaptcha-front.js',
			[],
			AIOYSC_VERSION,
			[ 'in_footer' => true, 'strategy' => 'defer' ]
		);

		wp_enqueue_script(
			'yandex-smartcaptcha',
			'https://smartcaptcha.cloud.yandex.ru/captcha.js?render=explicit',
			[ 'smartcaptcha-front' ],
			null,
			[ 'in_footer' => true, 'strategy' => 'defer' ]
		);

		wp_localize_script( 'smartcaptcha-front', 'smartcaptchaConfig', [
			'sitekey' => $settings['sitekey'],
		] );
	}

	/**
	 * Public verification API for themes/plugins.
	 * Usage: if ( ! AIOYSC\Core::verify_token() ) { ... }
	 *
	 * @return true if token is valid, false otherwise.
	 */
	public static function verify_token(): bool {
		$dev = self::is_dev_mode();
		$token = $_POST['smartcaptcha_token'] ?? '';

		if ( $dev ) {
			error_log( 'SmartCaptcha [DEV]: verify_token() started' );
			error_log( 'SmartCaptcha [DEV]: token = ' . ( empty( $token ) ? '(empty)' : substr( $token, 0, 20 ) . '...' ) );
		}

		if ( empty( $token ) ) {
			if ( $dev ) {
				error_log( 'SmartCaptcha [DEV]: token is empty — returning false' );
			}
			return false;
		}

		$settings = get_option( 'aioysc_settings', [] );
		$secret   = $settings['secret'] ?? '';

		if ( empty( $secret ) ) {
			error_log( 'SmartCaptcha: secret key is not set in plugin settings' );
			return false;
		}

		$ip = self::get_client_ip();

		if ( $dev ) {
			error_log( 'SmartCaptcha [DEV]: client IP = ' . $ip );
			error_log( 'SmartCaptcha [DEV]: sending validation request to Yandex...' );
		}

		$response = wp_remote_post( 'https://smartcaptcha.cloud.yandex.ru/validate', [
			'body'    => wp_json_encode( [
				'secret' => $secret,
				'token'  => $token,
				'ip'     => $ip,
			] ),
			'headers' => [ 'Content-Type' => 'application/json' ],
			'timeout' => 5,
		] );

		if ( is_wp_error( $response ) ) {
			error_log( 'SmartCaptcha: request error — ' . $response->get_error_message() );
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $dev ) {
			error_log( 'SmartCaptcha [DEV]: response status = ' . ( $body['status'] ?? '(not set)' ) );
			error_log( 'SmartCaptcha [DEV]: verify_token() result = ' . ( isset( $body['status'] ) && $body['status'] === 'ok' ? 'true' : 'false' ) );
		}

		return isset( $body['status'] ) && $body['status'] === 'ok';
	}

	/**
	 * Get client IP (proxy-compatible).
	 */
	private static function get_client_ip(): string {
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		}
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ips = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
			return sanitize_text_field( wp_unslash( trim( $ips[0] ) ) );
		}
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}

	/**
	 * CF7: automatic token verification.
	 * Marks submission as spam if token is invalid.
	 */
	public function cf7_spam_check( bool $spam ): bool {
		if ( ! $spam ) {
			$spam = ! self::verify_token();
		}
		return $spam;
	}
}
