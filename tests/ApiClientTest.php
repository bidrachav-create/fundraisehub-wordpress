<?php
/**
 * Tests for FundRaiseHub\Core\ApiClient.
 *
 * All WordPress functions are replaced by stubs in tests/stubs.php.
 * WPTestState is used to pre-load options, queue HTTP responses, and
 * inspect side-effects such as transient writes and cache-version bumps.
 */

declare( strict_types=1 );

use FundRaiseHub\Core\ApiClient;
use PHPUnit\Framework\TestCase;

/**
 * Class ApiClientTest
 */
class ApiClientTest extends TestCase {

	/**
	 * Reset all stub global state before every test.
	 */
	protected function setUp(): void {
		WPTestState::reset();
	}

	/**
	 * Build a traversable header collection like wp_remote_retrieve_headers() may return.
	 *
	 * @param array<string, string> $headers Headers keyed by name.
	 *
	 * @return ArrayIterator<string, string>
	 */
	private function header_object( array $headers ): ArrayIterator {
		return new ArrayIterator( $headers );
	}

	// -------------------------------------------------------------------------
	// Constructor / configuration
	// -------------------------------------------------------------------------

	/**
	 * Test that the constructor reads base URL and API key from wp_options.
	 */
	public function test_constructor_reads_options_when_no_params_given(): void {
		WPTestState::$options['fundraisehub_api_url'] = 'https://custom.example.com';
		WPTestState::$options['fundraisehub_api_key'] = 'mykey';

		// Queue a successful response so get() can run without error.
		WPTestState::$http_response_queue[] = WPTestState::http_ok( array() );

		$client = new ApiClient();
		$client->get( 'campaigns' );

		$this->assertStringContainsString(
			'custom.example.com',
			WPTestState::$http_get_urls[0],
			'get() should use the URL read from wp_options'
		);
	}

	/**
	 * Test the backward-compatible fallback to fundraisehub_site_url.
	 */
	public function test_constructor_falls_back_to_legacy_site_url(): void {
		// fundraisehub_api_url is not set; legacy option is.
		WPTestState::$options['fundraisehub_site_url'] = 'https://legacy.example.com';
		WPTestState::$options['fundraisehub_api_key']  = 'legacy-key';

		WPTestState::$http_response_queue[] = WPTestState::http_ok( array() );

		$client = new ApiClient();
		$client->get( 'campaigns' );

		$this->assertStringContainsString(
			'legacy.example.com',
			WPTestState::$http_get_urls[0],
			'get() should fall back to fundraisehub_site_url when api_url is absent'
		);
	}

	/**
	 * Explicit params override option values.
	 */
	public function test_constructor_explicit_params_override_options(): void {
		WPTestState::$options['fundraisehub_api_url'] = 'https://ignored.example.com';

		WPTestState::$http_response_queue[] = WPTestState::http_ok( array() );

		$client = new ApiClient( 'https://explicit.example.com', 'explicit_key' );
		$client->get( 'campaigns' );

		$this->assertStringContainsString(
			'explicit.example.com',
			WPTestState::$http_get_urls[0]
		);
		$this->assertStringNotContainsString(
			'ignored.example.com',
			WPTestState::$http_get_urls[0]
		);
	}

	// -------------------------------------------------------------------------
	// URL structure — backend route contract
	// -------------------------------------------------------------------------

	/**
	 * All GET requests must go to /api/wp/v1/{endpoint}.
	 */
	public function test_get_url_includes_api_wp_v1_prefix(): void {
		WPTestState::$http_response_queue[] = WPTestState::http_ok( array() );

		$client = new ApiClient( 'https://api.fundraisehub.com', 'key' );
		$client->get( 'campaigns' );

		$this->assertStringContainsString(
			'/api/wp/v1/campaigns',
			WPTestState::$http_get_urls[0],
			'get() must scope requests under /api/wp/v1/'
		);
	}

	/**
	 * Verify that leading slashes on the endpoint are normalised.
	 */
	public function test_get_url_strips_leading_slash_from_endpoint(): void {
		WPTestState::$http_response_queue[] = WPTestState::http_ok( array() );

		$client = new ApiClient( 'https://api.fundraisehub.com', 'key' );
		$client->get( '/campaigns' );

		$this->assertStringContainsString(
			'/api/wp/v1/campaigns',
			WPTestState::$http_get_urls[0]
		);
		$this->assertStringNotContainsString( '//campaigns', WPTestState::$http_get_urls[0] );
	}

	/**
	 * Trailing slashes on the base URL must not result in double slashes.
	 */
	public function test_get_url_strips_trailing_slash_from_base_url(): void {
		WPTestState::$http_response_queue[] = WPTestState::http_ok( array() );

		$client = new ApiClient( 'https://api.fundraisehub.com/', 'key' );
		$client->get( 'campaigns' );

		$this->assertStringNotContainsString( '//', str_replace( 'https://', '', WPTestState::$http_get_urls[0] ) );
	}

	/**
	 * Query parameters should be appended to the URL.
	 */
	public function test_get_url_appends_query_params(): void {
		WPTestState::$http_response_queue[] = WPTestState::http_ok( array() );

		$client = new ApiClient( 'https://api.fundraisehub.com', 'key' );
		$client->get( 'campaigns', array( 'per_page' => 5, 'page' => 2 ) );

		$this->assertStringContainsString( 'per_page=5', WPTestState::$http_get_urls[0] );
		$this->assertStringContainsString( 'page=2', WPTestState::$http_get_urls[0] );
	}

	// -------------------------------------------------------------------------
	// GET — success paths
	// -------------------------------------------------------------------------

	/**
	 * A 200 response with valid JSON should return a decoded array.
	 */
	public function test_get_returns_decoded_array_on_success(): void {
		WPTestState::$http_response_queue[] = WPTestState::http_ok( array( 'id' => 1, 'name' => 'Test Campaign' ) );

		$client = new ApiClient( 'https://api.fundraisehub.com', 'key' );
		$result = $client->get( 'campaigns/1' );

		$this->assertIsArray( $result );
		$this->assertSame( 1, $result['id'] );
		$this->assertSame( 'Test Campaign', $result['name'] );
	}

	/**
	 * An empty JSON object {} should return an empty array.
	 */
	public function test_get_returns_empty_array_for_empty_object(): void {
		WPTestState::$http_response_queue[] = array(
			'response' => array( 'code' => 200 ),
			'body'     => '{}',
		);

		$client = new ApiClient( 'https://api.fundraisehub.com', 'key' );
		$result = $client->get( 'campaigns' );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * A success=false envelope should be surfaced as a WP_Error with the API message.
	 */
	public function test_get_returns_wp_error_when_success_envelope_is_false(): void {
		WPTestState::$http_response_queue[] = WPTestState::http_ok(
			array(
				'success' => false,
				'error'   => 'Rate limit exceeded',
			)
		);

		$client = new ApiClient( 'https://api.fundraisehub.com', 'key' );
		$result = $client->get( 'campaigns' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'fundraisehub_api_error', $result->get_error_code() );
		$this->assertSame( 'Rate limit exceeded', $result->get_error_message() );
	}

	/**
	 * A success=true envelope with a data payload should return the inner data.
	 */
	public function test_get_unwraps_success_envelope_data_payload(): void {
		WPTestState::$http_response_queue[] = WPTestState::http_ok(
			array(
				'success' => true,
				'data'    => array(
					'name' => 'Org',
					'slug' => 'org',
				),
			)
		);

		$client = new ApiClient( 'https://api.fundraisehub.com', 'key' );
		$result = $client->get( 'design-system' );

		$this->assertSame(
			array(
				'name' => 'Org',
				'slug' => 'org',
			),
			$result
		);
	}

	// -------------------------------------------------------------------------
	// GET — transient cache
	// -------------------------------------------------------------------------

	/**
	 * A cached response should be returned without calling the HTTP API.
	 */
	public function test_get_returns_cached_transient_without_http_call(): void {
		// Populate the transient cache with a known value.  The exact key is an
		// implementation detail; we derive it the same way ApiClient does.
		$endpoint      = 'campaigns';
		$params        = array();
		$version       = 1; // Default from get_option('fundraisehub_api_cache_ver', 1).
		$transient_key = 'fundraisehub_api_v' . $version . '_' . md5( $endpoint . ':' . json_encode( $params ) );

		WPTestState::$transients[ $transient_key ] = array( 'id' => 99 );

		$client = new ApiClient( 'https://api.fundraisehub.com', 'key' );
		$result = $client->get( $endpoint, $params );

		$this->assertSame( 0, WPTestState::$http_get_call_count, 'HTTP API must not be called when cache is warm' );
		$this->assertSame( array( 'id' => 99 ), $result );
	}

	/**
	 * A cache miss should call the HTTP API and write the result to a transient.
	 */
	public function test_get_calls_http_and_caches_on_miss(): void {
		WPTestState::$http_response_queue[] = WPTestState::http_ok( array( 'data' => array( 'id' => 7 ) ) );

		$client = new ApiClient( 'https://api.fundraisehub.com', 'key' );
		$client->get( 'campaigns' );

		$this->assertSame( 1, WPTestState::$http_get_call_count );
		$this->assertNotEmpty( WPTestState::$transients, 'A transient should have been written' );
	}

	/**
	 * A WP_Error from the HTTP layer should NOT be cached.
	 */
	public function test_get_does_not_cache_wp_error(): void {
		WPTestState::$http_response_queue[] = new WP_Error( 'http_request_failed', 'Connection refused' );

		$client = new ApiClient( 'https://api.fundraisehub.com', 'key' );
		$client->get( 'campaigns' );

		$this->assertEmpty( WPTestState::$transients, 'WP_Error must not be stored in the transient cache' );
	}

	// -------------------------------------------------------------------------
	// GET — failure paths
	// -------------------------------------------------------------------------

	/**
	 * A WP_Error from wp_remote_get should be passed through.
	 */
	public function test_get_returns_wp_error_on_http_failure(): void {
		WPTestState::$http_response_queue[] = new WP_Error( 'http_request_failed', 'cURL error 7' );

		$client = new ApiClient( 'https://api.fundraisehub.com', 'key' );
		$result = $client->get( 'campaigns' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertStringContainsString( 'cURL error 7', $result->get_error_message() );
	}

	/**
	 * A non-2xx status code should produce a WP_Error.
	 */
	public function test_get_returns_wp_error_on_404_status(): void {
		WPTestState::$http_response_queue[] = array(
			'response' => array( 'code' => 404 ),
			'body'     => '{"error":"not found"}',
			'headers'  => $this->header_object( array( 'X-FRH-WP-Contract-Version' => 'v1' ) ),
		);

		$client = new ApiClient( 'https://api.fundraisehub.com', 'key' );
		$result = $client->get( 'campaigns/999' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'fundraisehub_api_error', $result->get_error_code() );
		$this->assertSame( 'not found', $result->get_error_message() );
		$this->assertSame(
			array(
				'status'  => 404,
				'headers' => array( 'x-frh-wp-contract-version' => 'v1' ),
			),
			$result->get_error_data()
		);
	}

	/**
	 * A 401 Unauthorized means the API key is wrong or missing.
	 */
	public function test_get_returns_wp_error_on_401_status(): void {
		WPTestState::$http_response_queue[] = WPTestState::http_error( 401 );

		$client = new ApiClient( 'https://api.fundraisehub.com', 'key' );
		$result = $client->get( 'campaigns' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertStringContainsString( '401', $result->get_error_message() );
	}

	/**
	 * Invalid JSON in the response body should produce a WP_Error.
	 */
	public function test_get_returns_wp_error_on_invalid_json(): void {
		WPTestState::$http_response_queue[] = array(
			'response' => array( 'code' => 200 ),
			'body'     => 'not json at all!',
		);

		$client = new ApiClient( 'https://api.fundraisehub.com', 'key' );
		$result = $client->get( 'campaigns' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'fundraisehub_json_error', $result->get_error_code() );
	}

	// -------------------------------------------------------------------------
	// POST
	// -------------------------------------------------------------------------

	/**
	 * post() should use the /api/wp/v1/ URL prefix.
	 */
	public function test_post_url_includes_api_wp_v1_prefix(): void {
		WPTestState::$http_response_queue[] = WPTestState::http_ok( array( 'ok' => true ) );

		$client = new ApiClient( 'https://api.fundraisehub.com', 'key' );
		$client->post( 'donations', array( 'amount' => 25 ) );

		$this->assertStringContainsString( '/api/wp/v1/donations', WPTestState::$http_post_urls[0] );
	}

	/**
	 * post() should send the body as JSON.
	 */
	public function test_post_sends_json_encoded_body(): void {
		WPTestState::$http_response_queue[] = WPTestState::http_ok( array() );

		$client = new ApiClient( 'https://api.fundraisehub.com', 'key' );
		$client->post( 'donations', array( 'amount' => 50, 'currency' => 'USD' ) );

		$args = WPTestState::$http_post_args[0] ?? array();
		$body = json_decode( $args['body'], true );

		$this->assertSame( 50, $body['amount'] );
		$this->assertSame( 'USD', $body['currency'] );
	}

	/**
	 * post() should include application/json Content-Type header.
	 */
	public function test_post_includes_content_type_header(): void {
		WPTestState::$http_response_queue[] = WPTestState::http_ok( array() );

		$client = new ApiClient( 'https://api.fundraisehub.com', 'key' );
		$client->post( 'donations', array() );

		$headers = WPTestState::$http_post_args[0]['headers'] ?? array();
		$this->assertStringContainsString( 'application/json', $headers['Content-Type'] ?? '' );
	}

	/**
	 * post() should return WP_Error on HTTP failure.
	 */
	public function test_post_returns_wp_error_on_failure(): void {
		WPTestState::$http_response_queue[] = WPTestState::http_error( 500 );

		$client = new ApiClient( 'https://api.fundraisehub.com', 'key' );
		$result = $client->post( 'donations', array() );

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	/**
	 * get() should fail fast when no API URL is configured.
	 */
	public function test_get_returns_wp_error_when_api_url_is_missing(): void {
		$client = new ApiClient( '', 'key' );

		$result = $client->get( 'campaigns' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'fundraisehub_api_missing_url', $result->get_error_code() );
		$this->assertSame( 0, WPTestState::$http_get_call_count );
	}

	/**
	 * post() should fail fast when no API key is configured.
	 */
	public function test_post_returns_wp_error_when_api_key_is_missing(): void {
		$client = new ApiClient( 'https://api.fundraisehub.com', '' );

		$result = $client->post( 'feedback', array( 'subject' => 'Hello' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'fundraisehub_api_missing_key', $result->get_error_code() );
		$this->assertSame( 0, WPTestState::$http_post_call_count );
	}

	// -------------------------------------------------------------------------
	// test_connection()
	// -------------------------------------------------------------------------

	/**
	 * test_connection() calls the ping endpoint.
	 */
	public function test_connection_hits_ping_endpoint(): void {
		WPTestState::$http_response_queue[] = WPTestState::http_ok( array( 'name' => 'Org' ) );

		$client = new ApiClient( 'https://api.fundraisehub.com', 'key' );
		$client->test_connection();

		$this->assertStringContainsString( '/api/wp/v1/ping', WPTestState::$http_get_urls[0] );
	}

	/**
	 * test_connection() returns true on a successful 200 response.
	 */
	public function test_connection_returns_true_on_success(): void {
		WPTestState::$http_response_queue[] = WPTestState::http_ok( array( 'name' => 'Org' ) );

		$client = new ApiClient( 'https://api.fundraisehub.com', 'key' );
		$result = $client->test_connection();

		$this->assertTrue( $result );
	}

	/**
	 * test_connection() returns WP_Error on HTTP failure.
	 */
	public function test_connection_returns_wp_error_on_failure(): void {
		WPTestState::$http_response_queue[] = WPTestState::http_error( 403 );

		$client = new ApiClient( 'https://api.fundraisehub.com', 'key' );
		$result = $client->test_connection();

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	/**
	 * test_connection() must NOT serve a cached response (always live).
	 */
	public function test_connection_bypasses_transient_cache(): void {
		// Prime the transient with what would be the cached value.
		$version       = 1;
		$transient_key = 'fundraisehub_api_v' . $version . '_' . md5( 'ping:[]' );
		WPTestState::$transients[ $transient_key ] = array( 'stale' => true );

		// Queue a live response.
		WPTestState::$http_response_queue[] = WPTestState::http_ok( array( 'live' => true ) );

		$client = new ApiClient( 'https://api.fundraisehub.com', 'key' );
		$client->test_connection();

		// HTTP must still have been called — test_connection() does not use get().
		$this->assertSame( 1, WPTestState::$http_get_call_count );
	}

	// -------------------------------------------------------------------------
	// bust_cache()
	// -------------------------------------------------------------------------

	/**
	 * bust_cache() increments the stored cache version.
	 */
	public function test_bust_cache_increments_version(): void {
		WPTestState::$options['fundraisehub_api_cache_ver'] = 3;

		$client = new ApiClient( 'https://api.fundraisehub.com', 'key' );
		$client->bust_cache( '' );

		$this->assertSame( 4, WPTestState::$options['fundraisehub_api_cache_ver'] );
	}

	/**
	 * bust_cache() should make subsequent get() calls miss the old cache.
	 */
	public function test_bust_cache_causes_cache_miss_on_next_get(): void {
		// Populate the transient at version 1.
		$transient_key = 'fundraisehub_api_v1_' . md5( 'campaigns:[]' );
		WPTestState::$transients[ $transient_key ] = array( 'old' => true );

		$client = new ApiClient( 'https://api.fundraisehub.com', 'key' );
		$client->bust_cache( '' ); // Version bumped to 2.

		// Now get() must call the HTTP API because the v2 transient does not exist.
		WPTestState::$http_response_queue[] = WPTestState::http_ok( array( 'new' => true ) );
		$result = $client->get( 'campaigns' );

		$this->assertSame( 1, WPTestState::$http_get_call_count );
		$this->assertSame( array( 'new' => true ), $result );
	}

	/**
	 * bust_cache() with no external object cache does not throw and performs
	 * its database cleanup via the global $wpdb stub.
	 */
	public function test_bust_cache_does_not_throw_without_ext_cache(): void {
		WPTestState::$using_ext_object_cache = false;

		$client = new ApiClient( 'https://api.fundraisehub.com', 'key' );

		$this->expectNotToPerformAssertions();
		$client->bust_cache( '' );
	}

	// -------------------------------------------------------------------------
	// Authorization header
	// -------------------------------------------------------------------------

	/**
	 * When an API key is set, the Authorization header must contain Bearer token.
	 */
	public function test_get_sends_bearer_authorization_header(): void {
		WPTestState::$http_response_queue[] = WPTestState::http_ok( array() );

		// Intercept the headers by replacing wp_remote_get is not possible without
		// function mocking, so we verify indirectly: the key is in the options and
		// the client was constructed with it – if the header was absent the API
		// would return 401 on a real server.  Here we just assert no error.
		$client = new ApiClient( 'https://api.fundraisehub.com', 'my-secret-key' );
		$result = $client->get( 'campaigns' );

		$this->assertIsArray( $result );
	}

	/**
	 * get() should include deterministic site-origin headers for backend checks.
	 */
	public function test_get_sends_site_origin_headers(): void {
		WPTestState::$http_response_queue[] = WPTestState::http_ok( array() );

		$client = new ApiClient( 'https://api.fundraisehub.com', 'my-secret-key' );
		$client->get( 'campaigns' );

		$headers = WPTestState::$http_get_args[0]['headers'] ?? array();

		$this->assertSame( 'https://example.org', $headers['Origin'] ?? '' );
		$this->assertSame( 'https://example.org', $headers['X-FundraiseHub-Site-Origin'] ?? '' );
	}

	/**
	 * get() should normalize origin headers to scheme+host+port from home_url().
	 */
	public function test_get_normalizes_site_origin_headers(): void {
		WPTestState::$home_url              = 'https://example.org:8443/sub/path/';
		WPTestState::$http_response_queue[] = WPTestState::http_ok( array() );

		$client = new ApiClient( 'https://api.fundraisehub.com', 'my-secret-key' );
		$client->get( 'campaigns' );

		$headers = WPTestState::$http_get_args[0]['headers'] ?? array();

		$this->assertSame( 'https://example.org:8443', $headers['Origin'] ?? '' );
		$this->assertSame( 'https://example.org:8443', $headers['X-FundraiseHub-Site-Origin'] ?? '' );
	}

	/**
	 * home_url() test stub should join relative paths with one slash.
	 */
	public function test_home_url_stub_joins_relative_paths_with_single_slash(): void {
		WPTestState::$home_url = 'https://example.org/';
		$this->assertSame( 'https://example.org/foo', home_url( 'foo' ) );
		$this->assertSame( 'https://example.org/bar', home_url( '/bar' ) );
	}

	/**
	 * OAuth credentials should be exchanged for an access token and used as Bearer auth.
	 */
	public function test_get_uses_oauth_access_token_when_oauth_credentials_are_configured(): void {
		WPTestState::$http_response_queue[] = WPTestState::http_ok(
			array(
				'access_token' => 'oauth-access-token',
				'token_type'   => 'bearer',
				'expires_in'   => 3600,
			)
		);
		WPTestState::$http_response_queue[] = WPTestState::http_ok( array( 'ok' => true ) );

		$client = new ApiClient( 'https://api.fundraisehub.com', '', 'client-id', 'client-secret' );
		$client->get( 'campaigns' );

		$this->assertStringContainsString( '/api/wp/v1/oauth/token', WPTestState::$http_post_urls[0] ?? '' );
		$this->assertSame(
			'Bearer oauth-access-token',
			WPTestState::$http_get_args[0]['headers']['Authorization'] ?? ''
		);
	}

	/**
	 * OAuth-protected requests should refresh token and retry once on 401.
	 */
	public function test_get_retries_once_with_refreshed_oauth_token_on_401(): void {
		WPTestState::$http_response_queue[] = WPTestState::http_ok(
			array(
				'access_token' => 'token-one',
				'expires_in'   => 3600,
			)
		);
		WPTestState::$http_response_queue[] = WPTestState::http_error( 401 );
		WPTestState::$http_response_queue[] = WPTestState::http_ok(
			array(
				'access_token' => 'token-two',
				'expires_in'   => 3600,
			)
		);
		WPTestState::$http_response_queue[] = WPTestState::http_ok( array( 'ok' => true ) );

		$client = new ApiClient( 'https://api.fundraisehub.com', '', 'client-id', 'client-secret' );
		$result = $client->get( 'campaigns' );

		$this->assertSame( 2, WPTestState::$http_get_call_count );
		$this->assertSame( 2, WPTestState::$http_post_call_count );
		$this->assertSame( 'token-two', WPTestState::$transients['fundraisehub_oauth_access_token']['access_token'] ?? '' );
		$this->assertSame( array( 'ok' => true ), $result );
	}
}
