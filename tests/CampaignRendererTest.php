<?php
/**
 * Tests for FundRaiseHub\Core\CampaignRenderer.
 *
 * Includes contract assertions that verify the iframe URL builder matches
 * the backend route: {api_url}/embed/campaign/{campaign_id}.
 *
 * All WordPress functions are replaced by stubs in tests/stubs.php.
 */

declare( strict_types=1 );

use FundRaiseHub\Core\CampaignRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Class CampaignRendererTest
 */
class CampaignRendererTest extends TestCase {

	protected function setUp(): void {
		WPTestState::reset();
	}

	// -------------------------------------------------------------------------
	// Shared fixture helpers
	// -------------------------------------------------------------------------

	/**
	 * Build a campaign fixture with a layout section enabled for the given block.
	 *
	 * @param string  $block_key      Layout key (e.g. 'donate_button').
	 * @param mixed[] $extra_campaign Additional campaign fields to merge.
	 * @param mixed[] $extra_block    Additional block-config fields to merge.
	 *
	 * @return array<string,mixed>
	 */
	private function campaign_with_block(
		string $block_key,
		array $extra_campaign = array(),
		array $extra_block = array()
	): array {
		$campaign = array_merge(
			array(
				'id'           => '42',
				'name'         => 'Test Campaign',
				'amount_raised' => 1500.00,
				'goal_amount'  => 5000.00,
				'donor_count'  => 20,
				'layout'       => array(
					$block_key => array_merge( array( 'enabled' => true ), $extra_block ),
				),
			),
			$extra_campaign
		);

		return $campaign;
	}

	// -------------------------------------------------------------------------
	// render_block() dispatcher
	// -------------------------------------------------------------------------

	/**
	 * render_block() should return an empty string for an unknown block slug,
	 * even when typical campaign data is supplied.
	 */
	public function test_render_block_returns_empty_for_unknown_block(): void {
		$campaign = array(
			'id'   => '1',
			'name' => 'Campaign',
			'layout' => array(),
		);
		$html = CampaignRenderer::render_block( 'not-a-block', $campaign );
		$this->assertSame( '', $html );
	}

	/**
	 * render_block() routes 'campaign-banner' to render_banner().
	 */
	public function test_render_block_dispatches_banner(): void {
		$campaign = $this->campaign_with_block( 'banner', array( 'banner_url' => 'https://img.example.com/banner.jpg' ) );
		$html     = CampaignRenderer::render_block( 'campaign-banner', $campaign );
		$this->assertStringContainsString( 'fundraisehub-campaign-banner', $html );
	}

	/**
	 * render_block() routes 'campaign-donate-button' to render_donate_button().
	 */
	public function test_render_block_dispatches_donate_button(): void {
		$campaign = $this->campaign_with_block( 'donate_button' );
		$html     = CampaignRenderer::render_block( 'campaign-donate-button', $campaign, 'https://api.example.com' );
		$this->assertStringContainsString( 'fundraisehub-campaign-donate-button', $html );
	}

	// -------------------------------------------------------------------------
	// render_banner()
	// -------------------------------------------------------------------------

	/**
	 * render_banner() returns '' when block is disabled.
	 */
	public function test_render_banner_returns_empty_when_disabled(): void {
		$campaign = array( 'layout' => array( 'banner' => array( 'enabled' => false ) ) );
		$this->assertSame( '', CampaignRenderer::render_banner( $campaign ) );
	}

	/**
	 * render_banner() returns '' when no banner URL is present.
	 */
	public function test_render_banner_returns_empty_when_no_url(): void {
		$campaign = array( 'layout' => array( 'banner' => array( 'enabled' => true ) ) );
		$this->assertSame( '', CampaignRenderer::render_banner( $campaign ) );
	}

	/**
	 * render_banner() includes the image URL and alt text.
	 */
	public function test_render_banner_includes_image_tag(): void {
		$campaign = $this->campaign_with_block(
			'banner',
			array( 'banner_url' => 'https://img.example.com/banner.jpg' )
		);

		$html = CampaignRenderer::render_banner( $campaign );

		$this->assertStringContainsString( '<img', $html );
		$this->assertStringContainsString( 'https://img.example.com/banner.jpg', $html );
		$this->assertStringContainsString( 'Test Campaign', $html );
	}

	// -------------------------------------------------------------------------
	// render_stats_bar()
	// -------------------------------------------------------------------------

	/**
	 * render_stats_bar() returns '' when disabled.
	 */
	public function test_render_stats_bar_returns_empty_when_disabled(): void {
		$campaign = array( 'layout' => array( 'stats_bar' => array( 'enabled' => false ) ) );
		$this->assertSame( '', CampaignRenderer::render_stats_bar( $campaign ) );
	}

	/**
	 * render_stats_bar() shows raised, donors, and goal figures.
	 */
	public function test_render_stats_bar_shows_amounts(): void {
		$campaign = $this->campaign_with_block( 'stats_bar' );
		$html     = CampaignRenderer::render_stats_bar( $campaign );

		$this->assertStringContainsString( '1,500.00', $html );
		$this->assertStringContainsString( '5,000.00', $html );
		$this->assertStringContainsString( '20', $html );
	}

	/**
	 * render_stats_bar() should support nested data.campaign-style payloads.
	 */
	public function test_render_stats_bar_supports_nested_campaign_payload(): void {
		$campaign = array(
			'campaign' => array(
				'id'          => '77',
				'title'       => 'Nested Campaign',
				'amountRaised' => 1500,
				'goalAmount'  => 5000,
				'layout'      => array(
					'stats_bar' => array( 'enabled' => true ),
				),
			),
			'recentDonations' => array(
				array( 'name' => 'Alice', 'amount' => 10 ),
				array( 'name' => 'Bob', 'amount' => 20 ),
			),
		);

		$html = CampaignRenderer::render_stats_bar( $campaign );

		$this->assertStringContainsString( '1,500.00', $html );
		$this->assertStringContainsString( '5,000.00', $html );
		$this->assertStringContainsString( '>2<', $html );
	}

	// -------------------------------------------------------------------------
	// render_thermometer()
	// -------------------------------------------------------------------------

	/**
	 * render_thermometer() returns '' when disabled.
	 */
	public function test_render_thermometer_returns_empty_when_disabled(): void {
		$campaign = array( 'layout' => array( 'thermometer' => array( 'enabled' => false ) ) );
		$this->assertSame( '', CampaignRenderer::render_thermometer( $campaign ) );
	}

	/**
	 * render_thermometer() computes the percentage (30% of 5000 = 1500).
	 */
	public function test_render_thermometer_calculates_percent(): void {
		$campaign = $this->campaign_with_block( 'thermometer' );
		$html     = CampaignRenderer::render_thermometer( $campaign );

		$this->assertStringContainsString( '30', $html );
	}

	/**
	 * render_thermometer() caps at 100% when raised exceeds goal.
	 */
	public function test_render_thermometer_caps_at_100_percent(): void {
		$campaign = $this->campaign_with_block(
			'thermometer',
			array( 'amount_raised' => 9999.00, 'goal_amount' => 100.00 )
		);
		$html = CampaignRenderer::render_thermometer( $campaign );

		$this->assertStringContainsString( '100', $html );
		$this->assertStringNotContainsString( '9999', $html );
	}

	/**
	 * render_thermometer() shows 0% when goal is zero (avoid division by zero).
	 */
	public function test_render_thermometer_shows_zero_percent_when_no_goal(): void {
		$campaign = $this->campaign_with_block(
			'thermometer',
			array( 'amount_raised' => 500.00, 'goal_amount' => 0 )
		);
		$html = CampaignRenderer::render_thermometer( $campaign );

		$this->assertStringContainsString( '0', $html );
	}

	// -------------------------------------------------------------------------
	// render_description()
	// -------------------------------------------------------------------------

	/**
	 * render_description() returns '' when disabled.
	 */
	public function test_render_description_returns_empty_when_disabled(): void {
		$campaign = array( 'layout' => array( 'description' => array( 'enabled' => false ) ) );
		$this->assertSame( '', CampaignRenderer::render_description( $campaign ) );
	}

	/**
	 * render_description() returns '' when there is no description text.
	 */
	public function test_render_description_returns_empty_when_no_text(): void {
		$campaign = array( 'layout' => array( 'description' => array( 'enabled' => true ) ) );
		$this->assertSame( '', CampaignRenderer::render_description( $campaign ) );
	}

	/**
	 * render_description() outputs the campaign description within a wrapper div.
	 */
	public function test_render_description_outputs_text(): void {
		$campaign       = $this->campaign_with_block( 'description', array( 'description' => 'Help us <strong>today</strong>!' ) );
		$html           = CampaignRenderer::render_description( $campaign );

		$this->assertStringContainsString( 'fundraisehub-campaign-description', $html );
		$this->assertStringContainsString( 'Help us', $html );
	}

	// -------------------------------------------------------------------------
	// render_donate_button() — iframe URL contract (KEY TESTS)
	// -------------------------------------------------------------------------

	/**
	 * render_donate_button() renders an iframe when both api_url and campaign_id are present.
	 */
	public function test_render_donate_button_renders_iframe_when_configured(): void {
		$campaign = $this->campaign_with_block( 'donate_button' );
		$html     = CampaignRenderer::render_donate_button( $campaign, 'https://app.fundraisehub.com' );

		$this->assertStringContainsString( '<iframe', $html );
	}

	/**
	 * The iframe src must use the /embed/campaign/{id} backend route — NOT /api/wp/v1/.
	 *
	 * This is the canonical backend contract assertion.
	 */
	public function test_donate_button_iframe_url_uses_embed_campaign_route(): void {
		$campaign = $this->campaign_with_block( 'donate_button' );
		$api_url  = 'https://app.fundraisehub.com';

		$html = CampaignRenderer::render_donate_button( $campaign, $api_url );

		// Must contain /embed/campaign/42.
		$this->assertStringContainsString( '/embed/campaign/42', $html );

		// Must NOT use the REST API path.
		$this->assertStringNotContainsString( '/api/wp/v1/', $html );
	}

	/**
	 * The iframe src must begin with the configured api_url (no double-slash, no path prefix).
	 */
	public function test_donate_button_iframe_url_starts_with_api_url(): void {
		$campaign = $this->campaign_with_block( 'donate_button' );
		$api_url  = 'https://app.fundraisehub.com';

		$html = CampaignRenderer::render_donate_button( $campaign, $api_url );

		$this->assertStringContainsString( 'src="https://app.fundraisehub.com/embed/campaign/42', $html );
	}

	/**
	 * A trailing slash on api_url must not produce a double-slash in the iframe src.
	 */
	public function test_donate_button_iframe_url_strips_trailing_slash_from_api_url(): void {
		$campaign = $this->campaign_with_block( 'donate_button' );

		$html = CampaignRenderer::render_donate_button( $campaign, 'https://app.fundraisehub.com/' );

		$this->assertStringNotContainsString( '//embed', $html );
		$this->assertStringContainsString( '/embed/campaign/42', $html );
	}

	/**
	 * The iframe src must include the primary colour param without the leading #.
	 */
	public function test_donate_button_iframe_url_includes_color_without_hash(): void {
		$campaign           = $this->campaign_with_block(
			'donate_button',
			array( 'colorPrimary' => '#ff5500' )
		);

		$html = CampaignRenderer::render_donate_button( $campaign, 'https://app.fundraisehub.com' );

		$this->assertStringContainsString( 'color=ff5500', $html );
		$this->assertStringNotContainsString( 'color=%23', $html ); // % encoded # not expected.
	}

	/**
	 * The iframe src must include the secondary colour param without the leading #.
	 */
	public function test_donate_button_iframe_url_includes_secondary_color(): void {
		$campaign = $this->campaign_with_block(
			'donate_button',
			array( 'colorSecondary' => '#003399' )
		);

		$html = CampaignRenderer::render_donate_button( $campaign, 'https://app.fundraisehub.com' );

		$this->assertStringContainsString( 'secondary=003399', $html );
	}

	/**
	 * The iframe src must include the origin param set to home_url().
	 *
	 * home_url() in stubs returns 'https://example.org'.
	 */
	public function test_donate_button_iframe_url_includes_origin_param(): void {
		$campaign = $this->campaign_with_block( 'donate_button' );

		$html = CampaignRenderer::render_donate_button( $campaign, 'https://app.fundraisehub.com' );

		$this->assertStringContainsString( 'origin=', $html );
		$this->assertStringContainsString( 'example.org', $html );
	}

	/**
	 * Empty color values must be omitted from the iframe src query string
	 * (array_filter removes falsy values before building the URL).
	 */
	public function test_donate_button_iframe_url_omits_empty_color_params(): void {
		// No colorPrimary or colorSecondary in campaign data.
		$campaign = $this->campaign_with_block( 'donate_button' );

		$html = CampaignRenderer::render_donate_button( $campaign, 'https://app.fundraisehub.com' );

		$this->assertStringNotContainsString( 'color=&', $html );
		$this->assertStringNotContainsString( 'secondary=&', $html );
	}

	/**
	 * The campaign id is URL-encoded in the iframe src path.
	 */
	public function test_donate_button_iframe_url_encodes_campaign_id(): void {
		$campaign = $this->campaign_with_block( 'donate_button', array( 'id' => 'org/campaign 123' ) );

		$html = CampaignRenderer::render_donate_button( $campaign, 'https://app.fundraisehub.com' );

		// rawurlencode('org/campaign 123') = 'org%2Fcampaign%20123'.
		$this->assertStringContainsString( 'org%2Fcampaign%20123', $html );
	}

	/**
	 * render_donate_button() must NOT render an iframe when api_url is empty.
	 */
	public function test_donate_button_skips_iframe_when_no_api_url(): void {
		$campaign = $this->campaign_with_block( 'donate_button' );
		$html     = CampaignRenderer::render_donate_button( $campaign, '' );

		$this->assertStringNotContainsString( '<iframe', $html );
	}

	/**
	 * render_donate_button() must NOT render an iframe when campaign id is absent.
	 */
	public function test_donate_button_skips_iframe_when_no_campaign_id(): void {
		$campaign = $this->campaign_with_block( 'donate_button' );
		unset( $campaign['id'] );

		$html = CampaignRenderer::render_donate_button( $campaign, 'https://app.fundraisehub.com' );

		$this->assertStringNotContainsString( '<iframe', $html );
	}

	/**
	 * render_donate_button() returns '' when the block is disabled.
	 */
	public function test_render_donate_button_returns_empty_when_disabled(): void {
		$campaign = array( 'id' => '1', 'layout' => array( 'donate_button' => array( 'enabled' => false ) ) );
		$html     = CampaignRenderer::render_donate_button( $campaign, 'https://app.fundraisehub.com' );

		$this->assertSame( '', $html );
	}

	// -------------------------------------------------------------------------
	// render_donation_tiles() — iframe URL contract (KEY TESTS)
	// -------------------------------------------------------------------------

	/**
	 * render_donation_tiles() must use the /embed/campaign/{id} route — NOT /api/wp/v1/.
	 */
	public function test_donation_tiles_iframe_url_uses_embed_campaign_route(): void {
		$campaign = $this->campaign_with_block(
			'donation_tiles',
			array( 'donation_amounts' => array( 25, 50, 100 ) )
		);

		$html = CampaignRenderer::render_donation_tiles( $campaign, 'https://app.fundraisehub.com' );

		$this->assertStringContainsString( '/embed/campaign/42', $html );
		$this->assertStringNotContainsString( '/api/wp/v1/', $html );
	}

	/**
	 * render_donation_tiles() iframe src must start with the configured api_url.
	 */
	public function test_donation_tiles_iframe_url_starts_with_api_url(): void {
		$campaign = $this->campaign_with_block(
			'donation_tiles',
			array( 'donation_amounts' => array( 10 ) )
		);

		$html = CampaignRenderer::render_donation_tiles( $campaign, 'https://app.fundraisehub.com' );

		$this->assertStringContainsString( 'src="https://app.fundraisehub.com/embed/campaign/42', $html );
	}

	/**
	 * render_donation_tiles() renders one tile per donation amount.
	 */
	public function test_donation_tiles_renders_tile_for_each_amount(): void {
		$campaign = $this->campaign_with_block(
			'donation_tiles',
			array( 'donation_amounts' => array( 10, 25, 50 ) )
		);

		$html = CampaignRenderer::render_donation_tiles( $campaign, 'https://app.fundraisehub.com' );

		$this->assertStringContainsString( '$10.00', $html );
		$this->assertStringContainsString( '$25.00', $html );
		$this->assertStringContainsString( '$50.00', $html );
	}

	/**
	 * render_donation_tiles() returns '' when there are no amounts.
	 */
	public function test_donation_tiles_returns_empty_when_no_amounts(): void {
		$campaign = $this->campaign_with_block( 'donation_tiles' );
		// No donation_amounts and no block_cfg amounts.
		$html = CampaignRenderer::render_donation_tiles( $campaign, 'https://app.fundraisehub.com' );

		$this->assertSame( '', $html );
	}

	/**
	 * render_donation_tiles() returns '' when disabled.
	 */
	public function test_donation_tiles_returns_empty_when_disabled(): void {
		$campaign = array(
			'id'     => '1',
			'layout' => array( 'donation_tiles' => array( 'enabled' => false ) ),
		);

		$this->assertSame( '', CampaignRenderer::render_donation_tiles( $campaign ) );
	}

	// -------------------------------------------------------------------------
	// iframe src exact format — full contract assertion
	// -------------------------------------------------------------------------

	/**
	 * Full contract test: the iframe src must match
	 * {api_url}/embed/campaign/{campaign_id}?color={hex}&secondary={hex}&origin={origin}
	 *
	 * This assertion pins the exact URL format that the FundRaiseHub backend
	 * expects for the donation embed endpoint.
	 */
	public function test_iframe_src_matches_full_backend_contract(): void {
		$campaign = $this->campaign_with_block(
			'donate_button',
			array(
				'id'             => '99',
				'colorPrimary'   => '#ab1234',
				'colorSecondary' => '#005566',
			)
		);

		$html = CampaignRenderer::render_donate_button( $campaign, 'https://app.fundraisehub.com' );

		// Extract the src attribute from the rendered iframe.
		preg_match( '/src="([^"]+)"/', $html, $matches );
		$src = $matches[1] ?? '';

		$this->assertNotEmpty( $src, 'An iframe with a src attribute must be present' );

		// Base path must be /embed/campaign/{id}.
		$parsed = parse_url( $src );
		$this->assertSame( 'https', $parsed['scheme'] );
		$this->assertSame( 'app.fundraisehub.com', $parsed['host'] );
		$this->assertSame( '/embed/campaign/99', $parsed['path'] );

		// Query params.
		parse_str( $parsed['query'] ?? '', $query );
		$this->assertSame( 'ab1234', $query['color'], 'color param must be hex without leading #' );
		$this->assertSame( '005566', $query['secondary'], 'secondary param must be hex without leading #' );
		$this->assertStringContainsString( 'example.org', $query['origin'] ?? '', 'origin param must be home_url()' );
	}

	// -------------------------------------------------------------------------
	// Other blocks
	// -------------------------------------------------------------------------

	/**
	 * render_honor_scroll() returns '' when disabled.
	 */
	public function test_render_honor_scroll_returns_empty_when_disabled(): void {
		$campaign = array( 'layout' => array( 'honor_scroll' => array( 'enabled' => false ) ) );
		$this->assertSame( '', CampaignRenderer::render_honor_scroll( $campaign ) );
	}

	/**
	 * render_honor_scroll() renders donor names when donors are present.
	 */
	public function test_render_honor_scroll_renders_donors(): void {
		$campaign = $this->campaign_with_block(
			'honor_scroll',
			array(
				'donors' => array(
					array( 'name' => 'Alice Smith', 'amount' => 50 ),
					array( 'name' => 'Bob Jones', 'amount' => 100 ),
				),
			)
		);

		$html = CampaignRenderer::render_honor_scroll( $campaign );

		$this->assertStringContainsString( 'Alice Smith', $html );
		$this->assertStringContainsString( 'Bob Jones', $html );
		$this->assertStringContainsString( '$50.00', $html );
	}

	/**
	 * render_teams() returns '' when disabled.
	 */
	public function test_render_teams_returns_empty_when_disabled(): void {
		$campaign = array( 'layout' => array( 'teams' => array( 'enabled' => false ) ) );
		$this->assertSame( '', CampaignRenderer::render_teams( $campaign ) );
	}

	/**
	 * render_teams() renders team names and raised amounts.
	 */
	public function test_render_teams_renders_team_leaderboard(): void {
		$campaign = $this->campaign_with_block(
			'teams',
			array(
				'teams' => array(
					array( 'name' => 'Team Alpha', 'amount_raised' => 3000 ),
					array( 'name' => 'Team Beta', 'amount_raised' => 1500 ),
				),
			)
		);

		$html = CampaignRenderer::render_teams( $campaign );

		$this->assertStringContainsString( 'Team Alpha', $html );
		$this->assertStringContainsString( '3,000.00', $html );
	}

	/**
	 * render_photo_gallery() returns '' when disabled.
	 */
	public function test_render_photo_gallery_returns_empty_when_disabled(): void {
		$campaign = array( 'layout' => array( 'photo_gallery' => array( 'enabled' => false ) ) );
		$this->assertSame( '', CampaignRenderer::render_photo_gallery( $campaign ) );
	}

	/**
	 * render_comments() returns '' when disabled.
	 */
	public function test_render_comments_returns_empty_when_disabled(): void {
		$campaign = array( 'layout' => array( 'comments' => array( 'enabled' => false ) ) );
		$this->assertSame( '', CampaignRenderer::render_comments( $campaign ) );
	}

	/**
	 * render_comments() renders author names and messages.
	 */
	public function test_render_comments_renders_comment_list(): void {
		$campaign = $this->campaign_with_block(
			'comments',
			array(
				'comments' => array(
					array( 'author' => 'Jane', 'message' => 'Great cause!' ),
				),
			)
		);

		$html = CampaignRenderer::render_comments( $campaign );

		$this->assertStringContainsString( 'Jane', $html );
		$this->assertStringContainsString( 'Great cause', $html );
	}
}
