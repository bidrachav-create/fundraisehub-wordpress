( () => {
	'use strict';
	const e = window.wp.blocks,
		n = JSON.parse( '{"UU":"fundraisehub/campaign-thermometer"}' ),
		o = window.wp.i18n,
		i = window.wp.blockEditor,
		r = window.wp.components,
		s = window.ReactJSXRuntime;
	( 0, e.registerBlockType )( n.UU, {
		edit() {
			const e = ( 0, i.useBlockProps )(),
				n = window.fundraisehubData?.apiKeyConfigured ?? ! 1;
			return ( 0, s.jsx )( 'div', {
				...e,
				children: ( 0, s.jsx )( r.Placeholder, {
					icon: n ? 'performance' : 'lock',
					label: ( 0, o.__ )(
						'Campaign Thermometer',
						'fundraisehub-core'
					),
					instructions: n
						? ( 0, o.__ )(
								'Campaign progress bar will appear here on the front end.',
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
