<?php
/**
 * Campaign Renderer – static helper for rendering campaign block HTML.
 *
 * Provides a single `render_block()` dispatcher and individual per-block
 * methods so that Elementor widgets (and any other consumers) can share the
 * same rendering logic as the Gutenberg render.php files without duplication.
 *
 * @package FundRaiseHub\Core
 */

declare( strict_types=1 );

namespace FundRaiseHub\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CampaignRenderer
 *
 * All methods are static so they can be called without instantiation.
 * Each method accepts the decoded campaign data array and an optional API URL
 * (required only by blocks that embed an iframe) and returns an HTML string.
 */
class CampaignRenderer {

	/**
	 * Dispatch to the appropriate per-block render method.
	 *
	 * @param string  $block         Block slug, e.g. "campaign-banner".
	 * @param mixed[] $campaign_data Decoded campaign data array.
	 * @param string  $api_url       Base URL of the remote FundRaiseHub API.
	 *
	 * @return string Rendered HTML.
	 */
	public static function render_block( string $block, array $campaign_data, string $api_url = '' ): string {
		switch ( $block ) {
			case 'campaign-banner':
				return self::render_banner( $campaign_data );
			case 'campaign-stats-bar':
				return self::render_stats_bar( $campaign_data );
			case 'campaign-thermometer':
				return self::render_thermometer( $campaign_data );
			case 'campaign-description':
				return self::render_description( $campaign_data );
			case 'campaign-donate-button':
				return self::render_donate_button( $campaign_data, $api_url );
			case 'campaign-donation-tiles':
				return self::render_donation_tiles( $campaign_data, $api_url );
			case 'campaign-honor-scroll':
				return self::render_honor_scroll( $campaign_data );
			case 'campaign-teams':
				return self::render_teams( $campaign_data );
			case 'campaign-video':
				return self::render_video( $campaign_data );
			case 'campaign-photo-gallery':
				return self::render_photo_gallery( $campaign_data );
			case 'campaign-comments':
				return self::render_comments( $campaign_data );
			default:
				return '';
		}
	}

	/**
	 * Normalize campaign payload variants into render-ready field names.
	 *
	 * @param mixed[] $campaign_data Campaign payload.
	 *
	 * @return mixed[]
	 */
	private static function normalize_campaign_data( array $campaign_data ): array {
		if ( isset( $campaign_data['data'] ) && is_array( $campaign_data['data'] ) ) {
			$campaign_data = $campaign_data['data'];
		}

		return CampaignSync::normalize_campaign_payload( $campaign_data );
	}

	// -------------------------------------------------------------------------
	// Per-block render methods
	// -------------------------------------------------------------------------

	/**
	 * Render the campaign banner image.
	 *
	 * @param mixed[] $campaign_data Decoded campaign data array.
	 *
	 * @return string Rendered HTML.
	 */
	public static function render_banner( array $campaign_data ): string {
		$campaign_data = self::normalize_campaign_data( $campaign_data );

		$layout    = $campaign_data['layout'] ?? array();
		$block_cfg = $layout['banner'] ?? array();

		if ( empty( $block_cfg['enabled'] ) ) {
			return '';
		}

		$banner_url     = (string) ( $campaign_data['banner_url'] ?? $campaign_data['banner_image'] ?? '' );
		$campaign_title = (string) ( $campaign_data['name'] ?? $campaign_data['title'] ?? '' );

		if ( ! $banner_url ) {
			return '';
		}

		ob_start();
		?>
		<div class="fundraisehub-campaign-banner">
			<img
				src="<?php echo esc_url( $banner_url ); ?>"
				alt="<?php echo esc_attr( $campaign_title ); ?>"
				class="fundraisehub-campaign-banner__image"
			/>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render the campaign stats bar (raised / donors / goal).
	 *
	 * @param mixed[] $campaign_data Decoded campaign data array.
	 *
	 * @return string Rendered HTML.
	 */
	public static function render_stats_bar( array $campaign_data ): string {
		$campaign_data = self::normalize_campaign_data( $campaign_data );

		$layout    = $campaign_data['layout'] ?? array();
		$block_cfg = $layout['stats_bar'] ?? array();

		if ( empty( $block_cfg['enabled'] ) ) {
			return '';
		}

		$amount_raised = number_format( (float) ( $campaign_data['amount_raised'] ?? $campaign_data['raised'] ?? 0 ), 2 );
		$donor_count   = (int) ( $campaign_data['donor_count'] ?? $campaign_data['donorCount'] ?? $campaign_data['totalDonors'] ?? $campaign_data['total_donors'] ?? $campaign_data['donorsCount'] ?? 0 );
		$goal_amount   = number_format( (float) ( $campaign_data['goal_amount'] ?? $campaign_data['goal'] ?? 0 ), 2 );

		ob_start();
		?>
		<div class="fundraisehub-campaign-stats-bar">
			<div class="fundraisehub-campaign-stats-bar__item">
				<span class="fundraisehub-campaign-stats-bar__value"><?php echo esc_html( '$' . $amount_raised ); ?></span>
				<span class="fundraisehub-campaign-stats-bar__label"><?php esc_html_e( 'Raised', 'fundraisehub-core' ); ?></span>
			</div>
			<div class="fundraisehub-campaign-stats-bar__item">
				<span class="fundraisehub-campaign-stats-bar__value"><?php echo esc_html( (string) $donor_count ); ?></span>
				<span class="fundraisehub-campaign-stats-bar__label"><?php esc_html_e( 'Donors', 'fundraisehub-core' ); ?></span>
			</div>
			<div class="fundraisehub-campaign-stats-bar__item">
				<span class="fundraisehub-campaign-stats-bar__value"><?php echo esc_html( '$' . $goal_amount ); ?></span>
				<span class="fundraisehub-campaign-stats-bar__label"><?php esc_html_e( 'Goal', 'fundraisehub-core' ); ?></span>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render the campaign thermometer (progress bar).
	 *
	 * @param mixed[] $campaign_data Decoded campaign data array.
	 *
	 * @return string Rendered HTML.
	 */
	public static function render_thermometer( array $campaign_data ): string {
		$campaign_data = self::normalize_campaign_data( $campaign_data );

		$layout    = $campaign_data['layout'] ?? array();
		$block_cfg = $layout['thermometer'] ?? array();

		if ( empty( $block_cfg['enabled'] ) ) {
			return '';
		}

		$raised  = (float) ( $campaign_data['amount_raised'] ?? $campaign_data['raised'] ?? 0 );
		$goal    = (float) ( $campaign_data['goal_amount'] ?? $campaign_data['goal'] ?? 0 );
		$percent = $goal > 0 ? min( 100, round( ( $raised / $goal ) * 100, 1 ) ) : 0;

		ob_start();
		?>
		<div class="fundraisehub-campaign-thermometer">
			<div
				class="fundraisehub-campaign-thermometer__track"
				role="progressbar"
				aria-valuenow="<?php echo esc_attr( (string) $percent ); ?>"
				aria-valuemin="0"
				aria-valuemax="100"
				aria-label="<?php esc_attr_e( 'Campaign progress', 'fundraisehub-core' ); ?>"
			>
				<div
					class="fundraisehub-campaign-thermometer__fill"
					style="width:<?php echo esc_attr( $percent . '%' ); ?>;"
				></div>
			</div>
			<p class="fundraisehub-campaign-thermometer__label">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: percentage */
						__( '%1$s%% of goal reached', 'fundraisehub-core' ),
						$percent
					)
				);
				?>
			</p>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render the campaign description.
	 *
	 * @param mixed[] $campaign_data Decoded campaign data array.
	 *
	 * @return string Rendered HTML.
	 */
	public static function render_description( array $campaign_data ): string {
		$campaign_data = self::normalize_campaign_data( $campaign_data );

		$layout    = $campaign_data['layout'] ?? array();
		$block_cfg = $layout['description'] ?? array();

		if ( empty( $block_cfg['enabled'] ) ) {
			return '';
		}

		$description = (string) ( $campaign_data['description'] ?? $campaign_data['body'] ?? '' );

		if ( '' === $description ) {
			return '';
		}

		ob_start();
		?>
		<div class="fundraisehub-campaign-description">
			<?php echo wp_kses_post( $description ); ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render the campaign donate button with an optional hidden iframe.
	 *
	 * @param mixed[] $campaign_data Decoded campaign data array.
	 * @param string  $api_url       Base URL of the remote FundRaiseHub API.
	 *
	 * @return string Rendered HTML.
	 */
	public static function render_donate_button( array $campaign_data, string $api_url = '' ): string {
		$campaign_data = self::normalize_campaign_data( $campaign_data );

		$layout    = $campaign_data['layout'] ?? array();
		$block_cfg = $layout['donate_button'] ?? array();

		if ( empty( $block_cfg['enabled'] ) ) {
			return '';
		}

		$campaign_id     = (string) ( $campaign_data['id'] ?? '' );
		$color_primary   = ltrim( (string) ( $campaign_data['colorPrimary'] ?? $campaign_data['color_primary'] ?? $layout['colorPrimary'] ?? '' ), '#' );
		$color_secondary = ltrim( (string) ( $campaign_data['colorSecondary'] ?? $campaign_data['color_secondary'] ?? $layout['colorSecondary'] ?? '' ), '#' );
		$button_label    = esc_html( (string) ( $block_cfg['label'] ?? $campaign_data['donate_button_label'] ?? __( 'Donate Now', 'fundraisehub-core' ) ) );

		$iframe_src = '';
		if ( $api_url && $campaign_id ) {
			$iframe_src = add_query_arg(
				array_filter(
					array(
						'color'     => $color_primary,
						'secondary' => $color_secondary,
						'origin'    => home_url(),
					)
				),
				rtrim( $api_url, '/' ) . '/embed/campaign/' . rawurlencode( $campaign_id )
			);
		}

		ob_start();
		?>
		<div class="fundraisehub-campaign-donate-button">
			<button
				type="button"
				class="fundraisehub-campaign-donate-button__btn"
			>
				<?php echo $button_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped above ?>
			</button>
			<?php if ( $iframe_src ) : ?>
			<iframe
				class="fundraisehub-donate-iframe"
				src="<?php echo esc_url( $iframe_src ); ?>"
				sandbox="allow-scripts allow-forms allow-same-origin allow-popups"
				allow="payment"
				title="<?php esc_attr_e( 'Donation form', 'fundraisehub-core' ); ?>"
				hidden
			></iframe>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render the campaign donation tiles with an optional hidden iframe.
	 *
	 * @param mixed[] $campaign_data Decoded campaign data array.
	 * @param string  $api_url       Base URL of the remote FundRaiseHub API.
	 *
	 * @return string Rendered HTML.
	 */
	public static function render_donation_tiles( array $campaign_data, string $api_url = '' ): string {
		$campaign_data = self::normalize_campaign_data( $campaign_data );

		$layout    = $campaign_data['layout'] ?? array();
		$block_cfg = $layout['donation_tiles'] ?? array();

		if ( empty( $block_cfg['enabled'] ) ) {
			return '';
		}

		$amounts = array();
		if ( ! empty( $campaign_data['donation_amounts'] ) && is_array( $campaign_data['donation_amounts'] ) ) {
			$amounts = $campaign_data['donation_amounts'];
		} elseif ( ! empty( $block_cfg['amounts'] ) && is_array( $block_cfg['amounts'] ) ) {
			$amounts = $block_cfg['amounts'];
		}

		if ( empty( $amounts ) ) {
			return '';
		}

		$campaign_id     = (string) ( $campaign_data['id'] ?? '' );
		$color_primary   = ltrim( (string) ( $campaign_data['colorPrimary'] ?? $campaign_data['color_primary'] ?? $layout['colorPrimary'] ?? '' ), '#' );
		$color_secondary = ltrim( (string) ( $campaign_data['colorSecondary'] ?? $campaign_data['color_secondary'] ?? $layout['colorSecondary'] ?? '' ), '#' );

		$iframe_src = '';
		if ( $api_url && $campaign_id ) {
			$iframe_src = add_query_arg(
				array_filter(
					array(
						'color'     => $color_primary,
						'secondary' => $color_secondary,
						'origin'    => home_url(),
					)
				),
				rtrim( $api_url, '/' ) . '/embed/campaign/' . rawurlencode( $campaign_id )
			);
		}

		ob_start();
		?>
		<div class="fundraisehub-campaign-donation-tiles">
			<?php foreach ( $amounts as $amount ) : ?>
				<button
					type="button"
					class="fundraisehub-campaign-donation-tiles__tile"
					data-amount="<?php echo esc_attr( (string) $amount ); ?>"
				>
					<?php echo esc_html( '$' . number_format( (float) $amount, 2 ) ); ?>
				</button>
			<?php endforeach; ?>
			<?php if ( $iframe_src ) : ?>
			<iframe
				class="fundraisehub-donate-iframe"
				src="<?php echo esc_url( $iframe_src ); ?>"
				sandbox="allow-scripts allow-forms allow-same-origin allow-popups"
				allow="payment"
				title="<?php esc_attr_e( 'Donation form', 'fundraisehub-core' ); ?>"
				hidden
			></iframe>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render the campaign honor scroll (donor list).
	 *
	 * @param mixed[] $campaign_data Decoded campaign data array.
	 *
	 * @return string Rendered HTML.
	 */
	public static function render_honor_scroll( array $campaign_data ): string {
		$campaign_data = self::normalize_campaign_data( $campaign_data );

		$layout    = $campaign_data['layout'] ?? array();
		$block_cfg = $layout['honor_scroll'] ?? array();

		if ( empty( $block_cfg['enabled'] ) ) {
			return '';
		}

		$donors = array();
		if ( ! empty( $campaign_data['donors'] ) && is_array( $campaign_data['donors'] ) ) {
			$donors = $campaign_data['donors'];
		} elseif ( ! empty( $campaign_data['recent_donors'] ) && is_array( $campaign_data['recent_donors'] ) ) {
			$donors = $campaign_data['recent_donors'];
		}

		if ( empty( $donors ) ) {
			return '';
		}

		ob_start();
		?>
		<div class="fundraisehub-campaign-honor-scroll">
			<ul class="fundraisehub-campaign-honor-scroll__list">
				<?php
				foreach ( $donors as $donor ) :
					$name   = esc_html( (string) ( $donor['name'] ?? $donor['donor_name'] ?? __( 'Anonymous', 'fundraisehub-core' ) ) );
					$amount = (float) ( $donor['amount'] ?? 0 );
					?>
					<li class="fundraisehub-campaign-honor-scroll__item">
						<span class="fundraisehub-campaign-honor-scroll__name"><?php echo $name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped ?></span>
						<?php if ( $amount > 0 ) : ?>
							<span class="fundraisehub-campaign-honor-scroll__amount"><?php echo esc_html( '$' . number_format( $amount, 2 ) ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render the campaign teams leaderboard.
	 *
	 * @param mixed[] $campaign_data Decoded campaign data array.
	 *
	 * @return string Rendered HTML.
	 */
	public static function render_teams( array $campaign_data ): string {
		$campaign_data = self::normalize_campaign_data( $campaign_data );

		$layout    = $campaign_data['layout'] ?? array();
		$block_cfg = $layout['teams'] ?? array();

		if ( empty( $block_cfg['enabled'] ) ) {
			return '';
		}

		$teams = array();
		if ( ! empty( $campaign_data['teams'] ) && is_array( $campaign_data['teams'] ) ) {
			$teams = $campaign_data['teams'];
		}

		if ( empty( $teams ) ) {
			return '';
		}

		ob_start();
		?>
		<div class="fundraisehub-campaign-teams">
			<ol class="fundraisehub-campaign-teams__list">
				<?php
				foreach ( $teams as $index => $team ) :
					$team_name   = esc_html( (string) ( $team['name'] ?? $team['team_name'] ?? '' ) );
					$team_raised = number_format( (float) ( $team['amount_raised'] ?? $team['raised'] ?? 0 ), 2 );
					?>
					<li class="fundraisehub-campaign-teams__item">
						<span class="fundraisehub-campaign-teams__rank"><?php echo esc_html( (string) ( $index + 1 ) ); ?></span>
						<span class="fundraisehub-campaign-teams__name"><?php echo $team_name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped ?></span>
						<span class="fundraisehub-campaign-teams__raised"><?php echo esc_html( '$' . $team_raised ); ?></span>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render the campaign video embed.
	 *
	 * @param mixed[] $campaign_data Decoded campaign data array.
	 *
	 * @return string Rendered HTML.
	 */
	public static function render_video( array $campaign_data ): string {
		$campaign_data = self::normalize_campaign_data( $campaign_data );

		$layout    = $campaign_data['layout'] ?? array();
		$block_cfg = $layout['video'] ?? array();

		if ( empty( $block_cfg['enabled'] ) ) {
			return '';
		}

		$video_url = (string) ( $campaign_data['video_url'] ?? $campaign_data['video'] ?? '' );

		if ( '' === $video_url ) {
			return '';
		}

		$embed = wp_oembed_get( $video_url );

		if ( ! $embed ) {
			$embed = '<iframe src="' . esc_url( $video_url ) . '" title="' . esc_attr__( 'Campaign video', 'fundraisehub-core' ) . '" allowfullscreen loading="lazy" class="fundraisehub-campaign-video__iframe"></iframe>';
		}

		ob_start();
		?>
		<div class="fundraisehub-campaign-video">
			<?php echo $embed; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_oembed_get is trusted ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render the campaign photo gallery.
	 *
	 * @param mixed[] $campaign_data Decoded campaign data array.
	 *
	 * @return string Rendered HTML.
	 */
	public static function render_photo_gallery( array $campaign_data ): string {
		$campaign_data = self::normalize_campaign_data( $campaign_data );

		$layout    = $campaign_data['layout'] ?? array();
		$block_cfg = $layout['photo_gallery'] ?? array();

		if ( empty( $block_cfg['enabled'] ) ) {
			return '';
		}

		$images = array();
		if ( ! empty( $campaign_data['gallery_images'] ) && is_array( $campaign_data['gallery_images'] ) ) {
			$images = $campaign_data['gallery_images'];
		} elseif ( ! empty( $campaign_data['images'] ) && is_array( $campaign_data['images'] ) ) {
			$images = $campaign_data['images'];
		}

		if ( empty( $images ) ) {
			return '';
		}

		ob_start();
		?>
		<div class="fundraisehub-campaign-photo-gallery">
			<ul class="fundraisehub-campaign-photo-gallery__grid">
				<?php
				foreach ( $images as $image ) :
					if ( is_string( $image ) ) {
						$src = $image;
						$alt = '';
					} else {
						$src = (string) ( $image['url'] ?? $image['src'] ?? '' );
						$alt = (string) ( $image['alt'] ?? $image['caption'] ?? '' );
					}

					if ( ! $src ) {
						continue;
					}
					?>
					<li class="fundraisehub-campaign-photo-gallery__item">
						<img
							src="<?php echo esc_url( $src ); ?>"
							alt="<?php echo esc_attr( $alt ); ?>"
							class="fundraisehub-campaign-photo-gallery__img"
							loading="lazy"
						/>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render the campaign comments list.
	 *
	 * @param mixed[] $campaign_data Decoded campaign data array.
	 *
	 * @return string Rendered HTML.
	 */
	public static function render_comments( array $campaign_data ): string {
		$campaign_data = self::normalize_campaign_data( $campaign_data );

		$layout    = $campaign_data['layout'] ?? array();
		$block_cfg = $layout['comments'] ?? array();

		if ( empty( $block_cfg['enabled'] ) ) {
			return '';
		}

		$campaign_comments = array();
		if ( ! empty( $campaign_data['comments'] ) && is_array( $campaign_data['comments'] ) ) {
			$campaign_comments = $campaign_data['comments'];
		}

		if ( empty( $campaign_comments ) ) {
			return '';
		}

		ob_start();
		?>
		<div class="fundraisehub-campaign-comments">
			<ul class="fundraisehub-campaign-comments__list">
				<?php
				foreach ( $campaign_comments as $campaign_comment ) :
					$author  = esc_html( (string) ( $campaign_comment['author'] ?? $campaign_comment['name'] ?? __( 'Anonymous', 'fundraisehub-core' ) ) );
					$message = wp_kses_post( (string) ( $campaign_comment['message'] ?? $campaign_comment['body'] ?? $campaign_comment['comment'] ?? '' ) );
					if ( '' === $message ) {
						continue;
					}
					?>
					<li class="fundraisehub-campaign-comments__item">
						<span class="fundraisehub-campaign-comments__author"><?php echo $author; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped ?></span>
						<div class="fundraisehub-campaign-comments__message"><?php echo $message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post applied ?></div>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
