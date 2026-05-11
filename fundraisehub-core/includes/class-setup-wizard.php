<?php
/**
 * Setup Wizard – first-run guided setup for FundRaiseHub Core.
 *
 * @package FundRaiseHub\Core
 */

declare( strict_types=1 );

namespace FundRaiseHub\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SetupWizard
 *
 * Provides a three-step first-run configuration wizard that guides site
 * administrators through:
 *
 *   Step 1 – Enter and test the API credentials.
 *   Step 2 – Choose the campaign archive URL slug.
 *   Step 3 – Run the initial campaign sync and review the imported campaigns.
 *
 * The wizard is triggered by a short-lived transient written during plugin
 * activation (see fundraisehub_core_activate()) and by the
 * `fundraisehub_needs_setup` flag.  Clicking "Finish Setup" on Step 3
 * deletes both the transient and the flag, then redirects the administrator
 * to the main settings page.
 */
class SetupWizard {

	/** Admin page slug for the wizard. */
	private const PAGE_SLUG = 'fundraisehub-setup';

	/** Nonce action for the Step 1 form. */
	private const NONCE_STEP1 = 'fundraisehub_wizard_step1';

	/** Nonce action for the Step 2 form. */
	private const NONCE_STEP2 = 'fundraisehub_wizard_step2';

	/** Nonce action for the Step 3 sync form. */
	private const NONCE_STEP3 = 'fundraisehub_wizard_step3';

	/** Nonce action for the Finish form. */
	private const NONCE_FINISH = 'fundraisehub_wizard_finish';

	/**
	 * Transient key that carries sync results across the POST-redirect-GET cycle.
	 *
	 * Stores an array of WP_Post IDs for up to five minutes.
	 */
	private const SYNC_RESULTS_TRANSIENT = 'fundraisehub_wizard_sync_results';

	/**
	 * Transient key set by the activation hook to trigger a one-time redirect.
	 *
	 * Public so the activation hook in fundraisehub-core.php can reference it.
	 */
	public const ACTIVATION_REDIRECT_TRANSIENT = 'fundraisehub_activation_redirect';

	/**
	 * Register all hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_wizard_page' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect_to_wizard' ) );
		add_action( 'admin_post_fundraisehub_wizard_step1', array( $this, 'handle_step1' ) );
		add_action( 'admin_post_fundraisehub_wizard_step2', array( $this, 'handle_step2' ) );
		add_action( 'admin_post_fundraisehub_wizard_step3', array( $this, 'handle_step3' ) );
		add_action( 'admin_post_fundraisehub_wizard_finish', array( $this, 'handle_finish' ) );
	}

	/**
	 * Register the hidden wizard admin page.
	 *
	 * The page is not attached to any parent menu, so it does not appear in
	 * the WordPress admin navigation but is accessible via its direct URL:
	 * admin.php?page=fundraisehub-setup
	 */
	public function add_wizard_page(): void {
		add_submenu_page(
			null,
			__( 'FundRaiseHub Setup', 'fundraisehub-core' ),
			__( 'FundRaiseHub Setup', 'fundraisehub-core' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_wizard' )
		);
	}

	/**
	 * On the first admin page load after activation, redirect to the wizard.
	 *
	 * Reads – and immediately deletes – a short-lived transient set by the
	 * activation hook so the redirect fires exactly once and only for an
	 * administrator.
	 */
	public function maybe_redirect_to_wizard(): void {
		if ( ! get_transient( self::ACTIVATION_REDIRECT_TRANSIENT ) ) {
			return;
		}

		delete_transient( self::ACTIVATION_REDIRECT_TRANSIENT );

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Do not redirect if the administrator is already on the wizard.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( self::PAGE_SLUG === $page ) {
			return;
		}

		wp_safe_redirect( add_query_arg( 'page', self::PAGE_SLUG, admin_url( 'admin.php' ) ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// Form action handlers (admin_post_*)
	// -------------------------------------------------------------------------

	/**
	 * Handle the Step 1 (API Connection) form submission.
	 *
	 * Validates the nonce and capability, tests the supplied API credentials,
	 * then saves them and forwards the user to Step 2.  On failure the user is
	 * returned to Step 1 with an error message in the query string.
	 */
	public function handle_step1(): void {
		check_admin_referer( self::NONCE_STEP1 );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'fundraisehub-core' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above by check_admin_referer()
		$api_url = esc_url_raw(
			sanitize_text_field(
				wp_unslash( isset( $_POST['fundraisehub_api_url'] ) ? (string) $_POST['fundraisehub_api_url'] : '' )
			)
		);
		$api_key = sanitize_text_field(
			wp_unslash( isset( $_POST['fundraisehub_api_key'] ) ? (string) $_POST['fundraisehub_api_key'] : '' )
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$client = new ApiClient( $api_url, $api_key );
		$result = $client->test_connection();

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'  => self::PAGE_SLUG,
						'step'  => '1',
						'error' => rawurlencode( $result->get_error_message() ),
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		update_option( 'fundraisehub_api_url', $api_url );
		update_option( 'fundraisehub_api_key', $api_key );

		// Bust the API response cache for all endpoints ('') so new credentials
		// take effect immediately without waiting for the transient to expire.
		$client->bust_cache( '' );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => self::PAGE_SLUG,
					'step' => '2',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle the Step 2 (Display Settings) form submission.
	 *
	 * Saves the chosen campaign archive slug and advances to Step 3.
	 */
	public function handle_step2(): void {
		check_admin_referer( self::NONCE_STEP2 );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'fundraisehub-core' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above by check_admin_referer()
		$raw_slug      = isset( $_POST['fundraisehub_campaign_slug'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['fundraisehub_campaign_slug'] ) ) : 'campaigns';
		$campaign_slug = sanitize_title( $raw_slug );
		if ( '' === $campaign_slug ) {
			$campaign_slug = 'campaigns';
		}

		update_option( 'fundraisehub_campaign_slug', $campaign_slug );
		flush_rewrite_rules();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => self::PAGE_SLUG,
					'step' => '3',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle the Step 3 (Initial Sync) form submission.
	 *
	 * Runs a full campaign sync, stores a list of synced post IDs in a
	 * short-lived transient, then redirects back to Step 3 to display the
	 * results.
	 */
	public function handle_step3(): void {
		check_admin_referer( self::NONCE_STEP3 );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'fundraisehub-core' ) );
		}

		( new CampaignSync() )->sync_all();

		$post_ids = get_posts(
			array(
				'post_type'      => CampaignCPT::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);

		set_transient( self::SYNC_RESULTS_TRANSIENT, $post_ids, 5 * MINUTE_IN_SECONDS );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'   => self::PAGE_SLUG,
					'step'   => '3',
					'synced' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle the Finish button click.
	 *
	 * Clears the 'needs-setup' flag and the sync-results transient, then
	 * redirects the administrator to the main settings page.
	 */
	public function handle_finish(): void {
		check_admin_referer( self::NONCE_FINISH );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'fundraisehub-core' ) );
		}

		delete_option( 'fundraisehub_needs_setup' );
		delete_transient( self::SYNC_RESULTS_TRANSIENT );

		wp_safe_redirect( admin_url( 'options-general.php?page=fundraisehub-settings' ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// Render methods
	// -------------------------------------------------------------------------

	/**
	 * Render the wizard page, routing to the appropriate step.
	 */
	public function render_wizard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$step = isset( $_GET['step'] ) ? (int) $_GET['step'] : 1;
		if ( $step < 1 || $step > 3 ) {
			$step = 1;
		}

		$this->render_wizard_header( $step );

		switch ( $step ) {
			case 2:
				$this->render_step2();
				break;
			case 3:
				$this->render_step3();
				break;
			default:
				$this->render_step1();
				break;
		}

		$this->render_wizard_footer();
	}

	/**
	 * Render the wizard page header with title and step progress indicator.
	 *
	 * @param int $current_step The active step number (1–3).
	 */
	private function render_wizard_header( int $current_step ): void {
		$steps = array(
			1 => __( 'API Connection', 'fundraisehub-core' ),
			2 => __( 'Display Settings', 'fundraisehub-core' ),
			3 => __( 'Initial Sync', 'fundraisehub-core' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'FundRaiseHub Setup', 'fundraisehub-core' ); ?></h1>

			<div style="display:flex;gap:0;margin:1.5em 0 2em;border-bottom:2px solid #ddd;">
				<?php foreach ( $steps as $number => $label ) : ?>
					<?php
					$is_active    = ( $number === $current_step );
					$is_completed = ( $number < $current_step );
					$style        = 'padding:.75em 1.5em;font-weight:600;border-bottom:3px solid transparent;margin-bottom:-2px;';
					if ( $is_active ) {
						$style .= 'border-bottom-color:#2271b1;color:#2271b1;';
					} elseif ( $is_completed ) {
						$style .= 'color:#46b450;';
					} else {
						$style .= 'color:#999;';
					}
					?>
					<div style="<?php echo esc_attr( $style ); ?>">
						<?php if ( $is_completed ) : ?>
							<span aria-hidden="true">&#10003;&nbsp;</span>
						<?php else : ?>
							<?php echo esc_html( (string) $number ); ?>.&nbsp;
						<?php endif; ?>
						<?php echo esc_html( $label ); ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php
	}

	/**
	 * Render the wizard page footer.
	 */
	private function render_wizard_footer(): void {
		echo '</div><!-- .wrap -->';
	}

	/**
	 * Render Step 1: API Connection.
	 */
	private function render_step1(): void {
		$api_url = (string) get_option( 'fundraisehub_api_url', '' );
		if ( '' === $api_url ) {
			$api_url = (string) get_option( 'fundraisehub_site_url', '' );
		}

		$api_key = (string) get_option( 'fundraisehub_api_key', '' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$error = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['error'] ) ) : '';
		?>
		<h2><?php esc_html_e( 'Step 1: API Connection', 'fundraisehub-core' ); ?></h2>
		<p>
			<?php
			esc_html_e(
				'Enter your FundRaiseHub platform URL and API key. You can generate an API key in your FundRaiseHub dashboard under Settings → WordPress Connections.',
				'fundraisehub-core'
			);
			?>
		</p>

		<?php if ( '' !== $error ) : ?>
			<div class="notice notice-error inline">
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: error message from the API */
							__( 'Connection failed: %s', 'fundraisehub-core' ),
							$error
						)
					);
					?>
				</p>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="fundraisehub_wizard_step1" />
			<?php wp_nonce_field( self::NONCE_STEP1 ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="wizard_api_url"><?php esc_html_e( 'API URL', 'fundraisehub-core' ); ?></label>
					</th>
					<td>
						<input
							type="url"
							id="wizard_api_url"
							name="fundraisehub_api_url"
							value="<?php echo esc_attr( $api_url ); ?>"
							class="regular-text"
							placeholder="https://app.fundraisehub.com"
							required
						/>
						<p class="description"><?php esc_html_e( 'The base URL of your FundRaiseHub installation (no trailing slash).', 'fundraisehub-core' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wizard_api_key"><?php esc_html_e( 'API Key', 'fundraisehub-core' ); ?></label>
					</th>
					<td>
						<span style="display:inline-flex;align-items:center;gap:6px;">
							<input
								type="password"
								id="wizard_api_key"
								name="fundraisehub_api_key"
								value="<?php echo esc_attr( $api_key ); ?>"
								class="regular-text"
								autocomplete="off"
								required
							/>
							<button
								type="button"
								class="button"
								onclick="(function(btn){var f=document.getElementById('wizard_api_key');var show=f.type==='password';f.type=show?'text':'password';btn.textContent=show?'<?php echo esc_js( __( 'Hide', 'fundraisehub-core' ) ); ?>':'<?php echo esc_js( __( 'Show', 'fundraisehub-core' ) ); ?>';})(this)"
							><?php esc_html_e( 'Show', 'fundraisehub-core' ); ?></button>
						</span>
						<p class="description"><?php esc_html_e( 'Your FundRaiseHub API key. Keep this secret.', 'fundraisehub-core' ); ?></p>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Test & Continue', 'fundraisehub-core' ), 'primary', 'fundraisehub_wizard_step1_submit' ); ?>
		</form>
		<?php
	}

	/**
	 * Render Step 2: Display Settings.
	 */
	private function render_step2(): void {
		$campaign_slug = (string) get_option( 'fundraisehub_campaign_slug', 'campaigns' );
		if ( '' === $campaign_slug ) {
			$campaign_slug = 'campaigns';
		}

		$site_url = rtrim( (string) get_site_url(), '/' );
		?>
		<h2><?php esc_html_e( 'Step 2: Display Settings', 'fundraisehub-core' ); ?></h2>
		<p>
			<?php
			esc_html_e(
				'Choose the URL slug used for single campaign pages and the campaign archive.',
				'fundraisehub-core'
			);
			?>
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="fundraisehub_wizard_step2" />
			<?php wp_nonce_field( self::NONCE_STEP2 ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="wizard_campaign_slug">
							<?php esc_html_e( 'Campaign URL Slug', 'fundraisehub-core' ); ?>
						</label>
					</th>
					<td>
						<input
							type="text"
							id="wizard_campaign_slug"
							name="fundraisehub_campaign_slug"
							value="<?php echo esc_attr( $campaign_slug ); ?>"
							class="regular-text"
						/>
						<p class="description">
							<?php
							printf(
								wp_kses(
									/* translators: %s: preview URL including the slug */
									__( 'Campaign archive will be at: <code>%s</code>', 'fundraisehub-core' ),
									array( 'code' => array() )
								),
								esc_html( $site_url . '/' . $campaign_slug . '/' )
							);
							?>
						</p>
					</td>
				</tr>
			</table>

			<p>
				<a
					href="
					<?php
					echo esc_url(
						add_query_arg(
							array(
								'page' => self::PAGE_SLUG,
								'step' => '1',
							),
							admin_url( 'admin.php' )
						)
					);
					?>
							"
					class="button button-secondary"
				><?php esc_html_e( '← Back', 'fundraisehub-core' ); ?></a>
				&nbsp;
				<?php submit_button( __( 'Continue', 'fundraisehub-core' ), 'primary', 'fundraisehub_wizard_step2_submit', false ); ?>
			</p>
		</form>
		<?php
	}

	/**
	 * Render Step 3: Initial Sync.
	 *
	 * Shows a "Run Initial Sync" button on the first visit.  After the sync
	 * runs, displays the list of imported campaigns and a "Finish Setup" button.
	 */
	private function render_step3(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$synced = ! empty( $_GET['synced'] );
		?>
		<h2><?php esc_html_e( 'Step 3: Initial Sync', 'fundraisehub-core' ); ?></h2>

		<?php if ( ! $synced ) : ?>
			<p>
				<?php
				esc_html_e(
					'Click the button below to fetch your campaigns from FundRaiseHub and create local posts for each one.',
					'fundraisehub-core'
				);
				?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="fundraisehub_wizard_step3" />
				<?php wp_nonce_field( self::NONCE_STEP3 ); ?>

				<p>
					<a
						href="
						<?php
						echo esc_url(
							add_query_arg(
								array(
									'page' => self::PAGE_SLUG,
									'step' => '2',
								),
								admin_url( 'admin.php' )
							)
						);
						?>
								"
						class="button button-secondary"
					><?php esc_html_e( '← Back', 'fundraisehub-core' ); ?></a>
					&nbsp;
					<?php submit_button( __( 'Run Initial Sync', 'fundraisehub-core' ), 'primary', 'fundraisehub_wizard_step3_submit', false ); ?>
				</p>
			</form>
		<?php else : ?>
			<?php $this->render_sync_results(); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="fundraisehub_wizard_finish" />
				<?php wp_nonce_field( self::NONCE_FINISH ); ?>
				<?php submit_button( __( 'Finish Setup', 'fundraisehub-core' ), 'primary', 'fundraisehub_wizard_finish_submit' ); ?>
			</form>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render the table of campaigns that were imported in Step 3.
	 *
	 * Reads the list of post IDs from the SYNC_RESULTS_TRANSIENT and outputs
	 * a summary notice and a table with title, status, and quick-action links.
	 */
	private function render_sync_results(): void {
		$post_ids = get_transient( self::SYNC_RESULTS_TRANSIENT );

		if ( ! is_array( $post_ids ) || empty( $post_ids ) ) {
			echo '<div class="notice notice-warning inline"><p>' .
				esc_html__( 'Sync completed, but no campaigns were found. Check your API key and try again.', 'fundraisehub-core' ) .
				'</p></div>';
			return;
		}

		$count = count( $post_ids );

		echo '<div class="notice notice-success inline"><p>' .
			esc_html(
				sprintf(
					/* translators: %d: number of campaigns synced */
					_n(
						'%d campaign synced successfully.',
						'%d campaigns synced successfully.',
						$count,
						'fundraisehub-core'
					),
					$count
				)
			) .
			'</p></div>';

		echo '<table class="wp-list-table widefat fixed striped" style="margin-top:1em;">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Campaign', 'fundraisehub-core' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'fundraisehub-core' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'fundraisehub-core' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $post_ids as $post_id ) {
			$post = get_post( (int) $post_id );
			if ( ! $post ) {
				continue;
			}

			$title    = get_the_title( $post );
			$edit_url = get_edit_post_link( $post );
			$view_url = get_permalink( $post );

			echo '<tr>';
			echo '<td>' . esc_html( '' !== $title ? $title : __( '(no title)', 'fundraisehub-core' ) ) . '</td>';
			echo '<td>' . esc_html( ucfirst( (string) $post->post_status ) ) . '</td>';
			echo '<td>';
			if ( $edit_url ) {
				echo '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'fundraisehub-core' ) . '</a>';
			}
			if ( $view_url ) {
				echo '&nbsp;|&nbsp;<a href="' . esc_url( $view_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View', 'fundraisehub-core' ) . '</a>';
			}
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}
}
