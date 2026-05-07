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
		add_action( 'init', array( $this, 'register_post_type' ) );
		// Prevent unauthenticated reads of the mirrored campaign data via the
		// standard WP REST endpoint (/wp-json/wp/v2/fundraisehub_campaign).
		// The CPT must have show_in_rest=true for Gutenberg to work, but we do
		// not want the raw synced snapshot to be publicly consumable as an API.
		add_filter( 'rest_pre_dispatch', array( $this, 'restrict_unauthenticated_rest_access' ), 10, 3 );
	}

	/**
	 * Restrict unauthenticated access to the CPT's standard WP REST endpoint.
	 *
	 * The CPT stores a read-only snapshot of remote API data. Allowing public
	 * consumption of /wp-json/wp/v2/fundraisehub_campaign would expose stale
	 * cached data as if it were a live API. Authenticated users (editors,
	 * admins) retain full access so Gutenberg continues to work.
	 *
	 * @param mixed            $result  Short-circuit result (null = proceed normally).
	 * @param \WP_REST_Server  $server  REST server instance.
	 * @param \WP_REST_Request $request Incoming request.
	 * @return mixed Original $result, or WP_Error for unauthenticated CPT requests.
	 */
	public function restrict_unauthenticated_rest_access( mixed $result, \WP_REST_Server $server, \WP_REST_Request $request ): mixed {
		if ( null !== $result ) {
			return $result;
		}

		$route = $request->get_route();
		if ( ! str_contains( $route, '/wp/v2/' . self::POST_TYPE ) ) {
			return $result;
		}

		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'The FundRaiseHub campaign REST endpoint is not available to unauthenticated requests.', 'fundraisehub-core' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return $result;
	}

	/**
	 * Register the custom post type.
	 */
	public function register_post_type(): void {
		$labels = array(
			'name'               => _x( 'Campaigns', 'post type general name', 'fundraisehub-core' ),
			'singular_name'      => _x( 'Campaign', 'post type singular name', 'fundraisehub-core' ),
			'menu_name'          => _x( 'Campaigns', 'admin menu', 'fundraisehub-core' ),
			'name_admin_bar'     => _x( 'Campaign', 'add new on dashboard', 'fundraisehub-core' ),
			'add_new'            => __( 'Add New', 'fundraisehub-core' ),
			'add_new_item'       => __( 'Add New Campaign', 'fundraisehub-core' ),
			'new_item'           => __( 'New Campaign', 'fundraisehub-core' ),
			'edit_item'          => __( 'Edit Campaign', 'fundraisehub-core' ),
			'view_item'          => __( 'View Campaign', 'fundraisehub-core' ),
			'all_items'          => __( 'All Campaigns', 'fundraisehub-core' ),
			'search_items'       => __( 'Search Campaigns', 'fundraisehub-core' ),
			'not_found'          => __( 'No campaigns found.', 'fundraisehub-core' ),
			'not_found_in_trash' => __( 'No campaigns found in Trash.', 'fundraisehub-core' ),
		);

		$slug = (string) get_option( 'fundraisehub_campaign_slug', 'campaigns' );
		if ( '' === $slug ) {
			$slug = 'campaigns';
		}

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => sanitize_title( $slug ) ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => 20,
			'menu_icon'          => 'dashicons-heart',
			'supports'           => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
			// Keep show_in_rest true so the Gutenberg block editor works on campaign
			// posts. Unauthenticated read access to the REST collection is restricted
			// via the rest_pre_dispatch hook registered in ::register().
			'show_in_rest'       => true,
		);

		register_post_type( self::POST_TYPE, $args );
	}
}
