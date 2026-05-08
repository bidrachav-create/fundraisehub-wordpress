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
		$subject = isset( $_POST['fundraisehub_feedback_subject'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['fundraisehub_feedback_subject'] ) )
			: '';
		$message = isset( $_POST['fundraisehub_feedback_message'] )
			? sanitize_textarea_field( wp_unslash( (string) $_POST['fundraisehub_feedback_message'] ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( '' === $subject || '' === $message ) {
			$this->redirect_with_status( 'feedback', 'error', __( 'Please complete all feedback fields.', 'fundraisehub-core' ) );
		}

		$response = ( new ApiClient() )->post(
			self::FEEDBACK_ENDPOINT,
			array(
				'subject' => $subject,
				'message' => $message,
				'source'  => 'wordpress_dashboard',
				'site'    => home_url(),
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
		$title       = isset( $_POST['fundraisehub_bug_title'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['fundraisehub_bug_title'] ) )
			: '';
		$description = isset( $_POST['fundraisehub_bug_description'] )
			? sanitize_textarea_field( wp_unslash( (string) $_POST['fundraisehub_bug_description'] ) )
			: '';
		$steps       = isset( $_POST['fundraisehub_bug_steps'] )
			? sanitize_textarea_field( wp_unslash( (string) $_POST['fundraisehub_bug_steps'] ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( '' === $title || '' === $description ) {
			$this->redirect_with_status( 'bug', 'error', __( 'Please complete all required bug report fields.', 'fundraisehub-core' ) );
		}

		$response = ( new ApiClient() )->post(
			self::BUG_REPORT_ENDPOINT,
			array(
				'title'       => $title,
				'description' => $description,
				'steps'       => $steps,
				'source'      => 'wordpress_dashboard',
				'site'        => home_url(),
				'wordpress'   => get_bloginfo( 'version' ),
				'plugin'      => FUNDRAISEHUB_CORE_VERSION,
				'php'         => PHP_VERSION,
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

		if ( 'index.php' !== $pagenow ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only notice state from redirect query args.
		$type   = isset( $_GET['fundraisehub_submission'] ) ? sanitize_key( wp_unslash( (string) $_GET['fundraisehub_submission'] ) ) : '';
		$status = isset( $_GET['fundraisehub_status'] ) ? sanitize_key( wp_unslash( (string) $_GET['fundraisehub_status'] ) ) : '';
		$text   = isset( $_GET['fundraisehub_notice'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['fundraisehub_notice'] ) ) : '';
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
					'fundraisehub_notice'     => rawurlencode( $message ),
				),
				admin_url( 'index.php' )
			)
		);
		exit;
	}
}
