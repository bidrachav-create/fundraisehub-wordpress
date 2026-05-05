<?php
/**
 * Server-side render for the campaign-thermometer block.
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
$block_cfg = $layout['thermometer'] ?? array();

if ( empty( $block_cfg['enabled'] ) ) {
	return;
}

$raised  = (float) ( $campaign_data['amount_raised'] ?? $campaign_data['raised'] ?? 0 );
$goal    = (float) ( $campaign_data['goal_amount'] ?? $campaign_data['goal'] ?? 0 );
$percent = $goal > 0 ? min( 100, round( ( $raised / $goal ) * 100, 1 ) ) : 0;

?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'fundraisehub-campaign-thermometer' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
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
