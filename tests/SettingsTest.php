<?php
/**
 * Tests for FundRaiseHub\Core\Settings.
 *
 * All WordPress functions are replaced by stubs in tests/stubs.php.
 */

declare( strict_types=1 );

use FundRaiseHub\Core\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Class SettingsTest
 */
class SettingsTest extends TestCase {

	protected function setUp(): void {
		WPTestState::reset();
	}

	// -------------------------------------------------------------------------
	// sanitize_api_key()
	// -------------------------------------------------------------------------

	/**
	 * An empty API key should be returned as-is without testing the connection.
	 */
	public function test_sanitize_api_key_returns_empty_string_without_connection_test(): void {
		$settings = new Settings();
		$result   = $settings->sanitize_api_key( '' );

		$this->assertSame( '', $result );
		$this->assertSame( 0, WPTestState::$http_get_call_count, 'No HTTP call should happen for an empty key' );
	}

	/**
	 * sanitize_api_key() should strip tags and whitespace.
	 */
	public function test_sanitize_api_key_sanitizes_input(): void {
		// Queue a successful response so test_connection() doesn't fail.
		WPTestState::$http_response_queue[] = WPTestState::http_ok( array( 'name' => 'Org' ) );
		WPTestState::$options['fundraisehub_api_url'] = 'https://api.example.com';

		$settings = new Settings();
		$result   = $settings->sanitize_api_key( '  valid-key-123  ' );

		$this->assertSame( 'valid-key-123', $result );
	}

	/**
	 * On a successful connection test, a success settings error should be added.
	 */
	public function test_sanitize_api_key_adds_success_error_on_good_connection(): void {
		WPTestState::$http_response_queue[] = WPTestState::http_ok( array( 'name' => 'Org' ) );
		WPTestState::$options['fundraisehub_api_url'] = 'https://api.example.com';

		$settings = new Settings();
		$settings->sanitize_api_key( 'myvalidkey' );

		$errors = WPTestState::$settings_errors;
		$codes  = array_column( $errors, 'code' );
		$this->assertContains( 'fundraisehub_connection_success', $codes );
	}

	/**
	 * On a failed connection test, an error settings notice should be added.
	 */
	public function test_sanitize_api_key_adds_error_notice_on_bad_connection(): void {
		WPTestState::$http_response_queue[] = WPTestState::http_error( 401 );
		WPTestState::$options['fundraisehub_api_url'] = 'https://api.example.com';

		$settings = new Settings();
		$settings->sanitize_api_key( 'badkey' );

		$errors = WPTestState::$settings_errors;
		$this->assertNotEmpty( $errors );
		$types = array_column( $errors, 'type' );
		$this->assertContains( 'error', $types );
	}

	/**
	 * sanitize_api_key() returns the (stripped) key even when connection fails.
	 */
	public function test_sanitize_api_key_returns_key_even_on_connection_failure(): void {
		WPTestState::$http_response_queue[] = WPTestState::http_error( 500 );
		WPTestState::$options['fundraisehub_api_url'] = 'https://api.example.com';

		$settings = new Settings();
		$result   = $settings->sanitize_api_key( 'mykey' );

		$this->assertSame( 'mykey', $result );
	}

	/**
	 * sanitize_api_key() should read the submitted fundraisehub_api_url from
	 * $_POST so the connection test uses the new, not-yet-saved URL.
	 */
	public function test_sanitize_api_key_reads_api_url_from_post(): void {
		$_POST['fundraisehub_api_url'] = 'https://from-post.example.com';
		WPTestState::$http_response_queue[] = WPTestState::http_ok( array() );

		$settings = new Settings();
		$settings->sanitize_api_key( 'key123' );

		$this->assertStringContainsString(
			'from-post.example.com',
			WPTestState::$http_get_urls[0] ?? '',
			'sanitize_api_key() must use the URL submitted in the same POST, not the stored option'
		);

		unset( $_POST['fundraisehub_api_url'] );
	}

	/**
	 * sanitize_api_key() should preserve stored OAuth credentials when the posted
	 * OAuth fields are submitted as empty strings.
	 */
	public function test_sanitize_api_key_preserves_stored_oauth_credentials_when_posted_empty(): void {
		$_POST['fundraisehub_api_url']              = 'https://from-post.example.com';
		$_POST['fundraisehub_oauth_client_id']      = '';
		$_POST['fundraisehub_oauth_client_secret']  = '';
		WPTestState::$options['fundraisehub_oauth_client_id'] = 'stored-client-id';
		WPTestState::$options['fundraisehub_oauth_client_secret'] = 'stored-client-secret';

		WPTestState::$http_response_queue[] = WPTestState::http_ok(
			array(
				'access_token' => 'jwt-token',
				'expires_in'   => 3600,
			)
		);
		WPTestState::$http_response_queue[] = WPTestState::http_ok(
			array(
				'success' => true,
				'data'    => array( 'status' => 'ok' ),
			)
		);

		$settings = new Settings();
		$settings->sanitize_api_key( '' );

		$oauth_body = (string) ( WPTestState::$http_post_args[0]['body'] ?? '' );
		$this->assertStringContainsString( 'client_id=stored-client-id', $oauth_body );
		$this->assertStringContainsString( 'client_secret=stored-client-secret', $oauth_body );

		unset( $_POST['fundraisehub_api_url'], $_POST['fundraisehub_oauth_client_id'], $_POST['fundraisehub_oauth_client_secret'] );
	}

	// -------------------------------------------------------------------------
	// dismiss_setup_flag()
	// -------------------------------------------------------------------------

	/**
	 * dismiss_setup_flag() should delete the fundraisehub_needs_setup option.
	 */
	public function test_dismiss_setup_flag_deletes_needs_setup_option(): void {
		WPTestState::$options['fundraisehub_needs_setup'] = '1';

		$settings = new Settings();
		$settings->dismiss_setup_flag();

		$this->assertArrayNotHasKey( 'fundraisehub_needs_setup', WPTestState::$options );
	}

	// -------------------------------------------------------------------------
	// flush_rewrite_on_slug_change()
	// -------------------------------------------------------------------------

	/**
	 * flush_rewrite_on_slug_change() must flush rewrite rules when the slug changes.
	 */
	public function test_flush_rewrite_on_slug_change_flushes_when_value_differs(): void {
		$settings = new Settings();
		$settings->flush_rewrite_on_slug_change( 'old-slug', 'new-slug' );

		$this->assertTrue( WPTestState::$flushed_rewrite_rules );
	}

	/**
	 * flush_rewrite_on_slug_change() must NOT flush when the value is unchanged.
	 */
	public function test_flush_rewrite_on_slug_change_skips_flush_when_same(): void {
		$settings = new Settings();
		$settings->flush_rewrite_on_slug_change( 'same-slug', 'same-slug' );

		$this->assertFalse( WPTestState::$flushed_rewrite_rules );
	}

	/**
	 * sanitize_oauth_client_id() should preserve existing value when submitted empty.
	 */
	public function test_sanitize_oauth_client_id_preserves_existing_value(): void {
		WPTestState::$options['fundraisehub_oauth_client_id'] = 'existing-client-id';

		$settings = new Settings();
		$result   = $settings->sanitize_oauth_client_id( '' );

		$this->assertSame( 'existing-client-id', $result );
	}

	/**
	 * sanitize_oauth_client_secret() should preserve existing value when submitted empty.
	 */
	public function test_sanitize_oauth_client_secret_preserves_existing_value(): void {
		WPTestState::$options['fundraisehub_oauth_client_secret'] = 'existing-secret';

		$settings = new Settings();
		$result   = $settings->sanitize_oauth_client_secret( '' );

		$this->assertSame( 'existing-secret', $result );
	}
}
