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
				icon={ apiKeyConfigured ? 'groups' : lock }
				label={ __( 'Campaign Teams', 'fundraisehub-core' ) }
				instructions={
					apiKeyConfigured
						? __(
								'Teams leaderboard will appear here on the front end.',
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
