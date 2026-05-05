<?php
/**
 * Server-side render for the campaign-video block.
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
$block_cfg = $layout['video'] ?? array();

if ( empty( $block_cfg['enabled'] ) ) {
	return;
}

$video_url = (string) ( $campaign_data['video_url'] ?? $campaign_data['video'] ?? '' );

if ( '' === $video_url ) {
	return;
}

$embed = wp_oembed_get( $video_url );

if ( ! $embed ) {
	// Fallback to a simple iframe for direct video URLs.
	$embed = '<iframe src="' . esc_url( $video_url ) . '" title="' . esc_attr__( 'Campaign video', 'fundraisehub-core' ) . '" allowfullscreen loading="lazy" class="fundraisehub-campaign-video__iframe"></iframe>';
}

?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'fundraisehub-campaign-video' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php echo $embed; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_oembed_get is trusted ?>
</div>
