<?php
/**
 * Campaign Custom Post Type – registers the "fundraisehub_campaign" CPT.
 *
 * @package FundRaiseHub\Core
 */

declare( strict_types=1 );

namespace FundRaiseHub\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CampaignCPT
 *
 * Registers and configures the custom post type used to mirror campaign
 * data fetched from the FundRaiseHub platform.
 */
class CampaignCPT {

	/** Post type slug. */
	public const POST_TYPE = 'fundraisehub_campaign';

	/**
	 * Hook the registration callback into WordPress.
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_post_type' ] );
	}

	/**
	 * Register the custom post type.
	 */
	public function register_post_type(): void {
		$labels = [
			'name'                  => _x( 'Campaigns', 'post type general name', 'fundraisehub-core' ),
			'singular_name'         => _x( 'Campaign', 'post type singular name', 'fundraisehub-core' ),
			'menu_name'             => _x( 'Campaigns', 'admin menu', 'fundraisehub-core' ),
			'name_admin_bar'        => _x( 'Campaign', 'add new on dashboard', 'fundraisehub-core' ),
			'add_new'               => __( 'Add New', 'fundraisehub-core' ),
			'add_new_item'          => __( 'Add New Campaign', 'fundraisehub-core' ),
			'new_item'              => __( 'New Campaign', 'fundraisehub-core' ),
			'edit_item'             => __( 'Edit Campaign', 'fundraisehub-core' ),
			'view_item'             => __( 'View Campaign', 'fundraisehub-core' ),
			'all_items'             => __( 'All Campaigns', 'fundraisehub-core' ),
			'search_items'          => __( 'Search Campaigns', 'fundraisehub-core' ),
			'not_found'             => __( 'No campaigns found.', 'fundraisehub-core' ),
			'not_found_in_trash'    => __( 'No campaigns found in Trash.', 'fundraisehub-core' ),
		];

		$args = [
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => [ 'slug' => 'campaigns' ],
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => 20,
			'menu_icon'          => 'dashicons-heart',
			'supports'           => [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ],
			'show_in_rest'       => true,
		];

		register_post_type( self::POST_TYPE, $args );
	}
}
