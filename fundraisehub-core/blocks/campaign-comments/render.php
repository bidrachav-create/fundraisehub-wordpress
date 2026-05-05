<?php
/**
 * Server-side render for the campaign-comments block.
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
$block_cfg = $layout['comments'] ?? array();

if ( empty( $block_cfg['enabled'] ) ) {
	return;
}

$comments = array();
if ( ! empty( $campaign_data['comments'] ) && is_array( $campaign_data['comments'] ) ) {
	$comments = $campaign_data['comments'];
}

if ( empty( $comments ) ) {
	return;
}

?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'fundraisehub-campaign-comments' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<ul class="fundraisehub-campaign-comments__list">
		<?php foreach ( $comments as $comment ) :
			$author  = esc_html( (string) ( $comment['author'] ?? $comment['name'] ?? __( 'Anonymous', 'fundraisehub-core' ) ) );
			$message = wp_kses_post( (string) ( $comment['message'] ?? $comment['body'] ?? $comment['comment'] ?? '' ) );
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
