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
 * error handling for the FundRaiseHub platform.
 */
class ApiClient {

	/** @var string Base URL of the FundRaiseHub API. */
	private string $base_url;

	/** @var string API key used for Bearer token authentication. */
	private string $api_key;

	/**
	 * Constructor.
	 *
	 * @param string $base_url Base URL of the remote API (no trailing slash).
	 * @param string $api_key  API key / token.
	 */
	public function __construct( string $base_url = '', string $api_key = '' ) {
		$this->base_url = rtrim( $base_url ?: (string) get_option( 'fundraisehub_site_url', '' ), '/' );
		$this->api_key  = $api_key ?: (string) get_option( 'fundraisehub_api_key', '' );
	}

	/**
	 * Perform a GET request to the given API endpoint.
	 *
	 * @param string  $endpoint Path relative to the base URL (e.g. '/campaigns').
	 * @param mixed[] $params   Optional query parameters.
	 *
	 * @return mixed[]|\WP_Error Decoded JSON body on success, WP_Error on failure.
	 */
	public function get( string $endpoint, array $params = [] ): array|\WP_Error {
		$url = $this->build_url( $endpoint, $params );

		$response = wp_remote_get(
			$url,
			[
				'headers' => $this->default_headers(),
				'timeout' => 15,
			]
		);

		return $this->parse_response( $response );
	}

	/**
	 * Perform a POST request to the given API endpoint.
	 *
	 * @param string  $endpoint Path relative to the base URL.
	 * @param mixed[] $body     Request body as an associative array (JSON-encoded).
	 *
	 * @return mixed[]|\WP_Error Decoded JSON body on success, WP_Error on failure.
	 */
	public function post( string $endpoint, array $body = [] ): array|\WP_Error {
		$url = $this->build_url( $endpoint );

		$response = wp_remote_post(
			$url,
			[
				'headers' => array_merge(
					$this->default_headers(),
					[ 'Content-Type' => 'application/json; charset=utf-8' ]
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 15,
			]
		);

		return $this->parse_response( $response );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Build a full URL from an endpoint path and optional query parameters.
	 *
	 * @param string  $endpoint Endpoint path.
	 * @param mixed[] $params   Optional query parameters.
	 */
	private function build_url( string $endpoint, array $params = [] ): string {
		$url = $this->base_url . '/' . ltrim( $endpoint, '/' );

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
		$headers = [
			'Accept' => 'application/json',
		];

		if ( $this->api_key !== '' ) {
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
				[ 'status' => $status_code ]
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

		return is_array( $data ) ? $data : [];
	}
}
