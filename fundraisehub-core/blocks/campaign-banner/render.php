<?php
/**
 * Server-side render for the campaign-banner block.
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

$layout    = $campaign_data['layout'] ?? array();
$block_cfg = $layout['banner'] ?? array();

if ( empty( $block_cfg['enabled'] ) ) {
	return;
}

$banner_url     = (string) ( $campaign_data['banner_url'] ?? $campaign_data['banner_image'] ?? '' );
$campaign_title = (string) ( $campaign_data['name'] ?? $campaign_data['title'] ?? '' );

if ( ! $banner_url ) {
	return;
}

?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'fundraisehub-campaign-banner' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<img
		src="<?php echo esc_url( $banner_url ); ?>"
		alt="<?php echo esc_attr( $campaign_title ); ?>"
		class="fundraisehub-campaign-banner__image"
	/>
</div>
