/**
 * CineRent Shop – ALTCHA-Löser (Proof-of-Work, selbst gehostet).
 *
 * Sucht die Zahl n mit SHA-256(salt + n) === challenge und liefert die Lösung
 * im ALTCHA-Format: base64(JSON{algorithm, challenge, number, salt, signature}).
 * Rechnet in einem Web Worker; ist das nicht möglich (z. B. wegen CSP),
 * in kleinen Schritten im Hauptthread.
 *
 * @package CineRent\Shop
 */
( function () {
	'use strict';

	/* Kompakte SHA-256-Implementierung für ASCII-Zeichenketten. */
	function sha256Factory() {
		var K = [];
		var H0 = [];
		( function () {
			function frac( x ) {
				return ( ( x - Math.floor( x ) ) * 4294967296 ) | 0;
			}
			var n = 2,
				count = 0;
			while ( count < 64 ) {
				var prime = true;
				for ( var f = 2; f * f <= n; f++ ) {
					if ( 0 === n % f ) {
						prime = false;
						break;
					}
				}
				if ( prime ) {
					if ( count < 8 ) {
						H0[ count ] = frac( Math.pow( n, 1 / 2 ) );
					}
					K[ count ] = frac( Math.pow( n, 1 / 3 ) );
					count++;
				}
				n++;
			}
		} )();

		var W = new Array( 64 );

		return function sha256( ascii ) {
			var bytes = ascii.length;
			var words = [];
			var i, j;
			for ( i = 0; i < bytes; i++ ) {
				words[ i >> 2 ] |= ( ascii.charCodeAt( i ) & 0xff ) << ( ( 3 - ( i % 4 ) ) * 8 );
			}
			words[ bytes >> 2 ] |= 0x80 << ( ( 3 - ( bytes % 4 ) ) * 8 );
			var total = ( ( ( bytes + 8 ) >> 6 ) + 1 ) * 16;
			for ( i = 0; i < total; i++ ) {
				words[ i ] = words[ i ] | 0;
			}
			words[ total - 1 ] = bytes * 8;

			var h = H0.slice();
			for ( j = 0; j < total; j += 16 ) {
				var a = h[ 0 ],
					b = h[ 1 ],
					c = h[ 2 ],
					d = h[ 3 ],
					e = h[ 4 ],
					f = h[ 5 ],
					g = h[ 6 ],
					hh = h[ 7 ];
				for ( i = 0; i < 64; i++ ) {
					if ( i < 16 ) {
						W[ i ] = words[ j + i ];
					} else {
						var w15 = W[ i - 15 ],
							w2 = W[ i - 2 ];
						var s0 = ( ( w15 >>> 7 ) | ( w15 << 25 ) ) ^ ( ( w15 >>> 18 ) | ( w15 << 14 ) ) ^ ( w15 >>> 3 );
						var s1 = ( ( w2 >>> 17 ) | ( w2 << 15 ) ) ^ ( ( w2 >>> 19 ) | ( w2 << 13 ) ) ^ ( w2 >>> 10 );
						W[ i ] = ( W[ i - 16 ] + s0 + W[ i - 7 ] + s1 ) | 0;
					}
					var S1 = ( ( e >>> 6 ) | ( e << 26 ) ) ^ ( ( e >>> 11 ) | ( e << 21 ) ) ^ ( ( e >>> 25 ) | ( e << 7 ) );
					var ch = ( e & f ) ^ ( ~e & g );
					var t1 = ( hh + S1 + ch + K[ i ] + W[ i ] ) | 0;
					var S0 = ( ( a >>> 2 ) | ( a << 30 ) ) ^ ( ( a >>> 13 ) | ( a << 19 ) ) ^ ( ( a >>> 22 ) | ( a << 10 ) );
					var maj = ( a & b ) ^ ( a & c ) ^ ( b & c );
					var t2 = ( S0 + maj ) | 0;
					hh = g;
					g = f;
					f = e;
					e = ( d + t1 ) | 0;
					d = c;
					c = b;
					b = a;
					a = ( t1 + t2 ) | 0;
				}
				h[ 0 ] = ( h[ 0 ] + a ) | 0;
				h[ 1 ] = ( h[ 1 ] + b ) | 0;
				h[ 2 ] = ( h[ 2 ] + c ) | 0;
				h[ 3 ] = ( h[ 3 ] + d ) | 0;
				h[ 4 ] = ( h[ 4 ] + e ) | 0;
				h[ 5 ] = ( h[ 5 ] + f ) | 0;
				h[ 6 ] = ( h[ 6 ] + g ) | 0;
				h[ 7 ] = ( h[ 7 ] + hh ) | 0;
			}

			var hex = '';
			for ( i = 0; i < 8; i++ ) {
				hex += ( '00000000' + ( h[ i ] >>> 0 ).toString( 16 ) ).slice( -8 );
			}
			return hex;
		};
	}

	/* Läuft im Worker und im Hauptthread. */
	function search( sha256, salt, challenge, from, to ) {
		for ( var n = from; n <= to; n++ ) {
			if ( sha256( salt + n ) === challenge ) {
				return n;
			}
		}
		return -1;
	}

	function encode( data, number ) {
		return window.btoa(
			JSON.stringify( {
				algorithm: data.algorithm,
				challenge: data.challenge,
				number: number,
				salt: data.salt,
				signature: data.signature,
			} )
		);
	}

	function solveInWorker( data ) {
		var source =
			'var sha256 = (' + sha256Factory.toString() + ')();' +
			'var search = ' + search.toString() + ';' +
			'onmessage = function (e) { var d = e.data; postMessage(search(sha256, d.salt, d.challenge, 0, d.maxnumber)); };';
		var url = URL.createObjectURL( new Blob( [ source ], { type: 'text/javascript' } ) );
		return new Promise( function ( resolve, reject ) {
			var worker = new Worker( url );
			worker.onmessage = function ( e ) {
				worker.terminate();
				URL.revokeObjectURL( url );
				resolve( e.data );
			};
			worker.onerror = function ( err ) {
				worker.terminate();
				URL.revokeObjectURL( url );
				reject( err );
			};
			worker.postMessage( { salt: data.salt, challenge: data.challenge, maxnumber: data.maxnumber } );
		} );
	}

	function solveInChunks( data ) {
		var sha256 = sha256Factory();
		var step = 5000;
		return new Promise( function ( resolve ) {
			var from = 0;
			( function next() {
				var to = Math.min( from + step - 1, data.maxnumber );
				var found = search( sha256, data.salt, data.challenge, from, to );
				if ( found >= 0 || to >= data.maxnumber ) {
					resolve( found );
					return;
				}
				from = to + 1;
				setTimeout( next, 0 );
			} )();
		} );
	}

	/**
	 * Löst eine Challenge.
	 *
	 * @param {Object} data Challenge vom Server.
	 * @return {Promise<string>} Payload für das Formular.
	 */
	function solve( data ) {
		var run = 'Worker' in window && 'Blob' in window ? solveInWorker( data ).catch( function () {
			return solveInChunks( data );
		} ) : solveInChunks( data );

		return run.then( function ( number ) {
			if ( number < 0 ) {
				throw new Error( 'altcha' );
			}
			return encode( data, number );
		} );
	}

	window.CineRentAltcha = { solve: solve, sha256: sha256Factory() };
} )();
