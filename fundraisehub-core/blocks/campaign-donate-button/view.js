/**
 * Front-end script for the Campaign Donate Button block.
 * Opens the FundRaiseHub donation form in an iframe overlay when clicked.
 */

document.querySelectorAll( '.fundraisehub-campaign-donate-button' ).forEach( ( wrapper ) => {
	const button = wrapper.querySelector( '.fundraisehub-campaign-donate-button__btn' );
	if ( ! button ) {
		return;
	}

	button.addEventListener( 'click', () => {
		const apiUrl       = wrapper.dataset.apiUrl ?? '';
		const campaignSlug = wrapper.dataset.campaignSlug ?? '';
		const orgSlug      = wrapper.dataset.orgSlug ?? '';

		if ( ! apiUrl ) {
			return;
		}

		const donateUrl = apiUrl.replace( /\/$/, '' ) + '/donate/' + orgSlug + '/' + campaignSlug;
		openDonationOverlay( donateUrl );
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
