( () => {
	'use strict';
	const n = window.wp.blocks,
		e = JSON.parse( '{"UU":"fundraisehub/campaign-comments"}' ),
		i = window.wp.i18n,
		o = window.wp.blockEditor,
		s = window.wp.components,
		r = window.ReactJSXRuntime;
	( 0, n.registerBlockType )( e.UU, {
		edit() {
			const n = ( 0, o.useBlockProps )(),
				e = window.fundraisehubData?.apiKeyConfigured ?? ! 1;
			return ( 0, r.jsx )( 'div', {
				...n,
				children: ( 0, r.jsx )( s.Placeholder, {
					icon: e ? 'admin-comments' : 'lock',
					label: ( 0, i.__ )(
						'Campaign Comments',
						'fundraisehub-core'
					),
					instructions: e
						? ( 0, i.__ )(
								'Campaign comment wall will appear here on the front end.',
								'fundraisehub-core'
						  )
						: ( 0, i.__ )(
								'Configure your FundRaiseHub API key in Settings to use this block.',
								'fundraisehub-core'
						  ),
				} ),
			} );
		},
		save: () => null,
	} );
} )();
