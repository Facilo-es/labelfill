/* LabelFill admin: product box, product list and wizard. */
( function () {
	'use strict';

	var cfg = window.labelfillAdmin || {};
	var t = cfg.i18n || {};

	function post( action, data ) {
		var body = new URLSearchParams();
		body.append( 'action', action );
		body.append( 'nonce', cfg.nonce );
		Object.keys( data ).forEach( function ( k ) {
			body.append( k, data[ k ] );
		} );
		return fetch( cfg.ajax, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) {
				return r.json();
			} )
			.catch( function () {
				return { success: false, data: { message: t.failed } };
			} );
	}

	function message( tone, text ) {
		var div = document.createElement( 'div' );
		div.className = 'labelfill-msg is-' + tone;
		div.textContent = text;
		var wrap = document.createElement( 'div' );
		wrap.className = 'labelfill-msgs';
		wrap.setAttribute( 'aria-live', 'polite' );
		wrap.appendChild( div );
		return wrap;
	}

	/* Product box */

	function paint( box, data ) {
		box.querySelector( '.labelfill-pillw' ).innerHTML = data.pill;
		box.querySelector( '.labelfill-state' ).innerHTML = data.left;
		box.querySelector( '.labelfill-r' ).innerHTML = data.right;
		if ( data.number ) {
			box.querySelector( '.labelfill-in' ).value = data.number;
		}
	}

	function fill( box, replace ) {
		var input = box.querySelector( '.labelfill-in' );
		var state = box.querySelector( '.labelfill-state' );
		var button = box.querySelector( '.labelfill-go' );
		if ( ! input.value.trim() ) {
			state.innerHTML = '';
			state.appendChild( message( 'bad', t.invalid ) );
			input.focus();
			return;
		}
		box.classList.add( 'is-loading' );
		button.disabled = true;
		button.setAttribute( 'aria-busy', 'true' );
		state.innerHTML = '';
		state.appendChild( message( 'info', t.loading ) );
		post( 'labelfill_fill', { id: box.dataset.lfId, number: input.value, replace: replace ? 1 : '' } ).then( function ( r ) {
			box.classList.remove( 'is-loading' );
			button.disabled = false;
			button.removeAttribute( 'aria-busy' );
			if ( r && r.success ) {
				paint( box, r.data );
				if ( replace ) {
					var toggle = box.querySelector( '[name="labelfill_manual"]' );
					toggle.checked = false;
					manual( box, false );
				}
				return;
			}
			state.innerHTML = '';
			state.appendChild( message( 'bad', ( r && r.data && r.data.message ) || t.failed ) );
		} );
	}

	function manual( box, on ) {
		box.classList.toggle( 'is-manual', on );
		box.querySelector( '.labelfill-manual' ).hidden = ! on;
	}

	function classes( select, scale, list ) {
		var keep = select.value;
		var first = select.options[ 0 ];
		select.innerHTML = '';
		select.appendChild( first );
		( list[ scale ] || [] ).forEach( function ( c ) {
			var o = document.createElement( 'option' );
			o.value = c;
			o.textContent = c;
			o.selected = c === keep;
			select.appendChild( o );
		} );
	}

	function pick( button ) {
		if ( ! window.wp || ! wp.media ) {
			return;
		}
		var row = button.closest( '.labelfill-file' );
		var kind = button.dataset.lfKind;
		var frame = wp.media( {
			title: t.pickFile,
			button: { text: t.use },
			library: { type: 'label' === kind ? 'image' : 'application/pdf' },
			multiple: false,
		} );
		frame.on( 'select', function () {
			var file = frame.state().get( 'selection' ).first().toJSON();
			row.querySelector( 'input[type="hidden"]' ).value = file.id;
			row.querySelector( '.labelfill-file__name' ).textContent = file.filename || file.title;
			row.querySelector( '.labelfill-unpick' ).hidden = false;
		} );
		frame.open();
	}

	document.addEventListener( 'click', function ( e ) {
		var el = e.target.closest( 'button' );
		if ( ! el ) {
			return;
		}
		var box = el.closest( '.labelfill-box' );
		if ( el.classList.contains( 'labelfill-go' ) && box ) {
			e.preventDefault();
			fill( box, false );
		} else if ( el.classList.contains( 'labelfill-replace' ) && box ) {
			e.preventDefault();
			fill( box, true );
		} else if ( el.classList.contains( 'labelfill-reviewed' ) && box ) {
			e.preventDefault();
			el.disabled = true;
			post( 'labelfill_reviewed', { id: box.dataset.lfId } ).then( function ( r ) {
				if ( r && r.success ) {
					paint( box, r.data );
				}
			} );
		} else if ( el.classList.contains( 'labelfill-pick' ) ) {
			e.preventDefault();
			pick( el );
		} else if ( el.classList.contains( 'labelfill-unpick' ) ) {
			e.preventDefault();
			var row = el.closest( '.labelfill-file' );
			row.querySelector( 'input[type="hidden"]' ).value = '';
			row.querySelector( '.labelfill-file__name' ).textContent = '';
			el.hidden = true;
		} else if ( el.classList.contains( 'labelfill-rowfill' ) ) {
			e.preventDefault();
			var id = el.dataset.lfId;
			var cell = document.querySelector( '.labelfill-cell[data-lf-id="' + id + '"]' );
			el.disabled = true;
			el.setAttribute( 'aria-busy', 'true' );
			if ( cell ) {
				cell.classList.add( 'is-loading' );
			}
			post( 'labelfill_fill', { id: id, where: 'list' } ).then( function ( r ) {
				el.disabled = false;
				el.removeAttribute( 'aria-busy' );
				if ( cell ) {
					cell.classList.remove( 'is-loading' );
					if ( r && r.success ) {
						cell.innerHTML = r.data.html;
					}
				}
			} );
		} else if ( el.classList.contains( 'labelfill-trygo' ) ) {
			e.preventDefault();
			var out = document.querySelector( '.labelfill-tryout' );
			var input = document.getElementById( 'labelfill-try' );
			out.innerHTML = '';
			out.appendChild( message( 'info', t.loading ) );
			el.disabled = true;
			post( 'labelfill_try', { number: input.value } ).then( function ( r ) {
				el.disabled = false;
				if ( r && r.success ) {
					out.innerHTML = r.data.html;
					return;
				}
				out.innerHTML = '';
				out.appendChild( message( 'bad', ( r && r.data && r.data.message ) || t.failed ) );
			} );
		}
	} );

	// Enter in the number field fills instead of saving the product.
	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Enter' !== e.key ) {
			return;
		}
		if ( e.target.id === 'labelfill-reg' ) {
			e.preventDefault();
			fill( e.target.closest( '.labelfill-box' ), false );
		} else if ( e.target.id === 'labelfill-try' ) {
			e.preventDefault();
			document.querySelector( '.labelfill-trygo' ).click();
		}
	} );

	document.addEventListener( 'change', function ( e ) {
		var el = e.target;
		var box = el.closest( '.labelfill-box' );
		if ( ! box ) {
			return;
		}
		if ( 'labelfill_manual' === el.name ) {
			manual( box, el.checked );
		} else if ( 'labelfill_m_scale' === el.name ) {
			var wrap = box.querySelector( '.labelfill-manual' );
			classes( box.querySelector( '[name="labelfill_m_class"]' ), el.value, JSON.parse( wrap.dataset.lfScales || '{}' ) );
		}
	} );
}() );
