<?php
/**
 * Plugin Name:       FundRaiseHub Elementor
 * Plugin URI:        https://fundraisehub.com/
 * Description:       Elementor widget pack for FundRaiseHub campaigns. Requires FundRaiseHub Core.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            FundRaiseHub
 * Author URI:        https://fundraisehub.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       fundraisehub-elementor
 * Domain Path:       /languages
 */

declare( strict_types=1 );

namespace FundRaiseHub\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'FUNDRAISEHUB_ELEMENTOR_VERSION', '1.0.0' );
define( 'FUNDRAISEHUB_ELEMENTOR_FILE', __FILE__ );
define( 'FUNDRAISEHUB_ELEMENTOR_DIR', plugin_dir_path( __FILE__ ) );
define( 'FUNDRAISEHUB_ELEMENTOR_URL', plugin_dir_url( __FILE__ ) );

/**
 * Bootstrap the Elementor plugin.
 *
 * Deferred until 'plugins_loaded' so we can check that both FundRaiseHub Core
 * and Elementor are active before registering anything.
 */
function fundraisehub_elementor_init(): void {
	// Dependency check: FundRaiseHub Core.
	if ( ! defined( 'FUNDRAISEHUB_CORE_VERSION' ) ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\\fundraisehub_elementor_missing_core_notice' );
		return;
	}

	// Dependency check: Elementor.
	if ( ! did_action( 'elementor/loaded' ) ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\\fundraisehub_elementor_missing_elementor_notice' );
		return;
	}

	load_plugin_textdomain(
		'fundraisehub-elementor',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);

	// Load the manager class.
	$manager_file = FUNDRAISEHUB_ELEMENTOR_DIR . 'includes/class-elementor-manager.php';
	if ( file_exists( $manager_file ) ) {
		require_once $manager_file;
		( new ElementorManager() )->register();
	}
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\fundraisehub_elementor_init' );

/**
 * Admin notice when FundRaiseHub Core is missing.
 */
function fundraisehub_elementor_missing_core_notice(): void {
	?>
	<div class="notice notice-error">
		<p>
			<?php esc_html_e( 'FundRaiseHub Elementor requires the FundRaiseHub Core plugin to be installed and active.', 'fundraisehub-elementor' ); ?>
		</p>
	</div>
	<?php
}

/**
 * Admin notice when Elementor is missing.
 */
function fundraisehub_elementor_missing_elementor_notice(): void {
	?>
	<div class="notice notice-error">
		<p>
			<?php esc_html_e( 'FundRaiseHub Elementor requires Elementor to be installed and active.', 'fundraisehub-elementor' ); ?>
		</p>
	</div>
	<?php
}
