<?php
/**
 * Server-side render for the campaign-stats-bar block.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner blocks.
 * @var WP_Block $block      Block instance.
 *
 * @package FundRaiseHub\Core
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$campaign_data_json = $block->context['fundraisehub/campaign-data'] ?? '';
$campaign_data      = $campaign_data_json ? json_decode( $campaign_data_json, true ) : array();

if ( ! is_array( $campaign_data ) ) {
	return;
}

$campaign_data = \FundRaiseHub\Core\CampaignSync::normalize_campaign_payload( $campaign_data );

$layout    = $campaign_data['layout'] ?? array();
$block_cfg = $layout['stats_bar'] ?? array();

if ( empty( $block_cfg['enabled'] ) ) {
	return;
}

$amount_raised = $campaign_data['amount_raised'] ?? $campaign_data['raised'] ?? 0;
$donor_count   = (int) ( $campaign_data['donor_count'] ?? $campaign_data['donorCount'] ?? $campaign_data['totalDonors'] ?? $campaign_data['total_donors'] ?? $campaign_data['donorsCount'] ?? 0 );
$goal_amount   = $campaign_data['goal_amount'] ?? $campaign_data['goal'] ?? 0;

?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'fundraisehub-campaign-stats-bar' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="fundraisehub-campaign-stats-bar__item">
		<span class="fundraisehub-campaign-stats-bar__value"><?php echo esc_html( \FundRaiseHub\Core\CampaignRenderer::format_money( $amount_raised, $campaign_data ) ); ?></span>
		<span class="fundraisehub-campaign-stats-bar__label"><?php esc_html_e( 'Raised', 'fundraisehub-core' ); ?></span>
	</div>
	<div class="fundraisehub-campaign-stats-bar__item">
		<span class="fundraisehub-campaign-stats-bar__value"><?php echo esc_html( (string) $donor_count ); ?></span>
		<span class="fundraisehub-campaign-stats-bar__label"><?php esc_html_e( 'Donors', 'fundraisehub-core' ); ?></span>
	</div>
	<div class="fundraisehub-campaign-stats-bar__item">
		<span class="fundraisehub-campaign-stats-bar__value"><?php echo esc_html( \FundRaiseHub\Core\CampaignRenderer::format_money( $goal_amount, $campaign_data ) ); ?></span>
		<span class="fundraisehub-campaign-stats-bar__label"><?php esc_html_e( 'Goal', 'fundraisehub-core' ); ?></span>
	</div>
</div>
