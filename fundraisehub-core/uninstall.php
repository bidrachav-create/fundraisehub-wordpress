<?php
/**
 * Uninstall routine for FundRaiseHub Core.
 *
 * Called automatically by WordPress when the plugin is deleted via the admin.
 *
 * @package FundRaiseHub\Core
 */

declare( strict_types=1 );

// Bail if uninstall is not called from WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete all plugin options.
$options = [
	'fundraisehub_needs_setup',
	'fundraisehub_api_key',
	'fundraisehub_site_url',
	'fundraisehub_campaign_cache',
];

foreach ( $options as $option ) {
	delete_option( $option );
}

// Remove any scheduled cron events added by the plugin.
$timestamp = wp_next_scheduled( 'fundraisehub_campaign_sync' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'fundraisehub_campaign_sync' );
}
