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

	/** Admin-post action for the Sync Now button (handled by CampaignSync). */
	private const SYNC_ACTION = 'fundraisehub_sync';

	/**
	 * Hook everything into WordPress.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_setup_notice' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_resync_notice' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_origin_warning' ) );

		// Dismiss the setup flag after settings are saved (nonce already verified by options.php).
		add_action( 'update_option_fundraisehub_api_key', array( $this, 'dismiss_setup_flag' ) );
		add_action( 'update_option_fundraisehub_api_url', array( $this, 'dismiss_setup_flag' ) );

		// Flush rewrite rules when the campaign archive slug is changed so the
		// new URL takes effect without requiring a manual permalink re-save.
		add_action( 'update_option_fundraisehub_campaign_slug', array( $this, 'flush_rewrite_on_slug_change' ), 10, 2 );
	}

	/**
	 * Flush rewrite rules when the campaign archive slug option changes.
	 *
	 * Called by `update_option_fundraisehub_campaign_slug` which fires only
	 * after WordPress has already verified the nonce via options.php.
	 *
	 * @param mixed $old_value Previous option value.
	 * @param mixed $new_value New option value.
	 */
	public function flush_rewrite_on_slug_change( mixed $old_value, mixed $new_value ): void {
		if ( $old_value !== $new_value ) {
			flush_rewrite_rules();
		}
	}

	/**
	 * Delete the "needs-setup" flag once the user has saved their settings.
	 *
	 * Called by `update_option_*` hooks which fire only after WordPress has
	 * already verified nonces via options.php.
	 */
	public function dismiss_setup_flag(): void {
		delete_option( 'fundraisehub_needs_setup' );
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
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings, sections, and fields.
	 */
	public function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			'fundraisehub_api_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_api_key' ),
				'default'           => '',
			)
		);

		register_setting(
			self::OPTION_GROUP,
			'fundraisehub_api_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_api_url' ),
				'default'           => 'https://app.fundraisehub.com',
			)
		);

		register_setting(
			self::OPTION_GROUP,
			'fundraisehub_campaign_slug',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_title',
				'default'           => 'campaigns',
			)
		);

		add_settings_section(
			'fundraisehub_api_section',
			__( 'API Connection', 'fundraisehub-core' ),
			array( $this, 'render_api_section_description' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'fundraisehub_api_url',
			__( 'API URL', 'fundraisehub-core' ),
			array( $this, 'render_api_url_field' ),
			self::PAGE_SLUG,
			'fundraisehub_api_section'
		);

		add_settings_field(
			'fundraisehub_api_key',
			__( 'API Key', 'fundraisehub-core' ),
			array( $this, 'render_api_key_field' ),
			self::PAGE_SLUG,
			'fundraisehub_api_section'
		);

		add_settings_field(
			'fundraisehub_scope_info',
			__( 'Connection Status', 'fundraisehub-core' ),
			array( $this, 'render_scope_info_field' ),
			self::PAGE_SLUG,
			'fundraisehub_api_section'
		);

		add_settings_section(
			'fundraisehub_display_section',
			__( 'Display Settings', 'fundraisehub-core' ),
			'__return_false',
			self::PAGE_SLUG
		);

		add_settings_field(
			'fundraisehub_campaign_slug',
			__( 'Campaign Archive Slug', 'fundraisehub-core' ),
			array( $this, 'render_campaign_slug_field' ),
			self::PAGE_SLUG,
			'fundraisehub_display_section'
		);
	}

	/**
	 * Sanitize the API URL on save.
	 *
	 * When the value is environment-managed via the FUNDRAISEHUB_API_URL
	 * constant, the submitted form value is discarded and the existing DB
	 * option is returned unchanged so the constant always takes precedence.
	 *
	 * @param mixed $value Raw submitted value.
	 *
	 * @return string Sanitized URL.
	 */
	public function sanitize_api_url( mixed $value ): string {
		if ( $this->is_api_url_env_managed() ) {
			// Return whatever is already stored without a fallback default:
			// the constant is authoritative, so we must not write a potentially
			// incorrect placeholder value to the database.
			return (string) get_option( 'fundraisehub_api_url', '' );
		}

		return esc_url_raw( (string) $value );
	}

	/**
	 * Sanitize the API key and test the connection on save.
	 *
	 * Behaviour:
	 * - When the FUNDRAISEHUB_API_KEY constant is defined the submitted value
	 *   is discarded and the existing stored option is returned unchanged.
	 * - When the submitted value is empty and a key is already stored, the
	 *   stored key is preserved (avoids accidental erasure when the admin
	 *   saves other settings without re-entering the key).
	 * - Otherwise the submitted value is sanitized and the connection is
	 *   tested; a settings error is added to report the outcome.
	 *
	 * @param mixed $value Raw submitted value.
	 *
	 * @return string Sanitized API key.
	 */
	public function sanitize_api_key( mixed $value ): string {
		// Constant-managed: ignore the form submission entirely.
		if ( $this->is_api_key_env_managed() ) {
			return (string) get_option( 'fundraisehub_api_key', '' );
		}

		$value = sanitize_text_field( (string) $value );

		// Empty submission while a key is already stored → keep the stored key.
		if ( '' === $value ) {
			$existing = (string) get_option( 'fundraisehub_api_key', '' );
			return $existing;
		}

		// Read the API URL from the current form submission so the test uses
		// the latest values before either option is committed to the database.
		// Nonce has already been verified by options.php before this callback fires.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$raw_url = isset( $_POST['fundraisehub_api_url'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['fundraisehub_api_url'] ) )
			: (string) get_option( 'fundraisehub_api_url', 'https://app.fundraisehub.com' );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$api_url = esc_url_raw( $raw_url );
		$client  = new ApiClient( $api_url, $value );
		$result  = $client->test_connection();

		if ( is_wp_error( $result ) ) {
			add_settings_error(
				'fundraisehub_api_key',
				'fundraisehub_connection_failed',
				sprintf(
					/* translators: %s: error message from the API */
					__( 'FundRaiseHub API connection failed: %s', 'fundraisehub-core' ),
					$result->get_error_message()
				),
				'error'
			);
		} else {
			// Bust the API cache so the new credentials take effect immediately.
			$client->bust_cache( '' );

			add_settings_error(
				'fundraisehub_api_key',
				'fundraisehub_connection_success',
				__( 'FundRaiseHub API connection successful.', 'fundraisehub-core' ),
				'success'
			);
		}

		return $value;
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

		$wizard_url = admin_url( 'admin.php?page=fundraisehub-setup' );
		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<?php
				printf(
					wp_kses(
						/* translators: %s: URL to the setup wizard */
						__( 'FundRaiseHub is almost ready. Please <a href="%s">complete the setup wizard</a> to get started.', 'fundraisehub-core' ),
						array( 'a' => array( 'href' => array() ) )
					),
					esc_url( $wizard_url )
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Show an admin notice after a successful Force Re-Sync.
	 */
	public function maybe_show_resync_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['fundraisehub_synced'] ) ) {
			return;
		}

		echo '<div class="notice notice-success is-dismissible"><p>' .
			esc_html__( 'FundRaiseHub data re-synced successfully.', 'fundraisehub-core' ) .
			'</p></div>';
	}

	/**
	 * Show an admin warning on the settings page when the WordPress site origin
	 * differs from the configured FundRaiseHub API origin.
	 *
	 * FundRaiseHub validates the Origin header on cross-origin requests. When
	 * the WordPress site is served from a different domain than the API, admins
	 * must add the site URL to the Allowed Origins list in their FundRaiseHub
	 * platform settings. This notice reminds them to do so.
	 */
	public function maybe_show_origin_warning(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Only show on the FundRaiseHub settings page to avoid flooding every
		// admin screen with a persistent notice.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ( sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ) ) !== self::PAGE_SLUG ) {
			return;
		}

		$api_url = $this->is_api_url_env_managed()
			? $this->read_api_url_constant()
			: (string) get_option( 'fundraisehub_api_url', '' );

		if ( '' === $api_url ) {
			$api_url = (string) get_option( 'fundraisehub_site_url', '' );
		}

		// Nothing to compare when the API URL is not yet configured.
		if ( '' === $api_url ) {
			return;
		}

		$site_url    = home_url();
		$site_origin = $this->parse_origin( $site_url );
		$api_origin  = $this->parse_origin( $api_url );

		// Bail if either URL could not be reduced to a valid origin (e.g. missing
		// scheme or host). Showing a false warning is worse than showing none.
		if ( '' === $site_origin || '' === $api_origin ) {
			return;
		}

		// Same origin — no cross-origin issue to warn about.
		if ( $site_origin === $api_origin ) {
			return;
		}

		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<?php
				printf(
					wp_kses(
						/* translators: 1: WordPress site URL, 2: FundRaiseHub API URL */
						__( 'FundRaiseHub: Your site (<strong>%1$s</strong>) connects to a FundRaiseHub instance at a different origin (<strong>%2$s</strong>). Please ensure <strong>%1$s</strong> is added to the <em>Allowed Origins</em> list in your FundRaiseHub platform settings to prevent cross-origin request errors.', 'fundraisehub-core' ),
						array(
							'strong' => array(),
							'em'     => array(),
						)
					),
					esc_url( $site_url ),
					esc_url( $api_url )
				);
				?>
			</p>
		</div>
		<?php
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

			<hr />

			<h2><?php esc_html_e( 'Sync Campaigns', 'fundraisehub-core' ); ?></h2>
			<p><?php esc_html_e( 'Fetch the latest campaign data from FundRaiseHub and update your local posts.', 'fundraisehub-core' ); ?></p>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::SYNC_ACTION ); ?>" />
				<?php wp_nonce_field( self::SYNC_ACTION ); ?>
				<?php submit_button( __( 'Sync Now', 'fundraisehub-core' ), 'secondary', 'fundraisehub_sync_submit', false ); ?>
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
	 * Render the API URL input field.
	 *
	 * When the value is supplied via the FUNDRAISEHUB_API_URL constant the
	 * field is rendered as read-only and a note is shown to the admin. Otherwise
	 * the field is pre-filled from the stored option (with a backward-compatible
	 * fallback to the legacy `fundraisehub_site_url` option).
	 */
	public function render_api_url_field(): void {
		if ( $this->is_api_url_env_managed() ) {
			echo '<input type="url" id="fundraisehub_api_url" name="fundraisehub_api_url" value="' . esc_attr( $this->read_api_url_constant() ) . '" class="regular-text" readonly />';
			echo '<p class="description">' . esc_html__( 'This value is set via the FUNDRAISEHUB_API_URL constant and cannot be changed here.', 'fundraisehub-core' ) . '</p>';
			return;
		}

		$value = (string) get_option( 'fundraisehub_api_url', '' );

		// Backward-compat: migrate from the legacy option key on first view.
		if ( '' === $value ) {
			$value = (string) get_option( 'fundraisehub_site_url', 'https://app.fundraisehub.com' );
		}

		echo '<input type="url" id="fundraisehub_api_url" name="fundraisehub_api_url" value="' . esc_attr( $value ) . '" class="regular-text" placeholder="https://app.fundraisehub.com" />';
		echo '<p class="description">' . esc_html__( 'The base URL of your FundRaiseHub installation.', 'fundraisehub-core' ) . '</p>';
	}

	/**
	 * Render the API Key input field.
	 *
	 * Security considerations:
	 * - The stored key value is never written back into the input so it is not
	 *   exposed to the browser. When a key is already saved the input is left
	 *   empty; submitting the form without entering a new value preserves the
	 *   existing key (handled in sanitize_api_key()).
	 * - When the FUNDRAISEHUB_API_KEY constant is defined the field is rendered
	 *   as read-only with a note that the value is environment-managed.
	 */
	public function render_api_key_field(): void {
		if ( $this->is_api_key_env_managed() ) {
			echo '<input type="password" id="fundraisehub_api_key" name="fundraisehub_api_key" value="" class="regular-text" readonly placeholder="' . esc_attr__( 'Set via FUNDRAISEHUB_API_KEY constant', 'fundraisehub-core' ) . '" autocomplete="off" />';
			echo '<p class="description">' . esc_html__( 'This value is set via the FUNDRAISEHUB_API_KEY constant and cannot be changed here.', 'fundraisehub-core' ) . '</p>';
			return;
		}

		$has_key = '' !== (string) get_option( 'fundraisehub_api_key', '' );
		?>
		<span style="display:inline-flex;align-items:center;gap:6px;">
			<input
				type="password"
				id="fundraisehub_api_key"
				name="fundraisehub_api_key"
				value=""
				class="regular-text"
				autocomplete="off"
				<?php if ( $has_key ) : ?>
				placeholder="<?php echo esc_attr__( '••••••••••• (saved)', 'fundraisehub-core' ); ?>"
				<?php endif; ?>
			/>
			<button
				type="button"
				class="button"
				id="fundraisehub_toggle_key"
				aria-controls="fundraisehub_api_key"
				onclick="(function(btn){
					var f = document.getElementById('fundraisehub_api_key');
					var show = f.type === 'password';
					f.type = show ? 'text' : 'password';
					btn.textContent = show
						? '<?php echo esc_js( __( 'Hide', 'fundraisehub-core' ) ); ?>'
						: '<?php echo esc_js( __( 'Show', 'fundraisehub-core' ) ); ?>';
				})(this)"
			><?php esc_html_e( 'Show', 'fundraisehub-core' ); ?></button>
		</span>
		<?php if ( $has_key ) : ?>
		<p class="description"><?php esc_html_e( 'API key is saved. Leave blank to keep the existing key, or enter a new value to replace it.', 'fundraisehub-core' ); ?></p>
		<?php else : ?>
		<p class="description"><?php esc_html_e( 'Your FundRaiseHub API key. Keep this secret.', 'fundraisehub-core' ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render the read-only connection status / scope info field.
	 *
	 * Displays the organisation or programme name returned by the API, or a
	 * descriptive error if the connection cannot be established.
	 */
	public function render_scope_info_field(): void {
		$api_key = (string) get_option( 'fundraisehub_api_key', '' );
		$api_url = (string) get_option( 'fundraisehub_api_url', '' );

		// Backward-compat: fall back to legacy option key.
		if ( '' === $api_url ) {
			$api_url = (string) get_option( 'fundraisehub_site_url', 'https://app.fundraisehub.com' );
		}

		if ( '' === $api_key ) {
			echo '<p class="description">' .
				esc_html__( 'Enter your API key and URL above, then save to test the connection.', 'fundraisehub-core' ) .
				'</p>';
			return;
		}

		$client = new ApiClient( $api_url, $api_key );
		$data   = $client->get( 'design-system' );

		if ( is_wp_error( $data ) ) {
			echo '<p class="description" style="color:#dc3232;">';
			echo esc_html(
				sprintf(
					/* translators: %s: error message */
					__( 'Connection failed: %s', 'fundraisehub-core' ),
					$data->get_error_message()
				)
			);
			echo '</p>';
			return;
		}

		// Try common field names for the org / programme name.
		$scope_name = $data['name'] ?? $data['org_name'] ?? $data['organization'] ?? $data['programme'] ?? $data['program'] ?? '';

		echo '<p class="description" style="color:#46b450;">';
		if ( '' !== $scope_name ) {
			echo esc_html(
				sprintf(
					/* translators: %s: organisation or programme name */
					__( 'Connected – %s', 'fundraisehub-core' ),
					$scope_name
				)
			);
		} else {
			esc_html_e( 'Connected.', 'fundraisehub-core' );
		}
		echo '</p>';
	}

	/**
	 * Render the Campaign Archive Slug input field.
	 */
	public function render_campaign_slug_field(): void {
		$value = (string) get_option( 'fundraisehub_campaign_slug', 'campaigns' );
		if ( '' === $value ) {
			$value = 'campaigns';
		}
		echo '<input type="text" id="fundraisehub_campaign_slug" name="fundraisehub_campaign_slug" value="' . esc_attr( $value ) . '" class="regular-text" />';
		echo '<p class="description">' . esc_html__( 'URL slug for the campaign archive page (default: campaigns). Changing this requires saving and then re-saving your WordPress permalinks.', 'fundraisehub-core' ) . '</p>';
	}

	// -------------------------------------------------------------------------
	// Constant / environment helpers
	// -------------------------------------------------------------------------

	/**
	 * Return the value of the FUNDRAISEHUB_API_URL constant, or '' if not defined.
	 *
	 * Extracted into a protected method so tests can override it via a subclass
	 * without needing to (re-)define a PHP constant.
	 *
	 * @return string
	 */
	protected function read_api_url_constant(): string {
		return defined( 'FUNDRAISEHUB_API_URL' ) ? (string) FUNDRAISEHUB_API_URL : '';
	}

	/**
	 * Return the value of the FUNDRAISEHUB_API_KEY constant, or '' if not defined.
	 *
	 * Extracted into a protected method so tests can override it via a subclass
	 * without needing to (re-)define a PHP constant.
	 *
	 * @return string
	 */
	protected function read_api_key_constant(): string {
		return defined( 'FUNDRAISEHUB_API_KEY' ) ? (string) FUNDRAISEHUB_API_KEY : '';
	}

	/**
	 * Return true when the API URL is provided via the FUNDRAISEHUB_API_URL constant.
	 *
	 * @return bool
	 */
	private function is_api_url_env_managed(): bool {
		return '' !== $this->read_api_url_constant();
	}

	/**
	 * Return true when the API key is provided via the FUNDRAISEHUB_API_KEY constant.
	 *
	 * @return bool
	 */
	private function is_api_key_env_managed(): bool {
		return '' !== $this->read_api_key_constant();
	}

	/**
	 * Reduce a URL to its scheme+host+port origin string.
	 *
	 * Returns an empty string when the URL cannot be parsed or is missing a
	 * scheme or host, which causes the caller to skip the comparison safely.
	 * Port is included only when it differs from the scheme's default (80 for
	 * http, 443 for https), matching the browser definition of "origin".
	 *
	 * @param string $url Full URL to parse.
	 *
	 * @return string Origin string (e.g. "https://example.com" or "https://example.com:8443"), or '' on failure.
	 */
	private function parse_origin( string $url ): string {
		$scheme = (string) ( wp_parse_url( $url, PHP_URL_SCHEME ) ?? '' );
		$host   = (string) ( wp_parse_url( $url, PHP_URL_HOST ) ?? '' );
		$port   = wp_parse_url( $url, PHP_URL_PORT );
		$port   = is_int( $port ) ? $port : null;

		// Cannot determine origin without both scheme and host.
		if ( '' === $scheme || '' === $host ) {
			return '';
		}

		$origin = $scheme . '://' . $host;

		// Append the port only when it differs from the scheme's well-known default.
		if ( null !== $port ) {
			$defaults = array(
				'http'  => 80,
				'https' => 443,
			);
			if ( ! isset( $defaults[ $scheme ] ) || $defaults[ $scheme ] !== $port ) {
				$origin .= ':' . $port;
			}
		}

		return $origin;
	}
}
