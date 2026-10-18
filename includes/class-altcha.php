<?php
/**
 * Selbst gehostetes ALTCHA (Proof-of-Work), kompatibel zum ALTCHA-Protokoll v1.
 *
 * @package CineRent\Shop
 */

namespace CineRent\Shop;

defined( 'ABSPATH' ) || exit;

/**
 * Erzeugt und prüft Challenges. Keine externen Dienste, keine Cookies.
 *
 * Challenge: challenge = SHA-256(salt + number), signature = HMAC-SHA-256(challenge, key).
 * Der Browser sucht number durch Ausprobieren (assets/js/altcha.js) und sendet
 * base64(JSON{algorithm, challenge, number, salt, signature}) zurück.
 */
final class Altcha {

	const ALGORITHM = 'SHA-256';
	const LIFETIME  = 20 * MINUTE_IN_SECONDS;

	/**
	 * HMAC-Schlüssel, abgeleitet aus den WordPress-Salts.
	 *
	 * @return string
	 */
	private static function key() {
		return hash_hmac( 'sha256', 'cinerent-altcha', wp_salt( 'nonce' ) );
	}

	/**
	 * Neue Challenge.
	 *
	 * @return array{algorithm:string, challenge:string, maxnumber:int, salt:string, signature:string}
	 */
	public static function create() {
		$max       = (int) Settings::get( 'altcha_komplexitaet' );
		$salt      = bin2hex( random_bytes( 12 ) ) . '?expires=' . ( time() + self::LIFETIME );
		$number    = random_int( (int) floor( $max / 10 ), $max );
		$challenge = hash( 'sha256', $salt . $number );

		return array(
			'algorithm' => self::ALGORITHM,
			'challenge' => $challenge,
			'maxnumber' => $max,
			'salt'      => $salt,
			'signature' => hash_hmac( 'sha256', $challenge, self::key() ),
		);
	}

	/**
	 * Prüft die Lösung und verhindert die Wiederverwendung.
	 *
	 * @param string $payload Base64-kodiertes JSON.
	 * @return bool
	 */
	public static function verify( $payload ) {
		$data = json_decode( (string) base64_decode( (string) $payload, true ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Teil des ALTCHA-Protokolls.
		if ( ! is_array( $data ) ) {
			return false;
		}
		foreach ( array( 'algorithm', 'challenge', 'number', 'salt', 'signature' ) as $field ) {
			if ( ! isset( $data[ $field ] ) || ! is_scalar( $data[ $field ] ) ) {
				return false;
			}
		}
		if ( self::ALGORITHM !== $data['algorithm'] ) {
			return false;
		}

		$salt = (string) $data['salt'];
		if ( ! preg_match( '/[?&]expires=(\d+)/', $salt, $m ) || (int) $m[1] < time() ) {
			return false;
		}

		$challenge = hash( 'sha256', $salt . (int) $data['number'] );
		if ( ! hash_equals( $challenge, (string) $data['challenge'] ) ) {
			return false;
		}
		if ( ! hash_equals( hash_hmac( 'sha256', $challenge, self::key() ), (string) $data['signature'] ) ) {
			return false;
		}

		$used = 'cinerent_altcha_' . md5( $challenge );
		if ( get_transient( $used ) ) {
			return false;
		}
		set_transient( $used, 1, self::LIFETIME );
		return true;
	}
}
