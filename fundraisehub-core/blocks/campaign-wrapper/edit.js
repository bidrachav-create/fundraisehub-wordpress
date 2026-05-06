import { __ } from '@wordpress/i18n';
import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';

const ALLOWED_BLOCKS = [
	'fundraisehub/campaign-banner',
	'fundraisehub/campaign-stats-bar',
	'fundraisehub/campaign-thermometer',
	'fundraisehub/campaign-description',
	'fundraisehub/campaign-donate-button',
	'fundraisehub/campaign-donation-tiles',
	'fundraisehub/campaign-honor-scroll',
	'fundraisehub/campaign-teams',
	'fundraisehub/campaign-video',
	'fundraisehub/campaign-photo-gallery',
	'fundraisehub/campaign-comments',
];

export default function Edit() {
	const blockProps = useBlockProps( {
		className: 'fundraisehub-campaign-wrapper',
	} );

	const apiKeyConfigured =
		window.fundraisehubData?.apiKeyConfigured ?? false;

	if ( ! apiKeyConfigured ) {
		return (
			<div { ...blockProps }>
				<Placeholder
					icon="lock"
					label={ __( 'FundRaiseHub Campaign', 'fundraisehub-core' ) }
					instructions={ __(
						'Configure your FundRaiseHub API key in Settings → FundRaiseHub to use this block.',
						'fundraisehub-core'
					) }
				/>
			</div>
		);
	}

	return (
		<div { ...blockProps }>
			<InnerBlocks allowedBlocks={ ALLOWED_BLOCKS } />
		</div>
	);
}
