/**
 * Front-end script for the Campaign Donation Tiles block.
 * Highlights the selected tile and opens the FundRaiseHub donation form in an iframe.
 */

document.querySelectorAll( '.fundraisehub-campaign-donation-tiles' ).forEach( ( wrapper ) => {
	const tiles = wrapper.querySelectorAll( '.fundraisehub-campaign-donation-tiles__tile' );

	tiles.forEach( ( tile ) => {
		tile.addEventListener( 'click', () => {
			tiles.forEach( ( t ) => t.classList.remove( 'is-selected' ) );
			tile.classList.add( 'is-selected' );

			const amount       = tile.dataset.amount ?? '';
			const apiUrl       = wrapper.dataset.apiUrl ?? '';
			const campaignSlug = wrapper.dataset.campaignSlug ?? '';
			const orgSlug      = wrapper.dataset.orgSlug ?? '';

			if ( ! apiUrl ) {
				return;
			}

			const params   = new URLSearchParams( { amount } );
			const donateUrl =
				apiUrl.replace( /\/$/, '' ) +
				'/donate/' +
				orgSlug +
				'/' +
				campaignSlug +
				'?' +
				params.toString();

			openDonationOverlay( donateUrl );
		} );
	} );
} );

/**
 * Create and open a full-screen iframe overlay for the donation form.
 *
 * @param {string} url Donation form URL.
 */
function openDonationOverlay( url ) {
	const overlay = document.createElement( 'div' );
	overlay.className = 'fundraisehub-donation-overlay';
	overlay.setAttribute( 'role', 'dialog' );
	overlay.setAttribute( 'aria-modal', 'true' );
	overlay.setAttribute( 'aria-label', 'Donation form' );

	const closeBtn = document.createElement( 'button' );
	closeBtn.className = 'fundraisehub-donation-overlay__close';
	closeBtn.setAttribute( 'aria-label', 'Close donation form' );
	closeBtn.textContent = '×';
	closeBtn.addEventListener( 'click', () => overlay.remove() );

	const iframe = document.createElement( 'iframe' );
	iframe.src = url;
	iframe.className = 'fundraisehub-donation-overlay__iframe';
	iframe.setAttribute( 'title', 'Donation form' );
	iframe.setAttribute( 'allowpaymentrequest', '' );

	overlay.appendChild( closeBtn );
	overlay.appendChild( iframe );
	document.body.appendChild( overlay );
	closeBtn.focus();

	overlay.addEventListener( 'keydown', ( e ) => {
		if ( 'Escape' === e.key ) {
			overlay.remove();
		}
	} );
}
