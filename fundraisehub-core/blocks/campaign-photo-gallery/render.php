<?php
/**
 * Server-side render for the campaign-photo-gallery block.
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
$block_cfg = $layout['photo_gallery'] ?? array();

if ( empty( $block_cfg['enabled'] ) ) {
	return;
}

$images = array();
if ( ! empty( $campaign_data['gallery_images'] ) && is_array( $campaign_data['gallery_images'] ) ) {
	$images = $campaign_data['gallery_images'];
} elseif ( ! empty( $campaign_data['images'] ) && is_array( $campaign_data['images'] ) ) {
	$images = $campaign_data['images'];
}

if ( empty( $images ) ) {
	return;
}

?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'fundraisehub-campaign-photo-gallery' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<ul class="fundraisehub-campaign-photo-gallery__grid">
		<?php
		foreach ( $images as $image ) :
			if ( is_string( $image ) ) {
				$src = esc_url( $image );
				$alt = '';
			} else {
				$src = esc_url( (string) ( $image['url'] ?? $image['src'] ?? '' ) );
				$alt = esc_attr( (string) ( $image['alt'] ?? $image['caption'] ?? '' ) );
			}
			?>
			<?php if ( $src ) : ?>
				<li class="fundraisehub-campaign-photo-gallery__item">
					<img
						src="<?php echo esc_url( $src ); ?>"
						alt="<?php echo esc_attr( $alt ); ?>"
						loading="lazy"
						class="fundraisehub-campaign-photo-gallery__image"
					/>
				</li>
			<?php endif; ?>
		<?php endforeach; ?>
	</ul>
</div>
