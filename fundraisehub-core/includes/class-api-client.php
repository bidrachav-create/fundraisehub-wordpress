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
	 * Reads connection settings from wp_options when explicit values are not
	 * provided. The URL defaults to https://app.fundraisehub.com when the
	 * option has not been saved yet.
	 *
	 * @param string $base_url Base URL of the remote API (no trailing slash).
	 * @param string $api_key  API key / token.
	 */
	public function __construct( string $base_url = '', string $api_key = '' ) {
		$this->base_url = rtrim(
			$base_url ? $base_url : (string) get_option( 'fundraisehub_api_url', 'https://app.fundraisehub.com' ),
			'/'
		);
		$this->api_key  = $api_key ? $api_key : (string) get_option( 'fundraisehub_api_key', '' );
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
		$transient_key = self::TRANSIENT_PREFIX . ltrim( $endpoint, '/' ) . '_' . md5( (string) wp_json_encode( $params ) );
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
	 * Delete all transients whose option-table key starts with the given prefix.
	 *
	 * Pass an empty string to clear every transient belonging to this client.
	 * Pass an endpoint name (e.g. 'campaigns') to clear only that endpoint's
	 * cached responses. Call after saving settings or forcing a sync.
	 *
	 * @param string $endpoint_prefix Endpoint prefix to match, or '' for all.
	 */
	public function bust_cache( string $endpoint_prefix ): void {
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

	/**
	 * Test the API connection by calling the design-system endpoint.
	 *
	 * This method bypasses the transient cache so it always reflects the
	 * current credentials.
	 *
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public function test_connection(): bool|\WP_Error {
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

		if ( '' !== $this->api_key ) {
			$headers['Authorization'] = 'Bearer ' . $this->api_key;
		}

		return $headers;
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

		if ( $status_code < 200 || $status_code >= 300 ) {
			return new \WP_Error(
				'fundraisehub_api_error',
				/* translators: %d: HTTP status code */
				sprintf( __( 'FundRaiseHub API returned HTTP %d.', 'fundraisehub-core' ), $status_code ),
				array( 'status' => $status_code )
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new \WP_Error(
				'fundraisehub_json_error',
				__( 'FundRaiseHub API returned invalid JSON.', 'fundraisehub-core' )
			);
		}

		return is_array( $data ) ? $data : array();
	}
}
