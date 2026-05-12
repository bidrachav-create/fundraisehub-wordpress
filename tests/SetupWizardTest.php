<?php
/**
 * Tests for FundRaiseHub\Core\SetupWizard.
 *
 * All WordPress functions are replaced by stubs in tests/stubs.php.
 * wp_safe_redirect() throws WPTestRedirectException to avoid calling exit().
 */

declare( strict_types=1 );

use FundRaiseHub\Core\SetupWizard;
use PHPUnit\Framework\TestCase;

/**
 * Class SetupWizardTest
 */
class SetupWizardTest extends TestCase {

	protected function setUp(): void {
		WPTestState::reset();
		// Simulate an admin user for all wizard tests.
		WPTestState::$current_user_can = true;
	}

	protected function tearDown(): void {
		// Clean up any $_POST / $_GET pollution from test setup.
		$_POST = array();
		$_GET  = array();
	}

	// -------------------------------------------------------------------------
	// handle_step1()
	// -------------------------------------------------------------------------

	/**
	 * A successful API connection should save the credentials and redirect to step 2.
	 */
	public function test_handle_step1_saves_credentials_and_redirects_to_step2(): void {
		$_POST['fundraisehub_api_url'] = 'https://api.example.com';
		$_POST['fundraisehub_api_key'] = 'secret-key';

		// Queue a successful connection response.
		WPTestState::$http_response_queue[] = WPTestState::http_ok( array( 'name' => 'Org' ) );

		$wizard = new SetupWizard();

		try {
			$wizard->handle_step1();
			$this->fail( 'Expected WPTestRedirectException was not thrown' );
		} catch ( WPTestRedirectException $e ) {
			// Credentials should have been saved.
			$this->assertSame( 'https://api.example.com', WPTestState::$options['fundraisehub_api_url'] );
			$this->assertSame( 'secret-key', WPTestState::$options['fundraisehub_api_key'] );

			// Should redirect to step 2.
			$this->assertStringContainsString( 'step=2', $e->getMessage() );
		}
	}

	/**
	 * A failed API connection should redirect back to step 1 with an error.
	 */
	public function test_handle_step1_redirects_to_step1_with_error_on_failure(): void {
		$_POST['fundraisehub_api_url'] = 'https://api.example.com';
		$_POST['fundraisehub_api_key'] = 'bad-key';

		WPTestState::$http_response_queue[] = WPTestState::http_error( 401 );

		$wizard = new SetupWizard();

		try {
			$wizard->handle_step1();
			$this->fail( 'Expected WPTestRedirectException was not thrown' );
		} catch ( WPTestRedirectException $e ) {
			// Must NOT have saved credentials.
			$this->assertArrayNotHasKey( 'fundraisehub_api_key', WPTestState::$options );

			// Should redirect back to step 1 with an error parameter.
			$this->assertStringContainsString( 'step=1', $e->getMessage() );
			$this->assertStringContainsString( 'error=', $e->getMessage() );
		}
	}

	/**
	 * handle_step1() must call bust_cache() after a successful save.
	 */
	public function test_handle_step1_busts_api_cache_on_success(): void {
		$_POST['fundraisehub_api_url'] = 'https://api.example.com';
		$_POST['fundraisehub_api_key'] = 'good-key';

		// Response for test_connection().
		WPTestState::$http_response_queue[] = WPTestState::http_ok( array() );

		$wizard = new SetupWizard();

		try {
			$wizard->handle_step1();
		} catch ( WPTestRedirectException $e ) {
			// Cache version should have been bumped (bust_cache increments it).
			$cache_ver = WPTestState::$options['fundraisehub_api_cache_ver'] ?? 1;
			$this->assertGreaterThanOrEqual( 2, $cache_ver );
		}
	}

	/**
	 * OAuth-only credentials should be accepted and saved in step 1.
	 */
	public function test_handle_step1_accepts_oauth_credentials_without_api_key(): void {
		$_POST['fundraisehub_api_url']              = 'https://api.example.com';
		$_POST['fundraisehub_oauth_client_id']      = 'oauth-client-id';
		$_POST['fundraisehub_oauth_client_secret']  = 'oauth-client-secret';
		$_POST['fundraisehub_api_key']              = '';

		WPTestState::$http_response_queue[] = WPTestState::http_ok(
			array(
				'access_token' => 'jwt',
				'expires_in'   => 3600,
			)
		);
		WPTestState::$http_response_queue[] = WPTestState::http_ok( array( 'success' => true ) );

		$wizard = new SetupWizard();

		try {
			$wizard->handle_step1();
			$this->fail( 'Expected WPTestRedirectException was not thrown' );
		} catch ( WPTestRedirectException $e ) {
			$this->assertSame( 'oauth-client-id', WPTestState::$options['fundraisehub_oauth_client_id'] ?? '' );
			$this->assertSame( 'oauth-client-secret', WPTestState::$options['fundraisehub_oauth_client_secret'] ?? '' );
			$this->assertStringContainsString( 'step=2', $e->getMessage() );
		}
	}

	// -------------------------------------------------------------------------
	// handle_step2()
	// -------------------------------------------------------------------------

	/**
	 * handle_step2() should save and sanitize the campaign slug, then redirect to step 3.
	 */
	public function test_handle_step2_saves_slug_and_redirects_to_step3(): void {
		$_POST['fundraisehub_campaign_slug'] = 'My Cool Campaigns!';

		$wizard = new SetupWizard();

		try {
			$wizard->handle_step2();
			$this->fail( 'Expected WPTestRedirectException was not thrown' );
		} catch ( WPTestRedirectException $e ) {
			$saved_slug = WPTestState::$options['fundraisehub_campaign_slug'] ?? '';
			// sanitize_title() should have cleaned the slug.
			$this->assertSame( 'my-cool-campaigns', $saved_slug );

			// Should redirect to step 3.
			$this->assertStringContainsString( 'step=3', $e->getMessage() );
		}
	}

	/**
	 * An empty slug should fall back to 'campaigns'.
	 */
	public function test_handle_step2_defaults_to_campaigns_for_empty_slug(): void {
		$_POST['fundraisehub_campaign_slug'] = '   ';

		$wizard = new SetupWizard();

		try {
			$wizard->handle_step2();
		} catch ( WPTestRedirectException $e ) {
			$this->assertSame( 'campaigns', WPTestState::$options['fundraisehub_campaign_slug'] ?? '' );
		}
	}

	/**
	 * handle_step2() should flush rewrite rules.
	 */
	public function test_handle_step2_flushes_rewrite_rules(): void {
		$_POST['fundraisehub_campaign_slug'] = 'campaigns';

		$wizard = new SetupWizard();

		try {
			$wizard->handle_step2();
		} catch ( WPTestRedirectException $e ) {
			$this->assertTrue( WPTestState::$flushed_rewrite_rules );
		}
	}

	// -------------------------------------------------------------------------
	// handle_finish()
	// -------------------------------------------------------------------------

	/**
	 * handle_finish() should delete the needs-setup flag and redirect to settings.
	 */
	public function test_handle_finish_deletes_needs_setup_and_redirects(): void {
		WPTestState::$options['fundraisehub_needs_setup'] = '1';

		$wizard = new SetupWizard();

		try {
			$wizard->handle_finish();
			$this->fail( 'Expected WPTestRedirectException was not thrown' );
		} catch ( WPTestRedirectException $e ) {
			$this->assertArrayNotHasKey( 'fundraisehub_needs_setup', WPTestState::$options );
			$this->assertStringContainsString( 'fundraisehub-settings', $e->getMessage() );
		}
	}

	// -------------------------------------------------------------------------
	// Activation redirect transient constant
	// -------------------------------------------------------------------------

	/**
	 * The ACTIVATION_REDIRECT_TRANSIENT constant must match the value used by
	 * the plugin's activation hook in fundraisehub-core.php.
	 */
	public function test_activation_redirect_transient_constant_is_defined(): void {
		$this->assertSame(
			'fundraisehub_activation_redirect',
			SetupWizard::ACTIVATION_REDIRECT_TRANSIENT
		);
	}
}
