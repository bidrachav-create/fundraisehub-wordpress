<?php
/**
 * API Client – handles authenticated HTTP requests to the FundRaiseHub REST API.
 *
 * @package FundRaiseHub\Core
 */

declare( strict_types=1 );

namespace FundRaiseHub\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ApiClient
 *
 * Wraps WordPress HTTP API calls with authentication headers and basic
 * error handling for the FundRaiseHub platform. GET responses are cached
 * in WordPress transients for 60 seconds to reduce remote API load.
 */
class ApiClient {

	/** Transient key prefix for all API response caches. */
	private const TRANSIENT_PREFIX = 'fundraisehub_api_';

	/** Option key that stores the global cache version integer. */
	private const CACHE_VER_OPTION = 'fundraisehub_api_cache_ver';

	/** Cache time-to-live in seconds for GET responses. */
	private const CACHE_TTL = 60;

	/**
	 * Base URL of the FundRaiseHub API (no trailing slash).
	 *
	 * @var string
	 */
	private string $base_url;

	/**
	 * API key used for Bearer token authentication.
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * Constructor.
	 *
	 * Resolution order for both values:
	 *   1. Explicit constructor argument (highest priority).
	 *   2. PHP constant (FUNDRAISEHUB_API_URL / FUNDRAISEHUB_API_KEY).
	 *   3. WordPress option stored in the database.
	 *
	 * @param string $base_url Base URL of the remote API (no trailing slash).
	 * @param string $api_key  API key / token.
	 */
	public function __construct( string $base_url = '', string $api_key = '' ) {
		if ( '' !== $base_url ) {
			$resolved_url = $base_url;
		} else {
			$constant_url = $this->read_api_url_constant();
			if ( '' !== $constant_url ) {
				$resolved_url = $constant_url;
			} else {
				$resolved_url = (string) get_option( 'fundraisehub_api_url', '' );

				// Fall back to the legacy option key used before the rename so that
				// existing installs do not silently revert to the default URL.
				if ( '' === $resolved_url ) {
					$resolved_url = (string) get_option( 'fundraisehub_site_url', '' );
				}
			}
		}

		$this->base_url = rtrim( $resolved_url, '/' );

		if ( '' !== $api_key ) {
			$this->api_key = $api_key;
		} else {
			$constant_key  = $this->read_api_key_constant();
			$this->api_key = '' !== $constant_key
				? $constant_key
				: (string) get_option( 'fundraisehub_api_key', '' );
		}
	}

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
	 * Perform a GET request to the given API endpoint.
	 *
	 * Results are cached in a WordPress transient keyed by the endpoint path
	 * and an MD5 of the serialised query parameters. The cache TTL is 60 seconds.
	 *
	 * @param string  $endpoint Path relative to /api/wp/v1/ (e.g. 'campaigns').
	 * @param mixed[] $params   Optional query parameters.
	 *
	 * @return mixed[]|\WP_Error Decoded JSON body on success, WP_Error on failure.
	 */
	public function get( string $endpoint, array $params = array() ): array|\WP_Error {
		$config_error = $this->validate_configuration();
		if ( is_wp_error( $config_error ) ) {
			return $config_error;
		}

		// Build a safe, fixed-length transient key.
		// Hashing both endpoint and params avoids issues with '/' characters in endpoint
		// paths being altered/truncated by WordPress's transient key sanitisation, and
		// keeps the total key length well within the 172-character options-table limit.
		// The cache version prefix ensures bust_cache() works even when an external
		// object cache (Redis/Memcached) is in use.
		$version       = $this->get_cache_version();
		$transient_key = self::TRANSIENT_PREFIX . 'v' . $version . '_' . md5( $endpoint . ':' . (string) wp_json_encode( $params ) );
		$cached        = get_transient( $transient_key );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$url = $this->build_url( $endpoint, $params );

		$response = wp_remote_get(
			$url,
			array(
				'headers' => $this->default_headers(),
				'timeout' => 15,
			)
		);

		$result = $this->parse_response( $response );

		if ( ! is_wp_error( $result ) ) {
			set_transient( $transient_key, $result, self::CACHE_TTL );
		}

		return $result;
	}

	/**
	 * Perform a POST request to the given API endpoint.
	 *
	 * POST requests are not cached and intended for mutation operations such
	 * as donation proxy submissions.
	 *
	 * @param string  $endpoint Path relative to /api/wp/v1/ (e.g. 'donations').
	 * @param mixed[] $body     Request body as an associative array (JSON-encoded).
	 *
	 * @return mixed[]|\WP_Error Decoded JSON body on success, WP_Error on failure.
	 */
	public function post( string $endpoint, array $body = array() ): array|\WP_Error {
		$config_error = $this->validate_configuration();
		if ( is_wp_error( $config_error ) ) {
			return $config_error;
		}

		$url = $this->build_url( $endpoint );

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array_merge(
					$this->default_headers(),
					array( 'Content-Type' => 'application/json; charset=utf-8' )
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 15,
			)
		);

		return $this->parse_response( $response );
	}

	/**
	 * Invalidate cached GET responses.
	 *
	 * Increments the global cache version stored in wp_options so that all
	 * subsequent requests use a new transient key. This works regardless of
	 * whether an external object cache (Redis, Memcached, etc.) is active,
	 * because the version change causes every cached key to be effectively
	 * unreachable; previously stored entries expire naturally via their TTL.
	 *
	 * When no external object cache is in use the method also deletes stale
	 * transient rows from the database directly, providing immediate cleanup.
	 *
	 * @param string $endpoint_prefix Endpoint prefix to scope DB cleanup, or '' for all.
	 */
	public function bust_cache( string $endpoint_prefix ): void {
		// Bump the global version — new transient keys will differ from old ones,
		// so external object caches will not serve stale data.
		$new_version = $this->get_cache_version() + 1;
		update_option( self::CACHE_VER_OPTION, $new_version, false );

		// For sites using the default database transient store (no external cache),
		// also delete the stale rows immediately so they do not accumulate.
		if ( ! wp_using_ext_object_cache() ) {
			global $wpdb;

			$key_prefix   = self::TRANSIENT_PREFIX . $endpoint_prefix;
			$like_value   = $wpdb->esc_like( '_transient_' . $key_prefix ) . '%';
			$like_timeout = $wpdb->esc_like( '_transient_timeout_' . $key_prefix ) . '%';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
					$like_value,
					$like_timeout
				)
			);
		}
	}

	/**
	 * Test the API connection by calling the design-system endpoint.
	 *
	 * This method bypasses the transient cache so it always reflects the
	 * current credentials.
	 *
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public function test_connection(): bool|\WP_Error {
		$config_error = $this->validate_configuration();
		if ( is_wp_error( $config_error ) ) {
			return $config_error;
		}

		$url = $this->build_url( 'design-system' );

		$response = wp_remote_get(
			$url,
			array(
				'headers' => $this->default_headers(),
				'timeout' => 15,
			)
		);

		$result = $this->parse_response( $response );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Return the current global cache version from wp_options.
	 *
	 * The version is incremented by bust_cache() to invalidate all cached
	 * transients without needing to enumerate individual keys.
	 *
	 * @return int
	 */
	private function get_cache_version(): int {
		return (int) get_option( self::CACHE_VER_OPTION, 1 );
	}

	/**
	 * Validate API URL and authentication configuration before making requests.
	 *
	 * @return null|\WP_Error
	 */
	private function validate_configuration(): ?\WP_Error {
		if ( '' === $this->base_url ) {
			return new \WP_Error(
				'fundraisehub_api_missing_url',
				__( 'FundRaiseHub API URL is not configured.', 'fundraisehub-core' )
			);
		}

		$scheme = (string) wp_parse_url( $this->base_url, PHP_URL_SCHEME );
		$host   = (string) wp_parse_url( $this->base_url, PHP_URL_HOST );

		if ( '' === $scheme || '' === $host || ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return new \WP_Error(
				'fundraisehub_api_invalid_url',
				__( 'FundRaiseHub API URL must be a valid http or https URL.', 'fundraisehub-core' )
			);
		}

		if ( '' === $this->api_key ) {
			return new \WP_Error(
				'fundraisehub_api_missing_key',
				__( 'FundRaiseHub API key is not configured.', 'fundraisehub-core' )
			);
		}

		return null;
	}

	/**
	 * Build a full URL from an endpoint path and optional query parameters.
	 *
	 * All endpoints are scoped under the /api/wp/v1/ base path.
	 *
	 * @param string  $endpoint Endpoint path (e.g. 'campaigns' or 'campaigns/123').
	 * @param mixed[] $params   Optional query parameters.
	 *
	 * @return string Full URL.
	 */
	private function build_url( string $endpoint, array $params = array() ): string {
		$url = $this->base_url . '/api/wp/v1/' . ltrim( $endpoint, '/' );

		if ( ! empty( $params ) ) {
			$url = add_query_arg( $params, $url );
		}

		return $url;
	}

	/**
	 * Return the default HTTP headers, including the Authorization header.
	 *
	 * @return string[]
	 */
	private function default_headers(): array {
		$headers = array(
			'Accept' => 'application/json',
		);

		$site_origin = $this->get_site_origin_header_value();
		if ( '' !== $site_origin ) {
			$headers['Origin']                     = $site_origin;
			$headers['X-FundraiseHub-Site-Origin'] = $site_origin;
		}

		if ( '' !== $this->api_key ) {
			$headers['Authorization'] = 'Bearer ' . $this->api_key;
		}

		return $headers;
	}

	/**
	 * Build a deterministic site origin value for backend request headers.
	 *
	 * @return string
	 */
	private function get_site_origin_header_value(): string {
		$home_url = (string) home_url();
		if ( '' === $home_url ) {
			return '';
		}

		$scheme = (string) wp_parse_url( $home_url, PHP_URL_SCHEME );
		$host   = (string) wp_parse_url( $home_url, PHP_URL_HOST );
		$port   = wp_parse_url( $home_url, PHP_URL_PORT );

		if ( '' === $scheme || '' === $host ) {
			return '';
		}

		$origin = $scheme . '://' . $host;

		if ( is_int( $port ) && $port > 0 ) {
			$origin .= ':' . $port;
		}

		return $origin;
	}

	/**
	 * Parse a WordPress HTTP API response into an array or WP_Error.
	 *
	 * @param mixed[]|\WP_Error $response Raw response from wp_remote_*.
	 *
	 * @return mixed[]|\WP_Error
	 */
	private function parse_response( array|\WP_Error $response ): array|\WP_Error {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );
		$headers     = $this->extract_contract_headers( $response );

		if ( $status_code < 200 || $status_code >= 300 ) {
			$error_message = $this->extract_error_message( is_array( $data ) ? $data : array() );

			return new \WP_Error(
				'fundraisehub_api_error',
				'' !== $error_message
					? $error_message
					: sprintf(
						/* translators: %d: HTTP status code */
						__( 'FundRaiseHub API returned HTTP %d.', 'fundraisehub-core' ),
						$status_code
					),
				array(
					'status'  => $status_code,
					'headers' => $headers,
				)
			);
		}

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new \WP_Error(
				'fundraisehub_json_error',
				__( 'FundRaiseHub API returned invalid JSON.', 'fundraisehub-core' ),
				array(
					'status'  => $status_code,
					'headers' => $headers,
				)
			);
		}

		if ( ! is_array( $data ) ) {
			return array();
		}

		if ( array_key_exists( 'success', $data ) && empty( $data['success'] ) ) {
			$error_message = $this->extract_error_message( $data );

			return new \WP_Error(
				'fundraisehub_api_error',
				'' !== $error_message
					? $error_message
					: __( 'FundRaiseHub API returned an unknown error.', 'fundraisehub-core' ),
				array(
					'status'  => $status_code,
					'headers' => $headers,
				)
			);
		}

		if ( array_key_exists( 'success', $data ) && array_key_exists( 'data', $data ) && is_array( $data['data'] ) ) {
			return $data['data'];
		}

		return $data;
	}

	/**
	 * Extract a helpful error message from an API error envelope.
	 *
	 * @param mixed[] $data Decoded response body.
	 *
	 * @return string
	 */
	private function extract_error_message( array $data ): string {
		$error = $data['error'] ?? $data['message'] ?? '';

		if ( is_string( $error ) ) {
			return trim( $error );
		}

		if ( is_array( $error ) ) {
			$error_message = $error['message'] ?? '';
			if ( is_string( $error_message ) ) {
				return trim( $error_message );
			}
		}

		return '';
	}

	/**
	 * Read contract-version headers from the HTTP response for diagnostics.
	 *
	 * @param mixed[] $response Raw response from wp_remote_*.
	 *
	 * @return mixed[]
	 */
	private function extract_contract_headers( array $response ): array {
		$raw_headers = wp_remote_retrieve_headers( $response );
		$headers     = $this->normalize_headers( $raw_headers );

		$meta = array();

		if ( isset( $headers['x-frh-wp-contract-version'] ) ) {
			$meta['x-frh-wp-contract-version'] = (string) $headers['x-frh-wp-contract-version'];
		}

		if ( isset( $headers['x-frh-wp-embed-contract-version'] ) ) {
			$meta['x-frh-wp-embed-contract-version'] = (string) $headers['x-frh-wp-embed-contract-version'];
		}

		return $meta;
	}

	/**
	 * Normalize header collections returned by the WordPress HTTP API into arrays.
	 *
	 * @param mixed $raw_headers Header collection.
	 *
	 * @return string[]
	 */
	private function normalize_headers( mixed $raw_headers ): array {
		if ( is_array( $raw_headers ) ) {
			return array_change_key_case( $raw_headers, CASE_LOWER );
		}

		if ( $raw_headers instanceof \Traversable ) {
			return array_change_key_case( iterator_to_array( $raw_headers ), CASE_LOWER );
		}

		if ( is_object( $raw_headers ) ) {
			if ( method_exists( $raw_headers, 'getAll' ) ) {
				$all_headers = $raw_headers->getAll();
				if ( is_array( $all_headers ) ) {
					return array_change_key_case( $all_headers, CASE_LOWER );
				}
			}

			$object_vars = get_object_vars( $raw_headers );
			if ( ! empty( $object_vars ) ) {
				return array_change_key_case( $object_vars, CASE_LOWER );
			}
		}

		return array();
	}
}
