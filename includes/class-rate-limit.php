<?php
/**
 * Rate-Limit je IP über Transients.
 *
 * @package CineRent\Shop
 */

namespace CineRent\Shop;

defined( 'ABSPATH' ) || exit;

/**
 * Zählt Anfragen je IP in einem Zeitfenster. Die IP wird nur als Hash gespeichert.
 */
final class Rate_Limit {

	/**
	 * IP des Besuchers. Standard: REMOTE_ADDR. Hinter einem Proxy über den Filter
	 * "cinerent_shop_client_ip" anpassen (Header nicht ungeprüft vertrauen).
	 *
	 * @return string
	 */
	private static function ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		/**
		 * IP-Adresse für das Rate-Limit.
		 *
		 * @param string $ip IP.
		 */
		return (string) apply_filters( 'cinerent_shop_client_ip', $ip );
	}

	/**
	 * Transient-Schlüssel.
	 *
	 * @param string $bucket Bereich, z. B. "anfrage".
	 * @return string
	 */
	private static function key( $bucket ) {
		return 'cinerent_rl_' . substr( hash_hmac( 'sha256', $bucket . '|' . self::ip(), wp_salt( 'nonce' ) ), 0, 32 );
	}

	/**
	 * Ob das Limit erreicht ist.
	 *
	 * @param string $bucket Bereich.
	 * @param int    $limit  Höchstzahl im Fenster.
	 * @return bool
	 */
	public static function exceeded( $bucket, $limit ) {
		$entry = get_transient( self::key( $bucket ) );
		return is_array( $entry ) && (int) $entry['n'] >= (int) $limit;
	}

	/**
	 * Zählt einen Versuch.
	 *
	 * @param string $bucket Bereich.
	 * @param int    $window Fenster in Sekunden.
	 */
	public static function hit( $bucket, $window = HOUR_IN_SECONDS ) {
		$key   = self::key( $bucket );
		$entry = get_transient( $key );
		if ( ! is_array( $entry ) || (int) $entry['bis'] < time() ) {
			$entry = array(
				'n'   => 0,
				'bis' => time() + $window,
			);
		}
		++$entry['n'];
		set_transient( $key, $entry, max( 1, (int) $entry['bis'] - time() ) );
	}
}
