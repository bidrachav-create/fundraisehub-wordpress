<?php
/**
 * Server-side render for the campaign-donate-button block.
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
$block_cfg = $layout['donate_button'] ?? array();

if ( empty( $block_cfg['enabled'] ) ) {
	return;
}

$campaign_slug = esc_attr( (string) ( $campaign_data['slug'] ?? $campaign_data['campaign_slug'] ?? '' ) );
$org_slug      = esc_attr( (string) ( $campaign_data['org_slug'] ?? $campaign_data['organisation_slug'] ?? '' ) );
$button_label  = esc_html( (string) ( $block_cfg['label'] ?? $campaign_data['donate_button_label'] ?? __( 'Donate Now', 'fundraisehub-core' ) ) );

?>
<div
	<?php echo get_block_wrapper_attributes( array( 'class' => 'fundraisehub-campaign-donate-button' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	data-api-url="<?php echo esc_attr( $api_url ); ?>"
	data-campaign-slug="<?php echo esc_attr( $campaign_slug ); ?>"
	data-org-slug="<?php echo esc_attr( $org_slug ); ?>"
>
	<button
		type="button"
		class="fundraisehub-campaign-donate-button__btn"
	>
		<?php echo $button_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped above ?>
	</button>
</div>
