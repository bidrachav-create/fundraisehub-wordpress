<?php
/**
 * Elementor widget: Campaign Video.
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
 * Class CampaignVideo
 *
 * Renders the campaign video embed via Elementor.
 */
class CampaignVideo extends CampaignWidgetBase {

	/**
	 * Return the unique widget name.
	 */
	public function get_name(): string {
		return 'fundraisehub-campaign-video';
	}

	/**
	 * Return the human-readable widget title shown in the Elementor panel.
	 */
	public function get_title(): string {
		return esc_html__( 'Campaign Video', 'fundraisehub-elementor' );
	}

	/**
	 * Return the Elementor icon class for the widget panel.
	 */
	public function get_icon(): string {
		return 'eicon-youtube';
	}

	/**
	 * Return the Gutenberg block slug this widget mirrors.
	 */
	public function get_block_name(): string {
		return 'campaign-video';
	}
}
