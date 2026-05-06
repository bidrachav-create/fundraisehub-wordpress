/**
 * FundRaiseHub donation iframe postMessage bridge.
 *
 * Listens for messages from FundRaiseHub donation iframes embedded on the
 * WordPress page and handles protocol messages:
 *
 *  - FRH_INIT           Sent TO the iframe when it loads; passes the WP
 *                       page origin so FundRaiseHub can validate against its
 *                       allowedOrigins list.
 *  - FRH_DONATION_COMPLETE  Received FROM iframe; shows a thank-you overlay.
 *  - FRH_RESIZE         Received FROM iframe; updates iframe height.
 *  - FRH_OPEN_MODAL     Received FROM iframe; makes the iframe full-screen.
 *
 * Enqueued on pages that contain a fundraisehub_campaign post.
 */
/* eslint-env browser */
( function () {
	'use strict';

	/**
	 * Localised strings and configuration injected via wp_localize_script.
	 * Provides i18n strings and the allowedOrigin for postMessage targeting.
	 *
	 * @type {{ thankYouMessage: string, closeLabel: string, allowedOrigin: string }}
	 */
	const cfg = window.fundraisehubBridge || {};

	/**
	 * Send FRH_INIT to a FundRaiseHub iframe after it loads.
	 *
	 * @param {HTMLIFrameElement} iframe The iframe element to initialise.
	 */
	function attachIframeInit( iframe ) {
		iframe.addEventListener( 'load', function () {
			if ( ! iframe.src ) {
				return;
			}

			let targetOrigin;
			try {
				targetOrigin = new URL( iframe.src ).origin;
			} catch ( e ) {
				return;
			}

			if ( iframe.contentWindow ) {
				iframe.contentWindow.postMessage(
					{ type: 'FRH_INIT', origin: window.location.origin },
					targetOrigin
				);
			}
		} );
	}

	/**
	 * Display a full-screen thank-you overlay on the WordPress page.
	 */
	function showThankYouOverlay() {
		if ( document.querySelector( '.fundraisehub-thankyou-overlay' ) ) {
			return;
		}

		const overlay = document.createElement( 'div' );
		overlay.className = 'fundraisehub-thankyou-overlay';
		overlay.setAttribute( 'role', 'dialog' );
		overlay.setAttribute( 'aria-modal', 'true' );
		overlay.setAttribute( 'aria-live', 'polite' );

		const message = document.createElement( 'div' );
		message.className = 'fundraisehub-thankyou-overlay__message';
		message.textContent =
			cfg.thankYouMessage || 'Thank you for your donation!';

		const closeBtn = document.createElement( 'button' );
		closeBtn.className = 'fundraisehub-thankyou-overlay__close';
		closeBtn.setAttribute( 'aria-label', cfg.closeLabel || 'Close' );
		closeBtn.textContent = '\u00d7';

		closeBtn.addEventListener( 'click', function () {
			overlay.remove();
		} );

		overlay.appendChild( message );
		overlay.appendChild( closeBtn );
		document.body.appendChild( overlay );
		closeBtn.focus();
	}

	/**
	 * Make an iframe full-screen by toggling a CSS class.
	 *
	 * The iframe (or its overlay wrapper) gains `is-fullscreen`.
	 * Themes and the plugin stylesheet use this class to expand to
	 * the viewport.
	 *
	 * @param {HTMLIFrameElement} iframe
	 */
	function fullscreenIframe( iframe ) {
		const overlay = iframe.closest( '.fundraisehub-donation-overlay' );
		if ( overlay ) {
			overlay.classList.add( 'is-fullscreen' );
		} else {
			iframe.classList.add( 'is-fullscreen' );
		}
	}

	/**
	 * Observe the DOM for newly added FundRaiseHub iframes and attach
	 * FRH_INIT listeners so the handshake fires even for dynamically
	 * created overlays.
	 */
	const observer = new MutationObserver( function ( mutations ) {
		mutations.forEach( function ( mutation ) {
			mutation.addedNodes.forEach( function ( node ) {
				if ( node.nodeType !== Node.ELEMENT_NODE ) {
					return;
				}
				if (
					'IFRAME' === node.tagName &&
					node.classList.contains( 'fundraisehub-donate-iframe' )
				) {
					attachIframeInit(
						/** @type {HTMLIFrameElement} */ ( node )
					);
				}
				node.querySelectorAll(
					'iframe.fundraisehub-donate-iframe'
				).forEach( attachIframeInit );
			} );
		} );
	} );

	observer.observe( document.body, { childList: true, subtree: true } );

	// Attach init listener to any iframes already rendered (hidden template
	// iframes output by render.php on page load).
	document
		.querySelectorAll( 'iframe.fundraisehub-donate-iframe' )
		.forEach( attachIframeInit );

	/**
	 * Handle postMessage events sent by FundRaiseHub iframes.
	 */
	window.addEventListener( 'message', function ( event ) {
		// Locate the iframe whose contentWindow matches the event source.
		let matchedIframe = null;
		document
			.querySelectorAll( 'iframe.fundraisehub-donate-iframe' )
			.forEach( function ( frame ) {
				if ( frame.contentWindow === event.source ) {
					matchedIframe = frame;
				}
			} );

		if ( ! matchedIframe ) {
			return;
		}

		const data = event.data;
		if ( ! data || 'object' !== typeof data ) {
			return;
		}

		switch ( data.type ) {
			case 'FRH_DONATION_COMPLETE':
				showThankYouOverlay();
				break;

			case 'FRH_RESIZE':
				if ( 'number' === typeof data.height && data.height > 0 ) {
					matchedIframe.style.height = data.height + 'px';
				}
				break;

			case 'FRH_OPEN_MODAL':
				fullscreenIframe( matchedIframe );
				break;
		}
	} );
} )();
