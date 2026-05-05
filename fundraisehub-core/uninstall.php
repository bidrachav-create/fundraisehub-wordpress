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

global $wpdb;

// Delete all plugin options.
$options = array(
	'fundraisehub_needs_setup',
	'fundraisehub_api_key',
	'fundraisehub_site_url',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// Remove any scheduled cron events added by the plugin.
$timestamp = wp_next_scheduled( 'fundraisehub_campaign_sync' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'fundraisehub_campaign_sync' );
}

// Delete all campaign transients (individual campaign cache + all list query caches).
$prefixes = array(
	'_transient_fundraisehub_campaign_',
	'_transient_timeout_fundraisehub_campaign_',
	'_transient_fundraisehub_campaign_list_',
	'_transient_timeout_fundraisehub_campaign_list_',
);

foreach ( $prefixes as $prefix ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( $prefix ) . '%'
		)
	);
}
