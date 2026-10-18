<?php
/**
 * Formatierung für die Ausgabe.
 *
 * @package CineRent\Shop
 */

namespace CineRent\Shop;

defined( 'ABSPATH' ) || exit;

/**
 * Preise, Listen und Mietdauer.
 */
final class Format {

	/**
	 * Cent-Betrag deutsch formatiert, z. B. 14900 → "149,00 €".
	 *
	 * @param int  $cent        Betrag in Cent.
	 * @param bool $with_symbol Mit Euro-Zeichen.
	 * @return string
	 */
	public static function price( $cent, $with_symbol = true ) {
		$value = number_format( ( (int) $cent ) / 100, 2, ',', '.' );
		return $with_symbol ? $value . "\u{00A0}€" : $value;
	}

	/**
	 * Technische Daten als Definitionsliste.
	 *
	 * @param array<int, array{label:string, wert:string}> $rows Zeilen.
	 * @return string HTML.
	 */
	public static function spec_list( array $rows ) {
		if ( ! $rows ) {
			return '';
		}
		$html = '<dl class="cr-specs">';
		foreach ( $rows as $row ) {
			$html .= '<div class="cr-specs__row"><dt>' . esc_html( $row['label'] ) . '</dt><dd>' . esc_html( $row['wert'] ) . '</dd></div>';
		}
		return $html . '</dl>';
	}

	/**
	 * Kit-Inhalt als Liste.
	 *
	 * @param array<int, array{name:string, menge:int}> $rows Zeilen.
	 * @return string HTML.
	 */
	public static function kit_list( array $rows ) {
		if ( ! $rows ) {
			return '';
		}
		$html = '<ul class="cr-kit">';
		foreach ( $rows as $row ) {
			$html .= '<li><span class="cr-kit__menge">' . esc_html( (int) $row['menge'] . '×' ) . '</span> <span class="cr-kit__name">' . esc_html( $row['name'] ) . '</span></li>';
		}
		return $html . '</ul>';
	}

	/**
	 * Mietdauer in Tagen. Abholung und Rückgabe am selben Tag zählen als ein Tag,
	 * sonst die Differenz der Kalendertage (Mo → Di = 1 Tag).
	 *
	 * @param string $von Y-m-d.
	 * @param string $bis Y-m-d.
	 * @return int
	 */
	public static function rental_days( $von, $bis ) {
		$start = date_create_immutable( $von, wp_timezone() );
		$end   = date_create_immutable( $bis, wp_timezone() );
		if ( ! $start || ! $end || $end < $start ) {
			return 0;
		}
		return max( 1, (int) $start->diff( $end )->days );
	}

	/**
	 * Unverbindlicher Richtwert: volle Wochen zum Wochenpreis, Resttage zum Tagespreis,
	 * höchstens jedoch eine weitere Woche.
	 *
	 * @param int $tag_cent   Preis pro Tag.
	 * @param int $woche_cent Preis pro Woche (0 = keiner).
	 * @param int $tage       Miettage.
	 * @return int Cent.
	 */
	public static function estimate( $tag_cent, $woche_cent, $tage ) {
		$tage = max( 0, (int) $tage );
		if ( $woche_cent <= 0 ) {
			return $tag_cent * $tage;
		}
		$wochen = intdiv( $tage, 7 );
		$rest   = $tage % 7;
		return $wochen * $woche_cent + min( $rest * $tag_cent, $woche_cent );
	}

	/**
	 * Datum deutsch, z. B. 2026-10-18 → 18.10.2026.
	 *
	 * @param string $ymd Y-m-d.
	 * @return string
	 */
	public static function date( $ymd ) {
		$date = date_create_immutable( $ymd, wp_timezone() );
		return $date ? wp_date( 'd.m.Y', $date->getTimestamp() ) : '';
	}
}
