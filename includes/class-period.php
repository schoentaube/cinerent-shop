<?php
/**
 * Gewählter Mietzeitraum.
 *
 * @package CineRent\Shop
 */

namespace CineRent\Shop;

defined( 'ABSPATH' ) || exit;

/**
 * Liest und prüft den Zeitraum. Gesetzt wird das Cookie im Browser
 * (assets/js/frontend.js), damit gecachte Seiten funktionieren.
 */
final class Period {

	const COOKIE = 'cinerent_zeitraum';

	/**
	 * Prüft ein Datum im Format Y-m-d.
	 *
	 * @param mixed $value Eingabe.
	 * @return string|null
	 */
	public static function sanitize_date( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return null;
		}
		return $value;
	}

	/**
	 * Prüft einen Zeitraum.
	 *
	 * @param mixed $von Abholung.
	 * @param mixed $bis Rückgabe.
	 * @return array{von:string, bis:string, tage:int}|null
	 */
	public static function validate( $von, $bis ) {
		$von = self::sanitize_date( $von );
		$bis = self::sanitize_date( $bis );
		if ( ! $von || ! $bis || $bis < $von ) {
			return null;
		}
		// Gestern als Puffer für Zeitzonen-Unterschiede zwischen Browser und Server.
		$yesterday = wp_date( 'Y-m-d', time() - DAY_IN_SECONDS );
		if ( $von < $yesterday ) {
			return null;
		}
		return array(
			'von'  => $von,
			'bis'  => $bis,
			'tage' => Format::rental_days( $von, $bis ),
		);
	}

	/**
	 * Zeitraum aus dem Cookie ("Y-m-d_Y-m-d").
	 *
	 * @return array{von:string, bis:string, tage:int}|null
	 */
	public static function current() {
		if ( empty( $_COOKIE[ self::COOKIE ] ) ) {
			return null;
		}
		$raw   = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) );
		$parts = explode( '_', $raw );
		return 2 === count( $parts ) ? self::validate( $parts[0], $parts[1] ) : null;
	}
}
