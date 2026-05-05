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

	/** Admin-post action for the Force Re-Sync button. */
	private const RESYNC_ACTION = 'fundraisehub_force_resync';

	/**
	 * Hook everything into WordPress.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_setup_notice' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_resync_notice' ) );
		add_action( 'admin_post_' . self::RESYNC_ACTION, array( $this, 'handle_force_resync' ) );

		// Dismiss the setup flag after settings are saved (nonce already verified by options.php).
		add_action( 'update_option_fundraisehub_api_key', array( $this, 'dismiss_setup_flag' ) );
		add_action( 'update_option_fundraisehub_api_url', array( $this, 'dismiss_setup_flag' ) );
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
				'sanitize_callback' => 'esc_url_raw',
				'default'           => 'https://app.fundraisehub.com',
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
			__( 'FundRaiseHub Site URL', 'fundraisehub-core' ),
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
	}

	/**
	 * Sanitize the API key and test the connection on save.
	 *
	 * Adds a settings error (shown as an admin notice on redirect) to report
	 * whether the connection succeeded or failed.
	 *
	 * @param mixed $value Raw submitted value.
	 *
	 * @return string Sanitized API key.
	 */
	public function sanitize_api_key( mixed $value ): string {
		$value = sanitize_text_field( (string) $value );

		if ( '' === $value ) {
			return $value;
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
	 * Handle the Force Re-Sync admin-post action.
	 *
	 * Clears all API transients and triggers a full campaign sync, then
	 * redirects back to the settings page with a success flag.
	 */
	public function handle_force_resync(): void {
		check_admin_referer( self::RESYNC_ACTION );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'fundraisehub-core' ) );
		}

		$api = new ApiClient();
		$api->bust_cache( '' );

		( new CampaignSync( $api ) )->sync_all();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                => self::PAGE_SLUG,
					'fundraisehub_synced' => '1',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
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
					wp_kses(
						/* translators: %s: URL to the settings page */
						__( 'FundRaiseHub is almost ready. Please <a href="%s">configure your API key and API URL</a> to get started.', 'fundraisehub-core' ),
						array( 'a' => array( 'href' => array() ) )
					),
					esc_url( $settings_url )
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
			<p><?php esc_html_e( 'Clear the API cache and fetch the latest campaign data from FundRaiseHub.', 'fundraisehub-core' ); ?></p>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::RESYNC_ACTION ); ?>" />
				<?php wp_nonce_field( self::RESYNC_ACTION ); ?>
				<?php submit_button( __( 'Force Re-Sync', 'fundraisehub-core' ), 'secondary', 'fundraisehub_resync_submit', false ); ?>
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
	 */
	public function render_api_url_field(): void {
		$value = (string) get_option( 'fundraisehub_api_url', 'https://app.fundraisehub.com' );
		echo '<input type="url" id="fundraisehub_api_url" name="fundraisehub_api_url" value="' . esc_attr( $value ) . '" class="regular-text" placeholder="https://app.fundraisehub.com" />';
		echo '<p class="description">' . esc_html__( 'The base URL of your FundRaiseHub installation.', 'fundraisehub-core' ) . '</p>';
	}

	/**
	 * Render the API Key input field with a Show/Hide toggle button.
	 */
	public function render_api_key_field(): void {
		$api_key = (string) get_option( 'fundraisehub_api_key', '' );
		?>
		<span style="display:inline-flex;align-items:center;gap:6px;">
			<input
				type="password"
				id="fundraisehub_api_key"
				name="fundraisehub_api_key"
				value="<?php echo esc_attr( $api_key ); ?>"
				class="regular-text"
				autocomplete="off"
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
		<p class="description"><?php esc_html_e( 'Your FundRaiseHub API key. Keep this secret.', 'fundraisehub-core' ); ?></p>
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
		$api_url = (string) get_option( 'fundraisehub_api_url', 'https://app.fundraisehub.com' );

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
}
