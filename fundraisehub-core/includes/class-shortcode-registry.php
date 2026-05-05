<?php
/**
 * Shortcode Registry – registers shortcode fallbacks for all FundRaiseHub blocks.
 *
 * @package FundRaiseHub\Core
 */

declare( strict_types=1 );

namespace FundRaiseHub\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ShortcodeRegistry
 *
 * Provides shortcode equivalents for users who cannot use the block editor.
 *
 * Available shortcodes:
 *   [fundraisehub_campaign id="123"]
 *   [fundraisehub_campaign_list limit="10" category="education"]
 */
class ShortcodeRegistry {

	/**
	 * Hook shortcode registrations into WordPress.
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_shortcodes' ] );
	}

	/**
	 * Register all shortcodes.
	 */
	public function register_shortcodes(): void {
		add_shortcode( 'fundraisehub_campaign', [ $this, 'render_campaign' ] );
		add_shortcode( 'fundraisehub_campaign_list', [ $this, 'render_campaign_list' ] );
	}

	/**
	 * Render a single campaign by its remote ID.
	 *
	 * @param mixed[]|string $atts Shortcode attributes.
	 *
	 * @return string HTML output.
	 */
	public function render_campaign( array|string $atts ): string {
		$atts = shortcode_atts(
			[
				'id'    => '',
				'class' => '',
			],
			$atts,
			'fundraisehub_campaign'
		);

		if ( empty( $atts['id'] ) ) {
			return '';
		}

		$sync     = new CampaignSync();
		$campaign = $sync->get_campaign( (string) $atts['id'] );

		if ( is_wp_error( $campaign ) || empty( $campaign ) ) {
			return '<p class="fundraisehub-error">' . esc_html__( 'Campaign not found.', 'fundraisehub-core' ) . '</p>';
		}

		ob_start();
		$this->render_campaign_card( $campaign, (string) $atts['class'] );
		return (string) ob_get_clean();
	}

	/**
	 * Render a list of campaigns.
	 *
	 * @param mixed[]|string $atts Shortcode attributes.
	 *
	 * @return string HTML output.
	 */
	public function render_campaign_list( array|string $atts ): string {
		$atts = shortcode_atts(
			[
				'limit'    => 10,
				'category' => '',
				'class'    => '',
			],
			$atts,
			'fundraisehub_campaign_list'
		);

		$sync      = new CampaignSync();
		$campaigns = $sync->get_campaigns(
			[
				'per_page' => (int) $atts['limit'],
				'category' => (string) $atts['category'],
			]
		);

		if ( is_wp_error( $campaigns ) || empty( $campaigns ) ) {
			return '<p class="fundraisehub-empty">' . esc_html__( 'No campaigns found.', 'fundraisehub-core' ) . '</p>';
		}

		ob_start();
		echo '<div class="fundraisehub-campaign-list ' . esc_attr( (string) $atts['class'] ) . '">';
		foreach ( $campaigns as $campaign ) {
			$this->render_campaign_card( $campaign );
		}
		echo '</div>';
		return (string) ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Render a single campaign card.
	 *
	 * @param mixed[] $campaign Campaign data array.
	 * @param string  $class    Additional CSS class.
	 */
	private function render_campaign_card( array $campaign, string $class = '' ): void {
		$title       = esc_html( $campaign['title'] ?? '' );
		$description = wp_kses_post( $campaign['description'] ?? '' );
		$goal        = isset( $campaign['goal'] ) ? (float) $campaign['goal'] : 0.0;
		$raised      = isset( $campaign['raised'] ) ? (float) $campaign['raised'] : 0.0;
		$url         = esc_url( $campaign['url'] ?? '' );
		$class_attr  = esc_attr( 'fundraisehub-campaign-card ' . $class );
		?>
		<div class="<?php echo esc_attr( $class_attr ); ?>">
			<h3 class="fundraisehub-campaign-title"><?php echo esc_html( $title ); ?></h3>
			<div class="fundraisehub-campaign-description"><?php echo wp_kses_post( $description ); ?></div>
			<?php if ( $goal > 0 ) : ?>
				<div class="fundraisehub-campaign-progress">
					<progress value="<?php echo esc_attr( (string) $raised ); ?>" max="<?php echo esc_attr( (string) $goal ); ?>"></progress>
					<span>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: amount raised, 2: goal amount */
								__( '%1$s raised of %2$s goal', 'fundraisehub-core' ),
								number_format_i18n( $raised, 2 ),
								number_format_i18n( $goal, 2 )
							)
						);
						?>
					</span>
				</div>
			<?php endif; ?>
			<?php if ( $url ) : ?>
				<a href="<?php echo esc_url( $url ); ?>" class="fundraisehub-campaign-link">
					<?php esc_html_e( 'Donate Now', 'fundraisehub-core' ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
	}
}
