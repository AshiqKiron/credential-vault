( function () {
	'use strict';

	if ( typeof window.credentialVault !== 'undefined' && window.credentialVault.clipboard ) {
		var value = window.credentialVault.clipboard;

		var done = function () {
			if ( window.credentialVault.clipboardNotice ) {
				window.alert( window.credentialVault.clipboardNotice );
			}
			delete window.credentialVault.clipboard;
		};

		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( value ).then( done ).catch( function () {
				window.prompt( 'Copy credential:', value );
				done();
			} );
		} else {
			window.prompt( 'Copy credential:', value );
			done();
		}
	}

	function bindServiceFields() {
		var serviceSelect = document.getElementById( 'credential_service' );
		if ( ! serviceSelect ) {
			return;
		}
		var rows = document.querySelectorAll( '.cv-service-field' );
		var valueLabel = document.getElementById( 'credential_value_label' );
		var sync = function () {
			var service = serviceSelect.value;
			for ( var i = 0; i < rows.length; i++ ) {
				rows[ i ].hidden = rows[ i ].getAttribute( 'data-cv-service' ) !== service;
			}
			if ( valueLabel ) {
				if ( service === 'aws' ) {
					valueLabel.textContent = 'Secret access key';
				} else if ( service === 'webhook' ) {
					valueLabel.textContent = 'Signing secret';
				} else if ( service === 'oauth' ) {
					valueLabel.textContent = 'Client secret';
				} else {
					valueLabel.textContent = 'Secret value';
				}
			}
		};
		serviceSelect.addEventListener( 'change', sync );
		sync();
	}

	bindServiceFields();

	function bindSelectAll( masterId, rowClass ) {
		var selectAll = document.getElementById( masterId );
		if ( ! selectAll ) {
			return;
		}
		selectAll.addEventListener( 'change', function () {
			var boxes = document.querySelectorAll( rowClass );
			for ( var i = 0; i < boxes.length; i++ ) {
				boxes[ i ].checked = selectAll.checked;
			}
		} );
	}

	bindSelectAll( 'cv-import-all', '.cv-import-row' );
	bindSelectAll( 'cv-scan-all', '.cv-scan-row' );
} )();
