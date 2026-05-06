<?php
/**
 * Elementor widget: Campaign Donate Button.
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
 * Class CampaignDonateButton
 *
 * Renders the campaign donate button (with hidden donation iframe) via Elementor.
 */
class CampaignDonateButton extends CampaignWidgetBase {

	/**
	 * Return the unique widget name.
	 */
	public function get_name(): string {
		return 'fundraisehub-campaign-donate-button';
	}

	/**
	 * Return the human-readable widget title shown in the Elementor panel.
	 */
	public function get_title(): string {
		return esc_html__( 'Campaign Donate Button', 'fundraisehub-elementor' );
	}

	/**
	 * Return the Elementor icon class for the widget panel.
	 */
	public function get_icon(): string {
		return 'eicon-button';
	}

	/**
	 * Return the Gutenberg block slug this widget mirrors.
	 */
	public function get_block_name(): string {
		return 'campaign-donate-button';
	}
}
