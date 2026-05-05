<?php
/**
 * Campaign Sync – fetches and caches campaign data from the FundRaiseHub API.
 *
 * @package FundRaiseHub\Core
 */

declare( strict_types=1 );

namespace FundRaiseHub\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CampaignSync
 *
 * Retrieves campaign data from the remote FundRaiseHub API, caches the
 * results in WordPress transients, and exposes helper methods for
 * retrieving individual or multiple campaigns.
 */
class CampaignSync {

	/** Transient key prefix for individual campaign cache. */
	private const CAMPAIGN_TRANSIENT_PREFIX = 'fundraisehub_campaign_';

	/** Transient key for the full campaign list cache. */
	private const LIST_TRANSIENT = 'fundraisehub_campaign_list';

	/** Default cache lifetime in seconds (1 hour). */
	private const CACHE_TTL = HOUR_IN_SECONDS;

	/** @var ApiClient */
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
	 * Hook optional WP-Cron sync into WordPress.
	 */
	public function register(): void {
		add_action( 'fundraisehub_campaign_sync', [ $this, 'sync_all' ] );

		if ( ! wp_next_scheduled( 'fundraisehub_campaign_sync' ) ) {
			wp_schedule_event( time(), 'hourly', 'fundraisehub_campaign_sync' );
		}
	}

	/**
	 * Fetch a single campaign by its remote ID.
	 *
	 * Results are cached in a transient to minimise remote API calls.
	 *
	 * @param string $campaign_id Remote campaign identifier.
	 *
	 * @return mixed[]|\WP_Error Campaign data array or WP_Error.
	 */
	public function get_campaign( string $campaign_id ): array|\WP_Error {
		$transient_key = self::CAMPAIGN_TRANSIENT_PREFIX . sanitize_key( $campaign_id );
		$cached        = get_transient( $transient_key );

		if ( $cached !== false && is_array( $cached ) ) {
			return $cached;
		}

		$response = $this->api->get( '/campaigns/' . rawurlencode( $campaign_id ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		set_transient( $transient_key, $response, self::CACHE_TTL );

		return $response;
	}

	/**
	 * Fetch a paginated list of campaigns.
	 *
	 * @param mixed[] $args Optional arguments: per_page, page, category.
	 *
	 * @return mixed[]|\WP_Error Array of campaign data or WP_Error.
	 */
	public function get_campaigns( array $args = [] ): array|\WP_Error {
		$defaults = [
			'per_page' => 10,
			'page'     => 1,
			'category' => '',
		];

		$args = wp_parse_args( $args, $defaults );

		// Build a unique cache key based on the query parameters.
		$transient_key = self::LIST_TRANSIENT . '_' . md5( (string) wp_json_encode( $args ) );
		$cached        = get_transient( $transient_key );

		if ( $cached !== false && is_array( $cached ) ) {
			return $cached;
		}

		$params = array_filter( [
			'per_page' => (int) $args['per_page'],
			'page'     => (int) $args['page'],
			'category' => (string) $args['category'],
		] );

		$response = $this->api->get( '/campaigns', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// API may return { data: [...] } or a plain array.
		$campaigns = $response['data'] ?? $response;

		if ( ! is_array( $campaigns ) ) {
			$campaigns = [];
		}

		set_transient( $transient_key, $campaigns, self::CACHE_TTL );

		return $campaigns;
	}

	/**
	 * Sync all campaigns and refresh transient caches.
	 *
	 * Paginates through every page of the API and refreshes individual
	 * campaign transients. All existing list-query transients are cleared
	 * so stale paginated results are not served after a sync.
	 *
	 * Intended to be called by WP-Cron or manually by an admin action.
	 */
	public function sync_all(): void {
		global $wpdb;

		// Delete every hashed list transient (both value and timeout rows).
		$like_value   = $wpdb->esc_like( '_transient_' . self::LIST_TRANSIENT . '_' ) . '%';
		$like_timeout = $wpdb->esc_like( '_transient_timeout_' . self::LIST_TRANSIENT . '_' ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$like_value,
				$like_timeout
			)
		);

		// Paginate through all remote campaigns.
		$page        = 1;
		$total_pages = 1;

		do {
			$response = $this->api->get( '/campaigns', [ 'per_page' => 100, 'page' => $page ] );

			if ( is_wp_error( $response ) ) {
				break;
			}

			// API may return { data: [...], meta: { total_pages: N } } or a plain array.
			$campaigns = $response['data'] ?? $response;

			if ( isset( $response['meta']['total_pages'] ) ) {
				$total_pages = (int) $response['meta']['total_pages'];
			}

			if ( ! is_array( $campaigns ) || empty( $campaigns ) ) {
				break;
			}

			foreach ( $campaigns as $campaign ) {
				if ( empty( $campaign['id'] ) ) {
					continue;
				}

				$transient_key = self::CAMPAIGN_TRANSIENT_PREFIX . sanitize_key( (string) $campaign['id'] );
				set_transient( $transient_key, $campaign, self::CACHE_TTL );
			}

			$page++;
		} while ( $page <= $total_pages );
	}
}
