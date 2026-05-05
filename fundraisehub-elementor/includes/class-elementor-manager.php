<?php
/**
 * Elementor Manager – registers all FundRaiseHub Elementor widgets.
 *
 * @package FundRaiseHub\Elementor
 */

declare( strict_types=1 );

namespace FundRaiseHub\Elementor;

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
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
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

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Convert a kebab-case slug to a PascalCase class name.
	 *
	 * @param string $slug e.g. "campaign-card"
	 *
	 * @return string e.g. "CampaignCard"
	 */
	private function slug_to_class_name( string $slug ): string {
		return str_replace( ' ', '', ucwords( str_replace( '-', ' ', $slug ) ) );
	}
}
