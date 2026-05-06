<?php
/**
 * Abstract base class for all FundRaiseHub Elementor widgets.
 *
 * Provides shared controls (campaign selector, branding colours, typography)
 * and a common render() implementation that delegates to CampaignRenderer.
 *
 * @package FundRaiseHub\Elementor
 */

declare( strict_types=1 );

namespace FundRaiseHub\Elementor;

use FundRaiseHub\Core\CampaignCPT;
use FundRaiseHub\Core\CampaignRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CampaignWidgetBase
 *
 * All concrete FundRaiseHub widgets extend this class and must implement
 * get_name(), get_title(), get_icon(), and get_block_name().
 */
abstract class CampaignWidgetBase extends \Elementor\Widget_Base {

	/**
	 * Return the Gutenberg block slug that this widget mirrors.
	 *
	 * E.g. "campaign-banner", "campaign-stats-bar".
	 */
	abstract public function get_block_name(): string;

	/**
	 * Return the Elementor widget categories.
	 *
	 * @return string[]
	 */
	public function get_categories(): array {
		return array( 'fundraisehub' );
	}

	/**
	 * Return script handles that must be enqueued when this widget is rendered.
	 *
	 * Interactive blocks (donate-button, donation-tiles) need both their
	 * block view.js and the postMessage donate-bridge. The honor-scroll block
	 * needs only its view.js.
	 *
	 * @return string[]
	 */
	public function get_script_depends(): array {
		$block = $this->get_block_name();
		$deps  = array();

		// Blocks that ship a front-end view.js registered by ElementorManager.
		$view_script_blocks = array(
			'campaign-donate-button',
			'campaign-donation-tiles',
			'campaign-honor-scroll',
		);

		if ( in_array( $block, $view_script_blocks, true ) ) {
			$deps[] = 'fundraisehub-' . $block . '-view';
		}

		// Iframe-based blocks also require the donate postMessage bridge.
		$iframe_blocks = array(
			'campaign-donate-button',
			'campaign-donation-tiles',
		);

		if ( in_array( $block, $iframe_blocks, true ) ) {
			$deps[] = 'fundraisehub-donate-bridge';
		}

		return $deps;
	}

	// -------------------------------------------------------------------------
	// Controls
	// -------------------------------------------------------------------------

	/**
	 * Register the widget's controls (Content + Style tabs).
	 */
	protected function register_controls(): void {
		// --- Content: Campaign selector ------------------------------------.
		$this->start_controls_section(
			'section_campaign',
			array(
				'label' => esc_html__( 'Campaign', 'fundraisehub-elementor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'campaign_post_id',
			array(
				'label'   => esc_html__( 'Campaign', 'fundraisehub-elementor' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => $this->get_campaign_options(),
			)
		);

		$this->end_controls_section();

		// --- Style: Branding overrides -------------------------------------.
		$this->start_controls_section(
			'section_branding',
			array(
				'label' => esc_html__( 'Branding', 'fundraisehub-elementor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'color_primary',
			array(
				'label'       => esc_html__( 'Primary Color', 'fundraisehub-elementor' ),
				'description' => esc_html__( 'Overrides the campaign primary color used in iframe embed URLs.', 'fundraisehub-elementor' ),
				'type'        => \Elementor\Controls_Manager::COLOR,
				'default'     => '',
			)
		);

		$this->add_control(
			'color_secondary',
			array(
				'label'       => esc_html__( 'Secondary Color', 'fundraisehub-elementor' ),
				'description' => esc_html__( 'Overrides the campaign secondary color used in iframe embed URLs.', 'fundraisehub-elementor' ),
				'type'        => \Elementor\Controls_Manager::COLOR,
				'default'     => '',
			)
		);

		$this->add_control(
			'text_color',
			array(
				'label'     => esc_html__( 'Text Color', 'fundraisehub-elementor' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}}' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'typography',
				'selector' => '{{WRAPPER}}',
			)
		);

		$this->end_controls_section();
	}

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	/**
	 * Render the widget on the front end and in Elementor's preview pane.
	 */
	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$post_id  = (int) ( $settings['campaign_post_id'] ?? 0 );

		if ( ! $post_id ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<p>' . esc_html__( 'Please select a campaign.', 'fundraisehub-elementor' ) . '</p>';
			}
			return;
		}

		$campaign_data_json = (string) get_post_meta( $post_id, '_fundraisehub_campaign_data', true );
		$api_url            = (string) get_post_meta( $post_id, '_fundraisehub_api_url', true );
		$campaign_data      = $campaign_data_json ? json_decode( $campaign_data_json, true ) : array();

		if ( ! is_array( $campaign_data ) ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<p>' . esc_html__( 'Campaign data not yet synced. Please sync campaigns in the FundRaiseHub settings.', 'fundraisehub-elementor' ) . '</p>';
			}
			return;
		}

		// Apply color overrides from widget style controls.
		$color_primary   = (string) ( $settings['color_primary'] ?? '' );
		$color_secondary = (string) ( $settings['color_secondary'] ?? '' );

		if ( '' !== $color_primary ) {
			$campaign_data['colorPrimary']  = $color_primary;
			$campaign_data['color_primary'] = $color_primary;
		}

		if ( '' !== $color_secondary ) {
			$campaign_data['colorSecondary']  = $color_secondary;
			$campaign_data['color_secondary'] = $color_secondary;
		}

		echo CampaignRenderer::render_block( $this->get_block_name(), $campaign_data, $api_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_block returns escaped HTML
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Build the SELECT options array for the campaign_post_id control.
	 *
	 * @return string[] Keys are post IDs (cast to string), values are post titles.
	 */
	private function get_campaign_options(): array {
		$options = array( '' => esc_html__( '— Select Campaign —', 'fundraisehub-elementor' ) );

		$posts = get_posts(
			array(
				'post_type'      => CampaignCPT::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		foreach ( $posts as $post ) {
			$options[ (string) $post->ID ] = self::format_campaign_title( $post );
		}

		return $options;
	}

	/**
	 * Return a campaign post's display title, falling back to a translated placeholder.
	 *
	 * @param \WP_Post $post Campaign post object.
	 *
	 * @return string Sanitized plain-text post title or "(no title)".
	 */
	private static function format_campaign_title( \WP_Post $post ): string {
		$title = '' !== $post->post_title ? $post->post_title : __( '(no title)', 'fundraisehub-elementor' );
		return sanitize_text_field( $title );
	}
}
