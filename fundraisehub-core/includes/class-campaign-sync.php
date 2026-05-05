<?php
/**
 * Campaign Sync – fetches and caches campaign data from the FundRaiseHub API
 * and mirrors it into the fundraisehub_campaign custom post type.
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
 * results in WordPress transients, and mirrors them into the
 * fundraisehub_campaign CPT. Provides helper methods for retrieving
 * individual or multiple campaigns.
 */
class CampaignSync {

	/** Transient key prefix for individual campaign cache. */
	private const CAMPAIGN_TRANSIENT_PREFIX = 'fundraisehub_campaign_';

	/** Transient key for the full campaign list cache. */
	private const LIST_TRANSIENT = 'fundraisehub_campaign_list';

	/** Option key that stores the list-cache version integer. */
	private const LIST_CACHE_VER_OPTION = 'fundraisehub_list_cache_ver';

	/** Default cache lifetime in seconds (1 hour). */
	private const CACHE_TTL = HOUR_IN_SECONDS;

	/** WP-Cron event name for daily sync. */
	private const CRON_EVENT = 'fundraisehub_campaign_sync';

	/** Admin-post action that triggers a manual sync. */
	private const SYNC_ACTION = 'fundraisehub_sync';

	/** Post meta key that stores the remote campaign ID. */
	public const META_CAMPAIGN_ID = '_fundraisehub_campaign_id';

	/** Post meta key that stores the full campaign JSON payload. */
	public const META_CAMPAIGN_DATA = '_fundraisehub_campaign_data';

	/** Post meta key that stores the MD5 hash of the campaign JSON (idempotency). */
	private const META_CAMPAIGN_HASH = '_fundraisehub_campaign_data_hash';

	/** Post meta key that stores the organisation slug. */
	public const META_ORG_SLUG = '_fundraisehub_org_slug';

	/** Post meta key that stores the campaign slug. */
	public const META_CAMPAIGN_SLUG = '_fundraisehub_campaign_slug';

	/** Post meta key that stores the campaign type. */
	public const META_CAMPAIGN_TYPE = '_fundraisehub_campaign_type';

	/** Post meta key that stores the API URL the campaign was fetched from. */
	public const META_API_URL = '_fundraisehub_api_url';

	/**
	 * API client instance used for remote requests.
	 *
	 * @var ApiClient
	 */
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
	 * Hook optional WP-Cron sync and admin-post sync action into WordPress.
	 */
	public function register(): void {
		add_action( self::CRON_EVENT, array( $this, 'sync_all' ) );
		add_action( 'admin_post_' . self::SYNC_ACTION, array( $this, 'handle_admin_sync' ) );

		// Re-schedule if the event does not exist or is on the wrong interval.
		$current_schedule = wp_get_schedule( self::CRON_EVENT );
		if ( 'daily' !== $current_schedule ) {
			wp_clear_scheduled_hook( self::CRON_EVENT );
			wp_schedule_event( time(), 'daily', self::CRON_EVENT );
		}
	}

	/**
	 * Handle the manual "Sync Now" admin-post action.
	 *
	 * Verifies the nonce and user capability, runs sync_all(), then
	 * redirects back to the settings page.
	 */
	public function handle_admin_sync(): void {
		check_admin_referer( self::SYNC_ACTION );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'fundraisehub-core' ) );
		}

		$this->sync_all();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                => 'fundraisehub-settings',
					'fundraisehub_synced' => '1',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
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

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$response = $this->api->get( '/campaigns/' . rawurlencode( $campaign_id ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Normalise: single-campaign endpoint may return { data: {...} } or a plain array.
		$campaign = $response['data'] ?? $response;

		if ( ! is_array( $campaign ) ) {
			$campaign = array();
		}

		set_transient( $transient_key, $campaign, self::CACHE_TTL );

		return $campaign;
	}

	/**
	 * Fetch a paginated list of campaigns.
	 *
	 * @param mixed[] $args Optional arguments: per_page, page, category.
	 *
	 * @return mixed[]|\WP_Error Array of campaign data or WP_Error.
	 */
	public function get_campaigns( array $args = array() ): array|\WP_Error {
		$defaults = array(
			'per_page' => 10,
			'page'     => 1,
			'category' => '',
		);

		$args = wp_parse_args( $args, $defaults );

		// Build a unique cache key based on the query parameters and the current list-cache version.
		$version       = $this->get_list_cache_version();
		$transient_key = self::LIST_TRANSIENT . '_v' . $version . '_' . md5( (string) wp_json_encode( $args ) );
		$cached        = get_transient( $transient_key );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$params = array_filter(
			array(
				'per_page' => (int) $args['per_page'],
				'page'     => (int) $args['page'],
				'category' => (string) $args['category'],
			)
		);

		$response = $this->api->get( '/campaigns', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// API may return { data: [...] } or a plain array.
		$campaigns = $response['data'] ?? $response;

		if ( ! is_array( $campaigns ) ) {
			$campaigns = array();
		}

		set_transient( $transient_key, $campaigns, self::CACHE_TTL );

		return $campaigns;
	}

	/**
	 * Sync all campaigns from the remote API into the CPT.
	 *
	 * Paginates through every page of the API, upserting each campaign as a
	 * fundraisehub_campaign post. Idempotent: posts whose data has not changed
	 * (same MD5 hash) are skipped. All list transient caches are cleared after
	 * the sync so fresh data is served on the next request.
	 *
	 * Intended to be called by WP-Cron or the manual admin-post action.
	 */
	public function sync_all(): void {
		$page        = 1;
		$total_pages = 1;

		do {
			$response = $this->api->get(
				'/campaigns',
				array(
					'per_page' => 100,
					'page'     => $page,
				)
			);

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

				$this->upsert_campaign_post( $campaign );

				// Refresh the individual campaign transient.
				$transient_key = self::CAMPAIGN_TRANSIENT_PREFIX . sanitize_key( (string) $campaign['id'] );
				set_transient( $transient_key, $campaign, self::CACHE_TTL );
			}

			++$page;
		} while ( $page <= $total_pages );

		// Invalidate all list-query transients by bumping the version counter.
		// This works whether or not an external object cache is in use: the old
		// transient keys become unreachable because the version has changed, and
		// they expire naturally via their TTL.
		$new_version = $this->get_list_cache_version() + 1;
		update_option( self::LIST_CACHE_VER_OPTION, $new_version, false );
	}

	/**
	 * Sync a single campaign by its remote ID.
	 *
	 * Fetches the campaign from the API and upserts it as a
	 * fundraisehub_campaign post. Updates the individual transient cache.
	 *
	 * @param string $campaign_id Remote campaign identifier.
	 *
	 * @return int|\WP_Error Inserted/updated post ID, or WP_Error on failure.
	 */
	public function sync_one( string $campaign_id ): int|\WP_Error {
		$response = $this->api->get( '/campaigns/' . rawurlencode( $campaign_id ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$campaign = $response['data'] ?? $response;

		if ( ! is_array( $campaign ) || empty( $campaign['id'] ) ) {
			return new \WP_Error(
				'fundraisehub_invalid_campaign',
				__( 'FundRaiseHub API returned invalid campaign data.', 'fundraisehub-core' )
			);
		}

		$post_id = $this->upsert_campaign_post( $campaign );

		if ( ! is_wp_error( $post_id ) ) {
			// Refresh the individual campaign transient.
			$transient_key = self::CAMPAIGN_TRANSIENT_PREFIX . sanitize_key( (string) $campaign['id'] );
			set_transient( $transient_key, $campaign, self::CACHE_TTL );
		}

		return $post_id;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Create or update a fundraisehub_campaign post for the given campaign data.
	 *
	 * Idempotent: if the stored MD5 hash of the campaign JSON matches the
	 * incoming data, the post and its meta are left unchanged.
	 *
	 * @param mixed[] $campaign Campaign data array from the API.
	 *
	 * @return int|\WP_Error Post ID on success, WP_Error on failure.
	 */
	private function upsert_campaign_post( array $campaign ): int|\WP_Error {
		$remote_id = (string) $campaign['id'];
		$json      = (string) wp_json_encode( $campaign );
		$hash      = md5( $json );
		$api_url   = (string) get_option( 'fundraisehub_api_url', '' );
		if ( '' === $api_url ) {
			$api_url = (string) get_option( 'fundraisehub_site_url', '' );
		}

		// Look for an existing post matched by the remote campaign ID.
		$existing = get_posts(
			array(
				'post_type'      => CampaignCPT::POST_TYPE,
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'meta_query'     => array(  // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => self::META_CAMPAIGN_ID,
						'value' => $remote_id,
					),
				),
				'fields'         => 'ids',
			)
		);

		$post_id = ! empty( $existing ) ? (int) $existing[0] : 0;

		// Idempotency: skip the write if nothing has changed.
		if ( $post_id > 0 ) {
			$stored_hash = get_post_meta( $post_id, self::META_CAMPAIGN_HASH, true );
			if ( $stored_hash === $hash ) {
				return $post_id;
			}
		}

		$title = '';
		foreach ( array( 'name', 'title', 'campaign_name' ) as $key ) {
			if ( ! empty( $campaign[ $key ] ) && is_string( $campaign[ $key ] ) ) {
				$title = $campaign[ $key ];
				break;
			}
		}

		if ( '' === $title ) {
			/* translators: %s: remote campaign ID */
			$title = sprintf( __( 'Campaign %s', 'fundraisehub-core' ), $remote_id );
		}

		$post_data = array(
			'post_type'   => CampaignCPT::POST_TYPE,
			'post_title'  => sanitize_text_field( $title ),
			'post_status' => 'publish',
		);

		if ( $post_id > 0 ) {
			$post_data['ID'] = $post_id;
			$result          = wp_update_post( $post_data, true );
		} else {
			$result = wp_insert_post( $post_data, true );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$post_id = (int) $result;

		// Store campaign meta.
		update_post_meta( $post_id, self::META_CAMPAIGN_ID, $remote_id );
		update_post_meta( $post_id, self::META_CAMPAIGN_DATA, $json );
		update_post_meta( $post_id, self::META_CAMPAIGN_HASH, $hash );
		update_post_meta( $post_id, self::META_ORG_SLUG, (string) ( $campaign['org_slug'] ?? $campaign['organisation_slug'] ?? '' ) );
		update_post_meta( $post_id, self::META_CAMPAIGN_SLUG, (string) ( $campaign['slug'] ?? $campaign['campaign_slug'] ?? '' ) );
		update_post_meta( $post_id, self::META_CAMPAIGN_TYPE, (string) ( $campaign['type'] ?? $campaign['campaign_type'] ?? '' ) );
		update_post_meta( $post_id, self::META_API_URL, $api_url );

		return $post_id;
	}

	/**
	 * Return the current list-cache version from wp_options.
	 *
	 * Incrementing this value (done by sync_all()) effectively invalidates all
	 * existing list transients without requiring individual key enumeration, and
	 * works correctly whether or not an external object cache is in use.
	 *
	 * @return int
	 */
	private function get_list_cache_version(): int {
		return (int) get_option( self::LIST_CACHE_VER_OPTION, 1 );
	}
}
