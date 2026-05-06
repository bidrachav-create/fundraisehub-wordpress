<?php
/**
 * Server-side render for the campaign-teams block.
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
$block_cfg = $layout['teams'] ?? array();

if ( empty( $block_cfg['enabled'] ) ) {
	return;
}

$teams = array();
if ( ! empty( $campaign_data['teams'] ) && is_array( $campaign_data['teams'] ) ) {
	$teams = $campaign_data['teams'];
}

if ( empty( $teams ) ) {
	return;
}

?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'fundraisehub-campaign-teams' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
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
