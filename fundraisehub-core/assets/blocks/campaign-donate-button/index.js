( () => {
	'use strict';
	const n = window.wp.blocks,
		e = JSON.parse( '{"UU":"fundraisehub/campaign-donate-button"}' ),
		o = window.wp.i18n,
		i = window.wp.blockEditor,
		t = window.wp.components,
		r = window.ReactJSXRuntime;
	( 0, n.registerBlockType )( e.UU, {
		edit() {
			const n = ( 0, i.useBlockProps )(),
				e = window.fundraisehubData?.apiKeyConfigured ?? ! 1;
			return ( 0, r.jsx )( 'div', {
				...n,
				children: ( 0, r.jsx )( t.Placeholder, {
					icon: e ? 'heart' : 'lock',
					label: ( 0, o.__ )(
						'Campaign Donate Button',
						'fundraisehub-core'
					),
					instructions: e
						? ( 0, o.__ )(
								'Donation button will appear here on the front end.',
								'fundraisehub-core'
						  )
						: ( 0, o.__ )(
								'Configure your FundRaiseHub API key in Settings to use this block.',
								'fundraisehub-core'
						  ),
				} ),
			} );
		},
		save: () => null,
	} );
} )();
