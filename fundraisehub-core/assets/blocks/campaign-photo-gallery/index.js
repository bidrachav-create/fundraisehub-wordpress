( () => {
	'use strict';
	const e = window.wp.blocks,
		o = JSON.parse( '{"UU":"fundraisehub/campaign-photo-gallery"}' ),
		n = window.wp.i18n,
		i = window.wp.blockEditor,
		r = window.wp.components,
		a = window.ReactJSXRuntime;
	( 0, e.registerBlockType )( o.UU, {
		edit() {
			const e = ( 0, i.useBlockProps )(),
				o = window.fundraisehubData?.apiKeyConfigured ?? ! 1;
			return ( 0, a.jsx )( 'div', {
				...e,
				children: ( 0, a.jsx )( r.Placeholder, {
					icon: o ? 'format-gallery' : 'lock',
					label: ( 0, n.__ )(
						'Campaign Photo Gallery',
						'fundraisehub-core'
					),
					instructions: o
						? ( 0, n.__ )(
								'Campaign photo gallery will appear here on the front end.',
								'fundraisehub-core'
						  )
						: ( 0, n.__ )(
								'Configure your FundRaiseHub API key in Settings to use this block.',
								'fundraisehub-core'
						  ),
				} ),
			} );
		},
		save: () => null,
	} );
} )();
