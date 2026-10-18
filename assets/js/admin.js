/**
 * CineRent Shop – Backend: Titel schreibgeschützt anzeigen.
 * Die eigentliche Sperre erfolgt serverseitig (Readonly_Guard).
 *
 * @package CineRent\Shop
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var title = document.getElementById( 'title' );
		if ( title ) {
			title.readOnly = true;
			title.setAttribute( 'aria-readonly', 'true' );
		}
	} );
} )();
