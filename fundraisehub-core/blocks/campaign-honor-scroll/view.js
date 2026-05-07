/**
 * Front-end script for the Campaign Honor Scroll block.
 * Auto-scrolls the donor list vertically.
 * Respects the `prefers-reduced-motion` media query.
 */

// Skip the animation entirely for users who prefer reduced motion.
if ( window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
	// Nothing to do; the static list is already visible.
} else if (
	'undefined' !== typeof window.IntersectionObserver &&
	'undefined' !== typeof window.requestAnimationFrame &&
	'undefined' !== typeof window.cancelAnimationFrame
) {
	document
		.querySelectorAll( '.fundraisehub-campaign-honor-scroll__list' )
		.forEach( ( list ) => {
			if ( list.children.length < 2 ) {
				return;
			}

			let scrollOffset = 0;
			const speed = 0.5; // pixels per frame
			const totalScrollHeight = list.scrollHeight;
			let rafId = null;

			function tick() {
				scrollOffset += speed;
				if ( scrollOffset >= totalScrollHeight / 2 ) {
					scrollOffset = 0;
				}
				list.style.transform = 'translateY(-' + scrollOffset + 'px)';
				rafId = window.requestAnimationFrame( tick );
			}

			// Duplicate items for seamless looping.
			const clone = list.cloneNode( true );
			clone.setAttribute( 'aria-hidden', 'true' );
			list.parentNode.appendChild( clone );

			// Pause when the element leaves the viewport; resume when it returns.
			const observer = new window.IntersectionObserver( ( entries ) => {
				entries.forEach( ( entry ) => {
					if ( entry.isIntersecting ) {
						if ( ! rafId ) {
							rafId = window.requestAnimationFrame( tick );
						}
					} else if ( rafId ) {
						window.cancelAnimationFrame( rafId );
						rafId = null;
					}
				} );
			} );

			observer.observe( list );
		} );
}
