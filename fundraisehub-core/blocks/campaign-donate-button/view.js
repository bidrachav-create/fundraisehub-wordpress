/**
 * Front-end script for the Campaign Donate Button block.
 * Opens the FundRaiseHub donation form in an iframe overlay when clicked.
 */

import { __ } from '@wordpress/i18n';

document
	.querySelectorAll( '.fundraisehub-campaign-donate-button' )
	.forEach( ( wrapper ) => {
		const button = wrapper.querySelector(
			'.fundraisehub-campaign-donate-button__btn'
		);
		if ( ! button ) {
			return;
		}

		button.addEventListener( 'click', () => {
			// Use the pre-rendered hidden iframe as a template so the URL,
			// sandbox, and allow attributes are always sourced from render.php.
			const template = wrapper.querySelector(
				'iframe.fundraisehub-donate-iframe'
			);
			if ( ! template ) {
				return;
			}

			const iframe = /** @type {HTMLIFrameElement} */ (
				template.cloneNode( true )
			);
			iframe.removeAttribute( 'hidden' );

			openDonationOverlay( iframe, button );
		} );
	} );

/**
 * Create and open a full-screen iframe overlay for the donation form.
 * Traps keyboard focus inside the overlay and restores it on close.
 *
 * @param {HTMLIFrameElement} iframe  Configured iframe element to embed.
 * @param {HTMLElement}       trigger Element that triggered the overlay (focus is restored here on close).
 */
function openDonationOverlay( iframe, trigger ) {
	const overlay = document.createElement( 'div' );
	overlay.className = 'fundraisehub-donation-overlay';
	overlay.setAttribute( 'role', 'dialog' );
	overlay.setAttribute( 'aria-modal', 'true' );
	overlay.setAttribute(
		'aria-label',
		__( 'Donation form', 'fundraisehub-core' )
	);

	const closeBtn = document.createElement( 'button' );
	closeBtn.className = 'fundraisehub-donation-overlay__close';
	closeBtn.setAttribute(
		'aria-label',
		__( 'Close donation form', 'fundraisehub-core' )
	);
	closeBtn.textContent = '×';

	iframe.classList.add( 'fundraisehub-donation-overlay__iframe' );
	iframe.setAttribute( 'title', __( 'Donation form', 'fundraisehub-core' ) );

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
			const last = focusableElements[ focusableElements.length - 1 ];

			const activeEl = overlay.ownerDocument.activeElement;

			if ( e.shiftKey && activeEl === first ) {
				e.preventDefault();
				last.focus();
			} else if ( ! e.shiftKey && activeEl === last ) {
				e.preventDefault();
				first.focus();
			}
		}
	} );
}
