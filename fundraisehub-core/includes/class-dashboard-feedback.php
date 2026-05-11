<?php
/**
 * Dashboard Feedback – admin dashboard widget for feedback and bug reports.
 *
 * @package FundRaiseHub\Core
 */

declare( strict_types=1 );

namespace FundRaiseHub\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DashboardFeedback
 *
 * Adds a WordPress Dashboard widget with two forms:
 * - General feedback
 * - Bug reports
 *
 * Submissions are sent to the FundRaiseHub backend via ApiClient::post().
 */
class DashboardFeedback {

	/** Dashboard widget slug. */
	private const WIDGET_ID = 'fundraisehub_dashboard_feedback';

	/** Admin-post action slug for feedback submissions. */
	private const FEEDBACK_ACTION = 'fundraisehub_submit_feedback';

	/** Admin-post action slug for bug report submissions. */
	private const BUG_REPORT_ACTION = 'fundraisehub_submit_bug_report';

	/** Default API endpoint for feedback submissions. */
	private const FEEDBACK_ENDPOINT = 'feedback';

	/** Default API endpoint for bug report submissions. */
	private const BUG_REPORT_ENDPOINT = 'bug-reports';

	/**
	 * API client instance used for backend submissions.
	 *
	 * @var ApiClient
	 */
	private ApiClient $api;

	/**
	 * Constructor.
	 *
	 * @param ApiClient|null $api Optional pre-configured API client.
	 */
	public function __construct( ?ApiClient $api = null ) {
		$this->api = $api ?? new ApiClient();
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
		add_action( 'admin_post_' . self::FEEDBACK_ACTION, array( $this, 'handle_feedback_submission' ) );
		add_action( 'admin_post_' . self::BUG_REPORT_ACTION, array( $this, 'handle_bug_report_submission' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_submission_notice' ) );
	}

	/**
	 * Add the dashboard widget.
	 */
	public function add_dashboard_widget(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			self::WIDGET_ID,
			__( 'FundRaiseHub Feedback & Bug Reports', 'fundraisehub-core' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Render dashboard widget content.
	 */
	public function render_dashboard_widget(): void {
		?>
		<p><?php esc_html_e( 'Send product feedback or report a bug directly to your FundRaiseHub backend.', 'fundraisehub-core' ); ?></p>

		<h4><?php esc_html_e( 'Feedback', 'fundraisehub-core' ); ?></h4>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::FEEDBACK_ACTION ); ?>" />
			<?php wp_nonce_field( self::FEEDBACK_ACTION ); ?>
			<p>
				<label for="fundraisehub_feedback_subject"><strong><?php esc_html_e( 'Subject', 'fundraisehub-core' ); ?></strong></label><br />
				<input type="text" id="fundraisehub_feedback_subject" name="fundraisehub_feedback_subject" class="widefat" maxlength="150" required />
			</p>
			<p>
				<label for="fundraisehub_feedback_message"><strong><?php esc_html_e( 'Message', 'fundraisehub-core' ); ?></strong></label><br />
				<textarea id="fundraisehub_feedback_message" name="fundraisehub_feedback_message" class="widefat" rows="4" required></textarea>
			</p>
			<?php submit_button( __( 'Send Feedback', 'fundraisehub-core' ), 'secondary', 'submit', false ); ?>
		</form>

		<hr />

		<h4><?php esc_html_e( 'Bug Report', 'fundraisehub-core' ); ?></h4>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::BUG_REPORT_ACTION ); ?>" />
			<?php wp_nonce_field( self::BUG_REPORT_ACTION ); ?>
			<p>
				<label for="fundraisehub_bug_title"><strong><?php esc_html_e( 'Title', 'fundraisehub-core' ); ?></strong></label><br />
				<input type="text" id="fundraisehub_bug_title" name="fundraisehub_bug_title" class="widefat" maxlength="150" required />
			</p>
			<p>
				<label for="fundraisehub_bug_description"><strong><?php esc_html_e( 'Description', 'fundraisehub-core' ); ?></strong></label><br />
				<textarea id="fundraisehub_bug_description" name="fundraisehub_bug_description" class="widefat" rows="4" required></textarea>
			</p>
			<p>
				<label for="fundraisehub_bug_steps"><strong><?php esc_html_e( 'Steps to reproduce (optional)', 'fundraisehub-core' ); ?></strong></label><br />
				<textarea id="fundraisehub_bug_steps" name="fundraisehub_bug_steps" class="widefat" rows="3"></textarea>
			</p>
			<?php submit_button( __( 'Report Bug', 'fundraisehub-core' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}

	/**
	 * Handle feedback form submission.
	 */
	public function handle_feedback_submission(): void {
		check_admin_referer( self::FEEDBACK_ACTION );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'fundraisehub-core' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified by check_admin_referer().
		$subject = ( isset( $_POST['fundraisehub_feedback_subject'] ) && is_string( $_POST['fundraisehub_feedback_subject'] ) )
			? sanitize_text_field( wp_unslash( $_POST['fundraisehub_feedback_subject'] ) )
			: '';
		$message = ( isset( $_POST['fundraisehub_feedback_message'] ) && is_string( $_POST['fundraisehub_feedback_message'] ) )
			? sanitize_textarea_field( wp_unslash( $_POST['fundraisehub_feedback_message'] ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$subject = $this->truncate_field( $subject, 150 );
		$message = $this->truncate_field( $message, 5000 );
		$site    = $this->get_submission_site_url();

		if ( '' === $subject || '' === $message ) {
			$this->redirect_with_status( 'feedback', 'error', __( 'Please complete all feedback fields.', 'fundraisehub-core' ) );
		}

		if ( '' === $site ) {
			$this->redirect_with_status( 'feedback', 'error', __( 'Unable to determine this WordPress site URL.', 'fundraisehub-core' ) );
		}

		$response = $this->api->post(
			self::FEEDBACK_ENDPOINT,
			array(
				'subject' => $subject,
				'message' => $message,
				'site'    => $site,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->redirect_with_status( 'feedback', 'error', $response->get_error_message() );
		}

		$this->redirect_with_status( 'feedback', 'success', __( 'Feedback sent successfully.', 'fundraisehub-core' ) );
	}

	/**
	 * Handle bug report form submission.
	 */
	public function handle_bug_report_submission(): void {
		check_admin_referer( self::BUG_REPORT_ACTION );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'fundraisehub-core' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified by check_admin_referer().
		$title       = ( isset( $_POST['fundraisehub_bug_title'] ) && is_string( $_POST['fundraisehub_bug_title'] ) )
			? sanitize_text_field( wp_unslash( $_POST['fundraisehub_bug_title'] ) )
			: '';
		$description = ( isset( $_POST['fundraisehub_bug_description'] ) && is_string( $_POST['fundraisehub_bug_description'] ) )
			? sanitize_textarea_field( wp_unslash( $_POST['fundraisehub_bug_description'] ) )
			: '';
		$steps       = ( isset( $_POST['fundraisehub_bug_steps'] ) && is_string( $_POST['fundraisehub_bug_steps'] ) )
			? sanitize_textarea_field( wp_unslash( $_POST['fundraisehub_bug_steps'] ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$title       = $this->truncate_field( $title, 150 );
		$description = $this->truncate_field( $description, 5000 );
		$steps       = $this->truncate_field( $steps, 5000 );
		$site        = $this->get_submission_site_url();

		if ( '' === $title || '' === $description ) {
			$this->redirect_with_status( 'bug', 'error', __( 'Please complete all required bug report fields.', 'fundraisehub-core' ) );
		}

		if ( '' === $site ) {
			$this->redirect_with_status( 'bug', 'error', __( 'Unable to determine this WordPress site URL.', 'fundraisehub-core' ) );
		}

		$response = $this->api->post(
			self::BUG_REPORT_ENDPOINT,
			array(
				'title'       => $title,
				'description' => $description,
				'steps'       => $steps,
				'site'        => $site,
				'wordpress'   => $this->truncate_field( (string) get_bloginfo( 'version' ), 50 ),
				'plugin'      => $this->truncate_field( (string) FUNDRAISEHUB_CORE_VERSION, 50 ),
				'php'         => $this->truncate_field( PHP_VERSION, 50 ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->redirect_with_status( 'bug', 'error', $response->get_error_message() );
		}

		$this->redirect_with_status( 'bug', 'success', __( 'Bug report sent successfully.', 'fundraisehub-core' ) );
	}

	/**
	 * Show submission result notice on the dashboard page.
	 */
	public function maybe_show_submission_notice(): void {
		global $pagenow;

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( 'index.php' !== $pagenow ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only notice state from redirect query args.
		$type   = ( isset( $_GET['fundraisehub_submission'] ) && is_string( $_GET['fundraisehub_submission'] ) )
			? sanitize_key( wp_unslash( $_GET['fundraisehub_submission'] ) )
			: '';
		$status = ( isset( $_GET['fundraisehub_status'] ) && is_string( $_GET['fundraisehub_status'] ) )
			? sanitize_key( wp_unslash( $_GET['fundraisehub_status'] ) )
			: '';
		$text   = ( isset( $_GET['fundraisehub_notice'] ) && is_string( $_GET['fundraisehub_notice'] ) )
			? sanitize_text_field( wp_unslash( $_GET['fundraisehub_notice'] ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( '' === $type || '' === $status || '' === $text ) {
			return;
		}

		$class = 'success' === $status ? 'notice notice-success is-dismissible' : 'notice notice-error is-dismissible';

		echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $text ) . '</p></div>';
	}

	/**
	 * Redirect back to dashboard with submission status in query args.
	 *
	 * @param string $type    Submission type.
	 * @param string $status  Result status.
	 * @param string $message Notice message.
	 */
	private function redirect_with_status( string $type, string $status, string $message ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'fundraisehub_submission' => sanitize_key( $type ),
					'fundraisehub_status'     => sanitize_key( $status ),
					'fundraisehub_notice'     => $message,
				),
				admin_url( 'index.php' )
			)
		);
		exit;
	}

	/**
	 * Limit an API field to the documented maximum length.
	 *
	 * @param string $value      Field value.
	 * @param int    $max_length Maximum length.
	 *
	 * @return string
	 */
	private function truncate_field( string $value, int $max_length ): string {
		$value = trim( $value );

		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			if ( mb_strlen( $value, 'UTF-8' ) <= $max_length ) {
				return $value;
			}

			return mb_substr( $value, 0, $max_length, 'UTF-8' );
		}

		if ( strlen( $value ) <= $max_length ) {
			return $value;
		}

		return substr( $value, 0, $max_length );
	}

	/**
	 * Build a site URL value that matches the backend contract expectations.
	 *
	 * @return string
	 */
	private function get_submission_site_url(): string {
		$site_url = $this->truncate_field( esc_url_raw( home_url() ), 255 );
		$scheme   = (string) wp_parse_url( $site_url, PHP_URL_SCHEME );
		$host     = (string) wp_parse_url( $site_url, PHP_URL_HOST );

		if ( '' === $scheme || '' === $host || ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return '';
		}

		return $site_url;
	}
}
