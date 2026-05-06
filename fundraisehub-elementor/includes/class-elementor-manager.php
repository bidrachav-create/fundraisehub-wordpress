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
		$base_file = FUNDRAISEHUB_ELEMENTOR_DIR . 'includes/class-campaign-widget-base.php';
		if ( file_exists( $base_file ) ) {
			require_once $base_file;
		}

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
				'text' => '' !== $post->post_title ? $post->post_title : esc_html__( '(no title)', 'fundraisehub-elementor' ),
			);
		}

		wp_send_json_success( $options );
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
}
