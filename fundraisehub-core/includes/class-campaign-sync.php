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

		$campaign = $this->normalize_campaign_detail_response( $response );

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

		$campaigns = $this->normalize_campaign_list_response( $response );

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

			$campaigns = $this->normalize_campaign_list_response( $response );

			if ( isset( $response['meta']['total_pages'] ) ) {
				$total_pages = (int) $response['meta']['total_pages'];
			} elseif ( isset( $response['pagination']['totalPages'] ) ) {
				$total_pages = (int) $response['pagination']['totalPages'];
			} elseif ( isset( $response['pagination']['total_pages'] ) ) {
				$total_pages = (int) $response['pagination']['total_pages'];
			}

			if ( ! is_array( $campaigns ) || empty( $campaigns ) ) {
				break;
			}

			foreach ( $campaigns as $campaign ) {
				$campaign_id = (string) ( $campaign['id'] ?? '' );
				if ( '' === $campaign_id ) {
					continue;
				}

				$campaign_detail = $this->get_campaign( $campaign_id );
				if ( is_wp_error( $campaign_detail ) || empty( $campaign_detail['id'] ) ) {
					$campaign_detail = $campaign;
				}

				$post_id = $this->upsert_campaign_post( $campaign_detail );

				if ( ! is_wp_error( $post_id ) ) {
					// Refresh the individual campaign transient even when detail falls
					// back to list data, so fetch callers do not repeatedly hit a
					// failing detail endpoint.
					$transient_key = self::CAMPAIGN_TRANSIENT_PREFIX . sanitize_key( $campaign_id );
					set_transient( $transient_key, $campaign_detail, self::CACHE_TTL );
				}
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

		$campaign = $this->normalize_campaign_detail_response( $response );

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
		update_post_meta( $post_id, self::META_ORG_SLUG, (string) ( $campaign['org_slug'] ?? $campaign['orgSlug'] ?? $campaign['organization_slug'] ?? $campaign['organisation_slug'] ?? '' ) );
		update_post_meta( $post_id, self::META_CAMPAIGN_SLUG, (string) ( $campaign['slug'] ?? $campaign['campaign_slug'] ?? $campaign['campaignSlug'] ?? '' ) );
		update_post_meta( $post_id, self::META_CAMPAIGN_TYPE, (string) ( $campaign['type'] ?? $campaign['campaign_type'] ?? $campaign['campaignType'] ?? '' ) );
		update_post_meta( $post_id, self::META_API_URL, $api_url );

		return $post_id;
	}

	/**
	 * Normalize a single-campaign API response into a render-ready payload.
	 *
	 * @param mixed[] $response API response array.
	 *
	 * @return mixed[]
	 */
	private function normalize_campaign_detail_response( array $response ): array {
		$data = $response['data'] ?? $response;

		if ( ! is_array( $data ) ) {
			return array();
		}

		return self::normalize_campaign_payload( $data );
	}

	/**
	 * Normalize a campaigns-list response into a list of campaign payloads.
	 *
	 * @param mixed[] $response API response array.
	 *
	 * @return mixed[]
	 */
	private function normalize_campaign_list_response( array $response ): array {
		$data = $response['data'] ?? $response;

		if ( ! is_array( $data ) ) {
			return array();
		}

		$is_list = isset( $data[0] ) && is_array( $data[0] );
		if ( ! $is_list ) {
			if ( isset( $data['campaign'] ) && is_array( $data['campaign'] ) ) {
				return array( self::normalize_campaign_payload( $data ) );
			}
			return array();
		}

		$campaigns = array();
		foreach ( $data as $campaign ) {
			if ( ! is_array( $campaign ) ) {
				continue;
			}
			$campaigns[] = self::normalize_campaign_payload( $campaign );
		}

		return $campaigns;
	}

	/**
	 * Normalize campaign fields from backend payload variants.
	 *
	 * @param mixed[] $campaign Campaign payload.
	 *
	 * @return mixed[]
	 */
	public static function normalize_campaign_payload( array $campaign ): array {
		if ( isset( $campaign['campaign'] ) && is_array( $campaign['campaign'] ) ) {
			$base_campaign = $campaign['campaign'];
			foreach ( array( 'layout', 'teams', 'ambassadors', 'comments', 'media', 'recentDonations', 'recent_donations' ) as $related_key ) {
				if ( isset( $campaign[ $related_key ] ) && is_array( $campaign[ $related_key ] ) ) {
					$base_campaign[ $related_key ] = $campaign[ $related_key ];
				}
			}
			$campaign = $base_campaign;
		}

		$campaign_id = $campaign['id'] ?? $campaign['campaignId'] ?? $campaign['campaign_id'] ?? '';
		if ( '' !== (string) $campaign_id ) {
			$campaign['id'] = (string) $campaign_id;
		}

		$name = $campaign['name'] ?? $campaign['title'] ?? $campaign['campaignName'] ?? $campaign['campaign_name'] ?? '';
		if ( '' !== (string) $name ) {
			$campaign['name']  = (string) $name;
			$campaign['title'] = (string) $name;
		}

		$description = $campaign['description'] ?? $campaign['body'] ?? $campaign['content'] ?? '';
		if ( '' !== (string) $description ) {
			$campaign['description'] = (string) $description;
		}

		$amount_raised = $campaign['amount_raised'] ?? $campaign['amountRaised'] ?? $campaign['raised'] ?? 0;
		$goal_amount   = $campaign['goal_amount'] ?? $campaign['goalAmount'] ?? $campaign['goal'] ?? 0;

		$campaign['amount_raised'] = (float) $amount_raised;
		$campaign['raised']        = (float) $amount_raised;
		$campaign['goal_amount']   = (float) $goal_amount;
		$campaign['goal']          = (float) $goal_amount;

		$currency = $campaign['currency'] ?? $campaign['currencyCode'] ?? $campaign['currency_code'] ?? '';
		if ( '' !== (string) $currency ) {
			$campaign['currency'] = strtoupper( (string) $currency );
		}

		$currency_symbol = $campaign['currency_symbol'] ?? $campaign['currencySymbol'] ?? '';
		if ( '' !== (string) $currency_symbol ) {
			$campaign['currency_symbol'] = (string) $currency_symbol;
		}

		$donation_amounts = $campaign['donation_amounts'] ?? $campaign['donationAmounts'] ?? $campaign['suggestedDonations'] ?? array();
		if ( is_array( $donation_amounts ) ) {
			$campaign['donation_amounts'] = $donation_amounts;
		}

		$teams = $campaign['teams'] ?? array();
		if ( is_array( $teams ) ) {
			$campaign['teams'] = $teams;
		}

		$comments = $campaign['comments'] ?? array();
		if ( is_array( $comments ) ) {
			$campaign['comments'] = $comments;
		}

		$media = $campaign['media'] ?? array();
		if ( is_array( $media ) ) {
			$campaign['media'] = $media;
		}

		$recent_donations = $campaign['recentDonations'] ?? $campaign['recent_donations'] ?? $campaign['donors'] ?? array();
		if ( is_array( $recent_donations ) ) {
			$normalized_recent_donations = self::normalize_recent_donations( $recent_donations );
			$campaign['recentDonations'] = $normalized_recent_donations;
			$campaign['recent_donors']   = $normalized_recent_donations;
			$campaign['donors']          = $normalized_recent_donations;
		}

		$donor_count = $campaign['donor_count'] ?? $campaign['donorCount'] ?? $campaign['totalDonors'] ?? $campaign['total_donors'] ?? $campaign['donorsCount'] ?? null;
		if ( null !== $donor_count ) {
			$campaign['donor_count'] = (int) $donor_count;
		}

		$banner_url = $campaign['banner_url'] ?? $campaign['bannerUrl'] ?? $campaign['banner_image'] ?? $campaign['bannerImage'] ?? '';
		if ( '' === (string) $banner_url && ! empty( $media ) ) {
			$first_media = $media[0] ?? array();
			if ( is_array( $first_media ) ) {
				$banner_url = $first_media['url'] ?? $first_media['src'] ?? '';
			}
		}
		if ( '' !== (string) $banner_url ) {
			$campaign['banner_url'] = (string) $banner_url;
		}

		$video_url = $campaign['video_url'] ?? $campaign['videoUrl'] ?? $campaign['video'] ?? '';
		if ( '' !== (string) $video_url ) {
			$campaign['video_url'] = (string) $video_url;
		}

		$gallery_images = $campaign['gallery_images'] ?? $campaign['galleryImages'] ?? array();
		if ( empty( $gallery_images ) && ! empty( $media ) ) {
			$gallery_images = $media;
		}
		if ( is_array( $gallery_images ) ) {
			$campaign['gallery_images'] = $gallery_images;
			$campaign['images']         = $gallery_images;
		}

		$url = $campaign['url'] ?? $campaign['publicUrl'] ?? $campaign['public_url'] ?? $campaign['campaignUrl'] ?? '';
		if ( '' !== (string) $url ) {
			$campaign['url'] = (string) $url;
		}

		return $campaign;
	}

	/**
	 * Normalize donor objects from API payload variants.
	 *
	 * @param mixed[] $donations Donor/recent-donations payload.
	 *
	 * @return mixed[]
	 */
	private static function normalize_recent_donations( array $donations ): array {
		$normalized = array();

		foreach ( $donations as $donation ) {
			if ( ! is_array( $donation ) ) {
				continue;
			}

			$name = $donation['name'] ?? $donation['donor_name'] ?? $donation['donorName'] ?? null;
			if ( null === $name && isset( $donation['donor'] ) && is_array( $donation['donor'] ) ) {
				$name = $donation['donor']['name'] ?? $donation['donor']['donorName'] ?? $donation['donor']['donor_name'] ?? null;
			}

			if ( null !== $name && '' !== (string) $name ) {
				$donation['name']       = (string) $name;
				$donation['donor_name'] = (string) $name;
			}

			$normalized[] = $donation;
		}

		return $normalized;
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
