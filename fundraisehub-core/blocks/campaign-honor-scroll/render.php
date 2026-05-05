<?php
/**
 * Server-side render for the campaign-honor-scroll block.
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
$block_cfg = $layout['honor_scroll'] ?? array();

if ( empty( $block_cfg['enabled'] ) ) {
	return;
}

$donors = array();
if ( ! empty( $campaign_data['donors'] ) && is_array( $campaign_data['donors'] ) ) {
	$donors = $campaign_data['donors'];
} elseif ( ! empty( $campaign_data['recent_donors'] ) && is_array( $campaign_data['recent_donors'] ) ) {
	$donors = $campaign_data['recent_donors'];
}

if ( empty( $donors ) ) {
	return;
}

?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'fundraisehub-campaign-honor-scroll' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<ul class="fundraisehub-campaign-honor-scroll__list">
		<?php foreach ( $donors as $donor ) :
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
