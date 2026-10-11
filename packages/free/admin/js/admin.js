( function () {
	'use strict';

	if ( typeof window.credentialVault !== 'undefined' && window.credentialVault.clipboard ) {
		var value = window.credentialVault.clipboard;

		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( value ).catch( function () {
				window.prompt( 'Copy credential:', value );
			} );
		} else {
			window.prompt( 'Copy credential:', value );
		}

		delete window.credentialVault.clipboard;
	}

	var selectAll = document.getElementById( 'cv-import-all' );
	if ( selectAll ) {
		selectAll.addEventListener( 'change', function () {
			var boxes = document.querySelectorAll( '.cv-import-row' );
			for ( var i = 0; i < boxes.length; i++ ) {
				boxes[ i ].checked = selectAll.checked;
			}
		} );
	}
} )();
