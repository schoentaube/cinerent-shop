<?php
/**
 * Normalisierung von Katalogdaten.
 *
 * @package CineRent\Shop
 */

namespace CineRent\Shop\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Bringt Rohdaten jeder Quelle in ein einheitliches, bereinigtes Format.
 */
final class Catalog {

	/**
	 * Normalisiert eine Kategorie.
	 *
	 * @param array<string, mixed> $raw Rohdaten.
	 * @param int                  $index Position als Fallback für die Reihenfolge.
	 * @return array{id:string, slug:string, name:string, reihenfolge:int}|null
	 */
	public static function normalize_category( $raw, $index = 0 ) {
		if ( ! is_array( $raw ) || empty( $raw['id'] ) || empty( $raw['name'] ) ) {
			return null;
		}
		$name = sanitize_text_field( (string) $raw['name'] );
		return array(
			'id'          => sanitize_text_field( (string) $raw['id'] ),
			'slug'        => sanitize_title( ! empty( $raw['slug'] ) ? (string) $raw['slug'] : $name ),
			'name'        => $name,
			'reihenfolge' => isset( $raw['reihenfolge'] ) ? (int) $raw['reihenfolge'] : (int) $index,
		);
	}

	/**
	 * Normalisiert ein Item.
	 *
	 * @param array<string, mixed> $raw Rohdaten.
	 * @return array<string, mixed>|null Null, wenn Pflichtfelder fehlen.
	 */
	public static function normalize_item( $raw ) {
		if ( ! is_array( $raw ) || empty( $raw['id'] ) || empty( $raw['name'] ) ) {
			return null;
		}

		$name = sanitize_text_field( (string) $raw['name'] );

		$technische_daten = array();
		foreach ( (array) ( $raw['technische_daten'] ?? array() ) as $row ) {
			if ( is_array( $row ) && isset( $row['label'], $row['wert'] ) && '' !== trim( (string) $row['label'] ) ) {
				$technische_daten[] = array(
					'label' => sanitize_text_field( (string) $row['label'] ),
					'wert'  => sanitize_text_field( (string) $row['wert'] ),
				);
			}
		}

		$kit_inhalt = array();
		foreach ( (array) ( $raw['kit_inhalt'] ?? array() ) as $row ) {
			if ( is_array( $row ) && ! empty( $row['name'] ) ) {
				$kit_inhalt[] = array(
					'name'  => sanitize_text_field( (string) $row['name'] ),
					'menge' => max( 1, (int) ( $row['menge'] ?? 1 ) ),
				);
			}
		}

		$bilder = array();
		foreach ( (array) ( $raw['bilder'] ?? array() ) as $bild ) {
			$bild = trim( (string) $bild );
			if ( '' !== $bild ) {
				$bilder[] = $bild;
			}
		}

		return array(
			'id'               => sanitize_text_field( (string) $raw['id'] ),
			'slug'             => sanitize_title( ! empty( $raw['slug'] ) ? (string) $raw['slug'] : $name ),
			'name'             => $name,
			'kategorie'        => sanitize_text_field( (string) ( $raw['kategorie'] ?? '' ) ),
			'kurztext'         => sanitize_textarea_field( (string) ( $raw['kurztext'] ?? '' ) ),
			'beschreibung'     => wp_kses_post( (string) ( $raw['beschreibung'] ?? '' ) ),
			'technische_daten' => $technische_daten,
			'preis_tag_cent'   => max( 0, (int) ( $raw['preis_tag_cent'] ?? 0 ) ),
			'preis_woche_cent' => max( 0, (int) ( $raw['preis_woche_cent'] ?? 0 ) ),
			'kaution_cent'     => max( 0, (int) ( $raw['kaution_cent'] ?? 0 ) ),
			'bilder'           => $bilder,
			'ist_kit'          => ! empty( $raw['ist_kit'] ),
			'kit_inhalt'       => $kit_inhalt,
		);
	}

	/**
	 * Fingerabdruck eines normalisierten Items, um unveränderte Einträge zu überspringen.
	 *
	 * @param array<string, mixed> $item Normalisiertes Item.
	 * @return string
	 */
	public static function fingerprint( array $item ) {
		return md5( (string) wp_json_encode( $item ) );
	}
}
