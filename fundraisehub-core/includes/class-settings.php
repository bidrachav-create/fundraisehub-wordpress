<?php
/**
 * Settings – admin settings page for the FundRaiseHub Core plugin.
 *
 * @package FundRaiseHub\Core
 */

declare( strict_types=1 );

namespace FundRaiseHub\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Settings
 *
 * Adds a settings page under Settings > FundRaiseHub where administrators
 * can configure the API key and remote site URL.
 */
class Settings {

	/** Option group slug. */
	private const OPTION_GROUP = 'fundraisehub_settings';

	/** Settings page slug. */
	private const PAGE_SLUG = 'fundraisehub-settings';

	/**
	 * Hook everything into WordPress.
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_notices', [ $this, 'maybe_show_setup_notice' ] );
	}

	/**
	 * Add the settings page to the WordPress admin menu.
	 */
	public function add_settings_page(): void {
		add_options_page(
			__( 'FundRaiseHub Settings', 'fundraisehub-core' ),
			__( 'FundRaiseHub', 'fundraisehub-core' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Register settings, sections, and fields.
	 */
	public function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			'fundraisehub_api_key',
			[
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			]
		);

		register_setting(
			self::OPTION_GROUP,
			'fundraisehub_site_url',
			[
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
				'default'           => '',
			]
		);

		add_settings_section(
			'fundraisehub_api_section',
			__( 'API Connection', 'fundraisehub-core' ),
			[ $this, 'render_api_section_description' ],
			self::PAGE_SLUG
		);

		add_settings_field(
			'fundraisehub_site_url',
			__( 'FundRaiseHub Site URL', 'fundraisehub-core' ),
			[ $this, 'render_site_url_field' ],
			self::PAGE_SLUG,
			'fundraisehub_api_section'
		);

		add_settings_field(
			'fundraisehub_api_key',
			__( 'API Key', 'fundraisehub-core' ),
			[ $this, 'render_api_key_field' ],
			self::PAGE_SLUG,
			'fundraisehub_api_section'
		);
	}

	/**
	 * Show a one-time admin notice prompting the user to complete setup.
	 */
	public function maybe_show_setup_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! get_option( 'fundraisehub_needs_setup' ) ) {
			return;
		}

		$settings_url = admin_url( 'options-general.php?page=' . self::PAGE_SLUG );
		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<?php
				printf(
					/* translators: %s: URL to the settings page */
					wp_kses(
						__( 'FundRaiseHub is almost ready. Please <a href="%s">configure your API key and site URL</a> to get started.', 'fundraisehub-core' ),
						[ 'a' => [ 'href' => [] ] ]
					),
					esc_url( $settings_url )
				);
				?>
			</p>
		</div>
		<?php

		// Dismiss the flag once the user has visited the settings page.
		if ( isset( $_GET['page'] ) && $_GET['page'] === self::PAGE_SLUG ) { // phpcs:ignore WordPress.Security.NonceVerification
			delete_option( 'fundraisehub_needs_setup' );
		}
	}

	// -------------------------------------------------------------------------
	// Render callbacks
	// -------------------------------------------------------------------------

	/**
	 * Render the settings page.
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button( __( 'Save Settings', 'fundraisehub-core' ) );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the API section description.
	 */
	public function render_api_section_description(): void {
		echo '<p>' . esc_html__( 'Enter your FundRaiseHub platform URL and API key to enable campaign synchronisation.', 'fundraisehub-core' ) . '</p>';
	}

	/**
	 * Render the Site URL input field.
	 */
	public function render_site_url_field(): void {
		$value = esc_attr( (string) get_option( 'fundraisehub_site_url', '' ) );
		echo '<input type="url" id="fundraisehub_site_url" name="fundraisehub_site_url" value="' . $value . '" class="regular-text" placeholder="https://app.fundraisehub.com" />';
		echo '<p class="description">' . esc_html__( 'The base URL of your FundRaiseHub installation.', 'fundraisehub-core' ) . '</p>';
	}

	/**
	 * Render the API Key input field.
	 */
	public function render_api_key_field(): void {
		$value = esc_attr( (string) get_option( 'fundraisehub_api_key', '' ) );
		echo '<input type="password" id="fundraisehub_api_key" name="fundraisehub_api_key" value="' . $value . '" class="regular-text" autocomplete="off" />';
		echo '<p class="description">' . esc_html__( 'Your FundRaiseHub API key. Keep this secret.', 'fundraisehub-core' ) . '</p>';
	}
}
