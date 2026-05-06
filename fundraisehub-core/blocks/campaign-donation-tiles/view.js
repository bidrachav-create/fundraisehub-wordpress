/**
 * Front-end script for the Campaign Donation Tiles block.
 * Highlights the selected tile and opens the FundRaiseHub donation form in an iframe.
 */

import { __ } from '@wordpress/i18n';

document
	.querySelectorAll( '.fundraisehub-campaign-donation-tiles' )
	.forEach( ( wrapper ) => {
		const tiles = wrapper.querySelectorAll(
			'.fundraisehub-campaign-donation-tiles__tile'
		);

		tiles.forEach( ( tile ) => {
			tile.addEventListener( 'click', () => {
				tiles.forEach( ( t ) => t.classList.remove( 'is-selected' ) );
				tile.classList.add( 'is-selected' );

				const amount = tile.dataset.amount ?? '';

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

				// Append the selected amount to the existing iframe src.
				if ( amount ) {
					try {
						const url = new URL( iframe.src );
						url.searchParams.set( 'amount', amount );
						iframe.src = url.toString();
					} catch ( e ) {
						// Leave the src unchanged if the URL is invalid.
					}
				}

				openDonationOverlay( iframe, tile );
			} );
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
