<?php
/**
 * Elementor widget: Campaign Banner.
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
 * Class CampaignBanner
 *
 * Renders the campaign banner image via the Elementor editor and front end.
 */
class CampaignBanner extends CampaignWidgetBase {

	/**
	 * Return the unique widget name.
	 */
	public function get_name(): string {
		return 'fundraisehub-campaign-banner';
	}

	/**
	 * Return the human-readable widget title shown in the Elementor panel.
	 */
	public function get_title(): string {
		return esc_html__( 'Campaign Banner', 'fundraisehub-elementor' );
	}

	/**
	 * Return the Elementor icon class for the widget panel.
	 */
	public function get_icon(): string {
		return 'eicon-image-bold';
	}

	/**
	 * Return the Gutenberg block slug this widget mirrors.
	 */
	public function get_block_name(): string {
		return 'campaign-banner';
	}
}
