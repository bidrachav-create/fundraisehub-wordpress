<?php
/**
 * Elementor Manager – registers all FundRaiseHub Elementor widgets.
 *
 * @package FundRaiseHub\Elementor
 */

declare( strict_types=1 );

namespace FundRaiseHub\Elementor;

use FundRaiseHub\Core\CampaignCPT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ElementorManager
 *
 * Discovers widget classes from the widgets/ sub-directory and registers
 * them with the Elementor Widgets Manager.
 */
class ElementorManager {

	/**
	 * Hook registration into Elementor's lifecycle.
	 */
	public function register(): void {
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
		add_action( 'elementor/editor/after_enqueue_scripts', array( $this, 'enqueue_editor_data' ) );
		add_action( 'elementor/frontend/after_register_scripts', array( $this, 'register_frontend_scripts' ) );
		add_action( 'wp_ajax_fundraisehub_get_campaigns', array( $this, 'get_campaigns_ajax' ) );
	}

	/**
	 * Register the "fundraisehub" widget category in Elementor's panel.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elementor elements manager instance.
	 */
	public function register_category( \Elementor\Elements_Manager $elements_manager ): void {
		$elements_manager->add_category(
			'fundraisehub',
			array(
				'title' => esc_html__( 'FundRaiseHub Campaigns', 'fundraisehub-elementor' ),
				'icon'  => 'fa fa-heart',
			)
		);
	}

	/**
	 * Register all widgets found in the widgets/ directory.
	 *
	 * Each widget must live in `widgets/{widget-name}/class-{widget-name}-widget.php`
	 * and extend \Elementor\Widget_Base.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager instance.
	 */
	public function register_widgets( \Elementor\Widgets_Manager $widgets_manager ): void {
		// Load the abstract base class before any concrete widget is required.
		require_once FUNDRAISEHUB_ELEMENTOR_DIR . 'includes/class-campaign-widget-base.php';

		$widgets_dir = FUNDRAISEHUB_ELEMENTOR_DIR . 'widgets';

		if ( ! is_dir( $widgets_dir ) ) {
			return;
		}

		$entries = glob( trailingslashit( $widgets_dir ) . '*', GLOB_ONLYDIR );

		if ( ! $entries ) {
			return;
		}

		foreach ( $entries as $widget_dir ) {
			$slug = basename( $widget_dir );
			$file = $widget_dir . '/class-' . $slug . '-widget.php';

			if ( ! file_exists( $file ) ) {
				continue;
			}

			require_once $file;

			// Build the fully-qualified class name from the widget slug.
			// Convention: widgets/campaign-card → FundRaiseHub\Elementor\Widget\CampaignCard.
			$class_name = __NAMESPACE__ . '\\Widget\\' . $this->slug_to_class_name( $slug );

			if ( class_exists( $class_name ) ) {
				$widgets_manager->register( new $class_name() );
			}
		}
	}

	/**
	 * Handle the AJAX request that returns all published campaign posts as JSON.
	 *
	 * Response shape: { success: true, data: [ { id: 123, text: "Title" }, … ] }
	 */
	public function get_campaigns_ajax(): void {
		check_ajax_referer( 'fundraisehub_elementor_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'fundraisehub-elementor' ) ) );
		}

		$posts = get_posts(
			array(
				'post_type'      => CampaignCPT::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$options = array();
		foreach ( $posts as $post ) {
			$options[] = array(
				'id'   => $post->ID,
				'text' => self::format_campaign_title( $post ),
			);
		}

		wp_send_json_success( $options );
	}

	/**
	 * Inject the AJAX nonce and endpoint URL into the Elementor editor.
	 *
	 * Creates `window.fundraisehubElementorData` so the editor UI (or any
	 * custom panel JS) can call the `fundraisehub_get_campaigns` AJAX action
	 * with a valid nonce without hard-coding it.
	 */
	public function enqueue_editor_data(): void {
		$data = array(
			'nonce'   => wp_create_nonce( 'fundraisehub_elementor_nonce' ),
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		);

		wp_add_inline_script(
			'elementor-editor',
			'window.fundraisehubElementorData = ' . wp_json_encode( $data ) . ';',
			'before'
		);
	}

	/**
	 * Register the front-end view scripts for interactive campaign blocks.
	 *
	 * These scripts are registered (not enqueued here) so that widgets can
	 * declare them via get_script_depends() and Elementor will enqueue them
	 * only on pages containing those widgets.
	 *
	 * Registered handles:
	 *   - fundraisehub-campaign-donate-button-view
	 *   - fundraisehub-campaign-donation-tiles-view
	 *   - fundraisehub-campaign-honor-scroll-view
	 *   - fundraisehub-donate-bridge
	 */
	public function register_frontend_scripts(): void {
		$blocks_dir = FUNDRAISEHUB_CORE_DIR . 'assets/blocks/';
		$blocks_url = FUNDRAISEHUB_CORE_URL . 'assets/blocks/';

		// Blocks that ship a front-end view.js.
		$view_script_blocks = array(
			'campaign-donate-button',
			'campaign-donation-tiles',
			'campaign-honor-scroll',
		);

		foreach ( $view_script_blocks as $block_slug ) {
			$script_path = $blocks_dir . $block_slug . '/view.js';
			$asset_file  = $blocks_dir . $block_slug . '/view.asset.php';

			if ( ! file_exists( $script_path ) ) {
				continue;
			}

			$asset        = file_exists( $asset_file ) ? require $asset_file : array();
			$dependencies = $asset['dependencies'] ?? array();
			$version      = $asset['version'] ?? FUNDRAISEHUB_CORE_VERSION;

			wp_register_script(
				'fundraisehub-' . $block_slug . '-view',
				$blocks_url . $block_slug . '/view.js',
				$dependencies,
				$version,
				true
			);
		}

		// Donate bridge – required by iframe-based widgets (donate-button, donation-tiles).
		$bridge_path  = FUNDRAISEHUB_CORE_DIR . 'assets/js/donate-bridge.js';
		$bridge_asset = FUNDRAISEHUB_CORE_DIR . 'assets/js/donate-bridge.asset.php';

		if ( file_exists( $bridge_path ) ) {
			$asset        = file_exists( $bridge_asset ) ? require $bridge_asset : array();
			$dependencies = $asset['dependencies'] ?? array();
			$version      = $asset['version'] ?? FUNDRAISEHUB_CORE_VERSION;

			wp_register_script(
				'fundraisehub-donate-bridge',
				FUNDRAISEHUB_CORE_URL . 'assets/js/donate-bridge.js',
				$dependencies,
				$version,
				true
			);

			wp_localize_script(
				'fundraisehub-donate-bridge',
				'fundraisehubBridge',
				array(
					'thankYouMessage' => __( 'Thank you for your donation!', 'fundraisehub-core' ),
					'closeLabel'      => __( 'Close', 'fundraisehub-core' ),
				)
			);
		}
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Convert a kebab-case slug to a PascalCase class name.
	 *
	 * @param string $slug e.g. "campaign-card".
	 *
	 * @return string e.g. "CampaignCard"
	 */
	private function slug_to_class_name( string $slug ): string {
		return str_replace( ' ', '', ucwords( str_replace( '-', ' ', $slug ) ) );
	}

	/**
	 * Return a campaign post's display title, falling back to a translated placeholder.
	 *
	 * @param \WP_Post $post Campaign post object.
	 *
	 * @return string Sanitized plain-text post title or "(no title)".
	 */
	private static function format_campaign_title( \WP_Post $post ): string {
		$title = '' !== $post->post_title ? $post->post_title : __( '(no title)', 'fundraisehub-elementor' );
		return sanitize_text_field( $title );
	}
}
