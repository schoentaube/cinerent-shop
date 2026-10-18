<?php
/**
 * Verarbeitung von Mietanfragen.
 *
 * @package CineRent\Shop
 */

namespace CineRent\Shop;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Prüft Spamschutz und Eingaben und versendet die Anfrage.
 */
final class Inquiry {

	const NONCE_ACTION = 'cinerent_anfrage';
	const MIN_SECONDS  = 3;
	const MAX_ITEMS    = 50;
	const MAX_MENGE    = 20;

	/**
	 * Signiertes Zeit-Token für die Mindest-Ausfüllzeit.
	 *
	 * @return string
	 */
	public static function create_token() {
		$time = (string) time();
		return $time . '.' . hash_hmac( 'sha256', 'cinerent-form|' . $time, wp_salt( 'nonce' ) );
	}

	/**
	 * Prüft das Zeit-Token: mindestens 3 Sekunden, höchstens 2 Stunden alt.
	 *
	 * @param string $token Token.
	 * @return bool
	 */
	private static function verify_token( $token ) {
		$parts = explode( '.', (string) $token );
		if ( 2 !== count( $parts ) || ! ctype_digit( $parts[0] ) ) {
			return false;
		}
		if ( ! hash_equals( hash_hmac( 'sha256', 'cinerent-form|' . $parts[0], wp_salt( 'nonce' ) ), $parts[1] ) ) {
			return false;
		}
		$age = time() - (int) $parts[0];
		return $age >= self::MIN_SECONDS && $age <= 2 * HOUR_IN_SECONDS;
	}

	/**
	 * Verarbeitet eine Anfrage.
	 *
	 * @param array<string, mixed> $params Formulardaten.
	 * @return true|WP_Error
	 */
	public static function handle( array $params ) {
		// 1. Honeypot: Bots erhalten eine scheinbare Erfolgsmeldung.
		if ( ! empty( $params['website'] ) ) {
			return true;
		}

		// 2. Nonce.
		if ( ! wp_verify_nonce( (string) ( $params['nonce'] ?? '' ), self::NONCE_ACTION ) ) {
			return self::error( 'nonce', __( 'Die Sitzung ist abgelaufen. Bitte die Seite neu laden.', 'cinerent-shop' ), 403 );
		}

		// 3. Mindest-Ausfüllzeit.
		if ( ! self::verify_token( (string) ( $params['token'] ?? '' ) ) ) {
			return self::error( 'zeit', __( 'Das Formular wurde zu schnell oder nach zu langer Zeit abgeschickt. Bitte erneut versuchen.', 'cinerent-shop' ), 400 );
		}

		// 4. Rate-Limit je IP.
		$limit = (int) Settings::get( 'rate_limit' );
		if ( Rate_Limit::exceeded( 'anfrage', $limit ) ) {
			return self::error( 'limit', __( 'Zu viele Anfragen. Bitte später erneut versuchen oder anrufen.', 'cinerent-shop' ), 429 );
		}
		Rate_Limit::hit( 'anfrage' );

		// 5. ALTCHA.
		if ( ! Altcha::verify( (string) ( $params['altcha'] ?? '' ) ) ) {
			return self::error( 'altcha', __( 'Die Spamschutz-Prüfung ist fehlgeschlagen. Bitte erneut versuchen.', 'cinerent-shop' ), 400 );
		}

		$inquiry = self::build( $params );
		if ( is_wp_error( $inquiry ) ) {
			return $inquiry;
		}

		$source = Plugin::instance()->source();
		if ( $source->supports_inquiries() ) {
			$result = $source->submit_inquiry( $inquiry );
		} else {
			$result = self::send_mails( $inquiry );
		}

		if ( true === $result ) {
			/**
			 * Nach erfolgreich verarbeiteter Anfrage.
			 *
			 * @param array<string, mixed> $inquiry Anfrage.
			 */
			do_action( 'cinerent_shop_inquiry_sent', $inquiry );
		}
		return $result;
	}

	/**
	 * Fehler mit HTTP-Status.
	 *
	 * @param string $code    Code.
	 * @param string $message Meldung.
	 * @param int    $status  HTTP-Status.
	 * @param string $field   Betroffenes Feld.
	 * @return WP_Error
	 */
	private static function error( $code, $message, $status, $field = '' ) {
		return new WP_Error(
			'cinerent_' . $code,
			$message,
			array(
				'status' => $status,
				'feld'   => $field,
			)
		);
	}

	/**
	 * Bereinigt und prüft die Felder. Gerätenamen und Preise kommen aus WordPress,
	 * nicht aus dem Browser.
	 *
	 * @param array<string, mixed> $params Formulardaten.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function build( array $params ) {
		$name  = sanitize_text_field( (string) ( $params['name'] ?? '' ) );
		$email = sanitize_email( (string) ( $params['email'] ?? '' ) );

		if ( '' === $name ) {
			return self::error( 'name', __( 'Bitte den Namen angeben.', 'cinerent-shop' ), 400, 'name' );
		}
		if ( ! is_email( $email ) ) {
			return self::error( 'email', __( 'Bitte eine gültige E-Mail-Adresse angeben.', 'cinerent-shop' ), 400, 'email' );
		}
		if ( empty( $params['mietbedingungen'] ) ) {
			return self::error( 'mietbedingungen', __( 'Bitte den Mietbedingungen zustimmen.', 'cinerent-shop' ), 400, 'mietbedingungen' );
		}
		if ( empty( $params['datenschutz'] ) ) {
			return self::error( 'datenschutz', __( 'Bitte der Datenschutzerklärung zustimmen.', 'cinerent-shop' ), 400, 'datenschutz' );
		}

		$period = Period::validate( $params['von'] ?? '', $params['bis'] ?? '' );
		if ( ! $period ) {
			return self::error( 'zeitraum', __( 'Bitte einen gültigen Mietzeitraum wählen.', 'cinerent-shop' ), 400, 'zeitraum' );
		}

		$items = array();
		$total = 0;
		foreach ( array_slice( (array) ( $params['items'] ?? array() ), 0, self::MAX_ITEMS ) as $row ) {
			if ( ! is_array( $row ) || empty( $row['id'] ) ) {
				continue;
			}
			$post_id = Post_Type::find_by_external_id( sanitize_text_field( (string) $row['id'] ) );
			if ( ! $post_id || 'publish' !== get_post_status( $post_id ) ) {
				continue;
			}
			$item     = Post_Type::get_item( $post_id );
			$menge    = min( self::MAX_MENGE, max( 1, (int) ( $row['menge'] ?? 1 ) ) );
			$estimate = Format::estimate( $item['preis_tag_cent'], $item['preis_woche_cent'], $period['tage'] ) * $menge;
			$total   += $estimate;
			$items[]  = array(
				'id'             => $item['id'],
				'name'           => $item['name'],
				'url'            => $item['url'],
				'menge'          => $menge,
				'richtwert_cent' => $estimate,
				'kaution_cent'   => $item['kaution_cent'] * $menge,
			);
		}
		if ( ! $items ) {
			return self::error( 'liste', __( 'Die Anfrageliste ist leer.', 'cinerent-shop' ), 400, 'liste' );
		}

		$lieferung = ( isset( $params['lieferung'] ) && 'versand' === $params['lieferung'] ) ? 'versand' : 'abholung';

		return array(
			'firma'          => sanitize_text_field( (string) ( $params['firma'] ?? '' ) ),
			'name'           => $name,
			'email'          => $email,
			'telefon'        => sanitize_text_field( (string) ( $params['telefon'] ?? '' ) ),
			'lieferung'      => $lieferung,
			'nachricht'      => sanitize_textarea_field( (string) ( $params['nachricht'] ?? '' ) ),
			'von'            => $period['von'],
			'bis'            => $period['bis'],
			'tage'           => $period['tage'],
			'items'          => $items,
			'richtwert_cent' => $total,
			'zustimmung'     => array(
				'mietbedingungen' => true,
				'datenschutz'     => true,
				'zeit'            => gmdate( 'c' ),
			),
		);
	}

	/**
	 * Zusammenfassung als Text.
	 *
	 * @param array<string, mixed> $inquiry Anfrage.
	 * @return string
	 */
	private static function summary( array $inquiry ) {
		$lines   = array();
		$lines[] = sprintf(
			/* translators: 1: Abholung, 2: Rückgabe, 3: Anzahl Tage. */
			__( 'Zeitraum: %1$s bis %2$s (%3$d Miettage)', 'cinerent-shop' ),
			Format::date( $inquiry['von'] ),
			Format::date( $inquiry['bis'] ),
			$inquiry['tage']
		);
		$lines[] = sprintf(
			/* translators: %s: Abholung oder Versand. */
			__( 'Übergabe: %s', 'cinerent-shop' ),
			'versand' === $inquiry['lieferung'] ? __( 'Versand', 'cinerent-shop' ) : __( 'Abholung', 'cinerent-shop' )
		);
		$lines[] = '';
		foreach ( $inquiry['items'] as $item ) {
			$lines[] = sprintf( '%d × %s – %s', $item['menge'], $item['name'], Format::price( $item['richtwert_cent'] ) );
		}
		$lines[] = '';
		$lines[] = sprintf(
			/* translators: %s: Betrag. */
			__( 'Unverbindlicher Richtwert: %s', 'cinerent-shop' ),
			Format::price( $inquiry['richtwert_cent'] )
		);
		return implode( "\n", $lines );
	}

	/**
	 * Versendet die Anfrage per wp_mail an den Betreiber und eine Bestätigung an den Kunden.
	 *
	 * @param array<string, mixed> $inquiry Anfrage.
	 * @return true|WP_Error
	 */
	private static function send_mails( array $inquiry ) {
		$to          = (string) Settings::get( 'anfrage_empfaenger' );
		$sender_name = (string) Settings::get( 'absender_name' );
		$summary     = self::summary( $inquiry );

		$set_name = static function () use ( $sender_name ) {
			return $sender_name;
		};
		if ( '' !== $sender_name ) {
			add_filter( 'wp_mail_from_name', $set_name );
		}

		$body = implode(
			"\n",
			array(
				__( 'Neue Mietanfrage über die Website', 'cinerent-shop' ),
				'',
				/* translators: %s: Firma. */
				sprintf( __( 'Firma: %s', 'cinerent-shop' ), $inquiry['firma'] ),
				/* translators: %s: Name. */
				sprintf( __( 'Name: %s', 'cinerent-shop' ), $inquiry['name'] ),
				/* translators: %s: E-Mail. */
				sprintf( __( 'E-Mail: %s', 'cinerent-shop' ), $inquiry['email'] ),
				/* translators: %s: Telefon. */
				sprintf( __( 'Telefon: %s', 'cinerent-shop' ), $inquiry['telefon'] ),
				'',
				$summary,
				'',
				__( 'Nachricht:', 'cinerent-shop' ),
				$inquiry['nachricht'],
				'',
				/* translators: %s: Zeitpunkt (ISO 8601). */
				sprintf( __( 'Zustimmung zu Mietbedingungen und Datenschutz erteilt am %s.', 'cinerent-shop' ), $inquiry['zustimmung']['zeit'] ),
			)
		);

		$sent = wp_mail(
			$to,
			/* translators: 1: Name, 2: Abholdatum. */
			sprintf( __( 'Mietanfrage: %1$s ab %2$s', 'cinerent-shop' ), $inquiry['name'], Format::date( $inquiry['von'] ) ),
			$body,
			array( 'Reply-To: ' . $inquiry['name'] . ' <' . $inquiry['email'] . '>' )
		);

		if ( $sent ) {
			$confirmation = implode(
				"\n",
				array(
					/* translators: %s: Name. */
					sprintf( __( 'Hallo %s,', 'cinerent-shop' ), $inquiry['name'] ),
					'',
					__( 'vielen Dank für Ihre Anfrage. Wir prüfen die Verfügbarkeit und melden uns zeitnah mit einem verbindlichen Angebot.', 'cinerent-shop' ),
					'',
					$summary,
					'',
					__( 'Der Richtwert ist unverbindlich. Maßgeblich ist unser Angebot.', 'cinerent-shop' ),
					'',
					$sender_name,
				)
			);
			wp_mail(
				$inquiry['email'],
				/* translators: %s: Absendername. */
				sprintf( __( 'Ihre Mietanfrage bei %s', 'cinerent-shop' ), $sender_name ),
				$confirmation,
				array( 'Reply-To: ' . $to )
			);
		}

		remove_filter( 'wp_mail_from_name', $set_name );

		return $sent ? true : self::error( 'mail', __( 'Die Anfrage konnte nicht versendet werden. Bitte rufen Sie uns an oder schreiben Sie uns direkt.', 'cinerent-shop' ), 500 );
	}
}
