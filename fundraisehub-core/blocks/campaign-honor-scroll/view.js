/**
 * Front-end script for the Campaign Honor Scroll block.
 * Auto-scrolls the donor list vertically.
 */

document.querySelectorAll( '.fundraisehub-campaign-honor-scroll__list' ).forEach( ( list ) => {
	if ( list.children.length < 2 ) {
		return;
	}

	let scrollOffset        = 0;
	const speed             = 0.5; // pixels per frame
	const totalScrollHeight = list.scrollHeight;

	function tick() {
		scrollOffset += speed;
		if ( scrollOffset >= totalScrollHeight / 2 ) {
			scrollOffset = 0;
		}
		list.style.transform = 'translateY(-' + scrollOffset + 'px)';
		requestAnimationFrame( tick );
	}

	// Duplicate items for seamless looping.
	const clone = list.cloneNode( true );
	clone.setAttribute( 'aria-hidden', 'true' );
	list.parentNode.appendChild( clone );

	requestAnimationFrame( tick );
} );
