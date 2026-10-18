/**
 * CineRent Shop – Frontend.
 *
 * Zeitraum (Cookie), Anfrageliste (localStorage), Verfügbarkeits-Badges,
 * Kalender und Button "Zur Anfrageliste". Ohne Abhängigkeiten, ohne externe Ressourcen.
 *
 * @package CineRent\Shop
 */
( function () {
	'use strict';

	var cfg = window.cinerentConfig || {};
	var t = cfg.i18n || {};

	/* ------------------------------------------------------------------ */
	/* Hilfsfunktionen                                                     */
	/* ------------------------------------------------------------------ */

	function pad( n ) {
		return ( n < 10 ? '0' : '' ) + n;
	}

	function ymd( date ) {
		return date.getFullYear() + '-' + pad( date.getMonth() + 1 ) + '-' + pad( date.getDate() );
	}

	function parseYmd( value ) {
		var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec( value || '' );
		return m ? new Date( +m[ 1 ], +m[ 2 ] - 1, +m[ 3 ] ) : null;
	}

	function today() {
		return ymd( new Date() );
	}

	/** Mietdauer: gleicher Tag = 1, sonst Differenz der Kalendertage. */
	function rentalDays( von, bis ) {
		var a = parseYmd( von ),
			b = parseYmd( bis );
		if ( ! a || ! b || b < a ) {
			return 0;
		}
		return Math.max( 1, Math.round( ( b - a ) / 86400000 ) );
	}

	/** Richtwert: volle Wochen zum Wochenpreis, Rest zum Tagespreis, höchstens eine weitere Woche. */
	function estimate( tagCent, wocheCent, tage ) {
		tagCent = +tagCent || 0;
		wocheCent = +wocheCent || 0;
		if ( wocheCent <= 0 ) {
			return tagCent * tage;
		}
		return Math.floor( tage / 7 ) * wocheCent + Math.min( ( tage % 7 ) * tagCent, wocheCent );
	}

	var priceFormat = new Intl.NumberFormat( 'de-DE', { style: 'currency', currency: 'EUR' } );
	function price( cent ) {
		return priceFormat.format( ( +cent || 0 ) / 100 );
	}

	function formatDate( value ) {
		var d = parseYmd( value );
		return d ? pad( d.getDate() ) + '.' + pad( d.getMonth() + 1 ) + '.' + d.getFullYear() : '';
	}

	function daysLabel( n ) {
		return n + ' ' + ( 1 === n ? t.tag : t.tage );
	}

	function el( tag, className, text ) {
		var node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( undefined !== text ) {
			node.textContent = text;
		}
		return node;
	}

	var ICONS = {
		plus: '<path d="M12 4.5v15M4.5 12h15"/>',
		minus: '<path d="M4.5 12h15"/>',
		schliessen: '<path d="M6 6l12 12M18 6 6 18"/>',
		haken: '<path d="m4.5 12.75 4.5 4.5 10.5-10.5"/>',
	};
	function icon( name ) {
		var span = el( 'span', 'cr-icon-wrap' );
		span.innerHTML = '<svg class="cr-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="square" aria-hidden="true" focusable="false">' + ICONS[ name ] + '</svg>';
		return span;
	}

	/** REST-URL, funktioniert mit und ohne Permalinks (?rest_route=…). */
	function api( path, params ) {
		var url = new URL( cfg.rest + path, window.location.href );
		Object.keys( params || {} ).forEach( function ( key ) {
			url.searchParams.set( key, params[ key ] );
		} );
		return url.toString();
	}

	function emit( name, detail ) {
		document.dispatchEvent( new CustomEvent( name, { detail: detail } ) );
	}

	function safeStorage() {
		try {
			var key = '__cr_test__';
			window.localStorage.setItem( key, '1' );
			window.localStorage.removeItem( key );
			return window.localStorage;
		} catch ( e ) {
			return null;
		}
	}
	var storage = safeStorage();

	/* ------------------------------------------------------------------ */
	/* Zeitraum (Cookie, seitenübergreifend)                               */
	/* ------------------------------------------------------------------ */

	var Period = {
		get: function () {
			var match = document.cookie.match( new RegExp( '(?:^|; )' + cfg.cookie + '=([^;]*)' ) );
			if ( ! match ) {
				return null;
			}
			var parts = decodeURIComponent( match[ 1 ] ).split( '_' );
			if ( 2 !== parts.length || ! parseYmd( parts[ 0 ] ) || ! parseYmd( parts[ 1 ] ) || parts[ 1 ] < parts[ 0 ] ) {
				return null;
			}
			if ( parts[ 0 ] < today() ) {
				Period.clear();
				return null;
			}
			return { von: parts[ 0 ], bis: parts[ 1 ], tage: rentalDays( parts[ 0 ], parts[ 1 ] ) };
		},
		set: function ( von, bis ) {
			var maxAge = ( cfg.cookieTage || 30 ) * 86400;
			var secure = 'https:' === window.location.protocol ? '; Secure' : '';
			document.cookie = cfg.cookie + '=' + encodeURIComponent( von + '_' + bis ) + '; path=/; max-age=' + maxAge + '; SameSite=Lax' + secure;
			emit( 'cinerent:zeitraum', Period.get() );
		},
		clear: function () {
			document.cookie = cfg.cookie + '=; path=/; max-age=0; SameSite=Lax';
		},
	};

	/* ------------------------------------------------------------------ */
	/* Anfrageliste (localStorage)                                         */
	/* ------------------------------------------------------------------ */

	var List = {
		get: function () {
			if ( ! storage ) {
				return List.memory || [];
			}
			try {
				var data = JSON.parse( storage.getItem( cfg.storageKey ) || '[]' );
				return Array.isArray( data ) ? data : [];
			} catch ( e ) {
				return [];
			}
		},
		save: function ( items ) {
			if ( storage ) {
				storage.setItem( cfg.storageKey, JSON.stringify( items ) );
			} else {
				List.memory = items;
			}
			emit( 'cinerent:liste', items );
		},
		add: function ( item ) {
			var items = List.get();
			var found = items.filter( function ( i ) {
				return i.id === item.id;
			} )[ 0 ];
			if ( found ) {
				found.menge = Math.min( 20, ( found.menge || 1 ) + 1 );
			} else {
				item.menge = 1;
				items.push( item );
			}
			List.save( items );
		},
		setMenge: function ( id, menge ) {
			var items = List.get().map( function ( i ) {
				if ( i.id === id ) {
					i.menge = Math.max( 1, Math.min( 20, menge ) );
				}
				return i;
			} );
			List.save( items );
		},
		remove: function ( id ) {
			List.save(
				List.get().filter( function ( i ) {
					return i.id !== id;
				} )
			);
		},
		clear: function () {
			List.save( [] );
		},
		count: function () {
			return List.get().reduce( function ( sum, i ) {
				return sum + ( i.menge || 1 );
			}, 0 );
		},
		has: function ( id ) {
			return List.get().some( function ( i ) {
				return i.id === id;
			} );
		},
	};

	// Andere Tabs halten Liste und Zähler aktuell.
	window.addEventListener( 'storage', function ( e ) {
		if ( e.key === cfg.storageKey ) {
			emit( 'cinerent:liste', List.get() );
		}
	} );

	/* ------------------------------------------------------------------ */
	/* Widget: Zeitraum-Auswahl                                            */
	/* ------------------------------------------------------------------ */

	function bindPeriodInputs( root, onValid ) {
		var von = root.querySelector( '[data-cr-von]' ),
			bis = root.querySelector( '[data-cr-bis]' ),
			dauer = root.querySelector( '[data-cr-dauer]' ),
			fehler = root.querySelector( '[data-cr-fehler]' );

		function fill() {
			var p = Period.get();
			von.min = today();
			von.value = p ? p.von : '';
			bis.value = p ? p.bis : '';
			bis.min = von.value || today();
			if ( dauer ) {
				dauer.textContent = p ? daysLabel( p.tage ) : t.keinZeitraum;
			}
		}

		function change() {
			bis.min = von.value || today();
			if ( von.value && ! bis.value ) {
				bis.value = von.value;
			}
			if ( ! von.value || ! bis.value ) {
				return;
			}
			if ( bis.value < von.value || von.value < today() ) {
				fehler.textContent = t.ungueltig;
				fehler.hidden = false;
				return;
			}
			fehler.hidden = true;
			if ( onValid ) {
				onValid( von.value, bis.value );
			} else {
				Period.set( von.value, bis.value );
			}
		}

		von.addEventListener( 'change', change );
		bis.addEventListener( 'change', change );
		document.addEventListener( 'cinerent:zeitraum', fill );
		fill();
	}

	function initZeitraum( root ) {
		var reload = '1' === root.dataset.reload;
		bindPeriodInputs( root, function ( von, bis ) {
			Period.set( von, bis );
			if ( reload ) {
				window.location.reload();
			}
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Widget: Verfügbarkeits-Badge (gesammelte REST-Abfrage)              */
	/* ------------------------------------------------------------------ */

	var badgeTimer = null;

	function setBadge( badge, status ) {
		var texte = {};
		try {
			texte = JSON.parse( badge.dataset.texte || '{}' );
		} catch ( e ) {}
		[ 'frei', 'belegt', 'anfrage' ].forEach( function ( s ) {
			badge.classList.toggle( 'cr-badge--' + s, s === status );
		} );
		badge.querySelector( '.cr-badge__text' ).textContent = texte[ status ] || t[ status ] || '';
	}

	function refreshBadges() {
		clearTimeout( badgeTimer );
		badgeTimer = setTimeout( function () {
			var badges = Array.prototype.slice.call( document.querySelectorAll( '[data-cr="badge"][data-id]:not([data-id=""])' ) );
			if ( ! badges.length ) {
				return;
			}
			var p = Period.get();
			if ( ! cfg.verfuegbarkeit || ! p ) {
				badges.forEach( function ( b ) {
					setBadge( b, 'anfrage' );
				} );
				return;
			}
			var ids = badges
				.map( function ( b ) {
					return b.dataset.id;
				} )
				.filter( function ( id, i, all ) {
					return all.indexOf( id ) === i;
				} );
			var url = api( 'verfuegbarkeit', { ids: ids.slice( 0, 100 ).join( ',' ), von: p.von, bis: p.bis } );
			fetch( url, { credentials: 'same-origin' } )
				.then( function ( r ) {
					return r.json();
				} )
				.then( function ( data ) {
					badges.forEach( function ( b ) {
						setBadge( b, ( data.status && data.status[ b.dataset.id ] ) || 'anfrage' );
					} );
				} )
				.catch( function () {} );
		}, 50 );
	}

	/* ------------------------------------------------------------------ */
	/* Dialog: Zeitraum abfragen                                           */
	/* ------------------------------------------------------------------ */

	function askPeriod( done ) {
		var dialog = el( 'dialog', 'cr-dialog' );
		dialog.setAttribute( 'aria-label', t.zeitraumWaehlen );
		dialog.innerHTML =
			'<form method="dialog" class="cr-dialog__inhalt">' +
			'<p class="cr-dialog__titel"></p>' +
			'<div class="cr-zeitraum cr-zeitraum--gross"><div class="cr-zeitraum__felder">' +
			'<label class="cr-zeitraum__feld"><span class="cr-zeitraum__label"></span><input class="cr-input" type="date" data-cr-von required></label>' +
			'<label class="cr-zeitraum__feld"><span class="cr-zeitraum__label"></span><input class="cr-input" type="date" data-cr-bis required></label>' +
			'</div><span class="cr-zeitraum__dauer" data-cr-dauer></span><p class="cr-zeitraum__fehler" data-cr-fehler role="alert" hidden></p></div>' +
			'<div class="cr-dialog__aktionen"><button type="button" class="cr-button cr-button--ghost" value="abbrechen"></button><button type="submit" class="cr-button" value="ok"></button></div>' +
			'</form>';
		dialog.querySelector( '.cr-dialog__titel' ).textContent = t.zeitraumWaehlen;
		var labels = dialog.querySelectorAll( '.cr-zeitraum__label' );
		labels[ 0 ].textContent = t.abholung || 'Abholung';
		labels[ 1 ].textContent = t.rueckgabe || 'Rückgabe';
		var buttons = dialog.querySelectorAll( 'button' );
		buttons[ 0 ].textContent = t.abbrechen || 'Abbrechen';
		buttons[ 1 ].textContent = t.uebernehmen || 'Übernehmen';

		var chosen = null;
		bindPeriodInputs( dialog, function ( von, bis ) {
			chosen = { von: von, bis: bis };
			dialog.querySelector( '[data-cr-dauer]' ).textContent = daysLabel( rentalDays( von, bis ) );
		} );

		buttons[ 0 ].addEventListener( 'click', function () {
			dialog.close();
		} );
		dialog.querySelector( 'form' ).addEventListener( 'submit', function ( e ) {
			if ( ! chosen ) {
				e.preventDefault();
				dialog.querySelector( '[data-cr-fehler]' ).textContent = t.zeitraumWaehlen;
				dialog.querySelector( '[data-cr-fehler]' ).hidden = false;
				return;
			}
			Period.set( chosen.von, chosen.bis );
			done();
		} );
		dialog.addEventListener( 'close', function () {
			dialog.remove();
		} );

		document.body.appendChild( dialog );
		if ( 'function' === typeof dialog.showModal ) {
			dialog.showModal();
		} else {
			dialog.setAttribute( 'open', '' );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Widget: Button "Zur Anfrageliste"                                   */
	/* ------------------------------------------------------------------ */

	function initAdd( button ) {
		var item = {};
		try {
			item = JSON.parse( button.dataset.item || '{}' );
		} catch ( e ) {}
		if ( ! item.id ) {
			return;
		}
		var label = button.querySelector( '.cr-button__text' );
		var original = label.textContent;

		function state() {
			var on = List.has( item.id );
			button.classList.toggle( 'is-added', on );
			label.textContent = on ? button.dataset.textOk || t.hinzugefuegt : original;
		}

		function add() {
			List.add( {
				id: item.id,
				name: item.name,
				url: item.url,
				bild: item.bild,
				preis_tag_cent: item.preis_tag_cent,
				preis_woche_cent: item.preis_woche_cent,
			} );
		}

		button.addEventListener( 'click', function () {
			if ( Period.get() ) {
				add();
			} else {
				askPeriod( add );
			}
		} );
		document.addEventListener( 'cinerent:liste', state );
		state();
	}

	/* ------------------------------------------------------------------ */
	/* Widget: Anfrageliste (mini, voll, im Formular)                      */
	/* ------------------------------------------------------------------ */

	function initListe( root ) {
		var variante = root.dataset.variante;

		if ( 'mini' === variante ) {
			var counter = root.querySelector( '[data-cr-zaehler]' );
			var updateMini = function () {
				var n = List.count();
				counter.textContent = n;
				root.classList.toggle( 'is-leer', 0 === n );
			};
			document.addEventListener( 'cinerent:liste', updateMini );
			updateMini();
			return;
		}

		var list = root.querySelector( '[data-cr-eintraege]' ),
			sum = root.querySelector( '[data-cr-summe]' ),
			info = root.querySelector( '[data-cr-zeitraum-info]' ),
			hint = root.querySelector( '[data-cr-hinweis]' ),
			next = root.querySelector( '[data-cr-weiter]' ),
			bilder = '1' === root.dataset.bilder;

		function render() {
			var items = List.get(),
				p = Period.get(),
				total = 0;

			list.textContent = '';
			info.textContent = p ? formatDate( p.von ) + ' – ' + formatDate( p.bis ) + ' · ' + daysLabel( p.tage ) : t.zeitraumWaehlen;
			info.classList.toggle( 'is-fehlt', ! p );

			if ( ! items.length ) {
				list.appendChild( el( 'li', 'cr-liste__leer', t.leer ) );
				sum.hidden = true;
				hint.hidden = true;
				if ( next ) {
					next.hidden = true;
				}
				return;
			}

			items.forEach( function ( item ) {
				var li = el( 'li', 'cr-liste__eintrag' );
				if ( bilder && item.bild ) {
					var img = el( 'img', 'cr-liste__bild' );
					img.src = item.bild;
					img.alt = '';
					img.loading = 'lazy';
					li.appendChild( img );
				}
				var name = el( 'a', 'cr-liste__name', item.name );
				name.href = item.url;
				li.appendChild( name );

				var menge = el( 'span', 'cr-liste__menge' );
				var minus = el( 'button', 'cr-liste__knopf' );
				minus.type = 'button';
				minus.setAttribute( 'aria-label', t.menge + ' −' );
				minus.appendChild( icon( 'minus' ) );
				minus.addEventListener( 'click', function () {
					if ( ( item.menge || 1 ) <= 1 ) {
						List.remove( item.id );
					} else {
						List.setMenge( item.id, item.menge - 1 );
					}
				} );
				var plus = el( 'button', 'cr-liste__knopf' );
				plus.type = 'button';
				plus.setAttribute( 'aria-label', t.menge + ' +' );
				plus.appendChild( icon( 'plus' ) );
				plus.addEventListener( 'click', function () {
					List.setMenge( item.id, ( item.menge || 1 ) + 1 );
				} );
				menge.appendChild( minus );
				menge.appendChild( el( 'span', 'cr-liste__anzahl', String( item.menge || 1 ) ) );
				menge.appendChild( plus );
				li.appendChild( menge );

				if ( p ) {
					var value = estimate( item.preis_tag_cent, item.preis_woche_cent, p.tage ) * ( item.menge || 1 );
					total += value;
					li.appendChild( el( 'span', 'cr-preis cr-liste__preis', price( value ) ) );
				}

				var remove = el( 'button', 'cr-liste__entfernen' );
				remove.type = 'button';
				remove.setAttribute( 'aria-label', t.entfernen + ': ' + item.name );
				remove.appendChild( icon( 'schliessen' ) );
				remove.addEventListener( 'click', function () {
					List.remove( item.id );
				} );
				li.appendChild( remove );

				list.appendChild( li );
			} );

			sum.hidden = ! p;
			hint.hidden = ! p;
			if ( p ) {
				sum.textContent = '';
				sum.appendChild( el( 'span', 'cr-liste__summe-label', t.summe ) );
				sum.appendChild( el( 'span', 'cr-preis', price( total ) ) );
				hint.textContent = t.richtwertHinweis;
			}
			if ( next ) {
				next.hidden = false;
			}
		}

		document.addEventListener( 'cinerent:liste', render );
		document.addEventListener( 'cinerent:zeitraum', render );
		render();
	}

	/* ------------------------------------------------------------------ */
	/* Widget: Verfügbarkeitskalender                                      */
	/* ------------------------------------------------------------------ */

	function initKalender( root ) {
		var id = root.dataset.id,
			monate = parseInt( root.dataset.monate, 10 ) || 1,
			auswahl = '1' === root.dataset.auswahl,
			container = root.querySelector( '[data-cr-monate]' ),
			hint = root.querySelector( '[data-cr-hinweis]' ),
			now = new Date(),
			cursor = new Date( now.getFullYear(), now.getMonth(), 1 ),
			belegt = {},
			pending = null;

		var p0 = Period.get();
		if ( p0 ) {
			var start = parseYmd( p0.von );
			cursor = new Date( start.getFullYear(), start.getMonth(), 1 );
		}

		var monthFormat = new Intl.DateTimeFormat( 'de-DE', { month: 'long', year: 'numeric' } );

		function monthKey( d ) {
			return d.getFullYear() + '-' + pad( d.getMonth() + 1 );
		}

		function load() {
			if ( ! id ) {
				render();
				return;
			}
			var requests = [];
			for ( var i = 0; i < monate; i++ ) {
				var m = new Date( cursor.getFullYear(), cursor.getMonth() + i, 1 );
				requests.push(
					fetch( api( 'kalender', { id: id, monat: monthKey( m ) } ), { credentials: 'same-origin' } ).then( function ( r ) {
						return r.json();
					} )
				);
			}
			Promise.all( requests )
				.then( function ( results ) {
					var anfrage = false;
					results.forEach( function ( res ) {
						if ( ! res || 'anfrage' === res.status ) {
							anfrage = true;
							return;
						}
						( res.belegt || [] ).forEach( function ( day ) {
							belegt[ day ] = true;
						} );
					} );
					hint.hidden = ! anfrage;
					hint.textContent = anfrage ? t.kalenderAnfrage : '';
					render();
				} )
				.catch( render );
		}

		function render() {
			var p = Period.get(),
				heute = today();
			container.textContent = '';

			for ( var i = 0; i < monate; i++ ) {
				var first = new Date( cursor.getFullYear(), cursor.getMonth() + i, 1 );
				var monat = el( 'div', 'cr-kalender__monat' );
				monat.appendChild( el( 'p', 'cr-kalender__monat-titel', monthFormat.format( first ) ) );

				var grid = el( 'div', 'cr-kalender__raster' );
				grid.setAttribute( 'role', 'grid' );
				( t.wochentage || [] ).forEach( function ( w ) {
					grid.appendChild( el( 'span', 'cr-kalender__wochentag', w ) );
				} );

				var offset = ( first.getDay() + 6 ) % 7;
				for ( var o = 0; o < offset; o++ ) {
					grid.appendChild( el( 'span', 'cr-kalender__leer' ) );
				}

				var daysInMonth = new Date( first.getFullYear(), first.getMonth() + 1, 0 ).getDate();
				for ( var d = 1; d <= daysInMonth; d++ ) {
					var day = ymd( new Date( first.getFullYear(), first.getMonth(), d ) );
					var cell = el( auswahl ? 'button' : 'span', 'cr-kalender__tag', String( d ) );
					if ( auswahl ) {
						cell.type = 'button';
					}
					cell.dataset.tag = day;
					if ( day < heute ) {
						cell.classList.add( 'is-vergangen' );
						cell.disabled = true;
					}
					if ( day === heute ) {
						cell.classList.add( 'is-heute' );
					}
					if ( belegt[ day ] ) {
						cell.classList.add( 'is-belegt' );
						cell.setAttribute( 'aria-label', d + ' ' + t.belegt );
					}
					var von = pending || ( p && p.von ),
						bis = pending ? pending : p && p.bis;
					if ( von && bis && day >= von && day <= bis ) {
						cell.classList.add( 'is-auswahl' );
						if ( day === von ) {
							cell.classList.add( 'is-start' );
						}
						if ( day === bis ) {
							cell.classList.add( 'is-ende' );
						}
					}
					grid.appendChild( cell );
				}
				monat.appendChild( grid );
				container.appendChild( monat );
			}
		}

		if ( auswahl ) {
			container.addEventListener( 'click', function ( e ) {
				var cell = e.target.closest( '.cr-kalender__tag' );
				if ( ! cell || cell.disabled ) {
					return;
				}
				var day = cell.dataset.tag;
				if ( ! pending ) {
					pending = day;
					render();
					return;
				}
				var von = day < pending ? day : pending,
					bis = day < pending ? pending : day;
				pending = null;
				Period.set( von, bis );
			} );
		}

		root.querySelector( '[data-cr-zurueck]' ).addEventListener( 'click', function () {
			cursor = new Date( cursor.getFullYear(), cursor.getMonth() - 1, 1 );
			load();
		} );
		root.querySelector( '[data-cr-weiter]' ).addEventListener( 'click', function () {
			cursor = new Date( cursor.getFullYear(), cursor.getMonth() + 1, 1 );
			load();
		} );
		document.addEventListener( 'cinerent:zeitraum', render );
		load();
	}

	/* ------------------------------------------------------------------ */
	/* Initialisierung                                                     */
	/* ------------------------------------------------------------------ */

	var handlers = {
		zeitraum: initZeitraum,
		add: initAdd,
		liste: initListe,
		kalender: initKalender,
	};

	function init( scope ) {
		var root = scope || document;
		var found = false;
		Object.keys( handlers ).forEach( function ( key ) {
			root.querySelectorAll( '[data-cr="' + key + '"]:not([data-cr-init])' ).forEach( function ( node ) {
				node.setAttribute( 'data-cr-init', '1' );
				handlers[ key ]( node );
			} );
		} );
		if ( root.querySelector( '[data-cr="badge"]' ) || root.matches && root.matches( '[data-cr="badge"]' ) ) {
			found = true;
		}
		if ( found ) {
			refreshBadges();
		}
	}

	document.addEventListener( 'cinerent:zeitraum', refreshBadges );

	// Loop Grid lädt bei Paginierung/"Mehr laden" nach: neue Elemente initialisieren.
	if ( 'MutationObserver' in window ) {
		new MutationObserver( function ( mutations ) {
			mutations.forEach( function ( m ) {
				m.addedNodes.forEach( function ( node ) {
					if ( 1 === node.nodeType ) {
						init( node );
					}
				} );
			} );
		} ).observe( document.documentElement, { childList: true, subtree: true } );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			init();
		} );
	} else {
		init();
	}

	// Elementor-Editor: Widgets nach jedem Rendern initialisieren.
	window.addEventListener( 'elementor/frontend/init', function () {
		if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
			window.elementorFrontend.hooks.addAction( 'frontend/element_ready/global', function ( $scope ) {
				init( $scope[ 0 ] );
			} );
		}
	} );

	window.CineRent = {
		period: Period,
		list: List,
		util: {
			api: api,
			rentalDays: rentalDays,
			estimate: estimate,
			price: price,
			formatDate: formatDate,
			daysLabel: daysLabel,
			el: el,
		},
		init: init,
	};
} )();
