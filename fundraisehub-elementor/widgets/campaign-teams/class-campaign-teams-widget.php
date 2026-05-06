<?php
/**
 * Elementor widget: Campaign Teams.
 *
 * @package FundRaiseHub\Elementor
 */

declare( strict_types=1 );

namespace FundRaiseHub\Elementor\Widget;

use FundRaiseHub\Elementor\CampaignWidgetBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CampaignTeams
 *
 * Renders the campaign teams leaderboard via Elementor.
 */
class CampaignTeams extends CampaignWidgetBase {

	/**
	 * Return the unique widget name.
	 */
	public function get_name(): string {
		return 'fundraisehub-campaign-teams';
	}

	/**
	 * Return the human-readable widget title shown in the Elementor panel.
	 */
	public function get_title(): string {
		return esc_html__( 'Campaign Teams', 'fundraisehub-elementor' );
	}

	/**
	 * Return the Elementor icon class for the widget panel.
	 */
	public function get_icon(): string {
		return 'eicon-social-icons';
	}

	/**
	 * Return the Gutenberg block slug this widget mirrors.
	 */
	public function get_block_name(): string {
		return 'campaign-teams';
	}
}
