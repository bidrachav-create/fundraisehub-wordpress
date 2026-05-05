<?php
/**
 * Server-side render for the campaign-donation-tiles block.
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
$api_url            = (string) ( $block->context['fundraisehub/api-url'] ?? '' );

if ( ! is_array( $campaign_data ) ) {
	return;
}

$layout    = $campaign_data['layout'] ?? array();
$block_cfg = $layout['donation_tiles'] ?? array();

if ( empty( $block_cfg['enabled'] ) ) {
	return;
}

$amounts = array();
if ( ! empty( $campaign_data['donation_amounts'] ) && is_array( $campaign_data['donation_amounts'] ) ) {
	$amounts = $campaign_data['donation_amounts'];
} elseif ( ! empty( $block_cfg['amounts'] ) && is_array( $block_cfg['amounts'] ) ) {
	$amounts = $block_cfg['amounts'];
}

if ( empty( $amounts ) ) {
	return;
}

$campaign_slug = esc_attr( (string) ( $campaign_data['slug'] ?? $campaign_data['campaign_slug'] ?? '' ) );
$org_slug      = esc_attr( (string) ( $campaign_data['org_slug'] ?? $campaign_data['organisation_slug'] ?? '' ) );

?>
<div
	<?php echo get_block_wrapper_attributes( array( 'class' => 'fundraisehub-campaign-donation-tiles' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	data-api-url="<?php echo esc_attr( $api_url ); ?>"
	data-campaign-slug="<?php echo esc_attr( $campaign_slug ); ?>"
	data-org-slug="<?php echo esc_attr( $org_slug ); ?>"
>
	<?php foreach ( $amounts as $amount ) : ?>
		<button
			type="button"
			class="fundraisehub-campaign-donation-tiles__tile"
			data-amount="<?php echo esc_attr( (string) $amount ); ?>"
		>
			<?php echo esc_html( '$' . number_format( (float) $amount, 2 ) ); ?>
		</button>
	<?php endforeach; ?>
</div>
