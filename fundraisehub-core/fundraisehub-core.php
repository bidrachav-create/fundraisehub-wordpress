<?php
/**
 * Plugin Name:       FundRaiseHub Core
 * Plugin URI:        https://fundraisehub.com/
 * Description:       Core plugin for FundRaiseHub – brings fundraising campaigns directly into your WordPress site.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            FundRaiseHub
 * Author URI:        https://fundraisehub.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       fundraisehub-core
 * Domain Path:       /languages
 */

declare( strict_types=1 );

namespace FundRaiseHub\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'FUNDRAISEHUB_CORE_VERSION', '1.0.0' );
define( 'FUNDRAISEHUB_CORE_FILE', __FILE__ );
define( 'FUNDRAISEHUB_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'FUNDRAISEHUB_CORE_URL', plugin_dir_url( __FILE__ ) );

// Autoload via Composer.
if ( file_exists( __DIR__ . '/../vendor/autoload.php' ) ) {
	require_once __DIR__ . '/../vendor/autoload.php';
}

// Manually require class files when Composer autoload is not available.
$includes = [
	'includes/class-settings.php',
	'includes/class-api-client.php',
	'includes/class-campaign-cpt.php',
	'includes/class-block-registry.php',
	'includes/class-shortcode-registry.php',
	'includes/class-campaign-sync.php',
];

foreach ( $includes as $file ) {
	$path = FUNDRAISEHUB_CORE_DIR . $file;
	if ( file_exists( $path ) ) {
		require_once $path;
	}
}

/**
 * Plugin activation hook.
 */
function fundraisehub_core_activate(): void {
	// Store "needs-setup" flag so the admin can be prompted.
	if ( ! get_option( 'fundraisehub_needs_setup' ) ) {
		add_option( 'fundraisehub_needs_setup', true );
	}

	// Call register_post_type() directly so the CPT exists before
	// flush_rewrite_rules() runs (the 'init' hook has not fired yet).
	( new CampaignCPT() )->register_post_type();

	flush_rewrite_rules();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\fundraisehub_core_activate' );

/**
 * Plugin deactivation hook.
 */
function fundraisehub_core_deactivate(): void {
	// Unschedule the recurring sync cron so it does not fire while inactive.
	$timestamp = wp_next_scheduled( 'fundraisehub_campaign_sync' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'fundraisehub_campaign_sync' );
	}

	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\fundraisehub_core_deactivate' );

/**
 * Bootstrap the plugin on 'plugins_loaded'.
 */
function fundraisehub_core_init(): void {
	load_plugin_textdomain(
		'fundraisehub-core',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);

	( new Settings() )->register();
	( new CampaignCPT() )->register();
	( new BlockRegistry() )->register();
	( new ShortcodeRegistry() )->register();
	( new CampaignSync() )->register();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\fundraisehub_core_init' );
