/**
 * Front-end script for the Campaign Donate Button block.
 * Opens the FundRaiseHub donation form in an iframe overlay when clicked.
 */

import { __ } from '@wordpress/i18n';

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
		openDonationOverlay( donateUrl, button );
	} );
} );

/**
 * Create and open a full-screen iframe overlay for the donation form.
 * Traps keyboard focus inside the overlay and restores it on close.
 *
 * @param {string}      url     Donation form URL.
 * @param {HTMLElement} trigger Element that triggered the overlay (focus is restored here on close).
 */
function openDonationOverlay( url, trigger ) {
	const overlay = document.createElement( 'div' );
	overlay.className = 'fundraisehub-donation-overlay';
	overlay.setAttribute( 'role', 'dialog' );
	overlay.setAttribute( 'aria-modal', 'true' );
	overlay.setAttribute( 'aria-label', __( 'Donation form', 'fundraisehub-core' ) );

	const closeBtn = document.createElement( 'button' );
	closeBtn.className = 'fundraisehub-donation-overlay__close';
	closeBtn.setAttribute( 'aria-label', __( 'Close donation form', 'fundraisehub-core' ) );
	closeBtn.textContent = '×';

	const iframe = document.createElement( 'iframe' );
	iframe.src = url;
	iframe.className = 'fundraisehub-donation-overlay__iframe';
	iframe.setAttribute( 'title', __( 'Donation form', 'fundraisehub-core' ) );
	iframe.setAttribute( 'allowpaymentrequest', '' );

	overlay.appendChild( closeBtn );
	overlay.appendChild( iframe );
	document.body.appendChild( overlay );

	function closeOverlay() {
		overlay.remove();
		document.removeEventListener( 'focusin', trapFocus );
		if ( trigger ) {
			trigger.focus();
		}
	}

	closeBtn.addEventListener( 'click', closeOverlay );

	// Focus the close button after mounting.
	closeBtn.focus();

	// Trap focus: only the close button and the iframe are focusable.
	const focusableElements = [ closeBtn, iframe ];

	function trapFocus( e ) {
		if ( ! overlay.contains( e.target ) ) {
			closeBtn.focus();
		}
	}

	document.addEventListener( 'focusin', trapFocus );

	// Handle Tab key to cycle between focusable elements.
	overlay.addEventListener( 'keydown', ( e ) => {
		if ( 'Escape' === e.key ) {
			closeOverlay();
			return;
		}

		if ( 'Tab' === e.key ) {
			const first = focusableElements[ 0 ];
			const last  = focusableElements[ focusableElements.length - 1 ];

			if ( e.shiftKey && document.activeElement === first ) {
				e.preventDefault();
				last.focus();
			} else if ( ! e.shiftKey && document.activeElement === last ) {
				e.preventDefault();
				first.focus();
			}
		}
	} );
}
