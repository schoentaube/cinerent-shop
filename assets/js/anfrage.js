/**
 * CineRent Shop – Anfrageformular.
 *
 * Holt beim Laden Nonce, Zeit-Token und ALTCHA-Challenge, rechnet den
 * Proof-of-Work im Hintergrund und sendet Formular, Anfrageliste und
 * Zeitraum als JSON an /wp-json/cinerent/v1/anfrage.
 *
 * @package CineRent\Shop
 */
( function () {
	'use strict';

	var cfg = window.cinerentConfig || {};
	var t = cfg.i18n || {};

	function initForm( form ) {
		var CR = window.CineRent,
			message = form.querySelector( '[data-cr-meldung]' ),
			status = form.querySelector( '[data-cr-altcha-status]' ),
			submit = form.querySelector( 'button[type="submit"]' ),
			state = { token: '', altcha: null, solving: null };

		function showMessage( text, isError ) {
			message.textContent = text;
			message.hidden = false;
			message.classList.toggle( 'is-fehler', !! isError );
			message.classList.toggle( 'is-erfolg', ! isError );
		}

		function prepare() {
			status.textContent = t.pruefen;
			form.classList.remove( 'is-bereit' );
			state.solving = fetch( CR.util.api( 'formular' ), { credentials: 'same-origin', cache: 'no-store' } )
				.then( function ( r ) {
					return r.json();
				} )
				.then( function ( data ) {
					form.querySelector( 'input[name="nonce"]' ).value = data.nonce;
					state.token = data.token;
					return window.CineRentAltcha.solve( data.altcha );
				} )
				.then( function ( payload ) {
					state.altcha = payload;
					status.textContent = '';
					form.classList.add( 'is-bereit' );
					return payload;
				} );
			return state.solving;
		}

		function fieldValue( name ) {
			var field = form.elements[ name ];
			return field ? String( field.value || '' ).trim() : '';
		}

		function markInvalid( name ) {
			var field = form.elements[ name ];
			if ( field && field.focus ) {
				field.setAttribute( 'aria-invalid', 'true' );
				field.focus();
			}
		}

		form.addEventListener( 'input', function ( e ) {
			e.target.removeAttribute( 'aria-invalid' );
		} );

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			message.hidden = true;

			var period = CR.period.get();
			var items = CR.list.get();

			if ( ! items.length ) {
				showMessage( t.leer, true );
				return;
			}
			if ( ! period ) {
				showMessage( t.zeitraumWaehlen, true );
				return;
			}
			if ( ! form.checkValidity() ) {
				var invalid = form.querySelector( ':invalid' );
				if ( invalid ) {
					markInvalid( invalid.name );
				}
				form.reportValidity();
				return;
			}

			submit.disabled = true;
			var original = submit.textContent;
			submit.textContent = state.altcha ? t.senden : t.pruefen;

			( state.altcha ? Promise.resolve( state.altcha ) : state.solving || prepare() )
				.then( function () {
					submit.textContent = t.senden;
					var lieferung = form.querySelector( 'input[name="lieferung"]:checked' );
					var body = {
						nonce: fieldValue( 'nonce' ),
						token: state.token,
						altcha: state.altcha,
						website: fieldValue( 'website' ),
						firma: fieldValue( 'firma' ),
						name: fieldValue( 'name' ),
						email: fieldValue( 'email' ),
						telefon: fieldValue( 'telefon' ),
						lieferung: lieferung ? lieferung.value : 'abholung',
						nachricht: fieldValue( 'nachricht' ),
						mietbedingungen: form.elements.mietbedingungen.checked,
						datenschutz: form.elements.datenschutz.checked,
						von: period.von,
						bis: period.bis,
						items: items.map( function ( i ) {
							return { id: i.id, menge: i.menge || 1 };
						} ),
					};
					return fetch( CR.util.api( 'anfrage' ), {
						method: 'POST',
						credentials: 'same-origin',
						headers: { 'Content-Type': 'application/json' },
						body: JSON.stringify( body ),
					} ).then( function ( r ) {
						return r.json().then( function ( data ) {
							return { ok: r.ok && data.ok, data: data };
						} );
					} );
				} )
				.then( function ( result ) {
					if ( result.ok ) {
						CR.list.clear();
						form.reset();
						form.classList.add( 'is-gesendet' );
						showMessage( form.dataset.erfolg || result.data.meldung, false );
						return;
					}
					showMessage( ( result.data && result.data.meldung ) || t.fehler, true );
					if ( result.data && result.data.feld ) {
						markInvalid( result.data.feld );
					}
					// Jede Challenge gilt nur einmal: neue holen.
					state.altcha = null;
					prepare();
				} )
				.catch( function () {
					showMessage( t.fehler, true );
					state.altcha = null;
					prepare();
				} )
				.then( function () {
					submit.disabled = false;
					submit.textContent = original;
				} );
		} );

		prepare().catch( function () {
			status.textContent = '';
		} );
	}

	function init( scope ) {
		( scope || document ).querySelectorAll( '[data-cr="formular"]:not([data-cr-init])' ).forEach( function ( form ) {
			form.setAttribute( 'data-cr-init', '1' );
			initForm( form );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			init();
		} );
	} else {
		init();
	}

	window.addEventListener( 'elementor/frontend/init', function () {
		if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
			window.elementorFrontend.hooks.addAction( 'frontend/element_ready/cinerent-anfrageformular.default', function ( $scope ) {
				init( $scope[ 0 ] );
			} );
		}
	} );
} )();
