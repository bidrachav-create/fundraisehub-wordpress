import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';
import { lock } from '@wordpress/icons';

export default function Edit() {
	const blockProps = useBlockProps();
	const apiKeyConfigured =
		window.fundraisehubData?.apiKeyConfigured ?? false;

	return (
		<div { ...blockProps }>
			<Placeholder
				icon={ apiKeyConfigured ? 'video-alt3' : lock }
				label={ __( 'Campaign Video', 'fundraisehub-core' ) }
				instructions={
					apiKeyConfigured
						? __(
								'Campaign video will appear here on the front end.',
								'fundraisehub-core'
						  )
						: __(
								'Configure your FundRaiseHub API key in Settings to use this block.',
								'fundraisehub-core'
						  )
				}
			/>
		</div>
	);
}
