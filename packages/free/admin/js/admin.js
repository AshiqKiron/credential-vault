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
	bindSelectAll( 'cv-bulk-all', '.cv-bulk-row' );

	function bindTokenBoard() {
		var board = document.getElementById( 'cv-token-board' );
		if ( ! board ) {
			return;
		}

		var search = board.querySelector( '#cv-token-search' );
		var filters = board.querySelectorAll( '[data-token-filter]' );
		var rows = board.querySelectorAll( '.new-vault-token-row' );
		var groups = board.querySelectorAll( '.new-vault-token-group' );
		var empty = board.querySelector( '.new-vault-token-empty' );
		var alertBox = board.querySelector( '[data-token-alert]' );
		var active = 'all';

		function rowMatches( row, filter, query ) {
			var status = row.getAttribute( 'data-token-status' ) || '';
			var configured = row.getAttribute( 'data-token-configured' ) === '1';
			var haystack = ( row.getAttribute( 'data-token-search' ) || '' ).toLowerCase();

			if ( query && haystack.indexOf( query ) === -1 ) {
				return false;
			}
			if ( filter === 'attention' ) {
				return status === 'attention';
			}
			if ( filter === 'configured' ) {
				return configured;
			}
			if ( filter === 'available' ) {
				return status === 'available';
			}
			return true;
		}

		function applyTokenFilter() {
			var query = search ? search.value.trim().toLowerCase() : '';
			var shown = 0;
			var i;

			for ( i = 0; i < rows.length; i++ ) {
				var match = rowMatches( rows[ i ], active, query );
				rows[ i ].hidden = ! match;
				if ( match ) {
					shown++;
				}
			}

			for ( i = 0; i < groups.length; i++ ) {
				var visibleRows = groups[ i ].querySelectorAll( '.new-vault-token-row:not([hidden])' );
				var countNode = groups[ i ].querySelector( '.new-vault-token-group__count' );
				groups[ i ].hidden = visibleRows.length === 0;
				if ( countNode ) {
					countNode.textContent = String( visibleRows.length );
				}
			}

			if ( empty ) {
				empty.hidden = shown !== 0;
			}
			if ( alertBox ) {
				alertBox.hidden = ! board.querySelector( '.new-vault-token-row[data-token-status="attention"]:not([hidden])' );
			}
		}

		function setTokenFilter( value ) {
			var i;
			active = value;
			for ( i = 0; i < filters.length; i++ ) {
				var pressed = filters[ i ].getAttribute( 'data-token-filter' ) === value;
				filters[ i ].setAttribute( 'aria-pressed', pressed ? 'true' : 'false' );
				filters[ i ].classList.toggle( 'is-active', pressed );
			}
			applyTokenFilter();
		}

		for ( var f = 0; f < filters.length; f++ ) {
			filters[ f ].addEventListener( 'click', function () {
				setTokenFilter( this.getAttribute( 'data-token-filter' ) || 'all' );
			} );
		}

		if ( search ) {
			search.addEventListener( 'input', applyTokenFilter );
		}
	}

	bindTokenBoard();

	var cfg = window.credentialVaultConfig || {};
	var clearSec = parseInt( cfg.clipboardClearSeconds, 10 );
	if ( window.credentialVault && window.credentialVault.clipboard && clearSec > 0 && navigator.clipboard && navigator.clipboard.writeText ) {
		setTimeout( function () {
			navigator.clipboard.writeText( '' ).catch( function () {} );
		}, clearSec * 1000 );
	}

	if ( window.credentialVaultSudo && window.credentialVaultSudo.remaining ) {
		var node = document.getElementById( 'wp-admin-bar-credential-vault-sudo' );
		var left = parseInt( window.credentialVaultSudo.remaining, 10 );
		if ( node && left > 0 ) {
			setInterval( function () {
				left -= 1;
				if ( left <= 0 ) {
					node.textContent = 'Vault sudo expired';
					return;
				}
				var m = Math.floor( left / 60 );
				var s = left % 60;
				node.textContent = 'Vault sudo: ' + ( m < 10 ? '0' : '' ) + m + ':' + ( s < 10 ? '0' : '' ) + s + ' left';
			}, 1000 );
		}
	}
} )();
