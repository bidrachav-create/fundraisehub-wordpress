<?php
/**
 * Server-side render for the campaign-wrapper block.
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

$frhub_post_id = get_the_ID();
$campaign_data = get_post_meta( $frhub_post_id, '_fundraisehub_campaign_data', true );

if ( ! $campaign_data ) {
	// Show an actionable notice to logged-in editors; return silently on the
	// public-facing front end to avoid leaking admin details to visitors.
	if ( current_user_can( 'edit_posts' ) ) {
		$frhub_settings_url = admin_url( 'options-general.php?page=fundraisehub-settings' );
		echo '<div class="fundraisehub-block-notice" style="border:1px solid #dba617;background:#fff8e1;padding:1em;border-radius:4px;">';
		echo '<strong>' . esc_html__( 'FundRaiseHub – No Campaign Data', 'fundraisehub-core' ) . '</strong><br />';
		echo esc_html__( 'This block requires a synced campaign post. Either:', 'fundraisehub-core' );
		echo '<ul style="margin:.5em 0 0 1.5em;list-style:disc;">';
		echo '<li>' . esc_html__( 'Place this block on a fundraisehub_campaign post (the campaign data is read from post meta).', 'fundraisehub-core' ) . '</li>';
		echo '<li>';
		printf(
			wp_kses(
				/* translators: %s: URL to the settings/sync page */
				__( 'Or run a <a href="%s">campaign sync</a> so the post has FundRaiseHub data.', 'fundraisehub-core' ),
				array(
					'a' => array( 'href' => array() ),
				)
			),
			esc_url( $frhub_settings_url )
		);
		echo '</li>';
		echo '</ul></div>';
	}
	return;
}

?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'fundraisehub-campaign-wrapper' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</div>
