<?php
namespace AIOYSC;

defined( 'ABSPATH' ) || exit;

class Admin {
	private static ?self $instance = null;
	private const OPTION_GROUP = 'aioysc_settings';
	private const OPTION_NAME  = 'aioysc_settings';
	private const PAGE_SLUG    = 'aioysc-settings';

	public static function get_instance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	public function add_menu(): void {
		add_options_page(
			'All-in-One Yandex SmartCaptcha',
			'Yandex SmartCaptcha',
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	public function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize' ],
				'default'           => [
					'enabled'  => true,
					'sitekey'  => '',
					'secret'   => '',
					'mode'     => 'invisible',
					'cf7_auto' => true,
				],
			]
		);

		add_settings_section(
			'aioysc_main',
			__( 'SmartCaptcha Settings', 'all-in-one-yandex-smart-captcha' ),
			null,
			self::PAGE_SLUG
		);

		add_settings_field( 'enabled', __( 'Enable', 'all-in-one-yandex-smart-captcha' ), [ $this, 'render_checkbox' ], self::PAGE_SLUG, 'aioysc_main', [
			'key'  => 'enabled',
			'desc' => __( 'Enable or disable SmartCaptcha protection', 'all-in-one-yandex-smart-captcha' ),
		] );

		add_settings_field( 'sitekey', 'Site Key', [ $this, 'render_text' ], self::PAGE_SLUG, 'aioysc_main', [
			'key'  => 'sitekey',
			'desc' => __( 'Public key from Yandex cabinet', 'all-in-one-yandex-smart-captcha' ),
		] );

		add_settings_field( 'secret', 'Secret Key', [ $this, 'render_text' ], self::PAGE_SLUG, 'aioysc_main', [
			'key'  => 'secret',
			'type' => 'password',
			'desc' => __( 'Secret key from Yandex cabinet', 'all-in-one-yandex-smart-captcha' ),
		] );

		add_settings_field( 'cf7_auto', 'CF7', [ $this, 'render_checkbox' ], self::PAGE_SLUG, 'aioysc_main', [
			'key'  => 'cf7_auto',
			'desc' => __( 'Automatically verify token in all Contact Form 7 forms', 'all-in-one-yandex-smart-captcha' ),
		] );
	}

	public function sanitize( array $input ): array {
		return [
			'enabled'  => ! empty( $input['enabled'] ),
			'sitekey'  => sanitize_text_field( $input['sitekey'] ?? '' ),
			'secret'   => sanitize_text_field( $input['secret'] ?? '' ),
			'mode'     => sanitize_text_field( $input['mode'] ?? 'invisible' ),
			'cf7_auto' => ! empty( $input['cf7_auto'] ),
		];
	}

	/**
	 * Render settings page — load template.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$args = [
			'option_group' => self::OPTION_GROUP,
			'page_slug'    => self::PAGE_SLUG,
		];

		include AIOYSC_PATH . 'template-parts/admin/settings-page.php';
	}

	/**
	 * Render checkbox field — load template.
	 */
	public function render_checkbox( array $args ): void {
		$options      = get_option( self::OPTION_NAME, [] );
		$args['value'] = $options[ $args['key'] ] ?? false;

		include AIOYSC_PATH . 'template-parts/admin/field-checkbox.php';
	}

	/**
	 * Render text/password field — load template.
	 */
	public function render_text( array $args ): void {
		$options      = get_option( self::OPTION_NAME, [] );
		$args['value'] = $options[ $args['key'] ] ?? '';
		$args['type']  = $args['type'] ?? 'text';

		include AIOYSC_PATH . 'template-parts/admin/field-text.php';
	}
}
