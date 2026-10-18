<?php
/**
 * Datenquelle: lokale JSON-Datei.
 *
 * @package CineRent\Shop
 */

namespace CineRent\Shop\Data;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Liest data/katalog.json. Verfügbarkeit ist in dieser Phase immer "auf Anfrage".
 */
class Local_Json_Source implements Data_Source {

	/**
	 * Pfad zur JSON-Datei.
	 *
	 * @var string
	 */
	private $file;

	/**
	 * Geparste Daten (pro Request gecacht).
	 *
	 * @var array<string, mixed>|WP_Error|null
	 */
	private $data = null;

	/**
	 * Konstruktor.
	 *
	 * @param string $file Pfad zur JSON-Datei.
	 */
	public function __construct( $file ) {
		/**
		 * Pfad zur Katalog-Datei, z. B. um eine Datei außerhalb des Plugins zu nutzen.
		 *
		 * @param string $file Pfad.
		 */
		$this->file = apply_filters( 'cinerent_shop_local_json_file', $file );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_id() {
		return 'local_json';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return __( 'Lokale JSON-Datei', 'cinerent-shop' );
	}

	/**
	 * Lädt und parst die Datei.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private function load() {
		if ( null !== $this->data ) {
			return $this->data;
		}

		if ( ! is_readable( $this->file ) ) {
			$this->data = new WP_Error( 'cinerent_json_missing', __( 'Die Katalog-Datei wurde nicht gefunden.', 'cinerent-shop' ) );
			return $this->data;
		}

		// Lokale Datei, kein Remote-Aufruf.
		$json = json_decode( (string) file_get_contents( $this->file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_array( $json ) ) {
			$this->data = new WP_Error( 'cinerent_json_invalid', __( 'Die Katalog-Datei enthält kein gültiges JSON.', 'cinerent-shop' ) );
			return $this->data;
		}

		$this->data = $json;
		return $this->data;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_categories() {
		$data = $this->load();
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$out = array();
		foreach ( (array) ( $data['kategorien'] ?? array() ) as $index => $raw ) {
			$category = Catalog::normalize_category( $raw, (int) $index );
			if ( $category ) {
				$out[] = $category;
			}
		}
		return $out;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_items() {
		$data = $this->load();
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$out = array();
		foreach ( (array) ( $data['items'] ?? array() ) as $raw ) {
			$item = Catalog::normalize_item( $raw );
			if ( $item ) {
				$out[] = $item;
			}
		}
		return $out;
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
		return new WP_Error( 'cinerent_not_supported', __( 'Diese Datenquelle nimmt keine Anfragen an.', 'cinerent-shop' ) );
	}
}
