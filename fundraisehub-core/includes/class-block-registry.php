<?php
/**
 * Block Registry – registers all Gutenberg blocks provided by FundRaiseHub Core.
 *
 * @package FundRaiseHub\Core
 */

declare( strict_types=1 );

namespace FundRaiseHub\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BlockRegistry
 *
 * Discovers and registers every block whose metadata lives in a
 * `blocks/{block-name}/block.json` file inside the plugin.
 */
class BlockRegistry {

	/**
	 * Hook the registration callback into WordPress.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_blocks' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_data' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_donate_bridge' ) );
		add_filter( 'render_block_context', array( $this, 'provide_campaign_context' ), 10, 3 );
	}

	/**
	 * Register all blocks found in the blocks/ directory.
	 *
	 * Each block must have a `block.json` metadata file as required by
	 * the block API v2 / v3 specification.
	 */
	public function register_blocks(): void {
		$blocks_dir = FUNDRAISEHUB_CORE_DIR . 'blocks';

		if ( ! is_dir( $blocks_dir ) ) {
			return;
		}

		$entries = glob( trailingslashit( $blocks_dir ) . '*', GLOB_ONLYDIR );

		if ( ! $entries ) {
			return;
		}

		foreach ( $entries as $block_dir ) {
			if ( file_exists( $block_dir . '/block.json' ) ) {
				register_block_type( $block_dir );
			}
		}
	}

	/**
	 * Enqueue the donate postMessage bridge script on campaign post type pages.
	 *
	 * The script is loaded in the footer so it runs after the DOM is ready.
	 * Localised strings are injected via `window.fundraisehubBridge`.
	 */
	public function enqueue_donate_bridge(): void {
		if ( ! is_singular( CampaignCPT::POST_TYPE ) ) {
			return;
		}

		$script_url  = FUNDRAISEHUB_CORE_URL . 'assets/js/donate-bridge.js';
		$script_path = FUNDRAISEHUB_CORE_DIR . 'assets/js/donate-bridge.js';
		$asset_file  = FUNDRAISEHUB_CORE_DIR . 'assets/js/donate-bridge.asset.php';

		if ( ! file_exists( $script_path ) ) {
			return;
		}

		$asset        = file_exists( $asset_file ) ? require $asset_file : array();
		$dependencies = $asset['dependencies'] ?? array();
		$version      = $asset['version'] ?? FUNDRAISEHUB_CORE_VERSION;

		wp_enqueue_script(
			'fundraisehub-donate-bridge',
			$script_url,
			$dependencies,
			$version,
			true
		);

		// Generate a short-lived HMAC-SHA256 embed token so the raw API key
		// never reaches the browser.  The token is computed server-side by
		// signing "<site_origin>:<hour_epoch>" with the API key as the
		// HMAC secret, using a 1-hour sliding window.  FundRaiseHub validates
		// the token server-side by recomputing the HMAC with the stored API
		// key for this site — the raw API key itself is never transmitted to
		// the client or included in the postMessage payload.
		$api_key     = defined( 'FUNDRAISEHUB_API_KEY' ) && '' !== (string) FUNDRAISEHUB_API_KEY
			? (string) FUNDRAISEHUB_API_KEY
			: (string) get_option( 'fundraisehub_api_key', '' );
		$window      = (int) ( floor( time() / HOUR_IN_SECONDS ) * HOUR_IN_SECONDS );
		$embed_token = '' !== $api_key
			? hash_hmac( 'sha256', home_url() . ':' . $window, $api_key )
			: '';

		wp_localize_script(
			'fundraisehub-donate-bridge',
			'fundraisehubBridge',
			array(
				'embedToken'      => $embed_token,
				'thankYouMessage' => __( 'Thank you for your donation!', 'fundraisehub-core' ),
				'closeLabel'      => __( 'Close', 'fundraisehub-core' ),
			)
		);
	}

	/**
	 * Inject plugin configuration data for use by block editor scripts.
	 *
	 * Sets `window.fundraisehubData` so that edit.js components can check
	 * whether the API key has been configured without making REST requests.
	 */
	public function enqueue_editor_data(): void {
		$api_url = (string) get_option( 'fundraisehub_api_url', '' );
		if ( '' === $api_url ) {
			$api_url = (string) get_option( 'fundraisehub_site_url', '' );
		}

		$data = array(
			'apiKeyConfigured' => ! empty( get_option( 'fundraisehub_api_key' ) ) || $this->is_oauth_configured(),
			'authConfigured'   => ! empty( get_option( 'fundraisehub_api_key' ) ) || $this->is_oauth_configured(),
			'siteUrl'          => esc_url_raw( $api_url ),
		);

		wp_add_inline_script(
			'wp-blocks',
			'window.fundraisehubData = ' . wp_json_encode( $data ) . ';',
			'before'
		);
	}

	/**
	 * Inject campaign post meta into the block context for all blocks whose
	 * immediate parent is `fundraisehub/campaign-wrapper`.
	 *
	 * This makes `_fundraisehub_campaign_data` and `_fundraisehub_api_url`
	 * available as `$block->context['fundraisehub/campaign-data']` and
	 * `$block->context['fundraisehub/api-url']` in child block render.php files.
	 *
	 * @param mixed[]        $context      Block context values.
	 * @param mixed[]        $parsed_block The block being rendered.
	 * @param \WP_Block|null $parent_block The parent block instance, if any.
	 *
	 * @return mixed[] Filtered context array.
	 */
	public function provide_campaign_context( array $context, array $parsed_block, ?\WP_Block $parent_block ): array {
		if ( null === $parent_block || 'fundraisehub/campaign-wrapper' !== $parent_block->name ) {
			return $context;
		}

		$post_id = get_the_ID();

		if ( ! $post_id ) {
			return $context;
		}

		$context['fundraisehub/campaign-data'] = (string) get_post_meta( $post_id, '_fundraisehub_campaign_data', true );
		$context['fundraisehub/api-url']       = (string) get_post_meta( $post_id, '_fundraisehub_api_url', true );

		return $context;
	}

	/**
	 * Return true when OAuth Client ID and secret are both configured.
	 *
	 * @return bool
	 */
	private function is_oauth_configured(): bool {
		$client_id = defined( 'FUNDRAISEHUB_OAUTH_CLIENT_ID' ) && '' !== (string) FUNDRAISEHUB_OAUTH_CLIENT_ID
			? (string) FUNDRAISEHUB_OAUTH_CLIENT_ID
			: (string) get_option( 'fundraisehub_oauth_client_id', '' );
		$secret    = defined( 'FUNDRAISEHUB_OAUTH_CLIENT_SECRET' ) && '' !== (string) FUNDRAISEHUB_OAUTH_CLIENT_SECRET
			? (string) FUNDRAISEHUB_OAUTH_CLIENT_SECRET
			: (string) get_option( 'fundraisehub_oauth_client_secret', '' );

		return '' !== $client_id && '' !== $secret;
	}
}
