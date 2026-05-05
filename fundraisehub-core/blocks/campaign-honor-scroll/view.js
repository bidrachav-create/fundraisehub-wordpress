/**
 * Front-end script for the Campaign Honor Scroll block.
 * Auto-scrolls the donor list vertically.
 */

document.querySelectorAll( '.fundraisehub-campaign-honor-scroll__list' ).forEach( ( list ) => {
	if ( list.children.length < 2 ) {
		return;
	}

	let offset   = 0;
	const speed  = 0.5; // pixels per frame
	const itemH  = list.firstElementChild?.offsetHeight ?? 40;
	const total  = list.scrollHeight;

	function tick() {
		offset += speed;
		if ( offset >= total / 2 ) {
			offset = 0;
		}
		list.style.transform = 'translateY(-' + offset + 'px)';
		requestAnimationFrame( tick );
	}

	// Duplicate items for seamless looping.
	const clone = list.cloneNode( true );
	clone.setAttribute( 'aria-hidden', 'true' );
	list.parentNode.appendChild( clone );

	requestAnimationFrame( tick );
} );
