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

$campaign_id     = (string) ( $campaign_data['id'] ?? '' );
$color_primary   = ltrim( (string) ( $campaign_data['colorPrimary'] ?? $campaign_data['color_primary'] ?? $layout['colorPrimary'] ?? '' ), '#' );
$color_secondary = ltrim( (string) ( $campaign_data['colorSecondary'] ?? $campaign_data['color_secondary'] ?? $layout['colorSecondary'] ?? '' ), '#' );
$button_label    = esc_html( (string) ( $block_cfg['label'] ?? $campaign_data['donate_button_label'] ?? __( 'Donate Now', 'fundraisehub-core' ) ) );

// Build the iframe embed URL.
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
} elseif ( current_user_can( 'edit_posts' ) ) {
	// Emit an admin-only notice so editors know why the donation form is missing.
	$frhub_missing = '' === $api_url
		? esc_html__( 'No API URL is configured.', 'fundraisehub-core' )
		: esc_html__( 'No campaign ID found in the campaign data.', 'fundraisehub-core' );
	echo '<div class="fundraisehub-block-notice" style="border:1px solid #dba617;background:#fff8e1;padding:1em;border-radius:4px;">';
	echo '<strong>' . esc_html__( 'FundRaiseHub – Donate Button: embed unavailable', 'fundraisehub-core' ) . '</strong> ' . $frhub_missing; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped
	echo '</div>';
}

?>
<div
	<?php echo get_block_wrapper_attributes( array( 'class' => 'fundraisehub-campaign-donate-button' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
>
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
