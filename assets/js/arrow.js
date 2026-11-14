/* LabelFill: a chosen variation shows its own arrow and sheet. */
( function () {
	'use strict';

	// Block themes redraw the price on their own (and strip buttons), so the arrow is put back after each redraw.
	var keeps = [];
	Array.prototype.forEach.call( document.querySelectorAll( '[data-wp-watch] .labelfill-main' ), function ( btn ) {
		var box = btn.closest( '[data-wp-watch]' );
		var line = box.querySelector( '.labelfill-sheetline' );
		var link = line && line.querySelector( 'a' );
		var k = {
			box: box,
			row: btn.parentNode.classList.contains( 'labelfill-row' ),
			original: btn.outerHTML,
			arrow: btn.outerHTML,
			line: line ? line.outerHTML : '',
			originalSheet: link ? link.getAttribute( 'href' ) : '',
			sheet: link ? link.getAttribute( 'href' ) : ''
		};
		keeps.push( k );
		if ( window.MutationObserver ) {
			new MutationObserver( function () {
				restore( k );
			} ).observe( box, { childList: true, subtree: true } );
		}
	} );

	function restore( k ) {
		if ( k.box.querySelector( '.labelfill-arrow' ) ) {
			return;
		}
		k.box.insertAdjacentHTML( 'beforeend', ( k.row ? '<span class="labelfill-row">' + k.arrow + '</span>' : ' ' + k.arrow ) + k.line );
		var link = k.box.querySelector( '.labelfill-sheetlink' );
		if ( link && k.sheet ) {
			link.setAttribute( 'href', k.sheet );
		}
	}

	function swap( form, data ) {
		var scope = form.closest( '.product' ) || document;
		var main = scope.querySelector( '.labelfill-main' ) || document.querySelector( '.labelfill-main' );
		var link = scope.querySelector( '.labelfill-sheetlink' ) || document.querySelector( '.labelfill-sheetlink' );
		keeps.forEach( function ( k ) {
			k.arrow = data ? data.button : k.original;
			k.sheet = data ? data.sheet : k.originalSheet;
			if ( main && k.box.contains( main ) ) {
				main.labelfillOriginal = main.labelfillOriginal || { html: k.original, sheet: k.originalSheet };
			}
		} );
		if ( ! main ) {
			keeps.forEach( restore );
			return;
		}
		if ( ! main.labelfillOriginal ) {
			main.labelfillOriginal = { html: main.outerHTML, sheet: link ? link.getAttribute( 'href' ) : '' };
		}
		var html = data ? data.button : main.labelfillOriginal.html;
		var wrap = document.createElement( 'div' );
		wrap.innerHTML = html;
		var next = wrap.firstChild;
		if ( ! next ) {
			return;
		}
		next.labelfillOriginal = main.labelfillOriginal;
		main.parentNode.replaceChild( next, main );
		if ( link ) {
			var url = data ? data.sheet : main.labelfillOriginal.sheet;
			if ( url ) {
				link.setAttribute( 'href', url );
			}
		}
	}

	if ( ! window.jQuery ) {
		return;
	}
	var $ = window.jQuery;
	$( document ).on( 'found_variation', 'form.variations_form', function ( e, variation ) {
		swap( this, variation && variation.labelfill ? variation.labelfill : null );
	} );
	$( document ).on( 'reset_data', 'form.variations_form', function () {
		swap( this, null );
	} );
}() );
