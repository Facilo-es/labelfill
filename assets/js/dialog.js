/* LabelFill: the label window. Opens from any arrow, loads the label only then. */
( function () {
	'use strict';
	var opener = null;

	function dlg() {
		return document.querySelector( '.labelfill-dialog' );
	}

	function open( btn ) {
		var d = dlg();
		if ( ! d ) {
			return;
		}
		var img = d.querySelector( '.labelfill-dialog__img img' );
		var miss = d.querySelector( '.labelfill-dialog__missing' );
		var sheet = d.querySelector( '.labelfill-dialog__sheet' );
		var extra = d.querySelector( '.labelfill-dialog__extra' );
		var source = d.querySelector( '.labelfill-dialog__source' );
		var label = btn.getAttribute( 'data-lf-label' ) || '';
		var sheetUrl = btn.getAttribute( 'data-lf-sheet' ) || '';
		var extraText = btn.getAttribute( 'data-lf-extra' ) || '';
		opener = btn;
		miss.hidden = true;
		img.hidden = ! label;
		img.onerror = function () {
			img.hidden = true;
			miss.hidden = false;
		};
		if ( label ) {
			img.src = label;
		} else {
			img.removeAttribute( 'src' );
			miss.hidden = false;
		}
		sheet.hidden = ! sheetUrl;
		if ( sheetUrl ) {
			sheet.href = sheetUrl;
		}
		extra.hidden = ! extraText;
		extra.textContent = extraText;
		source.hidden = 'shop' === btn.getAttribute( 'data-lf-src' );
		if ( typeof d.showModal === 'function' ) {
			d.showModal();
		} else {
			d.setAttribute( 'open', '' );
		}
		d.querySelector( '.labelfill-dialog__close' ).focus();
	}

	function close() {
		var d = dlg();
		if ( ! d || ! d.hasAttribute( 'open' ) ) {
			return;
		}
		if ( typeof d.close === 'function' ) {
			d.close();
		} else {
			d.removeAttribute( 'open' );
			restore();
		}
	}

	function restore() {
		if ( opener && document.contains( opener ) ) {
			opener.focus();
		}
		opener = null;
	}

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest && e.target.closest( '.labelfill-arrow' );
		if ( btn ) {
			// The arrow sits inside product links in shop grids.
			e.preventDefault();
			e.stopPropagation();
			open( btn );
			return;
		}
		var d = dlg();
		if ( d && e.target === d ) {
			close();
		} else if ( e.target.closest && e.target.closest( '.labelfill-dialog__close' ) ) {
			close();
		}
	}, true );

	document.addEventListener( 'keydown', function ( e ) {
		var d = dlg();
		if ( ! d || ! d.hasAttribute( 'open' ) ) {
			return;
		}
		if ( 'Escape' === e.key && typeof d.showModal !== 'function' ) {
			close();
		}
		if ( 'Tab' === e.key ) {
			var f = Array.prototype.filter.call( d.querySelectorAll( 'a[href]:not([hidden]), button' ), function ( el ) {
				return el.offsetParent !== null;
			} );
			if ( ! f.length ) {
				return;
			}
			if ( e.shiftKey && document.activeElement === f[ 0 ] ) {
				e.preventDefault();
				f[ f.length - 1 ].focus();
			} else if ( ! e.shiftKey && document.activeElement === f[ f.length - 1 ] ) {
				e.preventDefault();
				f[ 0 ].focus();
			}
		}
	} );

	document.addEventListener( 'DOMContentLoaded', function () {
		var d = dlg();
		if ( d ) {
			d.addEventListener( 'close', restore );
		}
	} );
	window.labelfillOpen = open;
}() );
