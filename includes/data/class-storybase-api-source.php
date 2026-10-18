<?php
/**
 * Datenquelle: Storybase-Shop-API (Supabase Edge Functions). Vorerst ein Stub.
 *
 * @package CineRent\Shop
 */

namespace CineRent\Shop\Data;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Platzhalter für die spätere Storybase-Anbindung.
 *
 * Alle Aufrufe laufen serverseitig über request(), der Shop-Schlüssel verlässt
 * den Server nie. Geplante Endpunkte stehen in der README (Abschnitt "Wechsel auf
 * die Storybase-API"). Solange die API nicht angebunden ist, liefern Katalog-Aufrufe
 * einen WP_Error (der Sync bricht dann ab, ohne Beiträge auf Entwurf zu setzen) und
 * die Verfügbarkeit bleibt "auf Anfrage".
 */
class Storybase_Api_Source implements Data_Source {

	/**
	 * Basis-URL der Edge Functions, z. B. https://xyz.supabase.co/functions/v1.
	 *
	 * @var string
	 */
	private $base_url;

	/**
	 * Shop-Schlüssel (nur serverseitig).
	 *
	 * @var string
	 */
	private $shop_key;

	/**
	 * Konstruktor.
	 *
	 * @param string $base_url Basis-URL.
	 * @param string $shop_key Shop-Schlüssel.
	 */
	public function __construct( $base_url, $shop_key ) {
		$this->base_url = untrailingslashit( (string) $base_url );
		$this->shop_key = (string) $shop_key;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_id() {
		return 'storybase';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return __( 'Storybase-API', 'cinerent-shop' );
	}

	/**
	 * Ob URL und Schlüssel hinterlegt sind.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return '' !== $this->base_url && '' !== $this->shop_key;
	}

	/**
	 * Fehler für noch nicht umgesetzte Aufrufe.
	 *
	 * @return WP_Error
	 */
	private function not_implemented() {
		return new WP_Error( 'cinerent_storybase_stub', __( 'Die Storybase-API ist noch nicht angebunden. Bitte vorerst die lokale JSON-Datei als Datenquelle nutzen.', 'cinerent-shop' ) );
	}

	/**
	 * Serverseitiger API-Aufruf. Bereits vorbereitet für die spätere Anbindung.
	 *
	 * @param string               $method   HTTP-Methode.
	 * @param string               $endpoint Pfad relativ zur Basis-URL, z. B. "shop-katalog".
	 * @param array<string, mixed> $query    Query-Parameter.
	 * @param array<string, mixed> $body     JSON-Body für POST.
	 * @return array<string, mixed>|WP_Error Dekodierte Antwort.
	 */
	protected function request( $method, $endpoint, array $query = array(), array $body = array() ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'cinerent_storybase_config', __( 'Storybase-URL oder Shop-Schlüssel fehlen.', 'cinerent-shop' ) );
		}

		$url  = add_query_arg( array_map( 'rawurlencode', $query ), $this->base_url . '/' . ltrim( $endpoint, '/' ) );
		$args = array(
			'method'  => $method,
			'timeout' => 10,
			'headers' => array(
				'Accept'     => 'application/json',
				'x-shop-key' => $this->shop_key,
			),
		);
		if ( 'POST' === $method ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			return new WP_Error(
				'cinerent_storybase_http',
				/* translators: %d: HTTP-Statuscode. */
				sprintf( __( 'Storybase-API antwortet mit Status %d.', 'cinerent-shop' ), $code )
			);
		}
		return $data;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_categories() {
		return $this->not_implemented();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_items() {
		return $this->not_implemented();
	}

	/**
	 * {@inheritDoc}
	 */
	public function supports_availability() {
		return false;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string[] $external_ids Externe IDs.
	 * @param string   $von          Abholung.
	 * @param string   $bis          Rückgabe.
	 */
	public function get_availability( array $external_ids, $von, $bis ) {
		return array_fill_keys( $external_ids, self::ANFRAGE );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $external_id Externe ID.
	 * @param string $monat       Monat.
	 */
	public function get_calendar( $external_id, $monat ) {
		return array(
			'status' => self::ANFRAGE,
			'belegt' => array(),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function supports_inquiries() {
		return false;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $inquiry Anfrage.
	 */
	public function submit_inquiry( array $inquiry ) {
		return $this->not_implemented();
	}
}
