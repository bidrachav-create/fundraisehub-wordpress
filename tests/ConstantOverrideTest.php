<?php
/**
 * Tests for constant/environment override behaviour in ApiClient and Settings.
 *
 * PHP constants (FUNDRAISEHUB_API_URL / FUNDRAISEHUB_API_KEY) cannot be
 * undefined once set, so the production classes expose the constant-reads as
 * protected methods. Test doubles override those methods to simulate "constant
 * is defined" without polluting the PHP runtime for other test classes.
 */

declare( strict_types=1 );

use FundRaiseHub\Core\ApiClient;
use FundRaiseHub\Core\Settings;
use PHPUnit\Framework\TestCase;

// ---------------------------------------------------------------------------
// Test doubles – simulate defined constants via subclassing.
// ---------------------------------------------------------------------------

/**
 * ApiClient subclass that pretends FUNDRAISEHUB_API_URL is defined.
 */
class ApiClientWithConstantUrl extends ApiClient {
	protected function read_api_url_constant(): string {
		return 'https://constant-url.example.com';
	}
}

/**
 * ApiClient subclass that pretends FUNDRAISEHUB_API_KEY is defined.
 */
class ApiClientWithConstantKey extends ApiClient {
	protected function read_api_key_constant(): string {
		return 'constant-api-key';
	}
}

/**
 * ApiClient subclass that pretends both constants are defined.
 */
class ApiClientWithBothConstants extends ApiClient {
	protected function read_api_url_constant(): string {
		return 'https://constant-url.example.com';
	}

	protected function read_api_key_constant(): string {
		return 'constant-api-key';
	}
}

/**
 * Settings subclass that pretends FUNDRAISEHUB_API_URL is defined.
 */
class SettingsWithConstantUrl extends Settings {
	protected function read_api_url_constant(): string {
		return 'https://constant-url.example.com';
	}
}

/**
 * Settings subclass that pretends FUNDRAISEHUB_API_KEY is defined.
 */
class SettingsWithConstantKey extends Settings {
	protected function read_api_key_constant(): string {
		return 'constant-api-key';
	}
}

/**
 * Settings subclass that pretends both constants are defined.
 */
class SettingsWithBothConstants extends Settings {
	protected function read_api_url_constant(): string {
		return 'https://constant-url.example.com';
	}

	protected function read_api_key_constant(): string {
		return 'constant-api-key';
	}
}

// ---------------------------------------------------------------------------
// Test cases.
// ---------------------------------------------------------------------------

/**
 * Class ConstantOverrideTest
 */
class ConstantOverrideTest extends TestCase {

	protected function setUp(): void {
		WPTestState::reset();
	}

	// -------------------------------------------------------------------------
	// ApiClient – URL constant
	// -------------------------------------------------------------------------

	/**
	 * When FUNDRAISEHUB_API_URL is defined it takes precedence over the stored option.
	 */
	public function test_api_client_uses_url_constant_over_option(): void {
		WPTestState::$options['fundraisehub_api_url'] = 'https://option-url.example.com';
		WPTestState::$http_response_queue[]            = WPTestState::http_ok( array() );

		$client = new ApiClientWithConstantUrl();
		$client->get( 'campaigns' );

		$this->assertStringContainsString(
			'constant-url.example.com',
			WPTestState::$http_get_urls[0],
			'ApiClient must use FUNDRAISEHUB_API_URL constant, not the stored option'
		);
		$this->assertStringNotContainsString( 'option-url.example.com', WPTestState::$http_get_urls[0] );
	}

	/**
	 * An explicit constructor argument still overrides the URL constant.
	 */
	public function test_api_client_explicit_url_param_overrides_constant(): void {
		WPTestState::$http_response_queue[] = WPTestState::http_ok( array() );

		$client = new ApiClientWithConstantUrl( 'https://explicit.example.com', 'key' );
		$client->get( 'campaigns' );

		$this->assertStringContainsString(
			'explicit.example.com',
			WPTestState::$http_get_urls[0],
			'An explicit constructor arg must take priority over the constant'
		);
	}

	// -------------------------------------------------------------------------
	// ApiClient – key constant
	// -------------------------------------------------------------------------

	/**
	 * When FUNDRAISEHUB_API_KEY is defined it takes precedence over the stored option.
	 *
	 * The Authorization header captured by the wp_remote_get stub must contain
	 * the constant key, not the value from the DB option.
	 */
	public function test_api_client_uses_key_constant_over_option(): void {
		WPTestState::$options['fundraisehub_api_key'] = 'option-api-key';
		WPTestState::$http_response_queue[]            = WPTestState::http_ok( array() );

		$client = new ApiClientWithConstantKey();
		$result = $client->test_connection();

		$this->assertTrue( $result );

		$args = WPTestState::$http_get_args[0] ?? array();
		$this->assertSame(
			'Bearer constant-api-key',
			$args['headers']['Authorization'] ?? '',
			'Authorization header must use the constant key, not the DB option key'
		);
	}

	/**
	 * An explicit constructor argument still overrides the key constant.
	 */
	public function test_api_client_explicit_key_param_overrides_constant(): void {
		WPTestState::$http_response_queue[] = WPTestState::http_ok( array() );

		$client = new ApiClientWithConstantKey( 'https://api.example.com', 'explicit-key' );
		$result = $client->test_connection();

		$this->assertTrue( $result );

		$args = WPTestState::$http_get_args[0] ?? array();
		$this->assertSame(
			'Bearer explicit-key',
			$args['headers']['Authorization'] ?? '',
			'An explicit constructor key must take priority over the constant'
		);
	}

	// -------------------------------------------------------------------------
	// ApiClient – both constants
	// -------------------------------------------------------------------------

	/**
	 * When both constants are defined, the constant URL and key are both used.
	 */
	public function test_api_client_uses_both_constants_over_options(): void {
		WPTestState::$options['fundraisehub_api_url'] = 'https://option-url.example.com';
		WPTestState::$options['fundraisehub_api_key'] = 'option-api-key';
		WPTestState::$http_response_queue[]            = WPTestState::http_ok( array() );

		$client = new ApiClientWithBothConstants();
		$client->get( 'campaigns' );

		$this->assertStringContainsString(
			'constant-url.example.com',
			WPTestState::$http_get_urls[0],
			'URL must come from the constant, not the DB option'
		);

		$args = WPTestState::$http_get_args[0] ?? array();
		$this->assertSame(
			'Bearer constant-api-key',
			$args['headers']['Authorization'] ?? '',
			'API key must come from the constant, not the DB option'
		);
	}

	// -------------------------------------------------------------------------
	// Settings – sanitize_api_url() with constant
	// -------------------------------------------------------------------------

	/**
	 * sanitize_api_url() must discard the submitted value when the URL is
	 * managed by a constant and return the currently stored option instead.
	 */
	public function test_sanitize_api_url_returns_stored_option_when_constant_defined(): void {
		WPTestState::$options['fundraisehub_api_url'] = 'https://stored-url.example.com';

		$settings = new SettingsWithConstantUrl();
		$result   = $settings->sanitize_api_url( 'https://submitted-url.example.com' );

		$this->assertSame(
			'https://stored-url.example.com',
			$result,
			'sanitize_api_url() must not persist a form submission when FUNDRAISEHUB_API_URL is set'
		);
	}

	/**
	 * sanitize_api_url() must NOT fall back to the built-in default when the
	 * option is unset and the URL is env-managed. The constant is authoritative;
	 * writing an incorrect placeholder to the DB would be misleading.
	 */
	public function test_sanitize_api_url_returns_empty_not_default_when_env_managed_and_option_unset(): void {
		// No stored option – env-managed, so nothing should be written.
		$settings = new SettingsWithConstantUrl();
		$result   = $settings->sanitize_api_url( 'https://submitted-url.example.com' );

		$this->assertSame( '', $result );
	}

	/**
	 * sanitize_api_url() should sanitize and return the submitted value when no constant is defined.
	 */
	public function test_sanitize_api_url_returns_submitted_value_without_constant(): void {
		$settings = new Settings();
		$result   = $settings->sanitize_api_url( 'https://new-url.example.com' );

		$this->assertSame( 'https://new-url.example.com', $result );
	}

	// -------------------------------------------------------------------------
	// Settings – both constants
	// -------------------------------------------------------------------------

	/**
	 * When both FUNDRAISEHUB_API_URL and FUNDRAISEHUB_API_KEY are defined,
	 * both sanitize callbacks must discard submitted values.
	 */
	public function test_settings_with_both_constants_discards_all_form_values(): void {
		WPTestState::$options['fundraisehub_api_url'] = 'https://stored-url.example.com';
		WPTestState::$options['fundraisehub_api_key'] = 'stored-key';

		$settings = new SettingsWithBothConstants();

		$url_result = $settings->sanitize_api_url( 'https://submitted-url.example.com' );
		$key_result = $settings->sanitize_api_key( 'submitted-key' );

		$this->assertSame( 'https://stored-url.example.com', $url_result );
		$this->assertSame( 'stored-key', $key_result );
		$this->assertSame( 0, WPTestState::$http_get_call_count, 'No HTTP call when both are env-managed' );
	}

	// -------------------------------------------------------------------------
	// Settings – sanitize_api_key() with constant
	// -------------------------------------------------------------------------

	/**
	 * sanitize_api_key() must discard the submitted value when the key is
	 * managed by a constant and return the currently stored option instead.
	 */
	public function test_sanitize_api_key_returns_stored_option_when_constant_defined(): void {
		WPTestState::$options['fundraisehub_api_key'] = 'stored-key';

		$settings = new SettingsWithConstantKey();
		$result   = $settings->sanitize_api_key( 'submitted-key' );

		$this->assertSame(
			'stored-key',
			$result,
			'sanitize_api_key() must not persist a form submission when FUNDRAISEHUB_API_KEY is set'
		);
		$this->assertSame( 0, WPTestState::$http_get_call_count, 'No HTTP call should happen when key is env-managed' );
	}

	// -------------------------------------------------------------------------
	// Settings – sanitize_api_key() empty-submission key preservation
	// -------------------------------------------------------------------------

	/**
	 * Submitting an empty API key when one is already stored should preserve
	 * the existing key (avoid accidental erasure).
	 */
	public function test_sanitize_api_key_preserves_stored_key_when_empty_submitted(): void {
		WPTestState::$options['fundraisehub_api_key'] = 'existing-key';

		$settings = new Settings();
		$result   = $settings->sanitize_api_key( '' );

		$this->assertSame(
			'existing-key',
			$result,
			'Submitting empty key must not erase the stored key'
		);
		$this->assertSame( 0, WPTestState::$http_get_call_count, 'No connection test should run for an empty submission' );
	}

	/**
	 * Submitting an empty API key when no key is stored should return empty string.
	 */
	public function test_sanitize_api_key_returns_empty_when_no_stored_key_and_empty_submitted(): void {
		// No stored key in options.
		$settings = new Settings();
		$result   = $settings->sanitize_api_key( '' );

		$this->assertSame( '', $result );
		$this->assertSame( 0, WPTestState::$http_get_call_count );
	}

	/**
	 * Submitting a new non-empty key should replace the stored key.
	 */
	public function test_sanitize_api_key_replaces_stored_key_when_new_value_submitted(): void {
		WPTestState::$options['fundraisehub_api_key'] = 'old-key';
		WPTestState::$options['fundraisehub_api_url'] = 'https://api.example.com';
		WPTestState::$http_response_queue[]            = WPTestState::http_ok( array( 'name' => 'Org' ) );

		$settings = new Settings();
		$result   = $settings->sanitize_api_key( 'new-key' );

		$this->assertSame( 'new-key', $result );
	}

	// -------------------------------------------------------------------------
	// Settings – origin warning
	// -------------------------------------------------------------------------

	/**
	 * maybe_show_origin_warning() must emit a notice when origins differ.
	 */
	public function test_origin_warning_shown_when_origins_differ(): void {
		WPTestState::$current_user_can               = true;
		WPTestState::$options['fundraisehub_api_url'] = 'https://app.fundraisehub.com';
		$_GET['page']                                 = 'fundraisehub-settings';

		$settings = new Settings();

		ob_start();
		$settings->maybe_show_origin_warning();
		$output = ob_get_clean();

		// The stub home_url() returns 'https://example.org', which differs
		// from 'https://app.fundraisehub.com', so a warning should appear.
		$this->assertStringContainsString( 'notice-warning', $output );
		$this->assertStringContainsString( 'Allowed Origins', $output );

		unset( $_GET['page'] );
	}

	/**
	 * maybe_show_origin_warning() must NOT emit a notice when the API URL
	 * is on the same origin as the WordPress site.
	 */
	public function test_origin_warning_not_shown_when_same_origin(): void {
		WPTestState::$current_user_can               = true;
		// home_url() stub returns 'https://example.org' – match it exactly.
		WPTestState::$options['fundraisehub_api_url'] = 'https://example.org';
		$_GET['page']                                 = 'fundraisehub-settings';

		$settings = new Settings();

		ob_start();
		$settings->maybe_show_origin_warning();
		$output = ob_get_clean();

		$this->assertSame( '', $output );

		unset( $_GET['page'] );
	}

	/**
	 * maybe_show_origin_warning() must NOT emit a notice when the API URL is
	 * managed by a constant that shares the same origin as the site.
	 */
	public function test_origin_warning_not_shown_when_constant_url_same_origin(): void {
		WPTestState::$current_user_can = true;
		$_GET['page']                  = 'fundraisehub-settings';

		// Constant URL matches the home_url() stub ('https://example.org').
		$settings = new class() extends Settings {
			protected function read_api_url_constant(): string {
				return 'https://example.org';
			}
		};

		ob_start();
		$settings->maybe_show_origin_warning();
		$output = ob_get_clean();

		$this->assertSame( '', $output );

		unset( $_GET['page'] );
	}

	/**
	 * maybe_show_origin_warning() must NOT emit a notice on non-settings pages.
	 */
	public function test_origin_warning_not_shown_on_other_pages(): void {
		WPTestState::$current_user_can               = true;
		WPTestState::$options['fundraisehub_api_url'] = 'https://app.fundraisehub.com';
		$_GET['page']                                 = 'some-other-page';

		$settings = new Settings();

		ob_start();
		$settings->maybe_show_origin_warning();
		$output = ob_get_clean();

		$this->assertSame( '', $output );

		unset( $_GET['page'] );
	}

	/**
	 * maybe_show_origin_warning() must NOT emit a notice when the user lacks
	 * manage_options capability.
	 */
	public function test_origin_warning_not_shown_without_manage_options(): void {
		WPTestState::$current_user_can               = false;
		WPTestState::$options['fundraisehub_api_url'] = 'https://app.fundraisehub.com';
		$_GET['page']                                 = 'fundraisehub-settings';

		$settings = new Settings();

		ob_start();
		$settings->maybe_show_origin_warning();
		$output = ob_get_clean();

		$this->assertSame( '', $output );

		unset( $_GET['page'] );
	}

	/**
	 * maybe_show_origin_warning() must NOT emit a notice when no API URL is configured.
	 */
	public function test_origin_warning_not_shown_when_api_url_not_configured(): void {
		WPTestState::$current_user_can = true;
		$_GET['page']                  = 'fundraisehub-settings';

		$settings = new Settings();

		ob_start();
		$settings->maybe_show_origin_warning();
		$output = ob_get_clean();

		$this->assertSame( '', $output );

		unset( $_GET['page'] );
	}

	/**
	 * When the API URL uses a non-standard port that differs from the site,
	 * origins are considered different and a warning must appear.
	 *
	 * home_url() stub returns 'https://example.org' (port 443 implied).
	 * API URL 'https://example.org:8443' is on the same host but a different
	 * port, so a warning should be emitted.
	 */
	public function test_origin_warning_shown_for_different_port(): void {
		WPTestState::$current_user_can               = true;
		WPTestState::$options['fundraisehub_api_url'] = 'https://example.org:8443';
		$_GET['page']                                 = 'fundraisehub-settings';

		$settings = new Settings();

		ob_start();
		$settings->maybe_show_origin_warning();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'notice-warning', $output );

		unset( $_GET['page'] );
	}

	/**
	 * Same scheme, host, and explicit standard port must NOT trigger a warning.
	 *
	 * https://example.org:443 and https://example.org are the same origin.
	 */
	public function test_origin_warning_not_shown_for_explicit_standard_port(): void {
		WPTestState::$current_user_can               = true;
		// home_url() stub returns 'https://example.org'; match with explicit 443.
		WPTestState::$options['fundraisehub_api_url'] = 'https://example.org:443';
		$_GET['page']                                 = 'fundraisehub-settings';

		$settings = new Settings();

		ob_start();
		$settings->maybe_show_origin_warning();
		$output = ob_get_clean();

		$this->assertSame( '', $output );

		unset( $_GET['page'] );
	}

	/**
	 * An unparseable API URL must not cause a false warning to appear.
	 */
	public function test_origin_warning_not_shown_when_api_url_is_unparseable(): void {
		WPTestState::$current_user_can               = true;
		WPTestState::$options['fundraisehub_api_url'] = '//not-a-full-url';
		$_GET['page']                                 = 'fundraisehub-settings';

		$settings = new Settings();

		ob_start();
		$settings->maybe_show_origin_warning();
		$output = ob_get_clean();

		$this->assertSame( '', $output );

		unset( $_GET['page'] );
	}
}
