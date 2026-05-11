<?php
/**
 * Tests for FundRaiseHub\Core\DashboardFeedback.
 */

declare( strict_types=1 );

use FundRaiseHub\Core\DashboardFeedback;
use PHPUnit\Framework\TestCase;

/**
 * Class DashboardFeedbackTest
 */
class DashboardFeedbackTest extends TestCase {

	protected function setUp(): void {
		WPTestState::reset();
		WPTestState::$current_user_can               = true;
		WPTestState::$options['fundraisehub_api_url'] = 'https://api.example.com';
		WPTestState::$options['fundraisehub_api_key'] = 'secret-key';
		if ( ! defined( 'FUNDRAISEHUB_CORE_VERSION' ) ) {
			define( 'FUNDRAISEHUB_CORE_VERSION', '1.0.0' );
		}
		$_POST                                       = array();
		$_GET                                        = array();
	}

	protected function tearDown(): void {
		$_POST = array();
		$_GET  = array();
	}

	/**
	 * Missing required feedback fields should redirect with error notice args.
	 */
	public function test_handle_feedback_submission_redirects_error_when_required_fields_missing(): void {
		$_POST['fundraisehub_feedback_subject'] = '';
		$_POST['fundraisehub_feedback_message'] = 'Looks good';

		$feedback = new DashboardFeedback();

		try {
			$feedback->handle_feedback_submission();
			$this->fail( 'Expected WPTestRedirectException was not thrown.' );
		} catch ( WPTestRedirectException $e ) {
			$this->assertStringContainsString( 'fundraisehub_submission=feedback', $e->getMessage() );
			$this->assertStringContainsString( 'fundraisehub_status=error', $e->getMessage() );
			$this->assertStringContainsString( 'fundraisehub_notice=Please+complete+all+feedback+fields.', $e->getMessage() );
			$this->assertSame( 0, WPTestState::$http_post_call_count );
		}
	}

	/**
	 * Successful feedback submission should POST expected endpoint and payload.
	 */
	public function test_handle_feedback_submission_posts_expected_payload_on_success(): void {
		$_POST['fundraisehub_feedback_subject'] = 'Feature request';
		$_POST['fundraisehub_feedback_message'] = 'Please add campaign drafts.';
		WPTestState::$http_response_queue[]     = WPTestState::http_ok( array( 'ok' => true ) );

		$feedback = new DashboardFeedback();

		try {
			$feedback->handle_feedback_submission();
			$this->fail( 'Expected WPTestRedirectException was not thrown.' );
		} catch ( WPTestRedirectException $e ) {
			$this->assertStringContainsString( 'fundraisehub_status=success', $e->getMessage() );
		}

		$this->assertSame( 1, WPTestState::$http_post_call_count );
		$this->assertStringContainsString( '/api/wp/v1/feedback', WPTestState::$http_post_urls[0] ?? '' );

		$args = WPTestState::$http_post_args[0] ?? array();
		$body = json_decode( (string) ( $args['body'] ?? '' ), true );

		$this->assertSame( 'Feature request', $body['subject'] ?? null );
		$this->assertSame( 'Please add campaign drafts.', $body['message'] ?? null );
		$this->assertSame( 'https://example.org', $body['site'] ?? null );
		$this->assertArrayNotHasKey( 'source', $body );
	}

	/**
	 * Missing required bug report fields should redirect with error notice args.
	 */
	public function test_handle_bug_report_submission_redirects_error_when_required_fields_missing(): void {
		$_POST['fundraisehub_bug_title']       = 'Broken form';
		$_POST['fundraisehub_bug_description'] = '';
		$_POST['fundraisehub_bug_steps']       = '1. Open dashboard';

		$feedback = new DashboardFeedback();

		try {
			$feedback->handle_bug_report_submission();
			$this->fail( 'Expected WPTestRedirectException was not thrown.' );
		} catch ( WPTestRedirectException $e ) {
			$this->assertStringContainsString( 'fundraisehub_submission=bug', $e->getMessage() );
			$this->assertStringContainsString( 'fundraisehub_status=error', $e->getMessage() );
			$this->assertStringContainsString( 'fundraisehub_notice=Please+complete+all+required+bug+report+fields.', $e->getMessage() );
			$this->assertSame( 0, WPTestState::$http_post_call_count );
		}
	}

	/**
	 * Successful bug report submission should POST expected endpoint and payload.
	 */
	public function test_handle_bug_report_submission_posts_expected_payload_on_success(): void {
		$_POST['fundraisehub_bug_title']       = 'Checkout failure';
		$_POST['fundraisehub_bug_description'] = 'Donation modal fails to open.';
		$_POST['fundraisehub_bug_steps']       = '1. Go to campaign page 2. Click Donate';
		WPTestState::$http_response_queue[]    = WPTestState::http_ok( array( 'ok' => true ) );

		$feedback = new DashboardFeedback();

		try {
			$feedback->handle_bug_report_submission();
			$this->fail( 'Expected WPTestRedirectException was not thrown.' );
		} catch ( WPTestRedirectException $e ) {
			$this->assertStringContainsString( 'fundraisehub_status=success', $e->getMessage() );
		}

		$this->assertSame( 1, WPTestState::$http_post_call_count );
		$this->assertStringContainsString( '/api/wp/v1/bug-reports', WPTestState::$http_post_urls[0] ?? '' );

		$args = WPTestState::$http_post_args[0] ?? array();
		$body = json_decode( (string) ( $args['body'] ?? '' ), true );

		$this->assertSame( 'Checkout failure', $body['title'] ?? null );
		$this->assertSame( 'Donation modal fails to open.', $body['description'] ?? null );
		$this->assertSame( '1. Go to campaign page 2. Click Donate', $body['steps'] ?? null );
		$this->assertSame( 'https://example.org', $body['site'] ?? null );
		$this->assertSame( '6.7', $body['wordpress'] ?? null );
		$this->assertSame( FUNDRAISEHUB_CORE_VERSION, $body['plugin'] ?? null );
		$this->assertSame( PHP_VERSION, $body['php'] ?? null );
		$this->assertArrayNotHasKey( 'source', $body );
	}

	/**
	 * Dashboard submissions should be trimmed to the backend field limits.
	 */
	public function test_handle_bug_report_submission_truncates_fields_to_api_limits(): void {
		$_POST['fundraisehub_bug_title']       = str_repeat( 'T', 180 );
		$_POST['fundraisehub_bug_description'] = str_repeat( 'D', 5100 );
		$_POST['fundraisehub_bug_steps']       = str_repeat( 'S', 5100 );
		WPTestState::$http_response_queue[]    = WPTestState::http_ok( array( 'success' => true ) );

		$feedback = new DashboardFeedback();

		try {
			$feedback->handle_bug_report_submission();
			$this->fail( 'Expected WPTestRedirectException was not thrown.' );
		} catch ( WPTestRedirectException $e ) {
			$this->assertStringContainsString( 'fundraisehub_status=success', $e->getMessage() );
		}

		$args = WPTestState::$http_post_args[0] ?? array();
		$body = json_decode( (string) ( $args['body'] ?? '' ), true );

		$this->assertSame( 150, strlen( $body['title'] ?? '' ) );
		$this->assertSame( 5000, strlen( $body['description'] ?? '' ) );
		$this->assertSame( 5000, strlen( $body['steps'] ?? '' ) );
	}

	/**
	 * Dashboard submissions should truncate UTF-8 text without splitting characters.
	 */
	public function test_handle_bug_report_submission_truncates_multibyte_fields_safely(): void {
		if ( ! function_exists( 'mb_strlen' ) || ! function_exists( 'mb_substr' ) ) {
			$this->markTestSkipped( 'mbstring is required for multibyte truncation assertions.' );
		}

		$_POST['fundraisehub_bug_title']       = str_repeat( '🙂', 180 );
		$_POST['fundraisehub_bug_description'] = str_repeat( 'é', 5100 );
		$_POST['fundraisehub_bug_steps']       = str_repeat( '界', 5100 );
		WPTestState::$http_response_queue[]    = WPTestState::http_ok( array( 'success' => true ) );

		$feedback = new DashboardFeedback();

		try {
			$feedback->handle_bug_report_submission();
			$this->fail( 'Expected WPTestRedirectException was not thrown.' );
		} catch ( WPTestRedirectException $e ) {
			$this->assertStringContainsString( 'fundraisehub_status=success', $e->getMessage() );
		}

		$args = WPTestState::$http_post_args[0] ?? array();
		$body = json_decode( (string) ( $args['body'] ?? '' ), true );

		$this->assertIsArray( $body );
		$this->assertSame( 150, mb_strlen( (string) ( $body['title'] ?? '' ), 'UTF-8' ) );
		$this->assertSame( 5000, mb_strlen( (string) ( $body['description'] ?? '' ), 'UTF-8' ) );
		$this->assertSame( 5000, mb_strlen( (string) ( $body['steps'] ?? '' ), 'UTF-8' ) );
	}
}
