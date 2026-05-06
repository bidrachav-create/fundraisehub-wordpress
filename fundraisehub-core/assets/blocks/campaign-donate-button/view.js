( () => {
	'use strict';
	const e = window.wp.i18n;
	document
		.querySelectorAll( '.fundraisehub-campaign-donate-button' )
		.forEach( ( t ) => {
			const n = t.querySelector(
				'.fundraisehub-campaign-donate-button__btn'
			);
			n &&
				n.addEventListener( 'click', () => {
					const a = t.dataset.apiUrl ?? '',
						o = t.dataset.campaignSlug ?? '',
						i = t.dataset.orgSlug ?? '';
					a &&
						( function ( t, n ) {
							const a = document.createElement( 'div' );
							( a.className = 'fundraisehub-donation-overlay' ),
								a.setAttribute( 'role', 'dialog' ),
								a.setAttribute( 'aria-modal', 'true' ),
								a.setAttribute(
									'aria-label',
									( 0, e.__ )(
										'Donation form',
										'fundraisehub-core'
									)
								);
							const o = document.createElement( 'button' );
							( o.className =
								'fundraisehub-donation-overlay__close' ),
								o.setAttribute(
									'aria-label',
									( 0, e.__ )(
										'Close donation form',
										'fundraisehub-core'
									)
								),
								( o.textContent = '×' );
							const i = document.createElement( 'iframe' );
							function c() {
								a.remove(),
									document.removeEventListener(
										'focusin',
										s
									),
									n && n.focus();
							}
							( i.src = t ),
								( i.className =
									'fundraisehub-donation-overlay__iframe' ),
								i.setAttribute(
									'title',
									( 0, e.__ )(
										'Donation form',
										'fundraisehub-core'
									)
								),
								i.setAttribute( 'allowpaymentrequest', '' ),
								a.appendChild( o ),
								a.appendChild( i ),
								document.body.appendChild( a ),
								o.addEventListener( 'click', c ),
								o.focus();
							const r = [ o, i ];
							function s( e ) {
								a.contains( e.target ) || o.focus();
							}
							document.addEventListener( 'focusin', s ),
								a.addEventListener( 'keydown', ( e ) => {
									if ( 'Escape' !== e.key ) {
										if ( 'Tab' === e.key ) {
											const t = r[ 0 ],
												n = r[ r.length - 1 ];
											e.shiftKey &&
											document.activeElement === t
												? ( e.preventDefault(),
												  n.focus() )
												: e.shiftKey ||
												  document.activeElement !==
														n ||
												  ( e.preventDefault(),
												  t.focus() );
										}
									} else {
										c();
									}
								} );
						} )(
							a.replace( /\/$/, '' ) + '/donate/' + i + '/' + o,
							n
						);
				} );
		} );
} )();
