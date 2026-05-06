<?php
/**
 * Elementor widget: Campaign Photo Gallery.
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
 * Class CampaignPhotoGallery
 *
 * Renders the campaign photo gallery grid via Elementor.
 */
class CampaignPhotoGallery extends CampaignWidgetBase {

	/**
	 * Return the unique widget name.
	 */
	public function get_name(): string {
		return 'fundraisehub-campaign-photo-gallery';
	}

	/**
	 * Return the human-readable widget title shown in the Elementor panel.
	 */
	public function get_title(): string {
		return esc_html__( 'Campaign Photo Gallery', 'fundraisehub-elementor' );
	}

	/**
	 * Return the Elementor icon class for the widget panel.
	 */
	public function get_icon(): string {
		return 'eicon-gallery-grid';
	}

	/**
	 * Return the Gutenberg block slug this widget mirrors.
	 */
	public function get_block_name(): string {
		return 'campaign-photo-gallery';
	}
}
