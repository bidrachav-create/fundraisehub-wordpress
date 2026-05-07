<?php
/**
 * Tests for FundRaiseHub\Core\CampaignSync.
 *
 * All WordPress functions are replaced by stubs in tests/stubs.php.
 */

declare( strict_types=1 );

use FundRaiseHub\Core\ApiClient;
use FundRaiseHub\Core\CampaignSync;
use PHPUnit\Framework\TestCase;

/**
 * Class CampaignSyncTest
 */
class CampaignSyncTest extends TestCase {

	protected function setUp(): void {
		WPTestState::reset();
		// Default API URL so the upsert helper can read it.
		WPTestState::$options['fundraisehub_api_url'] = 'https://api.example.com';
	}

	// -------------------------------------------------------------------------
	// Helper: build a minimal campaign fixture.
	// -------------------------------------------------------------------------

	/** @return array<string,mixed> */
	private function campaign( string $id = '1', string $name = 'Test Campaign', string $slug = 'test-campaign' ): array {
		return array(
			'id'   => $id,
			'name' => $name,
			'slug' => $slug,
		);
	}

	/** Build a mock ApiClient that returns pre-queued responses. */
	private function make_client(): ApiClient {
		return new ApiClient( 'https://api.example.com', 'testkey' );
	}

	// -------------------------------------------------------------------------
	// get_campaign()
	// -------------------------------------------------------------------------

	/**
	 * get_campaign() should return data from the transient when cached.
	 */
	public function test_get_campaign_returns_cached_data(): void {
		WPTestState::$transients['fundraisehub_campaign_1'] = $this->campaign();

		$sync   = new CampaignSync( $this->make_client() );
		$result = $sync->get_campaign( '1' );

		$this->assertSame( 0, WPTestState::$http_get_call_count, 'HTTP API must not be called on a cache hit' );
		$this->assertSame( '1', $result['id'] );
	}

	/**
	 * get_campaign() should call the API when the transient is absent.
	 */
	public function test_get_campaign_calls_api_on_cache_miss(): void {
		WPTestState::$http_response_queue[] = WPTestState::http_ok( $this->campaign( '5' ) );

		$sync   = new CampaignSync( $this->make_client() );
		$result = $sync->get_campaign( '5' );

		$this->assertSame( 1, WPTestState::$http_get_call_count );
		$this->assertSame( '5', $result['id'] );
	}

	/**
	 * get_campaign() should return WP_Error when the API fails.
	 */
	public function test_get_campaign_returns_wp_error_on_api_failure(): void {
		WPTestState::$http_response_queue[] = new WP_Error( 'http_request_failed', 'Timeout' );

		$sync   = new CampaignSync( $this->make_client() );
		$result = $sync->get_campaign( '99' );

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	/**
	 * get_campaign() should unwrap { data: {...} } envelopes.
	 */
	public function test_get_campaign_unwraps_data_envelope(): void {
		$raw = array( 'data' => $this->campaign( '3' ) );
		WPTestState::$http_response_queue[] = WPTestState::http_ok( $raw );

		$sync   = new CampaignSync( $this->make_client() );
		$result = $sync->get_campaign( '3' );

		$this->assertSame( '3', $result['id'] );
		$this->assertArrayNotHasKey( 'data', $result );
	}

	/**
	 * get_campaign() should write to the transient on a cache miss.
	 */
	public function test_get_campaign_caches_result(): void {
		WPTestState::$http_response_queue[] = WPTestState::http_ok( $this->campaign( '7' ) );

		$sync = new CampaignSync( $this->make_client() );
		$sync->get_campaign( '7' );

		$this->assertArrayHasKey( 'fundraisehub_campaign_7', WPTestState::$transients );
	}

	// -------------------------------------------------------------------------
	// get_campaigns()
	// -------------------------------------------------------------------------

	/**
	 * get_campaigns() should apply default per_page / page / category.
	 */
	public function test_get_campaigns_applies_default_args(): void {
		WPTestState::$http_response_queue[] = WPTestState::http_ok( array( $this->campaign() ) );

		$sync = new CampaignSync( $this->make_client() );
		$sync->get_campaigns();

		$url = WPTestState::$http_get_urls[0];
		$this->assertStringContainsString( 'per_page=10', $url );
		$this->assertStringContainsString( 'page=1', $url );
	}

	/**
	 * Caller-supplied args override defaults.
	 */
	public function test_get_campaigns_respects_caller_args(): void {
		WPTestState::$http_response_queue[] = WPTestState::http_ok( array() );

		$sync = new CampaignSync( $this->make_client() );
		$sync->get_campaigns( array( 'per_page' => 25, 'page' => 3 ) );

		$url = WPTestState::$http_get_urls[0];
		$this->assertStringContainsString( 'per_page=25', $url );
		$this->assertStringContainsString( 'page=3', $url );
	}

	/**
	 * get_campaigns() should return WP_Error on API failure.
	 */
	public function test_get_campaigns_returns_wp_error_on_failure(): void {
		WPTestState::$http_response_queue[] = WPTestState::http_error( 500 );

		$sync   = new CampaignSync( $this->make_client() );
		$result = $sync->get_campaigns();

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	/**
	 * get_campaigns() should unwrap { data: [...] } envelopes.
	 */
	public function test_get_campaigns_unwraps_data_envelope(): void {
		$payload = array(
			'data' => array( $this->campaign( '10' ), $this->campaign( '11' ) ),
			'meta' => array( 'total_pages' => 1 ),
		);
		WPTestState::$http_response_queue[] = WPTestState::http_ok( $payload );

		$sync   = new CampaignSync( $this->make_client() );
		$result = $sync->get_campaigns();

		$this->assertCount( 2, $result );
		$this->assertSame( '10', $result[0]['id'] );
	}

	/**
	 * get_campaigns() should return from the transient when cached.
	 */
	public function test_get_campaigns_returns_cached_data(): void {
		// Build the expected transient key.
		$args          = array( 'per_page' => 10, 'page' => 1, 'category' => '' );
		$version       = 1;
		$transient_key = 'fundraisehub_campaign_list_v' . $version . '_' . md5( (string) json_encode( $args ) );

		WPTestState::$transients[ $transient_key ] = array( $this->campaign( '20' ) );

		$sync   = new CampaignSync( $this->make_client() );
		$result = $sync->get_campaigns();

		$this->assertSame( 0, WPTestState::$http_get_call_count );
		$this->assertSame( '20', $result[0]['id'] );
	}

	// -------------------------------------------------------------------------
	// sync_all()
	// -------------------------------------------------------------------------

	/**
	 * sync_all() should silently abort without throwing when the API returns WP_Error.
	 */
	public function test_sync_all_aborts_gracefully_on_api_error(): void {
		WPTestState::$http_response_queue[] = new WP_Error( 'http_request_failed', 'No route to host' );

		$sync = new CampaignSync( $this->make_client() );

		$this->expectNotToPerformAssertions();
		$sync->sync_all(); // Must not throw.
	}

	/**
	 * sync_all() should skip campaigns that lack an 'id' field.
	 */
	public function test_sync_all_skips_campaigns_without_id(): void {
		$payload = array(
			'data' => array(
				array( 'name' => 'No ID campaign' ), // Missing 'id' key.
				$this->campaign( '2' ),
			),
		);
		WPTestState::$http_response_queue[] = WPTestState::http_ok( $payload );

		$sync = new CampaignSync( $this->make_client() );
		$sync->sync_all();

		// Only the campaign with id=2 should have been inserted (one post inserted).
		$this->assertCount( 1, WPTestState::$post_meta, 'Only the campaign with an id should be upserted' );
		// The single inserted post's meta should contain the campaign ID '2'.
		$metas = array_values( WPTestState::$post_meta );
		$this->assertSame( '2', $metas[0]['_fundraisehub_campaign_id'] ?? null );
	}

	/**
	 * sync_all() should paginate through all pages returned by the API.
	 */
	public function test_sync_all_paginates_through_pages(): void {
		// Page 1: two campaigns, total_pages = 2.
		WPTestState::$http_response_queue[] = WPTestState::http_ok(
			array(
				'data' => array( $this->campaign( '1' ), $this->campaign( '2' ) ),
				'meta' => array( 'total_pages' => 2 ),
			)
		);
		// Page 2: one campaign.
		WPTestState::$http_response_queue[] = WPTestState::http_ok(
			array(
				'data' => array( $this->campaign( '3' ) ),
				'meta' => array( 'total_pages' => 2 ),
			)
		);

		$sync = new CampaignSync( $this->make_client() );
		$sync->sync_all();

		$this->assertSame( 2, WPTestState::$http_get_call_count, 'Must issue one HTTP request per page' );
	}

	/**
	 * sync_all() should bump the list-cache version after completing.
	 */
	public function test_sync_all_bumps_list_cache_version(): void {
		WPTestState::$http_response_queue[] = WPTestState::http_ok(
			array( 'data' => array( $this->campaign() ) )
		);

		$sync = new CampaignSync( $this->make_client() );
		$sync->sync_all();

		$new_version = WPTestState::$options['fundraisehub_list_cache_ver'] ?? 0;
		$this->assertGreaterThanOrEqual( 2, $new_version, 'List cache version must be bumped after sync' );
	}

	// -------------------------------------------------------------------------
	// sync_one()
	// -------------------------------------------------------------------------

	/**
	 * sync_one() should insert a new post and update the transient.
	 */
	public function test_sync_one_inserts_post_and_caches(): void {
		WPTestState::$http_response_queue[] = WPTestState::http_ok( $this->campaign( '42', 'My Campaign' ) );

		$sync    = new CampaignSync( $this->make_client() );
		$post_id = $sync->sync_one( '42' );

		$this->assertIsInt( $post_id );
		$this->assertGreaterThan( 0, $post_id );
		$this->assertArrayHasKey( 'fundraisehub_campaign_42', WPTestState::$transients );
	}

	/**
	 * sync_one() should return WP_Error when the API fails.
	 */
	public function test_sync_one_returns_wp_error_on_api_failure(): void {
		WPTestState::$http_response_queue[] = new WP_Error( 'http_request_failed', 'DNS error' );

		$sync   = new CampaignSync( $this->make_client() );
		$result = $sync->sync_one( '99' );

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	/**
	 * sync_one() should return WP_Error when the API returns invalid data.
	 */
	public function test_sync_one_returns_wp_error_for_missing_id_in_response(): void {
		// The API returns a valid 200 but with no 'id' field.
		WPTestState::$http_response_queue[] = WPTestState::http_ok( array( 'name' => 'No ID' ) );

		$sync   = new CampaignSync( $this->make_client() );
		$result = $sync->sync_one( '1' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'fundraisehub_invalid_campaign', $result->get_error_code() );
	}

	// -------------------------------------------------------------------------
	// Idempotency (upsert logic)
	// -------------------------------------------------------------------------

	/**
	 * When data hasn't changed (same MD5), a second sync must not update the post.
	 */
	public function test_sync_all_skips_unchanged_campaigns(): void {
		$campaign = $this->campaign( '77' );
		$json     = (string) json_encode( $campaign );
		$hash     = md5( $json );

		// Simulate an existing post with the same hash.
		$existing_post_id = 5;
		// get_posts() stub looks up WPTestState::$posts[ meta_value ] where meta_value = '77'.
		// With 'fields' => 'ids', it returns the integer ID.
		WPTestState::$posts['77']                                                         = $existing_post_id;
		WPTestState::$post_meta[ $existing_post_id ]['_fundraisehub_campaign_hash']       = $hash;
		// Also store the campaign id meta so the idempotency check can find it.
		WPTestState::$post_meta[ $existing_post_id ]['_fundraisehub_campaign_id']         = '77';

		WPTestState::$http_response_queue[] = WPTestState::http_ok(
			array( 'data' => array( $campaign ) )
		);

		$sync = new CampaignSync( $this->make_client() );
		$sync->sync_all();

		// wp_insert_post must NOT have been called; next_post_id stays at 1.
		$this->assertSame( 1, WPTestState::$next_post_id, 'Unchanged campaign must not trigger a post insert/update' );
	}

	// -------------------------------------------------------------------------
	// Meta constant checks
	// -------------------------------------------------------------------------

	/**
	 * The META_CAMPAIGN_ID constant should be a private meta key starting with _.
	 */
	public function test_meta_campaign_id_constant(): void {
		$this->assertStringStartsWith( '_', CampaignSync::META_CAMPAIGN_ID );
	}
}
