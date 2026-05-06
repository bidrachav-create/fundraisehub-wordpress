( () => {
	'use strict';
	const n = window.wp.blocks,
		i = JSON.parse( '{"UU":"fundraisehub/campaign-donation-tiles"}' ),
		e = window.wp.i18n,
		o = window.wp.blockEditor,
		s = window.wp.components,
		t = window.ReactJSXRuntime;
	( 0, n.registerBlockType )( i.UU, {
		edit() {
			const n = ( 0, o.useBlockProps )(),
				i = window.fundraisehubData?.apiKeyConfigured ?? ! 1;
			return ( 0, t.jsx )( 'div', {
				...n,
				children: ( 0, t.jsx )( s.Placeholder, {
					icon: i ? 'grid-view' : 'lock',
					label: ( 0, e.__ )(
						'Campaign Donation Tiles',
						'fundraisehub-core'
					),
					instructions: i
						? ( 0, e.__ )(
								'Donation amount tiles will appear here on the front end.',
								'fundraisehub-core'
						  )
						: ( 0, e.__ )(
								'Configure your FundRaiseHub API key in Settings to use this block.',
								'fundraisehub-core'
						  ),
				} ),
			} );
		},
		save: () => null,
	} );
} )();
